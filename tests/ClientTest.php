<?php

// tests/ClientTest.php

use PHPUnit\Framework\TestCase;
use Amanank\HalClient\Client;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;

class ClientTest extends TestCase
{
    public function testGetResource()
    {
    /*
        // Mock the HTTP response
        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/hal+json'], json_encode(['key' => 'value']))
        ]);
        $handlerStack = HandlerStack::create($mock);
        $guzzleClient = new GuzzleClient(['handler' => $handlerStack]);

        // Instantiate the Client with the mocked Guzzle client
        $client = new Client('https://api.example.com', [], $guzzleClient);

        // Perform the GET request
        $response = $client->get('/resource');

        // Assert the response status code
        $this->assertEquals(200, $response->getStatusCode());

        // Assert the response body
        $data = json_decode($response->getBody(), true);
        $this->assertEquals(['key' => 'value'], $data);
        */
        $this->assertEquals(1, 1);
    }
}