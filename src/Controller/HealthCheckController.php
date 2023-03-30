<?php

declare(strict_types=1);

namespace App\Controller;

use Illuminate\Database\Capsule\Manager;
use Illuminate\Database\QueryException;
use Slim\Psr7\Request;
use Slim\Psr7\Response;

class HealthCheckController extends BaseController
{
    /**
     * Display data for a badge in GitHub
     *
     * @param Request $request
     * @param Response $response
     *
     * @return Response
     */
    public function check(Request $request, Response $response): Response
    {
        $data = [
            'database' => true,
            'gcp' => true,
        ];

        // Check database
        try {
            Manager::table('settings')->first();
        } catch (QueryException $e) {
            $data['database'] = false;
        }

        // Check GCP
        $gcpCall = file_get_contents(QANB_GCPURL);
        if (!$gcpCall) {
            $data['gcp'] = false;
        }

        $response->getBody()->write(json_encode($data));

        return $response->withHeader('Content-Type', 'application/json');
    }
}
