<?php
namespace App\Repositories;

class InvoiceRepository extends BaseRepository
{
    private $numberingLocked = false;

    /**
     * All-time issued invoice totals for an already authorized warehouse scope.
     * Null includes all warehouses; money stays a decimal string.
     *
     * @return array{count: int, total: string}
     */
    public function issuedSummaryForWarehouse(?int $warehouseId = null): array
    {
        $query = $this->db
            ->select('COUNT(*) AS invoice_count, COALESCE(SUM(total), 0.00) AS sales_total', false)
            ->where('status', 'issued');
        if ($warehouseId !== null) {
            $query->where('warehouse_id', $warehouseId);
        }
        $summary = $query->get('invoices')->row_array();

        return ['count' => (int) $summary['invoice_count'], 'total' => (string) $summary['sales_total']];
    }

    /** Hold the numbering lock until the complete invoice transaction commits. */
    public function numberedTransaction(callable $work)
    {
        return $this->withAdvisoryLock('invoice-number', function () use ($work) {
            $this->numberingLocked = true;
            try {
                return $this->transaction($work);
            } finally {
                $this->numberingLocked = false;
            }
        });
    }
    public function warehouseIsActive(int $warehouseId): bool
    {
        return $this->db->where(['id' => $warehouseId, 'status' => 'active'])->count_all_results('warehouses') === 1;
    }

    public function customerExists(int $customerId): bool
    {
        return $this->db->where('id', $customerId)->count_all_results('customers') === 1;
    }

    public function lockInventory(int $warehouseId, array $productIds): array
    {
        if (!$productIds) { return []; }
        $marks = implode(',', array_fill(0, count($productIds), '?'));
        $bindings = array_merge([$warehouseId], $productIds);
        return $this->db->query('SELECT inventory.product_id, inventory.quantity, products.code, products.name, products.name_ar, products.unit, products.price FROM inventory JOIN products ON products.id = inventory.product_id WHERE inventory.warehouse_id = ? AND inventory.product_id IN (' . $marks . ') AND products.status = "active" AND products.deleted_at IS NULL ORDER BY inventory.product_id ASC FOR UPDATE', $bindings)->result_array();
    }

    public function decrement(int $warehouseId, int $productId, int $quantity): void
    {
        $this->db->query('UPDATE inventory SET quantity = quantity - ? WHERE warehouse_id = ? AND product_id = ?', [$quantity, $warehouseId, $productId]);
    }

    public function nextNumber(): string
    {
        if (!$this->numberingLocked) { throw new \LogicException('Allocate invoice numbers inside numberedTransaction().'); }
        $prefix = $this->db->select('setting_value')->where('setting_key', 'invoice_prefix')->get('settings')->row_array();
        $prefix = preg_replace('/[^A-Z0-9]/', '', strtoupper((string) ($prefix['setting_value'] ?? 'INV')));
        $stem = ($prefix ?: 'INV') . '-' . date('Ymd') . '-';
        // Include cancelled invoices; COUNT()+1 can reuse numbers after gaps.
        // Call inside numberedTransaction(), never as an unlocked allocation.
        $row = $this->db->query(
            'SELECT COALESCE(MAX(CAST(SUBSTRING(invoice_number, ?) AS UNSIGNED)), 0) + 1 AS next_number FROM invoices WHERE invoice_number LIKE ? AND invoice_number REGEXP ?',
            [strlen($stem) + 1, $stem . '%', '^' . $stem . '[0-9]+$']
        )->row_array();
        return $stem . str_pad((string) $row['next_number'], 4, '0', STR_PAD_LEFT);
    }

    public function insertInvoice(array $invoice, array $items): int
    {
        $this->db->insert('invoices', $invoice);
        $id = (int) $this->db->insert_id();
        foreach ($items as $item) { $item['invoice_id'] = $id; $this->db->insert('invoice_items', $item); }
        return $id;
    }

    public function findScoped(int $id, ?int $warehouseId): ?array
    {
        $query = $this->db->select('invoices.*, customers.name AS customer_name, customers.phone AS customer_phone, warehouses.name AS warehouse_name, warehouses.name_ar AS warehouse_name_ar, warehouses.status AS warehouse_status, creator.name AS created_by_name, canceller.name AS cancelled_by_name')
            ->from('invoices')->join('customers', 'customers.id=invoices.customer_id')
            ->join('warehouses', 'warehouses.id=invoices.warehouse_id')
            ->join('users AS creator', 'creator.id=invoices.created_by', 'left')
            ->join('users AS canceller', 'canceller.id=invoices.cancelled_by', 'left')
            ->where('invoices.id', $id);
        if ($warehouseId) { $query->where('invoices.warehouse_id', $warehouseId); }
        $invoice = $query->get()->row_array();
        if (!$invoice) { return null; }
        $invoice['items'] = $this->db->where('invoice_id', $id)->order_by('product_id')->get('invoice_items')->result_array();
        return $invoice;
    }

    public function lockInvoiceScoped(int $id, ?int $warehouseId): ?array
    {
        $sql = 'SELECT * FROM invoices WHERE id = ?'; $bindings = [$id];
        if ($warehouseId) { $sql .= ' AND warehouse_id = ?'; $bindings[] = $warehouseId; }
        return $this->db->query($sql . ' FOR UPDATE', $bindings)->row_array();
    }

    public function restore(int $warehouseId, int $productId, int $quantity): void
    {
        $this->db->query('UPDATE inventory SET quantity = quantity + ? WHERE warehouse_id = ? AND product_id = ?', [$quantity, $warehouseId, $productId]);
    }

    public function lockInventoryRows(int $warehouseId, array $productIds): void
    {
        if (!$productIds) { return; }
        $marks = implode(',', array_fill(0, count($productIds), '?'));
        $this->db->query('SELECT product_id FROM inventory WHERE warehouse_id = ? AND product_id IN (' . $marks . ') ORDER BY product_id ASC FOR UPDATE', array_merge([$warehouseId], $productIds));
    }

    public function markCancelled(int $invoiceId, int $userId): void
    {
        $this->db->where('id', $invoiceId)->update('invoices', ['status' => 'cancelled', 'cancelled_at' => date('Y-m-d H:i:s'), 'cancelled_by' => $userId]);
    }
}
