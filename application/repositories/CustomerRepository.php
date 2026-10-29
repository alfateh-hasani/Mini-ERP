<?php
namespace App\Repositories;

class CustomerRepository extends BaseRepository
{
    public function duplicate(string $field, string $value, ?int $exceptId = null): bool
    {
        if ($value === '') { return false; }
        $query = $this->db->where($field, $value);
        if ($exceptId) { $query->where('id !=', $exceptId); }
        return $query->count_all_results('customers') > 0;
    }

    public function save(array $data, ?int $id = null): int
    {
        if ($id) { $this->db->where('id', $id)->update('customers', $data); return $id; }
        $this->db->insert('customers', $data); return (int) $this->db->insert_id();
    }
}
