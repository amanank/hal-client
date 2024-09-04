# HAL API Client
A PHP client for interacting with HAL APIs.  

##Installation
You can install the package via Composer:  
```bash 
composer require amanank/hal-client 
```

## Usage

### Basic Usage
```php 
require 'vendor/autoload.php';  
use Amanank\HalClient\HalClient;  
$client = new HalClient('https://api.example.com'); 
$response = $client->get('/resource'); 
$data = $response->getBody(); 
```

## Configuration
You can configure the client with additional options:  
```php
$options = [
    'headers' => [
        'Authorization' => 'Bearer your-token',
        'Accept' => 'application/hal+json',
    ],
];

$client = new HalClient('https://api.example.com', $options); 
```  

### Handling Responses
The client returns responses that you can handle as needed:  
```php 
$response = $client->get('/resource');
if ($response->getStatusCode() === 200) {
    $data = json_decode($response->getBody(), true);
    // Process the data
} else {
    // Handle error
}
```  

## Laravel Integration
### Service Provider
Register the service provider in `config/app.php`:
```php
'providers' => [
    // Other service providers...
    Amanank\HalClient\Providers\HalClientServiceProvider::class,
],
```

### Publish Configuration
Publish the configuration file:
```bash 
php artisan vendor:publish --provider="Amanank\HalClient\Providers\HalClientServiceProvider"
```

### Configuration File
Edit the `config/hal-client.php` file to set the appropriate `base_uri` and `options`.

### Usage in Laravel
```php
use Amanank\HalClient\Client;

$client = app(Client::class);
$response = $client->get('/resource');
$data = $response->getBody();
```


### License
This project is licensed under the MIT License. See the [LICENSE](LICENSE) file for details.  

### Contributing
1. Fork the repository. 
2. Create a new branch (`git checkout -b feature-branch`). 
3. Make your changes. 
4. Commit your changes (`git commit -am 'Add new feature'`). 
5. Push to the branch (`git push origin feature-branch`). 
6. Create a new Pull Request.

### Support
If you have any questions or need support, please open an issue on the GitHub repository. 