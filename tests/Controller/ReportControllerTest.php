<?php

namespace App\Tests\Controller;

use App\Service\ReportImporter;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ReportControllerTest extends WebTestCase
{
    private static int $reportId = 0;
    private static int $suiteId = 0;

    public static function setUpBeforeClass(): void
    {
        $data = file_get_contents('https://api-nightly.prestashop-project.org/reports?filter_version=develop&filter_campaign=functional');
        $data = json_decode($data, true);
        self::$reportId = $data[2]['id'];

        $data = file_get_contents('https://api-nightly.prestashop-project.org/reports/' . self::$reportId);
        $data = json_decode($data, true);
        self::$suiteId = array_key_first($data['suites_data']);
    }

    public function testReports(): void
    {
        $client = static::createClient();
        $client->request('GET', '/reports');
        $response = $client->getResponse();

        $this->assertTrue($response->isSuccessful());
        $this->assertTrue($response->headers->has('content-type'));
        $this->assertEquals('application/json', $response->headers->get('content-type'));

        $content = json_decode($response->getContent(), true);
        $this->assertGreaterThan(0, count($content));
        $datePrevious = null;
        foreach ($content as $item) {
            if ($datePrevious) {
                $this->assertGreaterThanOrEqual($item['start_date'], $datePrevious);
            }
            $datePrevious = $item['start_date'];
            $this->assertArrayHasKey('id', $item);
            $this->assertIsInt($item['id']);
            $this->assertArrayHasKey('date', $item);
            $this->assertArrayHasKey('version', $item);
            $this->assertArrayHasKey('campaign', $item);
            $this->assertContains($item['campaign'], ReportImporter::FILTER_CAMPAIGNS);
            $this->assertArrayHasKey('browser', $item);
            $this->assertContains($item['browser'], ReportImporter::FILTER_PLATFORMS);
            $this->assertArrayHasKey('platform', $item);
            $this->assertContains($item['platform'], ReportImporter::FILTER_PLATFORMS);
            $this->assertEquals($item['browser'], $item['platform']);
            $this->assertArrayHasKey('start_date', $item);
            $this->assertArrayHasKey('end_date', $item);
            $this->assertArrayHasKey('duration', $item);
            $this->assertIsInt($item['duration']);
            $this->assertArrayHasKey('suites', $item);
            $this->assertArrayHasKey('tests', $item);
            $this->assertIsArray($item['tests']);
            $this->assertArrayHasKey('total', $item['tests']);
            $this->assertArrayHasKey('passed', $item['tests']);
            $this->assertArrayHasKey('failed', $item['tests']);
            $this->assertArrayHasKey('pending', $item['tests']);
            $this->assertArrayHasKey('skipped', $item['tests']);
            $this->assertArrayHasKey('broken_since_last', $item);
            $this->assertArrayHasKey('fixed_since_last', $item);
            $this->assertArrayHasKey('equal_since_last', $item);
            $this->assertArrayHasKey('download', $item);
            $this->assertArrayHasKey('xml', $item);
        }
    }

    public function testReportNotFound(): void
    {
        $client = static::createClient();
        $client->request('GET', '/reports/1234567890');
        $response = $client->getResponse();

        $this->assertEquals(404, $response->getStatusCode());
        $this->assertTrue($response->headers->has('content-type'));
        $this->assertEquals('application/json', $response->headers->get('content-type'));

        $content = $response->getContent();
        $content = json_decode($content, true);
        $this->assertArrayHasKey('message', $content);
        $this->assertEquals('Execution not found', $content['message']);
    }

    public function testReportID(): void
    {
        $client = static::createClient();
        $client->request('GET', '/reports/2');
        $response = $client->getResponse();

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($response->headers->has('content-type'));
        $this->assertEquals('application/json', $response->headers->get('content-type'));

        $content = $response->getContent();
        $content = json_decode($content, true);

        $this->assertArrayHasKey('id', $content);
        $this->assertIsInt($content['id']);
        $this->assertArrayHasKey('date', $content);
        $this->assertArrayHasKey('version', $content);
        $this->assertArrayHasKey('campaign', $content);
        $this->assertContains($content['campaign'], ReportImporter::FILTER_CAMPAIGNS);
        $this->assertArrayHasKey('browser', $content);
        $this->assertContains($content['browser'], ReportImporter::FILTER_PLATFORMS);
        $this->assertArrayHasKey('platform', $content);
        $this->assertContains($content['platform'], ReportImporter::FILTER_PLATFORMS);
        $this->assertEquals($content['browser'], $content['platform']);
        $this->assertArrayHasKey('start_date', $content);
        $this->assertArrayHasKey('end_date', $content);
        $this->assertArrayHasKey('duration', $content);
        $this->assertIsInt($content['duration']);
        $this->assertArrayHasKey('suites', $content);
        $this->assertIsInt($content['tests']);
        $this->assertArrayHasKey('tests', $content);
        $this->assertIsInt($content['tests']);
        $this->assertArrayHasKey('broken_since_last', $content);
        $this->assertArrayHasKey('fixed_since_last', $content);
        $this->assertArrayHasKey('equal_since_last', $content);
        $this->assertArrayHasKey('skipped', $content);
        $this->assertIsInt($content['skipped']);
        $this->assertArrayHasKey('pending', $content);
        $this->assertIsInt($content['pending']);
        $this->assertArrayHasKey('passes', $content);
        $this->assertIsInt($content['passes']);
        $this->assertArrayHasKey('failures', $content);
        $this->assertIsInt($content['failures']);

        $this->assertArrayHasKey('suites_data', $content);
        $this->assertIsArray($content['suites_data']);
        foreach ($content['suites_data'] as $suiteId => $suiteItem) {
            $this->partialTestSuite($content['id'], $suiteId, $suiteItem, null, true);
        }
    }

    public function testReportIDSuiteID(): void
    {
        $client = static::createClient();
        $client->request('GET', '/reports/2/suites/4');
        $response = $client->getResponse();

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($response->headers->has('content-type'));
        $this->assertEquals('application/json', $response->headers->get('content-type'));

        $content = $response->getContent();
        $content = json_decode($content, true);

        $this->partialTestSuite(2, 4, $content, null, false);
    }

    public function testCompareReport(): void
    {
        $client = static::createClient();
        $client->request('GET', '/reports/2');
        $response = $client->getResponse();
        $content = $response->getContent();
        $content = json_decode($content, true);

        $data = \file_get_contents('https://api-nightly.prestashop-project.org/reports/' . self::$reportId);
        $data = json_decode($data, true);

        $this->partialCompare($data, $content);
    }

    public function testCompareReportFilterText(): void
    {
        $client = static::createClient();
        $client->request('GET', '/reports/2?search=currency');
        $response = $client->getResponse();
        $content = $response->getContent();
        $content = json_decode($content, true);

        $data = \file_get_contents('https://api-nightly.prestashop-project.org/reports/' . self::$reportId . '?search=currency');
        $data = json_decode($data, true);

        $this->partialCompare($data, $content);
    }

    public function testCompareReportFilterState(): void
    {
        $states = [
            'failed',
            'pending',
            'skipped',
            'passed',
        ];
        $client = static::createClient();

        foreach ($states as $stateRemoved) {
            $query = [];
            foreach ($states as $state) {
                if ($state === $stateRemoved) {
                    continue;
                }
                $query[] = 'filter_state[]=' . $state;
            }

            $client->request('GET', '/reports/2?' . implode('&', $query));
            $response = $client->getResponse();
            $content = $response->getContent();
            $content = json_decode($content, true);

            $data = \file_get_contents('https://api-nightly.prestashop-project.org/reports/' . self::$reportId . '?' . implode('&', $query));
            $data = json_decode($data, true);

            $this->partialCompare($data, $content);
        }
    }

    public function testCompareSuite(): void
    {
        $client = static::createClient();
        $client->request('GET', '/reports/2/suites/4');
        $response = $client->getResponse();
        $content = $response->getContent();
        $content = json_decode($content, true);

        $data = \file_get_contents('https://api-nightly.prestashop-project.org/reports/' . self::$reportId . '/suites/' . self::$suiteId);
        $data = json_decode($data, true);

        $this->partialCompareSuite($data, $content);
    }

    /**
     * @param array<string, string|array<string, string>> $expected
     * @param array<string, string|array<string, string>> $actual
     */
    private function partialCompare(array $expected, array $actual): void
    {
        foreach ($expected as $expectedKey => $expectedValue) {
            if (in_array($expectedKey, [
                'id',
                'start_date',
                'end_date',
            ])) {
                continue;
            }
            if ($expectedKey == 'suites_data') {
                $expectedArrayKeys = array_keys($expected['suites_data']);
                $actualArrayKeys = array_keys($actual['suites_data']);
                $this->assertEquals(count($expectedArrayKeys), count($actualArrayKeys));
                foreach ($expectedArrayKeys as $key => $value) {
                    // @phpstan-ignore-next-line
                    $this->partialCompareSuite($expected['suites_data'][$value], $actual['suites_data'][$actualArrayKeys[$key]]);
                }
                continue;
            }
            $this->assertEquals($expectedValue, $actual[$expectedKey], 'Key Root : ' . $expectedKey);
        }
    }

    /**
     * @param array<string, string|array<string, string>> $expected
     * @param array<string, string|array<string, string>> $actual
     */
    private function partialCompareSuite(array $expected, array $actual): void
    {
        foreach ($expected as $expectedKey => $expectedValue) {
            $actualValue = $actual[$expectedKey];
            if (in_array($expectedKey, [
                'id',
                'execution_id',
                'insertion_date',
                'parent_id',
            ])) {
                continue;
            }
            if ($expectedKey == 'tests') {
                $this->assertEquals(count($expectedValue), count($actualValue));
                foreach ($expectedValue as $key => $expectedItemValue) {
                    // @phpstan-ignore-next-line
                    $this->partialCompareTest($expectedItemValue, $actualValue[$key]);
                }
                continue;
            }
            if ($expectedKey == 'suites') {
                $expectedArrayKeys = array_keys($expectedValue);
                $actualArrayKeys = array_keys($actualValue);
                $this->assertEquals(count($expectedArrayKeys), count($actualArrayKeys));
                foreach ($expectedArrayKeys as $key => $value) {
                    // @phpstan-ignore-next-line
                    $this->partialCompareSuite($expectedValue[$value], $actualValue[$actualArrayKeys[$key]]);
                }
                continue;
            }
            $this->assertEquals($expectedValue, $actualValue, 'Key Suite : ' . $expectedKey);
        }
    }

    /**
     * @param array<string, string> $expected
     * @param array<string, string> $actual
     */
    private function partialCompareTest(array $expected, array $actual): void
    {
        foreach ($expected as $expectedKey => $expectedValue) {
            $actualValue = $actual[$expectedKey];
            if (in_array($expectedKey, [
                'id',
                'suite_id',
                'insertion_date',
            ])) {
                continue;
            }
            $this->assertEquals($expectedValue, $actualValue, 'Key Test: ' . $expectedKey);
        }
    }

    /**
     * @param array<string, string> $item
     */
    private function partialTestSuite(int $executionId, int $id, array $item, int $idParent = null, bool $hasChildrenData = null): void
    {
        $this->assertIsInt($id);

        $this->assertArrayHasKey('id', $item);
        $this->assertIsInt($item['id']);
        $this->assertEquals($item['id'], $id);
        $this->assertArrayHasKey('execution_id', $item);
        $this->assertEquals($item['execution_id'], $executionId);
        $this->assertArrayHasKey('uuid', $item);
        $this->assertArrayHasKey('title', $item);
        $this->assertArrayHasKey('campaign', $item);
        $this->assertArrayHasKey('file', $item);
        $this->assertArrayHasKey('duration', $item);
        $this->assertArrayHasKey('hasSkipped', $item);
        $this->assertIsInt($item['hasSkipped']);
        $this->assertArrayHasKey('hasPending', $item);
        $this->assertIsInt($item['hasPending']);
        $this->assertArrayHasKey('hasPasses', $item);
        $this->assertIsInt($item['hasPasses']);
        $this->assertArrayHasKey('hasFailures', $item);
        $this->assertIsInt($item['hasFailures']);
        $this->assertArrayHasKey('totalSkipped', $item);
        $this->assertIsInt($item['totalSkipped']);
        $this->assertArrayHasKey('totalPending', $item);
        $this->assertIsInt($item['totalPending']);
        $this->assertArrayHasKey('totalPasses', $item);
        $this->assertIsInt($item['totalPasses']);
        $this->assertArrayHasKey('totalFailures', $item);
        $this->assertIsInt($item['totalFailures']);
        $this->assertArrayHasKey('hasSuites', $item);
        $this->assertIsInt($item['hasSuites']);
        $this->assertArrayHasKey('hasTests', $item);
        $this->assertIsInt($item['hasTests']);
        $this->assertArrayHasKey('parent_id', $item);
        if (!$idParent) {
            $this->assertEquals($item['parent_id'], $idParent);
        }
        $this->assertArrayHasKey('insertion_date', $item);

        if ($item['hasSuites']) {
            $this->assertArrayHasKey('suites', $item);
            $this->assertIsArray($item['suites']);
            $this->assertGreaterThan(0, count($item['suites']));
            foreach ($item['suites'] as $suiteChildId => $suiteChild) {
                $this->assertIsInt($suiteChildId);
                $this->partialTestSuite($executionId, $suiteChildId, $suiteChild, $id);
            }
        }
        if ($item['hasTests']) {
            $this->assertArrayHasKey('tests', $item);
            $this->assertIsArray($item['tests']);
            $this->assertGreaterThan(0, count($item['tests']));
            foreach ($item['tests'] as $testItem) {
                $this->partialTestTest($item['id'], $testItem);
            }
        }

        if (is_bool($hasChildrenData) && $hasChildrenData) {
            $this->assertArrayHasKey('childrenData', $item);
            $this->assertIsArray($item['childrenData']);
            $this->assertArrayHasKey('totalPasses', $item['childrenData']);
            $this->assertIsInt($item['childrenData']['totalPasses']);
            $this->assertGreaterThanOrEqual($item['totalPasses'], $item['childrenData']['totalPasses']);
            $this->assertArrayHasKey('totalFailures', $item['childrenData']);
            $this->assertIsInt($item['childrenData']['totalFailures']);
            $this->assertGreaterThanOrEqual($item['totalFailures'], $item['childrenData']['totalFailures']);
            $this->assertArrayHasKey('totalPending', $item['childrenData']);
            $this->assertIsInt($item['childrenData']['totalPending']);
            $this->assertGreaterThanOrEqual($item['totalPending'], $item['childrenData']['totalPending']);
            $this->assertArrayHasKey('totalSkipped', $item['childrenData']);
            $this->assertIsInt($item['childrenData']['totalSkipped']);
            $this->assertGreaterThanOrEqual($item['totalSkipped'], $item['childrenData']['totalSkipped']);
        } else {
            $this->assertArrayNotHasKey('childrenData', $item);
        }
    }

    /**
     * @param array<string, string> $test
     */
    private function partialTestTest(int $suiteId, array $test): void
    {
        $this->assertArrayHasKey('id', $test);
        $this->assertIsInt($test['id']);
        $this->assertArrayHasKey('suite_id', $test);
        $this->assertEquals($test['suite_id'], $suiteId);
        $this->assertArrayHasKey('uuid', $test);
        $this->assertArrayHasKey('identifier', $test);
        $this->assertArrayHasKey('title', $test);
        $this->assertArrayHasKey('state', $test);
        $this->assertArrayHasKey('duration', $test);
        $this->assertArrayHasKey('error_message', $test);
        $this->assertArrayHasKey('stack_trace', $test);
        $this->assertArrayHasKey('diff', $test);
        $this->assertArrayHasKey('insertion_date', $test);
    }
}