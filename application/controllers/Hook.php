<?php
class Hook extends MY_Base {

    private $execution_id = null;
    private $pattern = '/[0-9]{4}-[0-9]{2}-[0-9]{2}-(.*)?\.json/';
    private $force = false;
    private $GCPURL = '';

    public function add()
    {
        $this->load->model('Execution');
        $this->load->model('Suite');
        $this->load->model('Test');

        //is there a GCP URL in environment variable ?
        $this->GCPURL = $this->config->item('GCP_URL').'reports/';
        log_message('info', '"verifying data');
        if (!$this->input->get('token') || !$this->input->get('filename')) {
            $this->setHeaders(400);
            exit(json_encode(['error' => 'no enough parameters']));
        }

        log_message('info', '"verifying name of file');
        preg_match($this->pattern, $this->input->get('filename'), $matches);
        if (!isset($matches[1])) {
            $this->setHeaders(400);
            exit(json_encode(['error' => 'filename is invalid']));
        }

        log_message('info', 'verifying token is valid');
        if (!$this->checkToken($this->input->get('token'))) {
            $this->setHeaders(400);
            exit(json_encode(['error' => 'invalid token']));
        }

        if ($this->input->get('force') && ($this->input->get('force') !== 'false')) {
            $this->force = true;
        }

        //store filename
        $filename = $this->input->get('filename');

        //retrieving version number
        preg_match($this->pattern, $filename, $matches);
        if (!isset($matches[1])) {
            exit(json_encode(['error' => 'could not retrieve version from filename']));
        }
        $version = $matches[1];
        if (strlen($matches[1]) < 1) {
            $this->setHeaders(400);
            exit(json_encode(['error' => sprintf("version found not correct (%s) from filename %s", $version, $filename)]));
        }

        //get the file from the GCP API
        log_message('info', 'receiving filename '.$filename);
        //create URL
        $url = $this->GCPURL.$filename;
        //retrieve content
        log_message('info', 'retrieving content from '.$url);

        try {
            $contents = file_get_contents($url);
        } catch(Exception $e) {
            log_message('error', 'Could not retrieve content from '.$url);
            $this->setHeaders(400);
            exit(json_encode(['error' => 'unable to retrieve content from GCP URL']));
        }

        log_message('info', "decoding JSON...");
        try {
            $file_contents = json_decode($contents);
        } catch(Exception $e) {
            log_message('error', "Could not decode JSON data from the file");
            $this->setHeaders(400);
            exit(json_encode(['error' => 'unable to decode JSON data']));
        }

        if ($file_contents == NULL) {
            $this->setHeaders(400);
            exit(json_encode(['error' => 'unable to decode JSON data']));
        }

        //starting real stuff
        //create the execution
        $stats = $file_contents->stats;
        $execution_data = [
            'ref' => date('YmdHis'),
            'filename' => $filename,
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
                log_message('error', 'A similar entry was found (criteria: version '.$version.' and date '.$entry_date);
                $this->setHeaders(409);
                exit(json_encode(['error' => sprintf("A similar entry was found (criteria: version %s and date %s). Use the force parameter to force insert", $version, $entry_date)]));
            } else {
                log_message('warning', 'A similar entry was found (criteria: version '.$version.' and date '.$entry_date.') but FORCING insert anyway');
            }
        }

        try {
            log_message('info', "Inserting Execution");
            $this->execution_id = $this->Execution->insert($execution_data);
        } catch(Exception $e) {
            $this->setHeaders(500);
            exit(json_encode(['error' => 'failed to insert execution']));
        }

        //launching into orbit
        $this->loopThrough($file_contents->suites);

        //get the data from the database itself
        $updated_data = $this->Execution->getSummaryData($this->execution_id);

        $update_data = [
            'skipped' => $updated_data->skipped,
            'pending' => $updated_data->pending,
            'suites' => $updated_data->suites,
            'tests' => $updated_data->tests,
            'passes' => $updated_data->passed,
            'failures' => $updated_data->failed,
            'insertion_end_date' => 'NOW()'
        ];

        //get comparison data
        $comparison = $this->compareReportData($this->execution_id);
        if (count($comparison) > 0) {
            $update_data['broken_since_last'] = $comparison['broken'];
            $update_data['fixed_since_last'] = $comparison['fixed'];
            $update_data['equal_since_last'] = $comparison['equal'];
        }

        //update the execution row with updated data
        $this->Execution->update($update_data, $this->execution_id);
        $this->setHeaders(200);
        echo json_encode(['status' => 'ok']);
    }

    /**
     * Get comparison of failed tests with last execution
     *
     * @param $id
     * @return array
     */
    public function compareReportData($id) {
        $this->load->model('Execution');
        //get data for the precedent report
        $precedentReport = $this->Execution->getPrecedentReport($id);
        if (!$precedentReport) {
            return [];
        }
        $data = $this->Execution->compareDataWithPrecedentReport($id, $precedentReport->id);
        if (count($data) > 0) {
            $results = [
                'fixed' => 0,
                'broken' => 0,
                'equal' => 0,
            ];
            foreach($data as $line) {
                if ($line['old_test_state'] == 'failed' && $line['current_test_state'] == 'failed') {
                    $results['equal'] += 1;
                }
                if ($line['old_test_state'] == 'passed' && $line['current_test_state'] == 'failed') {
                    $results['broken'] += 1;
                }
                if ($line['old_test_state'] == 'failed' && $line['current_test_state'] == 'passed') {
                    $results['fixed'] += 1;
                }
            }
        } else {
            return [];
        }
        return $results;
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
            'hasPending' => $suite->hasPending ? 1 :0,
            'hasPasses' => $suite->hasPasses ? 1 :0,
            'hasFailures' => $suite->hasFailures ? 1 :0,
            'totalSkipped' => $suite->totalSkipped,
            'totalPending' => $suite->totalPending,
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
                    $identifier = '';
                    if (isset($test->context)) {
                        $identifier_data = json_decode($test->context);
                        $identifier = $identifier_data->value;
                    }
                    $data_test = [
                        'suite_id' => $suite_id,
                        'uuid' => $test->uuid,
                        'identifier' => $identifier,
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
        if (strlen($filename) == 0) {
            return null;
        }
        if (strpos($filename, '/full/') !== false) {
            //selenium
            $pattern = '/\/full\/(.*?)\/(.*)/';
            preg_match($pattern, $filename, $matches);
            if ($type == 'campaign') {
                return isset($matches[1]) ? $matches[1] : null;
            }

            if ($type == 'file') {
                return isset($matches[2]) ? $matches[2] : null;
            }
        } else {
            //puppeteer
            $pattern = '/\/campaigns\/(.*?)\/(.*?)\/(.*)/';
            preg_match($pattern, $filename, $matches);
            if ($type == 'campaign') {
                return isset($matches[2]) ? $matches[2] : null;
            }

            if ($type == 'file') {
                return isset($matches[3]) ? $matches[3] : null;
            }
        }
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

        if ($test->pending == true) {
            return 'pending';
        }

        return 'unknown';
    }

    /**
     * Set headers to change http status code
     * @param $code
     */
    private function setHeaders($code)
    {
        $headersCodes = [
            200 => 'OK',
            400 => 'Bad Request',
            409 => 'Conflict',
            500 => 'Internal Server Error'
        ];
        if (in_array($code, array_keys($headersCodes))) {
            header($_SERVER['SERVER_PROTOCOL'] . ' '.$code.' '.$headersCodes[$code], true, $code);
        }
    }
}
