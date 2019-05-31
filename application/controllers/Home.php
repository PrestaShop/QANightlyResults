<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Home extends CI_Controller {

    public function index()
    {
        $this->load->model('Execution');
        $this->load->helper('my_duration');


        //get all data from executions
        $execution_list = $this->Execution->getAllInformation();
        //get all versions
        $versions_list = $this->Execution->getVersions();

        $content_data = [
            'execution_list' => $execution_list,
            'versions_list' => $versions_list
        ];

        $header_data = [
            'title' => "Nightlies reports"
        ];
        $this->load->view('templates/header', $header_data);
        $this->load->view('home', $content_data);
        $this->load->view('templates/footer');
    }
}
