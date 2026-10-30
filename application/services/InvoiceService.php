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
        $requestedWarehouse = isset($payload['warehouse_id']) ? NumericInput::integer($payload['warehouse_id']) : null;
        $warehouseId = $scopeWarehouse ?: $requestedWarehouse;
        if (!$warehouseId) { throw new ValidationException('Warehouse is required.'); }
        $customerId = NumericInput::integer($payload['customer_id'] ?? null);
        $userId = NumericInput::integer($userId);
        $lines = $payload['items'] ?? null;
        if (!is_array($lines) || !$lines || count($lines) > 500 || array_keys($lines) !== range(0, count($lines) - 1)) {
            throw new ValidationException('A list of one to 500 invoice items is required.');
        }
        $items = $this->aggregate($lines);
        $invoiceRate = NumericInput::hundredths($payload['discount_percent'] ?? '0', 10000);
        if (!$this->invoices->warehouseIsActive($warehouseId) || !$this->invoices->customerExists($customerId)) {
            throw new ValidationException('Selected warehouse or customer does not exist.');
        }
        return $this->invoices->numberedTransaction(function () use ($items, $customerId, $invoiceRate, $userId, $warehouseId): array {
            $products = [];
            foreach ($this->invoices->lockInventory($warehouseId, array_keys($items)) as $product) { $products[(int) $product['product_id']] = $product; }
            if (count($products) !== count($items)) { throw new ValidationException('One or more products are inactive or not stocked in this warehouse.'); }
            $snapshots = []; $subtotal = 0; $lineDiscount = 0;
            foreach ($items as $productId => $item) {
                $product = $products[$productId];
                if ($product['quantity'] < $item['quantity']) { throw new InsufficientStockException($product['name']); }
                $price = NumericInput::hundredths($product['price'], NumericInput::MAX_MONEY);
                if ($price > 0 && $item['quantity'] > intdiv(NumericInput::MAX_MONEY, $price)) { throw new ValidationException('Line amount exceeds storage limits.'); }
                $gross = $price * $item['quantity'];
                if ($subtotal > NumericInput::MAX_MONEY - $gross) { throw new ValidationException('Invoice amount exceeds storage limits.'); }
                $discount = $this->percentage($gross, $item['discount_rate']);
                $subtotal += $gross; $lineDiscount += $discount;
                $snapshots[] = ['product_id' => $productId, 'product_code' => $product['code'], 'product_name' => $product['name'], 'product_name_ar' => $product['name_ar'], 'unit' => $product['unit'], 'quantity' => $item['quantity'], 'unit_price' => NumericInput::decimal($price), 'discount_amount' => NumericInput::decimal($discount), 'line_total' => NumericInput::decimal($gross - $discount)];
                $this->invoices->decrement($warehouseId, $productId, $item['quantity']);
            }
            $invoiceDiscount = $this->percentage($subtotal - $lineDiscount, $invoiceRate);
            $totalDiscount = $lineDiscount + $invoiceDiscount;
            $id = $this->invoices->insertInvoice(['invoice_number' => $this->invoices->nextNumber(), 'warehouse_id' => $warehouseId, 'customer_id' => $customerId, 'created_by' => $userId, 'subtotal' => NumericInput::decimal($subtotal), 'discount_amount' => NumericInput::decimal($totalDiscount), 'total' => NumericInput::decimal($subtotal - $totalDiscount)], $snapshots);
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
        foreach ($lines as $line) {
            if (!is_array($line)) { throw new ValidationException('Invalid invoice item.'); }
            $id = NumericInput::integer($line['product_id'] ?? null);
            $quantity = NumericInput::integer($line['quantity'] ?? null, 1, NumericInput::MAX_QUANTITY);
            $rate = NumericInput::hundredths($line['discount_percent'] ?? '0', 10000);
            if (isset($result[$id])) {
                if ($result[$id]['discount_rate'] !== $rate) { throw new ValidationException('Duplicate product discounts must match.'); }
                if ($result[$id]['quantity'] > NumericInput::MAX_QUANTITY - $quantity) { throw new ValidationException('Combined quantity exceeds limits.'); }
                $result[$id]['quantity'] += $quantity;
            } else {
                $result[$id] = ['quantity' => $quantity, 'discount_rate' => $rate];
            }
        }
        ksort($result, SORT_NUMERIC);
        return $result;
    }

    private function percentage(int $amount, int $basisPoints): int
    {
        return intdiv($amount * $basisPoints + 5000, 10000);
    }
}
