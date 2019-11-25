<?php
class Home extends MY_Base {

    public function index()
    {
        $this->load->model('Execution');
        $this->load->helper('my_duration');

        //get all data from GCP
        $GCP_files_list = [];
        $gcp_url = $this->config->item('GCP_URL');
        try {
            $t = file_get_contents($gcp_url);
            $xml = new SimpleXMLElement($t);
            foreach($xml->Contents as $content) {
                if (strpos((string)$content->Key, '.zip') !== false) {
                    $GCP_files_list[] = (string)$content->Key;
                }
            }

        } catch(Exception $e) {
            log_message('warning', "couldn't fetch files from GCP");
        }
        //get all data from executions
        $execution_list = $this->Execution->getAllInformation();

        $full_list = [];
        foreach($execution_list->result() as $execution) {
            $full_list[date('Y-m-d', strtotime($execution->start_date))][] = $execution;
        }
        foreach($GCP_files_list as $item) {
            preg_match('/([0-9]{4}-[0-9]{2}-[0-9]{2})-.*\.zip/', $item, $matches_filename);
            if (isset($full_list[$matches_filename[1]])) {
                //already stuff for this date, let's check it's not already in here
                foreach($full_list[$matches_filename[1]] as $element) {
                    if (is_object($element)) {
                        //get the filename
                        $filename = basename($element->filename, '.json');

                        if (strpos($item, $filename) === false) {
                            $full_list[$matches_filename[1]][] = $item;
                        }
                    }
                }
            } else {
                $full_list[$matches_filename[1]][] = $item;
            }
        }
        uksort($full_list, "compare_date_keys");

        $content_data = [
            'execution_list' => $full_list,
            'gcp_files_list' => $GCP_files_list,
            'gcp_url' => $gcp_url
        ];

        $header_data = [
            'title' => "Nightlies reports",
            'js' => ['/assets/js/jquery-3.4.1.min.js']
        ];

        $this->display('home', $content_data, $header_data);
    }
}
