<?php

declare(strict_types=1);

namespace App\Lsp;

use App\Lsp\Contracts\DataProvider;
use App\Lsp\Contracts\TemplateDataProvider;
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
     * Template providers whose data is a snapshot of application state.
     *
     * Their templates run before the others share a process with them, so
     * the snapshot is not changed by whatever the other templates touch.
     *
     * @var array<int, string>
     */
    protected array $snapshots = ['configs'];

    /**
     * Loaded project data.
     *
     * @var array<string, mixed>
     */
    protected array $loaded = [];

    /**
     * Instantiate a new class instance.
     */
    public function __construct(
        protected Container $container,
        protected ScriptRunner $scripts,
    ) {}

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
        if (!isset($this->loaded[$name])) {
            $this->load($name);
        }

        return $this->loaded[$name];
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
     * Load the data.
     */
    protected function load(string $name): void
    {
        if (! isset($this->providers[$name])) {
            throw new DataProviderNotFoundException($name);
        }

        $provider = $this->container->make($this->providers[$name]);

        if ($provider instanceof TemplateDataProvider) {
            $this->loadTemplates();

            return;
        }

        $this->loaded[$name] = $provider->get();
    }

    /**
     * Load every template provider that is not loaded yet.
     *
     * Booting the application dominates the cost of running a template, so the
     * pending templates run in a single process and share one boot.
     */
    protected function loadTemplates(): void
    {
        $providers = array_filter(
            $this->templateProviders(),
            fn (string $name): bool => !isset($this->loaded[$name]),
            ARRAY_FILTER_USE_KEY,
        );

        $data = $this->scripts->batch(array_map(
            fn (TemplateDataProvider $provider): string => $provider->template(),
            $providers,
        ));

        foreach ($providers as $name => $provider) {
            $this->loaded[$name] = $provider->parse(is_array($data[$name] ?? null) ? $data[$name] : []);
        }
    }

    /**
     * Get the template providers in the order their templates run.
     *
     * @return array<string, TemplateDataProvider>
     */
    protected function templateProviders(): array
    {
        $providers = [];

        foreach ($this->providers as $name => $class) {
            $provider = $this->container->make($class);

            if ($provider instanceof TemplateDataProvider) {
                $providers[$name] = $provider;
            }
        }

        $snapshots = array_flip($this->snapshots);

        return array_intersect_key($providers, $snapshots) + array_diff_key($providers, $snapshots);
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
