<?php

namespace Amanank\HalClient;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;

class Client {
    private $client;

    public function __construct($config = [], GuzzleClient $client = null) {
        if (!$client) {
            $config['allow_redirects'] = false;
            $this->client = new GuzzleClient($config);
        } else {
            $this->client = $client;
        }
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

    public function getJson($uri, $options = []) {
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

    public function create($uri, $data = [], $options = []): array {
        $response = $this->post($uri, $data, $options);
        if ($response->getStatusCode() == 201) {
            $body = (string) $response->getBody();
            $payload = null;

            if ($body !== '') {
                $payload = json_decode($body, true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    $payload = null;
                }
            }

            $location = $response->getHeader('Location')[0] ?? null;
            $location ??= $payload['_links']['self']['href'] ?? null;

            if (! $location) {
                throw new \Exception('Post response does not contain a location header or self link'); //TODO: return custom exception with request/response details
            }

            return [
                'location' => $location,
                'data' => is_array($payload) ? $payload : null,
            ];
        } else {
            throw new \Exception('Post response status code is not 201');
        }
    }

    public function put($uri, $data = [], $options = []) {
        try {
            $options['json'] = $data;

            // Helpful debug when upstream rejects payloads.
            Log::debug('HAL PUT request', [
                'uri' => $uri,
                'payload' => $data,
            ]);

            $response = $this->client->request('PUT', $uri, $options);
            return $response;
        } catch (RequestException $e) {
            // Capture request/response context for the exception handler.
            app()->instance('guzzle.debug.context', [
                'guzzle_request' => [
                    'method' => $e->getRequest()?->getMethod(),
                    'uri' => (string) ($e->getRequest()?->getUri()),
                    'headers' => $e->getRequest()?->getHeaders(),
                    'body' => $e->getRequest() ? (string) $e->getRequest()->getBody() : null,
                ],
                'guzzle_response' => [
                    'status' => $e->getResponse()?->getStatusCode(),
                    'headers' => $e->getResponse()?->getHeaders(),
                    'body' => $e->getResponse() ? (string) $e->getResponse()->getBody() : null,
                ],
            ]);

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
