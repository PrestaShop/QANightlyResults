<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Hook extends MY_Base {

    private $execution_id = null;
    private $pattern = '/[0-9]{4}-[0-9]{2}-[0-9]{2}-(.*)?\.json/';
    private $force = false;

    public function add()
    {
        $this->load->model('Execution');
        $this->load->model('Suite');
        $this->load->model('Test');

        log_message('info', "verifying data");
        if (!$this->input->get('token') || !$this->input->get('filename')) {
            header($_SERVER['SERVER_PROTOCOL'] . ' 400 Bad Request', true, 400);
            exit("no enough parameters");
        }

        log_message('info', "verifying name of file (".$this->input->get('filename').")");
        preg_match($this->pattern, $this->input->get('filename'), $matches);
        if (!isset($matches[1])) {
            header($_SERVER['SERVER_PROTOCOL'] . ' 400 Bad Request', true, 400);
            exit("filename '".$this->input->get('filename')."' is invalid");
        }

        log_message('info', "verifying token is here");
        if (!$this->checkToken($this->input->get('token'))) {
            header($_SERVER['SERVER_PROTOCOL'] . ' 400 Bad Request', true, 400);
            exit("invalid token");
        }

        if ($this->input->get('force') !== NULL) {
            $this->force = true;
        }

        //get the file from the GCP API
        $filename = $this->input->get('filename');
        log_message('info', "receiving filename $filename");
        //create URL
        $url = sprintf("https://storage.googleapis.com/prestashop-core-nightly/reports/%s", $filename);
        log_message('info', "creating url $url");
        //retrieve content
        log_message('info', "retrieving content...");
        try {
            $contents = file_get_contents($url);
        } catch(Exception $e) {
            log_message('error', "Could not retrieve content from $url");
            header($_SERVER['SERVER_PROTOCOL'] . ' 400 Bad Request', true, 400);
            exit("unable to decode JSON data");
        }

        log_message('info', "decoding JSON...");
        try {
            $file_contents = json_decode($contents);
        } catch(Exception $e) {
            log_message('error', "Could not decode JSON data from the file");
            header($_SERVER['SERVER_PROTOCOL'] . ' 400 Bad Request', true, 400);
            exit("unable to decode JSON data");
        }

        //retrieving version number
        preg_match($this->pattern, $filename, $matches);
        if (!isset($matches[1])) {
            exit("could not retrieve version from filename '$filename'");
        }
        $version = $matches[1];
        if (strlen($matches[1]) < 1) {
            header($_SERVER['SERVER_PROTOCOL'] . ' 400 Bad Request', true, 400);
            exit("version found not correct ('$version') from filename '$filename'");
        }

        //starting real stuff
        //create the execution
        $stats = $file_contents->stats;
        $execution_data = [
            'ref' => date('YmdHis'),
            'start_date' => date('Y-m-d H:i:s', strtotime($stats->start)),
            'end_date' => date('Y-m-d H:i:s', strtotime($stats->end)),
            'duration' => $stats->duration,
            'version' => $version
        ];
        //let's check if there's not a similar entry...
        $entry_date = date('Y-m-d', strtotime($stats->start));
        $similar = $this->Execution->findSimilarEntries($entry_date, $version);

        if ($similar !== NULL) {
            if (!$this->force) {
                log_message('error', "A similar entry was found (criteria: version $version and date $entry_date)");
                header($_SERVER['SERVER_PROTOCOL'] . ' 409 Conflict', true, 409);
                exit("A similar entry was found (criteria: version '$version' and date '$entry_date'). Use the 'force' parameter to force insert");
            } else {
                log_message('warning', "A similar entry was found (criteria: version $version and date $entry_date) but FORCING insert anyway");
            }
        }

        try {
            log_message('info', "Inserting Execution");
            $this->execution_id = $this->Execution->insert($execution_data);
        } catch(Exception $e) {
            header($_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error', true, 500);
            exit("could not insert execution");
        }

        //launching into orbit
        $this->loopThrough($file_contents->suites);

        //get the data from the database itself
        $updated_data = $this->Execution->getSummaryData($this->execution_id);

        $update_data = [
            'skipped' => $updated_data->skipped,
            'suites' => $updated_data->suites,
            'tests' => $updated_data->tests,
            'passes' => $updated_data->passed,
            'failures' => $updated_data->failed,
            'insertion_end_date' => "NOW()"
        ];
        //update the execution row with updated data
        $this->Execution->update($update_data, $this->execution_id);
        header($_SERVER['SERVER_PROTOCOL'] . ' 200 OK', true, 200);
        echo json_encode(['status' => 'ok']);

    }

    /**
     * Check validity of token provided
     * @param $token
     * @return bool
     */
    private function checkToken($token) {
        return $token == getenv('QANB_TOKEN');
    }

    /**
     * Loop through data, recursive function
     * @param $suite
     * @param null $parent_suite_id
     */
    private function loopThrough($suite, $parent_suite_id = null) {

        $data_suite = [
            'execution_id' => $this->execution_id,
            'uuid' => $suite->uuid,
            'title' => $suite->title,
            'campaign' => $this->extractNames($suite->file, 'campaign'),
            'file' => $this->extractNames($suite->file, 'file'),
            'duration' => $suite->duration,
            'hasSkipped' => $suite->hasSkipped ? 1 :0,
            'hasPasses' => $suite->hasPasses ? 1 :0,
            'hasFailures' => $suite->hasFailures ? 1 :0,
            'totalSkipped' => $suite->totalSkipped,
            'totalPasses' => $suite->totalPasses,
            'totalFailures' => $suite->totalFailures,
            'hasSuites' => $suite->hasSuites ? 1 :0,
            'hasTests' => $suite->hasTests ? 1 :0,
            'parent_id' => $parent_suite_id,
        ];


        //inserting current suite
        $suite_id = $this->Suite->insert($data_suite);

        if ($suite_id) {
            //insert tests
            if (count($suite->tests) > 0) {
                foreach($suite->tests as $test) {
                    $data_test = [
                        'suite_id' => $suite_id,
                        'uuid' => $test->uuid,
                        'title' => $test->title,
                        'state' => $this->getTestState($test),
                        'duration' => $test->duration,
                        'error_message' => isset($test->err->message) ? $this->sanitize($test->err->message) : null,
                        'stack_trace' => isset($test->err->estack) ? $this->sanitize($test->err->estack) : null,
                        'diff' => isset($test->err->diff) ? $this->sanitize($test->err->diff) : null,
                    ];

                    $this->Test->insert($data_test);
                }
            }
            //insert children suites
            if (count($suite->suites) > 0) {
                foreach($suite->suites as $s) {
                    $this->loopThrough($s, $suite_id);
                }
            }
        } else {
            //we don't want to abort, just log this
            log_message("error", "Error inserting suite into database.");
        }
    }

    /**
     * Sanitize text by removing weird characters
     * @param $text
     * @return string
     */
    private function sanitize($text) {
        $StrArr = str_split($text);
        $NewStr = '';
        foreach ($StrArr as $Char) {
            $CharNo = ord($Char);
            if ($CharNo == 163) { $NewStr .= $Char; continue; }
            if ($CharNo > 31 && $CharNo < 127) {
                $NewStr .= $Char;
            }
        }
        return $NewStr;
    }

    /**
     * Extract campaign name and file name from json data
     * @param $filename
     * @param $type
     * @return mixed|null
     */
    private function extractNames($filename, $type)
    {
        if (strlen($filename) > 0) {
            $pattern = '/\/full\/(.*?)\/(.*)/';
            preg_match($pattern, $filename, $matches);
            if ($type == 'campaign') {
                return isset($matches[1]) ? $matches[1] : null;
            }

            if ($type == 'file') {
                return isset($matches[2]) ? $matches[2] : null;
            }
        }

        return null;
    }

    /**
     * Get the test state
     * @param $test
     * @return string
     */
    private function getTestState($test)
    {
        if (isset($test->state)) {
            return $test->state;
        }

        if ($test->skipped == true) {
            return 'skipped';
        }

        return 'unknown';
    }
}
