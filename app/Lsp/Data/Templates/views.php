<?php

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Livewire\LivewireManager;
use Symfony\Component\Finder\Finder;

$livewire = new class
{
    protected $namespaces;

    protected $paths;

    protected $extensions = ['.blade.php', '.php', '.js', '.global.css', '.css', '.test.php'];

    public function __construct()
    {
        $this->namespaces = collect(
            config('livewire.component_namespaces', [])
        )->map(LspHelper::relativePath(...));

        $this->paths = collect($this->namespaces->values())
            ->merge(config('livewire.component_locations', []))
            ->unique()
            ->map(LspHelper::relativePath(...));
    }

    public function parse(Collection $views)
    {
        return $this->isVersionFour()
            ? $this->parseLivewireFour($views)
            : $this->parseLivewireThree($views);
    }

    protected function parseLivewireFour(Collection $views)
    {
        return $views
            ->map(function (array $view) {
                if (!$this->pathExists($view['path'])) {
                    return $view;
                }

                if (is_null($component = $this->getComponent($key = $this->key($view)))) {
                    return $view;
                }

                $files = $this->files($component, $view);

                if (count($files) === 1 && !str($view['path'])->endsWith('.blade.php')) {
                    return null;
                }

                return array_merge($view, [
                    'key'      => $key,
                    'livewire' => [
                        'props' => $this->getProps($component),
                        'files' => $files,
                    ],
                ]);
            })
            ->whereNotNull()
            ->unique('key')
            ->values();
    }

    protected function parseLivewireThree(Collection $views)
    {
        return $views->map(function (array $view) {
            if (!str($view['key'])->startsWith('livewire.')) {
                return $view;
            }

            $key = str($view['key'])->after('livewire.')->value();

            if (is_null($component = $this->getComponent($key))) {
                return $view;
            }

            $files = $this->files($component, $view);

            return array_merge($view, [
                'livewire' => [
                    'props' => $this->getProps($component),
                    'files' => $files,
                ],
            ]);
        });
    }

    protected function isVersionFour(): bool
    {
        return property_exists(LivewireManager::class, 'v4') &&
            LivewireManager::$v4;
    }

    protected function pathExists(string $path): bool
    {
        return $this->paths->contains(fn (string $item) => str($path)->contains($item));
    }

    protected function getComponent(string $key)
    {
        try {
            return app('livewire')->new($key);
        } catch (Throwable $e) {
            return null;
        }
    }

    protected function getProps($component): array
    {
        return array_map(function ($prop) use ($component) {
            $reflection = new ReflectionProperty($component, $prop);

            return [
                'name'            => $prop,
                'type'            => (string) $reflection->getType() ?: 'mixed',
                'hasDefaultValue' => $reflection->hasDefaultValue(),
                'defaultValue'    => LspHelper::formatDefaultValue($reflection->getDefaultValue()),
            ];
        }, array_keys($component->all()));
    }

    protected function key(array $view): string
    {
        return str($view['key'])
            ->replace('⚡', '')
            ->when($this->isMfc($view), fn ($key) => $key->beforeLast('.'))
            ->value();
    }

    protected function classFile(object $class): ?string
    {
        try {
            $reflection = new \ReflectionClass($class);

            if ($reflection->isAnonymous()) {
                return null;
            }

            return LspHelper::relativePath($reflection->getFileName());
        } catch (Throwable $e) {
            return null;
        }
    }

    protected function files(object $component, array $view): array
    {
        return collect()
            ->when(
                $this->isMfc($view),
                fn (Collection $files) => $files->merge($this->mfcFiles($view)),
                fn (Collection $files) => $files->prepend($view['path'])
            )
            ->push($this->classFile($component))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    protected function mfcFiles(array $view): Collection
    {
        $filePathWithoutExtension = str($view['path'])->replace($this->extensions, '');

        return collect($this->extensions)
            ->map(fn (string $extension) => $filePathWithoutExtension->append($extension))
            ->filter(fn (string $path) => File::exists($path));
    }

    protected function isMfc(array $view): bool
    {
        if (! $this->isVersionFour()) {
            return false;
        }

        $directory = str($view['path'])
            ->replace('⚡', '')
            ->dirname()
            ->afterLast(DIRECTORY_SEPARATOR);

        $file = str($view['path'])
            ->replace('⚡', '')
            ->basename()
            ->replace($this->extensions, '');

        $class = str($view['path'])
            ->dirname()
            ->append(DIRECTORY_SEPARATOR . $file . '.php');

        return $directory->is($file)
            && File::exists($class);
    }
};

$blade = new class($livewire)
{
    public function __construct(protected $livewire)
    {
        //
    }

    public function getAllViews()
    {
        $finder = app('view')->getFinder();

        $paths = collect($finder->getPaths())->flatMap(fn ($path) => $this->findViews($path));

        $hints = collect($finder->getHints())
            ->filter(
                fn ($_, $key) => !(strlen($key) === 32 && ctype_xdigit($key))
            )->flatMap(
                fn ($paths, $key) => collect($paths)->flatMap(
                    fn ($path) => collect($this->findViews($path))->map(
                        fn ($value) => array_merge($value, ['key' => "{$key}::{$value['key']}"])
                    )
                )
            );

        [$local, $vendor] = $paths
            ->merge($hints)
            ->values()
            ->partition(fn ($v) => !$v['isVendor']);

        return $local
            ->sortBy('key', SORT_NATURAL)
            ->merge($vendor->sortBy('key', SORT_NATURAL))
            ->pipe($this->livewire->parse(...));
    }

    public function getAllComponents()
    {
        $namespaced = Blade::getClassComponentNamespaces();
        $autoloaded = require base_path('vendor/composer/autoload_psr4.php');
        $components = [];

        foreach ($namespaced as $key => $ns) {
            $path = null;

            foreach ($autoloaded as $namespace => $paths) {
                if (str_starts_with($ns, $namespace)) {
                    foreach ($paths as $p) {
                        $test = str($ns)->replace($namespace, '')->replace('\\', '/')->prepend($p . DIRECTORY_SEPARATOR)->toString();

                        if (is_dir($test)) {
                            $path = $test;
                            break;
                        }
                    }

                    break;
                }
            }

            if (!$path) {
                continue;
            }

            $files = Finder::create()
                ->files()
                ->name('*.php')
                ->in($path);

            foreach ($files as $file) {
                $realPath = $file->getRealPath();

                $components[] = [
                    'path'     => LspHelper::relativePath($realPath),
                    'isVendor' => str_contains($realPath, base_path('vendor')),
                    'key'      => str($realPath)
                        ->replace(realpath($path), '')
                        ->replace('.php', '')
                        ->ltrim(DIRECTORY_SEPARATOR)
                        ->replace(DIRECTORY_SEPARATOR, '.')
                        ->kebab()
                        ->prepend($key . '::'),
                ];
            }
        }

        return $components;
    }

    protected function findViews($path)
    {
        $paths = [];

        if (!is_dir($path)) {
            return $paths;
        }

        $finder = app('view')->getFinder();
        $extensions = array_map(fn ($extension) => ".{$extension}", $finder->getExtensions());

        $files = Finder::create()
            ->files()
            ->name(array_map(fn ($ext) => "*{$ext}", $extensions))
            ->in($path);

        foreach ($files as $file) {
            $paths[] = [
                'path'     => LspHelper::relativePath($file->getRealPath()),
                'isVendor' => str_contains($file->getRealPath(), base_path('vendor')),
                'key'      => str($file->getRealPath())
                    ->replace(realpath($path), '')
                    ->replace($extensions, '')
                    ->ltrim(DIRECTORY_SEPARATOR)
                    ->replace(DIRECTORY_SEPARATOR, '.'),
            ];
        }

        return $paths;
    }
};

echo json_encode($blade->getAllViews()->merge($blade->getAllComponents()));
