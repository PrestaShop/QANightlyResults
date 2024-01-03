<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class HealthCheckController extends AbstractController
{
    #[Route('/healthcheck', methods: ['GET'])]
    public function check(string $nightlyGCPUrl): JsonResponse
    {
        $data = [
            'database' => true,
            'gcp' => true,
        ];

        // Check database
        try {
            // @todo
            // Manager::table('settings')->first();
        } catch (QueryException $e) {
            $data['database'] = false;
        }

        // Check GCP
        $gcpCall = file_get_contents($nightlyGCPUrl);
        if (!$gcpCall) {
            $data['gcp'] = false;
        }

        return $this->json($data);
    }
}
