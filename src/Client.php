<?php
namespace Amanank\HalClient;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\RequestException;

class Client {
    private $client;

    public function __construct($config = []) {
        $this->client = new GuzzleClient($config);
    }

    public function get($uri, $options = [])
    {
        try {
            $response = $this->client->request('GET', $uri, $options);
            return $response;
        } catch (RequestException $e) {
            // Handle exception or rethrow
            throw $e;
        }
    }

    public function post($uri, $data = [], $options = [])
    {
        try {
            $options['json'] = $data;
            $response = $this->client->request('POST', $uri, $options);
            return $response;
        } catch (RequestException $e) {
            // Handle exception or rethrow
            throw $e;
        }
    }

    public function put($uri, $data = [], $options = [])
    {
        try {
            $options['json'] = $data;
            $response = $this->client->request('PUT', $uri, $options);
            return $response;
        } catch (RequestException $e) {
            // Handle exception or rethrow
            throw $e;
        }
    }

    public function delete($uri, $options = [])
    {
        try {
            $response = $this->client->request('DELETE', $uri, $options);
            return $response;
        } catch (RequestException $e) {
            // Handle exception or rethrow
            throw $e;
        }
    }
}