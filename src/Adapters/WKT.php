<?php

namespace Tochka\GeoPHP\Adapters;

use Tochka\GeoPHP\Geometry\GeometryInterface;
use Tochka\GeoPHP\Geometry\GeometryCollection;
use Tochka\GeoPHP\Geometry\LineString;
use Tochka\GeoPHP\Geometry\MultiLineString;
use Tochka\GeoPHP\Geometry\MultiPoint;
use Tochka\GeoPHP\Geometry\MultiPolygon;
use Tochka\GeoPHP\Geometry\Point;
use Tochka\GeoPHP\Geometry\Polygon;
use Tochka\GeoPHP\GeoPHP;

/**
 * WKT (Well Known Text) Adapter
 *
 * @api
 */
class WKT implements GeoAdapterInterface
{
    /**
     * Read WKT string into geometry objects
     *
     * @throws \Exception
     */
    public function read(string $input): GeometryInterface
    {
        $input = trim($input);

        // If it contains a ';', then it contains additional SRID data
        if (strpos($input, ';')) {
            $parts = explode(';', $input);
            $input = $parts[1];
            $eparts = explode('=', $parts[0]);
            $srid = (int) $eparts[1];
        } else {
            $srid = null;
        }

        // If geos is installed, then we take a shortcut and let it parse the WKT
        if (GeoPHP::geosInstalled()) {
            $reader = new \GEOSWKTReader();

            $geometry = GeoPHP::geosToGeometry($reader->read($input));
            if ($geometry === null) {
                throw new \RuntimeException('Error while read WKT');
            }
            if ($srid) {
                $geometry->setSRID($srid);
            }

            return $geometry;
        }
        $input = str_replace(', ', ',', $input);

        // For each geometry type, check to see if we have a match at the
        // beginning of the string. If we do, then parse using that type
        foreach (GeoPHP::geometryList() as $geometryType) {
            $wktGeometry = strtoupper($geometryType);
            if (strtoupper(substr($input, 0, strlen($wktGeometry))) === $wktGeometry) {
                $data = $this->getDataString($input);

                $geometry = match ($geometryType) {
                    Point::class => $this->parsePoint($data),
                    LineString::class => $this->parseLineString($data),
                    Polygon::class => $this->parsePolygon($data),
                    MultiPoint::class => $this->parseMultiPoint($data),
                    MultiLineString::class => $this->parseMultiLineString($data),
                    MultiPolygon::class => $this->parseMultiPolygon($data),
                    GeometryCollection::class => $this->parseGeometryCollection($data),
                };

                if ($srid) {
                    $geometry->setSRID($srid);
                }

                return $geometry;
            }
        }

        throw new \RuntimeException('Error while reading input');
    }

    private function parsePoint(string $data): Point
    {
        $data = $this->trimParens($data);

        // If it's marked as empty, then return an empty point
        if ($data == 'EMPTY') {
            return new Point();
        }

        $parts = explode(' ', $data);
        return new Point($parts[0], $parts[1]);
    }

    private function parseLineString(string $data): LineString
    {
        $data = $this->trimParens($data);

        // If it's marked as empty, then return an empty line
        if ($data == 'EMPTY') {
            return new LineString();
        }

        $parts = explode(',', $data);
        $points = [];
        foreach ($parts as $part) {
            $points[] = $this->parsePoint($part);
        }
        return new LineString($points);
    }

    private function parsePolygon(string $data): Polygon
    {
        $data = $this->trimParens($data);

        // If it's marked as empty, then return an empty polygon
        if ($data == 'EMPTY') {
            return new Polygon();
        }

        $parts = explode('),(', $data);
        $lines = [];
        foreach ($parts as $part) {
            if (!str_starts_with($part, '(')) {
                $part = '(' . $part;
            }
            if (!str_ends_with($part, ')')) {
                $part = $part . ')';
            }
            $lines[] = $this->parseLineString($part);
        }
        return new Polygon($lines);
    }

    private function parseMultiPoint(string $data): MultiPoint
    {
        $data = $this->trimParens($data);

        // If it's marked as empty, then return an empty MultiPoint
        if ($data == 'EMPTY') {
            return new MultiPoint();
        }

        $parts = explode(',', $data);
        $points = [];
        foreach ($parts as $part) {
            $points[] = $this->parsePoint($part);
        }
        return new MultiPoint($points);
    }

    private function parseMultiLineString(string $data): MultiLineString
    {
        $data = $this->trimParens($data);

        // If it's marked as empty, then return an empty multi-linestring
        if ($data == 'EMPTY') {
            return new MultiLineString();
        }

        $parts = explode('),(', $data);
        $lines = [];
        foreach ($parts as $part) {
            // Repair the string if the explode broke it
            if (!str_starts_with($part, '(')) {
                $part = '(' . $part;
            }
            if (!str_ends_with($part, ')')) {
                $part = $part . ')';
            }
            $lines[] = $this->parseLineString($part);
        }
        return new MultiLineString($lines);
    }

    private function parseMultiPolygon(string $data): MultiPolygon
    {
        $data = $this->trimParens($data);

        // If it's marked as empty, then return an empty multi-polygon
        if ($data == 'EMPTY') {
            return new MultiPolygon();
        }

        $parts = explode(')),((', $data);
        $polys = [];
        foreach ($parts as $part) {
            // Repair the string if the explode broke it
            if (!str_starts_with($part, '((')) {
                $part = '((' . $part;
            }
            if (!str_ends_with($part, '))')) {
                $part = $part . '))';
            }
            $polys[] = $this->parsePolygon($part);
        }
        return new MultiPolygon($polys);
    }

    /**
     * @throws \Exception
     */
    private function parseGeometryCollection(string $data): GeometryCollection
    {
        $data = $this->trimParens($data);

        // If it's marked as empty, then return an empty geom-collection
        if ($data == 'EMPTY') {
            return new GeometryCollection();
        }

        $geometries = [];
        $str = preg_replace('/,\s*([A-Za-z])/', '|$1', $data);
        $components = explode('|', trim($str));

        foreach ($components as $component) {
            $geometries[] = $this->read($component);
        }
        return new GeometryCollection($geometries);
    }

    protected function getDataString(string $wkt): string|false
    {
        $first_paren = strpos($wkt, '(');

        if ($first_paren !== false) {
            return substr($wkt, $first_paren);
        } elseif (str_contains($wkt, 'EMPTY')) {
            return 'EMPTY';
        } else {
            return false;
        }
    }

    /**
     * Trim the parenthesis and spaces
     */
    protected function trimParens(string $str): string
    {
        $str = trim($str);

        // We want to only strip off one set of parenthesis
        if (str_starts_with($str, '(')) {
            return substr($str, 1, -1);
        } else {
            return $str;
        }
    }

    /**
     * Serialize geometries into a WKT string.
     *
     * @param GeometryInterface $geometry
     *
     * @return string The WKT string representation of the input geometries
     *
     * @throws \Exception
     */
    public function write(GeometryInterface $geometry): string
    {
        // If geos is installed, then we take a shortcut and let it write the WKT
        if (GeoPHP::geosInstalled()) {
            $writer = new \GEOSWKTWriter();
            $writer->setTrim(true);
            return $writer->write($geometry->getGeos());
        }

        if ($geometry->isEmpty()) {
            return strtoupper($geometry->geometryType()) . ' EMPTY';
        }

        $data = $this->extractData($geometry);

        return strtoupper($geometry->geometryType()) . ' (' . $data . ')';
    }

    /**
     * Extract geometry to a WKT string
     *
     * @param GeometryInterface $geometry A Geometry object
     *
     * @return string
     */
    public function extractData(GeometryInterface $geometry): string
    {
        $parts = [];
        switch (true) {
            case $geometry instanceof Point:
                return $geometry->getX() . ' ' . $geometry->getY();
            case $geometry instanceof LineString:
                foreach ($geometry->getComponents() as $component) {
                    $parts[] = $this->extractData($component);
                }
                return implode(', ', $parts);
            case $geometry instanceof Polygon:
            case $geometry instanceof MultiPoint:
            case $geometry instanceof MultiLineString:
            case $geometry instanceof MultiPolygon:
                foreach ($geometry->getComponents() as $component) {
                    $parts[] = '(' . $this->extractData($component) . ')';
                }
                return implode(', ', $parts);
            case $geometry instanceof GeometryCollection:
                foreach ($geometry->getComponents() as $component) {
                    $parts[] = strtoupper($component->geometryType()) . ' (' . $this->extractData($component) . ')';
                }
                return implode(', ', $parts);
        }

        throw new \RuntimeException('Unknown geometry type for extract data');
    }
}
