<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Auth extends MY_Controller
{
    public function login(): void
    {
        if ($this->currentUser) { redirect(''); }
        if ($this->input->method() === 'post') {
            $this->db->where('attempted_at >=', date('Y-m-d H:i:s', time() - 900))->delete('login_attempts');
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
                $user = $this->db->where('email', $email)->where('status', STATUS_ACTIVE)->get('users')->row_array();
                if ($user && password_verify($this->input->post('password', false), $user['password_hash'])) {
                    $this->session->sess_regenerate(true);
                    $this->session->set_userdata('user', ['id' => (int) $user['id'], 'name' => $user['name'], 'role' => $user['role'], 'warehouse_id' => $user['warehouse_id']]);
                    $this->session->set_userdata('lang', $user['preferred_lang']);
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
        $this->requirePost();
        $this->session->sess_destroy();
        redirect('login');
    }
}
