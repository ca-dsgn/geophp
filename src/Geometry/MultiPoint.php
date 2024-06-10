<?php

namespace Tochka\GeoPHP\Geometry;

/**
 * MultiPoint: A collection Points
 * @api
 * @extends Collection<Point>
 * @psalm-immutable
 */
readonly class MultiPoint extends Collection
{
    public function geometryType(): string
    {
        return 'MultiPoint';
    }

    public function numPoints(): int
    {
        return $this->numGeometries();
    }

    public function isSimple(): bool
    {
        return true;
    }

    // Not valid for this geometry type
    // --------------------------------
    public function explode(): ?array
    {
        return null;
    }
}
