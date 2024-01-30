<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ImportControllerTest extends WebTestCase
{
    private const DATE_RESOURCE = '2024-01-30';

    public function testReportOkAutoupgrade(): void
    {
        $client = static::createClient();
        $client->request('GET', '/hook/reports/import?filename=autoupgrade_' . self::DATE_RESOURCE . '-develop.json&token=AZERTY&campaign=autoupgrade&platform=cli');

        $response = $client->getResponse();

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($response->headers->has('content-type'));
        $this->assertEquals('application/json', $response->headers->get('content-type'));

        $content = $response->getContent();
        $content = json_decode($content, true);
        $this->assertArrayHasKey('status', $content);
        $this->assertEquals('ok', $content['status']);
        $this->assertArrayHasKey('report', $content);
        $this->assertIsInt($content['report']);
    }

    public function testReportWithNoParameters(): void
    {
        $client = static::createClient();
        $client->request('GET', '/hook/reports/import');
        $response = $client->getResponse();

        $this->assertEquals(400, $response->getStatusCode());
        $this->assertTrue($response->headers->has('content-type'));
        $this->assertEquals('application/json', $response->headers->get('content-type'));

        $content = $response->getContent();
        $content = json_decode($content, true);
        $this->assertArrayHasKey('message', $content);
        $this->assertEquals('No enough parameters', $content['message']);
    }

    public function testReportWithParameterToken(): void
    {
        $client = static::createClient();
        $client->request('GET', '/hook/reports/import?token=AZERTY');
        $response = $client->getResponse();

        $this->assertEquals(400, $response->getStatusCode());
        $this->assertTrue($response->headers->has('content-type'));
        $this->assertEquals('application/json', $response->headers->get('content-type'));

        $content = $response->getContent();
        $content = json_decode($content, true);
        $this->assertArrayHasKey('message', $content);
        $this->assertEquals('No enough parameters', $content['message']);
    }

    public function testReportWithParameterFilename(): void
    {
        $client = static::createClient();
        $client->request('GET', '/hook/reports/import?filename=' . self::DATE_RESOURCE . '-develop.json');
        $response = $client->getResponse();

        $this->assertEquals(400, $response->getStatusCode());
        $this->assertTrue($response->headers->has('content-type'));
        $this->assertEquals('application/json', $response->headers->get('content-type'));

        $content = $response->getContent();
        $content = json_decode($content, true);
        $this->assertArrayHasKey('message', $content);
        $this->assertEquals('No enough parameters', $content['message']);
    }

    public function testReportWithParameterFilenameAndBakToken(): void
    {
        $client = static::createClient();
        $client->request('GET', '/hook/reports/import?filename=' . self::DATE_RESOURCE . '-develop.json&token=BAD');
        $response = $client->getResponse();

        $this->assertEquals(401, $response->getStatusCode());
        $this->assertTrue($response->headers->has('content-type'));
        $this->assertEquals('application/json', $response->headers->get('content-type'));

        $content = $response->getContent();
        $content = json_decode($content, true);
        $this->assertArrayHasKey('message', $content);
        $this->assertEquals('Invalid token', $content['message']);
    }

    public function testReportWithNoVersionInFilename(): void
    {
        $client = static::createClient();
        $client->request('GET', '/hook/reports/import?filename=' . self::DATE_RESOURCE . '.json&token=AZERTY');
        $response = $client->getResponse();

        $this->assertEquals(400, $response->getStatusCode());
        $this->assertTrue($response->headers->has('content-type'));
        $this->assertEquals('application/json', $response->headers->get('content-type'));

        $content = $response->getContent();
        $content = json_decode($content, true);
        $this->assertArrayHasKey('message', $content);
        $this->assertEquals('Could not retrieve version from filename', $content['message']);
    }

    public function testReportWithBadVersionInFilename(): void
    {
        $client = static::createClient();
        $client->request('GET', '/hook/reports/import?filename=' . self::DATE_RESOURCE . '-.json&token=AZERTY');
        $response = $client->getResponse();

        $this->assertEquals(400, $response->getStatusCode());
        $this->assertTrue($response->headers->has('content-type'));
        $this->assertEquals('application/json', $response->headers->get('content-type'));

        $content = $response->getContent();
        $content = json_decode($content, true);
        $this->assertArrayHasKey('message', $content);
        $this->assertEquals('Version found not correct () from filename ' . self::DATE_RESOURCE . '-.json', $content['message']);
    }

    public function testReportWithNotExistingFilename(): void
    {
        $client = static::createClient();
        $client->request('GET', '/hook/reports/import?filename=' . self::DATE_RESOURCE . '-truc.json&token=AZERTY');
        $response = $client->getResponse();

        $this->assertEquals(400, $response->getStatusCode());
        $this->assertTrue($response->headers->has('content-type'));
        $this->assertEquals('application/json', $response->headers->get('content-type'));

        $content = $response->getContent();
        $content = json_decode($content, true);
        $this->assertArrayHasKey('message', $content);
        $this->assertEquals('Unable to retrieve content from GCP URL', $content['message']);
    }

    public function testReportOk(): void
    {
        $client = static::createClient();
        $client->request('GET', '/hook/reports/import?filename=' . self::DATE_RESOURCE . '-develop.json&token=AZERTY');
        $response = $client->getResponse();

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($response->headers->has('content-type'));
        $this->assertEquals('application/json', $response->headers->get('content-type'));

        $content = $response->getContent();
        $content = json_decode($content, true);
        $this->assertArrayHasKey('status', $content);
        $this->assertEquals('ok', $content['status']);
        $this->assertArrayHasKey('report', $content);
        $this->assertIsInt($content['report']);
    }

    public function testReportAlreadyExisting(): void
    {
        $client = static::createClient();
        $client->request('GET', '/hook/reports/import?filename=' . self::DATE_RESOURCE . '-develop.json&token=AZERTY');
        $response = $client->getResponse();

        $this->assertEquals(403, $response->getStatusCode());
        $this->assertTrue($response->headers->has('content-type'));
        $this->assertEquals('application/json', $response->headers->get('content-type'));

        $content = $response->getContent();
        $content = json_decode($content, true);
        $this->assertArrayHasKey('message', $content);
        $this->assertEquals('A similar entry was found (criteria: version develop, platform chromium, campaign functional, date ' . self::DATE_RESOURCE . ').', $content['message']);
    }
}
