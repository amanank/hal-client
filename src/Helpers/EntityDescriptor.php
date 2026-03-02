<?php

namespace Amanank\HalClient\Helpers;

use Illuminate\Support\Str;
use Illuminate\Support\Collection;

class EntityDescriptor {

    protected $enums;

    public function __construct(
        protected string $name,
        protected array $descriptor,
        protected ?string $representationId = null,
        protected bool $includeStaticMethods = true
    ) {
        $this->enums = new Collection();
    }

    public function getClassName(): string {
        $representation = $this->getRepresentationDescriptor();
        if (!$representation || !isset($representation['id'])) {
            return ucfirst(Str::singular($this->name));
        }

        return ucfirst(Str::before($representation['id'], '-representation'));
    }

    public function getDescriptor(): Collection {
        return new Collection($this->descriptor);
    }

    public function getName(): string {
        return $this->name;
    }

    public function parseTemplate(string $template): string {

        $fillables = $this->getTemplateFillables();
        $template = str_replace($fillables->keys()->toArray(), $fillables->values()->toArray(), $template);

        //remove all other placeholders
        $template = preg_replace('/{{.*}}/', '', $template);

        return $template;
    }

    protected function getTemplateFillables(): Collection {
        return new Collection([
            '{{className}}' => $this->getClassName(),
            '{{endpoint}}' => $this->name,
            '{{fillables}}' => $this->getFillables()->map(fn($item) => "'{$item['name']}'")->implode(', '),
            '{{properties}}' => $this->getProperties()->map(fn($item) => $this->parseProperty($item))->implode("\n\t"),
            '{{propertiesDocs}}' => $this->getProperties()->map(fn($item) => $this->parsePropertyDoc($item))->implode("\n * "),
            '{{relations}}' => $this->getRelations()->map(fn($item) => $this->parseRelation($item))->implode("\n\t"),
            '{{staticMethods}}' => $this->getStaticMethods()->map(fn($item) => $this->parseStaticMethod($item))->implode("\n\n\t"),
            '{{enumSetters}}' => $this->enums->map(fn($enum) => $this->getEnumSetter($enum))->implode("\n\n\t"),
            '{{enumGetters}}' => $this->enums->map(fn($enum) => $this->getEnumGetter($enum))->implode("\n\n\t"),
        ]);
    }

    public static function getEnumTemplate($enum, string $template): string {
        $template = str_replace('{{name}}', $enum['name'], $template);
        $template = str_replace('{{values}}', collect($enum['values'])->map(fn($value) => "\tcase {$value} = '{$value}';")->implode("\n\t"), $template);

        return $template;
    }

    public function hasEnums(): bool {
        return $this->enums->isNotEmpty();
    }

    public function getEnums(): Collection {
        return $this->enums;
    }

    protected function getRepresentationDescriptor(): ?array {
        $descriptors = $this->getDescriptor();

        if ($this->representationId) {
            $matched = $descriptors->first(fn($item) => isset($item['id']) && $item['id'] === $this->representationId);
            if ($matched) {
                return $matched;
            }
        }

        return $descriptors->first(fn($item) => isset($item['id']) && Str::endsWith($item['id'], '-representation'));
    }

    protected function getRepresentation(): Collection {
        $representation = $this->getRepresentationDescriptor();
        if (!$representation || !isset($representation['descriptor']) || !is_array($representation['descriptor'])) {
            return collect();
        }

        return collect($representation['descriptor']);
    }

    protected function getFillables(): Collection {
        return $this->getRepresentation()
            ->filter(fn($item) => $item['type'] === 'SEMANTIC' || $item['type'] === 'SAFE')
            ->map(fn($item) => $this->setReturnType($item));
    }

    protected function getProperties(): Collection {
        return $this->getRepresentation()
            ->filter(fn($item) => $item['type'] === 'SEMANTIC')
            ->map(fn($item) => $this->setReturnType($item));
    }

    protected function setReturnType($item) {
        if (isset($item['doc']) && $item['doc']['format'] === 'TEXT') {
            $name = $this->getClassName() . ucfirst($item['name'])  . 'Enum';
            $this->enums->put($name, [
                'name' => $name,
                'propertyName' => $item['name'],
                'propertyVar' => $this->normalizeName($item['name']),
                'propertyMethod' => Str::studly($this->normalizeName($item['name'])),
                'values' => explode(', ', $item['doc']['value'])
            ]);
            $item['returnType'] = "Enums\\{$name} ";
        } else {
            $item['returnType'] = '';
        }
        return $item;
    }

    protected function getRelations(): Collection {
        return $this->getRepresentation()
            ->filter(fn($item) => $item['type'] === 'SAFE' && isset($item['rt']))
            ->map(fn($item) => $this->toRelationDetail($item));
    }

    protected function toRelationDetail($attr) {
        return [
            'name' => $attr['name'],
            'methodName' => $this->normalizeName($attr['name']),
            'returnType' => ucfirst(Str::before(Str::after($attr['rt'], '#'), '-representation')),
            'relationMethod' => Str::plural($attr['name']) === $attr['name'] ? 'hasMany' : 'hasOne',
        ];
    }

    protected function getStaticMethods(): Collection {
        if (!$this->includeStaticMethods) {
            return collect();
        }

        return $this->getDescriptor()
            ->filter(fn($item) => isset($item['name']) && !isset($item['id']))
            ->map(fn($item) => $this->toMethodDetail($item));
    }

    protected function toMethodDetail($item) {
        return [
            'functionName' => $this->normalizeName($item['name']),
            'apiName' => $item['name'],
            'params' => collect($item['descriptor'])->map(fn($attr) => [
                'name' => $attr['name'],
                'var' => $this->normalizeName($attr['name']),
            ]),
            'returnType' => $this->detectReturnType($item)
        ];
    }

    protected function detectReturnType($item) {
        if (Str::startsWith($item['name'], 'get')) {
            return ': ' . $this->getClassName();
        } else if (Str::startsWith($item['name'], 'search') || Str::startsWith($item['name'], 'find')) {
            return ': \Illuminate\Support\Collection';
        } else if (Str::startsWith($item['name'], 'count')) {
            return ': int';
        } else if (Str::startsWith($item['name'], 'has') || Str::startsWith($item['name'], 'is')) {
            return ': bool';
        }
        return '';
    }

    protected function parseProperty($item): string {
        $name = $this->normalizeName($item['name']);
        return "protected {$item['returnType']}\${$name};";
    }

    protected function parsePropertyDoc($item): string {
        $name = $this->normalizeName($item['name']);
        return "@property {$item['returnType']}\${$name}";
    }

    protected function parseRelation($item): string {
        return "public function {$item['methodName']}() {\n\t\treturn \$this->{$item['relationMethod']}({$item['returnType']}::class,'{$item['name']}');\n\t}";
    }

    protected function parseStaticMethod($item): string {
        $functionParams = collect($item['params'])->map(fn($attr) => "\${$attr['var']}")->implode(', ');
        $searchParams = collect($item['params'])->map(fn($attr) => "'{$attr['name']}' => \${$attr['var']}")->implode(', ');

        return "public static function {$item['functionName']}($functionParams){$item['returnType']} {\n\t\treturn static::halSearch('{$item['apiName']}', [{$searchParams}]);\n\t}";
    }

    protected function getEnumSetter($enum): string {
        return "protected function set{$enum['propertyMethod']}Attribute(Enums\\{$enum['name']} \${$enum['propertyVar']}) {\n\t\t\$this->attributes['{$enum['propertyName']}'] = \${$enum['propertyVar']}->value;\n\t}";
    }

    protected function getEnumGetter($enum): string {
        return "protected function get{$enum['propertyMethod']}Attribute(\${$enum['propertyVar']}) {\n\t\treturn \${$enum['propertyVar']} ? Enums\\{$enum['name']}::from(\${$enum['propertyVar']}) : null;\n\t}";
    }

    protected function normalizeName(string $name): string {
        $normalized = Str::camel(str_replace(['-', '.', ' '], ' ', $name));
        if ($normalized === '') {
            return $name;
        }
        if (is_numeric($normalized[0])) {
            return '_' . $normalized;
        }
        return $normalized;
    }
}
