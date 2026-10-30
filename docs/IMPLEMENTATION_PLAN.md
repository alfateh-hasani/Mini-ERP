# Mini ERP — Stocks & Sales
## Master Implementation Plan v3.0

> **Framework:** CodeIgniter 3.1.13 · **PHP:** 7.4+ · **DB:** MySQL 8.0
> **Pattern:** Service-Repository · **UI:** Tabler (Bootstrap 5, RTL native)
> **Project DB:** `mini_erp_smart_life` · **Base URL:** `http://localhost/mini-erp/`

---

## ● CRITICAL REVIEW — Gaps Found in v2.0

> Reading the plan fresh as a Senior Engineer. Below are all issues and omissions found:

| # | Gap | Impact | Fix in v3.0 |
|---|-----|--------|-------------|
| 1 | No `ci_sessions` DB table | CI3 `sess_use_database=TRUE` will crash | Added migration 012 |
| 2 | No `.htaccess` to remove `index.php` from URL | All routes include `/index.php/` — looks amateur | Added setup step + rewrite rules |
| 3 | No `public/` assets folder structure | Assets will be served from inside `application/` | Added `public/` folder spec |
| 4 | UI was AdminLTE 3 (Bootstrap 4, old) | Research shows **Tabler** is superior for this stack | **Switched to Tabler 1.x (Bootstrap 5, RTL native)** |
| 5 | No ResponseBuilder JSON contract defined | Agent will invent its own format → inconsistency | Exact JSON schema defined |
| 6 | No form validation rules documented | Every form will be validated differently | Full validation rules per form |
| 7 | No AJAX product search response contract | The invoice JS won't know what JSON to expect | Contract defined in §13 |
| 8 | No DataTables server-side request/response | Agent will implement wrong pagination | Full DataTables contract defined |
| 9 | Invoice number sequence not defined | Per-day auto-increment logic unspecified | Full algorithm in §12 |
| 10 | Cancel invoice → no stock restoration rule | Business rule missing — what happens to stock? | Rule: **restore stock on cancel** |
| 11 | No `MY_Controller::render()` method signature | Agent won't know how layout composition works | Full method documented |
| 12 | No CSRF on AJAX details | JavaScript contract missing | JS snippet in §6 |
| 13 | No flash message → SweetAlert2 bridge | Alert system works on redirect only if coded | Pattern documented |
| 14 | No sidebar menu spec | Agent will guess menu structure | Full sidebar spec for each role |
| 15 | No `composer.json` full content | Agent will install wrong package versions | Full `composer.json` in §2 |
| 16 | No `.gitignore` spec | `/vendor`, `/logs`, `.env` will be committed | `.gitignore` in §18 |
| 17 | No `README.md` spec | Setup steps for junior joining project don't exist | README spec in §18 |
| 18 | No exception classes defined | Agent will use generic `Exception` everywhere | Custom exception hierarchy |
| 19 | No `ci_sessions` table | Session DB fails silently | Session table SQL added |
| 20 | Git strategy entirely missing | No branching, no commits, looks like amateur work | **Full Git strategy in §20** |

---

## Confirmed Configuration

| Setting | Value |
|---------|-------|
| Database Name | `mini_erp_smart_life` |
| Base URL | `http://localhost/mini-erp/` |
| Admin Email | `test@smartlife.com` |
| Admin Password | `87654321` (bcrypt cost 12) |
| Currencies | SAR · USD · YER |
| Languages | Arabic (RTL) · English (LTR) |
| Max Login Attempts | 5 per 15 minutes per IP |
| user_warehouse → Create Customers | ✅ Yes (duplicate check: phone + email) |
| Cancel Invoice → Stock | ✅ Restore quantity to warehouse |

---

## 1. UI Template Decision — Tabler (Final Choice)

### Why Tabler Instead of AdminLTE

| Criterion | Tabler 1.x | AdminLTE 3.x |
|-----------|-----------|-------------|
| Bootstrap version | **5.3** (modern) | 4.6 (aging) |
| jQuery dependency | **None** (Vanilla JS) | Required |
| RTL support | **Native built-in CSS** (`tabler.rtl.min.css`) | Requires patch work |
| Design quality | **Premium, modern** | Classic, corporate |
| GitHub stars | 41,000+ | 45,000+ |
| Dark mode | **Built-in** | Plugin needed |
| License | **MIT** | MIT |
| Active development | ✅ 2024-2025 | ✅ v4 in progress |
| ERP-specific components | Cards, stats, tables, modals | Same |

**Verdict:** Tabler wins on RTL nativity, modern aesthetics, no jQuery pollution, and Bootstrap 5. For an ERP that must impress reviewers, Tabler's visual quality is noticeably superior.

### Tabler CDN / Local Setup
```
public/
└── assets/
    ├── libs/
    │   ├── tabler/
    │   │   ├── css/
    │   │   │   ├── tabler.min.css          ← LTR
    │   │   │   └── tabler.rtl.min.css      ← RTL (auto-loaded by lang)
    │   │   └── js/
    │   │       └── tabler.min.js
    │   ├── datatables/
    │   │   ├── datatables.min.css
    │   │   └── datatables.min.js
    │   ├── select2/
    │   │   ├── select2.min.css
    │   │   └── select2.min.js
    │   └── sweetalert2/
    │       └── sweetalert2.all.min.js
    ├── css/
    │   ├── app.css                         ← custom LTR overrides
    │   └── app.rtl.css                     ← custom RTL overrides
    └── js/
        ├── app.js                          ← global helpers, CSRF setup
        └── invoice.js                      ← invoice page logic
```

---

## 2. Complete `composer.json`

```json
{
    "name": "smartlife/mini-erp",
    "description": "Mini ERP — Stocks & Sales — SmartLife",
    "type": "project",
    "license": "proprietary",
    "require": {
        "php": ">=7.4",
        "league/csv": "^9.10"
    },
    "require-dev": {
        "phpunit/phpunit": "^9.6",
        "fakerphp/faker": "^1.23",
        "phpstan/phpstan": "^1.11",
        "squizlabs/php_codesniffer": "^3.10",
        "friendsofphp/php-cs-fixer": "^3.58",
        "phpmd/phpmd": "^2.15"
    },
    "autoload": {
        "psr-4": {
            "App\\Repositories\\": "application/repositories/",
            "App\\Services\\": "application/services/",
            "App\\Exceptions\\": "application/exceptions/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "Tests\\": "application/tests/"
        }
    },
    "scripts": {
        "test":  "phpunit --configuration phpunit.xml --colors=always",
        "lint":  "phpcs --standard=.phpcs.xml --colors application/",
        "fix":   "php-cs-fixer fix application/ --config=.php-cs-fixer.php",
        "stan":  "phpstan analyse --configuration phpstan.neon --memory-limit=256M",
        "mess":  "phpmd application/ ansi phpmd.xml",
        "check": ["@lint", "@stan", "@mess", "@test"],
        "seed":  "php index.php cli/seed run"
    },
    "config": {
        "sort-packages": true,
        "optimize-autoloader": true
    }
}
```

---

## 3. Project Setup Files

### `.htaccess` (project root) — Remove `index.php` from URL
```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php/$1 [L]

# Security headers
Header always set X-Frame-Options "DENY"
Header always set X-Content-Type-Options "nosniff"
Header always set X-XSS-Protection "1; mode=block"
Header always set Referrer-Policy "strict-origin-when-cross-origin"
```

### `application/.htaccess` — Deny direct access
```apache
Deny from all
```

### `index.php` — Set environment
```php
define('ENVIRONMENT', isset($_SERVER['CI_ENV']) ? $_SERVER['CI_ENV'] : 'development');
```

### `ci_sessions` Migration (migration 012)
```sql
CREATE TABLE ci_sessions (
    id          VARCHAR(128) NOT NULL,
    ip_address  VARCHAR(45)  NOT NULL,
    timestamp   INT UNSIGNED NOT NULL DEFAULT 0,
    data        BLOB         NOT NULL,
    KEY ci_sessions_timestamp (timestamp)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

## 4. Full Directory Structure

```
mini-erp/                         ← project root
├── .gitignore
├── .htaccess                     ← remove index.php, security headers
├── .phpcs.xml
├── .php-cs-fixer.php
├── phpunit.xml
├── phpstan.neon
├── phpmd.xml
├── composer.json
├── composer.lock
├── index.php                     ← CI3 front controller
├── IMPLEMENTATION_PLAN.md
├── README.md
│
├── public/                       ← all public static assets
│   └── assets/ ...               ← (see §1)
│
├── system/                       ← CI3 core (never modify)
│
└── application/
    ├── config/
    │   ├── autoload.php
    │   ├── config.php
    │   ├── constants.php
    │   ├── database.php
    │   ├── hooks.php
    │   ├── migration.php
    │   └── routes.php
    │
    ├── core/
    │   ├── MY_Controller.php
    │   ├── MY_Model.php
    │   └── MY_Exceptions.php
    │
    ├── exceptions/               ← Custom exception classes
    │   ├── InsufficientStockException.php
    │   ├── DuplicateCustomerException.php
    │   ├── UnauthorizedException.php
    │   └── ValidationException.php
    │
    ├── controllers/
    │   ├── Auth.php
    │   ├── admin/
    │   │   ├── Dashboard.php
    │   │   ├── Products.php
    │   │   ├── Categories.php
    │   │   ├── Warehouses.php
    │   │   ├── Customers.php
    │   │   ├── Inventory.php
    │   │   ├── Invoices.php
    │   │   ├── Reports.php
    │   │   └── Settings.php
    │   └── warehouse/
    │       ├── Dashboard.php
    │       ├── Customers.php
    │       ├── Inventory.php
    │       ├── Invoices.php
    │       └── Reports.php
    │
    ├── models/
    │   ├── User_model.php
    │   ├── Product_model.php
    │   ├── Category_model.php
    │   ├── Warehouse_model.php
    │   ├── Customer_model.php
    │   ├── Inventory_model.php
    │   ├── Invoice_model.php
    │   ├── Invoice_item_model.php
    │   ├── Currency_model.php
    │   └── Setting_model.php
    │
    ├── repositories/
    │   ├── contracts/
    │   │   ├── BaseRepositoryInterface.php
    │   │   ├── ProductRepositoryInterface.php
    │   │   ├── InventoryRepositoryInterface.php
    │   │   ├── InvoiceRepositoryInterface.php
    │   │   ├── CustomerRepositoryInterface.php
    │   │   └── CurrencyRepositoryInterface.php
    │   ├── BaseRepository.php
    │   ├── ProductRepository.php
    │   ├── CategoryRepository.php
    │   ├── WarehouseRepository.php
    │   ├── InventoryRepository.php
    │   ├── InvoiceRepository.php
    │   ├── CustomerRepository.php
    │   └── CurrencyRepository.php
    │
    ├── services/
    │   ├── AuthService.php
    │   ├── ProductService.php
    │   ├── InventoryService.php
    │   ├── InvoiceService.php
    │   ├── CustomerService.php
    │   ├── ReportService.php
    │   ├── CurrencyService.php
    │   └── LocaleService.php
    │
    ├── libraries/
    │   ├── Auth_lib.php
    │   ├── ResponseBuilder.php
    │   └── CsvExporter.php
    │
    ├── helpers/
    │   ├── format_helper.php
    │   ├── auth_helper.php
    │   └── string_helper_ext.php
    │
    ├── hooks/
    │   ├── AuthHook.php
    │   └── LangHook.php
    │
    ├── language/
    │   ├── arabic/
    │   │   ├── app_lang.php
    │   │   └── validation_lang.php
    │   └── english/
    │       ├── app_lang.php
    │       └── validation_lang.php
    │
    ├── migrations/
    │   ├── 001_create_currencies_table.php
    │   ├── 002_create_settings_table.php
    │   ├── 003_create_warehouses_table.php
    │   ├── 004_create_categories_table.php
    │   ├── 005_create_users_table.php
    │   ├── 006_create_login_attempts_table.php
    │   ├── 007_create_products_table.php
    │   ├── 008_create_inventory_table.php
    │   ├── 009_create_customers_table.php
    │   ├── 010_create_invoices_table.php
    │   ├── 011_create_invoice_items_table.php
    │   └── 012_create_ci_sessions_table.php
    │
    ├── seeds/
    │   ├── DatabaseSeeder.php
    │   ├── CurrencySeeder.php
    │   ├── SettingSeeder.php
    │   ├── WarehouseSeeder.php
    │   ├── CategorySeeder.php
    │   ├── UserSeeder.php
    │   ├── ProductSeeder.php
    │   ├── InventorySeeder.php
    │   ├── CustomerSeeder.php
    │   └── InvoiceSeeder.php
    │
    ├── views/
    │   ├── layouts/
    │   │   ├── app.php
    │   │   ├── auth.php
    │   │   └── partials/
    │   │       ├── head.php
    │   │       ├── navbar.php
    │   │       ├── sidebar.php
    │   │       └── footer_scripts.php
    │   ├── auth/
    │   │   └── login.php
    │   ├── products/
    │   │   ├── index.php
    │   │   ├── create.php
    │   │   └── edit.php
    │   ├── inventory/
    │   │   └── index.php
    │   ├── invoices/
    │   │   ├── index.php
    │   │   ├── create.php
    │   │   └── show.php
    │   ├── customers/
    │   │   ├── index.php
    │   │   ├── create.php
    │   │   └── edit.php
    │   ├── reports/
    │   │   └── low_stock.php
    │   ├── settings/
    │   │   └── index.php
    │   └── errors/
    │       ├── 403.php
    │       └── 404.php
    │
    └── tests/
        ├── bootstrap.php
        ├── unit/
        │   ├── services/
        │   │   ├── InvoiceServiceTest.php
        │   │   ├── ProductServiceTest.php
        │   │   ├── CustomerServiceTest.php
        │   │   └── ReportServiceTest.php
        │   └── helpers/
        │       └── FormatHelperTest.php
        └── integration/
            └── InvoiceFlowTest.php
```

---

## 5. Custom Exception Hierarchy

```php
// Base
class AppException extends RuntimeException {}

// Domain exceptions
class InsufficientStockException extends AppException
{
    public function __construct(string $productName)
    {
        parent::__construct("Insufficient stock for: {$productName}");
    }
}

class DuplicateCustomerException extends AppException
{
    public function __construct(string $field)
    {
        parent::__construct("Customer with this {$field} already exists");
    }
}

class UnauthorizedException extends AppException {}
class ValidationException extends AppException {}
```

---

## 6. Core Patterns

### `MY_Controller::render()` — Layout Composition
```php
protected function render(string $view, array $data = [], string $layout = 'app'): void
{
    $data['lang']          = $this->session->userdata('lang') ?? 'ar';
    $data['dir']           = $data['lang'] === 'ar' ? 'rtl' : 'ltr';
    $data['current_user']  = $this->auth_lib->getUser();
    $data['active_currency'] = $this->session->userdata('currency_code') ?? 'SAR';
    $data['flash']         = $this->session->flashdata('flash');

    $this->load->view("layouts/partials/head", $data);
    $this->load->view("layouts/partials/navbar", $data);
    $this->load->view("layouts/partials/sidebar", $data);
    $this->load->view($view, $data);
    $this->load->view("layouts/partials/footer_scripts", $data);
}
```

### Flash Message → SweetAlert2 Bridge
```php
// Controller sets flash:
$this->session->set_flashdata('flash', [
    'type'    => 'success',   // success | error | warning | info
    'message' => lang('invoice_saved'),
]);
redirect('admin/invoices');
```

```javascript
// footer_scripts.php injects flash as JS:
<?php if ($flash): ?>
Swal.fire({
    icon: '<?= $flash['type'] ?>',
    title: '<?= htmlspecialchars($flash['message']) ?>',
    toast: true,
    position: '<?= $lang === "ar" ? "top-start" : "top-end" ?>',
    showConfirmButton: false,
    timer: 3500,
    timerProgressBar: true,
});
<?php endif; ?>
```

### CSRF Setup for All AJAX Requests (`app.js`)
```javascript
// Set up CSRF for all jQuery/fetch AJAX requests
const csrfName  = '<?= $this->security->get_csrf_token_name() ?>';
const csrfHash  = '<?= $this->security->get_csrf_hash() ?>';

// Axios / fetch interceptor pattern:
document.addEventListener('DOMContentLoaded', () => {
    // Attach to all fetch calls
    const originalFetch = window.fetch;
    window.fetch = (url, options = {}) => {
        options.headers = options.headers || {};
        options.headers['X-CSRF-Token'] = csrfHash;
        return originalFetch(url, options);
    };
});
```

---

## 7. ResponseBuilder JSON Contract

Every AJAX endpoint returns **exactly** this structure:

```json
// ── Success (single object)
{
  "success": true,
  "data": { ... },
  "message": "Invoice saved successfully"
}

// ── Success (list with pagination)
{
  "success": true,
  "data": [ ... ],
  "pagination": {
    "total": 150,
    "per_page": 20,
    "current_page": 2,
    "last_page": 8
  }
}

// ── Error
{
  "success": false,
  "error": {
    "code": "INSUFFICIENT_STOCK",
    "message": "Insufficient stock for: Wireless Keyboard",
    "field": null
  }
}

// ── Validation Error
{
  "success": false,
  "error": {
    "code": "VALIDATION_FAILED",
    "message": "Validation failed",
    "fields": {
      "name": "Name is required",
      "phone": "Phone number already registered"
    }
  }
}
```

### Error Codes
| Code | Meaning |
|------|---------|
| `INSUFFICIENT_STOCK` | Qty requested > qty available |
| `DUPLICATE_CUSTOMER` | Phone or email already registered |
| `VALIDATION_FAILED` | Form validation errors |
| `UNAUTHORIZED` | Role not allowed |
| `NOT_FOUND` | Record doesn't exist |
| `INTERNAL_ERROR` | Catch-all server error |

---

## 8. Form Validation Rules (All Forms)

### Login
```php
['field' => 'email',    'rules' => 'required|valid_email|max_length[150]'],
['field' => 'password', 'rules' => 'required|min_length[6]|max_length[100]'],
```

### Product Create/Edit
```php
['field' => 'code',           'rules' => 'required|alpha_dash|max_length[50]|is_unique[products.code,id,{id}]'],
['field' => 'name',           'rules' => 'required|max_length[200]'],
['field' => 'name_ar',        'rules' => 'required|max_length[200]'],
['field' => 'category_id',    'rules' => 'required|is_natural_no_zero'],
['field' => 'unit',           'rules' => 'required|max_length[30]'],
['field' => 'price',          'rules' => 'required|decimal|greater_than[0]'],
['field' => 'alert_quantity', 'rules' => 'required|is_natural'],
```

### Customer Create
```php
['field' => 'name',  'rules' => 'required|max_length[150]'],
['field' => 'phone', 'rules' => 'permit_empty|max_length[30]'],
['field' => 'email', 'rules' => 'permit_empty|valid_email|max_length[150]'],
// Uniqueness checked in CustomerService (not CI3 is_unique — service gives better error msg)
```

### Invoice Store (JSON body validated in service)
```php
// Checked in InvoiceService::validatePayload()
- customer_id: required, integer, exists in customers
- warehouse_id: required, integer, exists in warehouses, user has access
- discount_percent: 0–100, decimal
- items: array, min 1 item
- items[].product_id: required, integer, active product
- items[].quantity: required, integer >= 1
- items[].discount_percent: 0–100, decimal
```

---

## 9. DataTables Server-Side Contract

### Request (from DataTables JS → server)
```
GET /admin/products?draw=1&start=0&length=20&search[value]=keyboard&order[0][column]=2&order[0][dir]=asc
Optional: ?category_id=1&status=active
```

### Response (server → DataTables)
```json
{
  "draw": 1,
  "recordsTotal": 150,
  "recordsFiltered": 12,
  "data": [
    {
      "id": 5,
      "code": "ELEC-001",
      "name": "Wireless Keyboard",
      "name_ar": "لوحة مفاتيح لاسلكية",
      "category": "Electronics",
      "price": "120.00",
      "price_formatted": "ر.س 120.00",
      "alert_quantity": 5,
      "status": "active",
      "actions": "<a href='/admin/products/edit/5'>...</a>"
    }
  ]
}
```

---

## 10. Product Search AJAX Contract

Used by **Select2** on the invoice creation page:

```
GET /admin/products/search?q=key&warehouse_id=1
```

```json
{
  "results": [
    {
      "id": 5,
      "text": "ELEC-001 — Wireless Keyboard",
      "code": "ELEC-001",
      "name": "Wireless Keyboard",
      "name_ar": "لوحة مفاتيح لاسلكية",
      "price": 120.00,
      "available_qty": 45,
      "unit": "piece",
      "unit_ar": "قطعة"
    }
  ]
}
```

---

## 11. Sidebar Menu Specification

### Admin Role Menu
```
🏠 Dashboard
📦 Products
   ├── All Products
   ├── Add Product
   └── Categories
🏭 Inventory
   └── Stock Overview (all warehouses)
👥 Customers
   ├── All Customers
   └── Add Customer
🧾 Invoices
   ├── All Invoices
   └── Create Invoice
📊 Reports
   └── Low Stock Alert
🔧 Settings
   ├── App Settings
   ├── Currencies
   └── Warehouses
👤 Users (admin only)
```

### user_warehouse Role Menu
```
🏠 Dashboard
🏭 Inventory
   └── My Warehouse Stock
👥 Customers
   ├── Customer List
   └── Add Customer
🧾 Invoices
   ├── My Invoices
   └── Create Invoice
📊 Reports
   └── Low Stock Alert (my warehouse)
```

---

## 12. Invoice Number Algorithm

```php
// string_helper_ext.php
function generate_invoice_number(): string
{
    $CI    = &get_instance();
    $today = date('Ymd');
    $prefix = $CI->setting_model->get('invoice_prefix') ?? 'INV';

    // Count today's invoices and pad to 4 digits
    $count = $CI->db
        ->where('DATE(created_at)', date('Y-m-d'))
        ->count_all_results('invoices');

    return sprintf('%s-%s-%04d', $prefix, $today, $count + 1);
    // → INV-20261029-0001
}
```

> **Note:** This runs INSIDE the transaction after `FOR UPDATE` lock is acquired, so concurrent invoices cannot get the same number.

---

## 13. Cancel Invoice — Stock Restoration Rule

```php
// InvoiceService::cancel(int $invoiceId): void
public function cancel(int $invoiceId): void
{
    $this->db->trans_begin();
    try {
        $invoice = $this->invoiceRepo->findWithItems($invoiceId);
        if ($invoice->status === 'cancelled') {
            throw new AppException('Invoice already cancelled');
        }

        foreach ($invoice->items as $item) {
            // Restore stock: add quantity back to warehouse
            $this->inventoryRepo->increment(
                $item->product_id,
                $invoice->warehouse_id,
                $item->quantity
            );
        }

        $this->invoiceRepo->update($invoiceId, [
            'status'     => 'cancelled',
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $this->db->trans_commit();
    } catch (Throwable $e) {
        $this->db->trans_rollback();
        throw $e;
    }
}
```

---

## 14. Database Schema (Complete — v3.0)

> *[Full schema as in v2.0 — identical, keeping here for completeness]*

**Migration order with `ci_sessions` added:**

```
001 currencies → 002 settings → 003 warehouses → 004 categories
→ 005 users → 006 login_attempts → 007 products → 008 inventory
→ 009 customers → 010 invoices → 011 invoice_items → 012 ci_sessions
```

---

## 15. Security Architecture

| Threat | Mitigation |
|--------|-----------|
| SQL Injection | CI3 Query Builder exclusively; zero raw string interpolation |
| XSS | `htmlspecialchars()` in all view output; `xss_clean()` on input |
| CSRF | CI3 `csrf_protection = TRUE`; AJAX sends `X-CSRF-Token` header (see §6) |
| Brute Force | 5 attempts/15 min per IP → `login_attempts` table block |
| Password Storage | `password_hash($pass, PASSWORD_BCRYPT, ['cost' => 12])` |
| Session Hijack | `sess_use_database = TRUE`; `session_regenerate_id()` on login |
| Privilege Escalation | Role verified in `MY_Controller::requireRole()` every request |
| Directory Traversal | `application/.htaccess` denies direct access |
| Info Leakage | `display_errors = Off`; CI3 `log_threshold = 1` in production |
| HTTP Headers | `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff` |
| Invalid Roles | `UnauthorizedException` thrown → 403 page |

---

## 16. Unit Testing Strategy

```xml
<!-- phpunit.xml -->
<phpunit bootstrap="application/tests/bootstrap.php" colors="true">
    <testsuites>
        <testsuite name="Unit">
            <directory>application/tests/unit</directory>
        </testsuite>
        <testsuite name="Integration">
            <directory>application/tests/integration</directory>
        </testsuite>
    </testsuites>
    <coverage>
        <include>
            <directory suffix=".php">application/services</directory>
            <directory suffix=".php">application/repositories</directory>
            <directory suffix=".php">application/helpers</directory>
        </include>
        <report>
            <html outputDirectory="coverage-report"/>
        </report>
    </coverage>
</phpunit>
```

### Key Tests
| Test | Validates |
|------|----------|
| `test_rejects_sale_when_stock_is_insufficient` | `InsufficientStockException` thrown |
| `test_accepts_sale_when_stock_equals_requested` | Edge case — exact qty succeeds |
| `test_stock_restored_on_invoice_cancel` | `increment()` called for each item |
| `test_discount_rounding_is_half_up` | 1000 × 15.5% = 845.00 exactly |
| `test_invoice_number_format` | Regex `/^INV-\d{8}-\d{4}$/` |
| `test_customer_duplicate_phone_rejected` | `DuplicateCustomerException` |
| `test_null_phone_does_not_block_other_null_phone` | MySQL nullable unique |
| `test_low_stock_quantity_lte_alert_quantity` | `qty <= alert_qty` returns true |
| `test_shortage_calculation` | `shortage = alert_qty - qty` |
| `test_warehouse_user_cannot_see_other_warehouses` | Scope check in repository |
| `test_money_format_uses_session_currency` | SAR vs USD formatting |

---

## 17. Linting & Code Quality

### `.phpcs.xml`
```xml
<?xml version="1.0"?>
<ruleset name="MiniERP-PSR12">
    <file>application/controllers</file>
    <file>application/models</file>
    <file>application/services</file>
    <file>application/repositories</file>
    <file>application/libraries</file>
    <file>application/helpers</file>
    <file>application/exceptions</file>

    <rule ref="PSR12"/>
    <rule ref="Generic.PHP.DisallowShortOpenTag"/>
    <rule ref="Generic.PHP.ForbiddenFunctions">
        <properties>
            <property name="forbiddenFunctions" type="array">
                <element key="var_dump"  value="null"/>
                <element key="print_r"   value="null"/>
                <element key="die"       value="null"/>
                <element key="exit"      value="null"/>
            </property>
        </properties>
    </rule>
    <rule ref="Generic.Files.LineLength">
        <properties>
            <property name="lineLimit" value="120"/>
            <property name="absoluteLineLimit" value="150"/>
        </properties>
    </rule>
    <!-- CI3 models use underscore naming — allow it -->
    <rule ref="PSR1.Methods.CamelCapsMethodName.NotCamelCaps">
        <exclude-pattern>application/models/*</exclude-pattern>
    </rule>
</ruleset>
```

### `phpstan.neon`
```neon
parameters:
    level: 6
    paths:
        - application/services
        - application/repositories
        - application/exceptions
        - application/libraries
    ignoreErrors:
        - '#Access to an undefined property CI_Controller#'
        - '#Call to an undefined method CI_DB_query_builder#'
```

---

## 18. Project Meta Files

### `.gitignore`
```gitignore
# Dependencies
/vendor/

# CI3 logs and cache
application/logs/*.log
application/cache/*
!application/cache/.gitkeep

# Environment
.env
.env.local

# IDE
.idea/
.vscode/
*.swp

# OS
.DS_Store
Thumbs.db

# Test artifacts
/coverage-report/
.phpunit.result.cache

# Composer lock (commit this for applications)
# composer.lock  ← DO NOT add to gitignore for applications
```

### `README.md` Spec (document these steps)
```markdown
## Setup
1. Clone repo, run `composer install`
2. Create DB `mini_erp_smart_life`
3. Copy `application/config/database.php.example` → configure
4. Run: `php index.php migrate/run`
5. Run: `php index.php cli/seed run`
6. Configure Apache/Nginx vhost → point to project root
7. Access: http://localhost/mini-erp/

## Credentials
Admin: test@smartlife.com / 87654321
Riyadh: ahmed@smartlife.com / pass@1234

## Commands
composer test   → Run all tests
composer lint   → Check code style
composer check  → Full quality gate
```

---

## 19. Seeder Data (Complete Specification)

*(Full seeder spec from v2.0 preserved — 3 currencies, 3 warehouses, 5 categories,
4 users, 30 products, 90 inventory rows, 15 customers, 20 invoices with stock deduction)*

### Additional Seeder Details (new in v3.0)

**`InvoiceSeeder` — Cancel 2 invoices after creation**
- Invoice #3 (Riyadh) → cancel → verify stock restored in `inventory`
- Invoice #15 (Jeddah) → cancel → verify stock restored
- This ensures the low-stock report shows some products dipping below alert level post-cancellation reversal

**`InventorySeeder` — Deliberate Low-Stock Scenarios**
| Product | Riyadh | Jeddah | Sana'a | Alert Qty | Notes |
|---------|--------|--------|--------|-----------|-------|
| ELEC-001 | 45 | **4** | 0 | 5 | Jeddah & Sana'a trigger alert |
| ELEC-003 | 1 | 8 | 2 | 2 | Riyadh triggers alert |
| CLEAN-002 | 0 | 0 | 12 | 0 | Zero stock — not below alert |
| FOOD-004 | **3** | **2** | **1** | 5 | ALL warehouses trigger alert |

---

## 20. Git Strategy — Professional Branch & Commit Model

### Branch Architecture

```
main ─────────────────────────────────────── production-ready, tagged releases
  └── develop ──────────────────────────────── integration branch
        ├── feature/project-foundation
        ├── feature/auth-system
        ├── feature/product-catalog
        ├── feature/inventory-management
        ├── feature/invoice-system
        ├── feature/reporting-module
        ├── feature/i18n-currency
        ├── feature/demo-seeders
        ├── feature/unit-tests
        └── feature/ui-polish
```

### Merge Strategy
- All feature branches → `develop` via **Squash Merge** (one clean commit per feature)
- `develop` → `main` via **Merge Commit** with release tag
- Direct commits to `main` or `develop` are **forbidden**
- Each PR must pass `composer check` (lint + stan + mess + tests) before merge

---

### Commit Convention — Conventional Commits

```
<type>(<scope>): <description>

[optional body — what and why, not how]

[optional footer: BREAKING CHANGE / Refs / Closes]
```

**Types used in this project:**
| Type | When |
|------|------|
| `feat` | New feature or endpoint |
| `fix` | Bug fix |
| `refactor` | Code structure change, no behaviour change |
| `test` | Adding or updating tests |
| `chore` | Config, tooling, dependencies, build |
| `db` | Migration, schema, index change |
| `style` | Formatting only (via php-cs-fixer) |
| `docs` | README, plan, code comments |
| `perf` | Performance improvement |
| `security` | Security fix or hardening |

---

### All Commits — Ordered by Execution

#### 🌿 Branch: `feature/project-foundation`

```bash
chore(init): initialise CodeIgniter 3.1.13 project structure

Set up base CI3 installation with clean directory layout,
remove default welcome controller and view,
configure .htaccess to strip index.php from URL.
```

```bash
chore(composer): add dev dependencies for testing and code quality

PHPUnit 9.x, FakerPHP, PHPStan level 6, PHP_CodeSniffer PSR-12,
PHP-CS-Fixer, PHPMD, league/csv. Configure autoload for
App\Repositories, App\Services, App\Exceptions namespaces.
```

```bash
chore(config): configure application settings and constants

Set encryption_key, sess_use_database=TRUE, base_url, timezone.
Define constants: ROLE_ADMIN, ROLE_WAREHOUSE, STATUS_ACTIVE,
STATUS_INACTIVE, APP_VERSION. Enable hooks.
```

```bash
chore(lint): add code quality configuration files

Add .phpcs.xml (PSR-12 with project exceptions),
phpstan.neon (level 6), phpmd.xml (cyclomatic ≤10),
.php-cs-fixer.php, phpunit.xml with coverage config.
Register pre-commit hook blocking on lint/stan failure.
```

```bash
feat(core): implement MY_Controller base with auth guard and layout renderer

Add requireRole(), requireAuth(), render() layout composition method,
CSRF injection into view data, flash data bridge for SweetAlert2,
and HTTP security headers (X-Frame-Options, X-Content-Type-Options).
```

```bash
feat(core): implement MY_Model base with timestamps and soft-delete helpers

Add beforeInsert/beforeUpdate timestamp auto-fill,
softDelete(), restore(), onlyActive() scope methods,
fillable property enforcement to prevent mass assignment.
```

```bash
feat(exceptions): add custom exception hierarchy

InsufficientStockException, DuplicateCustomerException,
UnauthorizedException, ValidationException — all extend AppException.
```

```bash
db(migrate): create all 12 database migrations

001 currencies, 002 settings, 003 warehouses, 004 categories,
005 users, 006 login_attempts, 007 products (FULLTEXT index),
008 inventory (CHECK quantity>=0, FOR UPDATE ready),
009 customers (nullable UNIQUE phone/email),
010 invoices, 011 invoice_items (CASCADE delete),
012 ci_sessions. All InnoDB, utf8mb4_unicode_ci.
```

---

#### 🌿 Branch: `feature/auth-system`

```bash
feat(auth): implement login throttle service

LoginThrottle checks ip_address in login_attempts table.
Blocks after 5 failed attempts in 15-minute window.
Auto-cleans expired attempts older than 15 minutes.
```

```bash
feat(auth): implement AuthService with bcrypt verification

login() verifies email existence → password_verify() → session set.
Session payload: user_id, role, warehouse_id, name, preferred_lang,
preferred_currency. Regenerates session ID on successful login.
logout() destroys session completely.
```

```bash
feat(auth): implement Auth_lib session wrapper

Provides: isLoggedIn(), getUser(), getRole(), getWarehouseId(),
hasRole(string $role) — used in all controllers and hooks.
```

```bash
feat(auth): add AuthHook and LangHook as pre_controller hooks

AuthHook: redirects unauthenticated to /login, enforces role-based
URL prefix guard (/admin/* blocked for user_warehouse role).
LangHook: reads session lang → loads language file → sets RTL/LTR.
```

```bash
feat(auth): add login view with Tabler auth layout

Tabler card-centered auth page, bilingual label switching,
email + password fields, CSRF token, remember me checkbox.
RTL layout applied when default_lang = ar.
```

```bash
test(auth): add unit tests for AuthService and LoginThrottle

test_login_fails_with_wrong_password,
test_login_blocked_after_5_attempts,
test_login_succeeds_and_sets_session,
test_logout_destroys_session,
test_throttle_resets_after_15_minutes.
```

---

#### 🌿 Branch: `feature/product-catalog`

```bash
feat(repos): implement BaseRepository and ProductRepository with interface

BaseRepositoryInterface: findById, findAll, count, create, update, softDelete.
ProductRepository: addSearchFilter (FULLTEXT MATCH AGAINST),
addCategoryFilter, getPaginated, findByCode.
```

```bash
feat(repos): implement CategoryRepository and WarehouseRepository

CRUD operations, status filter, bilingual name support,
count products per category for category list display.
```

```bash
feat(service): implement ProductService with business logic

create() validates unique code, sets created_by, timestamps.
update() validates unique code excluding self.
toggle() switches active/inactive, clears updated_by.
getPaginated() wires filters → repository → DataTables response.
search() returns Select2-compatible JSON for invoice use.
```

```bash
feat(controller): implement admin Products controller (thin)

index() → DataTables JSON response.
create/store() → form + validation → service → flash redirect.
edit/update() → form + validation → service → flash redirect.
toggle() → service → JSON response.
search() → ProductService::search() → Select2 JSON.
```

```bash
feat(views): add product list, create, and edit views with Tabler UI

Tabler page-header + DataTables table with category filter badge,
status toggle switch, action buttons. Create/edit forms with
bilingual name fields, category Select2, price input with currency
symbol. Client-side code/name validation before submit.
```

```bash
feat(controller): implement admin Categories controller

Index shows categories with product count.
Store/update via modal (no separate page).
Inline edit/delete within list.
```

```bash
db(seed): add CurrencySeeder, SettingSeeder, CategorySeeder, WarehouseSeeder

SAR/USD/YER with symbols and decimal_places.
6 app settings. 5 product categories bilingual.
3 warehouses (Riyadh, Jeddah, Sana'a) bilingual.
```

```bash
db(seed): add UserSeeder and ProductSeeder

4 users: 1 admin + 3 warehouse users with bcrypt passwords.
30 products: 6 per category with codes, bilingual names,
realistic prices, and alert_quantity values.
```

```bash
test(service): add unit tests for ProductService

test_create_product_returns_new_id,
test_create_fails_if_code_duplicate,
test_update_allows_same_code_for_self,
test_toggle_changes_status,
test_search_returns_select2_format,
test_paginated_filters_by_category.
```

---

#### 🌿 Branch: `feature/inventory-management`

```bash
feat(repos): implement InventoryRepository with warehouse scope

getByWarehouse() with product join and filters.
getByProductAndWarehouse() for SELECT FOR UPDATE.
decrement() and increment() with version bump (optimistic lock).
getLowStock() with shortage calculation: alert_qty - quantity.
```

```bash
feat(service): implement InventoryService with warehouse access guard

Admin: all warehouses. user_warehouse: own warehouse_id only.
Throws UnauthorizedException if user_warehouse targets other warehouse.
```

```bash
feat(controller): implement admin/Inventory and warehouse/Inventory controllers

admin: getAllWarehouses view with warehouse filter tabs.
warehouse: scoped to session warehouse_id, filter hidden.
Both serve DataTables JSON with product code, name_ar,
available qty, alert qty, status badge (normal/low/empty).
```

```bash
feat(views): add inventory index view with Tabler

Warehouse tab pills (admin only), status badges (green/orange/red),
DataTables sortable by qty/alert. Responsive grid layout.
```

```bash
db(seed): add InventorySeeder with deliberate low-stock scenarios

90 rows (30 products × 3 warehouses). ~30% below alert_quantity.
~10% at zero. Jeddah/Sana'a intentionally starved on
select products for realistic low-stock report population.
```

---

#### 🌿 Branch: `feature/invoice-system`

```bash
feat(repos): implement CustomerRepository and InvoiceRepository

CustomerRepository: findByPhone, findByEmail for duplicate check,
getActive for Select2, paginated list.
InvoiceRepository: createWithItems (single call), findWithItems (join),
generateInvoiceNumber (INV-YYYYMMDD-NNNN), paginate with filters.
```

```bash
feat(service): implement CustomerService with duplicate guard

create() checks phone uniqueness → DuplicateCustomerException.
check() checks email uniqueness → DuplicateCustomerException.
Both admin and warehouse user route through same service.
```

```bash
feat(service): implement InvoiceService with pessimistic locking

create(): opens transaction → FOR UPDATE lock per item →
validates qty → decrements inventory → saves invoice + items →
commits. cancel(): restores stock → updates status → commits.
calculateTotals(): subtotal, discount (round half-up), final total.
generateInvoiceNumber(): runs inside transaction after lock acquired.
```

```bash
feat(controller): implement admin/Invoices (create, store, show, cancel)

create(): loads customers + warehouses + empty invoice page.
store(): accepts JSON body → InvoiceService::create() → JSON response.
show(): invoice detail with all items, totals, status badge.
cancel(): POST → InvoiceService::cancel() → flash redirect.
index(): DataTables JSON with filters (warehouse, date range, status).
```

```bash
feat(controller): implement warehouse/Invoices scoped to own warehouse

Same as admin but warehouse_id locked to session value.
customer create shortcut in modal (no redirect away from invoice).
```

```bash
feat(views): add invoice create page with live JS calculation

Tabler page layout. Select2 for customer (with add-new modal).
Warehouse selector (locked for user_warehouse).
Product search Select2 (AJAX → /products/search).
Dynamic row table: add/remove rows, qty input, per-line discount.
numeral.js formats all monetary values client-side.
Invoice-level discount recalculates all totals on change.
Save sends one JSON POST, receives success/error JSON.
SweetAlert2 shows result — redirects on success.
```

```bash
feat(views): add invoice list and invoice detail views

List: DataTables with status badge, warehouse filter, date range picker.
Detail: print-friendly layout, product table, totals summary,
cancel button (admin only) with SweetAlert2 confirm dialog.
```

```bash
db(seed): add CustomerSeeder and InvoiceSeeder

15 customers (Saudi + Yemeni mix). 20 invoices across 3 warehouses
with 2–5 items each. Stock deducted per item within transactions.
2 invoices cancelled post-creation (stock restored). Mixed currencies.
Dates spread over last 90 days using Faker.
```

```bash
test(service): add unit tests for InvoiceService

test_create_throws_when_stock_insufficient,
test_create_succeeds_when_stock_exact,
test_cancel_restores_stock_for_all_items,
test_discount_rounding_half_up (15.5% of 1000 = 845.00),
test_invoice_number_format_matches_regex,
test_zero_discount_does_not_alter_total.
```

```bash
test(integration): add InvoiceFlowTest against real test database

Full flow: seed product → create invoice → check inventory deducted →
cancel invoice → check inventory restored → verify invoice status.
Uses separate test DB, truncation in tearDown.
```

---

#### 🌿 Branch: `feature/reporting-module`

```bash
feat(service): implement ReportService for low-stock report

getLowStock(int $warehouseId = null): returns products where
quantity <= alert_quantity. Includes: product_code, name, name_ar,
warehouse_name, quantity, alert_quantity, shortage (alert - qty).
Admin gets all warehouses; user_warehouse gets own only.
```

```bash
feat(controller): implement admin/Reports and warehouse/Reports

lowStock(): renders table view with warehouse filter (admin).
exportLowStock(): streams CSV via CsvExporter (no temp file on disk).
CSV columns: Product Code, Name, Name (AR), Category, Warehouse,
Current Qty, Alert Qty, Shortage.
```

```bash
feat(views): add low-stock report view with Tabler danger badges

Page header with export button. Table with red/orange badges
for shortage level. Warehouse filter pills (admin only).
Empty state illustration when all stock is healthy.
```

```bash
test(service): add unit tests for ReportService

test_returns_only_products_at_or_below_alert,
test_shortage_is_alert_minus_quantity,
test_warehouse_filter_scopes_to_own (user_warehouse),
test_admin_sees_all_warehouses,
test_zero_quantity_product_appears_in_report.
```

---

#### 🌿 Branch: `feature/i18n-currency`

```bash
feat(i18n): add Arabic and English language files

All UI strings: navigation, form labels, validation messages,
status labels, report headings, flash messages, error pages.
Both languages complete — no missing keys.
```

```bash
feat(locale): implement LocaleService and language switcher

switchLang(): validates lang in ['ar','en'], sets session,
updates users.preferred_lang in DB.
switchCurrency(): validates code in currencies table, sets session,
updates users.preferred_currency in DB.
```

```bash
feat(views): add language and currency switcher to Tabler navbar

Flag icon + code toggle for language.
Currency code + symbol toggle for active currency.
Both POST to respective endpoints with CSRF token.
Immediately refreshes page to re-render in new locale/currency.
```

```bash
feat(views): apply RTL layout throughout all Tabler views

head.php dynamically loads tabler.rtl.min.css when lang=ar.
All custom CSS uses logical properties (margin-inline-start).
app.rtl.css overrides for Arabic typography (font: Noto Sans Arabic).
Select2 and DataTables RTL alignment confirmed.
```

---

#### 🌿 Branch: `feature/ui-polish`

```bash
feat(views): add Tabler dashboard with stats cards and charts

Admin dashboard: total invoices MTD, low stock products count,
revenue MTD, active customers count. Quick action buttons.
Recent invoices table (last 5). Low stock alert badge on sidebar.
user_warehouse dashboard: scoped to own warehouse.
```

```bash
feat(views): add 403 and 404 error pages in Tabler style

403: lock icon, "Access Denied" bilingual message, back button.
404: ghost icon, "Page Not Found" bilingual message, home button.
Both pages styled consistently with rest of UI.
```

```bash
feat(views): add sidebar active state and breadcrumb system

Sidebar item highlights based on current URI segment.
Breadcrumbs injected via MY_Controller::$breadcrumbs array.
Each controller sets breadcrumbs in constructor.
```

```bash
security(headers): add security HTTP headers to MY_Controller

X-Frame-Options: DENY
X-Content-Type-Options: nosniff
X-XSS-Protection: 1; mode=block
Referrer-Policy: strict-origin-when-cross-origin
Content-Security-Policy: default-src 'self' (relaxed for CDN assets)
```

```bash
chore(readme): update README with full setup and credential instructions

Setup steps, migration command, seed command, test command,
all login credentials, environment configuration notes.
```

---

#### Release Merge Commits

```bash
# feature/* → develop (squash merge, one commit per feature):
chore(develop): merge feature/project-foundation [v0.1]
chore(develop): merge feature/auth-system [v0.2]
chore(develop): merge feature/product-catalog [v0.3]
chore(develop): merge feature/inventory-management [v0.4]
chore(develop): merge feature/invoice-system [v0.5]
chore(develop): merge feature/reporting-module [v0.6]
chore(develop): merge feature/i18n-currency [v0.7]
chore(develop): merge feature/demo-seeders [v0.8]
chore(develop): merge feature/unit-tests [v0.9]
chore(develop): merge feature/ui-polish [v1.0-rc]

# develop → main:
release: v1.0.0 — Mini ERP initial release

Complete Mini ERP with products, inventory, invoices,
low-stock reporting, bilingual RTL/LTR support (AR/EN),
multi-currency (SAR/USD/YER), RBAC (admin/user_warehouse),
race condition protection (SELECT FOR UPDATE),
PSR-12 compliant, PHPStan level 6, 80%+ test coverage.
```

---

## 21. Execution Roadmap (Updated — v3.0)

| # | Branch | Phase | Quality Gate |
|---|--------|-------|-------------|
| 1 | `feature/project-foundation` | Setup + configs + core + all migrations | `composer lint` passes |
| 2 | `feature/auth-system` | Auth service + lib + hooks + login view | `composer test` auth tests pass |
| 3 | `feature/product-catalog` | Products + categories + seeders | `composer check` all green |
| 4 | `feature/inventory-management` | Inventory repo + service + views + seed | `composer check` all green |
| 5 | `feature/invoice-system` | Invoice service (FOR UPDATE) + views + seed | Integration test passes |
| 6 | `feature/reporting-module` | Reports + CSV export | `composer check` all green |
| 7 | `feature/i18n-currency` | Language files + RTL/LTR + currency | Manual AR/EN toggle test |
| 8 | `feature/unit-tests` | All remaining unit tests + coverage ≥ 80% | `composer test` all green |
| 9 | `feature/ui-polish` | Dashboard stats, 403/404, breadcrumbs, headers | Visual review |
| 10 | Release | `develop` → `main`, tag `v1.0.0` | Full `composer check` + tag |

---

*Plan version 3.0 — Reviewed & finalized: 2026-10-29 — No open blockers. Ready for execution.*

---

## v3.1 Amendment - Final Scope and Correctness Requirements

This amendment supersedes every conflicting statement in earlier sections of this plan.

### Product scope retained

- Keep the Arabic/English language switcher and RTL/LTR layouts.
- Keep dashboards, breadcrumbs, and the admin Users CRUD.
- Use one controller set (`Dashboard`, `Customers`, `Inventory`, `Invoices`, `Reports`, etc.), not parallel `admin/` and `warehouse/` controllers. Routes may remain role-protected, but controllers derive the effective warehouse scope from the authenticated user.
- Admins may select any active warehouse. `user_warehouse` users are always constrained to their own `warehouse_id`; submitted warehouse IDs never widen that scope.
- Keep Products, Categories, Warehouses and Settings admin-only. Keep Customers, Inventory, Invoices and Reports available to both roles under the scope rule above.

### SAR-only money policy

The system has exactly one currency: Saudi Riyal (`SAR`, `ر.س`, 2 decimal places). Remove currency tables, models, repositories, services, seeders, settings, endpoints, navbar currency controls, and `users.preferred_currency`. All money fields are `DECIMAL(12,2)` and all stored/displayed values are SAR. There is no exchange-rate or currency conversion feature.

### CI3 lifecycle, wiring, and validation

- Do not use `pre_controller` hooks for authentication or locale selection. Put both guards in `MY_Controller` (or use `post_controller_constructor` only after the CI instance and session are available).
- `MY_Controller` loads shared dependencies and constructs services with explicit constructor injection. Controllers obtain services through protected factory methods; services do not access the CI superobject.
- Set `composer_autoload = TRUE` in `application/config/config.php`. PSR-4 maps `App\\Contracts\\` to the case-correct `application/repositories/Contracts/` directory.
- CI3 has no `permit_empty`; optional rules omit `required`. CI3 `is_unique` cannot exclude the current record, so product-code uniqueness uses a callback/service query that excludes the record ID.
- Provide actual `Cli::migrate()` and `Cli::seed()` controllers, or document `database/schema.sql` and `database/seed.sql` as the supported setup. Do not advertise commands that do not exist.

### Browser, CSRF, and output safety

`public/assets/js/app.js` is static JavaScript and must contain no PHP. `head.php` emits escaped metadata:

```html
<meta name="csrf-name" content="...">
<meta name="csrf-hash" content="...">
```

The JavaScript reads this metadata, sends the current token in the request body under the CSRF field name (FormData or JSON decoded into `$_POST`), and updates the hash from the `csrf_hash` included in every JSON response. It must not rely on an `X-CSRF-Token` header because CI3 does not validate that header by default.

Flash values are embedded with `json_encode()` (including the SweetAlert icon, title and position), never with raw PHP interpolation or `htmlspecialchars()` intended for HTML. Escape all dynamic HTML at the output sink; do not use `xss_clean()` as an application-wide input policy.

### Search, headers, and CSV

- Product search uses case-insensitive `LIKE` on `code`, `name`, and `name_ar`; it does not use FULLTEXT. This supports partial text such as `key` and product codes containing hyphens.
- Tabler itself does not require jQuery. jQuery is intentionally included only for DataTables and Select2.
- Configure security headers in one place only: the web-server configuration (or project `.htaccess` guarded by `<IfModule mod_headers.c>`). Do not set them again in `MY_Controller`; omit deprecated `X-XSS-Protection`. Use a CSP compatible with the chosen local/CDN assets and avoid inline scripts where feasible.
- CSV export prepends a UTF-8 BOM and prefixes a cell beginning with `=`, `+`, `-`, or `@` with a single quote before writing it.

### Transactions, invoice numbers, and concurrency

Repositories expose `transaction(callable $work)` and own `trans_begin`, `trans_commit`, and rollback. Services express business workflows but do not call CI3 transaction APIs directly.

Invoice creation runs as one transaction:

1. Resolve and authorize the warehouse scope.
2. Aggregate duplicate product lines and sort product IDs ascending.
3. Lock matching inventory rows in ascending `product_id` order with `SELECT ... FOR UPDATE`.
4. Validate quantities, calculate money in integer halalas (or bcmath), decrement stock, create the invoice, and insert item snapshots.
5. Commit or roll back as one unit.

The invoice number is generated safely by locking a dated `invoice_counters` row with `FOR UPDATE`, incrementing it, and storing a unique `invoice_number` such as `INV-20261029-0001`. The database also enforces `UNIQUE (invoice_number)`. Inventory uses pessimistic locking only; remove its `version` column and all optimistic-version updates.

Invoice cancellation also runs in a transaction. It locks the invoice row with `FOR UPDATE`, checks both authorization and `status <> 'cancelled'` while locked, loads its items, locks inventory rows in ascending product order, restores quantities, then marks the invoice cancelled. Invoice show and cancel must verify the invoice warehouse is within the current user's effective scope to prevent IDOR.

### Data integrity rules

- Invoice items snapshot `product_code`, product names, unit, `unit_price`, line discount, quantity, and line total. Historic invoices never change when products later change.
- Normalize phones before storage; persist missing phone/email as `NULL`, not an empty string. Unique database indexes back up service checks; on MySQL error 1062 return the duplicate-field validation error.
- All discounts and totals are calculated in halalas with documented positive-value half-up rounding, then stored as `DECIMAL(12,2)`.
- `database/schema.sql` is the canonical fresh-install schema. `database/seed.sql` is optional demo data and is idempotent where practical.

### Required verification

Add tests for concurrent invoice number allocation, reverse-order item locking, double-cancel prevention, warehouse IDOR on show/cancel, product-code update uniqueness, CSRF token refresh, CSV formula protection, and SAR-only formatting. The release definition must say AR/EN bilingual and SAR-only, not multi-currency.

*Amendment version 3.1 - 2026-10-29. See `database/schema.sql` and `IMPLEMENTATION_DECISIONS.md`.*
---

## v3.2 Clarification - No Currency Module

This is a single-currency ERP. SAR is a fixed application invariant, not a configurable feature.

Delete or do not create every legacy v3.0 currency artifact, including:

- `001_create_currencies_table.php`, the `currencies` table, and `CurrencySeeder`;
- `Currency_model.php`, `CurrencyRepository.php`, `CurrencyRepositoryInterface.php`, and `CurrencyService.php`;
- `users.preferred_currency`, currency settings, currency endpoints, and the navbar currency switcher;
- all SAR/USD/YER seed data, mixed-currency invoices, and currency-specific tests.

The migration order starts with settings, not currencies:

```text
001 settings -> 002 warehouses -> 003 categories -> 004 users -> 005 login_attempts
-> 006 products -> 007 inventory -> 008 customers -> 009 invoice_counters
-> 010 invoices -> 011 invoice_items -> 012 ci_sessions
```

Use fixed `SAR` formatting in one presentation helper. Persist every monetary value as `DECIMAL(12,2)` representing SAR. The language switcher remains; a currency switcher does not exist.

*Clarification version 3.2 - 2026-10-29. This overrides all earlier currency-related plan text.*

## v3.3 Amendment ? Counter-free invoice numbering (2026-10-30)

At the user's request, this supersedes the earlier `invoice_counters` requirement. `InvoiceRepository::nextNumber()` derives the next numeric suffix from retained invoices for the current prefix/date. `BaseRepository::withAdvisoryLock()` provides database-scoped locking, and `InvoiceRepository::numberedTransaction()` holds that lock from before transaction start until after commit/rollback. All invoice creators must use this path. A standalone COUNT/MAX query is not safe for allocation.

Migration 014 removes the counter table; migration 009 is retained for historical upgrade compatibility. Fresh schema installations omit the table. Existing invoice numbers are unchanged. The new scheme assumes one MySQL writer and retained invoices, and deliberately serializes number-allocating transactions. See `DATABASE_REVIEW.md` for tradeoffs and review-only schema recommendations.
