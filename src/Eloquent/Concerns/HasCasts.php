<?php

declare(strict_types=1);

namespace Foxdb\Eloquent\Concerns;

use DateTime;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * HasCasts — casts raw column values to PHP types on read,
 * and back to storable values on write.
 *
 * Supported cast types:
 *   'int'      | 'integer'
 *   'float'    | 'double' | 'real'
 *   'bool'     | 'boolean'
 *   'string'
 *   'array'    | 'json'       — JSON encode/decode
 *   'object'                  — JSON decode as stdClass
 *   'datetime' | 'date'       — DateTime object
 *   'immutable_datetime'      — DateTimeImmutable object
 *
 * Usage:
 *   protected array $casts = [
 *       'is_active' => 'bool',
 *       'settings'  => 'array',
 *       'price'     => 'float',
 *       'born_at'   => 'date',
 *   ];
 */
trait HasCasts
{
    /**
     * Column-to-type cast map defined on the model.
     *
     * @var array<string, string>
     */
    protected array $casts = [];

    /**
     * Cast a raw database value to the declared PHP type for a column.
     *
     * @param  string $key    Column name
     * @param  mixed  $value  Raw value from PDO
     * @return mixed
     */
    protected function castValue(string $key, mixed $value): mixed
    {
        if (! isset($this->casts[$key]) || $value === null) {
            return $value;
        }

        return match ($this->normalizeCastType($this->casts[$key])) {
            'int'               => (int) $value,
            'float'             => (float) $value,
            'bool'              => (bool) $value,
            'string'            => (string) $value,
            'array'             => $this->castToArray($value),
            'object'            => $this->castToObject($value),
            'datetime'          => $this->castToDateTime($value),
            'immutable_datetime'=> $this->castToDateTimeImmutable($value),
            default             => $value,
        };
    }

    /**
     * Cast a PHP value back to a storable format for a column.
     * Called before INSERT/UPDATE.
     *
     * @param  string $key    Column name
     * @param  mixed  $value  PHP value to store
     * @return mixed
     */
    protected function castForStorage(string $key, mixed $value): mixed
    {
        if (! isset($this->casts[$key]) || $value === null) {
            return $value;
        }

        $type = $this->normalizeCastType($this->casts[$key]);

        if (in_array($type, ['array', 'object'], strict: true)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        if (in_array($type, ['datetime', 'immutable_datetime'], strict: true)) {
            if ($value instanceof DateTime || $value instanceof DateTimeImmutable) {
                return $value->format('Y-m-d H:i:s');
            }
        }

        return $value;
    }

    /**
     * Apply castValue() to all columns in the given attribute map.
     *
     * @param  array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    protected function castAttributes(array $attributes): array
    {
        foreach ($attributes as $key => $value) {
            $attributes[$key] = $this->castValue($key, $value);
        }

        return $attributes;
    }

    /**
     * Apply castForStorage() to all columns in the given attribute map.
     *
     * @param  array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    protected function castAttributesForStorage(array $attributes): array
    {
        foreach ($attributes as $key => $value) {
            $attributes[$key] = $this->castForStorage($key, $value);
        }

        return $attributes;
    }

    /**
     * Check whether a column has a declared cast.
     *
     * @param  string $key
     * @return bool
     */
    public function hasCast(string $key): bool
    {
        return isset($this->casts[$key]);
    }

    /**
     * Get the cast type for a column, or null if none is declared.
     *
     * @param  string $key
     * @return string|null
     */
    public function getCastType(string $key): ?string
    {
        return isset($this->casts[$key])
            ? $this->normalizeCastType($this->casts[$key])
            : null;
    }

    // -----------------------------------------------------------------------
    // Internal helpers
    // -----------------------------------------------------------------------

    /**
     * Normalise cast type aliases to a canonical form.
     *
     * @param  string $type
     * @return string
     */
    private function normalizeCastType(string $type): string
    {
        return match (strtolower(trim($type))) {
            'int', 'integer'            => 'int',
            'float', 'double', 'real'   => 'float',
            'bool', 'boolean'           => 'bool',
            'string'                    => 'string',
            'array', 'json'             => 'array',
            'object'                    => 'object',
            'datetime', 'date'          => 'datetime',
            'immutable_datetime'        => 'immutable_datetime',
            default                     => strtolower(trim($type)),
        };
    }

    /**
     * @param  mixed $value
     * @return array<mixed>
     */
    private function castToArray(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        $decoded = json_decode((string) $value, associative: true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  mixed $value
     * @return object
     */
    private function castToObject(mixed $value): object
    {
        if (is_object($value)) {
            return $value;
        }

        $decoded = json_decode((string) $value);

        return is_object($decoded) ? $decoded : (object) [];
    }

    /**
     * @param  mixed $value
     * @return DateTime
     */
    private function castToDateTime(mixed $value): DateTime
    {
        if ($value instanceof DateTime) {
            return $value;
        }

        return new DateTime((string) $value);
    }

    /**
     * @param  mixed $value
     * @return DateTimeImmutable
     */
    private function castToDateTimeImmutable(mixed $value): DateTimeImmutable
    {
        if ($value instanceof DateTimeImmutable) {
            return $value;
        }

        return new DateTimeImmutable((string) $value);
    }
}
