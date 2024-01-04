<?php

namespace App\Controller;

use App\Entity\Execution;
use App\Entity\Suite;
use App\Entity\Test;
use App\Repository\ExecutionRepository;
use App\Repository\TestRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ImportController extends AbstractController
{
    public const FILTER_PLATFORMS = ['chromium', 'firefox', 'webkit', 'cli'];

    public const FILTER_CAMPAIGNS = ['functional', 'sanity', 'e2e', 'regression', 'autoupgrade'];

    private const FORMAT_DATE_MOCHA5 = 'Y-m-d H:i:s';
    private const FORMAT_DATE_MOCHA6 = \DateTime::RFC3339_EXTENDED;

    private EntityManagerInterface $entityManager;

    private ExecutionRepository $executionRepository;

    private TestRepository $testRepository;

    private string $nightlyToken;

    private string $nightlyGCPUrl;

    private ?string $filename;

    private ?string $version;

    private ?\stdClass $jsonContent;

    private ?string $platform;

    private ?string $campaign;

    private ?\DateTime $startDate;

    public function __construct(
        EntityManagerInterface $entityManager,
        ExecutionRepository $executionRepository,
        TestRepository $testRepository,
        string $nightlyToken,
        string $nightlyGCPUrl
    ) {
        $this->entityManager = $entityManager;
        $this->executionRepository = $executionRepository;
        $this->testRepository = $testRepository;
        $this->nightlyToken = $nightlyToken;
        $this->nightlyGCPUrl = $nightlyGCPUrl;
    }

    #[Route('/hook/add', methods: ['GET'])]
    /** Used in 1.7.7 & autoupgrade */
    public function importReportOld(Request $request): JsonResponse
    {
        $response = $this->checkAuth($request, self::FORMAT_DATE_MOCHA5);
        if ($response instanceof JsonResponse) {
            return $response;
        }

        $execution = new Execution();
        $execution
            ->setRef(date('YmdHis'))
            ->setFilename($this->filename)
            ->setPlatform($this->platform)
            ->setCampaign($this->campaign)
            ->setStartDate($this->startDate)
            ->setEndDate(\DateTime::createFromFormat(self::FORMAT_DATE_MOCHA5, $this->jsonContent->stats->end))
            ->setDuration($this->jsonContent->stats->duration)
            ->setVersion($this->version)
            ->setSkipped($this->jsonContent->stats->skipped)
            ->setPending($this->jsonContent->stats->pending)
            ->setPasses($this->jsonContent->stats->passes)
            ->setFailures($this->jsonContent->stats->failures)
            ->setInsertionStartDate(new \DateTime())
        ;
        $this->entityManager->persist($execution);
        $this->entityManager->flush();

        $this->insertExecutionSuite($execution, $this->jsonContent->suites, self::FORMAT_DATE_MOCHA5);

        // Calculate comparison with last execution
        $execution = $this->compareReportData($execution);
        $execution->setInsertionEndDate(new \DateTime());

        $this->entityManager->persist($execution);
        $this->entityManager->flush();

        return new JsonResponse([
            'status' => 'ok',
            'report' => $execution->getId(),
        ]);
    }

    #[Route('/hook/reports/import', methods: ['GET'])]
    /** Used in 1.7.8+ & autoupgrade */
    public function importReport(Request $request): JsonResponse
    {
        $response = $this->checkAuth($request, self::FORMAT_DATE_MOCHA6);
        if ($response instanceof JsonResponse) {
            return $response;
        }

        $execution = new Execution();
        $execution
            ->setRef(date('YmdHis'))
            ->setFilename($this->filename)
            ->setPlatform($this->platform)
            ->setCampaign($this->campaign)
            ->setStartDate($this->startDate)
            ->setEndDate(\DateTime::createFromFormat(self::FORMAT_DATE_MOCHA6, $this->jsonContent->stats->end))
            ->setDuration($this->jsonContent->stats->duration)
            ->setVersion($this->version)
            ->setSkipped($this->jsonContent->stats->skipped)
            ->setPending($this->jsonContent->stats->pending)
            ->setPasses($this->jsonContent->stats->passes)
            ->setFailures($this->jsonContent->stats->failures)
            ->setInsertionStartDate(new \DateTime())
        ;
        $this->entityManager->persist($execution);
        $this->entityManager->flush();

        foreach ($this->jsonContent->results as $suite) {
            $this->insertExecutionSuite($execution, $suite, self::FORMAT_DATE_MOCHA6);
        }

        // Calculate comparison with last execution
        $execution = $this->compareReportData($execution);
        $execution->setInsertionEndDate(new \DateTime());

        $this->entityManager->persist($execution);
        $this->entityManager->flush();

        return new JsonResponse([
            'status' => 'ok',
            'report' => $execution->getId(),
        ]);
    }

    private function checkAuth(Request $request, string $dateFormat): ?JsonResponse
    {
        $token = $request->query->get('token');
        $this->filename = $request->query->get('filename');

        if (!$token || !$this->filename) {
            return new JsonResponse([
                'message' => 'No enough parameters',
            ], Response::HTTP_BAD_REQUEST);
        }
        if ($token !== $this->nightlyToken) {
            return new JsonResponse([
                'message' => 'Invalid token',
            ], Response::HTTP_UNAUTHORIZED);
        }

        preg_match('/[0-9]{4}-[0-9]{2}-[0-9]{2}-(.*)?\.json/', $this->filename, $matchesVersion);
        if (!isset($matchesVersion[1])) {
            return new JsonResponse([
                'message' => 'Could not retrieve version from filename',
            ], Response::HTTP_BAD_REQUEST);
        }

        $this->version = $matchesVersion[1];
        if (strlen($this->version) < 1) {
            return new JsonResponse([
                'message' => sprintf(
                    'Version found not correct (%s) from filename %s',
                    $this->version,
                    $this->filename
                ),
            ], Response::HTTP_BAD_REQUEST);
        }

        $fileContent = @file_get_contents($this->nightlyGCPUrl . 'reports/' . $this->filename);
        if (!$fileContent) {
            return new JsonResponse([
                'message' => 'Unable to retrieve content from GCP URL',
            ], Response::HTTP_BAD_REQUEST);
        }

        $this->jsonContent = json_decode($fileContent);
        if (!$this->jsonContent) {
            return new JsonResponse([
                'message' => 'Unable to decode JSON data',
            ], Response::HTTP_BAD_REQUEST);
        }

        $force = $request->query->get('force', false);
        $force = is_bool($force) ? $force : false;

        $this->platform = $request->query->has('platform') ? $request->query->get('platform') : (
            $request->query->has('browser') ? $request->query->get('browser') : null
        );
        $this->platform = in_array($this->platform, self::FILTER_PLATFORMS) ? $this->platform : self::FILTER_PLATFORMS[0];

        $this->campaign = $request->query->has('campaign') ? $request->query->get('campaign') : null;
        $this->campaign = in_array($this->campaign, self::FILTER_CAMPAIGNS) ? $this->campaign : self::FILTER_CAMPAIGNS[0];

        $this->startDate = \DateTime::createFromFormat($dateFormat, $this->jsonContent->stats->start);

        // Check if there is no similar entry
        if (!$force && $this->executionRepository->findOneByNightly($this->version, $this->platform, $this->campaign, $this->startDate->format('Y-m-d'))) {
            return new JsonResponse([
                'message' => sprintf(
                    'A similar entry was found (criteria: version %s, platform %s, campaign %s, date %s).',
                    $this->version,
                    $this->platform,
                    $this->campaign,
                    $this->startDate->format('Y-m-d')
                ),
            ], Response::HTTP_FORBIDDEN);
        }

        return null;
    }

    private function insertExecutionSuite(Execution $execution, \stdClass $suite, string $dateFormat, int $parentSuiteId = null): void
    {
        $isMocha6 = $dateFormat === self::FORMAT_DATE_MOCHA6;

        $executionSuite = new Suite();
        $executionSuite
            ->setExecution($execution)
            ->setUuid($suite->uuid)
            ->setTitle($suite->title)
            ->setDuration($suite->duration)
            ->setHasSkipped($isMocha6 ? (!empty($suite->skipped)) : ($suite->hasSkipped ? 1 : 0))
            ->setHasPending($isMocha6 ? (!empty($suite->pending)) : ($suite->hasPending ? 1 : 0))
            ->setHasPasses($isMocha6 ? (!empty($suite->passes)) : ($suite->hasPasses ? 1 : 0))
            ->setHasFailures($isMocha6 ? (!empty($suite->failures)) : ($suite->hasFailures ? 1 : 0))
            ->setHasSuites($isMocha6 ? (!empty($suite->suites)) : ($suite->hasSuites ? 1 : 0))
            ->setHasTests($isMocha6 ? (!empty($suite->tests)) : ($suite->hasTests ? 1 : 0))
            ->setTotalSkipped($isMocha6 ? (count($suite->skipped)) : ($suite->totalSkipped))
            ->setTotalPending($isMocha6 ? (count($suite->pending)) : ($suite->totalPending))
            ->setTotalPasses($isMocha6 ? (count($suite->passes)) : ($suite->totalPasses))
            ->setTotalFailures($isMocha6 ? (count($suite->failures)) : ($suite->totalFailures))
            ->setParentId($parentSuiteId)
            ->setCampaign($this->extractDataFromFile($suite->file, 'campaign'))
            ->setFile($this->extractDataFromFile($suite->file, 'file'))
        ;
        $this->entityManager->persist($executionSuite);
        $this->entityManager->flush();

        // Insert tests
        foreach ($suite->tests as $test) {
            $identifier = '';
            if (!empty($test->context)) {
                $identifier_data = json_decode($test->context);
                $identifier = is_array($identifier_data) ? $identifier_data[0]->value : $identifier_data->value;
            }
            $executionTest = new Test();
            $executionTest
                ->setSuite($executionSuite)
                ->setUuid($test->uuid)
                ->setTitle($test->title)
                ->setDuration($test->duration)
                ->setIdentifier($identifier)
                ->setState($this->extractTestState($test))
                ->setErrorMessage(isset($test->err->message) ? $this->sanitize($test->err->message) : null)
                ->setStackTrace(isset($test->err->estack) ? $this->sanitize($test->err->estack) : null)
                ->setDiff(isset($test->err->diff) ? $this->sanitize($test->err->diff) : null)
            ;
            $this->entityManager->persist($executionTest);
        }
        $this->entityManager->flush();

        // Insert children suites
        foreach ($suite->suites as $suiteChildren) {
            $this->insertExecutionSuite($execution, $suiteChildren, $dateFormat, $executionSuite->getId());
        }
    }

    /**
     * Extract campaign name and file name from json data
     */
    private function extractDataFromFile(string $filename, string $type): string
    {
        if (strlen($filename) == 0) {
            return '';
        }
        if (strpos($filename, '/full/') !== false) {
            // Selenium
            $pattern = '/\/full\/(.*?)\/(.*)/';
            preg_match($pattern, $filename, $matches);
            if ($type == 'campaign') {
                return isset($matches[1]) ? $matches[1] : '';
            }
            if ($type == 'file') {
                return isset($matches[2]) ? $matches[2] : '';
            }
        } else {
            // Puppeteer
            $pattern = '/\/campaigns\/(.*?)\/(.*?)\/(.*)/';
            preg_match($pattern, $filename, $matches);
            if ($type == 'campaign') {
                return isset($matches[2]) ? $matches[2] : '';
            }
            if ($type == 'file') {
                return isset($matches[3]) ? $matches[3] : '';
            }
        }

        return '';
    }

    private function extractTestState(\stdClass $test): string
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
     * Sanitize text by removing weird characters
     */
    private function sanitize(string $text): string
    {
        $result = '';
        foreach (str_split($text) as $character) {
            $ascii = ord($character);
            if ($ascii == 163) {
                $result .= $character;
                continue;
            }
            if ($ascii > 31 && $ascii < 127) {
                $result .= $character;
            }
        }

        return $result;
    }

    private function compareReportData(Execution $execution): Execution
    {
        if (!$execution->getStartDate()) {
            return $execution;
        }

        $executionPrevious = $this->executionRepository->findOneByNightlyBefore(
            $execution->getVersion(),
            $execution->getPlatform(),
            $execution->getCampaign(),
            $execution->getStartDate()
        );
        if (!$executionPrevious) {
            return $execution;
        }

        $data = $this->testRepository->findComparisonDate($execution, $executionPrevious);
        if (empty($data)) {
            return $execution;
        }

        // Reset
        $execution
            ->setFixedSinceLast(0)
            ->setBrokenSinceLast(0)
            ->setEqualSinceLast(0)
        ;
        foreach ($data as $datum) {
            if ($datum['old_test_state'] == 'failed' && $datum['current_test_state'] == 'failed') {
                $execution->setEqualSinceLast($execution->getEqualSinceLast() + 1);
            }
            if ($datum['old_test_state'] == 'passed' && $datum['current_test_state'] == 'failed') {
                $execution->setBrokenSinceLast($execution->getBrokenSinceLast() + 1);
            }
            if ($datum['old_test_state'] == 'failed' && $datum['current_test_state'] == 'passed') {
                $execution->setFixedSinceLast($execution->getFixedSinceLast() + 1);
            }
        }

        return $execution;
    }
}
