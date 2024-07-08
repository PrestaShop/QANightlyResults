<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ImportControllerTest extends WebTestCase
{
    private const DATE_RESOURCE = '2024-01-30';

    public function testReportMochaOkAutoupgrade(): void
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

    public function testReportMochaWithNoParameters(): void
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

    public function testReportMochaWithParameterToken(): void
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

    public function testReportMochaWithParameterFilename(): void
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

    public function testReportMochaWithParameterFilenameAndBakToken(): void
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

    public function testReportMochaWithNoVersionInFilename(): void
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

    public function testReportMochaWithBadVersionInFilename(): void
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

    public function testReportMochaWithNotExistingFilename(): void
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

    public function testReportMochaOk(): void
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

    public function testReportMochaAlreadyExisting(): void
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
        $this->assertEquals('A similar entry was found (criteria: version develop, platform chromium, campaign functional, database mysql, date ' . self::DATE_RESOURCE . ').', $content['message']);
    }

    public function testReportPlaywrightWithNoParameters(): void
    {
        $client = static::createClient();
        $client->request('GET', '/import/report/playwright');
        $response = $client->getResponse();

        $this->assertEquals(400, $response->getStatusCode());
        $this->assertTrue($response->headers->has('content-type'));
        $this->assertEquals('application/json', $response->headers->get('content-type'));

        $content = $response->getContent();
        $content = json_decode($content, true);
        $this->assertArrayHasKey('message', $content);
        $this->assertEquals('No enough parameters', $content['message']);
    }

    public function testReportPlaywrightWithParameterToken(): void
    {
        $client = static::createClient();
        $client->request('GET', '/import/report/playwright?token=AZERTY');
        $response = $client->getResponse();

        $this->assertEquals(400, $response->getStatusCode());
        $this->assertTrue($response->headers->has('content-type'));
        $this->assertEquals('application/json', $response->headers->get('content-type'));

        $content = $response->getContent();
        $content = json_decode($content, true);
        $this->assertArrayHasKey('message', $content);
        $this->assertEquals('No enough parameters', $content['message']);
    }

    public function testReportPlaywrightWithParameterFilename(): void
    {
        $client = static::createClient();
        $client->request('GET', '/import/report/playwright?filename=blockwishlist_' . self::DATE_RESOURCE . '-develop.json');
        $response = $client->getResponse();

        $this->assertEquals(400, $response->getStatusCode());
        $this->assertTrue($response->headers->has('content-type'));
        $this->assertEquals('application/json', $response->headers->get('content-type'));

        $content = $response->getContent();
        $content = json_decode($content, true);
        $this->assertArrayHasKey('message', $content);
        $this->assertEquals('No enough parameters', $content['message']);
    }

    public function testReportPlaywrightWithParameterFilenameAndBakToken(): void
    {
        $client = static::createClient();
        $client->request('GET', '/import/report/playwright?filename=blockwishlist_' . self::DATE_RESOURCE . '-develop.json&token=BAD');
        $response = $client->getResponse();

        $this->assertEquals(401, $response->getStatusCode());
        $this->assertTrue($response->headers->has('content-type'));
        $this->assertEquals('application/json', $response->headers->get('content-type'));

        $content = $response->getContent();
        $content = json_decode($content, true);
        $this->assertArrayHasKey('message', $content);
        $this->assertEquals('Invalid token', $content['message']);
    }

    public function testReportPlaywrightWithNoVersionInFilename(): void
    {
        $client = static::createClient();
        $client->request('GET', '/import/report/playwright?filename=blockwishlist_' . self::DATE_RESOURCE . '.json&token=AZERTY');
        $response = $client->getResponse();

        $this->assertEquals(400, $response->getStatusCode());
        $this->assertTrue($response->headers->has('content-type'));
        $this->assertEquals('application/json', $response->headers->get('content-type'));

        $content = $response->getContent();
        $content = json_decode($content, true);
        $this->assertArrayHasKey('message', $content);
        $this->assertEquals('Could not retrieve version from filename', $content['message']);
    }

    public function testReportPlaywrightWithBadVersionInFilename(): void
    {
        $client = static::createClient();
        $client->request('GET', '/import/report/playwright?filename=blockwishlist_' . self::DATE_RESOURCE . '-.json&token=AZERTY');
        $response = $client->getResponse();

        $this->assertEquals(400, $response->getStatusCode());
        $this->assertTrue($response->headers->has('content-type'));
        $this->assertEquals('application/json', $response->headers->get('content-type'));

        $content = $response->getContent();
        $content = json_decode($content, true);
        $this->assertArrayHasKey('message', $content);
        $this->assertEquals('Version found not correct () from filename blockwishlist_' . self::DATE_RESOURCE . '-.json', $content['message']);
    }

    public function testReportPlaywrightWithNotExistingFilename(): void
    {
        $client = static::createClient();
        $client->request('GET', '/import/report/playwright?filename=blockwishlist_' . self::DATE_RESOURCE . '-truc.json&token=AZERTY');
        $response = $client->getResponse();

        $this->assertEquals(400, $response->getStatusCode());
        $this->assertTrue($response->headers->has('content-type'));
        $this->assertEquals('application/json', $response->headers->get('content-type'));

        $content = $response->getContent();
        $content = json_decode($content, true);
        $this->assertArrayHasKey('message', $content);
        $this->assertEquals('Unable to retrieve content from GCP URL', $content['message']);
    }

    public function testReportPlaywrightWithNoValidCampaign(): void
    {
        $client = static::createClient();
        $client->request('GET', '/import/report/playwright?filename=blockwishlist_' . self::DATE_RESOURCE . '-develop.json&token=AZERTY&campaign=ps_notAllowedCampaign&platform=chromium');
        $response = $client->getResponse();

        $this->assertEquals(403, $response->getStatusCode());
        $this->assertTrue($response->headers->has('content-type'));
        $this->assertEquals('application/json', $response->headers->get('content-type'));

        $content = $response->getContent();
        $content = json_decode($content, true);
        $this->assertArrayHasKey('message', $content);
        $this->assertEquals('The campaign "ps_notAllowedCampaign" is not allowed (blockwishlist, ps_cashondelivery, autoupgrade).', $content['message']);
    }

    public function testReportPlaywrightOk(): void
    {
        $client = static::createClient();
        $client->request('GET', '/import/report/playwright?filename=blockwishlist_' . self::DATE_RESOURCE . '-develop.json&token=AZERTY&campaign=blockwishlist&platform=chromium');
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

    public function testReportPlaywrightAlreadyExisting(): void
    {
        $client = static::createClient();
        $client->request('GET', '/import/report/playwright?filename=blockwishlist_' . self::DATE_RESOURCE . '-develop.json&token=AZERTY&campaign=blockwishlist&platform=chromium');
        $response = $client->getResponse();

        $this->assertEquals(403, $response->getStatusCode());
        $this->assertTrue($response->headers->has('content-type'));
        $this->assertEquals('application/json', $response->headers->get('content-type'));

        $content = $response->getContent();
        $content = json_decode($content, true);
        $this->assertArrayHasKey('message', $content);
        $this->assertEquals('A similar entry was found (criteria: version develop, platform chromium, campaign blockwishlist, database mysql, date ' . self::DATE_RESOURCE . ').', $content['message']);
    }
}
