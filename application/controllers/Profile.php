<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Profile extends MY_Controller
{
    public function index(): void
    {
        $this->requireAuth();
        $user = $this->db->where('id', (int) $this->currentUser['id'])->get('users')->row_array();
        if (!$user) { $this->session->sess_destroy(); redirect('login'); }
        $this->render('profile/index', ['user' => $user]);
    }

    public function update(): void
    {
        $this->requireAuth();
        $this->requirePost();
        $user = $this->db->where('id', (int) $this->currentUser['id'])->get('users')->row_array();
        if (!$user) { show_error(lang('forbidden'), 403); }

        $this->form_validation->set_rules('name', lang('name'), 'required|max_length[150]');
        $this->form_validation->set_rules('email', lang('email'), 'required|valid_email|max_length[150]');
        $newPassword = (string) $this->input->post('new_password', false);
        if ($newPassword !== '') {
            $this->form_validation->set_rules('current_password', lang('current_password'), 'required');
            $this->form_validation->set_rules('new_password', lang('new_password'), 'min_length[8]|max_length[100]');
            $this->form_validation->set_rules('new_password_confirm', lang('confirm_password'), 'required|matches[new_password]');
        }
        if (!$this->form_validation->run()) { $this->render('profile/index', ['user' => $user]); return; }

        $email = strtolower(trim((string) $this->input->post('email', true)));
        if ($this->db->where('email', $email)->where('id !=', (int) $user['id'])->count_all_results('users')) {
            $this->session->set_flashdata('flash', ['type' => 'error', 'message' => lang('email_taken')]);
            redirect('profile');
        }
        if ($newPassword !== '' && !password_verify((string) $this->input->post('current_password', false), $user['password_hash'])) {
            $this->session->set_flashdata('flash', ['type' => 'error', 'message' => lang('current_password_incorrect')]);
            redirect('profile');
        }

        $data = ['name' => trim((string) $this->input->post('name', true)), 'email' => $email, 'preferred_lang' => $this->input->post('preferred_lang', true) === 'en' ? 'en' : 'ar'];
        if ($newPassword !== '') { $data['password_hash'] = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]); }
        $this->db->where('id', (int) $user['id'])->update('users', $data);
        if ($newPassword !== '') {
            $this->authentication->revokeUser((int) $user['id']);
            $this->authentication->forget();
        }
        $this->authentication->refresh(array_replace($user, $data));
        $this->session->set_flashdata('flash', ['type' => 'success', 'message' => lang('profile_saved')]);
        redirect('profile');
    }
}
