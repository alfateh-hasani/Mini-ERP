<?php
namespace App\Repositories;

class BaseRepository
{
    protected $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function database()
    {
        return $this->db;
    }

    public function transaction(callable $work)
    {
        $this->db->trans_begin();
        try {
            $result = $work();
            if ($this->db->trans_status() === false) {
                throw new \RuntimeException('Database transaction failed.');
            }
            $this->db->trans_commit();
            return $result;
        } catch (\Throwable $exception) {
            $this->db->trans_rollback();
            throw $exception;
        }
    }
}
