<?php

namespace Tochka\GeoPHP\Geometry;

use Tochka\GeoPHP\GeoPHP;

/**
 * Collection: Abstract class for compound geometries
 *
 * A geometry is a collection if it is made up of other
 * component geometries. Therefore everything but a Point
 * is a Collection. For example a LingString is a collection
 * of Points. A Polygon is a collection of LineStrings etc.
 *
 * @api
 * @template-covariant TType of GeometryInterface
 */
abstract class Collection extends Geometry
{
    /**
     * @var list<TType>
     */
    private array $components = [];

    /**
     * @param list<TType> $components array of geometries
     */
    public function __construct(array $components = [])
    {
        foreach ($components as $component) {
            if ($component instanceof GeometryInterface) {
                $this->components[] = $component;
            } else {
                throw new \RuntimeException("Cannot create a collection with non-geometries");
            }
        }
    }

    /**
     * Returns Collection component geometries
     * @return list<TType>
     */
    public function getComponents(): array
    {
        return $this->components;
    }

    /*
     * inverts x and y coordinates
     * Useful for old data still using lng lat
     */
    public function invertxy(): void
    {
        foreach ($this->components as $component) {
            if(method_exists($component, 'invertxy')) {
                $component->invertxy();
            }
        }
    }

    /**
     * @throws \Exception
     */
    public function getCentroid(): ?Point
    {
        if ($this->isEmpty()) {
            return null;
        }

        if ($this->getGeos()) {
            $geosCentroid = $this->getGeos()->centroid();
            if ($geosCentroid->typeName() === 'Point') {
                return GeoPHP::geosToGeometry($this->getGeos()->centroid());
            }
        }

        // As a rough estimate, we say that the centroid of a colletion is the centroid of it's envelope
        // @@TODO: Make this the centroid of the convexHull
        // Note: Outside of polygons, geometryCollections and the trivial case of points, there is no standard on what a "centroid" is
        return $this->envelope()->getCentroid();
    }

    /**
     * @throws \Exception
     */
    public function getBBox(): ?BoundBox
    {
        if ($this->isEmpty()) {
            return null;
        }

        if ($this->getGeos()) {
            $envelope = $this->getGeos()->envelope();
            if ($envelope->typeName() === 'Point') {
                return GeoPHP::geosToGeometry($envelope)->getBBOX();
            }

            $geosRing = $envelope->exteriorRing();

            return new BoundBox(
                $geosRing->pointN(3)->getX(),
                $geosRing->pointN(1)->getX(),
                $geosRing->pointN(1)->getY(),
                $geosRing->pointN(3)->getY(),
            );
        }

        $maxX = 0;
        $maxY = 0;
        $minX = INF;
        $minY = INF;

        // Go through each component and get the max and min x and y
        foreach ($this->components as $component) {
            $componentBbox = $component->getBBox();

            // Do a check and replace on each boundary, slowly growing the bbox
            $maxX = max($componentBbox->maxX, $maxX);
            $maxY = max($componentBbox->maxY, $maxY);
            $minX = min($componentBbox->minX, $minX);
            $minY = min($componentBbox->minY, $minY);
        }

        return new BoundBox($minX, $maxX, $minY, $maxY);
    }

    public function asArray(): array
    {
        $array = [];
        foreach ($this->components as $component) {
            $array[] = $component->asArray();
        }
        return $array;
    }

    /**
     * @throws \Exception
     */
    public function getArea(): float
    {
        if ($this->getGeos()) {
            return $this->getGeos()->area();
        }

        $area = 0;
        foreach ($this->components as $component) {
            $area += $component->getArea();
        }
        return $area;
    }

    /**
     * By default, the boundary of a collection is the boundary of it's components
     * @throws \Exception
     */
    public function boundary(): ?GeometryInterface
    {
        if ($this->isEmpty()) {
            return new LineString();
        }

        if ($this->getGeos()) {
            return GeoPHP::geosToGeometry($this->getGeos()->boundary());
        }

        $componentsBoundaries = [];
        foreach ($this->components as $component) {
            $componentsBoundaries[] = $component->boundary();
        }

        return GeoPHP::geometryReduce(array_filter($componentsBoundaries));
    }

    public function numGeometries(): int
    {
        return count($this->components);
    }

    /**
     * Note that the standard is 1 based indexing
     * @psalm-return TType|null
     */
    public function geometryN(int $n): ?GeometryInterface
    {
        return $this->components[$n - 1] ?? null;
    }

    public function length(): ?float
    {
        $length = 0;
        foreach ($this->components as $component) {
            $length += $component->length();
        }

        return $length;
    }

    public function greatCircleLength(int $radius = 6378137): float
    {
        $length = 0;
        foreach ($this->components as $component) {
            $length += $component->greatCircleLength($radius);
        }
        return $length;
    }

    public function haversineLength(): float
    {
        $length = 0;
        foreach ($this->components as $component) {
            $length += $component->haversineLength();
        }
        return $length;
    }

    public function dimension(): int
    {
        $dimension = 0;
        foreach ($this->components as $component) {
            if ($component->dimension() > $dimension) {
                $dimension = $component->dimension();
            }
        }
        return $dimension;
    }

    // A collection is empty if it has no components OR all it's components are empty
    public function isEmpty(): bool
    {
        if (!count($this->components)) {
            return true;
        } else {
            foreach ($this->components as $component) {
                if (!$component->isEmpty()) {
                    return false;
                }
            }
            return true;
        }
    }

    public function numPoints(): int
    {
        $num = 0;
        foreach ($this->components as $component) {
            $num += $component->numPoints();
        }
        return $num;
    }

    /**
     * @return list<Point>
     */
    public function getPoints(): array
    {
        $points = [];
        foreach ($this->components as $component) {
            $points = array_merge($points, $component->getPoints());
        }
        return $points;
    }

    public function equals(GeometryInterface $geometry): bool
    {
        if ($this->getGeos()) {
            return $this->getGeos()->equals($geometry->getGeos());
        }

        // To test for equality we check to make sure that there is a matching point
        // in the other geometry for every point in this geometry.
        // This is slightly more strict than the standard, which
        // uses Within(A,B) = true and Within(B,A) = true
        // @@TODO: Eventually we could fix this by using some sort of simplification
        // method that strips redundant vertices (that are all in a row)

        $this_points = $this->getPoints();
        $other_points = $geometry->getPoints();

        // First do a check to make sure they have the same number of vertices
        if (count($this_points) != count($other_points)) {
            return false;
        }

        foreach ($this_points as $point) {
            $found_match = false;
            foreach ($other_points as $key => $test_point) {
                if ($point->equals($test_point)) {
                    $found_match = true;
                    unset($other_points[$key]);
                    break;
                }
            }
            if (!$found_match) {
                return false;
            }
        }

        // All points match, return TRUE
        return true;
    }

    public function isSimple(): bool
    {
        if ($this->getGeos()) {
            return $this->getGeos()->isSimple();
        }

        // A collection is simple if all it's components are simple
        foreach ($this->components as $component) {
            if (!$component->isSimple()) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<TType>|null
     */
    public function explode(): ?array
    {
        $parts = [];
        foreach ($this->components as $component) {
            $parts = array_merge($parts, $component->explode());
        }

        return $parts;
    }

    // Not valid for this geometry type
    // --------------------------------
    public function getX(): ?float
    {
        return null;
    }

    public function getY(): ?float
    {
        return null;
    }

    public function startPoint(): ?Point
    {
        return null;
    }
    public function endPoint(): ?Point
    {
        return null;
    }
    public function isRing(): bool
    {
        return false;
    }
    public function isClosed(): bool
    {
        return false;
    }
    public function pointN(int $n): ?Point
    {
        return null;
    }
    public function exteriorRing(): ?GeometryInterface
    {
        return null;
    }
    public function numInteriorRings(): ?int
    {
        return null;
    }
    public function interiorRingN(int $n): ?GeometryInterface
    {
        return null;
    }

    public function pointOnSurface(): ?GeometryInterface
    {
        return null;
    }
}
