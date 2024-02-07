<?php

namespace App\Controller;

use App\Repository\ExecutionRepository;
use App\Service\ReportLister;
use App\Service\ReportSuiteBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ReportController extends AbstractController
{
    private ExecutionRepository $executionRepository;

    private ReportLister $reportLister;

    private ReportSuiteBuilder $reportSuiteBuilder;

    private string $nightlyReportPath;

    public function __construct(
        ExecutionRepository $executionRepository,
        ReportLister $reportLister,
        ReportSuiteBuilder $reportSuiteBuilder,
        string $nightlyReportPath
    ) {
        $this->executionRepository = $executionRepository;
        $this->reportLister = $reportLister;
        $this->reportSuiteBuilder = $reportSuiteBuilder;
        $this->nightlyReportPath = $nightlyReportPath;
    }

    #[Route('/reports', methods: ['GET'])]
    public function reports(Request $request): JsonResponse
    {
        $executionFilters = [];
        $requestParams = $request->query->all();

        if (isset($requestParams['filter_platform'])) {
            $executionFilters['platform'] = $requestParams['filter_platform'];
        } elseif (isset($requestParams['filter_browser'])) {
            $executionFilters['platform'] = $requestParams['filter_browser'];
        }
        if (isset($requestParams['filter_campaign'])) {
            $executionFilters['campaign'] = $requestParams['filter_campaign'];
        }
        if (isset($requestParams['filter_version'])) {
            $executionFilters['version'] = $requestParams['filter_version'];
        }
        $executions = $this->executionRepository->findBy($executionFilters, [
            'start_date' => 'DESC',
        ]);

        $reportListing = [];
        if (!isset($executionFilters['platform']) && !isset($executionFilters['campaign'])) {
            // Get all data from GCP
            // No need to get these data if we filtered by platform or campaign
            $reportListing = $this->reportLister->get();
        }

        $reports = [];
        foreach ($executions as $execution) {
            $download = $xml = null;
            $date = $execution->getStartDate()->format('Y-m-d');

            if (isset($reportListing[$date][$execution->getVersion()]['zip'])) {
                $download = $this->nightlyReportPath . $reportListing[$date][$execution->getVersion()]['zip'];
            }
            if (isset($reportListing[$date][$execution->getVersion()]['xml'])) {
                $xml = $this->nightlyReportPath . $reportListing[$date][$execution->getVersion()]['xml'];
            }

            $reports[] = [
                'id' => $execution->getId(),
                'date' => $date,
                'version' => $execution->getVersion(),
                'campaign' => $execution->getCampaign(),
                'browser' => $execution->getPlatform(), // retro-compatibility
                'platform' => $execution->getPlatform(),
                'start_date' => $execution->getStartDate()->format('Y-m-d H:i:s'),
                'end_date' => $execution->getEndDate()->format('Y-m-d H:i:s'),
                'duration' => $execution->getDuration(),
                'suites' => $execution->getSuites(),
                'tests' => [
                    'total' => $execution->getTests(),
                    'passed' => $execution->getPasses(),
                    'failed' => $execution->getFailures(),
                    'pending' => $execution->getPending(),
                    'skipped' => $execution->getSkipped(),
                ],
                'broken_since_last' => $execution->getBrokenSinceLast(),
                'fixed_since_last' => $execution->getFixedSinceLast(),
                'equal_since_last' => $execution->getEqualSinceLast(),
                'download' => $download,
                'xml' => $xml,
            ];
        }

        // merge two arrays in one and sort them by date
        usort($reports, function ($dt1, $dt2) {
            $tm1 = $dt1['start_date'];
            $tm2 = $dt2['start_date'];

            return ($tm1 < $tm2) ? 1 : (($tm1 > $tm2) ? -1 : 0);
        });

        return new JsonResponse($reports);
    }

    #[Route('/reports/{idReport}', methods: ['GET'])]
    public function report(int $idReport, Request $request): JsonResponse
    {
        $execution = $this->executionRepository->findOneBy([
            'id' => $idReport,
        ]);
        if (!$execution) {
            return new JsonResponse([
                'message' => 'Execution not found',
            ], Response::HTTP_NOT_FOUND);
        }

        $requestQueries = $request->query->all();
        $filters = [];
        $filters['search'] = $requestQueries['search'] ?? null;
        $filters['filter_state'] = $requestQueries['filter_state'] ?? ReportSuiteBuilder::FILTER_STATES;

        $return = [
            'id' => $execution->getId(),
            'date' => $execution->getStartDate()->format('Y-m-d'),
            'version' => $execution->getVersion(),
            'campaign' => $execution->getCampaign(),
            'browser' => $execution->getPlatform(), // retro-compatibility
            'platform' => $execution->getPlatform(),
            'start_date' => $execution->getStartDate()->setTimezone(new \DateTimeZone('-01:00'))->format('Y-m-d H:i:s'),
            'end_date' => $execution->getEndDate()->setTimezone(new \DateTimeZone('-01:00'))->format('Y-m-d H:i:s'),
            'duration' => $execution->getDuration(),
            'suites' => $execution->getSuites(),
            'tests' => $execution->getTests(),
            'broken_since_last' => $execution->getBrokenSinceLast(),
            'fixed_since_last' => $execution->getFixedSinceLast(),
            'equal_since_last' => $execution->getEqualSinceLast(),
            'skipped' => $execution->getSkipped(),
            'pending' => $execution->getPending(),
            'passes' => $execution->getPasses(),
            'failures' => $execution->getFailures(),
            'suites_data' => $this->reportSuiteBuilder
                ->filterStates($filters['filter_state'])
                ->filterSearch($filters['search'])
                ->filterEmptyArrays(true)
                ->build($execution)
                ->toArray(),
        ];

        return new JsonResponse($return);
    }

    #[Route('/reports/{idReport}/suites/{idSuite}', methods: ['GET'])]
    public function reportSuite(int $idReport, int $idSuite, Request $request): JsonResponse
    {
        $execution = $this->executionRepository->findOneBy([
            'id' => $idReport,
        ]);
        if (!$execution) {
            return new JsonResponse([
                'message' => 'Execution not found',
            ], Response::HTTP_NOT_FOUND);
        }

        $return = $this->reportSuiteBuilder
            ->filterSuite($idSuite)
            ->filterEmptyArrays(false)
            ->build($execution)
            ->toArrayNth(0);

        unset($return['childrenData']);
        foreach ($return['suites'] as &$returnSuite) {
            if (!is_array($returnSuite)) {
                continue;
            }
            unset($returnSuite['childrenData']);
        }

        return new JsonResponse($return);
    }
}
