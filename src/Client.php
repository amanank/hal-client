<?php

namespace Amanank\HalClient;

use Amanank\HalClient\Query\QueryBuilder;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\RequestException;

class Client {
    private $client;

    public function __construct($config = []) {
        $config['allow_redirects'] = false;
        $this->client = new GuzzleClient($config);
    }

    public function query() {
        return null;
    }

    public function get($uri, $options = []) {
        try {
            $response = $this->client->request('GET', $uri, $options);
            return $response;
        } catch (RequestException $e) {
            // Handle exception or rethrow
            throw $e;
        }
    }

    public function getData($uri, $options = []): array {
        try {
            $response = $this->client->request('GET', $uri, $options);
            if ($response->getStatusCode() == 404) {
                return null;
            } else if ($response->getStatusCode() != 200) {
                throw new \Exception("Unknown Get response status code {$response->getStatusCode()} expecting 200 or 404");
            }
            return json_decode($response->getBody()->getContents(), true);
        } catch (RequestException $e) {
            // Handle exception or rethrow
            throw $e;
        }
    }

    public function getJson($uri, $options = []): array {
        $response = $this->get($uri, $options);
        if ($response->getStatusCode() == 404) {
            return null;
        } else if ($response->getStatusCode() != 200) {
            throw new \Exception("Unknown Get response status code {$response->getStatusCode()} expecting 200 or 404");
        }
        return json_decode($response->getBody()->getContents(), true);
    }

    public function post($uri, $data = [], $options = []) {
        try {
            $options['json'] = $data;
            $response = $this->client->request('POST', $uri, $options);
            return $response;
        } catch (RequestException $e) {
            // Handle exception or rethrow
            throw $e;
        }
    }

    public function update($uri, $data = [], $options = []): bool {
        $response = $this->put($uri, $data, $options);
        // check for no content response
        if ($response->getStatusCode() == 200 || $response->getStatusCode() == 204) {
            return true;
        } else {
            throw new \Exception("Unknown Put response status code {$response->getStatusCode()}");
        }
    }

    public function create($uri, $data = [], $options = []) {
        $response = $this->post($uri, $data, $options);
        if ($response->getStatusCode() == 201) {
            // get redirect location
            $location = $response->getHeader('Location');
            if ($location) {
                return $location[0];
            } else {
                return throw new \Exception('Post response does not contain a location header'); //TODO: return custom exception with request/response details
            }
        } else {
            throw new \Exception('Post response status code is not 201');
        }
    }

    public function put($uri, $data = [], $options = []) {
        try {
            $options['json'] = $data;
            $response = $this->client->request('PUT', $uri, $options);
            return $response;
        } catch (RequestException $e) {
            // Handle exception or rethrow
            throw $e;
        }
    }

    public function delete($uri, $options = []) {
        try {
            $response = $this->client->request('DELETE', $uri, $options);
            return $response;
        } catch (RequestException $e) {
            // Handle exception or rethrow
            throw $e;
        }
    }

    public function remove($selfHref, $options = []): bool {
        try {
            $response = $this->client->request('DELETE', $selfHref, $options);
            if ($response->getStatusCode() == 204) {
                return true;
            } else {
                throw new \Exception("Unknown Delete response status code {$response->getStatusCode()} expecting 204");
            }
        } catch (RequestException $e) {
            // Handle exception or rethrow
            throw $e;
        }
    }
}
