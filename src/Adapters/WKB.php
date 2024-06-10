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
 */
class WKB implements GeoAdapterInterface
{
    /**
     * Read WKB into geometry objects
     *
     * @param string $input Well-known-binary string
     * @param bool $isHexString If this is a hexedecimal string that is in need of packing
     */
    public function read(string $input, bool $isHexString = false): GeometryInterface
    {
        if ($isHexString) {
            $input = pack('H*', $input);
        }

        if (empty($input)) {
            throw new \RuntimeException('Cannot read empty WKB geometry. Found ' . gettype($input));
        }

        $memory = fopen('php://memory', 'r+');
        fwrite($memory, $input);
        fseek($memory, 0);

        $geometry = $this->getGeometry($memory);
        fclose($memory);

        return $geometry;
    }

    /**
     * @param resource $memory
     */
    private function getGeometry(&$memory): GeometryInterface
    {
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
            fread($memory, 4);
        }

        switch ($baseInfo['type']) {
            case 1:
                return $this->getPoint($memory, $dimension);
            case 2:
                return $this->getLineString($memory, $dimension);
            case 3:
                return $this->getPolygon($memory, $dimension);
            case 4:
                return $this->getMulti($memory, 'point');
            case 5:
                return $this->getMulti($memory, 'line');
            case 6:
                return $this->getMulti($memory, 'polygon');
            case 7:
                return $this->getMulti($memory, 'geometry');
        }

        throw new \RuntimeException('Error while reading input');
    }

    /**
     * @param resource $memory
     */
    private function getPoint(&$memory, int $dimension): Point
    {
        $pointCoords = unpack("d*", fread($memory, $dimension * 8));
        if (!empty($pointCoords)) {
            return new Point($pointCoords[1], $pointCoords[2]);
        }

        return new Point();
    }

    /**
     * @param resource $memory
     */
    private function getLineString(&$memory, int $dimension): LineString
    {
        // Get the number of points expected in this string out of the first 4 bytes
        $lineLength = unpack('L', fread($memory, 4));

        // Return an empty linestring if there is no line-length
        if (!$lineLength[1]) {
            return new LineString();
        }

        // Read the nubmer of points x2 (each point is two coords) into decimal-floats
        $lineCoords = unpack('d*', fread($memory, $lineLength[1] * $dimension * 8));

        // We have our coords, build up the linestring
        $components = [];
        $i = 1;
        $num_coords = count($lineCoords);
        while ($i <= $num_coords) {
            $components[] = new Point($lineCoords[$i], $lineCoords[$i + 1]);
            $i += 2;
        }
        return new LineString($components);
    }

    /**
     * @param resource $memory
     */
    private function getPolygon(&$memory, int $dimension): Polygon
    {
        // Get the number of linestring expected in this poly out of the first 4 bytes
        $polyLength = unpack('L', fread($memory, 4));

        $components = [];
        $i = 1;
        while ($i <= $polyLength[1]) {
            $components[] = $this->getLineString($memory, $dimension);
            $i++;
        }
        return new Polygon($components);
    }

    /**
     * @param resource $memory
     */
    private function getMulti(&$memory, string $type): MultiPoint|MultiLineString|MultiPolygon|GeometryCollection
    {
        // Get the number of items expected in this multi out of the first 4 bytes
        $multiLength = unpack('L', fread($memory, 4));

        $components = [];
        $i = 1;
        while ($i <= $multiLength[1]) {
            $components[] = $this->getGeometry($memory);
            $i++;
        }
        switch ($type) {
            case 'point':
                return new MultiPoint($components);
            case 'line':
                return new MultiLineString($components);
            case 'polygon':
                return new MultiPolygon($components);
            case 'geometry':
                return new GeometryCollection($components);
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
