<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class HealthCheckControllerTest extends WebTestCase
{
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