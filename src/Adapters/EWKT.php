<?php

namespace Tochka\GeoPHP\Adapters;

use Tochka\GeoPHP\Geometry\GeometryInterface;

/**
 * EWKT (Extended Well Known Text) Adapter
 *
 * @api
 * @psalm-immutable
 */
readonly class EWKT extends WKT
{
    /**
     * Serialize geometries into an EWKT string.
     *
     * @param GeometryInterface $geometry
     *
     * @return string The Extended-WKT string representation of the input geometries
     */
    public function write(GeometryInterface $geometry): string
    {
        $srid = $geometry->getSRID();
        if ($srid !== null) {
            $wkt = 'SRID=' . $srid . ';';
            $wkt .= $geometry->out('wkt');
            return $wkt;
        } else {
            return $geometry->out('wkt');
        }
    }
}
