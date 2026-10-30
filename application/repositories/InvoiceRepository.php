<?php
namespace App\Repositories;

class InvoiceRepository extends BaseRepository
{
    private $numberingLocked = false;
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
        $query = $this->db->where('id', $id);
        if ($warehouseId) { $query->where('warehouse_id', $warehouseId); }
        $invoice = $query->get('invoices')->row_array();
        if (!$invoice) { return null; }
        $creator = $this->db->select('name')->where('id', (int) $invoice['created_by'])->get('users')->row_array();
        $invoice['created_by_name'] = $creator['name'] ?? '';
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
