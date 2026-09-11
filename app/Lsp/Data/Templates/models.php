<?php

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use phpDocumentor\Reflection\DocBlockFactory;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

if (class_exists('\phpDocumentor\Reflection\DocBlockFactory')) {
    $factory = DocBlockFactory::createInstance();
} else {
    $factory = null;
}

$docblocks = new class($factory)
{
    public function __construct(protected $factory) {}

    public function forMethod($method)
    {
        if ($this->factory !== null) {
            $docblock = $this->factory->create($method->getDocComment());
            $params = collect($docblock->getTagsByName('param'))->map(fn ($p) => (string) $p)->all();
            $return = (string) $docblock->getTagsByName('return')[0] ?? null;

            return [$params, $return];
        }

        $params = collect($method->getParameters())
            ->map(function (ReflectionParameter $param) {
                $types = match ($param?->getType()) {
                    null    => [],
                    default => method_exists($param->getType(), 'getTypes')
                        ? $param->getType()->getTypes()
                        : [$param->getType()]
                };

                $types = collect($types)
                    ->filter()
                    ->values()
                    ->map(fn ($t) => $t->getName());

                return trim($types->join('|') . ' $' . $param->getName());
            })
            ->all();

        $return = $method->getReturnType()?->getName();

        return [$params, $return];
    }
};

$models = new class($factory)
{
    protected $output;

    public function __construct(protected $factory)
    {
        $this->output = new BufferedOutput;
    }

    /**
     * Get the files that may declare the application's models.
     *
     * Models are not required to live in app/Models, so each namespace the
     * application autoloads is searched for a Models directory. Only the
     * production autoload roots are used, since the dev roots cover tests
     * that are not safe to include. Directories are matched first so that
     * files outside a Models directory are never enumerated.
     */
    protected function modelFiles()
    {
        $composer = base_path('composer.json');
        $roots = collect();

        if (is_file($composer)) {
            $config = json_decode((string) file_get_contents($composer), true);

            $roots = collect($config['autoload']['psr-4'] ?? [])
                ->flatten()
                ->map(fn ($path) => base_path(trim((string) $path, '/')));
        }

        return $roots
            ->push(base_path('app'))
            ->map(fn ($path) => realpath($path) ?: null)
            ->filter(fn ($path) => $path !== null && File::isDirectory($path))
            ->unique()
            ->flatMap(fn ($root) => iterator_to_array(
                Finder::create()->directories()->name('Models')->in($root)->ignoreUnreadableDirs(),
                false
            ))
            ->map(fn (SplFileInfo $directory) => $directory->getPathname())
            ->unique()
            ->flatMap(fn ($directory) => iterator_to_array(
                Finder::create()->files()->name('*.php')->in($directory)->ignoreUnreadableDirs(),
                false
            ))
            ->map(fn (SplFileInfo $file) => $file->getPathname())
            ->unique()
            ->values();
    }

    public function all()
    {
        $this->modelFiles()->each(function ($file) {
            try {
                include_once $file;
            } catch (Throwable $e) {
                // A file that refuses to load should not hide every other model.
            }
        });

        return collect(get_declared_classes())
            ->filter(fn ($class) => is_subclass_of($class, Model::class))
            ->filter(fn ($class) => !in_array($class, [Pivot::class, User::class]))
            ->values()
            ->flatMap(fn (string $className) => $this->getInfo($className))
            ->filter();
    }

    protected function getCastReturnType($className)
    {
        if ($className === null) {
            return null;
        }

        try {
            $method = (new ReflectionClass($className))->getMethod('get');

            if ($method->hasReturnType()) {
                return $method->getReturnType()->getName();
            }

            return $className;
        } catch (Exception|Throwable $e) {
            return $className;
        }
    }

    protected function fromArtisan($className)
    {
        try {
            Artisan::call(
                'model:show',
                [
                    'model'  => $className,
                    '--json' => true,
                ],
                $this->output
            );
        } catch (Exception|Throwable $e) {
            return null;
        }

        return json_decode($this->output->fetch(), true);
    }

    protected function collectExistingProperties($reflection)
    {
        if ($this->factory === null) {
            return collect();
        }

        if ($comment = $reflection->getDocComment()) {
            $docblock = $this->factory->create($comment);

            $existingProperties = collect($docblock->getTagsByName('property'))->map(fn ($p) => $p->getVariableName());
            $existingReadProperties = collect($docblock->getTagsByName('property-read'))->map(fn ($p) => $p->getVariableName());

            return $existingProperties->merge($existingReadProperties);
        }

        return collect();
    }

    protected function getParentClass(ReflectionClass $reflection)
    {
        if (!$reflection->getParentClass()) {
            return null;
        }

        $parent = $reflection->getParentClass()->getName();

        if ($parent === Model::class) {
            return null;
        }

        return Str::start($parent, '\\');
    }

    protected function getInfo($className)
    {
        if (($data = $this->fromArtisan($className)) === null) {
            return null;
        }

        $reflection = new ReflectionClass($className);

        $data['extends'] = $this->getParentClass($reflection);

        $existingProperties = $this->collectExistingProperties($reflection);

        $data['attributes'] = collect($data['attributes'])
            ->map(fn ($attrs) => array_merge($attrs, [
                'title_case' => str($attrs['name'])->title()->replace('_', '')->toString(),
                'documented' => $existingProperties->contains($attrs['name']),
                'cast'       => $this->getCastReturnType($attrs['cast']),
            ]))
            ->toArray();

        $data['scopes'] = collect($reflection->getMethods())
            ->filter(fn (ReflectionMethod $method) => !$method->isStatic() && ($method->getAttributes(Scope::class) || ($method->isPublic() && str_starts_with($method->name, 'scope'))))
            ->map(fn (ReflectionMethod $method) => [
                'name'       => str($method->name)->replace('scope', '')->lcfirst()->toString(),
                'method'     => $method->name,
                'parameters' => collect($method->getParameters())->map($this->getScopeParameterInfo(...)),
            ])
            ->values()
            ->toArray();

        $data['relations'] = collect($data['relations'])
            ->map(fn ($relation) => array_merge($relation, [
                'snake_case' => Str::snake($relation['name']),
            ]))
            ->toArray();

        $data['path'] = LspHelper::relativePath($reflection->getFileName() ?: '');

        return [
            $className => $data,
        ];
    }

    protected function getScopeParameterInfo(ReflectionParameter $parameter): array
    {
        $result = [
            'name'                => $parameter->getName(),
            'type'                => $this->typeToString($parameter->getType()),
            'hasDefault'          => $parameter->isDefaultValueAvailable(),
            'isVariadic'          => $parameter->isVariadic(),
            'isPassedByReference' => $parameter->isPassedByReference(),
        ];

        if ($parameter->isDefaultValueAvailable()) {
            $result['default'] = $this->defaultValueToString($parameter);
        }

        return $result;
    }

    protected function typeToString(?ReflectionType $type): string
    {
        return match (true) {
            $type instanceof ReflectionNamedType        => $this->namedTypeToString($type),
            $type instanceof ReflectionUnionType        => $this->unionTypeToString($type),
            $type instanceof ReflectionIntersectionType => $this->intersectionTypeToString($type),
            default                                     => 'mixed',
        };
    }

    protected function namedTypeToString(ReflectionNamedType $type): string
    {
        $name = $type->getName();

        if (!$type->isBuiltin() && !in_array($name, ['self', 'parent', 'static'])) {
            $name = '\\' . $name;
        }

        if ($type->allowsNull() && !in_array($name, ['null', 'mixed', 'void'])) {
            $name = '?' . $name;
        }

        return $name;
    }

    protected function unionTypeToString(ReflectionUnionType $type): string
    {
        return implode('|', array_map(function (ReflectionType $type) {
            $result = $this->typeToString($type);

            if ($type instanceof ReflectionIntersectionType) {
                return "({$result})";
            }

            return $result;
        }, $type->getTypes()));
    }

    protected function intersectionTypeToString(ReflectionIntersectionType $type): string
    {
        return implode('&', array_map($this->typeToString(...), $type->getTypes()));
    }

    protected function defaultValueToString(ReflectionParameter $param): string
    {
        if ($param->isDefaultValueConstant()) {
            return '\\' . $param->getDefaultValueConstantName();
        }

        $value = $param->getDefaultValue();

        return match (true) {
            is_null($value)    => 'null',
            is_numeric($value) => $value,
            is_bool($value)    => $value ? 'true' : 'false',
            is_array($value)   => '[]',
            is_object($value)  => 'new \\' . get_class($value),
            default            => "'{$value}'",
        };
    }
};

$builder = new class($docblocks)
{
    public function __construct(protected $docblocks) {}

    public function methods()
    {
        $reflection = new ReflectionClass(Builder::class);

        return collect($reflection->getMethods(ReflectionMethod::IS_PUBLIC | ReflectionMethod::IS_PROTECTED))
            ->filter(fn (ReflectionMethod $method) => !str_starts_with($method->getName(), '__') || (!$method->isPublic() && empty($method->getAttributes(Scope::class))))
            ->map(fn (ReflectionMethod $method) => $this->getMethodInfo($method))
            ->filter()
            ->values();
    }

    protected function getMethodInfo($method)
    {
        [$params, $return] = $this->docblocks->forMethod($method);

        return [
            'name'       => $method->getName(),
            'parameters' => $params,
            'return'     => $return,
        ];
    }
};

echo json_encode([
    'builderMethods' => $builder->methods(),
    'models'         => $models->all(),
]);
