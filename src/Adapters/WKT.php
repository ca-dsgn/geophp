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
 * @psalm-immutable
 */
readonly class WKT implements GeoAdapterInterface
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
        if (str_contains($input, ';')) {
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
            /** @psalm-suppress ImpureMethodCall */
            $geometry = GeoPHP::geosToGeometry($reader->read($input), $srid);
            if ($geometry === null) {
                throw new \RuntimeException('Error while read WKT');
            }

            return $geometry;
        }
        $input = str_replace(', ', ',', $input);

        // For each geometry type, check to see if we have a match at the
        // beginning of the string. If we do, then parse using that type
        foreach (GeoPHP::geometryList() as $geometryType => $geometryClass) {
            $wktGeometry = strtoupper($geometryType);
            if (strtoupper(substr($input, 0, strlen($wktGeometry))) === $wktGeometry) {
                continue;
            }

            $data = $this->getDataString($input);
            if ($data === false) {
                throw new \RuntimeException('Error while read WKT');
            }

            return match ($geometryClass) {
                Point::class => $this->parsePoint($data, $srid),
                LineString::class => $this->parseLineString($data, $srid),
                Polygon::class => $this->parsePolygon($data, $srid),
                MultiPoint::class => $this->parseMultiPoint($data, $srid),
                MultiLineString::class => $this->parseMultiLineString($data, $srid),
                MultiPolygon::class => $this->parseMultiPolygon($data, $srid),
                GeometryCollection::class => $this->parseGeometryCollection($data, $srid),
            };
        }

        throw new \RuntimeException('Error while reading input');
    }

    private function parsePoint(string $data, ?int $srid = null): Point
    {
        $data = $this->trimParens($data);

        // If it's marked as empty, then return an empty point
        if ($data == 'EMPTY') {
            return new Point(srid: $srid);
        }

        $parts = explode(' ', $data);
        return new Point($parts[0], $parts[1], srid: $srid);
    }

    private function parseLineString(string $data, ?int $srid = null): LineString
    {
        $data = $this->trimParens($data);

        // If it's marked as empty, then return an empty line
        if ($data == 'EMPTY') {
            return new LineString(srid: $srid);
        }

        $parts = explode(',', $data);
        $points = [];
        foreach ($parts as $part) {
            $points[] = $this->parsePoint($part);
        }
        return new LineString($points, srid: $srid);
    }

    private function parsePolygon(string $data, ?int $srid = null): Polygon
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
        return new Polygon($lines, srid: $srid);
    }

    private function parseMultiPoint(string $data, ?int $srid = null): MultiPoint
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
        return new MultiPoint($points, srid: $srid);
    }

    private function parseMultiLineString(string $data, ?int $srid = null): MultiLineString
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
        return new MultiLineString($lines, srid: $srid);
    }

    private function parseMultiPolygon(string $data, ?int $srid = null): MultiPolygon
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
        return new MultiPolygon($polys, srid: $srid);
    }

    /**
     * @throws \Exception
     */
    private function parseGeometryCollection(string $data, ?int $srid = null): GeometryCollection
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
        return new GeometryCollection($geometries, srid: $srid);
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
        if ($geometry->getGeos()) {
            $writer = new \GEOSWKTWriter();
            /** @psalm-suppress ImpureMethodCall */
            $writer->setTrim(true);
            /** @psalm-suppress ImpureMethodCall */
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
