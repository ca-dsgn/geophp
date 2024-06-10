<?php

namespace Tochka\GeoPHP\Adapters;

use Tochka\GeoPHP\Geometry\Collection;
use Tochka\GeoPHP\Geometry\GeometryInterface;
use Tochka\GeoPHP\Geometry\GeometryCollection;
use Tochka\GeoPHP\Geometry\LineString;
use Tochka\GeoPHP\Geometry\MultiLineString;
use Tochka\GeoPHP\Geometry\MultiPoint;
use Tochka\GeoPHP\Geometry\MultiPolygon;
use Tochka\GeoPHP\Geometry\Point;
use Tochka\GeoPHP\Geometry\Polygon;

/**
 * @api
 * @psalm-immutable
 */
readonly class WKB implements GeoAdapterInterface
{
    /**
     * Read WKB into geometry objects
     *
     * @param string $input Well-known-binary string
     * @param bool $isHexString If this is a hexedecimal string that is in need of packing
     */
    public function read(string $input, bool $isHexString = false, ?\GEOSGeometry $geos = null, ?int $srid = null): GeometryInterface
    {
        if ($isHexString) {
            $input = pack('H*', $input);
        }

        if (empty($input)) {
            throw new \RuntimeException('Cannot read empty WKB geometry. Found ' . gettype($input));
        }

        /** @psalm-suppress ImpureFunctionCall */
        $memory = fopen('php://memory', 'r+');
        /** @psalm-suppress ImpureFunctionCall */
        fwrite($memory, $input);
        /** @psalm-suppress ImpureFunctionCall */
        fseek($memory, 0);

        $geometry = $this->getGeometry($memory, $geos, $srid);
        /** @psalm-suppress ImpureFunctionCall */
        fclose($memory);

        return $geometry;
    }

    /**
     * @param resource $memory
     */
    private function getGeometry(&$memory, ?\GEOSGeometry $geos = null, ?int $srid = null): GeometryInterface
    {
        /** @psalm-suppress ImpureFunctionCall */
        $baseInfo = unpack("corder/ctype/cz/cm/cs", fread($memory, 5));
        if ($baseInfo['order'] !== 1) {
            throw new \RuntimeException('Only NDR (little endian) SKB format is supported at the moment');
        }

        $dimension = 2;
        if ($baseInfo['z']) {
            $dimension++;
        }
        if ($baseInfo['m']) {
            $dimension++;
        }

        // If there is SRID information, ignore it - use EWKB Adapter to get SRID support
        if ($baseInfo['s']) {
            /** @psalm-suppress ImpureFunctionCall */
            fread($memory, 4);
        }

        switch ($baseInfo['type']) {
            case 1:
                return $this->getPoint($memory, $dimension, $geos, $srid);
            case 2:
                return $this->getLineString($memory, $dimension, $geos, $srid);
            case 3:
                return $this->getPolygon($memory, $dimension, $geos, $srid);
            case 4:
                return $this->getMulti($memory, 'point', $geos, $srid);
            case 5:
                return $this->getMulti($memory, 'line', $geos, $srid);
            case 6:
                return $this->getMulti($memory, 'polygon', $geos, $srid);
            case 7:
                return $this->getMulti($memory, 'geometry', $geos, $srid);
        }

        throw new \RuntimeException('Error while reading input');
    }

    /**
     * @param resource $memory
     */
    private function getPoint(&$memory, int $dimension, ?\GEOSGeometry $geos = null, ?int $srid = null): Point
    {
        /** @psalm-suppress ImpureFunctionCall */
        $pointCoords = unpack("d*", fread($memory, $dimension * 8));
        /** @psalm-suppress RiskyTruthyFalsyComparison */
        if (!empty($pointCoords)) {
            return new Point($pointCoords[1], $pointCoords[2], $geos, $srid);
        }

        return new Point(geos: $geos, srid: $srid);
    }

    /**
     * @param resource $memory
     */
    private function getLineString(&$memory, int $dimension, ?\GEOSGeometry $geos = null, ?int $srid = null): LineString
    {
        // Get the number of points expected in this string out of the first 4 bytes
        /** @psalm-suppress ImpureFunctionCall */
        $lineLength = unpack('L', fread($memory, 4));

        // Return an empty linestring if there is no line-length
        if (!$lineLength[1]) {
            return new LineString(geos: $geos, srid: $srid);
        }

        // Read the nubmer of points x2 (each point is two coords) into decimal-floats
        /** @psalm-suppress ImpureFunctionCall */
        $lineCoords = unpack('d*', fread($memory, $lineLength[1] * $dimension * 8));

        // We have our coords, build up the linestring
        $components = [];
        $i = 1;
        $num_coords = count($lineCoords);
        while ($i <= $num_coords) {
            $components[] = new Point($lineCoords[$i], $lineCoords[$i + 1]);
            $i += 2;
        }
        return new LineString($components, $geos, $srid);
    }

    /**
     * @param resource $memory
     */
    private function getPolygon(&$memory, int $dimension, ?\GEOSGeometry $geos = null, ?int $srid = null): Polygon
    {
        // Get the number of linestring expected in this poly out of the first 4 bytes
        /** @psalm-suppress ImpureFunctionCall */
        $polyLength = unpack('L', fread($memory, 4));

        $components = [];
        $i = 1;
        while ($i <= $polyLength[1]) {
            $components[] = $this->getLineString($memory, $dimension);
            $i++;
        }
        return new Polygon($components, $geos, $srid);
    }

    /**
     * @param resource $memory
     */
    private function getMulti(&$memory, string $type, ?\GEOSGeometry $geos = null, ?int $srid = null): MultiPoint|MultiLineString|MultiPolygon|GeometryCollection
    {
        // Get the number of items expected in this multi out of the first 4 bytes
        /** @psalm-suppress ImpureFunctionCall */
        $multiLength = unpack('L', fread($memory, 4));

        $components = [];
        $i = 1;
        while ($i <= $multiLength[1]) {
            $components[] = $this->getGeometry($memory);
            $i++;
        }
        switch ($type) {
            case 'point':
                /** @var list<Point> $components */
                return new MultiPoint($components, $geos, $srid);
            case 'line':
                /** @var list<LineString> $components */
                return new MultiLineString($components, $geos, $srid);
            case 'polygon':
                /** @var list<Polygon> $components */
                return new MultiPolygon($components, $geos, $srid);
            case 'geometry':
                return new GeometryCollection($components, $geos, $srid);
        }

        throw new \RuntimeException('Unknown multi type');
    }

    /**
     * Serialize geometries into WKB string.
     *
     * @param GeometryInterface $geometry
     *
     * @return string The WKB string representation of the input geometries
     */
    public function write(GeometryInterface $geometry, bool $write_as_hex = false): string
    {
        // We always write into NDR (little endian)
        $wkb = pack('c', 1);

        switch (true) {
            case $geometry instanceof Point:
                $wkb .= pack('L', 1);
                $wkb .= $this->writePoint($geometry);
                break;
            case $geometry instanceof LineString:
                $wkb .= pack('L', 2);
                $wkb .= $this->writeLineString($geometry);
                break;
            case $geometry instanceof Polygon:
                $wkb .= pack('L', 3);
                $wkb .= $this->writePolygon($geometry);
                break;
            case $geometry instanceof MultiPoint:
                $wkb .= pack('L', 4);
                $wkb .= $this->writeMulti($geometry);
                break;
            case $geometry instanceof MultiLineString:
                $wkb .= pack('L', 5);
                $wkb .= $this->writeMulti($geometry);
                break;
            case $geometry instanceof MultiPolygon:
                $wkb .= pack('L', 6);
                $wkb .= $this->writeMulti($geometry);
                break;
            case $geometry instanceof GeometryCollection:
                $wkb .= pack('L', 7);
                $wkb .= $this->writeMulti($geometry);
                break;
        }

        if ($write_as_hex) {
            $unpacked = unpack('H*', $wkb);
            return $unpacked[1];
        } else {
            return $wkb;
        }
    }

    private function writePoint(Point $point): string
    {
        // Set the coords
        if (!$point->isEmpty()) {
            return pack('dd', $point->getX(), $point->getY());
        } else {
            return '';
        }
    }

    private function writeLineString(LineString $line): string
    {
        // Set the number of points in this line
        $wkb = pack('L', $line->numPoints());

        // Set the coords
        foreach ($line->getComponents() as $point) {
            $wkb .= pack('dd', $point->getX(), $point->getY());
        }

        return $wkb;
    }

    private function writePolygon(Polygon $polygon): string
    {
        // Set the number of lines in this poly
        $wkb = pack('L', $polygon->numGeometries());

        // Write the lines
        foreach ($polygon->getComponents() as $line) {
            $wkb .= $this->writeLineString($line);
        }

        return $wkb;
    }

    private function writeMulti(Collection $geometry): string
    {
        // Set the number of components
        $wkb = pack('L', $geometry->numGeometries());

        // Write the components
        foreach ($geometry->getComponents() as $component) {
            $wkb .= $this->write($component);
        }

        return $wkb;
    }
}
