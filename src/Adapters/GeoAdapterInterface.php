<?php

namespace Tochka\GeoPHP\Adapters;

use Tochka\GeoPHP\Geometry\GeometryInterface;

/**
 * GeoAdapter : abstract class which represents an adapter
 * for reading and writing to and from Geometry objects
 *
 * @api
 * @psalm-immutable
 */
interface GeoAdapterInterface
{
    /**
     * Read input and return a Geometry or GeometryCollection
     */
    public function read(string $input): GeometryInterface;

    /**
     * Write out a Geometry or GeometryCollection in the adapter's format
     */
    public function write(GeometryInterface $geometry): mixed;
}
