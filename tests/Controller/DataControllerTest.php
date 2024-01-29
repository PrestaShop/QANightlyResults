<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class DataControllerTest extends WebTestCase
{
    public function testCorsBadgeJson(): void
    {
        $client = static::createClient();
        $client->request('OPTIONS', '/data/badge');
        $response = $client->getResponse();
        $this->assertEquals($response->getStatusCode(), 200);
        $this->assertEquals($response->headers->get('access-control-allow-methods'), 'GET');
        $this->assertEquals($response->headers->get('access-control-max-age'), 3600);
        $this->assertEquals($response->headers->get('access-control-allow-origin'), '*');
    }

    public function testBadgeJson(): void
    {
        $client = static::createClient();
        $client->request('GET', '/data/badge');
        $response = $client->getResponse();

        $this->assertTrue($response->isSuccessful());
        $this->assertTrue($response->headers->has('content-type'));
        $this->assertEquals('application/json', $response->headers->get('content-type'));

        $content = $response->getContent();
        $content = json_decode($content, true);
        $this->assertArrayHasKey('schemaVersion', $content);
        $this->assertEquals('1', $content['schemaVersion']);
        $this->assertArrayHasKey('label', $content);
        $this->assertEquals('develop', $content['label']);
        $this->assertArrayHasKey('message', $content);
        $this->assertStringEndsWith('% passed', $content['message']);
        $this->assertArrayHasKey('color', $content);
        $this->assertContains($content['color'], ['red', 'orange', 'green']);
    }

    public function testBadgeJsonNotFound(): void
    {
        $client = static::createClient();
        $client->request('GET', '/data/badge?branch=1.6');
        $response = $client->getResponse();

        $this->assertEquals(404, $response->getStatusCode());
        $this->assertTrue($response->headers->has('content-type'));
        $this->assertEquals('application/json', $response->headers->get('content-type'));

        $content = $response->getContent();
        $content = json_decode($content, true);
        $this->assertArrayHasKey('message', $content);
        $this->assertEquals('Execution not found', $content['message']);
    }

    public function testCorsBadgeSvg(): void
    {
        $client = static::createClient();
        $client->request('OPTIONS', '/data/badge/svg');
        $response = $client->getResponse();
        $this->assertEquals($response->getStatusCode(), 200);
        $this->assertEquals($response->headers->get('access-control-allow-methods'), 'GET');
        $this->assertEquals($response->headers->get('access-control-max-age'), 3600);
        $this->assertEquals($response->headers->get('access-control-allow-origin'), '*');
    }

    public function testBadgeSvg(): void
    {
        $client = static::createClient();
        $client->request('GET', '/data/badge/svg');
        $response = $client->getResponse();

        $this->assertTrue($response->isSuccessful());
        $this->assertTrue($response->headers->has('content-type'));
        $this->assertEquals('image/svg+xml', $response->headers->get('content-type'));

        $content = $response->getContent();
        $this->assertStringContainsString('develop', $content);
    }

    public function testBadgeSvgNotFound(): void
    {
        $client = static::createClient();
        $client->request('GET', '/data/badge/svg?branch=1.6');
        $response = $client->getResponse();

        $this->assertEquals(404, $response->getStatusCode());
        $this->assertTrue($response->headers->has('content-type'));
        $this->assertEquals('text/html; charset=UTF-8', $response->headers->get('content-type'));

        $content = $response->getContent();
        $this->assertEquals('Execution not found', $content);
    }
}
