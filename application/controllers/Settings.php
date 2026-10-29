<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Settings extends MY_Controller
{
    private const KEYS = ['company_name', 'invoice_prefix'];

    public function index(): void
    {
        $this->requireAdmin();
        $values = ['company_name' => 'SmartLife ERP', 'invoice_prefix' => 'INV'];
        foreach ($this->db->where_in('setting_key', self::KEYS)->get('settings')->result_array() as $row) {
            $values[$row['setting_key']] = $row['setting_value'];
        }
        $this->render('settings/index', ['settings' => $values]);
    }

    public function update(): void
    {
        $this->requireAdmin();
        $this->requirePost();
        $this->form_validation->set_rules('company_name', lang('company_name'), 'required|max_length[150]');
        $this->form_validation->set_rules('invoice_prefix', lang('invoice_prefix'), 'required|alpha_numeric|max_length[10]');
        if (!$this->form_validation->run()) { $this->index(); return; }
        foreach (self::KEYS as $key) {
            $this->db->query('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)', [$key, trim((string) $this->input->post($key, true))]);
        }
        $this->session->set_flashdata('flash', ['type' => 'success', 'message' => lang('settings_saved')]);
        redirect('settings');
    }
}
