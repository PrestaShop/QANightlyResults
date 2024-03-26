<?php

namespace App\Controller;

use App\Repository\ExecutionRepository;
use App\Service\ReportMochaImporter;
use App\Service\ReportPlaywrightImporter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ImportController extends AbstractController
{
    private ExecutionRepository $executionRepository;

    private ReportMochaImporter $reportMochaImporter;

    private ReportPlaywrightImporter $reportPlaywrightImporter;

    private string $nightlyToken;

    private string $nightlyReportPath;

    private ?string $filename;

    private ?string $version;

    private ?\stdClass $jsonContent;

    private ?string $platform;

    private ?string $campaign;

    private ?\DateTime $startDate;

    public function __construct(
        ExecutionRepository $executionRepository,
        ReportMochaImporter $reportMochaImporter,
        ReportPlaywrightImporter $reportPlaywrightImporter,
        string $nightlyToken,
        string $nightlyReportPath
    ) {
        $this->executionRepository = $executionRepository;
        $this->reportMochaImporter = $reportMochaImporter;
        $this->reportPlaywrightImporter = $reportPlaywrightImporter;
        $this->nightlyToken = $nightlyToken;
        $this->nightlyReportPath = $nightlyReportPath;
    }

    #[Route('/hook/reports/import', methods: ['GET'])]
    public function importReportMocha(Request $request): JsonResponse
    {
        $response = $this->checkAuth($request, ReportMochaImporter::FILTER_CAMPAIGNS, true);
        if ($response instanceof JsonResponse) {
            return $response;
        }

        $execution = $this->reportMochaImporter->import(
            $this->filename,
            $this->platform,
            $this->campaign,
            $this->version,
            $this->startDate,
            $this->jsonContent
        );

        return new JsonResponse([
            'status' => 'ok',
            'report' => $execution->getId(),
        ]);
    }

    #[Route('/import/report/playwright', methods: ['GET'])]
    public function importReportPlaywright(Request $request): JsonResponse
    {
        $response = $this->checkAuth($request, ReportPlaywrightImporter::FILTER_CAMPAIGNS, false);
        if ($response instanceof JsonResponse) {
            return $response;
        }

        $execution = $this->reportPlaywrightImporter->import(
            $this->filename,
            $this->platform,
            $this->campaign,
            $this->version,
            $this->startDate,
            $this->jsonContent
        );

        return new JsonResponse([
            'status' => 'ok',
            'report' => $execution->getId(),
        ]);
    }

    /**
     * @param array<string> $allowedCampaigns
     */
    private function checkAuth(Request $request, array $allowedCampaigns, bool $forceCampaign): ?JsonResponse
    {
        $token = $request->query->get('token');
        $this->filename = $request->query->get('filename');

        if (!$token || !$this->filename) {
            return new JsonResponse([
                'message' => 'No enough parameters',
            ], Response::HTTP_BAD_REQUEST);
        }
        if ($token !== $this->nightlyToken) {
            return new JsonResponse([
                'message' => 'Invalid token',
            ], Response::HTTP_UNAUTHORIZED);
        }

        preg_match('/[0-9]{4}-[0-9]{2}-[0-9]{2}-(.*)?\.json/', $this->filename, $matchesVersion);
        if (!isset($matchesVersion[1])) {
            return new JsonResponse([
                'message' => 'Could not retrieve version from filename',
            ], Response::HTTP_BAD_REQUEST);
        }

        $this->version = $matchesVersion[1];
        if (strlen($this->version) < 1) {
            return new JsonResponse([
                'message' => sprintf(
                    'Version found not correct (%s) from filename %s',
                    $this->version,
                    $this->filename
                ),
            ], Response::HTTP_BAD_REQUEST);
        }

        $fileContent = @file_get_contents($this->nightlyReportPath . 'reports/' . $this->filename);
        if (!$fileContent) {
            return new JsonResponse([
                'message' => 'Unable to retrieve content from GCP URL',
            ], Response::HTTP_BAD_REQUEST);
        }

        $this->jsonContent = json_decode($fileContent);
        if (!$this->jsonContent) {
            return new JsonResponse([
                'message' => 'Unable to decode JSON data',
            ], Response::HTTP_BAD_REQUEST);
        }

        $force = $request->query->get('force', false);
        $force = is_bool($force) ? $force : false;

        $this->platform = $request->query->has('platform') ? $request->query->get('platform') : (
            $request->query->has('browser') ? $request->query->get('browser') : null
        );
        $this->platform = in_array($this->platform, ReportMochaImporter::FILTER_PLATFORMS) ? $this->platform : ReportMochaImporter::FILTER_PLATFORMS[0];

        $this->campaign = $request->query->has('campaign') ? $request->query->get('campaign') : null;
        if (!in_array($this->campaign, $allowedCampaigns)) {
            if ($forceCampaign) {
                $this->campaign = $allowedCampaigns[0];
            } else {
                return new JsonResponse([
                    'message' => sprintf(
                        'The campaign "%s" is not allowed (%s).',
                        $this->campaign,
                        implode(', ', $allowedCampaigns),
                    ),
                ], Response::HTTP_FORBIDDEN);
            }
        }

        $this->startDate = \DateTime::createFromFormat(\DateTime::RFC3339_EXTENDED, $this->jsonContent->stats->start ?? $this->jsonContent->stats->startTime);

        // Check if there is no similar entry
        if (!$force && $this->executionRepository->findOneByNightly($this->version, $this->platform, $this->campaign, $this->startDate->format('Y-m-d'))) {
            return new JsonResponse([
                'message' => sprintf(
                    'A similar entry was found (criteria: version %s, platform %s, campaign %s, date %s).',
                    $this->version,
                    $this->platform,
                    $this->campaign,
                    $this->startDate->format('Y-m-d')
                ),
            ], Response::HTTP_FORBIDDEN);
        }

        return null;
    }
}
