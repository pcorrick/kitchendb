<?php
class Stock extends CI_Controller {

        public function __construct()
        {
                parent::__construct();
                $this->load->model('stock_model');
                $this->load->helper('url_helper');
        }

        public function index()
        {
            $data['stock'] = $this->stock_model->get_stock();
            $data['title'] = 'Stock';

            $this->load->view('templates/header', $data);
            $this->load->view('stock/index', $data);
            $this->load->view('templates/footer');
        }

    public function view($slug = NULL)
    {
        $data['stock_item'] = $this->stock_model->get_stock($slug);

        if (empty($data['stock_item']))
        {
                show_404();
        }

        $data['title'] = $data['stock_item']['title'];

        $this->load->view('templates/header', $data);
        $this->load->view('stock/view', $data);
        $this->load->view('templates/footer');
}
}
