<?php

namespace App\Service;

use App\Entity\Execution;
use App\Entity\Suite;
use App\Entity\Test;
use App\Repository\SuiteRepository;

class ReportSuiteBuilder
{
    public const FILTER_STATE_FAILED = 'failed';
    public const FILTER_STATE_PASSED = 'passed';
    public const FILTER_STATE_SKIPPED = 'skipped';
    public const FILTER_STATE_PENDING = 'pending';

    public const FILTER_STATES = [
        self::FILTER_STATE_FAILED,
        self::FILTER_STATE_PASSED,
        self::FILTER_STATE_SKIPPED,
        self::FILTER_STATE_PENDING,
    ];

    private array $filterStates = self::FILTER_STATES;

    private ?string $filterSearch = null;

    private ?int $filterSuiteId = null;

    private array $suites = [];

    private array $tests = [];

    /**
     * @var array<int, array<string, int>>
     */
    private array $stats = [];

    private SuiteRepository $suiteRepository;

    public function __construct(SuiteRepository $suiteRepository)
    {
        $this->suiteRepository = $suiteRepository;
    }

    public function filterSearch(string $search = null): self
    {
        $this->filterSearch = $search;

        return $this;
    }

    public function filterStates(array $states = self::FILTER_STATES): self
    {
        $this->filterStates = $states;

        return $this;
    }

    public function filterSuite(int $suiteId = null): self
    {
        $this->filterSuiteId = $suiteId;

        return $this;
    }

    public function build(Execution $execution): self
    {
        $this->suites = $execution->getSuitesCollection()->toArray();
        // Find if there is main suite id

        $hasOnlyOneMainSuite = false;
        $mainSuiteId = null;
        foreach ($this->suites as $suite) {
            if ($suite->getParentId()) {
                continue;
            }

            if ($hasOnlyOneMainSuite) {
                // There is another suite with null, so not only one is used
                // Used for legacy purpose
                $hasOnlyOneMainSuite = false;
                $mainSuiteId = null;
                break;
            }

            $hasOnlyOneMainSuite = true;
            $mainSuiteId = $suite->getId();
        }
        // Extract tests
        $this->tests = $this->getTests();

        // Build the recursive tree
        $this->suites = $this->buildTree($mainSuiteId, true);
        $this->suites = $this->filterTree($this->suites, true);

        return $this;
    }

    public function toArrayNth(int $nth): array
    {
        $data = array_values($this->toArray());

        return $data[$nth] ?? [];
    }

    public function toArray(): array
    {
        $data = [];

        foreach ($this->suites as $suite) {
            $data[$suite->getId()] = $this->formatSuite($suite);
        }

        return $data;
    }

    private function formatSuite(Suite $suite): array
    {
        $suites = $tests = [];
        foreach ($suite->getSuites() as $suiteChild) {
            $suites[$suiteChild->getId()] = $this->formatSuite($suiteChild);
        }
        foreach ($suite->getTests() as $test) {
            $tests[] = $this->formatTest($test);
        }

        $data = [
            'id' => $suite->getId(),
            'execution_id' => $suite->getExecution()->getId(),
            'uuid' => $suite->getUuid(),
            'title' => $suite->getTitle(),
            'campaign' => $suite->getCampaign(),
            'file' => $suite->getFile(),
            'duration' => $suite->getDuration(),
            'hasSkipped' => $suite->isHasSkipped() ? 1 : 0,
            'hasPending' => $suite->isHasPending() ? 1 : 0,
            'hasPasses' => $suite->isHasPasses() ? 1 : 0,
            'hasFailures' => $suite->isHasFailures() ? 1 : 0,
            'totalSkipped' => $suite->getTotalSkipped(),
            'totalPending' => $suite->getTotalPending(),
            'totalPasses' => $suite->getTotalPasses(),
            'totalFailures' => $suite->getTotalFailures(),
            'hasSuites' => $suite->getHasSuites(),
            'hasTests' => $suite->getHasTests(),
            'parent_id' => $suite->getParentId(),
            'insertion_date' => $suite->getInsertionDate()
                ->setTimezone(new \DateTimeZone('-01:00'))
                ->format('Y-m-d H:i:s'),
            'suites' => $suites,
            'tests' => $tests,
            'childrenData' => $this->stats[$suite->getId()] ?? [],
        ];

        return array_filter($data, function ($value): bool {
            return !is_array($value) || !empty($value);
        });
    }

    private function formatTest(Test $test): array
    {
        $data = [
            'id' => $test->getId(),
            'suite_id' => $test->getSuite()->getId(),
            'uuid' => $test->getUuid(),
            'identifier' => $test->getIdentifier(),
            'title' => $test->getTitle(),
            'state' => $test->getState(),
            'duration' => $test->getDuration(),
            'error_message' => $test->getErrorMessage(),
            'stack_trace' => $test->getStackTrace(),
            'diff' => $test->getDiff(),
            'insertion_date' => $test->getInsertionDate()
                ->setTimezone(new \DateTimeZone('-01:00'))
                ->format('Y-m-d H:i:s'),
        ];

        if ($test->getStackTraceFormatted() !== null) {
            $data['stack_trace_formatted'] = $test->getStackTraceFormatted();
        }

        return $data;
    }

    private function getTests(): array
    {
        $tests = [];
        foreach ($this->suites as $suite) {
            foreach ($suite->getTests() as $test) {
                if ($test->getState() == 'failed' && $test->getStackTrace()) {
                    $stackTrace = str_replace('    at', '<br />&nbsp;&nbsp;&nbsp;&nbsp;at', htmlentities($test->getStackTrace()));
                    $test->setStackTraceFormatted($stackTrace);
                }
                if (!isset($tests[$test->getSuite()->getId()])) {
                    $tests[$test->getSuite()->getId()] = [];
                }
                $tests[$test->getSuite()->getId()][$test->getId()] = $test;
            }
        }

        return $tests;
    }

    /**
     * @return array<int, Suite>
     */
    private function buildTree(?int $parentId, bool $isRoot): array
    {
        $tree = [];
        foreach ($this->suites as $suite) {
            if ($this->filterSuiteId
                && $isRoot
                && $suite->getId() !== $this->filterSuiteId) {
                continue;
            }
            if ($suite->getParentId() !== $parentId) {
                continue;
            }

            if ($suite->getHasTests() == 1 && isset($this->tests[$suite->getId()])) {
                $suite->setTests($this->tests[$suite->getId()]);
            }

            $suite->setSuites(
                $this->buildTree($suite->getId(), false)
            );

            $tree[$suite->getId()] = $suite;

            if ($isRoot) {
                $this->stats[$suite->getId()] = [
                    'totalPasses' => $this->countStatus($suite->getTotalPasses(), $suite->getSuites(), 'passes'),
                    'totalFailures' => $this->countStatus($suite->getTotalFailures(), $suite->getSuites(), 'failures'),
                    'totalPending' => $this->countStatus($suite->getTotalPending(), $suite->getSuites(), 'pending'),
                    'totalSkipped' => $this->countStatus($suite->getTotalSkipped(), $suite->getSuites(), 'skipped'),
                ];

                // When the "failed" toggle is turned on
                if (in_array('failed', $this->filterStates) && $this->stats[$suite->getId()]['totalFailures'] > 0) {
                    continue;
                }
                // When the "pending" toggle is turned on
                if (in_array('pending', $this->filterStates) && $this->stats[$suite->getId()]['totalPending'] > 0) {
                    continue;
                }
                // When the "skipped" toggle is turned on
                if (in_array('skipped', $this->filterStates) && $this->stats[$suite->getId()]['totalSkipped'] > 0) {
                    continue;
                }
                // When the "passed" toggle is turned on and we didn't accept this suite, it must only be shown if
                // it hasn't any pending or failed test
                // this prevents showing a suite with passed and failed test when we hide failed tests for example
                if (in_array('passed', $this->filterStates)
                    && $this->stats[$suite->getId()]['totalPasses'] > 0
                    && $this->stats[$suite->getId()]['totalFailures'] == 0
                    && $this->stats[$suite->getId()]['totalSkipped'] == 0
                    && $this->stats[$suite->getId()]['totalPending'] == 0) {
                    continue;
                }
                unset($tree[$suite->getId()]);
            }
        }

        return $tree;
    }

    private function countStatus(int $basis, array $suites, string $status): int
    {
        $num = $basis;

        foreach ($suites as $suite) {
            $num += $suite->{'getTotal' . ucfirst($status)}();

            if ($suite->getHasSuites() == 1) {
                $num += $this->countStatus(0, $suite->getSuites(), $status);
            }
        }

        return $num;
    }

    /**
     * Filter the whole tree when using fulltext search
     *
     * @param $suites
     * @param callable|null $function
     *
     * @return array
     */
    private function filterTree(array $suites, bool $isRoot): array
    {
        if ($isRoot) {
            foreach ($suites as $key => &$suite) {
            }
        }

        foreach ($suites as $key => &$suite) {
            $suiteChildren = $suite->getSuites();
            $suiteTests = $suite->getTests();
            if (!empty($suiteChildren)) {
                $suite->setSuites($this->filterTree($suiteChildren, false));
            }
            if (empty($suiteChildren)
                && empty($suiteTests)
                && $this->filterSuiteSearch($suite)
            ) {
                unset($suites[$key]);
            }
        }

        return array_filter($suites, [$this, 'filterSuiteSearch']);
    }

    /**
     * Filter each suite with text search in tests
     *
     * @return bool
     */
    private function filterSuiteSearch(Suite $suite): bool
    {
        if (empty($this->filterSearch)) {
            return true;
        }

        // Title
        if (stripos($suite->getTitle(), $this->filterSearch) !== false) {
            return true;
        }
        // Tests
        foreach ($suite->getTests() as $test) {
            if (stripos($test->getTitle(), $this->filterSearch) !== false) {
                return true;
            }
        }

        return false;
    }
}
