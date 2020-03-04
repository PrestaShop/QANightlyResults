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
                $build_name = (string)$content->Key;
                if (strpos($build_name, '.zip') !== false) {
                    //get version and date
                    preg_match('/([0-9]{4}-[0-9]{2}-[0-9]{2})-([A-z0-9\.]*)-prestashop_(.*)\.zip/', $build_name, $matches_filename);
                    if (count($matches_filename) == 4) {
                        $date = $matches_filename[1];
                        $version = $matches_filename[2];
                        $GCP_files_list[$date][$version] = $build_name;
                    }
                }
            }
        } catch(Exception $e) {
            log_message('warning', "couldn't fetch files from GCP");
        }
        $full_list = [];
        //get all data from executions
        $executions = $this->Execution->getAllInformation();
        foreach($executions->result() as $execution) {
            $download = null;
            if (isset($GCP_files_list[date('Y-m-d', strtotime($execution->start_date))][$execution->version])) {
                $download = $gcp_url.$GCP_files_list[date('Y-m-d', strtotime($execution->start_date))][$execution->version];
                unset($GCP_files_list[date('Y-m-d', strtotime($execution->start_date))][$execution->version]);
            }
            $full_list[] = [
                'id' => $execution->id,
                'date' => date('Y-m-d', strtotime($execution->start_date)),
                'version' => $execution->version,
                'start_date' => $execution->start_date,
                'end_date' => $execution->end_date,
                'duration' => $execution->duration,
                'suites' => $execution->suites,
                'total' => $execution->tests,
                'passes' => $execution->passes,
                'failures' => $execution->failures,
                'pending' => $execution->pending,
                'skipped' => $execution->skipped,
                'broken_since_last' => $execution->broken_since_last,
                'fixed_since_last' => $execution->fixed_since_last,
                'equal_since_last' => $execution->equal_since_last,
                'download' => $download
            ];
        }
        //clean ugly array
        $orphan_builds_list = [];
        foreach($GCP_files_list as $date => $values) {
            foreach($values as $version => $build) {
                preg_match('/([0-9]{4}-[0-9]{2}-[0-9]{2})-([A-z0-9\.]*)-prestashop_(.*)\.zip/', $build, $matches_filename);
                if (count($matches_filename) == 4) {
                    $orphan_builds_list[] =
                        [
                            'date' => $matches_filename[1],
                            'version' => $matches_filename[2],
                            'download' => $gcp_url.$build
                        ];
                }
            }
        }
        //merge two arrays in one and sort them by date
        $full_list = array_merge($full_list, $orphan_builds_list);
        usort($full_list, function ($dt1, $dt2) {
            $tm1 = strtotime($dt1['date']);
            $tm2 = strtotime($dt2['date']);
            return ($tm1 < $tm2) ? 1 : (($tm1 > $tm2) ? -1 : 0);
        });

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
