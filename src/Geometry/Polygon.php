<?php

namespace Tochka\GeoPHP\Geometry;

use Tochka\GeoPHP\GeoPHP;

/**
 * Polygon: A polygon is a plane figure that is bounded by a closed path,
 * composed of a finite sequence of straight line segments
 * @api
 *
 * @extends Collection<LineString>
 */
class Polygon extends Collection
{
    public function geometryType(): string
    {
        return 'Polygon';
    }

    /**
     * The boundary of a polygin is it's outer ring
     */
    public function boundary(): GeometryInterface
    {
        return $this->exteriorRing();
    }

    public function getArea($exteriorOnly = false, $signed = false): float
    {
        if ($this->isEmpty()) {
            return 0;
        }

        if ($this->getGeos() && $exteriorOnly === false) {
            return $this->getGeos()->area();
        }

        $exteriorRing = $this->getComponents()[0];
        if (!$exteriorRing instanceof Collection) {
            return 0;
        }
        $pts = $exteriorRing->getComponents();

        $c = count($pts);
        if ($c === 0) {
            return 0;
        }
        $a = '0';
        foreach ($pts as $k => $p) {
            $j = ($k + 1) % $c;
            $a = $a + ($p->getX() * $pts[$j]->getY()) - ($p->getY() * $pts[$j]->getX());
        }

        if ($signed) {
            $area = ($a / 2);
        } else {
            $area = abs(($a / 2));
        }

        if ($exteriorOnly) {
            return $area;
        }
        foreach ($this->getComponents() as $delta => $component) {
            if ($delta != 0) {
                $innerPoly = new Polygon([$component]);
                $area -= $innerPoly->getArea();
            }
        }

        return $area;
    }

    public function getCentroid(): ?Point
    {
        if ($this->isEmpty()) {
            return null;
        }

        if ($this->getGeos()) {
            return GeoPHP::geosToGeometry($this->getGeos()->centroid());
        }

        $exteriorRing = $this->getComponents()[0];
        $pts = $exteriorRing->getComponents();

        $c = count($pts);
        if ($c === 0) {
            return null;
        }
        $x = 0;
        $y = 0;
        $a = $this->getArea(true, true);

        // If this is a polygon with no area. Just return the first point.
        if ($a == 0) {
            return $this->exteriorRing()->pointN(1);
        }

        foreach ($pts as $k => $p) {
            $j = ($k + 1) % $c;
            $P = ($p->getX() * $pts[$j]->getY()) - ($p->getY() * $pts[$j]->getX());
            $x = $x + ($p->getX() + $pts[$j]->getX()) * $P;
            $y = $y + ($p->getY() + $pts[$j]->getY()) * $P;
        }

        $x = $x / (6 * $a);
        $y = $y / (6 * $a);

        return new Point($x, $y);
    }

    /**
     * Find the outermost point from the centroid
     *
     * @returns Point The outermost point
     */
    public function outermostPoint(): ?Point
    {
        $centroid = $this->getCentroid();
        if ($centroid === null) {
            return null;
        }

        $maxLength = 0;
        $maxPoint = null;

        foreach ($this->getPoints() as $point) {
            $lineString = new LineString([$centroid, $point]);

            if ($lineString->length() > $maxLength) {
                $maxLength = $lineString->length();
                $maxPoint = $point;
            }
        }

        return $maxPoint;
    }

    public function exteriorRing(): GeometryInterface
    {
        if ($this->isEmpty()) {
            return new LineString();
        }
        return $this->getComponents()[0];
    }

    public function numInteriorRings(): int
    {
        if ($this->isEmpty()) {
            return 0;
        }
        return $this->numGeometries() - 1;
    }

    public function interiorRingN($n): ?GeometryInterface
    {
        return $this->geometryN($n + 1);
    }

    public function dimension(): int
    {
        if ($this->isEmpty()) {
            return 0;
        }

        return 2;
    }

    public function isSimple(): bool
    {
        if ($this->getGeos()) {
            return $this->getGeos()->isSimple();
        }

        $segments = $this->explode();

        foreach ($segments as $i => $segment) {
            foreach ($segments as $j => $checkSegment) {
                if ($i != $j) {
                    if ($segment->lineSegmentIntersect($checkSegment)) {
                        return false;
                    }
                }
            }
        }
        return true;
    }

    /**
     * For a given point, determine whether it's bounded by the given polygon.
     * Adapted from http://www.assemblysys.com/dataServices/php_pointinpolygon.php
     * @see http://en.wikipedia.org/wiki/Point%5Fin%5Fpolygon
     *
     * @param Point $point
     * @param boolean $pointOnBoundary - whether a boundary should be considered "in" or not
     * @param boolean $pointOnVertex - whether a vertex should be considered "in" or not
     * @return boolean
     */
    public function pointInPolygon(Point $point, bool $pointOnBoundary = true, bool $pointOnVertex = true): bool
    {
        $vertices = $this->getPoints();

        // Check if the point sits exactly on a vertex
        if ($this->pointOnVertex($point)) {
            return $pointOnVertex;
        }

        // Check if the point is inside the polygon or on the boundary
        $intersections = 0;
        $verticesCount = count($vertices);

        for ($i = 1; $i < $verticesCount; $i++) {
            $vertex1 = $vertices[$i - 1];
            $vertex2 = $vertices[$i];
            if ($vertex1->getY() == $vertex2->getY()
                && $vertex1->getY() == $point->getY()
                && $point->getX() > min($vertex1->getX(), $vertex2->getX())
                && $point->getX() < max($vertex1->getX(), $vertex2->getX())) {
                // Check if point is on an horizontal polygon boundary
                return $pointOnBoundary;
            }
            if ($point->getY() > min($vertex1->getY(), $vertex2->getY())
                && $point->getY() <= max($vertex1->getY(), $vertex2->getY())
                && $point->getX() <= max($vertex1->getX(), $vertex2->getX())
                && $vertex1->getY() != $vertex2->getY()) {
                $xinters =
                    ($point->getY() - $vertex1->getY()) * ($vertex2->getX() - $vertex1->getX())
                    / ($vertex2->getY() - $vertex1->getY())
                    + $vertex1->getX();
                if ($xinters == $point->getX()) {
                    // Check if point is on the polygon boundary (other than horizontal)
                    return $pointOnBoundary;
                }
                if ($vertex1->getX() == $vertex2->getX() || $point->getX() <= $xinters) {
                    $intersections++;
                }
            }
        }
        // If the number of edges we passed through is even, then it's in the polygon.
        if ($intersections % 2 != 0) {
            return true;
        } else {
            return false;
        }
    }

    public function pointOnVertex(Point $point): bool
    {
        foreach ($this->getPoints() as $vertex) {
            if ($point->equals($vertex)) {
                return true;
            }
        }

        return false;
    }


    // Not valid for this geometry type
    // --------------------------------
    public function length(): ?float
    {
        return null;
    }
}
