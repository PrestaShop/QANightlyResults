<?php

namespace App\Controller;

use App\Repository\ExecutionRepository;
use App\Service\ReportMochaImporter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ImportController extends AbstractController
{
    private ExecutionRepository $executionRepository;

    private ReportMochaImporter $reportImporter;

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
        ReportMochaImporter $reportImporter,
        string $nightlyToken,
        string $nightlyReportPath
    ) {
        $this->executionRepository = $executionRepository;
        $this->reportImporter = $reportImporter;
        $this->nightlyToken = $nightlyToken;
        $this->nightlyReportPath = $nightlyReportPath;
    }

    #[Route('/hook/reports/import', methods: ['GET'])]
    public function importReport(Request $request): JsonResponse
    {
        $response = $this->checkAuth($request);
        if ($response instanceof JsonResponse) {
            return $response;
        }

        $execution = $this->reportImporter->import(
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

    private function checkAuth(Request $request): ?JsonResponse
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
        $this->campaign = in_array($this->campaign, ReportMochaImporter::FILTER_CAMPAIGNS) ? $this->campaign : ReportMochaImporter::FILTER_CAMPAIGNS[0];

        $this->startDate = \DateTime::createFromFormat(\DateTime::RFC3339_EXTENDED, $this->jsonContent->stats->start);

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
