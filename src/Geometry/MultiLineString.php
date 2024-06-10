<?php

namespace Tochka\GeoPHP\Geometry;

/**
 * MultiLineString: A collection of LineStrings
 *
 * @api
 */
class MultiLineString extends Collection
{
    public function geometryType(): string
    {
        return 'MultiLineString';
    }

    // MultiLineString is closed if all it's components are closed
    public function isClosed()
    {
        foreach ($this->components as $line) {
            if (!$line->isClosed()) {
                return false;
            }
        }
        return true;
    }
}
