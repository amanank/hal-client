<?php

namespace Tests;

use Amanank\HalClient\Client;
use Amanank\HalClient\Exceptions\ConstraintViolationException;
use Amanank\HalClient\Exceptions\ModelNotFoundException;
use Amanank\HalClient\Models\Discovered\Enums\UserStatusEnum;
use Amanank\HalClient\Models\Discovered\User;
use Orchestra\Testbench\TestCase;
use Amanank\HalClient\Providers\HalClientServiceProvider;
use Tests\Helpers\MockAPI;

class ModelSearchFunctionTest extends TestCase {

    protected static $client;

    public static function setUpBeforeClass(): void {
        parent::setUpBeforeClass();
        self::$client = MockAPI::getClient();
    }


    protected function setUp(): void {
        parent::setUp();
        // Bind the mock client to the service container
        $this->app->instance(Client::class, self::$client);
    }

    protected function getPackageProviders($app) {
        return [
            HalClientServiceProvider::class
        ];
    }

    /**
     * Test User::findByLastName with a valid last name returns collection of users
     */
    public function testFindByLastNameReturnsCollectionOfUsers() {
        $users = User::findByLastName('Doe');
        $this->assertNotNull($users);
        $this->assertCount(3, $users);
        $this->assertEquals('John', $users[0]->firstName);
        $this->assertEquals('Doe', $users[0]->lastName);

        $this->assertEquals('Jane', $users[1]->firstName);
        $this->assertEquals('Doe', $users[1]->lastName);

        $this->assertEquals('Richard', $users[2]->firstName);
        $this->assertEquals('Doe', $users[2]->lastName);
    }
}
