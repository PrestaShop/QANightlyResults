<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class OptionsController extends AbstractController
{
    #[Route('/data/badge', methods: ['OPTIONS'])]
    #[Route('/data/badge/svg', methods: ['OPTIONS'])]
    #[Route('/graph', methods: ['OPTIONS'])]
    #[Route('/graph/parameters', methods: ['OPTIONS'])]
    #[Route('/healthcheck', methods: ['OPTIONS'])]
    #[Route('/reports', methods: ['OPTIONS'])]
    #[Route('/reports/{idReport}', methods: ['OPTIONS'])]
    #[Route('/reports/{idReport}/suites/{idSuite}', methods: ['OPTIONS'])]
    public function badgeJsonOptions(): Response
    {
        $response = new Response();
        $response->headers->set('Access-Control-Allow-Origin', '*');
        $response->headers->set('Access-Control-Allow-Methods', 'GET');
        $response->headers->set('Access-Control-Max-Age', '3600');

        return $response;
    }
}
