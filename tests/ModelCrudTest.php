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

class ModelCrudTest extends TestCase {

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
     * Test Model::find with a valid user ID returns a user object
     */
    public function testFindUserByIdReturnsUser() {
        $user = User::find(1);
        $this->assertNotNull($user);
        $this->assertEquals('John', $user->firstName);
        $this->assertEquals('Doe', $user->lastName);
        $this->assertEquals('john.doe@example.com', $user->email);
        $this->assertEquals('john.doe', $user->username);
        $this->assertEquals('ACTIVE', $user->status->value);
    }

    /**
     * Test Model::find with a non-existent user ID returns null
     */
    public function testFindUserByIdReturnsNullForNonExistentUser() {
        $user = User::find(100);
        $this->assertNull($user);
    }


    /**
     * Test Model::findOrFail with a non-existent user ID throws a ModelNotFoundException
     */
    public function testFindOrFailThrowsExceptionForNonExistentUser() {
        $this->expectException(ModelNotFoundException::class);
        $this->expectExceptionMessage("Model [" . class_basename(User::class) . "] with id [100] not found.");

        User::findOrFail(100);
    }

    public function testGetEnumOnNewUser() {
        $user = new User();
        $this->assertNull($user->status);
    }

    /**
     * Test Model::save creates a new user successfully
     */
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

    /**
     * Test Model::save throws a ConstraintViolationException when a constraint is violated
     */
    public function testCreateUserThrowsConstraintViolationException() {
        $this->expectException(ConstraintViolationException::class);
        $this->expectExceptionMessage("Constraint violation for model [" . class_basename(User::class) . "] with id [].");
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
            $this->assertNotNull($e->getErrors());

            $this->assertEquals('CONFLICT', $e->getErrors()['status']);
            $this->assertEquals('Unique index or primary key violation', $e->getErrors()['message']);
            $this->assertEquals('User', $e->getErrors()['subErrors'][0]['object']);
            $this->assertEquals('email', $e->getErrors()['subErrors'][0]['field']);
            $this->assertEquals('Email must be unique.', $e->getErrors()['subErrors'][0]['message']);

            throw $e;
        }
    }

    /**
     * Test Model::get returns a paginated list of users
     */
    public function testGetUsersReturnsPaginatedList() {
        $usersPage = User::get();

        $this->assertCount(3, $usersPage);
        $this->assertEquals('John', $usersPage[0]->firstName);
        $this->assertEquals('Doe', $usersPage[0]->lastName);
        $this->assertEquals(9, $usersPage->total());
        $this->assertEquals(1, $usersPage->currentPage());
    }

    /**
     * Test Model::save updates an existing user successfully
     */
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

    /**
     * Test Model::save on existing user throws a ConstraintViolationException when a constraint is violated
     */
    public function testUpdateUserThrowsConstraintViolationException() {
        // Assume we have a user with ID 1
        $user = User::find(1);
        $this->assertNotNull($user);
        $this->assertTrue($user->exists);
        $this->assertEquals('users/1', $user->getLink());

        // Try to update user email to an existing email to trigger a constraint violation
        $user->firstName = 'conflict'; // This will trigger a 409 response

        $this->expectException(ConstraintViolationException::class);
        $this->expectExceptionMessage("Constraint violation for model [" . class_basename(User::class) . "] with id [users/1].");
        $this->expectExceptionCode(409);

        try {
            $user->save();
        } catch (ConstraintViolationException $e) {
            $this->assertEquals(User::class, $e->getModel());
            $this->assertEquals("users/1", $e->getId());
            $this->assertNotNull($e->getErrors());

            $this->assertEquals('CONFLICT', $e->getErrors()['status']);
            $this->assertEquals('Unique index or primary key violation', $e->getErrors()['message']);
            $this->assertEquals('User', $e->getErrors()['subErrors'][0]['object']);
            $this->assertEquals('email', $e->getErrors()['subErrors'][0]['field']);
            $this->assertEquals('Email must be unique.', $e->getErrors()['subErrors'][0]['message']);

            throw $e;
        }
    }

    /**
     * Test Model::delete deletes an existing user successfully
     */
    public function testDeleteUserSuccessfully() {
        // Assume we have a user with ID 1
        $user = User::findOrFail(1);

        $this->assertTrue($user->delete());

        $this->assertFalse($user->exists);
    }

    /**
     * Test Model::delete on non-existent user throws a ModelNotFoundException
     */
    public function testDeleteUserThrowsModelNotFoundException() {
        $this->expectException(ModelNotFoundException::class);
        $this->expectExceptionMessage("Model [" . class_basename(User::class) . "] with id [users/100] not found.");

        $user = new User();
        $user->setRawAttributes(['_links' => ['self' => ['href' => 'users/100']]]);
        $user->exists = true;

        $user->delete();
    }

    /**
     * TODO: Test Model::all returns a collection of all users
     */
}
