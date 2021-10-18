<?php

declare(strict_types=1);

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
use stdClass;

class ReportController extends BaseController
{
    private const FILTER_STATE_FAILED = 'failed';
    private const FILTER_STATE_PASSED = 'passed';
    private const FILTER_STATE_SKIPPED = 'skipped';
    private const FILTER_STATE_PENDING = 'pending';

    private const FILTER_PLATFORMS = ['chromium', 'firefox', 'webkit', 'cli'];

    private const FILTER_CAMPAIGNS = ['functional', 'sanity', 'e2e', 'regression', 'autoupgrade'];

    private $mainSuiteId = null;
    private $suiteChildrenData = [
        'totalPasses' => 0,
        'totalFailures' => 0,
        'totalPending' => 0,
        'totalSkipped' => 0,
    ];

    private $paramsReportDefault = [
        'search' => null,
        'filter_state' => [
            self::FILTER_STATE_FAILED,
            self::FILTER_STATE_PASSED,
            self::FILTER_STATE_SKIPPED,
            self::FILTER_STATE_PENDING,
        ],
    ];

    private $paramsReport = [];

    /**
     * @param Request $request
     * @param Response $response
     *
     * @return Response
     */
    public function index(Request $request, Response $response): Response
    {
        $requestPlatform = $request->getQueryParams()['filter_platform']
            ?? ($request->getQueryParams()['filter_browser'] ?? false);

        $requestCampaign = isset($request->getQueryParams()['filter_campaign']) ?
            $request->getQueryParams()['filter_campaign'] : false;

        $requestVersion = isset($request->getQueryParams()['filter_version']) ?
            $request->getQueryParams()['filter_version'] : false;

        //get all data from executions
        $executions = Manager::table('execution');
        if ($requestPlatform) {
            $executions = $executions->where('platform', '=', $requestPlatform);
        }
        if ($requestCampaign) {
            $executions = $executions->where('campaign', '=', $requestCampaign);
        }
        if ($requestVersion) {
            $executions = $executions->where('version', '=', $requestVersion);
        }
        $executions = $executions
            ->orderBy('start_date', 'desc')
            ->get();

        $GCP_files_list = [];
        if (!$requestPlatform && !$requestCampaign) {
            //get all data from GCP
            //no need to get these data if we filtered by platform or campaign
            $GCP_files_list = $this->getDataFromGCP(QANB_GCPURL);
        }

        $full_list = [];
        foreach ($executions as $execution) {
            $download = null;
            if (isset($GCP_files_list[date('Y-m-d', strtotime($execution->start_date))][$execution->version])) {
                $download = QANB_GCPURL . $GCP_files_list[date('Y-m-d', strtotime($execution->start_date))][$execution->version];
            }

            $full_list[] = [
                'id' => $execution->id,
                'date' => date('Y-m-d', strtotime($execution->start_date)),
                'version' => $execution->version,
                'campaign' => $execution->campaign,
                'browser' => $execution->platform, // retro-compatibility
                'platform' => $execution->platform,
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
                'download' => $download,
            ];
        }

        //merge two arrays in one and sort them by date
        usort($full_list, function ($dt1, $dt2) {
            $tm1 = isset($dt1['start_date']) ? $dt1['start_date'] : $dt1['date'];
            $tm2 = isset($dt2['start_date']) ? $dt2['start_date'] : $dt2['date'];

            return ($tm1 < $tm2) ? 1 : (($tm1 > $tm2) ? -1 : 0);
        });

        $response->getBody()->write(json_encode($full_list));

        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Display a single report information
     *
     * @param Request $request
     * @param Response $response
     *
     * @return Response
     *
     * @throws NotFoundException
     */
    public function report(Request $request, Response $response): Response
    {
        $routeContext = RouteContext::fromRequest($request);
        $route = $routeContext->getRoute();

        $report_id = (int) $route->getArgument('report');

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
            'browser' => $execution->platform, // retro-compatibility
            'platform' => $execution->platform,
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
            'failures' => $execution->failures,
        ];

        $suites = $this->getReportData($report_id);
        $testsData = $this->getTestData($report_id);

        // Find if there is main suite id
        $hasOnlyOneMainSuite = false;
        foreach ($suites as $suite) {
            if ($suite->parent_id === null) {
                if ($hasOnlyOneMainSuite === false) {
                    $hasOnlyOneMainSuite = true;
                    $this->mainSuiteId = $suite->id;
                } else {
                    // There is another suite with null, so not only one is used
                    // Used for legacy purpose
                    $hasOnlyOneMainSuite = false;
                    $this->mainSuiteId = null;
                    break;
                }
            }
        }

        //build the recursive tree
        $suites = $this->buildTree($suites, $testsData, $this->mainSuiteId);
        $suites = $this->getRootSuitesAggregatedData($suites);
        $suites = $this->filterSuitesByRootData($suites);
        $suites = $this->filterTree($suites);
        //put suites data into the final object
        $execution_data['suites_data'] = $suites;
        $response->getBody()->write(json_encode($execution_data));

        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Delete a report - needs the token
     *
     * @param Request $request
     * @param Response $response
     *
     * @return Response
     *
     * @throws HttpBadRequestException
     * @throws NotFoundException
     */
    public function delete(Request $request, Response $response): Response
    {
        $this->authenticateHeaderToken($request);
        //check if report exists
        $routeContext = RouteContext::fromRequest($request);
        $route = $routeContext->getRoute();
        $report_id = $route->getArgument('report');
        $execution = Manager::table('execution')->find($report_id);

        if (!$execution) {
            throw new NotFoundException('Report not found');
        }

        //here we go...
        $delete = Manager::table('execution')->where('id', '=', $report_id)->delete();

        $response->getBody()->write(json_encode([
            'status' => 'ok',
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Display a single suite information
     *
     * @param Request $request
     * @param Response $response
     *
     * @return Response
     */
    public function suite(Request $request, Response $response): Response
    {
        $routeContext = RouteContext::fromRequest($request);
        $route = $routeContext->getRoute();

        $report_id = (int) $route->getArgument('report');
        $suiteId = (int) $route->getArgument('suite');

        //get suite data
        $root_suite = Manager::table('suite')
            ->where('execution_id', '=', $report_id)
            ->where('id', '=', $suiteId)
            ->first();

        if (!$root_suite) {
            throw new NotFoundException('Suite not found for this execution');
        }

        //get tests for this root suite
        $tests = Manager::table('test')
            ->where('suite_id', '=', $suiteId)
            ->get();
        $root_suite->tests = $tests;

        $children_suites = $this->getReportData($report_id);
        $testsData = $this->getTestData($report_id);
        //build the recursive tree
        $suites = $this->buildTree($children_suites, $testsData, $suiteId);
        $root_suite->suites = $suites;
        //put suites data into the final object
        $response->getBody()->write(json_encode($root_suite));

        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Import a new report data in the database
     *
     * @param Request $request
     * @param Response $response
     *
     * @return Response
     *
     * @throws HttpBadRequestException

     * @throws HttpForbiddenException
     */
    public function import(Request $request, Response $response): Response
    {
        $getQueryParams = $request->getQueryParams();
        $this->checkAuth($getQueryParams, $request);
        $force = isset($getQueryParams['force']) && $getQueryParams['force'] == 'true';
        $platform = $this->getPlatform($getQueryParams);
        $campaign = $this->getCampaign($getQueryParams);
        $filename = $getQueryParams['filename'];

        $version = $this->getNumberVersion($filename, $request);
        $fileContents = $this->getContents($filename, $request);

        //starting real stuff
        $stats = $fileContents->stats;
        $execution_data = [
            'ref' => date('YmdHis'),
            'filename' => $filename,
            'platform' => $platform,
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
            'tests' => $stats->tests,
        ];

        //let's check if there's not a similar entry...
        $entry_date = date('Y-m-d', strtotime($stats->start));
        $similar = Manager::table('execution')
            ->where('version', '=', $version)
            ->where('platform', '=', $platform)
            ->where('campaign', '=', $campaign)
            ->whereDate('start_date', '=', $entry_date)
            ->first();
        if ($similar && !$force) {
            throw new HttpForbiddenException($request, sprintf('A similar entry was found (criteria: version %s, platform %s, campaign %s, date %s).', $version, $platform, $campaign, $entry_date));
        }
        //insert execution
        $executionId = Manager::table('execution')->insertGetId($execution_data);

        foreach ($fileContents->results as $suite) {
            $this->loopThroughSuite($executionId, $suite);
        }

        $update_data = ['insertion_end_date' => Manager::Raw('NOW()')];

        //calculate comparison with last execution
        $comparison = $this->compareReportData($executionId);
        if ($comparison) {
            $update_data['broken_since_last'] = $comparison['broken'];
            $update_data['fixed_since_last'] = $comparison['fixed'];
            $update_data['equal_since_last'] = $comparison['equal'];
        }

        Manager::table('execution')
            ->where('id', '=', $executionId)
            ->update($update_data);

        $response->getBody()->write(json_encode([
            'status' => 'ok',
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Insert a new report data in the database
     *
     * @param Request $request
     * @param Response $response
     *
     * @return Response
     *
     * @throws HttpBadRequestException
     * @throws HttpForbiddenException
     */
    public function insert(Request $request, Response $response): Response
    {
        $getQueryParams = $request->getQueryParams();
        $this->checkAuth($getQueryParams, $request);
        $force = isset($getQueryParams['force']) && $getQueryParams['force'] == 'true';
        $platform = $this->getPlatform($getQueryParams);
        $campaign = $this->getCampaign($getQueryParams);
        $filename = $getQueryParams['filename'];

        $version = $this->getNumberVersion($filename, $request);
        $fileContents = $this->getContents($filename, $request);

        //starting real stuff
        $stats = $fileContents->stats;
        $execution_data = [
            'ref' => date('YmdHis'),
            'filename' => $filename,
            'platform' => $platform,
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
            'tests' => $stats->tests,
        ];

        //let's check if there's not a similar entry...
        $entry_date = date('Y-m-d', strtotime($stats->start));
        $similar = Manager::table('execution')
            ->where('version', '=', $version)
            ->where('platform', '=', $platform)
            ->where('campaign', '=', $campaign)
            ->whereDate('start_date', '=', $entry_date)
            ->first();
        if ($similar && !$force) {
            throw new HttpForbiddenException($request, sprintf('A similar entry was found (criteria: version %s, platform %s, campaign %s, date %s).', $version, $platform, $campaign, $entry_date));
        }
        //insert execution
        $executionId = Manager::table('execution')->insertGetId($execution_data);

        $this->loopThrough($executionId, $fileContents->suites);

        $update_data = ['insertion_end_date' => Manager::Raw('NOW()')];

        //calculate comparison with last execution
        $comparison = $this->compareReportData($executionId);
        if ($comparison) {
            $update_data['broken_since_last'] = $comparison['broken'];
            $update_data['fixed_since_last'] = $comparison['fixed'];
            $update_data['equal_since_last'] = $comparison['equal'];
        }

        Manager::table('execution')
            ->where('id', '=', $executionId)
            ->update($update_data);

        $response->getBody()->write(json_encode([
            'status' => 'ok',
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Verify the token in headers
     *
     * @param Request $request
     *
     * @throws HttpBadRequestException
     */
    private function authenticateHeaderToken(Request $request)
    {
        $headerValueArray = $request->getHeader('QANB_TOKEN');
        //check token
        if (!isset($headerValueArray[0]) || $headerValueArray[0] != getenv('QANB_TOKEN')) {
            throw new HttpBadRequestException($request, 'invalid token');
        }
    }

    /**
     * Filter suites by using root data (when using toggles)
     */
    private function filterSuitesByRootData(array $suites): array
    {
        $paramsFilter = array_values($this->paramsReport['filter_state']);
        foreach ($suites as $key => $root_suite) {
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
                $suiteChild->suites = $this->filterTree($suiteChild->suites, [$this, 'filterSuite']);
            }
            if (empty($suiteChild->suites)
                && empty($suiteChild->tests)
                && ($this->filterSuite($suiteChild) && empty($this->paramsReport['search']))) {
                unset($suites[$key]);
            }
        }

        return array_filter($suites, [$this, 'filterSuite']);
    }

    /**
     * Filter suites
     *
     * @return bool
     */
    private function filterSuite(stdClass $suite): bool
    {
        $status = true;
        // If we need to search fulltext
        if (!empty($this->paramsReport['search'])) {
            $status = $this->filterSuiteSearch($suite, $this->paramsReport['search']);
        }

        return $status;
    }

    /**
     * Filter each suite with text search in tests
     *
     * @return bool
     */
    private function filterSuiteSearch(stdClass $suite, string $text): bool
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
     * Method to render the whole suites tree
     */
    private function buildTree(Collection $suites, array $testsData, ?int $parentId = null): array
    {
        $branch = [];
        foreach ($suites as &$suite) {
            // add tests in suite
            if ($suite->hasTests == 1 && isset($testsData[$suite->id])) {
                $suite->tests = $testsData[$suite->id];
            }

            if ($suite->parent_id == $parentId) {
                $children = $this->buildTree($suites, $testsData, $suite->id);

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
     */
    private function getRootSuitesAggregatedData(array $suites): array
    {
        foreach ($suites as $root_suite) {
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
     */
    private function loopThroughSuiteData(stdClass $suite)
    {
        $this->suiteChildrenData['totalPasses'] += $suite->totalPasses;
        $this->suiteChildrenData['totalFailures'] += $suite->totalFailures;
        $this->suiteChildrenData['totalPending'] += $suite->totalPending;
        $this->suiteChildrenData['totalSkipped'] += $suite->totalSkipped;
        if ($suite->hasSuites == 1) {
            foreach ($suite->suites as $child_suite) {
                $this->loopThroughSuiteData($child_suite);
            }
        }
    }

    /**
     * Get all the suites data from an execution
     */
    private function getReportData(int $report_id): Collection
    {
        return Manager::table('suite')
            ->where('execution_id', '=', $report_id)
            ->orderBy('id')
            ->get();
    }

    /**
     * Get all the tests data from an execution
     */
    private function getTestData(int $report_id): array
    {
        $tests = Manager::table('test')
            ->join('suite', 'test.suite_id', '=', 'suite.id')
            ->where('suite.execution_id', '=', $report_id)
            ->select('test.*')
            ->get();
        $testsData = [];
        foreach ($tests as $test) {
            if ($test->state == 'failed' && is_string($test->stack_trace)) {
                $test->stack_trace_formatted = $this->formatStackTrace($test->stack_trace);
            }
            $testsData[$test->suite_id][] = $test;
        }

        return $testsData;
    }

    /**
     * Format the stack_trace
     */
    private function formatStackTrace(string $stack_trace): string
    {
        return str_replace('    at', '<br />&nbsp;&nbsp;&nbsp;&nbsp;at', htmlentities($stack_trace));
    }

    /**
     * Loop through data and insert it, recursive function
     */
    private function loopThrough(int $executionId, stdClass $suite, ?int $parentSuiteId = null)
    {
        $dataSuite = [
            'execution_id' => $executionId,
            'uuid' => $suite->uuid,
            'title' => $suite->title,
            'campaign' => $this->extractNames($suite->file, 'campaign'),
            'file' => $this->extractNames($suite->file, 'file'),
            'duration' => $suite->duration,
            'hasSkipped' => $suite->hasSkipped ? 1 : 0,
            'hasPending' => $suite->hasPending ? 1 : 0,
            'hasPasses' => $suite->hasPasses ? 1 : 0,
            'hasFailures' => $suite->hasFailures ? 1 : 0,
            'totalSkipped' => $suite->totalSkipped,
            'totalPending' => $suite->totalPending,
            'totalPasses' => $suite->totalPasses,
            'totalFailures' => $suite->totalFailures,
            'hasSuites' => $suite->hasSuites ? 1 : 0,
            'hasTests' => $suite->hasTests ? 1 : 0,
            'parent_id' => $parentSuiteId,
        ];

        //inserting current suite
        $suiteId = Manager::table('suite')->insertGetId($dataSuite);

        if ($suiteId) {
            //insert tests
            if (count($suite->tests) > 0) {
                foreach ($suite->tests as $test) {
                    $identifier = '';
                    if (isset($test->context)) {
                        try {
                            $identifier_data = json_decode($test->context);
                            $identifier = $identifier_data->value;
                        } catch (Exception $e) {
                        }
                    }
                    $dataTest = [
                        'suite_id' => $suiteId,
                        'uuid' => $test->uuid,
                        'identifier' => $identifier,
                        'title' => $test->title,
                        'state' => $this->getTestState($test),
                        'duration' => $test->duration,
                        'error_message' => isset($test->err->message) ? $this->sanitize($test->err->message) : null,
                        'stack_trace' => isset($test->err->estack) ? $this->sanitize($test->err->estack) : null,
                        'diff' => isset($test->err->diff) ? $this->sanitize($test->err->diff) : null,
                    ];
                    Manager::table('test')->insertGetId($dataTest);
                }
            }
            //insert children suites
            if (count($suite->suites) > 0) {
                foreach ($suite->suites as $s) {
                    $this->loopThrough($executionId, $s, $suiteId);
                }
            }
        }
    }

    /**
     * Loop through data and insert it, recursive function
     */
    private function loopThroughSuite(int $executionId, stdClass $suite, ?int $parentSuiteId = null)
    {
        if (!empty($suite->root)) {
            $suiteId = null;
        } else {
            $dataSuite = [
                'execution_id' => $executionId,
                'uuid' => $suite->uuid,
                'title' => $suite->title,
                'campaign' => $this->extractNames($suite->file, 'campaign'),
                'file' => $this->extractNames($suite->file, 'file'),
                'duration' => $suite->duration,
                'hasSkipped' => !empty($suite->skipped),
                'hasPending' => !empty($suite->pending),
                'hasPasses' => !empty($suite->passes),
                'hasFailures' => !empty($suite->failures),
                'totalSkipped' => count($suite->skipped),
                'totalPending' => count($suite->pending),
                'totalPasses' => count($suite->passes),
                'totalFailures' => count($suite->failures),
                'hasSuites' => !empty($suite->suites),
                'hasTests' => !empty($suite->tests),
                'parent_id' => $parentSuiteId,
            ];

            //inserting current suite
            $suiteId = Manager::table('suite')->insertGetId($dataSuite);
            if (!$suiteId) {
                return;
            }
        }

        //insert tests
        foreach ($suite->tests as $test) {
            $identifier = '';
            if (isset($test->context)) {
                try {
                    $identifier_data = json_decode($test->context);
                    $identifier = $identifier_data->value;
                } catch (Exception $e) {
                    // Don't care if it fails
                }
            }

            $dataTest = [
                'suite_id' => $suiteId,
                'uuid' => $test->uuid,
                'identifier' => $identifier,
                'title' => $test->title,
                'state' => $this->getTestState($test),
                'duration' => $test->duration,
                'error_message' => isset($test->err->message) ? $this->sanitize($test->err->message) : null,
                'stack_trace' => isset($test->err->estack) ? $this->sanitize($test->err->estack) : null,
                'diff' => isset($test->err->diff) ? $this->sanitize($test->err->diff) : null,
            ];
            Manager::table('test')->insertGetId($dataTest);
        }

        //insert children suites
        foreach ($suite->suites as $s) {
            $this->loopThroughSuite($executionId, $s, $suiteId);
        }
    }

    /**
     * @return array|bool
     */
    public function compareReportData(int $id)
    {
        //get version and start_date of the given report
        $tempData = Manager::table('execution')
            ->select(['version', 'start_date', 'platform', 'campaign'])
            ->where('id', '=', $id)
            ->first();

        //get id of the precedent report
        $precedentReport = Manager::table('execution')
            ->select('id')
            ->where('version', '=', $tempData->version)
            ->where('platform', '=', $tempData->platform)
            ->where('campaign', '=', $tempData->campaign)
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
            ->crossJoin('test as t2', function ($join) {
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
            foreach ($data as $line) {
                if ($line->old_test_state == 'failed' && $line->current_test_state == 'failed') {
                    ++$results['equal'];
                }
                if ($line->old_test_state == 'passed' && $line->current_test_state == 'failed') {
                    ++$results['broken'];
                }
                if ($line->old_test_state == 'failed' && $line->current_test_state == 'passed') {
                    ++$results['fixed'];
                }
            }
        } else {
            return false;
        }

        return $results;
    }

    /**
     * Sanitize text by removing weird characters
     */
    private function sanitize(string $text): string
    {
        $StrArr = str_split($text);
        $NewStr = '';
        foreach ($StrArr as $Char) {
            $CharNo = ord($Char);
            if ($CharNo == 163) {
                $NewStr .= $Char;
                continue;
            }
            if ($CharNo > 31 && $CharNo < 127) {
                $NewStr .= $Char;
            }
        }

        return $NewStr;
    }

    /**
     * Extract campaign name and file name from json data
     *
     * @return mixed|null
     */
    private function extractNames(string $filename, string $type)
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
     */
    private function getTestState(stdClass $test): string
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
     */
    private function getDataFromGCP(string $gcp_url): array
    {
        $GCP_files_list = [];
        $GCPCallResult = file_get_contents($gcp_url);
        if ($GCPCallResult) {
            $xml = new \SimpleXMLElement($GCPCallResult);
            foreach ($xml->Contents as $content) {
                $build_name = (string) $content->Key;
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

    private function checkAuth(array $queryParams, $request): void
    {
        //check arguments in GET query
        if (!isset($queryParams['token']) || !isset($queryParams['filename'])) {
            throw new HttpBadRequestException($request, 'no enough parameters');
        }
        //check token
        if ($queryParams['token'] != getenv('QANB_TOKEN')) {
            throw new HttpBadRequestException($request, 'invalid token');
        }
    }

    private function getPlatform(array $queryParams): string
    {
        $platform = self::FILTER_PLATFORMS[0];
        $queryPlatform = $queryParams['platform'] ?? ($queryParams['browser'] ?? null); // retro-compatibility
        if (null !== $queryPlatform && in_array($queryPlatform, self::FILTER_PLATFORMS)) {
            $platform = $queryPlatform;
        }

        return $platform;
    }

    private function getCampaign(array $queryParams): string
    {
        $campaign = self::FILTER_CAMPAIGNS[0];
        if (isset($queryParams['campaign']) && in_array($queryParams['campaign'], self::FILTER_CAMPAIGNS)) {
            $campaign = $queryParams['campaign'];
        }

        return $campaign;
    }

    private function getNumberVersion(string $filename, Request $request): string
    {
        //retrieving version number
        preg_match('/[0-9]{4}-[0-9]{2}-[0-9]{2}-(.*)?\.json/', $filename, $matches);
        if (!isset($matches[1])) {
            throw new HttpBadRequestException($request, 'could not retrieve version from filename');
        }

        $version = $matches[1];
        if (strlen($version) < 1) {
            throw new HttpBadRequestException($request, sprintf('version found not correct (%s) from filename %s', $version, $filename));
        }

        return $version;
    }

    private function getContents(string $filename, Request $request): stdClass
    {
        $url = QANB_GCPURL . 'reports/' . $filename;
        $contents = file_get_contents($url);
        if (!$contents) {
            throw new HttpBadRequestException($request, 'unable to retrieve content from GCP URL');
        }

        //try to decode json
        $fileContents = json_decode($contents);
        if ($fileContents == null) {
            throw new HttpBadRequestException($request, 'unable to decode JSON data');
        }

        return $fileContents;
    }
}
