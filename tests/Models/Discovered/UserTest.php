<?php

namespace Tests\Models\Discovered;

use Amanank\HalClient\Client;
use Amanank\HalClient\Exceptions\ConstraintViolationException;
use Amanank\HalClient\Exceptions\ModelNotFoundException;
use Amanank\HalClient\Models\Discovered\Enums\UserStatusEnum;
use Amanank\HalClient\Models\Discovered\User;
use Orchestra\Testbench\TestCase;
use Amanank\HalClient\Providers\HalClientServiceProvider;
use Tests\MockAPI;

class UserTest extends TestCase {
    const MODEL_PATH = __DIR__ . '/../../../src/Models/Discovered';

    protected static $client;

    public static function setUpBeforeClass(): void {
        parent::setUpBeforeClass();
        self::$client = MockAPI::getClient();
        include_once self::MODEL_PATH . '/User.php';
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

    public function testFindUserByIdReturnsUser() {
        $user = User::find(1);
        $this->assertNotNull($user);
        $this->assertEquals('John', $user->firstName);
        $this->assertEquals('Doe', $user->lastName);
        $this->assertEquals('john.doe@example.com', $user->email);
        $this->assertEquals('john.doe', $user->username);
        $this->assertEquals('ACTIVE', $user->status->value);
    }

    public function testFindUserByIdReturnsNullForNonExistentUser() {
        $user = User::find(100);
        $this->assertNull($user);
    }

    public function testFindOrFailThrowsExceptionForNonExistentUser() {
        $this->expectException(ModelNotFoundException::class);
        $this->expectExceptionMessage("No results for GET model [" . User::class . "] with id [100].");

        User::findOrFail(100);
    }

    public function testCreateUserSuccessfully() {
        $user = new User();
        $user->username = 'unit.test';
        $user->email = 'unit.test@phpunit';
        $user->firstName = 'Unit';
        $user->lastName = 'Test';
        $user->status = UserStatusEnum::ACTIVE;

        $user->save();

        $this->assertTrue($user->exists);
        $this->assertEquals('users/9', $user->getLink());
    }

    public function testCreateUserThrowsConstraintViolationException() {
        $this->expectException(ConstraintViolationException::class);
        $this->expectExceptionMessage("Constraint violation for model [" . User::class . "] with id [].");
        $this->expectExceptionCode(409);

        try {
            $user = new User();
            $user->username = 'unit.test';
            $user->email = 'unit.test@phpunit';
            $user->firstName = 'conflict'; // This will trigger a 409 response
            $user->lastName = 'Test';
            $user->status = UserStatusEnum::ACTIVE;

            $user->save();
        } catch (ConstraintViolationException $e) {
            $this->assertEquals(User::class, $e->getModel());
            $this->assertNull($e->getId());
            $this->assertNotNull($e->getResponse());

            $this->assertEquals('CONFLICT', $e->getResponse()['status']);
            $this->assertEquals('Unique index or primary key violation', $e->getResponse()['message']);
            $this->assertEquals('User', $e->getResponse()['subErrors'][0]['object']);
            $this->assertEquals('email', $e->getResponse()['subErrors'][0]['field']);
            $this->assertEquals('Email must be unique.', $e->getResponse()['subErrors'][0]['message']);

            throw $e;
        }
    }

    public function testGetUsersReturnsPaginatedList() {
        $usersPage = User::get();

        $this->assertCount(3, $usersPage);
        $this->assertEquals('John', $usersPage[0]->firstName);
        $this->assertEquals('Doe', $usersPage[0]->lastName);
        $this->assertEquals(9, $usersPage->total());
        $this->assertEquals(1, $usersPage->currentPage());
    }

    public function testUpdateUserSuccessfully() {
        // Assume we have a user with ID 1
        $user = User::find(1);
        $this->assertNotNull($user);
        $this->assertTrue($user->exists);
        $this->assertEquals('users/1', $user->getLink());

        // Update user details
        $user->firstName = 'UpdatedFirstName';
        $user->lastName = 'UpdatedLastName';
        $user->email = 'updated.email@example.com';

        $this->assertTrue($user->isDirty());

        $this->assertTrue($user->save());

        $this->assertFalse($user->isDirty());
    }

    public function testUpdateUserThrowsConstraintViolationException() {
        // Assume we have a user with ID 1
        $user = User::find(1);
        $this->assertNotNull($user);
        $this->assertTrue($user->exists);
        $this->assertEquals('users/1', $user->getLink());

        // Try to update user email to an existing email to trigger a constraint violation
        $user->firstName = 'conflict'; // This will trigger a 409 response

        $this->expectException(ConstraintViolationException::class);
        $this->expectExceptionMessage("Constraint violation for model [" . User::class . "] with id [users/1].");
        $this->expectExceptionCode(409);

        try {
            $user->save();
        } catch (ConstraintViolationException $e) {
            $this->assertEquals(User::class, $e->getModel());
            $this->assertEquals("users/1", $e->getId());
            $this->assertNotNull($e->getResponse());

            $this->assertEquals('CONFLICT', $e->getResponse()['status']);
            $this->assertEquals('Unique index or primary key violation', $e->getResponse()['message']);
            $this->assertEquals('User', $e->getResponse()['subErrors'][0]['object']);
            $this->assertEquals('email', $e->getResponse()['subErrors'][0]['field']);
            $this->assertEquals('Email must be unique.', $e->getResponse()['subErrors'][0]['message']);

            throw $e;
        }
    }
}
