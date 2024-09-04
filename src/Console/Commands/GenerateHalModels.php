<?php

namespace Amanank\HalClient\Console\Commands;

use Illuminate\Console\Command;
use Amanank\HalClient\Client;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use \Illuminate\Support\Str;

class GenerateHalModels extends Command
{
    protected $signature = 'hal:generate-models';
    protected $description = 'Generate PHP models from HAL API';

    protected $client;
    protected $filesystem;

    const TEMPLATE_PATH = __DIR__ . '/../../resources/templates/model_template.php';

    public function __construct(Client $client, Filesystem $filesystem)
    {
        parent::__construct();
        $this->client = $client;
        $this->filesystem = $filesystem;
    }

    public function handle()
    {
        $response = $this->client->get('profile', [
            'headers' => [
                'accept' => 'application/hal+json'
            ]
        ]);

        $data = json_decode($response->getBody(), true);
        $links = $data['_links'];

        foreach ($links as $name => $link) {
            if ($name !== 'self') {
                $this->generateModel($name, $link['href']);
            }
        }

        $this->info('Models generated successfully.');
    }

    protected function generateModel($name, $url)
    {
        $response = $this->client->get($url, [
            'headers' => [
                'accept' => 'application/hal+json'
            ]
        ]);

        $modelDetails = json_decode($response->getBody(), true);

        $descriptors = collect($modelDetails['alps']['descriptor']);
        $objectRepresentation = $descriptors->first(fn($item) => Str::endsWith($item['id'], '-representation'));

        $endpoint = basename($objectRepresentation['href']);
        $className = ucfirst(Str::before($objectRepresentation['id'], '-representation'));

        $attributes = $this->parseAttributes($objectRepresentation);

        $relations = $this->parseRelations($objectRepresentation);

        $staticMethods = $this->generateStaticMethods($descriptors->filter(fn($item) => !isset($item['id'])));

        $modelTemplate = $this->getModelTemplate($className, $endpoint, $attributes, $relations, $staticMethods);

        $path = app_path("Models/{$className}.php");
        $this->filesystem->put($path, $modelTemplate);
        $this->info('Model created: ' . $path);
    }

    protected function generateStaticMethods($descriptors)
    {
        return $descriptors->map(function ($descriptor) {
            $functionName = $descriptor['name'];
            $functionParams = collect($descriptor['descriptor'])->map(function ($attr) {
                return "\${$attr['name']}";
            })->implode(', ');
            $searchParams = collect($descriptor['descriptor'])->map(function ($attr) {
                return "'{$attr['name']}' => \${$attr['name']}";
            })->implode(', ');
            return "public static function {$functionName}($functionParams) {\n\t\treturn static::search('$functionName', [$searchParams]);\n\t}";
        })->implode("\n\n\t");
    }

    protected function parseAttributes($descriptor)
    {
        return collect($descriptor['descriptor'])
        ->filter(fn($attr) => !isset($attr['rt']))
        ->map(function ($attr) {
            return "protected \${$attr['name']};";
        })->implode("\n\t");
    }

    protected function parseRelations($descriptor)
    {
        return collect($descriptor['descriptor'])
        ->filter(fn($attr) => isset($attr['rt']))
        ->map(function ($attr) {
            return "public function {$attr['name']}() {\n\t\treturn \$this->getRelation('{$attr['name']}');\n\t}";
        })->implode("\n\t");
    }

    protected function getModelTemplate($className, $url, $attributes, $relations, $staticMethods)
    {
        $template = file_get_contents(self::TEMPLATE_PATH);
        $template = str_replace('{{className}}', $className, $template);
        $template = str_replace('{{url}}', $url, $template);
        $template = str_replace('{{attributes}}', $attributes, $template);
        $template = str_replace('{{relations}}', $relations, $template);
        $template = str_replace('{{staticMethods}}', $staticMethods, $template);
        return $template;
    }
}