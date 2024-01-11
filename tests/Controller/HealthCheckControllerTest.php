<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class HealthCheckControllerTest extends WebTestCase
{
    public function testCorsHealthcheck(): void
    {
        $client = static::createClient();
        $client->request('OPTIONS', '/healthcheck');
        $response = $client->getResponse();
        $this->assertEquals($response->getStatusCode(), 200);
        $this->assertEquals($response->headers->get('access-control-allow-methods'), 'GET');
        $this->assertEquals($response->headers->get('access-control-max-age'), 3600);
        $this->assertEquals($response->headers->get('access-control-allow-origin'), '*');
    }

    public function testHealthcheck(): void
    {
        $client = static::createClient();
        $client->request('GET', '/healthcheck');
        $response = $client->getResponse();

        $this->assertTrue($response->isSuccessful());
        $this->assertTrue($response->headers->has('content-type'));
        $this->assertEquals('application/json', $response->headers->get('content-type'));

        $content = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('database', $content);
        $this->assertEquals(true, $content['database']);
        $this->assertArrayHasKey('gcp', $content);
        $this->assertEquals(true, $content['gcp']);
    }
}
