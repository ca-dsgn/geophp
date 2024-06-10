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
 * PHP Geometry/GPX encoder/decoder
 *
 * @api
 * @psalm-immutable
 */
readonly class GPX implements GeoAdapterInterface
{
    /**
     * Read GPX string into geometry objects
     *
     * @param string $input A GPX string
     *
     * @return GeometryInterface
     */
    public function read(string $input): GeometryInterface
    {
        return $this->geometryFromText($input);
    }

    /**
     * Serialize geometries into a GPX string.
     *
     * @param GeometryInterface $geometry
     * @param string|null $namespace
     * @return string The GPX string representation of the input geometries
     */
    public function write(GeometryInterface $geometry, string $namespace = null): string
    {
        if ($geometry->isEmpty()) {
            return '';
        }

        $nss = $namespace !== null ? $namespace . ':' : '';
        return '<' . $nss . 'gpx creator="geoPHP" version="1.0">' . $this->geometryToGPX($geometry, $nss) . '</' . $nss . 'gpx>';
    }

    private function geometryFromText(string $text): GeometryInterface
    {
        // Change to lower-case and strip all CDATA
        $text = strtolower($text);
        $text = preg_replace('/<!\[cdata\[(.*?)\]\]>/s', '', $text);

        // Load into DOMDocument
        $xml = new \DOMDocument();
        /** @psalm-suppress ImpureMethodCall */
        if (@$xml->loadXML($text) === false) {
            throw new \RuntimeException("Invalid GPX: " . $text);
        }

        return $this->geometryFromXML($xml) ?? throw new \RuntimeException("Invalid GPX: " . $text);
    }

    private function geometryFromXML(\DOMDocument $document): ?GeometryInterface
    {
        $geometries = [];
        $geometries = array_merge($geometries, $this->parseWaypoints($document));
        $geometries = array_merge($geometries, $this->parseTracks($document));
        $geometries = array_merge($geometries, $this->parseRoutes($document));

        if (empty($geometries)) {
            throw new \RuntimeException("Invalid / Empty GPX");
        }

        return GeoPHP::geometryReduce($geometries);
    }

    private function childElements(\DOMElement $xml, string $nodeName): array
    {
        $result = [];
        /** @psalm-suppress ImpureMethodCall */
        $children = $xml->childNodes->getIterator();
        foreach ($children as $child) {
            if ($child->nodeName == $nodeName) {
                $result[] = $child;
            }
        }
        return $result;
    }

    /**
     * @return list<Point>
     */
    private function parseWaypoints(\DOMDocument $document): array
    {
        $points = [];
        /** @psalm-suppress ImpureMethodCall */
        $wptElements = $document->getElementsByTagName('wpt');
        /** @psalm-suppress ImpureMethodCall */
        $wptElementsIterator = $wptElements->getIterator();
        foreach ($wptElementsIterator as $wpt) {
            /** @psalm-suppress ImpureMethodCall */
            $lat = $wpt->attributes->getNamedItem('lat')?->nodeValue;
            /** @psalm-suppress ImpureMethodCall */
            $lon = $wpt->attributes->getNamedItem('lon')?->nodeValue;
            $points[] = new Point($lon, $lat);
        }
        return $points;
    }

    /**
     * @return list<LineString>
     */
    private function parseTracks(\DOMDocument $document): array
    {
        $lines = [];
        /** @psalm-suppress ImpureMethodCall */
        $trkElements = $document->getElementsByTagName('trk');
        /** @psalm-suppress ImpureMethodCall */
        $trkElementsIterator = $trkElements->getIterator();
        foreach ($trkElementsIterator as $trk) {
            $components = [];
            foreach ($this->childElements($trk, 'trkseg') as $trkseg) {
                foreach ($this->childElements($trkseg, 'trkpt') as $trkpt) {
                    $lat = $trkpt->attributes->getNamedItem('lat')->nodeValue;
                    $lon = $trkpt->attributes->getNamedItem('lon')->nodeValue;
                    $components[] = new Point($lon, $lat);
                }
            }
            if ($components) {
                $lines[] = new LineString($components);
            }
        }
        return $lines;
    }

    /**
     * @return list<LineString>
     */
    private function parseRoutes(\DOMDocument $document): array
    {
        $lines = [];
        /** @psalm-suppress ImpureMethodCall */
        $rteElements = $document->getElementsByTagName('rte');
        /** @psalm-suppress ImpureMethodCall */
        $rteElementsIterator = $rteElements->getIterator();
        foreach ($rteElementsIterator as $rte) {
            $components = [];
            foreach ($this->childElements($rte, 'rtept') as $rtept) {
                $lat = $rtept->attributes->getNamedItem('lat')->nodeValue;
                $lon = $rtept->attributes->getNamedItem('lon')->nodeValue;
                $components[] = new Point($lon, $lat);
            }

            $lines[] = new LineString($components);
        }
        return $lines;
    }

    private function geometryToGPX(GeometryInterface $geometry, string $nss): string
    {
        return match (true) {
            $geometry instanceof Point => $this->pointToGPX($geometry, $nss),
            $geometry instanceof LineString => $this->lineStringToGPX($geometry, $nss),
            $geometry instanceof Polygon,
            $geometry instanceof MultiPoint,
            $geometry instanceof MultiLineString,
            $geometry instanceof MultiPolygon,
            $geometry instanceof GeometryCollection => $this->collectionToGPX($geometry, $nss),
            default => throw new \RuntimeException('Unknown geometry type'),
        };
    }

    private function pointToGPX(Point $geometry, string $nss): string
    {
        return '<' . $nss . 'wpt lat="' . $geometry->getY() . '" lon="' . $geometry->getX() . '" />';
    }

    private function lineStringToGPX(LineString $geometry, string $nss): string
    {
        $gpx = '<' . $nss . 'trk><' . $nss . 'trkseg>';

        foreach ($geometry->getComponents() as $comp) {
            $gpx .= '<' . $nss . 'trkpt lat="' . $comp->getY() . '" lon="' . $comp->getX() . '" />';
        }

        $gpx .= '</' . $nss . 'trkseg></' . $nss . 'trk>';

        return $gpx;
    }

    private function collectionToGPX(Collection $geometry, string $nss): string
    {
        $output = '';
        foreach ($geometry->getComponents() as $component) {
            $output .= $this->geometryToGPX($component, $nss);
        }

        return $output;
    }
}
