<?php

namespace Tochka\GeoPHP\Geometry;

/**
 * MultiPoint: A collection Points
 * @api
 */
class MultiPoint extends Collection
{
    public function geometryType(): string
    {
        return 'MultiPoint';
    }

    public function numPoints()
    {
        return $this->numGeometries();
    }

    public function isSimple()
    {
        return true;
    }

    // Not valid for this geometry type
    // --------------------------------
    public function explode()
    {
        return null;
    }
}
