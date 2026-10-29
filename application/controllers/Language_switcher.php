<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Language_switcher extends MY_Controller
{
    public function change(string $lang): void
    {
        $this->session->set_userdata('lang', $lang === 'en' ? 'en' : 'ar');
        redirect($this->input->server('HTTP_REFERER') ?: 'login');
    }
}
