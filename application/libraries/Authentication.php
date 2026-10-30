<?php
defined('BASEPATH') OR exit('No direct script access allowed');

use App\Repositories\AuthRepository;
use App\Services\RememberMeService;

/** CI session/cookie adapter; cryptography and persistence live in the service/repository. */
class Authentication
{
    private const COOKIE = 'erp_remember';
    private $ci;
    private $repository;
    private $remember;

    public function __construct()
    {
        $this->ci =& get_instance();
        $this->repository = new AuthRepository($this->ci->db);
        $this->remember = new RememberMeService($this->repository);
    }

    public function restore(): ?array
    {
        $saved = $this->ci->session->userdata('user');
        $now = time();
        if (is_array($saved) && isset($saved['id'])) {
            $user = $this->repository->user((int) $saved['id']);
            $last = $this->ci->session->userdata('auth_last_activity');
            $signature = $this->ci->session->userdata('auth_signature');
            if (!$user || $user['status'] !== 'active' || !is_string($signature) || !hash_equals($signature, RememberMeService::fingerprint($user))) {
                $this->forget();
                $this->clearSession();
                return null;
            }
            if (is_int($last) && $last > 0 && $last <= $now && $now - $last < (int) $this->ci->config->item('sess_expiration')) {
                $this->saveSession($user, $now, false);
                return $this->ci->session->userdata('user');
            }
            $this->clearSession();
        }
        $cookie = $this->ci->input->cookie(self::COOKIE, false);
        if (!$cookie) { return null; }
        try {
            $restored = $this->remember->consume($cookie, $now);
            if ($restored) {
                $this->saveSession($restored['user'], $now, true);
                $this->writeCookie($restored['value'], $restored['expires']);
                return $this->ci->session->userdata('user');
            }
        } catch (\Throwable $exception) {
            log_message('error', 'Remembered login failed: ' . $exception->getMessage());
        }
        $this->writeCookie('', $now - 3600);
        return null;
    }

    public function login(array $user, bool $remember): void
    {
        $this->forget();
        $this->saveSession($user, time(), true);
        if ($remember) {
            try {
                $token = $this->remember->issue($user, time());
                $this->writeCookie($token['value'], $token['expires']);
            } catch (\Throwable $exception) {
                log_message('error', 'Remembered login could not be saved: ' . $exception->getMessage());
                $this->ci->session->set_flashdata('flash', ['type' => 'warning', 'message' => lang('remember_failed')]);
            }
        }
    }

    public function forget(): void
    {
        try {
            $this->remember->revoke($this->ci->input->cookie(self::COOKIE, false));
        } finally {
            $this->writeCookie('', time() - 3600);
        }
    }

    public function revokeUser(int $id): void { $this->repository->revokeUser($id); }

    public function refresh(array $user): void { $this->saveSession($user, time(), true); }

    private function saveSession(array $user, int $now, bool $new): void
    {
        if ($new) { $this->ci->session->sess_regenerate(true); }
        $this->ci->session->set_userdata([
            'user' => ['id' => (int) $user['id'], 'name' => $user['name'], 'role' => $user['role'], 'warehouse_id' => $user['warehouse_id']],
            'auth_last_activity' => $now, 'auth_signature' => RememberMeService::fingerprint($user),
        ]);
        if ($new) { $this->ci->session->set_userdata('lang', $user['preferred_lang']); }
    }

    private function clearSession(): void
    {
        $this->ci->session->unset_userdata(['user', 'auth_last_activity', 'auth_signature']);
        $this->ci->session->sess_regenerate(true);
    }

    private function writeCookie(string $value, int $expires): void
    {
        setcookie(self::COOKIE, $value, [
            'expires' => $expires, 'path' => $this->ci->config->item('cookie_path') ?: '/',
            'domain' => $this->ci->config->item('cookie_domain') ?: '',
            'secure' => is_https() || (bool) $this->ci->config->item('cookie_secure'),
            'httponly' => true, 'samesite' => 'Lax',
        ]);
    }
}
