<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class GraphControllerTest extends WebTestCase
{
    public function testCorsGraph(): void
    {
        $client = static::createClient();
        $client->request('OPTIONS', '/graph');
        $response = $client->getResponse();
        $this->assertEquals($response->getStatusCode(), 200);
        $this->assertEquals($response->headers->get('access-control-allow-methods'), 'GET');
        $this->assertEquals($response->headers->get('access-control-max-age'), 3600);
        $this->assertEquals($response->headers->get('access-control-allow-origin'), '*');
    }

    public function testGraph(): void
    {
        $client = static::createClient();
        $client->request('GET', '/graph?start_date=2023-11-01');
        $response = $client->getResponse();

        $this->assertTrue($response->isSuccessful());
        $this->assertTrue($response->headers->has('content-type'));
        $this->assertEquals('application/json', $response->headers->get('content-type'));

        $content = json_decode($response->getContent(), true);
        $this->assertGreaterThan(0, count($content));
        foreach ($content as $item) {
            $this->assertArrayHasKey('id', $item);
            $this->assertIsInt($item['id']);
            $this->assertArrayHasKey('start_date', $item);
            $this->assertArrayHasKey('end_date', $item);
            $this->assertArrayHasKey('version', $item);
            $this->assertEquals('develop', $item['version']);
            $this->assertArrayHasKey('suites', $item);
            $this->assertIsInt($item['suites']);
            $this->assertArrayHasKey('tests', $item);
            $this->assertIsInt($item['tests']);
            $this->assertArrayHasKey('skipped', $item);
            $this->assertIsInt($item['skipped']);
            $this->assertArrayHasKey('passes', $item);
            $this->assertIsInt($item['passes']);
            $this->assertArrayHasKey('failures', $item);
            $this->assertIsInt($item['failures']);
            $this->assertArrayHasKey('pending', $item);
            $this->assertIsInt($item['pending']);
        }
    }

    public function testCorsGraphParameters(): void
    {
        $client = static::createClient();
        $client->request('OPTIONS', '/graph/parameters');
        $response = $client->getResponse();
        $this->assertEquals($response->getStatusCode(), 200);
        $this->assertEquals($response->headers->get('access-control-allow-methods'), 'GET');
        $this->assertEquals($response->headers->get('access-control-max-age'), 3600);
        $this->assertEquals($response->headers->get('access-control-allow-origin'), '*');
    }

    public function testParameters(): void
    {
        $client = static::createClient();
        $client->request('GET', '/graph/parameters');
        $response = $client->getResponse();

        $this->assertTrue($response->isSuccessful());
        $this->assertTrue($response->headers->has('content-type'));
        $this->assertEquals('application/json', $response->headers->get('content-type'));

        $content = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('periods', $content);
        $this->assertIsArray($content['periods']);
        $this->assertArrayHasKey('type', $content['periods']);
        $this->assertEquals('select', $content['periods']['type']);
        $this->assertArrayHasKey('name', $content['periods']);
        $this->assertEquals('period', $content['periods']['name']);
        $this->assertArrayHasKey('values', $content['periods']);
        $this->assertIsArray($content['periods']['values']);
        $this->assertEquals('Last 30 days', $content['periods']['values'][0]['name']);
        $this->assertEquals('last_month', $content['periods']['values'][0]['value']);
        $this->assertEquals('Last 60 days', $content['periods']['values'][1]['name']);
        $this->assertEquals('last_two_months', $content['periods']['values'][1]['value']);
        $this->assertEquals('Last 12 months', $content['periods']['values'][2]['name']);
        $this->assertEquals('last_year', $content['periods']['values'][2]['value']);
        $this->assertArrayHasKey('default', $content['periods']);
        $this->assertEquals('last_month', $content['periods']['default']);

        $this->assertArrayHasKey('versions', $content);
        $this->assertIsArray($content['versions']);
        $this->assertArrayHasKey('type', $content['versions']);
        $this->assertEquals('select', $content['versions']['type']);
        $this->assertArrayHasKey('name', $content['versions']);
        $this->assertEquals('version', $content['versions']['name']);
        $this->assertArrayHasKey('values', $content['versions']);
        $this->assertIsArray($content['versions']['values']);
        $this->assertEquals('Develop', $content['versions']['values'][0]['name']);
        $this->assertEquals('develop', $content['versions']['values'][0]['value']);
        $this->assertArrayHasKey('default', $content['versions']);
        $this->assertEquals('develop', $content['versions']['default']);
    }
}
