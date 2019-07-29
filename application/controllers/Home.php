<?php
class Home extends MY_Base {

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
            'title' => "Nightlies reports",
            'js' => ['https://code.jquery.com/jquery-3.4.1.min.js']
        ];
        $this->load->view('templates/header', $header_data);
        $this->load->view('home', $content_data);
        $this->load->view('templates/footer');
    }
}
