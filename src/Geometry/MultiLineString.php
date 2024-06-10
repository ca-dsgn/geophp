<?php

namespace Tochka\GeoPHP\Geometry;

/**
 * MultiLineString: A collection of LineStrings
 *
 * @api
 * @extends Collection<LineString>
 */
class MultiLineString extends Collection
{
    public function geometryType(): string
    {
        return 'MultiLineString';
    }

    // MultiLineString is closed if all it's components are closed
    public function isClosed(): bool
    {
        foreach ($this->getComponents() as $line) {
            if (!$line->isClosed()) {
                return false;
            }
        }
        return true;
    }
}
