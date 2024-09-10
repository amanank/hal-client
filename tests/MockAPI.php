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
        $mock = new MockHandler(array_values($responses)[0]);

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

        if (isset($responses[$method][$path])) {
            return $responses[$method][$path];
        }

        print_r([$method, $path]);
        die();

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

                $responses[strtoupper($method)][$path] = new Response($code, ['Content-Type' => 'application/hal+json'], file_get_contents($file));
            }
        }

        return $responses;
    }
}
