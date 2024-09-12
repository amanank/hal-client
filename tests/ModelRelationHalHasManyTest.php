<?php

namespace Tests;

use Amanank\HalClient\Client;
use Amanank\HalClient\Exceptions\ConstraintViolationException;
use Amanank\HalClient\Models\Discovered\Comment;
use Amanank\HalClient\Models\Discovered\Post;
use Orchestra\Testbench\TestCase;
use Amanank\HalClient\Providers\HalClientServiceProvider;
use Illuminate\Support\Collection;
use Tests\Helpers\MockAPI;

class ModelRelationHalHasManyTest extends TestCase {

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
     * Test HalHasMany::get returns the related models
     */
    public function testHalHasManyGetReturnsRelatedModels() {
        $post = Post::findOrFail(2);
        $comments = $post->comments;

        $this->assertNotNull($comments);
        $this->assertCount(2, $comments);
        $this->assertInstanceOf(Comment::class, $comments[0]);
        $this->assertInstanceOf(Comment::class, $comments[1]);
        $this->assertEquals('comments/1', $comments[0]->getLink());
        $this->assertEquals('comments/2', $comments[1]->getLink());
    }

    /**
     * Test HalHasMany::get returns an empty array when the link is not found
     */
    public function testHalHasManyGetReturnsEmptyArrayWhenLinkIsNotFound() {
        $post = Post::findOrFail(1);

        $this->assertInstanceOf(Collection::class, $post->comments);
        $this->assertCount(0, $post->comments);
    }

    /**
     * Test HalHasMany::associate sets the related models
     */
    public function testHalHasManyAssociateSetsRelatedModels() {
        $post = Post::findOrFail(3);

        $this->assertCount(5, $post->comments);

        $comment = Comment::findOrFail(3);
        $this->assertNull($comment->post);


        $post->comments()->associate($comment);
        $this->assertCount(6, $post->comments);
        $this->assertEquals($comment, $post->comments[5]);

        $this->assertTrue($post->isDirty());
        $this->assertTrue($post->isDirty('comments'));

        try {
            $this->assertTrue($post->save());
        } catch (ConstraintViolationException $e) {
            print_r($e->getErrors());
            throw $e;
        }
    }
}
