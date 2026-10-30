<?php
namespace App\Services;

use App\Exceptions\InsufficientStockException;
use App\Exceptions\UnauthorizedException;
use App\Exceptions\ValidationException;
use App\Repositories\InvoiceRepository;

class InvoiceService
{
    private $invoices;

    public function __construct(InvoiceRepository $invoices)
    {
        $this->invoices = $invoices;
    }

    public function create(array $payload, int $userId, ?int $scopeWarehouse): array
    {
        $warehouseId = $scopeWarehouse ?: (int) ($payload['warehouse_id'] ?? 0);
        if (!$warehouseId || empty($payload['customer_id']) || empty($payload['items']) || !is_array($payload['items'])) { throw new ValidationException('Warehouse, customer and at least one item are required.'); }
        if (!$this->invoices->warehouseIsActive($warehouseId) || !$this->invoices->customerExists((int) $payload['customer_id'])) { throw new ValidationException('Selected warehouse or customer does not exist.'); }
        $items = $this->aggregate($payload['items']);
        return $this->invoices->numberedTransaction(function () use ($items, $payload, $userId, $warehouseId): array {
            $products = [];
            foreach ($this->invoices->lockInventory($warehouseId, array_keys($items)) as $product) { $products[(int) $product['product_id']] = $product; }
            if (count($products) !== count($items)) { throw new ValidationException('One or more products are inactive or not stocked in this warehouse.'); }
            $snapshots = []; $subtotal = 0; $lineDiscount = 0;
            foreach ($items as $productId => $item) {
                $product = $products[$productId];
                if ($product['quantity'] < $item['quantity']) { throw new InsufficientStockException($product['name']); }
                $price = $this->halalas($product['price']); $gross = $price * $item['quantity']; $discount = $this->percentage($gross, $item['discount_percent']);
                $subtotal += $gross; $lineDiscount += $discount;
                $snapshots[] = ['product_id' => $productId, 'product_code' => $product['code'], 'product_name' => $product['name'], 'product_name_ar' => $product['name_ar'], 'unit' => $product['unit'], 'quantity' => $item['quantity'], 'unit_price' => $this->decimal($price), 'discount_amount' => $this->decimal($discount), 'line_total' => $this->decimal($gross - $discount)];
                $this->invoices->decrement($warehouseId, $productId, $item['quantity']);
            }
            $invoiceDiscount = $this->percentage($subtotal - $lineDiscount, (string) ($payload['discount_percent'] ?? '0'));
            $totalDiscount = $lineDiscount + $invoiceDiscount;
            $id = $this->invoices->insertInvoice(['invoice_number' => $this->invoices->nextNumber(), 'warehouse_id' => $warehouseId, 'customer_id' => (int) $payload['customer_id'], 'created_by' => $userId, 'subtotal' => $this->decimal($subtotal), 'discount_amount' => $this->decimal($totalDiscount), 'total' => $this->decimal($subtotal - $totalDiscount)], $snapshots);
            return $this->invoices->findScoped($id, $warehouseId);
        });
    }

    public function cancel(int $invoiceId, int $userId, ?int $scopeWarehouse): void
    {
        $this->invoices->transaction(function () use ($invoiceId, $userId, $scopeWarehouse): void {
            $invoice = $this->invoices->lockInvoiceScoped($invoiceId, $scopeWarehouse);
            if (!$invoice) { throw new UnauthorizedException('Invoice was not found in your warehouse scope.'); }
            if ($invoice['status'] === 'cancelled') { throw new ValidationException('Invoice has already been cancelled.'); }
            $items = $this->invoices->findScoped($invoiceId, $scopeWarehouse)['items'];
            $productIds = array_column($items, 'product_id'); sort($productIds, SORT_NUMERIC);
            $this->invoices->lockInventoryRows((int) $invoice['warehouse_id'], $productIds);
            foreach ($items as $item) { $this->invoices->restore((int) $invoice['warehouse_id'], (int) $item['product_id'], (int) $item['quantity']); }
            $this->invoices->markCancelled($invoiceId, $userId);
        });
    }

    private function aggregate(array $lines): array
    {
        $result = [];
        foreach ($lines as $line) { $id = (int) ($line['product_id'] ?? 0); $quantity = (int) ($line['quantity'] ?? 0); if (!$id || $quantity < 1) { throw new ValidationException('Each item requires a positive quantity.'); } if (!isset($result[$id])) { $result[$id] = ['quantity' => 0, 'discount_percent' => (string) ($line['discount_percent'] ?? '0')]; } $result[$id]['quantity'] += $quantity; }
        ksort($result, SORT_NUMERIC); return $result;
    }

    private function halalas(string $amount): int { return $this->fixedInt($amount, 2); }
    private function decimal(int $halalas): string { return number_format($halalas / 100, 2, '.', ''); }
    private function percentage(int $amount, string $percentage): int { $basisPoints = $this->fixedInt($percentage, 2); if ($basisPoints < 0 || $basisPoints > 10000) { throw new ValidationException('Discount must be between 0 and 100.'); } return intdiv($amount * $basisPoints + 5000, 10000); }
    private function fixedInt(string $value, int $scale): int
    {
        if (!preg_match('/^([0-9]+)(?:\.([0-9]+))?$/', trim($value), $parts)) { throw new ValidationException('Invalid monetary value.'); }
        $fraction = $parts[2] ?? ''; $kept = substr(str_pad($fraction, $scale, '0'), 0, $scale); $result = ((int) $parts[1] * (10 ** $scale)) + (int) $kept;
        return isset($fraction[$scale]) && (int) $fraction[$scale] >= 5 ? $result + 1 : $result;
    }
}
