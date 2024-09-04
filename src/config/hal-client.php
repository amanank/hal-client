<?php

// config/hal-client.php
return [
    'base_uri' => 'https://api.example.com/v1',
    'headers' => [
        'Authorization' => 'Bearer your-token',
        'Accept' => 'application/hal+json',
    ],
    'verify' => true // Disable SSL verification for testing
];

