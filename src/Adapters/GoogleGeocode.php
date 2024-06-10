<?php

namespace Tochka\GeoPHP\Adapters;

use Tochka\GeoPHP\Geometry\BoundBox;
use Tochka\GeoPHP\Geometry\CommonGeometryInterface;
use Tochka\GeoPHP\Geometry\GeometryInterface;
use Tochka\GeoPHP\Geometry\LineString;
use Tochka\GeoPHP\Geometry\MultiPoint;
use Tochka\GeoPHP\Geometry\MultiPolygon;
use Tochka\GeoPHP\Geometry\Point;
use Tochka\GeoPHP\Geometry\Polygon;

/**
 * PHP Google Geocoder Adapter
 *
 * @api
 */
class GoogleGeocode implements GeoAdapterInterface
{
    /**
     * Read an address string or array geometry objects
     *
     * @param string $input Address to geocode
     * @param string $returnType Type of Geometry to return. Can either be 'points' or 'bounds' (polygon)
     * @param CommonGeometryInterface|BoundBox|null $bounds Limit the search area to within this region. For example
     *                                by default geocoding "Cairo" will return the location of Cairo Egypt.
     *                                If you pass a polygon of illinois, it will return Cairo IL.
     * @param bool $returnMultiple - Return all results in a multipoint or multipolygon
     */
    public function read(string $input, string $returnType = 'point', CommonGeometryInterface|BoundBox|null $bounds = null, bool $returnMultiple = false): GeometryInterface
    {
        if ($bounds instanceof CommonGeometryInterface) {
            $bounds = $bounds->getBBox();
        }
        if ($bounds instanceof BoundBox) {
            $boundsString = '&bounds=' . $bounds->minY . ',' . $bounds->minX . '|' . $bounds->maxY . ',' . $bounds->maxX;
        } else {
            $boundsString = '';
        }

        $url = "http://maps.googleapis.com/maps/api/geocode/json";
        $url .= '?address=' . urlencode($input);
        $url .= $boundsString;
        $url .= '&sensor=false';
        $result = json_decode(@file_get_contents($url));

        if ($result->status == 'OK') {
            if (!$returnMultiple) {
                if ($returnType === 'point') {
                    return $this->getPoint($result->results[0]);
                }
                if ($returnType === 'bounds' || $returnType === 'polygon') {
                    return $this->getPolygon($result->results[0]);
                }
            } else {
                if ($returnType === 'point') {
                    $points = [];
                    foreach ($result->results as $item) {
                        $points[] = $this->getPoint($item);
                    }
                    return new MultiPoint($points);
                }
                if ($returnType === 'bounds' || $returnType === 'polygon') {
                    $polygons = [];
                    foreach ($result->results as $item) {
                        $polygons[] = $this->getPolygon($item);
                    }
                    return new MultiPolygon($polygons);
                }
            }

            throw new \RuntimeException('Unknown ReturnType: ' . $returnType);
        } else {
            if ($result->status) {
                throw new \RuntimeException('Error in Google Geocoder: ' . $result->status);
            } else {
                throw new \RuntimeException('Unknown error in Google Geocoder');
            }
        }
    }

    /**
     * Serialize geometries into a WKT string.
     *
     * @param GeometryInterface $geometry
     *
     * @return string Does a reverse geocode of the geometry
     */
    public function write(GeometryInterface $geometry): string
    {
        $centroid = $geometry->getCentroid();
        $lat = $centroid->getY();
        $lon = $centroid->getX();

        $url = "http://maps.googleapis.com/maps/api/geocode/json";
        $url .= '?latlng=' . $lat . ',' . $lon;
        $url .= '&sensor=false';
        $result = json_decode(@file_get_contents($url));

        if ($result->status == 'OK') {
            return $result->results[0]->formatted_address;
        } elseif ($result->status == 'ZERO_RESULTS') {
            return '';
        } else {
            if ($result->status) {
                throw new \RuntimeException('Error in Google Reverse Geocoder: ' . $result->status);
            } else {
                throw new \RuntimeException('Unknown error in Google Reverse Geocoder');
            }
        }
    }

    private function getPoint(object $result): Point
    {
        $lat = $result->geometry->location->lat;
        $lon = $result->geometry->location->lng;
        return new Point($lon, $lat);
    }

    private function getPolygon(object $result): Polygon
    {
        $points = [
            $this->getTopLeft($result),
            $this->getTopRight($result),
            $this->getBottomRight($result),
            $this->getBottomLeft($result),
            $this->getTopLeft($result),
        ];
        $outerRing = new LineString($points);
        return new Polygon([$outerRing]);
    }

    private function getTopLeft(object $result): Point
    {
        $lat = $result->geometry->bounds->northeast->lat;
        $lon = $result->geometry->bounds->southwest->lng;
        return new Point($lon, $lat);
    }

    private function getTopRight(object $result): Point
    {
        $lat = $result->geometry->bounds->northeast->lat;
        $lon = $result->geometry->bounds->northeast->lng;
        return new Point($lon, $lat);
    }

    private function getBottomLeft(object $result): Point
    {
        $lat = $result->geometry->bounds->southwest->lat;
        $lon = $result->geometry->bounds->southwest->lng;
        return new Point($lon, $lat);
    }

    private function getBottomRight(object $result): Point
    {
        $lat = $result->geometry->bounds->southwest->lat;
        $lon = $result->geometry->bounds->northeast->lng;
        return new Point($lon, $lat);
    }
}
