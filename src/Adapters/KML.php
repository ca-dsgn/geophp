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
 * PHP Geometry/KML encoder/decoder
 *
 * Mainly inspired/adapted from OpenLayers( http://www.openlayers.org )
 *   Openlayers/format/WKT.js
 *
 * @api
 * @psalm-immutable
 */
readonly class KML implements GeoAdapterInterface
{
    /**
     * Read KML string into geometry objects
     *
     * @param string $input A KML string
     */
    public function read(string $input): GeometryInterface
    {
        // Change to lower-case and strip all CDATA
        $text = mb_strtolower($input, mb_detect_encoding($input));
        $text = preg_replace('/<!\[cdata\[(.*?)\]\]>/s', '', $text);

        // Load into DOMDocument
        $xml = new \DOMDocument();
        /** @psalm-suppress ImpureMethodCall */
        if (@$xml->loadXML($text) === false) {
            throw new \RuntimeException("Invalid KML: " . $text);
        }

        return $this->geometryFromXML($xml) ?? throw new \RuntimeException("Invalid KML: " . $text);
    }

    /**
     * Serialize geometries into a KML string.
     *
     * @param GeometryInterface $geometry
     * @param string|null $namespace
     * @return string The KML string representation of the input geometries
     */
    public function write(GeometryInterface $geometry, string $namespace = null): string
    {
        $nss = $namespace ? $namespace . ':' : '';
        return $this->geometryToKML($geometry, $nss);
    }

    private function geometryFromXML(\DOMDocument $document): ?GeometryInterface
    {
        $geometries = [];

        /** @psalm-suppress ImpureMethodCall */
        $placemarkElements = $document->getElementsByTagName('placemark');
        if ($placemarkElements->length) {
            /** @psalm-suppress ImpureMethodCall */
            $placemarkElementsIterator = $placemarkElements->getIterator();
            foreach ($placemarkElementsIterator as $placemark) {
                /** @psalm-suppress ImpureMethodCall */
                $children = $placemark->childNodes->getIterator();
                foreach ($children as $child) {
                    $geometry = $this->parseNode($child);
                    if ($geometry !== null) {
                        $geometries[] = $geometry;
                    }
                }
            }
        } else {
            $geometry = $this->parseNode($document->documentElement);
            if ($geometry !== null) {
                $geometries[] = $geometry;
            }
        }

        return GeoPHP::geometryReduce($geometries);
    }

    private function parseNode(\DOMNode $element): ?GeometryInterface
    {
        $geometryTypes = GeoPHP::geometryList();

        // Node names are all the same, except for MultiGeometry, which maps to GeometryCollection
        $nodeName = $element->nodeName == 'multigeometry' ? 'geometrycollection' : $element->nodeName;
        if (!array_key_exists($nodeName, $geometryTypes)) {
            return null;

        }

        $geometryType = $geometryTypes[$nodeName];

        return match ($geometryType) {
            Point::class => $this->parsePoint($element),
            LineString::class => $this->parseLineString($element),
            Polygon::class => $this->parsePolygon($element),
            GeometryCollection::class => $this->parseGeometryCollection($element),
            default => null,
        };
    }

    /**
     * @return list<\DOMNode>
     */
    private function childElements(\DOMNode $xml, string $nodeName = ''): array
    {
        $elements = [];
        /** @psalm-suppress ImpureMethodCall */
        $children = $xml->childNodes->getIterator();
        foreach ($children as $child) {
            if ($child->nodeName === $nodeName) {
                $elements[] = $child;
            }
        }
        return $elements;
    }

    private function parsePoint(\DOMNode $xml): Point
    {
        $coordinates = $this->extractCoordinates($xml);
        if (!empty($coordinates)) {
            return new Point($coordinates[0][0], $coordinates[0][1]);
        } else {
            return new Point();
        }
    }

    private function parseLineString(\DOMNode $xml): LineString
    {
        $coordinates = $this->extractCoordinates($xml);
        $point_array = [];
        foreach ($coordinates as $set) {
            $point_array[] = new Point($set[0], $set[1]);
        }
        return new LineString($point_array);
    }

    private function parsePolygon(\DOMNode $xml): Polygon
    {
        $components = [];

        $outer_boundary_element_a = $this->childElements($xml, 'outerboundaryis');
        if (empty($outer_boundary_element_a)) {
            return new Polygon(); // It's an empty polygon
        }
        $outer_boundary_element = $outer_boundary_element_a[0];
        $outer_ring_element_a = $this->childElements($outer_boundary_element, 'linearring');
        $outer_ring_element = $outer_ring_element_a[0];
        $components[] = $this->parseLineString($outer_ring_element);

        if (count($components) != 1) {
            throw new \RuntimeException("Invalid KML");
        }

        $inner_boundary_element_a = $this->childElements($xml, 'innerboundaryis');
        if (count($inner_boundary_element_a)) {
            foreach ($inner_boundary_element_a as $inner_boundary_element) {
                foreach ($this->childElements($inner_boundary_element, 'linearring') as $inner_ring_element) {
                    $components[] = $this->parseLineString($inner_ring_element);
                }
            }
        }

        return new Polygon($components);
    }

    private function parseGeometryCollection(\DOMNode $xml): GeometryCollection
    {
        $components = [];
        $geomTypes = GeoPHP::geometryList();
        /** @psalm-suppress ImpureMethodCall */
        $children = $xml->childNodes->getIterator();
        foreach ($children as $child) {
            $nodeName = ($child->nodeName == 'linearring') ? 'linestring' : $child->nodeName;
            if (array_key_exists($nodeName, $geomTypes)) {
                $function = 'parse' . $geomTypes[$nodeName];
                $components[] = $this->$function($child);
            }
        }
        return new GeometryCollection($components);
    }

    /**
     * @psalm-return list<list{string, string, ...string}>
     */
    private function extractCoordinates(\DOMNode $xml): array
    {
        $coordinateElements = $this->childElements($xml, 'coordinates');
        $coordinates = [];
        if (count($coordinateElements)) {
            $coordinateSets = explode(' ', preg_replace('/[\r\n]+/', ' ', $coordinateElements[0]->nodeValue ?? ''));
            foreach ($coordinateSets as $set) {
                $set = trim($set);
                if ($set) {
                    $setArray = explode(',', $set);
                    if (count($setArray) >= 2) {
                        $coordinates[] = $setArray;
                    }
                }
            }
        }

        return $coordinates;
    }

    private function geometryToKML(GeometryInterface $geometry, string $nss): string
    {
        return match (true) {
            $geometry instanceof Point => $this->pointToKML($geometry, $nss),
            $geometry instanceof LineString => $this->lineStringToKML($geometry, $nss),
            $geometry instanceof Polygon => $this->polygonToKML($geometry, $nss),
            $geometry instanceof MultiPoint,
            $geometry instanceof MultiLineString,
            $geometry instanceof MultiPolygon,
            $geometry instanceof GeometryCollection => $this->collectionToKML($geometry, $nss),
            default => '',
        };
    }

    private function pointToKML(Point $geometry, string $nss): string
    {
        $out = '<' . $nss . 'Point>';
        if (!$geometry->isEmpty()) {
            $out .= '<' . $nss . 'coordinates>' . $geometry->getX() . "," . $geometry->getY() . '</' . $nss . 'coordinates>';
        }
        $out .= '</' . $nss . 'Point>';
        return $out;
    }

    private function lineStringToKML(LineString $geometry, string $nss, ?string $type = null): string
    {
        if ($type === null) {
            $type = $geometry->geometryType();
        }

        $str = '<' . $nss . $type . '>';

        if (!$geometry->isEmpty()) {
            $str .= '<' . $nss . 'coordinates>';
            $i = 0;
            foreach ($geometry->getComponents() as $comp) {
                if ($i != 0) {
                    $str .= ' ';
                }
                $str .= $comp->getX() . ',' . $comp->getY();
                $i++;
            }

            $str .= '</' . $nss . 'coordinates>';
        }

        $str .= '</' . $nss . $type . '>';

        return $str;
    }

    public function polygonToKML(Polygon $geometry, string $nss): string
    {
        $components = $geometry->getComponents();
        $str = '';
        if (!empty($components)) {
            $str = '<' . $nss . 'outerBoundaryIs>' . $this->lineStringToKML($components[0], $nss, 'LinearRing') . '</' . $nss . 'outerBoundaryIs>';
            foreach (array_slice($components, 1) as $component) {
                $str .= '<' . $nss . 'innerBoundaryIs>' . $this->lineStringToKML($component, $nss) . '</' . $nss . 'innerBoundaryIs>';
            }
        }

        return '<' . $nss . 'Polygon>' . $str . '</' . $nss . 'Polygon>';
    }

    public function collectionToKML(Collection $geometry, string $nss): string
    {
        $str = '<' . $nss . 'MultiGeometry>';
        foreach ($geometry->getComponents() as $component) {
            $sub_adapter = new KML();
            $str .= $sub_adapter->write($component);
        }

        return $str . '</' . $nss . 'MultiGeometry>';
    }
}
