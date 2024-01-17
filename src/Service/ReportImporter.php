<?php

namespace App\Service;

use App\Entity\Execution;
use App\Entity\Suite;
use App\Entity\Test;
use App\Repository\ExecutionRepository;
use App\Repository\TestRepository;
use Doctrine\ORM\EntityManagerInterface;

class ReportImporter
{
    public const FILTER_PLATFORMS = ['chromium', 'firefox', 'webkit', 'cli'];

    public const FILTER_CAMPAIGNS = ['functional', 'sanity', 'e2e', 'regression', 'autoupgrade'];

    public const FORMAT_DATE_MOCHA5 = 'Y-m-d H:i:s';
    public const FORMAT_DATE_MOCHA6 = \DateTime::RFC3339_EXTENDED;

    private EntityManagerInterface $entityManager;

    private ExecutionRepository $executionRepository;

    private TestRepository $testRepository;

    public function __construct(
        EntityManagerInterface $entityManager,
        ExecutionRepository $executionRepository,
        TestRepository $testRepository
    ) {
        $this->entityManager = $entityManager;
        $this->executionRepository = $executionRepository;
        $this->testRepository = $testRepository;
    }

    public function import(
        string $filename,
        string $platform,
        string $campaign,
        string $version,
        \DateTime $startDate,
        \stdClass $jsonContent,
        string $dateformat
    ): Execution {
        $execution = new Execution();
        $execution
            ->setRef(date('YmdHis'))
            ->setFilename($filename)
            ->setPlatform($platform)
            ->setCampaign($campaign)
            ->setStartDate($startDate)
            ->setEndDate(\DateTime::createFromFormat($dateformat, $jsonContent->stats->end))
            ->setDuration($jsonContent->stats->duration)
            ->setVersion($version)
            ->setSuites($jsonContent->stats->suites)
            ->setTests($jsonContent->stats->tests)
            ->setSkipped($jsonContent->stats->skipped)
            ->setPending($jsonContent->stats->pending)
            ->setPasses($jsonContent->stats->passes)
            ->setFailures($jsonContent->stats->failures)
            ->setInsertionStartDate(new \DateTime())
        ;
        $this->entityManager->persist($execution);
        $this->entityManager->flush();
        $executionId = $execution->getId();

        if ($dateformat == self::FORMAT_DATE_MOCHA5) {
            $this->insertExecutionSuite($execution, $jsonContent->suites, $dateformat);
        } else {
            foreach ($jsonContent->results as $suite) {
                if ($suite->root) {
                    foreach ($suite->suites as $suiteChild) {
                        $this->insertExecutionSuite($execution, $suiteChild, $dateformat);
                        // Reload of execution (bcz insertExecutionSuite make a Doctrine Clear)
                        $execution = $this->executionRepository->findOneBy(['id' => $executionId]);
                    }
                } else {
                    $this->insertExecutionSuite($execution, $suite, $dateformat);
                }
            }
        }
        // Reload of execution (bcz insertExecutionSuite make a Doctrine Clear)
        $execution = $this->executionRepository->findOneBy(['id' => $executionId]);

        // Calculate comparison with last execution
        $execution = $this->compareReportData($execution);
        $execution->setInsertionEndDate(new \DateTime());

        $this->entityManager->persist($execution);
        $this->entityManager->flush();

        return $execution;
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
            ->setInsertionDate(new \DateTime())
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
                ->setInsertionDate(new \DateTime())
            ;
            $this->entityManager->persist($executionTest);
        }
        $this->entityManager->flush();

        // Insert children suites
        foreach ($suite->suites as $suiteChildren) {
            $this->insertExecutionSuite($execution, $suiteChildren, $dateFormat, $executionSuite->getId());
        }
        if (!$parentSuiteId) {
            $this->entityManager->clear();
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
