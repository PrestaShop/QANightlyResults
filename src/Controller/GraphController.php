<?php
namespace App\Controller;

use App\Entity\Execution;
use App\Repository\ExecutionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class GraphController extends AbstractController
{
    private const DEFAULT_PERIOD = 'last_month';
    private const DEFAULT_VERSION = 'develop';

    private ExecutionRepository $executionRepository;

    public function __construct(ExecutionRepository $executionRepository)
    {
        $this->executionRepository = $executionRepository;
    }

    #[Route('/graph', methods: ['GET'])]
    public function data(Request $request): JsonResponse
    {
        $parameters = $this->getParameters();
        $period = $request->query->get('period', self::DEFAULT_PERIOD);
        if (!$this->isValidParameter($period, $parameters['periods']['values'])) {
            $period = self::DEFAULT_PERIOD;
        }
        $version = $request->query->get('version', self::DEFAULT_VERSION);
        if (!$this->isValidParameter($version, $parameters['versions']['values'])) {
            $version = self::DEFAULT_VERSION;
        }

        switch ($period) {
            case 'last_two_months':
                $dateStartBase = date('Y-m-d', strtotime(' -60 days'));
                $dateEndBase = date('Y-m-d', strtotime(' +1 days'));
                break;
            case 'last_year':
                $dateStartBase = date('Y-m-d', strtotime(' -1 years'));
                $dateEndBase = date('Y-m-d', strtotime(' +1 days'));
                break;
            default:
                $dateStartBase = date('Y-m-d', strtotime(' -30 days'));
                $dateEndBase = date('Y-m-d', strtotime(' +1 days'));
        }
        $dateStart = $request->query->get('start_date', $dateStartBase);
        if (date('Y-m-d', strtotime($dateStart)) !== $dateStart) {
            $dateStart = $dateStartBase;
        }
        $dateEnd = $request->query->get('end_date', $dateEndBase);
        if (date('Y-m-d', strtotime($dateStart)) !== $dateEnd) {
            $dateEnd = $dateEndBase;
        }

        $executions = [];
        foreach ($this->executionRepository->findAllBetweenDates($version, $dateStart, $dateEnd) as $execution) {
            $executions[] = [
                'id' => $execution->getId(),
                'start_date' => $execution->getStartDate()->format('Y-m-d H:i:s'),
                'end_date' => $execution->getEndDate()->format('Y-m-d H:i:s'),
                'version' => $execution->getVersion(),
                'suites' => $execution->getSuites(),
                'tests' => $execution->getTests(),
                'skipped' => $execution->getSkipped(),
                'passes' => $execution->getPasses(),
                'failures' => $execution->getFailures(),
                'pending' => $execution->getPending(),
            ];
        };

        return new JsonResponse($executions);
    }

    #[Route('/graph/parameters', methods: ['GET'])]
    public function parameters(Request $request): JsonResponse
    {
        return new JsonResponse($this->getParameters());
    }

    /**
     * Format a list of all the parameters to use in all methods
     */
    private function getParameters(): array
    {
        $versions = [];
        foreach (array_merge([self::DEFAULT_VERSION], $this->executionRepository->findAllVersions()) as $version) {
            $versions[] = [
                'name' => ucfirst($version),
                'value' => $version,
            ];
        }

        $periods = [
            [
                'name' => 'Last 30 days',
                'value' => self::DEFAULT_PERIOD,
            ],
            [
                'name' => 'Last 60 days',
                'value' => 'last_two_months',
            ],
            [
                'name' => 'Last 12 months',
                'value' => 'last_year',
            ],
        ];

        return [
            'periods' => [
                'type' => 'select',
                'name' => 'period',
                'values' => $periods,
                'default' => self::DEFAULT_PERIOD,
            ],
            'versions' => [
                'type' => 'select',
                'name' => 'version',
                'values' => $versions,
                'default' => self::DEFAULT_VERSION,
            ],
        ];
    }

    /**
     * Check is the parameter is valid
     */
    private function isValidParameter(string $parameter, array $values): bool
    {
        foreach ($values as $value) {
            if ($parameter === $value['value']) {
                return true;
            }
        }

        return false;
    }
}