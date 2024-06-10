<?php

namespace Tochka\GeoPHP\Geometry;

/**
 * MultiPolygon: A collection of Polygons
 * @api
 * @extends Collection<Polygon>
 * @psalm-immutable
 */
readonly class MultiPolygon extends Collection
{
    public function geometryType(): string
    {
        return 'MultiPolygon';
    }
}
