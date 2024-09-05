<?php

namespace App\Controller;

use App\Repository\ExecutionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DataController extends AbstractController
{
    private ExecutionRepository $executionRepository;

    public function __construct(ExecutionRepository $executionRepository)
    {
        $this->executionRepository = $executionRepository;
    }

    #[Route('/data/badge', methods: ['GET'])]
    public function badgeJson(Request $request): JsonResponse
    {
        $badge_data = $this->getBadgeData($request, false);
        if (!$badge_data) {
            return new JsonResponse([
                'message' => 'Execution not found',
            ], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse([
            'schemaVersion' => 1,
            'label' => $badge_data['branch'],
            'message' => $badge_data['percent'] . '% passed',
            'color' => $badge_data['color'],
        ]);
    }

    #[Route('/data/badge/svg', methods: ['GET'])]
    public function badgeSvg(Request $request): Response
    {
        $badge_data = $this->getBadgeData($request, true);
        if (!$badge_data) {
            return new Response('Execution not found', Response::HTTP_NOT_FOUND);
        }

        $content = sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="190" height="20">'
            . '<g clip-path="url(#a)">'
            . '<path fill="#444" d="M0 0h58v20H0z"/>'
            . '<path fill="%s" d="M58 0h85v20H58z"/>'
            . '</g>'
            . '<g fill="#fff" text-anchor="middle" font-family="DejaVu Sans,Verdana,Geneva,sans-serif" font-size="110">'
            . '<text x="285" y="140" transform="scale(0.1)" textLength="430">%s</text>'
            . '<text x="1000" y="140" transform="scale(.1)" textLength="750">%.2f%% passed</text>'
            . '</g>'
            . '</svg>',
            $badge_data['color'],
            $badge_data['branch'],
            $badge_data['percent']
        );

        return new Response(
            $content,
            Response::HTTP_OK,
            [
                'content-type' => 'image/svg+xml',
            ]
        );
    }

    /**
     * @return array{'branch': string, 'percent': float, 'color': string}|null
     */
    private function getBadgeData(
        Request $request,
        bool $hexColor,
    ): ?array {
        $branch = (string) $request->query->get('branch', 'develop');
        $date = $request->query->get('date');
        if ($date) {
            $date = date('Y-m-d', strtotime($date)) == $date ? $date : null;
        }

        $execution = $this->executionRepository->findOneByVersionAndDate($branch, $date);
        if ($execution) {
            $percent = round(($execution->getPasses() * 100) / ($execution->getTests() - $execution->getPending() - $execution->getSkipped()), 2);

            if ($hexColor) {
                $color = $percent < 80 ? '#e00707' : ($percent == 100 ? '#76ca00' : '#eba400');
            } else {
                $color = $percent < 80 ? 'red' : ($percent == 100 ? 'green' : 'orange');
            }

            return [
                'branch' => $branch,
                'percent' => $percent,
                'color' => $color,
            ];
        }

        return null;
    }
}
