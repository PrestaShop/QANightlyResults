<?php

namespace App\Service;

use App\Entity\Execution;
use App\Entity\Suite;
use App\Entity\Test;
use App\Repository\ExecutionRepository;
use App\Repository\TestRepository;
use Doctrine\ORM\EntityManagerInterface;

class ReportPlaywrightImporter extends AbstractReportImporter
{
    public const FILTER_CAMPAIGNS = ['blockwishlist'];

    private EntityManagerInterface $entityManager;

    public function __construct(
        EntityManagerInterface $entityManager,
        ExecutionRepository $executionRepository,
        TestRepository $testRepository
    ) {
        parent::__construct($executionRepository, $testRepository);
        $this->entityManager = $entityManager;
    }

    public function import(
        string $filename,
        string $platform,
        string $campaign,
        string $version,
        \DateTime $startDate,
        \stdClass $jsonContent
    ): Execution {
        $endDate = clone $startDate;
        $endDate->modify('+ ' . (int) $jsonContent->stats->duration . ' milliseconds');

        $execution = new Execution();
        $execution
            ->setRef(date('YmdHis'))
            ->setFilename($filename)
            ->setPlatform($platform)
            ->setCampaign($campaign)
            ->setStartDate($startDate)
            ->setEndDate($endDate)
            ->setDuration((int) $jsonContent->stats->duration)
            ->setVersion($version)
            ->setSuites(count($jsonContent->suites))
            ->setFailures(0)
            ->setInsertionStartDate(new \DateTime())
        ;
        $this->entityManager->persist($execution);
        $this->entityManager->flush();
        $executionId = $execution->getId();

        $countFailures = $countPasses = $countPending = $countSkipped = $countTests = 0;
        foreach ($jsonContent->suites as $suite) {
            foreach ($suite->suites as $suiteChild) {
                $executionSuite = $this->insertExecutionSuite($execution, $suiteChild);
                $countFailures += $executionSuite->getTotalFailures();
                $countPasses += $executionSuite->getTotalPasses();
                $countPending += $executionSuite->getTotalPending();
                $countSkipped += $executionSuite->getTotalSkipped();
                $countTests += count($executionSuite->getTests());
            }
        }
        // Reload of execution (bcz insertExecutionSuite make a Doctrine Clear)
        $execution = $this->executionRepository->findOneBy(['id' => $executionId]);

        // Update stats
        $execution->setFailures($countFailures);
        $execution->setPasses($countPasses);
        $execution->setPending($countPending);
        $execution->setSkipped($countSkipped);
        $execution->setTests($countTests);

        // Calculate comparison with last execution
        $execution = $this->compareReportData($execution);
        $execution->setInsertionEndDate(new \DateTime());

        $this->entityManager->persist($execution);
        $this->entityManager->flush();

        return $execution;
    }

    protected function insertExecutionSuite(Execution $execution, \stdClass $suite): Suite
    {
        $executionSuite = new Suite();
        $executionSuite
            ->setExecution($execution)
            // @todo
            ->setUuid('')
            ->setTitle($suite->title)
            ->setHasSuites(false)
            ->setHasTests(!empty($suite->specs))
            ->setParent(null)
            ->setCampaign($this->extractDataFromFile('/' . $suite->file, 'campaign'))
            ->setFile($this->extractDataFromFile('/' . $suite->file, 'file'))
            ->setInsertionDate(new \DateTime())
            ->setHasFailures(false)
        ;
        $this->entityManager->persist($executionSuite);
        $this->entityManager->flush();

        // Insert tests
        $countFailures = $countPasses = $countPending = $countSkipped = $duration = 0;
        foreach ($suite->specs as $spec) {
            $identifier = '';
            $attachments = $spec->tests[0]->results[0]->attachments;
            if (!empty($attachments[0]) && $attachments[0]->name == 'testIdentifier') {
                $identifier = base64_decode($attachments[0]->body);
            }
            $executionTest = new Test();
            $executionTest
                ->setSuite($executionSuite)
                ->setUuid($spec->id)
                ->setTitle($spec->title)
                ->setDuration($spec->tests[0]->results[0]->duration)
                ->setIdentifier($identifier)
                ->setState($spec->tests[0]->results[0]->status)
                ->setErrorMessage(null)
                ->setStackTrace(null)
                ->setDiff(null)
                ->setInsertionDate(new \DateTime())
            ;
            $this->entityManager->persist($executionTest);

            // Stats
            $duration += $spec->tests[0]->results[0]->duration;
            switch ($executionTest->getState()) {
                case 'failed':
                    $countFailures++;
                    break;
                case 'passed':
                    $countPasses++;
                    break;
                case 'pending':
                    $countPending++;
                    break;
                case 'skipped':
                    $countSkipped++;
                    break;
            }
        }

        $executionSuite
            ->setDuration($duration)
            ->setHasFailures($countFailures > 0)
            ->setHasPasses($countPasses > 0)
            ->setHasPending($countPending > 0)
            ->setHasSkipped($countSkipped > 0)
            ->setTotalFailures($countFailures)
            ->setTotalPasses($countPasses)
            ->setTotalPending($countPending)
            ->setTotalSkipped($countSkipped);
        $this->entityManager->flush();

        return $executionSuite;
    }

    /**
     * Extract campaign name and file name from json data
     */
    protected function extractDataFromFile(string $filename, string $type): string
    {
        $pattern = '/\/campaigns\/(.*?)\/(.*)/';
        preg_match($pattern, $filename, $matches);
        if ($type == 'campaign') {
            return isset($matches[1]) ? $matches[1] : '';
        }
        if ($type == 'file') {
            return isset($matches[2]) ? $matches[2] : '';
        }

        return '';
    }
}
