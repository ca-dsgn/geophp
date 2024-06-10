<?php

namespace Tochka\GeoPHP\Adapters;

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
 * GeoJSON class : a geojson reader/writer.
 *
 * Note that it will always return a GeoJSON geometry. This
 * means that if you pass it a feature, it will return the
 * geometry of that feature strip everything else.
 *
 * @api
 */
class GeoJSON implements GeoAdapterInterface
{
    /**
     * Given an object or a string, return a Geometry
     *
     * @param string|object $input The GeoJSON string or object
     * @return GeometryInterface Geometry
     */
    public function read(string|object $input): GeometryInterface
    {
        if (is_string($input)) {
            $input = json_decode($input);
        }
        if (!is_object($input)) {
            throw new \RuntimeException('Invalid JSON');
        }
        if (!is_string($input->type)) {
            throw new \RuntimeException('Invalid JSON');
        }

        // Check to see if it's a FeatureCollection
        if ($input->type == 'FeatureCollection') {
            $geometries = [];
            foreach ($input->features as $feature) {
                $geometries[] = $this->read($feature);
            }

            return GeoPHP::geometryReduce($geometries) ?? throw new \RuntimeException('Invalid JSON');
        }

        // Check to see if it's a Feature
        if ($input->type == 'Feature') {
            return $this->read($input->geometry);
        }

        // It's a geometry - process it
        return $this->objectToGeometry($input);
    }

    private function objectToGeometry(object $object): GeometryInterface
    {
        return match ($object->type) {
            'GeometryCollection' => $this->objectToGeometryCollection($object),
            'Point' => $this->arrayToPoint($object->coordinates),
            'LineString' => $this->arrayToLineString($object->coordinates),
            'Polygon' => $this->arrayToPolygon($object->coordinates),
            'MultiPoint' => $this->arrayToMultiPoint($object->coordinates),
            'MultiLineString' => $this->arrayToMultiLineString($object->coordinates),
            'MultiPolygon' => $this->arrayToMultiPolygon($object->coordinates),
            default => throw new \RuntimeException('Invalid JSON'),
        };
    }

    private function arrayToPoint(array $array): Point
    {
        if (!empty($array)) {
            return new Point($array[0], $array[1]);
        } else {
            return new Point();
        }
    }

    private function arrayToLineString(array $array): LineString
    {
        $points = [];
        foreach ($array as $item) {
            $points[] = $this->arrayToPoint($item);
        }
        return new LineString($points);
    }

    private function arrayToPolygon(array $array): Polygon
    {
        $lines = [];
        foreach ($array as $item) {
            $lines[] = $this->arrayToLineString($item);
        }
        return new Polygon($lines);
    }

    private function arrayToMultiPoint(array $array): MultiPoint
    {
        $points = [];
        foreach ($array as $item) {
            $points[] = $this->arrayToPoint($item);
        }
        return new MultiPoint($points);
    }

    private function arrayToMultiLineString(array $array): MultiLineString
    {
        $lines = [];
        foreach ($array as $item) {
            $lines[] = $this->arrayToLineString($item);
        }
        return new MultiLineString($lines);
    }

    private function arrayToMultiPolygon(array $array): MultiPolygon
    {
        $polys = [];
        foreach ($array as $item) {
            $polys[] = $this->arrayToPolygon($item);
        }
        return new MultiPolygon($polys);
    }

    private function objectToGeometryCollection(object $object): GeometryCollection
    {
        $geometries = [];
        if (empty($object->geometries)) {
            throw new \RuntimeException('Invalid GeoJSON: GeometryCollection with no component geometries');
        }
        foreach ($object->geometries as $item) {
            $geometries[] = $this->objectToGeometry($item);
        }
        return new GeometryCollection($geometries);
    }

    /**
     * Serializes an object into a geojson string
     *
     * @param GeometryInterface $geometry The object to serialize
     * @return string|array The GeoJSON string
     */
    public function write(GeometryInterface $geometry, $return_array = false): string|array
    {
        $output = $this->getArray($geometry);
        if ($return_array) {
            return $output;
        }

        return json_encode($output);
    }

    private function getArray(GeometryInterface $geometry): array
    {
        if ($geometry instanceof GeometryCollection) {
            $result = [];
            foreach ($geometry->getComponents() as $component) {
                $result[] = [
                    'type' => $component->geometryType(),
                    'coordinates' => $component->asArray(),
                ];
            }
            return [
                'type' => 'GeometryCollection',
                'geometries' => $result,
            ];
        } else {
            return [
                'type' => $geometry->geometryType(),
                'coordinates' => $geometry->asArray(),
            ];
        }
    }
}
