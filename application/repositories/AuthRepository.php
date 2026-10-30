<?php
namespace App\Repositories;

class AuthRepository extends BaseRepository
{
    public function user(int $id): ?array
    {
        return $this->db->where('id', $id)->get('users')->row_array();
    }

    public function lockToken(string $selector): ?array
    {
        return $this->db->query('SELECT * FROM remember_tokens WHERE selector = ? FOR UPDATE', [$selector])->row_array();
    }

    public function storeToken(array $token): void
    {
        if (!$this->db->insert('remember_tokens', $token)) { throw new \RuntimeException('Cannot store remember token.'); }
    }

    public function rotateToken(string $selector, string $hash): void
    {
        if (!$this->db->where('selector', $selector)->update('remember_tokens', ['token_hash' => $hash])) { throw new \RuntimeException('Cannot rotate remember token.'); }
    }

    public function revoke(string $selector): void
    {
        if (!$this->db->where('selector', $selector)->delete('remember_tokens')) { throw new \RuntimeException('Cannot revoke remember token.'); }
    }

    public function revokeUser(int $userId): void
    {
        if (!$this->db->where('user_id', $userId)->delete('remember_tokens')) { throw new \RuntimeException('Cannot revoke remembered logins.'); }
    }

    public function deleteExpired(int $now): void
    {
        if (!$this->db->where('expires_at <=', $now)->delete('remember_tokens')) { throw new \RuntimeException('Cannot expire remember tokens.'); }
    }
}
