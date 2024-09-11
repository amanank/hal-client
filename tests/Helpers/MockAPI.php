<?php

namespace Tests\Helpers;

use Amanank\HalClient\Client;
use GuzzleHttp\Client as GuzzleHttpClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;

class MockAPI {

    const JSON_FILE_PATH = __DIR__ . '/../resources';

    public static function getClient() {
        $responses = static::loadResponses();


        // Create a mock handler with the responses
        // these 404 responses are consumed at each call even though they are not used. So we need to fill the array with enough responses
        // The middleware will handle the request and return the appropriate response
        $mock = new MockHandler(array_fill(0, 100, new Response(404)));

        // Create a handler stack and push the mock handler
        $handlerStack = HandlerStack::create($mock);

        // Add custom middleware to handle requests
        $handlerStack->push(function (callable $handler) use ($responses) {
            return function (RequestInterface $request, array $options) use ($handler, $responses) {
                $response = static::handleRequest($request, $responses);
                return $handler($request, $options)->then(function () use ($response) {
                    return $response;
                });
            };
        });

        // $handlerStack->push(Middleware::mapRequest(fn(Request $request) => static::handleRequest($request, $responses)));

        // Create the Guzzle client with the custom handler stack
        $guzzleClient = new GuzzleHttpClient(['handler' => $handlerStack]);

        return new Client([], $guzzleClient);
    }

    protected static function handleRequest(Request $request, $responses) {
        $requestBody = $request->getBody()->getContents();
        $method = $request->getMethod();
        $path = preg_replace('/^\/api\/v1\//', '', $request->getUri()->getPath());

        if ($method == 'POST' || $method == 'PUT') {
            // if the request body contains "conflict", return a 409 response
            if (strpos($requestBody, 'conflict') !== false && isset($responses[$method][$path][409])) {
                list($code, $headers, $body) = $responses[$method][$path][409];
                return new Response($code, $headers, $body);
            } else if (isset($responses[$method][$path][204])) {
                list($code, $headers, $body) = $responses[$method][$path][204];

                //match parsed body with the request body
                $body = json_decode($body, true);
                $requestBody = json_decode($requestBody, true);

                $differences = static::getArrayDifferences($body, $requestBody);

                if ($differences) {
                    return new Response(409, ['Content-Type' => 'application/hal+json'], json_encode(['message' => 'Bad Request', 'differences' => $differences]));
                }

                return new Response($code);
            }

            return $method == 'POST'
                ? new Response(201, ['Location' => "$path/9"]) // return a 201 response with the location header set to the new resource path
                : new Response(204);
        } else if (isset($responses[$method][$path]) && count($responses[$method][$path]) > 0) {
            list($code, $headers, $body) = static::pickResponse($responses[$method][$path], $method);
            return new Response($code, $headers, $body);
        }

        return new Response(404);
    }

    protected static function getArrayDifferences($array1, $array2) {
        $array1 = $array1 ?? [];
        $array2 = $array2 ?? [];

        $differences = [];

        // Check for differences in $array1
        foreach ($array1 as $key => $value) {
            if (!array_key_exists($key, $array2)) {
                $differences[$key] = ['old' => $value, 'new' => null];
            } elseif ($value != $array2[$key]) {
                $differences[$key] = ['old' => $value, 'new' => $array2[$key]];
            }
        }

        // Check for differences in $array2
        foreach ($array2 as $key => $value) {
            if (!array_key_exists($key, $array1)) {
                $differences[$key] = ['old' => null, 'new' => $value];
            }
        }

        return $differences;
    }

    protected static function pickResponse($responses, $method) {
        if ($method == 'DELETE' && isset($responses[204])) {
            return $responses[204];
        } else if (isset($responses[200])) {
            return $responses[200];
        } else {
            return reset($responses);
        }
    }

    protected static function loadResponses() {
        $jsonFilePath = static::JSON_FILE_PATH;

        $responses = [];

        // Create a recursive directory iterator
        $directoryIterator = new \RecursiveDirectoryIterator($jsonFilePath);
        $iterator = new \RecursiveIteratorIterator($directoryIterator);

        //loop through the files and create $responses array with filename as key and file content as value
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'json') {
                $filename = pathinfo($file, PATHINFO_FILENAME);
                list($method, $name, $code) = array_pad(explode('_', $filename), 3, 200);

                $filePath = ltrim(str_replace($jsonFilePath, '', pathinfo($file, PATHINFO_DIRNAME)), '/');
                $path = $filePath ? $filePath . '/' . $name : $name;

                $responses[strtoupper($method)][$path][$code] = [$code, ['Content-Type' => 'application/hal+json'], file_get_contents($file)];
            }
        }

        return $responses;
    }
}
