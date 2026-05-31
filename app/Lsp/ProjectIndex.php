<?php

declare(strict_types=1);

namespace App\Lsp;

use App\Lsp\Contracts\DataProvider;
use App\Lsp\Data\AppBindings;
use App\Lsp\Data\Assets;
use App\Lsp\Data\Auth;
use App\Lsp\Data\BladeComponents;
use App\Lsp\Data\Configs;
use App\Lsp\Data\Controllers;
use App\Lsp\Data\CustomBladeDirectives;
use App\Lsp\Data\DebugInfo;
use App\Lsp\Data\Env;
use App\Lsp\Data\InertiaViews;
use App\Lsp\Data\Middleware;
use App\Lsp\Data\MixManifest;
use App\Lsp\Data\Models;
use App\Lsp\Data\Paths;
use App\Lsp\Data\Routes;
use App\Lsp\Data\Tests;
use App\Lsp\Data\Translations;
use App\Lsp\Data\Views;
use App\Lsp\Exceptions\DataProviderNotFoundException;
use App\Lsp\Support\Pattern;
use Illuminate\Container\Container;
use Illuminate\Support\Collection;

class ProjectIndex
{
    /**
     * Project data providers.
     *
     * @var array<string, class-string<DataProvider>>
     */
    protected array $providers = [
        'appBindings' => AppBindings::class,
        'assets' => Assets::class,
        'auth' => Auth::class,
        'bladeComponents' => BladeComponents::class,
        'configs' => Configs::class,
        'controllers' => Controllers::class,
        'customBladeDirectives' => CustomBladeDirectives::class,
        'debugInfo' => DebugInfo::class,
        'env' => Env::class,
        'inertiaViews' => InertiaViews::class,
        'middleware' => Middleware::class,
        'mixManifest' => MixManifest::class,
        'models' => Models::class,
        'paths' => Paths::class,
        'routes' => Routes::class,
        'tests' => Tests::class,
        'translations' => Translations::class,
        'views' => Views::class,
    ];

    /**
     * Loaded project data.
     *
     * @var array<string, mixed>
     */
    protected array $loaded = [];

    /**
     * Instantiate a new class instance.
     */
    public function __construct(protected Container $container)
    {
        //
    }

    /**
     * Get the app bindings provider.
     */
    public function appBindings(): Collection
    {
        return $this->get(__FUNCTION__);
    }

    /**
     * Get the assets provider.
     */
    public function assets(): Collection
    {
        return $this->get(__FUNCTION__);
    }

    /**
     * Get the auth provider.
     */
    public function auth(): array
    {
        return $this->get(__FUNCTION__);
    }

    /**
     * Get the Blade components provider.
     */
    public function bladeComponents(): array
    {
        return $this->get(__FUNCTION__);
    }

    /**
     * Get the configs provider.
     */
    public function configs(): array
    {
        return $this->get(__FUNCTION__);
    }

    /**
     * Get the controllers provider.
     */
    public function controllers(): Collection
    {
        return $this->get(__FUNCTION__);
    }

    /**
     * Get the custom Blade directives provider.
     */
    public function customBladeDirectives(): array
    {
        return $this->get(__FUNCTION__);
    }

    /**
     * Get the debug info provider.
     */
    public function debugInfo(): array
    {
        return $this->get(__FUNCTION__);
    }

    /**
     * Get the env provider.
     */
    public function env(): Collection
    {
        return $this->get(__FUNCTION__);
    }

    /**
     * Get the inertia views provider.
     */
    public function inertiaViews(): array
    {
        return $this->get(__FUNCTION__);
    }

    /**
     * Get the middleware provider.
     */
    public function middleware(): Collection
    {
        return $this->get(__FUNCTION__);
    }

    /**
     * Get the mix manifest provider.
     */
    public function mixManifest(): Collection
    {
        return $this->get(__FUNCTION__);
    }

    /**
     * Get the models provider.
     */
    public function models(): array
    {
        return $this->get(__FUNCTION__);
    }

    /**
     * Get the paths provider.
     */
    public function paths(): Collection
    {
        return $this->get(__FUNCTION__);
    }

    /**
     * Get the routes provider.
     */
    public function routes(): Collection
    {
        return $this->get(__FUNCTION__);
    }

    /**
     * Get the tests provider.
     */
    public function tests(): array
    {
        return $this->get(__FUNCTION__);
    }

    /**
     * Get the translations provider.
     */
    public function translations(): array
    {
        return $this->get(__FUNCTION__);
    }

    /**
     * Get the views provider.
     */
    public function views(): Collection
    {
        return $this->get(__FUNCTION__);
    }

    /**
     * Get data by provider name.
     */
    public function get(string $name): mixed
    {
        return $this->loaded[$name] ??= $this->load($name);
    }

    /**
     * Get data provider watcher patterns.
     *
     * @return array<int, string>
     */
    public function patterns(): array
    {
        $patterns = [];

        foreach ($this->providers as $class) {
            array_push($patterns, ...$this->container->make($class)->patterns());
        }

        return array_values(array_unique($patterns));
    }

    /**
     * Get the data.
     */
    protected function load(string $name): mixed
    {
        if (! isset($this->providers[$name])) {
            throw new DataProviderNotFoundException($name);
        }

        return $this->container->make($this->providers[$name])->get();
    }

    /**
     * Invalidate data providers.
     *
     * @param  array<int, string>  $changes
     */
    public function invalidate(array $changes): void
    {
        foreach ($this->providers as $name => $class) {
            $provider = $this->container->make($class);

            if (Pattern::matchesAnyPath($changes, $provider->patterns())) {
                unset($this->loaded[$name]);
            }
        }
    }
}
