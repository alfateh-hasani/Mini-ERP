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

    /** Run cooperative work under a database-scoped MySQL advisory lock. */
    public function withAdvisoryLock(string $resource, callable $work)
    {
        $name = 'mini-erp:' . sha1($this->db->database . ':' . $resource);
        $result = $this->db->query('SELECT GET_LOCK(?, 10) AS acquired', [$name])->row_array();
        if ((int) ($result['acquired'] ?? 0) !== 1) {
            throw new \App\Exceptions\ValidationException('The operation is busy. Please retry.');
        }
        try {
            return $work();
        } finally {
            $this->db->query('SELECT RELEASE_LOCK(?)', [$name]);
        }
    }

    public function transaction(callable $work)
    {
        $debug = $this->db->db_debug ?? false;
        $this->db->db_debug = false;
        try {
            if ($this->db->trans_begin() === false) { throw new \RuntimeException('Cannot begin database transaction.'); }
            $result = $work();
            if ($this->db->trans_status() === false) {
                throw new \RuntimeException('Database transaction failed.');
            }
            if ($this->db->trans_commit() === false) { throw new \RuntimeException('Cannot commit database transaction.'); }
            return $result;
        } catch (\Throwable $exception) {
            $this->db->trans_rollback();
            throw $exception;
        } finally {
            $this->db->db_debug = $debug;
        }
    }
}
