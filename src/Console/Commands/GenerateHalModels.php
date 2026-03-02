<?php

namespace Amanank\HalClient\Console\Commands;

use Illuminate\Console\Command;
use Amanank\HalClient\Client;
use Amanank\HalClient\Helpers\EntityDescriptor;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class GenerateHalModels extends Command {
    protected $signature = 'hal:generate-models';
    protected $description = 'Generate PHP models from HAL API';

    // Don't require full application bootstrap - we'll resolve dependencies manually
    protected $hidden = false;

    protected $client;
    protected $filesystem;

    const TEMPLATE_PATH = __DIR__ . '/../../resources/templates/model_template.php';
    const MODEL_PATH = __DIR__ . '/../../Models/Discovered/';

    public function __construct() {
        parent::__construct();
    }

    public function handle() {
        // Resolve dependencies lazily when command runs, not during registration
        try {
            $this->client = $this->laravel->make(Client::class);
            $this->filesystem = $this->laravel->make(Filesystem::class);
            $generatedClasses = collect();
            $generatedEnums = collect();

            $this->getProfileLinks()
                ->filter(fn($link, $name) => $name !== 'self')
                ->map(fn($link, $name) => $this->fetchEntityDescriptor($name, $link['href']))
                ->flatMap(fn(EntityDescriptor $descriptor) => $this->expandEntityDescriptors($descriptor))
                ->each(function (EntityDescriptor $descriptor) use ($generatedClasses, $generatedEnums) {
                    $className = $descriptor->getClassName();
                    if ($generatedClasses->contains($className)) {
                        return;
                    }

                    $this->createModelFile(
                        $this->getModelFilePath($className),
                        $this->toModelTemplate($descriptor)
                    );
                    $generatedClasses->push($className);

                    if ($descriptor->hasEnums()) {
                        foreach ($descriptor->getEnums() as $enum) {
                            $generatedEnums->put($enum['name'], $enum);
                        }
                    }
                });

            $generatedEnums
                ->each(fn($enum) => $this->createEnumFile(
                    $this->getEnumFilePath($enum['name']),
                    $this->toEnumTemplate($enum)
                ));

            // Refresh composer autoloader to ensure generated models are discoverable
            $this->refreshComposerAutoloader();
        } catch (\Error $e) {
            // If bootstrap fails due to missing discovered models, provide helpful guidance
            if (str_contains($e->getMessage(), 'not found') && str_contains($e->getMessage(), 'Discovered')) {
                $this->error('Cannot generate models because the application cannot boot.');
                $this->info('This may happen when versioned code references discovered models that haven\'t been generated yet.');
                $this->info('To resolve this:');
                $this->line('1. Check that your HAL API is running and accessible');
                $this->line('2. Check the HAL_CLIENT_BASE_URI in your .env file');
                $this->line('3. Run this command again: php artisan hal:generate-models');
                throw $e;
            }
            throw $e;
        }
    }

    protected function refreshComposerAutoloader() {
        $composerPath = base_path('composer.json');
        if (!file_exists($composerPath)) {
            $this->warn('composer.json not found at project root');
            return;
        }

        $this->info('Refreshing Composer autoloader...');
        $process = new Process(['composer', 'dump-autoload'], base_path());

        $process->run();

        if ($process->isSuccessful()) {
            $this->info('Composer autoloader refreshed successfully');
        } else {
            $this->error('Failed to refresh Composer autoloader: ' . $process->getErrorOutput());
        }
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

    protected function expandEntityDescriptors(EntityDescriptor $descriptor): Collection {
        $descriptors = $descriptor->getDescriptor();
        $primaryRepresentationId = $this->getPrimaryRepresentationId($descriptors);
        if (!$primaryRepresentationId) {
            return collect([$descriptor]);
        }

        $expanded = collect([
            new EntityDescriptor($descriptor->getName(), $descriptors->all(), $primaryRepresentationId, true),
        ]);

        return $expanded->merge(
            $this->getEmbeddedRepresentationIds($descriptors, $primaryRepresentationId)
                ->map(function (string $representationId) use ($descriptors) {
                    $embeddedName = Str::camel($this->getRepresentationName($descriptors, $representationId));
                    return new EntityDescriptor($embeddedName, $descriptors->all(), $representationId, false);
                })
        );
    }

    protected function getPrimaryRepresentationId(Collection $descriptors): ?string {
        $operationRt = $descriptors
            ->filter(fn($item) => isset($item['id']) && isset($item['rt']) && Str::startsWith($item['rt'], '#') && Str::endsWith($item['rt'], '-representation'))
            ->map(fn($item) => Str::after($item['rt'], '#'))
            ->first();

        if ($operationRt) {
            return $operationRt;
        }

        return $descriptors
            ->filter(fn($item) => isset($item['id']) && Str::endsWith($item['id'], '-representation'))
            ->map(fn($item) => $item['id'])
            ->first();
    }

    protected function getEmbeddedRepresentationIds(Collection $descriptors, string $primaryRepresentationId): Collection {
        $primaryRepresentation = $descriptors->first(fn($item) => isset($item['id']) && $item['id'] === $primaryRepresentationId);
        if (!$primaryRepresentation || !isset($primaryRepresentation['descriptor']) || !is_array($primaryRepresentation['descriptor'])) {
            return collect();
        }

        return collect($primaryRepresentation['descriptor'])
            ->filter(fn($item) => isset($item['type']) && $item['type'] === 'SAFE' && isset($item['rt']) && Str::startsWith($item['rt'], '#') && Str::endsWith($item['rt'], '-representation'))
            ->map(fn($item) => Str::after($item['rt'], '#'))
            ->reject(fn($representationId) => $representationId === $primaryRepresentationId)
            ->unique()
            ->values();
    }

    protected function getRepresentationName(Collection $descriptors, string $representationId): string {
        $representation = $descriptors->first(fn($item) => isset($item['id']) && $item['id'] === $representationId);

        if ($representation && isset($representation['name']) && $representation['name'] !== '') {
            return $representation['name'];
        }

        return Str::before($representationId, '-representation');
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
