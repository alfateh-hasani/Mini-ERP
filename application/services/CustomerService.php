<?php
namespace App\Services;

use App\Exceptions\DuplicateCustomerException;
use App\Repositories\CustomerRepository;

class CustomerService
{
    private $customers;
    public function __construct(CustomerRepository $customers) { $this->customers = $customers; }
    public function save(array $input, int $userId, ?int $id = null): int
    {
        $phone = $this->phone($input['phone'] ?? ''); $email = trim(strtolower($input['email'] ?? ''));
        if ($this->customers->duplicate('phone', $phone, $id)) { throw new DuplicateCustomerException('phone'); }
        if ($this->customers->duplicate('email', $email, $id)) { throw new DuplicateCustomerException('email'); }
        $data = ['name' => trim($input['name']), 'phone' => $phone ?: null, 'email' => $email ?: null, 'address' => trim($input['address'] ?? '') ?: null];
        if (!$id) { $data['created_by'] = $userId; }
        $database = $this->customers->database();
        $debug = $database->db_debug;
        $database->db_debug = false;
        try {
            $result = $this->customers->save($data, $id);
            $error = $database->error();
            if ((int) $error['code'] === 1062) { throw new DuplicateCustomerException('phone or email'); }
            return $result;
        } finally {
            $database->db_debug = $debug;
        }
    }
    private function phone(string $phone): string { return preg_replace('/[^0-9+]/', '', trim($phone)); }
}
