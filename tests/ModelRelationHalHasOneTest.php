<?php

namespace Tests;

use Amanank\HalClient\Client;
use Amanank\HalClient\Exceptions\ConstraintViolationException;
use Amanank\HalClient\Models\Discovered\Post;
use Amanank\HalClient\Models\Discovered\User;
use Orchestra\Testbench\TestCase;
use Amanank\HalClient\Providers\HalClientServiceProvider;
use Tests\Helpers\MockAPI;
use Tests\Models\Post as ModelsPost;

class ModelRelationHalHasOneTest extends TestCase {

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
     * Test HalHasOne::get returns the related model
     */
    public function testHalHasOneGetReturnsRelatedModel() {
        $post = Post::findOrFail(1);
        $author = $post->author;


        $this->assertNotNull($author);
        $this->assertEquals('john.doe', $author->username);
        $this->assertEquals('users/1', $author->getLink());
    }

    /**
     * Test HalHasOne::get returns null when the link is not found
     */
    public function testHalHasOneGetReturnsNullWhenLinkIsNotFound() {
        $post = Post::findOrFail(2);
        $this->assertNull($post->author);
    }

    /**
     * Test HalHasOne::associate sets the related model
     */
    public function testHalHasOneAssociateSetsRelatedModel() {
        $post = Post::findOrFail(2);
        $author = User::findOrFail(1);

        $this->assertNull($post->author);

        $post->author()->associate($author);

        $this->assertEquals($author, $post->author);

        try {
            $this->assertTrue($post->save());
        } catch (ConstraintViolationException $e) {
            print_r($e->getErrors());
            throw $e;
        }
    }

    /**
     * Test HalHasOne::dissociate removes the related model. Assuming relation is nullable
     */
    public function testHalHasOneDissociateRemovesRelatedModel() {
        $post = Post::findOrFail(1);

        $this->assertNotNull($post->author);

        $post->author()->dissociate();

        $this->assertNull($post->author);

        try {
            $this->assertTrue($post->save());
        } catch (ConstraintViolationException $e) {
            print_r($e->getErrors());
            throw $e;
        }
    }

    /**
     * Test HalHasOne::dissociate throws an exception when the relation is not nullable
     */
    public function testHalHasOneDissociateThrowsExceptionWhenRelationIsNotNullable() {
        $this->expectException(ConstraintViolationException::class);
        $this->expectExceptionMessage('Constraint violation for model [' . class_basename(Post::class) . '] with id [posts/2].');

        $post = Post::findOrFail(2);
        $post->author()->dissociate();

        $post->save();
    }

    public function testExtendedModelHasOne() {
        $post = ModelsPost::findOrFail(1);

        $this->assertEquals('Tests\Models\Post', get_class($post));

        $author = $post->author;

        $this->assertNotNull($author);
        //assert namesapce of author is Tests\Models\User
        $this->assertEquals('Tests\Models\User', get_class($author));
    }

    /**
     * TODO: Test HalHasOne::save is successful when updating model without changing the relation (and without getting the related model)
     */
}
