<?php

namespace Amanank\HalClient\Console\Commands;

use Illuminate\Console\Command;
use Amanank\HalClient\Client;
use Amanank\HalClient\Helpers\EntityDescriptor;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;

class GenerateHalModels extends Command {
    protected $signature = 'hal:generate-models';
    protected $description = 'Generate PHP models from HAL API';

    protected $client;
    protected $filesystem;

    const TEMPLATE_PATH = __DIR__ . '/../../resources/templates/model_template.php';
    const MODEL_PATH = __DIR__ . '/../../Models/Discovered/';

    public function __construct(Client $client, Filesystem $filesystem) {
        parent::__construct();
        $this->client = $client;
        $this->filesystem = $filesystem;
    }

    public function handle() {
        $this->getProfileLinks()
            ->filter(fn($link, $name) => $name !== 'self')
            ->map(fn($link, $name) => $this->fetchEntityDescriptor($name, $link['href']))
            ->each(fn(EntityDescriptor $descriptor) => $this->createModelFile(
                $this->getModelFilePath($descriptor->getClassName()),
                $this->toModelTemplate($descriptor)
            ))
            ->filter(fn(EntityDescriptor $descriptor) => $descriptor->hasEnums())
            ->flatMap(fn(EntityDescriptor $descriptor) => $descriptor->getEnums())
            ->each(fn($enum) => $this->createEnumFile(
                $this->getEnumFilePath($enum['name']),
                $this->toEnumTemplate($enum)
            ));
    }

    protected function getProfileLinks(): Collection {
        $response = $this->client->get('profile');
        $data = json_decode($response->getBody(), true);
        return new Collection($data['_links']);
    }

    protected function fetchEntityDescriptor(string $name, string $href): EntityDescriptor {
        $response = $this->client->get($href);
        return new EntityDescriptor($name, json_decode($response->getBody(), true)["alps"]["descriptor"]);
    }

    protected function toModelTemplate(EntityDescriptor $descriptor): string {
        $template = file_get_contents(self::TEMPLATE_PATH);
        return $descriptor->parseTemplate($template);
    }

    protected function getModelFilePath($className) {
        if (!file_exists(static::MODEL_PATH)) {
            mkdir(static::MODEL_PATH, 0755, true);
        }
        return static::MODEL_PATH . "{$className}.php";
    }

    protected function createModelFile($filePath, $modelTemplate) {
        $this->filesystem->put($filePath, $modelTemplate);
        $this->info('Model created: ' . $filePath);
    }

    protected function toEnumTemplate($enum): string {
        $template = file_get_contents(__DIR__ . '/../../resources/templates/enum_template.php');
        return EntityDescriptor::getEnumTemplate($enum, $template);
    }

    protected function getEnumFilePath($name) {
        if (!file_exists(static::MODEL_PATH . 'Enums/')) {
            mkdir(static::MODEL_PATH . 'Enums/', 0755, true);
        }
        return static::MODEL_PATH . "Enums/{$name}.php";
    }

    protected function createEnumFile($filePath, $enumTemplate) {
        $this->filesystem->put($filePath, $enumTemplate);
        $this->info('Enum created: ' . $filePath);
    }
}
