<?php
namespace App\Services;

use App\Repositories\AuthRepository;

class RememberMeService
{
    public const LIFETIME = 30 * 86400;
    private $repository;

    public function __construct(AuthRepository $repository) { $this->repository = $repository; }

    public static function fingerprint(array $user): string
    {
        return hash('sha256', implode("\0", [$user['password_hash'], $user['role'], (string) ($user['warehouse_id'] ?? ''), $user['status']]));
    }

    public function issue(array $user, int $now): array
    {
        $selector = bin2hex(random_bytes(12));
        $validator = bin2hex(random_bytes(32));
        $expires = $now + self::LIFETIME;
        $this->repository->transaction(function () use ($user, $selector, $validator, $expires, $now): void {
            $this->repository->deleteExpired($now);
            $this->repository->storeToken(['selector' => $selector, 'user_id' => (int) $user['id'], 'token_hash' => hash('sha256', $validator), 'auth_hash' => self::fingerprint($user), 'expires_at' => $expires]);
        });
        return ['value' => $selector . ':' . $validator, 'expires' => $expires];
    }

    public function consume($cookie, int $now): ?array
    {
        if (!is_string($cookie) || !preg_match('/^([a-f0-9]{24}):([a-f0-9]{64})$/D', $cookie, $parts)) { return null; }
        return $this->repository->transaction(function () use ($parts, $now): ?array {
            $token = $this->repository->lockToken($parts[1]);
            if (!$token) { return null; }
            $user = $this->repository->user((int) $token['user_id']);
            if ((int) $token['expires_at'] <= $now || !hash_equals($token['token_hash'], hash('sha256', $parts[2]))
                || !$user || $user['status'] !== 'active' || !hash_equals($token['auth_hash'], self::fingerprint($user))) {
                $this->repository->revoke($parts[1]);
                return null;
            }
            $validator = bin2hex(random_bytes(32));
            $this->repository->rotateToken($parts[1], hash('sha256', $validator));
            return ['user' => $user, 'value' => $parts[1] . ':' . $validator, 'expires' => (int) $token['expires_at']];
        });
    }

    public function revoke($cookie): void
    {
        if (is_string($cookie) && preg_match('/^([a-f0-9]{24}):[a-f0-9]{64}$/D', $cookie, $parts)) { $this->repository->revoke($parts[1]); }
    }
}
