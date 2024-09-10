<?php

namespace Tests\Console\Commands;

use Amanank\HalClient\Client;
use Orchestra\Testbench\TestCase;
use Amanank\HalClient\Providers\HalClientServiceProvider;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;
use Tests\MockAPI;

class GenerateHalModelsTest extends TestCase {

    const MODEL_PATH = __DIR__ . '/../../../src/Models/Discovered';
    const FILES_TO_TEST = [
        'User.php',
        'Tag.php',
        'Post.php',
        'Comment.php',
        'Enums/UserStatusEnum.php',
        'Enums/PostStatusEnum.php'
    ];

    protected $filesystem;
    protected static $client;

    public static function setUpBeforeClass(): void {
        parent::setUpBeforeClass();
        self::$client = MockAPI::getClient();
    }

    protected function setUp(): void {
        parent::setUp();

        $this->filesystem = new Filesystem();

        // Bind the mock client to the service container
        $this->app->instance(Client::class, self::$client);
    }

    protected function getPackageProviders($app) {
        return [
            HalClientServiceProvider::class
        ];
    }

    public function testGenerateModelsCommand() {
        $this->cleanModelPath();

        foreach (static::FILES_TO_TEST as $file) {
            $this->assertFileDoesNotExist(static::MODEL_PATH . '/' . $file);
        }

        // Run the command
        Artisan::call('hal:generate-models');

        // Capture and display the output
        $output = Artisan::output();
        echo $output;


        foreach (static::FILES_TO_TEST as $file) {
            $this->assertFileExists(static::MODEL_PATH . '/' . $file);
        }
    }

    /**
     * @depends testGenerateModelsCommand
     */
    public function testUsersModel() {
        $userModelPath = static::MODEL_PATH . '/User.php';
        require_once $userModelPath;

        try {
            $user = new \Amanank\HalClient\Models\Discovered\User();
        } catch (\Throwable $e) {
            $this->fail('User model not found');
            throw $e;
        }

        $this->assertInstanceOf(\Amanank\HalClient\Models\Model::class, $user);
        $this->assertEquals(['username', 'email', 'firstName', 'lastName', 'status', 'createdAt', 'updatedAt'], $user->getFillable());
    }

    protected function cleanModelPath() {
        $files = $this->filesystem->allFiles(directory: static::MODEL_PATH, hidden: false); //get all files excluding hiddens
        $this->filesystem->delete($files);
        echo "Deleted all files in " . static::MODEL_PATH . "\n";
    }
}
