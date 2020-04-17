<?php
namespace App\Controller;

use DI\NotFoundException;
use Exception;
use Illuminate\Database\Capsule\Manager;
use Illuminate\Support\Collection;
use Slim\Exception\HttpBadRequestException;
use Slim\Exception\HttpForbiddenException;
use Slim\Psr7\Request;
use Slim\Psr7\Response;
use Slim\Routing\RouteContext;

class ReportController extends BaseController
{
    private const FILTER_STATE_FAILED = 'failed';
    private const FILTER_STATE_PASSED = 'passed';
    private const FILTER_STATE_SKIPPED = 'skipped';
    private const FILTER_STATE_PENDING = 'pending';

    private $main_suite_id = null;
    private $suiteChildrenData = [
        'totalPasses' => 0,
        'totalFailures' => 0,
        'totalPending' => 0,
        'totalSkipped' => 0,
    ];

    private $browsers = [
        'chromium',
        'firefox',
        'edge'
    ];
    private $defaultBrowser = 'chromium';

    private $campaigns = [
        'functional',
        'sanity',
        'e2e',
        'regression'
    ];
    private $defaultCampaign = 'functional';

    private $paramsReportDefault = [
        'search' => null,
        'filter_state' => [
            self::FILTER_STATE_FAILED,
            self::FILTER_STATE_PASSED,
            self::FILTER_STATE_SKIPPED,
            self::FILTER_STATE_PENDING,
        ]
    ];

    private $paramsReport = [];

    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function index(Request $request, Response $response):Response {
        //get all data from GCP
        $GCP_files_list = $this->getDataFromGCP(QANB_GCPURL);

        //get all data from executions
        $executions = Manager::table('execution')->orderBy('start_date', 'desc')->get();

        $full_list = [];
        $orphan_builds_list = [];
        foreach($executions as $execution) {
            $download = null;
            if (isset($GCP_files_list[date('Y-m-d', strtotime($execution->start_date))][$execution->version])) {
                $download = QANB_GCPURL.$GCP_files_list[date('Y-m-d', strtotime($execution->start_date))][$execution->version];
                unset($GCP_files_list[date('Y-m-d', strtotime($execution->start_date))][$execution->version]);
            }
            $full_list[] = [
                'id' => $execution->id,
                'date' => date('Y-m-d', strtotime($execution->start_date)),
                'version' => $execution->version,
                'campaign' => $execution->campaign,
                'browser' => $execution->browser,
                'start_date' => $execution->start_date,
                'end_date' => $execution->end_date,
                'duration' => $execution->duration,
                'suites' => $execution->suites,
                'tests' => [
                    'total' => ($execution->tests),
                    'passed' => $execution->passes,
                    'failed' => $execution->failures,
                    'pending' => $execution->pending,
                    'skipped' => $execution->skipped,
                ],
                'broken_since_last' => $execution->broken_since_last,
                'fixed_since_last' => $execution->fixed_since_last,
                'equal_since_last' => $execution->equal_since_last,
                'download' => $download
            ];
        }
        //clean ugly array
        foreach($GCP_files_list as $date => $values) {
            foreach($values as $version => $build) {
                preg_match('/([0-9]{4}-[0-9]{2}-[0-9]{2})-([A-z0-9\.]*)-prestashop_(.*)\.zip/', $build, $matches_filename);
                if (count($matches_filename) == 4) {
                    $orphan_builds_list[] =
                        [
                            'date' => $matches_filename[1],
                            'version' => $matches_filename[2],
                            'download' => QANB_GCPURL.$build
                        ];
                }
            }
        }

        //merge two arrays in one and sort them by date
        $full_list = array_merge($full_list, $orphan_builds_list);
        usort($full_list, function ($dt1, $dt2) {
            $tm1 = isset($dt1['start_date']) ? $dt1['start_date'] : $dt1['date'];
            $tm2 = isset($dt2['start_date']) ? $dt2['start_date'] : $dt2['date'];
            return ($tm1 < $tm2) ? 1 : (($tm1 > $tm2) ? -1 : 0);
        });

        $response->getBody()->write(json_encode($full_list));
        return $response;
    }

    /**
     * Display a single report information
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws NotFoundException
     */
    public function report(Request $request, Response $response):Response {

        $routeContext = RouteContext::fromRequest($request);
        $route = $routeContext->getRoute();

        $report_id = $route->getArgument('report');

        $this->paramsReport = array_merge($this->paramsReportDefault, $request->getQueryParams());

        // Get all the data for this report
        $execution = Manager::table('execution')->find($report_id);

        if (!$execution) {
            //error
            throw new NotFoundException('Report not found');
        }
        $execution_data = [
            'id' => $execution->id,
            'date' => date('Y-m-d', strtotime($execution->start_date)),
            'version' => $execution->version,
            'campaign' => $execution->campaign,
            'browser' => $execution->browser,
            'start_date' => $execution->start_date,
            'end_date' => $execution->end_date,
            'duration' => $execution->duration,
            'suites' => $execution->suites,
            'tests' => $execution->tests,
            'broken_since_last' => $execution->broken_since_last,
            'fixed_since_last' => $execution->fixed_since_last,
            'equal_since_last' => $execution->equal_since_last,
            'skipped' => $execution->skipped,
            'pending' => $execution->pending,
            'passes' => $execution->passes,
            'failures' => $execution->failures
        ];

        $suites = $this->getReportData($report_id);
        $tests_data = $this->getTestData($report_id);
        //find the first suite ID
        foreach($suites as $suite) {
            if ($suite->parent_id == null) {
                $this->main_suite_id = $suite->id;
                break;
            }
        }
        //build the recursive tree
        $suites = $this->buildTree($suites, $tests_data, $this->main_suite_id);
        $suites = $this->getRootSuitesAggregatedData($suites);
        $suites = $this->filterSuitesByRootData($suites);
        $suites = $this->filterTree($suites);
        //put suites data into the final object
        $execution_data['suites_data'] = $suites;
        $response->getBody()->write(json_encode($execution_data));
        return $response;
    }

    /**
     * Filter suites by using root data (when using toggles)
     * @param $suites
     * @return mixed
     */
    private function filterSuitesByRootData($suites) {
        $paramsFilter = array_values($this->paramsReport['filter_state']);
        foreach($suites as $key => $root_suite) {
            //when the "failed" toggle is turned on
            if (in_array('failed', $paramsFilter) && $root_suite->childrenData['totalFailures'] > 0) {
                continue;
            }
            //when the "pending" toggle is turned on
            if (in_array('pending', $paramsFilter) && $root_suite->childrenData['totalPending'] > 0) {
                continue;
            }
            //when the "skipped" toggle is turned on
            if (in_array('skipped', $paramsFilter) && $root_suite->childrenData['totalSkipped'] > 0) {
                continue;
            }
            //when the "passed" toggle is turned on and we didn't accept this suite, it must only be shown if
            //it hasn't any pending or failed test
            //this prevents showing a suite with passed and failed test when we hide failed tests for example
            if (in_array('passed', $paramsFilter) && $root_suite->childrenData['totalPasses'] > 0
                && $root_suite->childrenData['totalFailures'] == 0
                && $root_suite->childrenData['totalSkipped'] == 0
                && $root_suite->childrenData['totalPending'] == 0) {
                continue;
            }
            unset($suites[$key]);
        }
        return $suites;
    }

    /**Filter the whole tree when using fulltext search
     * @param $suites
     * @param callable|null $function
     * @return array
     */
    private function filterTree($suites, callable $function = null): array
    {
        foreach ($suites as $key => &$suiteChild) {
            if (isset($suiteChild->suites) && is_array($suiteChild->suites)) {
                $suiteChild->suites = $this->filterTree($suiteChild->suites, array($this, 'filterSuite'));
            }
            if (empty($suiteChild->suites)
                && empty($suiteChild->tests)
                && ($this->filterSuite($suiteChild) && empty($this->paramsReport['search']))) {
                unset($suites[$key]);
            }
        }
        return array_filter($suites, array($this, 'filterSuite'));
    }

    /**
     * Filter suites
     *
     * @param \stdClass $suite
     * @return bool
     */
    private function filterSuite(\stdClass $suite): bool
    {
        $status = true;
        // If we need to search fulltext
        if ($status && !empty($this->paramsReport['search'])) {
            $status = $this->filterSuiteSearch($suite, $this->paramsReport['search']);
        }
        return $status;
    }

    /**
     * Filter each suite with text search in tests
     *
     * @param \stdClass $suite
     * @param string $text
     * @return bool
     */
    private function filterSuiteSearch(\stdClass $suite, string $text): bool
    {
        // Title
        if (stripos($suite->title, $text) !== false) {
            return true;
        }
        // Tests
        if (!empty($suite->tests)) {
            foreach ($suite->tests as $test) {
                if (stripos($test->title, $text) !== false) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Display a single suite information
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function suite(Request $request, Response $response):Response {
        $routeContext = RouteContext::fromRequest($request);
        $route = $routeContext->getRoute();

        $report_id = $route->getArgument('report');
        $suite_id = $route->getArgument('suite');

        //get suite data
        $root_suite = Manager::table('suite')
            ->where('execution_id', '=', $report_id)
            ->where('id', '=', $suite_id)
            ->first();
        //get tests for this root suite
        $tests = Manager::table('test')
            ->where('suite_id', '=', $suite_id)
            ->get();
        $root_suite->tests = $tests;

        $children_suites = $this->getReportData($report_id);
        $tests_data = $this->getTestData($report_id);
        //build the recursive tree
        $suites = $this->buildTree($children_suites, $tests_data, $suite_id);
        $root_suite->suites = $suites;
        //put suites data into the final object
        $response->getBody()->write(json_encode($root_suite));
        return $response;
    }

    /**
     * Insert a new report data in the database
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws HttpBadRequestException
     * @throws HttpForbiddenException
     */
    public function insert(Request $request, Response $response):Response {
        $get_query_params = $request->getQueryParams();
        $force = false;

        //check arguments in GET query
        if (!isset($get_query_params['token']) || !isset($get_query_params['filename'])) {
            throw new HttpBadRequestException($request, "no enough parameters");
        }
        //check token
        if ($get_query_params['token'] != getenv('QANB_TOKEN')) {
            throw new HttpBadRequestException($request, "invalid token");
        }
        //force parameter
        if (isset($get_query_params['force']) && $get_query_params['force'] == 'true') {
            $force = true;
        }

        //get browser and campaign info
        $browser = $this->defaultBrowser;
        $campaign = $this->defaultCampaign;
        if (isset($get_query_params['browser']) && in_array($get_query_params['browser'], $this->browsers)) {
            $browser = $get_query_params['browser'];
        }
        if (isset($get_query_params['campaign']) && in_array($get_query_params['campaign'], $this->campaigns)) {
            $campaign = $get_query_params['campaign'];
        }

        $filename = $get_query_params['filename'];

        //retrieving version number
        preg_match('/[0-9]{4}-[0-9]{2}-[0-9]{2}-(.*)?\.json/', $filename, $matches);
        if (!isset($matches[1])) {
            throw new HttpBadRequestException($request, "could not retrieve version from filename");
        }
        $version = $matches[1];
        if (strlen($matches[1]) < 1) {
            throw new HttpBadRequestException($request, sprintf("version found not correct (%s) from filename %s", $version, $filename));
        }
        $url = QANB_GCPURL.'reports/'.$filename;
        $contents = file_get_contents($url);
        if (!$contents) {
            throw new HttpBadRequestException($request, "unable to retrieve content from GCP URL");
        }
        //try to decode json
        $file_contents = json_decode($contents);
        if ($file_contents == NULL) {
            throw new HttpBadRequestException($request, "unable to decode JSON data");
        }
        //starting real stuff
        $stats = $file_contents->stats;
        $execution_data = [
            'ref' => date('YmdHis'),
            'filename' => $filename,
            'browser' => $browser,
            'campaign' => $campaign,
            'start_date' => date('Y-m-d H:i:s', strtotime($stats->start)),
            'end_date' => date('Y-m-d H:i:s', strtotime($stats->end)),
            'duration' => $stats->duration,
            'version' => $version,
            'skipped' => $stats->skipped,
            'pending' => $stats->pending,
            'passes' => $stats->passes,
            'failures' => $stats->failures,
            'suites' => $stats->suites,
            'tests' => $stats->tests
        ];

        //let's check if there's not a similar entry...
        $entry_date = date('Y-m-d', strtotime($stats->start));
        $similar = Manager::table('execution')
            ->where('version', '=', $version)
            ->where('browser', '=', $browser)
            ->where('campaign', '=', $campaign)
            ->whereDate('start_date', '=', $entry_date)
            ->first();
        if ($similar && !$force) {
            throw new HttpForbiddenException($request,
                sprintf("A similar entry was found (criteria: version %s, browser %s, campaign %s, date %s).",
                    $version, $browser, $campaign, $entry_date));
        }
        //insert execution
        $execution_id = Manager::table('execution')->insertGetId($execution_data);

        $this->loopThrough($execution_id, $file_contents->suites);

        $update_data = ['insertion_end_date' => Manager::Raw('NOW()')];

        //calculate comparison with last execution
        $comparison = $this->compareReportData($execution_id);
        if ($comparison) {
            $update_data['broken_since_last'] = $comparison['broken'];
            $update_data['fixed_since_last'] = $comparison['fixed'];
            $update_data['equal_since_last'] = $comparison['equal'];
        }

        Manager::table('execution')
            ->where('id', '=', $execution_id)
            ->update($update_data);

        $response->getBody()->write(json_encode([
            'status' => 'ok'
        ]));
        return $response;
    }

    /**
     * Method to render the whole suites tree
     * @param $suites
     * @param $tests_data
     * @param null $parent_id
     * @return array
     */
    private function buildTree($suites, $tests_data, $parent_id = null) {
        $branch = [];
        foreach ($suites as &$suite) {
            //add tests in suite
            if ($suite->hasTests == 1 && isset($tests_data[$suite->id])) {
                $suite->tests = $tests_data[$suite->id];
            }
            if ($suite->parent_id == $parent_id) {
                $children = $this->buildTree($suites, $tests_data, $suite->id);
                if ($children) {
                    $suite->suites = $children;
                }
                $branch[$suite->id] = $suite;
                unset($suite);
            }
        }
        return $branch;
    }

    /**
     * Loop through the whole suites tree to count all the tests children by state
     *
     * @param $suites
     * @return array
     */
    private function getRootSuitesAggregatedData($suites) {
        foreach($suites as $root_suite) {
            $this->suiteChildrenData = [
                'totalPasses' => 0,
                'totalFailures' => 0,
                'totalPending' => 0,
                'totalSkipped' => 0,
            ];
            $this->loopThroughSuiteData($root_suite);
            $root_suite->childrenData = $this->suiteChildrenData;
        }
        return $suites;
    }

    /**
     * Recursive function to map all the tests
     *
     * @param $suite
     */
    private function loopThroughSuiteData($suite) {
        $this->suiteChildrenData['totalPasses'] += $suite->totalPasses;
        $this->suiteChildrenData['totalFailures'] += $suite->totalFailures;
        $this->suiteChildrenData['totalPending'] += $suite->totalPending;
        $this->suiteChildrenData['totalSkipped'] += $suite->totalSkipped;
        if ($suite->hasSuites == 1) {
            foreach($suite->suites as $child_suite) {
                $this->loopThroughSuiteData($child_suite);
            }
        }
    }

    /**
     * Get all the suites data from an execution
     * @param $report_id
     * @return Collection
     */
    private function getReportData($report_id) {
        return Manager::table('suite')
            ->where('execution_id', '=', $report_id)
            ->orderBy('id')
            ->get();
    }

    /**
     * Get all the tests data from an execution
     * @param $report_id
     * @return array
     */
    private function getTestData($report_id) {
        $tests = Manager::table('test')
            ->join('suite', 'test.suite_id', '=', 'suite.id')
            ->where('suite.execution_id', '=', $report_id)
            ->select('test.*')
            ->get();
        $tests_data = [];
        foreach($tests as $test) {
            $tests_data[$test->suite_id][] = $test;
        }
        return $tests_data;
    }

    /**
     * Loop through data and insert it, recursive function
     * @param $execution_id
     * @param $suite
     * @param null $parent_suite_id
     */
    private function loopThrough($execution_id, $suite, $parent_suite_id = null) {

        $data_suite = [
            'execution_id' => $execution_id,
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
        $suite_id = Manager::table('suite')->insertGetId($data_suite);

        if ($suite_id) {
            //insert tests
            if (count($suite->tests) > 0) {
                foreach($suite->tests as $test) {
                    $identifier = '';
                    if (isset($test->context)) {
                        try {
                            $identifier_data = json_decode($test->context);
                            $identifier = $identifier_data->value;
                        } catch(Exception $e) {

                        }
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
                    Manager::table('test')->insertGetId($data_test);
                }
            }
            //insert children suites
            if (count($suite->suites) > 0) {
                foreach($suite->suites as $s) {
                    $this->loopThrough($execution_id, $s, $suite_id);
                }
            }
        }
    }

    /**
     *
     *
     * @param $id
     * @return array|bool
     */
    public function compareReportData($id) {
        //get version and start_date of the given report
        $tempData = Manager::table('execution')
            ->select(['version', 'start_date'])
            ->where('id', '=', $id)
            ->first();

        //get id of the precedent report
        $precedentReport = Manager::table('execution')
            ->select('id')
            ->where('version', '=', $tempData->version)
            ->where('start_date', '<', $tempData->start_date)
            ->orderBy('start_date', 'desc')
            ->first();
        if (!$precedentReport) {
            return false;
        }

        // get comparison data between current report and precedent report
        $data = Manager::table('test as t1')
            ->select([
                't1.state as old_test_state',
                't2.state as current_test_state',
            ])
            ->join('suite as s1', 's1.id', '=', 't1.suite_id')
            ->crossJoin('test as t2', function($join) {
                $join->on('t2.identifier', '=', 't1.identifier');
                $join->whereNotNull('t2.identifier');
                })
            ->join('suite as s2', 's2.id', '=', 't2.suite_id')
            ->where('s1.execution_id', '=', $precedentReport->id)
            ->where('s2.execution_id', '=', $id)
            ->where('t1.identifier', '!=', 'loginBO')
            ->where('t1.identifier', '!=', 'logoutBO')
            ->where(function ($query) {
                $query->where('t1.state', '=', 'failed')
                    ->orWhere('t2.state', '=', 'failed');
            })
            ->get();


        if (count($data) > 0) {
            $results = [
                'fixed' => 0,
                'broken' => 0,
                'equal' => 0,
            ];
            foreach($data as $line) {
                if ($line->old_test_state == 'failed' && $line->current_test_state == 'failed') {
                    $results['equal'] += 1;
                }
                if ($line->old_test_state == 'passed' && $line->current_test_state == 'failed') {
                    $results['broken'] += 1;
                }
                if ($line->old_test_state == 'failed' && $line->current_test_state == 'passed') {
                    $results['fixed'] += 1;
                }
            }
        } else {
            return false;
        }
        return $results;
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
        if ($test->pending == true) {
            return 'pending';
        }
        return 'unknown';
    }

    /**
     * Format data from GCP (list of builds)
     * @param $gcp_url
     * @return array
     */
    private function getDataFromGCP($gcp_url) {
        $GCP_files_list = [];
        $GCPCallResult = file_get_contents($gcp_url);
        if ($GCPCallResult) {
            $xml = new \SimpleXMLElement($GCPCallResult);
            foreach ($xml->Contents as $content) {
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
        }
        return $GCP_files_list;
    }
}
