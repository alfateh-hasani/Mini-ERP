<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Cli extends CI_Controller
{
    public function migrate(): void
    {
        $this->cliOnly();
        $this->load->library('migration');
        if (!$this->migration->latest()) {
            echo $this->migration->error_string() . PHP_EOL;
            return;
        }
        echo "Migration completed.\n";
    }

    /** Creates or backfills safe demo catalogue data without duplicating it. */
    public function seed(): void
    {
        $this->cliOnly();
        $this->load->database();

        $warehouseId = $this->firstOrCreate('warehouses', ['name' => 'Riyadh'], [
            'name' => 'Riyadh', 'name_ar' => 'الرياض', 'address' => 'Riyadh', 'status' => 'active',
        ]);
        $warehouseIds = [
            'Riyadh' => $warehouseId,
            'Jeddah' => $this->firstOrCreate('warehouses', ['name' => 'Jeddah'], ['name' => 'Jeddah', 'name_ar' => 'جدة', 'address' => 'Jeddah', 'status' => 'active']),
            'Dammam' => $this->firstOrCreate('warehouses', ['name' => 'Dammam'], ['name' => 'Dammam', 'name_ar' => 'الدمام', 'address' => 'Dammam', 'status' => 'active']),
        ];
        $categoryIds = [];
        foreach ([
            'General' => 'عام', 'Electronics' => 'إلكترونيات', 'Office Supplies' => 'مستلزمات مكتبية',
            'Cleaning' => 'منظفات', 'Food & Beverage' => 'أغذية ومشروبات',
        ] as $name => $nameAr) {
            $categoryIds[$name] = $this->firstOrCreate('categories', ['name' => $name], ['name' => $name, 'name_ar' => $nameAr, 'status' => 'active']);
        }
        $adminId = $this->firstOrCreate('users', ['email' => 'test@smartlife.com'], [
            'name' => 'Administrator', 'email' => 'test@smartlife.com',
            'password_hash' => password_hash('87654321', PASSWORD_BCRYPT, ['cost' => 12]),
            'role' => 'admin', 'preferred_lang' => 'ar', 'status' => 'active',
        ]);
        $this->firstOrCreate('users', ['email' => 'ahmed@smartlife.com'], [
            'name' => 'Riyadh Warehouse', 'email' => 'ahmed@smartlife.com',
            'password_hash' => password_hash('pass@1234', PASSWORD_BCRYPT, ['cost' => 12]),
            'role' => 'user_warehouse', 'warehouse_id' => $warehouseIds['Riyadh'],
            'preferred_lang' => 'ar', 'status' => 'active',
        ]);
        $this->firstOrCreate('users', ['email' => 'sara@smartlife.com'], [
            'name' => 'Jeddah Warehouse', 'email' => 'sara@smartlife.com',
            'password_hash' => password_hash('pass@1234', PASSWORD_BCRYPT, ['cost' => 12]),
            'role' => 'user_warehouse', 'warehouse_id' => $warehouseIds['Jeddah'],
            'preferred_lang' => 'en', 'status' => 'active',
        ]);
        $this->firstOrCreate('users', ['email' => 'khalid@smartlife.com'], [
            'name' => 'Dammam Warehouse', 'email' => 'khalid@smartlife.com',
            'password_hash' => password_hash('pass@1234', PASSWORD_BCRYPT, ['cost' => 12]),
            'role' => 'user_warehouse', 'warehouse_id' => $warehouseIds['Dammam'],
            'preferred_lang' => 'ar', 'status' => 'active',
        ]);

        $products = [
            ['ELEC-001', 'Wireless Keyboard', 'لوحة مفاتيح لاسلكية', 'piece', 'قطعة', '120.00', 5, 45, 'Electronics'],
            ['ELEC-002', 'Wireless Mouse', 'فأرة لاسلكية', 'piece', 'قطعة', '75.00', 5, 30, 'Electronics'],
            ['OFF-001', 'A4 Copy Paper', 'ورق تصوير A4', 'ream', 'رزمة', '24.50', 10, 80, 'Office Supplies'],
            ['CLEAN-001', 'Hand Sanitizer', 'معقم يدين', 'bottle', 'عبوة', '18.00', 8, 24, 'Cleaning'],
            ['FOOD-001', 'Bottled Water', 'مياه معبأة', 'pack', 'عبوة', '12.00', 10, 6, 'Food & Beverage'],
            ['ELEC-003', 'USB-C Cable', 'كابل يو إس بي سي', 'piece', 'قطعة', '35.00', 10, 55, 'Electronics'],
            ['ELEC-004', 'Laptop Stand', 'حامل حاسوب محمول', 'piece', 'قطعة', '95.00', 4, 18, 'Electronics'],
            ['OFF-002', 'Blue Ballpoint Pen', 'قلم حبر أزرق', 'box', 'صندوق', '16.00', 8, 40, 'Office Supplies'],
            ['OFF-003', 'Stapler', 'دباسة', 'piece', 'قطعة', '28.00', 5, 15, 'Office Supplies'],
            ['CLEAN-002', 'Surface Cleaner', 'منظف أسطح', 'bottle', 'زجاجة', '22.00', 6, 20, 'Cleaning'],
            ['FOOD-003', 'Instant Coffee', 'قهوة سريعة التحضير', 'pack', 'عبوة', '29.00', 5, 12, 'Food & Beverage'],
            ['FOOD-004', 'Instant Coffee', 'قهوة سريعة التحضير', 'pack', 'عبوة', '29.00', 5, 12, 'Food & Beverage'],
            ['FOOD-005', 'Instant Coffee', 'قهوة سريعة التحضير', 'pack', 'عبوة', '29.00', 5, 12, 'Food & Beverage'],
            ['FOOD-006', 'Instant Coffee', 'قهوة سريعة التحضير', 'pack', 'عبوة', '29.00', 5, 12, 'Food & Beverage'],
        ];
        for ($number = 1; $number <= 16; $number++) {
            $products[] = [sprintf('GEN-%03d', $number), 'General Item ' . $number, 'منتج عام ' . $number, 'piece', 'قطعة', number_format(10 + $number, 2, '.', ''), 5, 20 + $number, 'General'];
        }
        foreach ($products as $product) {
            $productId = $this->firstOrCreate('products', ['code' => $product[0]], [
                'category_id' => $categoryIds[$product[8]], 'code' => $product[0], 'name' => $product[1], 'name_ar' => $product[2],
                'unit' => $product[3], 'unit_ar' => $product[4], 'price' => $product[5],
                'alert_quantity' => $product[6], 'status' => 'active', 'created_by' => $adminId,
            ]);
            $this->db->where('id', $productId)->update('products', ['category_id' => $categoryIds[$product[8]]]);
            foreach ($warehouseIds as $city => $cityWarehouseId) {
                $quantity = $city === 'Riyadh' ? $product[7] : ($city === 'Jeddah' ? max(0, $product[7] - 8) : $product[7] + 12);
                $this->firstOrCreate('inventory', ['warehouse_id' => $cityWarehouseId, 'product_id' => $productId], [
                    'warehouse_id' => $cityWarehouseId, 'product_id' => $productId, 'quantity' => $quantity,
                ]);
            }
        }

        for ($number = 1; $number <= 15; $number++) {
            $this->firstOrCreate('customers', ['email' => 'customer' . $number . '@example.test'], ['name' => 'Demo Customer ' . $number, 'phone' => '+966500000' . str_pad((string) $number, 3, '0', STR_PAD_LEFT), 'email' => 'customer' . $number . '@example.test', 'address' => array_keys($warehouseIds)[($number - 1) % 3], 'created_by' => $adminId]);
        }
        $this->seedInvoices($adminId, $warehouseIds['Riyadh']);
        echo "Demo data is ready: 30 products, 90 inventory rows, 3 warehouses, 4 users, 15 customers and 20 invoices are available.\n";
    }

    private function seedInvoices(int $adminId, int $warehouseId): void
    {
        if ($this->db->count_all('invoices') > 0) { return; }
        $customerIds = array_column($this->db->order_by('id')->limit(15)->get('customers')->result_array(), 'id');
        $productIds = array_column($this->db->where('warehouse_id', $warehouseId)->where('quantity >', 0)->order_by('product_id')->limit(30)->get('inventory')->result_array(), 'product_id');
        if (!$customerIds || !$productIds) { return; }
        $service = new \App\Services\InvoiceService(new \App\Repositories\InvoiceRepository($this->db));
        $created = [];
        for ($number = 0; $number < 20; $number++) { $created[] = $service->create(['warehouse_id'=>$warehouseId, 'customer_id'=>$customerIds[$number % count($customerIds)], 'items'=>[['product_id'=>$productIds[$number % count($productIds)], 'quantity'=>1, 'discount_percent'=>'0']]], $adminId, $warehouseId); }
        foreach (array_slice($created, 0, 2) as $invoice) { $service->cancel((int) $invoice['id'], $adminId, $warehouseId); }
    }

    private function firstOrCreate(string $table, array $where, array $values): int
    {
        $row = $this->db->where($where)->get($table)->row_array();
        if ($row) {
            return (int) $row['id'];
        }
        $this->db->insert($table, $values);
        return (int) $this->db->insert_id();
    }

    private function cliOnly(): void
    {
        if (!is_cli()) {
            show_404();
        }
    }
}
