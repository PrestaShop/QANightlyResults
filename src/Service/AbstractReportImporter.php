<?php

namespace App\Service;

use App\Entity\Execution;
use App\Repository\ExecutionRepository;
use App\Repository\TestRepository;

abstract class AbstractReportImporter
{
    abstract public function import(
        string $filename,
        string $platform,
        string $database,
        string $campaign,
        string $version,
        \DateTime $startDate,
        \stdClass $jsonContent,
    ): Execution;

    public const FILTER_PLATFORMS = ['chromium', 'firefox', 'webkit', 'cli'];

    public const FILTER_DATABASES = ['mysql', 'mariadb'];

    public const REGEX_FILE = '/[0-9]{4}-[0-9]{2}-[0-9]{2}-([^-]*)[-]?(.*)]?\.json/';

    protected ExecutionRepository $executionRepository;

    protected TestRepository $testRepository;

    public function __construct(ExecutionRepository $executionRepository, TestRepository $testRepository)
    {
        $this->executionRepository = $executionRepository;
        $this->testRepository = $testRepository;
    }

    protected function compareReportData(Execution $execution): Execution
    {
        if (!$execution->getStartDate()) {
            return $execution;
        }

        $executionPrevious = $this->executionRepository->findOneByNightlyBefore(
            $execution->getVersion(),
            $execution->getPlatform(),
            $execution->getCampaign(),
            $execution->getDatabase(),
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
