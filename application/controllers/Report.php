<?php
class Report extends MY_Base {

    private $suites_content = '';
    private $suites = [];

    public function show($id)
    {
        $this->load->model('Execution');
        $this->load->model('Suite');
        $this->load->model('Test');
        $this->load->helper('url');
        $this->load->helper('my_duration_helper');

        //get the current execution
        if (!is_numeric($id)) {
            return redirect('/');
        }

        //attemps to retrieve the execution
        $execution = $this->Execution->find($id);

        //data for the summary and the navigation
        $campaignsAndFiles = $this->Suite->getAllCampaignsAndFilesByExecutionId($id);

        $details = $this->Execution->getExecutionPreciseStats($id);

        $content_data = [
            'execution' => $execution,
            'summaryData' => $campaignsAndFiles,
            'details' => $details
        ];

        $header_data = [
            'title' => "Report",
            'js' => ['https://code.jquery.com/jquery-3.4.1.min.js']
        ];
        $this->display('report', $content_data, $header_data);
    }

    public function getSuiteData()
    {
        $this->load->model('Execution');
        $this->load->model('Suite');
        $this->load->model('Test');

        //get the data
        $campaign = $this->input->get('campaign');
        $file = $this->input->get('file');
        $execution_id = $this->input->get('execution_id');

        if (!$campaign || !$file || !$execution_id) {
            return false;
        }

        $suites = $this->Suite->getAllSuitesByFile($execution_id, $campaign, $file);

        $tests = $this->Test->getAllTestsByFile($execution_id, $campaign, $file);

        if ($suites->num_rows() > 0 && $tests->num_rows() > 0) {
            //add tests in each suite
            foreach($suites->result() as $suite) {
                $suite->tests = [];
                foreach($tests->result() as $test) {
                    if ($test->suite_id == $suite->id) {
                        $suite->tests[] = $test;
                        unset($test);
                    }
                }
            }

            //recreate the suite tree
            $this->suites = $suites;
            $suites_tree = $this->buildTree($suites->row()->parent_id);

            //create the display
            $this->loop_through($suites_tree);

            $this->load->view('ajax_suite_data', ['content' => $this->suites_content]);

        } else {
            http_response_code(403);
            echo json_encode(['message' => 'No suite or tests found']);
            return false;
        }
    }

    private function buildTree($parentId = null) {
        $branch = array();
        foreach ($this->suites->result() as &$suite) {

            if ($suite->parent_id == $parentId) {
                $children = $this->buildTree($suite->id);
                if ($children) {
                    $suite->suites = $children;
                }
                $branch[$suite->id] = $suite;
                unset($suite);
            }
        }
        return $branch;
    }

    private function loop_through($cur_suites) {
        $this->load->helper('my_duration');

        foreach($cur_suites as $suite) {
            $this->suites_content .= '<section class="suite '.($suite->hasFailures ? 'hasFailed' : '').' '.($suite->hasPasses ? 'hasPassed' : '').'">';
            $this->suites_content .= '<header class="suite_header">';
            $this->suites_content .= '<h3 class="suite_title">' . $suite->title . '</h3>';
            if (count($suite->tests) > 0) {
                $this->suites_content .= '<div class="campaign">' . $suite->campaign . '/<span class="filename">' . $suite->file . '</span></div>';
            }

            if (count($suite->tests) > 0) {
                $this->suites_content .= '<div class="informations">';
                $this->suites_content .= '<div class="block_info"><i class="material-icons">timer</i> <div class="info duration">'.format_duration($suite->duration).'</div></div>';
                $this->suites_content .= '<div class="block_info"><i class="material-icons">assignment</i> <div class="info number_tests"> '.count($suite->tests).'</div></div>';
                //get number of passed
                if ($suite->totalPasses > 0) {
                    $this->suites_content .= '<div class="block_info tests_passed"><i class="material-icons">check</i> <div class="info ">'.$suite->totalPasses.'</div></div>';
                }
                if ($suite->totalFailures > 0) {
                    $this->suites_content .= '<div class="block_info tests_failed"><i class="material-icons">close</i> <div class="info ">'.$suite->totalFailures.'</div></div>';
                }
                if ($suite->totalSkipped> 0) {
                    $this->suites_content .= '<div class="block_info tests_skipped"><i class="material-icons">skip_next</i> <div class="info ">'.$suite->totalSkipped.'</div></div>';
                }
                $this->suites_content .= '<div class="metric_container">';
                $this->suites_content .= '<div class="metric">';
                $this->suites_content .= '<div class="background"><div class="metric_number">'.(round($suite->totalPasses/count($suite->tests), 3) * 100).'%</div><div class="advancement" style="width:'.(round($suite->totalPasses/count($suite->tests), 3) * 100).'%"></div></div>';
                $this->suites_content .= '</div>';
                $this->suites_content .= '</div>';

                $this->suites_content .= '</div>';
            }

            $this->suites_content .= '</header>';
            if (count($suite->tests) > 0) {
                $this->suites_content .= '<div class="test_container">';
                foreach ($suite->tests as $test) {
                    $icon = '';
                    if ($test->state == 'passed') {
                        $icon = '<i class="icon material-icons">check_circle</i>';
                    }
                    if ($test->state == 'failed') {
                        $icon = '<i class="icon material-icons">remove_circle</i>';
                    }
                    if ($test->state == 'skipped') {
                        $icon = '<i class="icon material-icons">error</i>';
                    }
                    $this->suites_content .= '<section class="test_component '.$test->state.'">';
                    $this->suites_content .= '<div class="block_test">';
                    $this->suites_content .= '<div id="' . $test->uuid . '" class="test"><div class="test_' . $test->state . '"> ' .$icon.' <span class="test_title" id="' . $test->uuid . '">'.$test->title . '</span></div>';
                    $this->suites_content .= '<div class="test_duration"><i class="material-icons">timer</i> '.format_duration($test->duration).'</div>';
                    if ($test->state == 'failed') {
                        $this->suites_content .= '<div class="test_info error_message">' . htmlentities($test->error_message) . '</div>';
                        $this->suites_content .= '<div class="test_info stack_trace" id="stack_'.$test->uuid.'"><code>'.str_replace('    at', "<br />&nbsp;&nbsp;&nbsp;&nbsp;at", htmlentities($test->stack_trace)).'</code></div>';
                    }

                    $this->suites_content .= '</div>'; //uuid
                    $this->suites_content .= '</div>'; //block_test
                    $this->suites_content .= '</section>'; //test_component
                }
                $this->suites_content .= '</div>';
            }
            if ($suite->hasSuites) {
                $this->loop_through($suite->suites);
            }
            $this->suites_content .= '</section>';
        }
    }
}
