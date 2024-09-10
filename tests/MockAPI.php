<?php

namespace Tests;

use Amanank\HalClient\Client;
use GuzzleHttp\Client as GuzzleHttpClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;

class MockAPI {


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
        $method = $request->getMethod();
        $path = preg_replace('/^\/api\/v1\//', '', $request->getUri()->getPath());

        if ($method == 'POST') {
            // if the request body contains "conflict", return a 409 response
            if (strpos($request->getBody()->getContents(), 'conflict') !== false && isset($responses[$method][$path][409])) {
                return $responses[$method][$path][409];
            }

            // return a 201 response with the location header set to the new resource path
            return new Response(201, ['Location' => "$path/9"]);
        } else if (isset($responses[$method][$path][200])) {
            return $responses[$method][$path][200];
        }

        return new Response(404);
    }

    protected static function loadResponses() {
        $jsonFilePath = __DIR__ . '/resources';

        echo "Loading responses from: $jsonFilePath\n";

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

                echo "Loading response: $method $path\n";

                $responses[strtoupper($method)][$path][$code] = new Response($code, ['Content-Type' => 'application/hal+json'], file_get_contents($file));
            }
        }

        return $responses;
    }
}
