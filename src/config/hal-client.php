<?php

// config/hal-client.php
return [
    'base_uri' => env('HAL_API_BASE_URI', 'https://example.com/api/v1/'),
    'headers' => [
        'Authorization' => 'Bearer ' . env('HAL_API_TOKEN'),
        'Accept' => 'application/hal+json',
    ],
    'verify' => true // Disable SSL verification for testing
];

