<?php

declare(strict_types=1);

namespace Waffo\Model;

use JsonSerializable;
use Waffo\Model\Generated\ModelRegistry;

abstract class AbstractModel implements JsonSerializable
{
    /** @var array<string, array{property: string, type: string, required: bool, ref: ?string, itemsRef: ?string}> */
    public const SCHEMA = [];

    /** @var array<string, mixed> */
    private array $extra = [];

    /** @param array<string, mixed> $values */
    final public function __construct(array $values = [])
    {
        foreach ($values as $wireName => $value) {
            $field = static::SCHEMA[$wireName] ?? null;
            if ($field === null) {
                $this->extra[$wireName] = $value;
                continue;
            }

            $property = $field['property'];
            $this->{$property} = self::hydrateValue($value, $field['ref'], $field['itemsRef']);
        }
    }

    /** @param array<string, mixed> $values */
    public static function fromArray(array $values): static
    {
        return new static($values);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $result = [];
        foreach (static::SCHEMA as $wireName => $field) {
            $property = $field['property'];
            $value = $this->{$property};
            if ($value !== null) {
                $result[$wireName] = self::normalizeValue($value);
            }
        }

        return array_replace($result, $this->extra);
    }

    /** @return array<string, mixed> */
    public function extraFields(): array
    {
        return $this->extra;
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    private static function hydrateValue(mixed $value, ?string $ref, ?string $itemsRef): mixed
    {
        if ($value === null) {
            return null;
        }
        if ($ref !== null && is_array($value)) {
            return ModelRegistry::hydrate($ref, $value);
        }
        if ($itemsRef !== null && is_array($value)) {
            return array_map(
                static fn (mixed $item): mixed => is_array($item) ? ModelRegistry::hydrate($itemsRef, $item) : $item,
                $value,
            );
        }

        return $value;
    }

    private static function normalizeValue(mixed $value): mixed
    {
        if ($value instanceof self) {
            return $value->toArray();
        }
        if (is_array($value)) {
            return array_map(static fn (mixed $item): mixed => self::normalizeValue($item), $value);
        }

        return $value;
    }
}
