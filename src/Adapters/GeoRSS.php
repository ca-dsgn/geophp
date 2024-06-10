<?php

namespace Tochka\GeoPHP\Adapters;

use Tochka\GeoPHP\Geometry\Collection;
use Tochka\GeoPHP\Geometry\GeometryCollection;
use Tochka\GeoPHP\Geometry\GeometryInterface;
use Tochka\GeoPHP\Geometry\LineString;
use Tochka\GeoPHP\Geometry\MultiLineString;
use Tochka\GeoPHP\Geometry\MultiPoint;
use Tochka\GeoPHP\Geometry\MultiPolygon;
use Tochka\GeoPHP\Geometry\Point;
use Tochka\GeoPHP\Geometry\Polygon;
use Tochka\GeoPHP\GeoPHP;

/**
 * PHP Geometry/GeoRSS encoder/decoder
 * @api
 * @psalm-immutable
 */
readonly class GeoRSS implements GeoAdapterInterface
{
    /**
     * Read GeoRSS string into geometry objects
     *
     * @param string $input - an XML feed containing geoRSS
     */
    public function read(string $input): GeometryInterface
    {
        return $this->geometryFromText($input);
    }

    /**
     * Serialize geometries into a GeoRSS string.
     *
     * @param GeometryInterface $geometry
     * @param string|null $namespace
     * @return string The georss string representation of the input geometries
     */
    public function write(GeometryInterface $geometry, ?string $namespace = null): string
    {
        $nss = $namespace ? $namespace . ':' : '';
        return $this->geometryToGeoRSS($geometry, $nss);
    }

    private function geometryFromText($text): GeometryInterface
    {
        // Change to lower-case, strip all CDATA, and de-namespace
        $text = strtolower($text);
        $text = preg_replace('/<!\[cdata\[(.*?)\]\]>/s', '', $text);

        // Load into DOMDocument
        $xml = new \DOMDocument();
        /** @psalm-suppress ImpureMethodCall */
        if (@$xml->loadXML($text) === false) {
            throw new \RuntimeException("Invalid GeoRSS: " . $text);
        }

        return $this->geometryFromXML($xml) ?? throw new \RuntimeException("Invalid GeoRSS: " . $text);
    }

    private function geometryFromXML(\DOMDocument $document): ?GeometryInterface
    {
        $geometries = [];
        $geometries = array_merge($geometries, $this->parsePoints($document));
        $geometries = array_merge($geometries, $this->parseLines($document));
        $geometries = array_merge($geometries, $this->parsePolygons($document));
        $geometries = array_merge($geometries, $this->parseBoxes($document));
        $geometries = array_merge($geometries, $this->parseCircles($document));

        if (empty($geometries)) {
            throw new \RuntimeException("Invalid / Empty GeoRSS");
        }

        return GeoPHP::geometryReduce($geometries);
    }

    /**
     * @return list<Point>
     */
    private function getPointsFromCoords(string $string): array
    {
        $coords = [];

        if (empty($string)) {
            return $coords;
        }

        $lat = 0;
        $coordinates = explode(' ', $string);
        foreach ($coordinates as $key => $item) {
            if (!($key % 2)) {
                // It's a latitude
                $lat = $item;
            } else {
                // It's a longitude
                $lon = $item;
                $coords[] = new Point($lon, $lat);
            }
        }
        return $coords;
    }

    /**
     * @return list<Point>
     */
    private function parsePoints(\DOMDocument $document): array
    {
        $points = [];
        /** @psalm-suppress ImpureMethodCall */
        $pointElements = $document->getElementsByTagName('point');
        /** @psalm-suppress ImpureMethodCall */
        $pointElementsIterator = $pointElements->getIterator();
        foreach ($pointElementsIterator as $point) {
            /** @psalm-suppress ImpureMethodCall */
            if ($point->hasChildNodes()) {
                $pointArray = $this->getPointsFromCoords(trim($point->firstChild->nodeValue ?? ''));
            }
            if (!empty($pointArray)) {
                $points[] = $pointArray[0];
            } else {
                $points[] = new Point();
            }
        }
        return $points;
    }

    /**
     * @return list<LineString>
     */
    private function parseLines(\DOMDocument $document): array
    {
        $lines = [];
        /** @psalm-suppress ImpureMethodCall */
        $lineElements = $document->getElementsByTagName('line');
        /** @psalm-suppress ImpureMethodCall */
        $lineElementsIterator = $lineElements->getIterator();
        foreach ($lineElementsIterator as $line) {
            $components = $this->getPointsFromCoords(trim($line->firstChild->nodeValue ?? ''));
            $lines[] = new LineString($components);
        }
        return $lines;
    }

    /**
     * @return list<Polygon>
     */
    private function parsePolygons(\DOMDocument $document): array
    {
        $polygons = [];
        /** @psalm-suppress ImpureMethodCall */
        $polyElements = $document->getElementsByTagName('polygon');
        /** @psalm-suppress ImpureMethodCall */
        $polyElementsIterator = $polyElements->getIterator();
        foreach ($polyElementsIterator as $poly) {
            /** @psalm-suppress ImpureMethodCall */
            if ($poly->hasChildNodes()) {
                $points = $this->getPointsFromCoords(trim($poly->firstChild->nodeValue ?? ''));
                $exteriorRing = new LineString($points);
                $polygons[] = new Polygon([$exteriorRing]);
            } else {
                // It's an EMPTY polygon
                $polygons[] = new Polygon();
            }
        }
        return $polygons;
    }

    /**
     * Boxes are rendered into polygons
     *
     * @return list<Polygon>
     */
    private function parseBoxes(\DOMDocument $document): array
    {
        $polygons = [];
        /** @psalm-suppress ImpureMethodCall */
        $boxElements = $document->getElementsByTagName('box');
        /** @psalm-suppress ImpureMethodCall */
        $boxElementsIterator = $boxElements->getIterator();
        foreach ($boxElementsIterator as $box) {
            $parts = explode(' ', trim($box->firstChild->nodeValue ?? ''));
            $components = [
                new Point($parts[3], $parts[2]),
                new Point($parts[3], $parts[0]),
                new Point($parts[1], $parts[0]),
                new Point($parts[1], $parts[2]),
                new Point($parts[3], $parts[2]),
            ];
            $exteriorRing = new LineString($components);
            $polygons[] = new Polygon([$exteriorRing]);
        }
        return $polygons;
    }

    /**
     * Circles are rendered into points
     * @TODO: Add good support once we have circular-string geometry support
     *
     * @return list<Point>
     */
    private function parseCircles(\DOMDocument $document): array
    {
        $points = [];
        /** @psalm-suppress ImpureMethodCall */
        $circleElements = $document->getElementsByTagName('circle');
        /** @psalm-suppress ImpureMethodCall */
        $circleElementsIterator = $circleElements->getIterator();
        foreach ($circleElementsIterator as $circle) {
            $parts = explode(' ', trim($circle->firstChild->nodeValue ?? ''));
            $points[] = new Point($parts[1], $parts[0]);
        }

        return $points;
    }

    private function geometryToGeoRSS(GeometryInterface $geometry, string $nss): string
    {
        return match (true) {
            $geometry instanceof Point => $this->pointToGeoRSS($geometry, $nss),
            $geometry instanceof LineString => $this->lineStringToGeoRSS($geometry, $nss),
            $geometry instanceof Polygon => $this->polygonToGeoRSS($geometry, $nss),
            $geometry instanceof MultiPoint,
            $geometry instanceof MultiLineString,
            $geometry instanceof MultiPolygon,
            $geometry instanceof GeometryCollection => $this->collectionToGeoRSS($geometry, $nss),
            default => '',
        };
    }

    private function pointToGeoRSS(Point $geometry, string $nss): string
    {
        $out = '<' . $nss . 'point>';
        if (!$geometry->isEmpty()) {
            $out .= $geometry->getY() . ' ' . $geometry->getX();
        }
        $out .= '</' . $nss . 'point>';
        return $out;
    }

    private function lineStringToGeoRSS(LineString $geometry, string $nss): string
    {
        $output = '<' . $nss . 'line>';
        foreach ($geometry->getComponents() as $k => $point) {
            $output .= $point->getY() . ' ' . $point->getX();
            if ($k < ($geometry->numGeometries() - 1)) {
                $output .= ' ';
            }
        }
        $output .= '</' . $nss . 'line>';
        return $output;
    }

    private function polygonToGeoRSS(Polygon $geometry, string $nss): string
    {
        $output = '<' . $nss . 'polygon>';
        $exteriorRing = $geometry->exteriorRing();
        foreach ($exteriorRing->getComponents() as $k => $point) {
            $output .= $point->getY() . ' ' . $point->getX();
            if ($k < ($exteriorRing->numGeometries() - 1)) {
                $output .= ' ';
            }
        }
        $output .= '</' . $nss . 'polygon>';
        return $output;
    }

    public function collectionToGeoRSS(Collection $geometry, string $nss): string
    {
        $output = '<' . $nss . 'where>';
        foreach ($geometry->getComponents() as $comp) {
            $output .= $this->geometryToGeoRSS($comp, $nss);
        }

        $output .= '</' . $nss . 'where>';

        return $output;
    }
}
