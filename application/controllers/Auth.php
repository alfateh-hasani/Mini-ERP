<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Auth extends MY_Controller
{
    public function csrf(): void
    {
        $this->requireAuth();
        $this->output->set_header('Cache-Control: no-store');
        $this->json(true);
    }

    /** Logout must still be possible after the ordinary session expires. */
    public function logout_csrf(): void
    {
        $this->output->set_header('Cache-Control: no-store');
        $this->json(true);
    }

    public function login(): void
    {
        if ($this->currentUser) { redirect(''); }
        if ($this->input->method() === 'post') {
            $this->db->where('attempted_at <', date('Y-m-d H:i:s', time() - 900))->delete('login_attempts');
            $attempts = $this->db->where('ip_address', $this->input->ip_address())->count_all_results('login_attempts');
            if ($attempts >= 5) {
                $data['error'] = lang('too_many_login_attempts');
                $this->render('auth/login', $data, 'auth');
                return;
            }
            $this->form_validation->set_rules('email', lang('email'), 'required|valid_email|max_length[150]');
            $this->form_validation->set_rules('password', lang('password'), 'required|min_length[6]|max_length[100]');
            if ($this->form_validation->run()) {
                $email = strtolower(trim($this->input->post('email', true)));
                $user = $this->db->where('email', $email)->get('users')->row_array();
                if ($user && password_verify($this->input->post('password', false), $user['password_hash'])) {
                    if ($user['status'] !== STATUS_ACTIVE) {
                        $data['error'] = lang('account_inactive');
                        $this->render('auth/login', $data, 'auth');
                        return;
                    }
                    $this->authentication->login($user, $this->input->post('remember', false) === '1');
                    redirect('');
                }
                $this->db->insert('login_attempts', ['ip_address' => $this->input->ip_address()]);
                $data['error'] = lang('invalid_login');
            }
        }
        $this->render('auth/login', isset($data) ? $data : [], 'auth');
    }

    public function logout(): void
    {
        if ($this->input->method() === 'get') {
            if (!$this->currentUser) { redirect('login'); return; }
            $this->render('auth/logout');
            return;
        }
        $this->requirePost();
        try {
            $this->authentication->forget();
        } catch (\Throwable $exception) {
            log_message('error', 'Remembered logout could not be revoked: ' . $exception->getMessage());
        } finally {
            $this->session->sess_destroy();
        }
        if (strpos((string) $this->input->get_request_header('Accept', true), 'application/json') !== false) {
            $this->json(true, ['redirect' => site_url('login')]);
            return;
        }
        redirect('login');
    }
}
