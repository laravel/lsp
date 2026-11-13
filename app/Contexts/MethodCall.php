<?php

namespace App\Contexts;

class MethodCall extends AbstractContext
{
    public bool $isAbleToAutocomplete = true;

    public ?string $methodName = null;

    public ?string $className = null;

    public Arguments $arguments;

    public function init()
    {
        $this->arguments = new Arguments;
        $this->arguments->parent = $this;
    }

    public function type(): string
    {
        return 'methodCall';
    }

    public function castToArray(): array
    {
        return [
            'methodName' => $this->methodName,
            'className'  => $this->className,
            'arguments'  => $this->arguments->toArray(),
        ];
    }

    public function flip()
    {
        $receiver = $this->receiver();

        return array_merge(
            parent::flip(),
            $receiver ? ['receiver' => $receiver] : [],
        );
    }

    /**
     * Get the method call chain this method is called on, e.g. `posts()` in `$user->posts()->create()`.
     */
    public function receiver(): ?array
    {
        foreach ($this->children as $child) {
            if ($child instanceof MethodCall) {
                $receiver = $child->receiver();

                return array_merge(
                    ['methodName' => $child->methodName, 'className' => $child->className],
                    $receiver ? ['receiver' => $receiver] : [],
                );
            }
        }

        return null;
    }

    public function name()
    {
        return $this->methodName;
    }
}
