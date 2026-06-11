<?php

declare(strict_types=1);

namespace Foxdb\Query;

/**
 * Wraps a raw SQL expression so Grammar can distinguish it
 * from a regular string value and pass it through unquoted.
 *
 * Usage:
 *   DB::table('users')->select(new RawExpression('COUNT(*) as total'));
 *   DB::raw('NOW()')  // shorthand via DB facade
 */
final class RawExpression
{
    /**
     * @param string                   $value    The raw SQL string
     * @param array<int|string, mixed> $bindings Optional bindings for this expression
     */
    public function __construct(
        public readonly string $value,
        public readonly array  $bindings = [],
    ) {}

    /**
     * Return the raw SQL string when cast to string.
     *
     * @return string
     */
    public function __toString(): string
    {
        return $this->value;
    }
}
