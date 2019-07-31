<?php
class Home extends MY_Base {

    public function index()
    {
        $this->load->model('Execution');
        $this->load->helper('my_duration');

        //get all data from GCP
        $GCP_files_list = [];
        $url = 'https://storage.googleapis.com/prestashop-core-nightly/';
        try {
            $t = file_get_contents($url);
            $xml = new SimpleXMLElement($t);
            foreach($xml->Contents as $content) {
                if (strpos((string)$content->Key, '.zip') !== false) {
                    $GCP_files_list[] = (string)$content->Key;
                }
            }
            //var_dump($GCP_files_list);

        } catch(Exception $e) {
            log_message('warning', "couldn't fetch files from GCP");
        }

        //get all data from executions
        $execution_list = $this->Execution->getAllInformation();
        //get all versions
        $versions_list = $this->Execution->getVersions();

        $content_data = [
            'execution_list' => $execution_list,
            'versions_list' => $versions_list,
            'gcp_files_list' => $GCP_files_list
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
