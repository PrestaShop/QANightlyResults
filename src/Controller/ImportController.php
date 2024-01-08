<?php

namespace App\Controller;

use App\Repository\ExecutionRepository;
use App\Service\ReportImporter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ImportController extends AbstractController
{
    private ExecutionRepository $executionRepository;

    private ReportImporter $reportImporter;

    private string $nightlyToken;

    private string $nightlyGCPUrl;

    private ?string $filename;

    private ?string $version;

    private ?\stdClass $jsonContent;

    private ?string $platform;

    private ?string $campaign;

    private ?\DateTime $startDate;

    public function __construct(
        ExecutionRepository $executionRepository,
        ReportImporter $reportImporter,
        string $nightlyToken,
        string $nightlyGCPUrl
    ) {
        $this->executionRepository = $executionRepository;
        $this->reportImporter = $reportImporter;
        $this->nightlyToken = $nightlyToken;
        $this->nightlyGCPUrl = $nightlyGCPUrl;
    }

    #[Route('/hook/add', methods: ['GET'])]
    /** Used in 1.7.7 & autoupgrade */
    public function importReportOld(Request $request): JsonResponse
    {
        $response = $this->checkAuth($request, ReportImporter::FORMAT_DATE_MOCHA5);
        if ($response instanceof JsonResponse) {
            return $response;
        }

        $execution = $this->reportImporter->import(
            $this->filename,
            $this->platform,
            $this->campaign,
            $this->version,
            $this->startDate,
            $this->jsonContent,
            ReportImporter::FORMAT_DATE_MOCHA5
        );

        return new JsonResponse([
            'status' => 'ok',
            'report' => $execution->getId(),
        ]);
    }

    #[Route('/hook/reports/import', methods: ['GET'])]
    /** Used in 1.7.8+ */
    public function importReport(Request $request): JsonResponse
    {
        $response = $this->checkAuth($request, ReportImporter::FORMAT_DATE_MOCHA6);
        if ($response instanceof JsonResponse) {
            return $response;
        }

        $execution = $this->reportImporter->import(
            $this->filename,
            $this->platform,
            $this->campaign,
            $this->version,
            $this->startDate,
            $this->jsonContent,
            ReportImporter::FORMAT_DATE_MOCHA6
        );

        return new JsonResponse([
            'status' => 'ok',
            'report' => $execution->getId(),
        ]);
    }

    private function checkAuth(Request $request, string $dateFormat): ?JsonResponse
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

        $fileContent = @file_get_contents($this->nightlyGCPUrl . 'reports/' . $this->filename);
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
        $this->platform = in_array($this->platform, ReportImporter::FILTER_PLATFORMS) ? $this->platform : ReportImporter::FILTER_PLATFORMS[0];

        $this->campaign = $request->query->has('campaign') ? $request->query->get('campaign') : null;
        $this->campaign = in_array($this->campaign, ReportImporter::FILTER_CAMPAIGNS) ? $this->campaign : ReportImporter::FILTER_CAMPAIGNS[0];

        $this->startDate = \DateTime::createFromFormat($dateFormat, $this->jsonContent->stats->start);

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
