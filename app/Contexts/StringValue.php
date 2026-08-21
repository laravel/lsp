<?php

namespace App\Contexts;

class StringValue extends AbstractContext
{
    public ?string $value = null;

    public bool $interpolated = false;

    protected bool $hasChildren = false;

    public function type(): string
    {
        return 'string';
    }

    public function castToArray(): array
    {
        return array_merge(
            ['value' => $this->value],
            $this->interpolated ? ['interpolated' => true] : [],
        );
    }
}
