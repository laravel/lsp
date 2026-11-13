<?php

use Illuminate\Auth\DatabaseUserProvider;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Auth\GenericUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Gate;

if (!App::bound('auth')) {
    echo json_encode([
        'authenticatable' => null,
        'policies'        => (object) [],
    ]);
} else {
    LspHelper::includeModels($GLOBALS['arguments']['modelPaths']);

    $modelPolicies = collect(get_declared_classes())
        ->filter(fn ($class) => is_subclass_of($class, Model::class))
        ->filter(fn ($class) => !in_array($class, [
            Pivot::class,
            User::class,
        ]))
        ->filter(fn ($class) => (new ReflectionClass($class))->isInstantiable())
        ->flatMap(fn ($class) => [
            $class => Gate::getPolicyFor($class),
        ])
        ->filter(fn ($policy) => $policy !== null);

    function vsCodeGetAuthenticatable()
    {
        try {
            $guard = auth()->guard();

            $reflection = new ReflectionClass($guard);

            if (!$reflection->hasProperty('provider')) {
                return null;
            }

            $property = $reflection->getProperty('provider');
            $provider = $property->getValue($guard);

            if ($provider instanceof EloquentUserProvider) {
                $providerReflection = new ReflectionClass($provider);
                $modelProperty = $providerReflection->getProperty('model');

                return str($modelProperty->getValue($provider))->prepend('\\')->toString();
            }

            if ($provider instanceof DatabaseUserProvider) {
                return str(GenericUser::class)->prepend('\\')->toString();
            }
        } catch (Exception|Throwable $e) {
            return null;
        }

        return null;
    }

    function vsCodeGetPolicyInfo($policy, $model)
    {
        $methods = (new ReflectionClass($policy))->getMethods();

        return collect($methods)->map(fn (ReflectionMethod $method) => [
            'key'    => $method->getName(),
            'uri'    => LspHelper::relativePath($method->getFileName()),
            'policy' => is_string($policy) ? $policy : get_class($policy),
            'model'  => $model,
            'line'   => $method->getStartLine(),
        ])->filter(fn ($ability) => !in_array($ability['key'], ['allow', 'deny']));
    }

    echo json_encode([
        'authenticatable' => vsCodeGetAuthenticatable(),
        'policies'        => collect(Gate::abilities())
            ->map(function ($policy, $key) {
                $reflection = new ReflectionFunction($policy);
                $policyClass = null;
                $closureThis = $reflection->getClosureThis();

                if ($closureThis !== null) {
                    if (get_class($closureThis) === Illuminate\Auth\Access\Gate::class) {
                        $vars = $reflection->getClosureUsedVariables();

                        if (isset($vars['callback']) && str_contains($vars['callback'], '@')) {
                            [$policyClass, $method] = explode('@', $vars['callback']);

                            if (method_exists($policyClass, $method)) {
                                $reflection = new ReflectionMethod($policyClass, $method);
                            }
                        }
                    }
                }

                return [
                    'key'    => $key,
                    'uri'    => LspHelper::relativePath($reflection->getFileName()),
                    'policy' => $policyClass,
                    'line'   => $reflection->getStartLine(),
                ];
            })
            ->merge(
                collect(Gate::policies())->flatMap(fn ($policy, $model) => vsCodeGetPolicyInfo($policy, $model)),
            )
            ->merge(
                $modelPolicies->flatMap(fn ($policy, $model) => vsCodeGetPolicyInfo($policy, $model)),
            )
            ->values()
            ->groupBy('key')
            ->map(fn ($item) => $item->map(fn ($i) => Arr::except($i, 'key'))),
    ]);
}
