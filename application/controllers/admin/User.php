<?php
defined('BASEPATH') OR exit('No direct script access allowed');
 
class User extends CI_Controller {
    function __construct() {
        parent::__construct();
        $this->load->add_package_path(APPPATH.'third_party/ion_auth/');
        $this->load->library('ion_auth');
    }
 
    public function index() {
    }
 
    public function login() {
        $this->data['page_title'] = 'Login';
        if($this->input->post()) {
            $this->load->library('form_validation');
            $this->form_validation->set_rules('identity', 'Identity', 'required');
            $this->form_validation->set_rules('password', 'Password', 'required');
            $this->form_validation->set_rules('remember','Remember me','integer');
            if($this->form_validation->run() === TRUE) {
                $remember = (bool) $this->input->post('remember');
                if ($this->ion_auth->login($this->input->post('identity'), $this->input->post('password'), $remember)) {
                    redirect('kitchendb', 'refresh');
                } else {
                    $this->session->set_flashdata('message',$this->ion_auth->errors());
                    redirect('admin/user/login', 'refresh');
                }
            }
        }
        $this->load->helper('form');
        $this->load->view('admin/login_view','admin_master');
    }
  
    public function logout() {
        $this->ion_auth->logout();
        redirect('admin/user/login', 'refresh');
    }
}