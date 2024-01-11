<?php

namespace App\Controller;

use App\Repository\ExecutionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class HealthCheckController extends AbstractController
{
    private ExecutionRepository $executionRepository;

    public function __construct(ExecutionRepository $executionRepository)
    {
        $this->executionRepository = $executionRepository;
    }

    #[Route('/healthcheck', methods: ['GET'])]
    public function check(string $nightlyGCPUrl): JsonResponse
    {
        $data = [
            'database' => true,
            'gcp' => true,
        ];

        // Check database
        try {
            $this->executionRepository->findOneBy([
                'version' => 'develop',
                'campaign' => 'functional',
                'platform' => 'chromium',
            ]);
        } catch (\Exception $e) {
            $data['database'] = false;
        }

        // Check GCP
        $gcpCall = @file_get_contents($nightlyGCPUrl);
        if (!$gcpCall) {
            $data['gcp'] = false;
        }

        return $this->json($data);
    }
}
