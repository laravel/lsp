<?php

declare(strict_types=1);

namespace App\Lsp\Detection;

use App\Lsp\Support\Position;

class DetectedArgument
{
    /**
     * Create a new detected argument instance.
     *
     * @param  array<string, mixed>  $item
     * @param  array<string, mixed>  $param
     */
    public function __construct(
        protected array $item,
        protected int $argumentIndex,
        protected array $param,
    ) {}

    /**
     * Get the matched parser item.
     *
     * @return array<string, mixed>
     */
    public function item(): array
    {
        return $this->item;
    }

    /**
     * Get the matched parameter array.
     *
     * @return array<string, mixed>
     */
    public function param(): array
    {
        return $this->param;
    }

    /**
     * Get the matched argument index.
     */
    public function argumentIndex(): int
    {
        return $this->argumentIndex;
    }

    /**
     * Get the detected parameter range.
     *
     * @return array<string, array<string, int>>
     */
    public function range(): array
    {
        return [
            'start' => [
                'line'      => $this->param['start']['line'],
                'character' => $this->param['start']['column'] + 1,
            ],
            'end' => [
                'line'      => $this->param['end']['line'],
                'character' => $this->param['end']['column'] + 1,
            ],
        ];
    }

    /**
     * Get the detected parameter value.
     */
    public function value(): mixed
    {
        return $this->param['value'] ?? null;
    }

    /**
     * Determine if the argument is an interpolated string.
     */
    public function isInterpolated(): bool
    {
        return ($this->param['interpolated'] ?? false) === true;
    }

    /**
     * Get the detected parameter value as a string.
     */
    public function stringValue(): ?string
    {
        $value = $this->value();

        return is_string($value) ? $value : null;
    }

    /**
     * Get string values contained by the detected argument.
     *
     * @return array<int, array{value: string, range: array<string, array<string, int>>}>
     */
    public function stringValues(): array
    {
        if ($this->param['type'] === 'string') {
            return [[
                'value'        => $this->param['value'],
                'range'        => $this->range(),
                'interpolated' => $this->isInterpolated(),
            ]];
        }

        $values = [];

        foreach ($this->param['children'] as $child) {
            $value = $child['value'];

            if ($value === null || $value['type'] !== 'string') {
                continue;
            }

            $values[] = [
                'value'        => $value['value'],
                'range'        => $this->rangeForValue($value),
                'interpolated' => ($value['interpolated'] ?? false) === true,
            ];
        }

        return $values;
    }

    /**
     * Get the detected string values, excluding interpolated ones.
     *
     * @return array<int, array<string, mixed>>
     */
    public function literalStringValues(): array
    {
        return array_values(array_filter(
            $this->stringValues(),
            fn (array $value): bool => $value['interpolated'] !== true,
        ));
    }

    /**
     * Determine if the detected argument contains the position.
     *
     * @param  array<string, mixed>  $position
     */
    public function containsPosition(array $position): bool
    {
        if (isset($this->param['start'], $this->param['end']) && Position::inRange($this->range(), $position)) {
            return true;
        }

        foreach ($this->stringValues() as $value) {
            if (Position::inRange($value['range'], $position)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get an LSP range from a parser value.
     *
     * @param  array<string, mixed>  $value
     * @return array<string, array<string, int>>
     */
    private function rangeForValue(array $value): array
    {
        return [
            'start' => [
                'line'      => $value['start']['line'],
                'character' => $value['start']['column'] + 1,
            ],
            'end' => [
                'line'      => $value['end']['line'],
                'character' => $value['end']['column'] + 1,
            ],
        ];
    }
}
