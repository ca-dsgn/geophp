<?php

namespace Tochka\GeoPHP\Geometry;

/**
 * LineString. A collection of Points representing a line.
 * A line can have more than one segment.
 * @api
 * @extends Collection<Point>
 */
class LineString extends Collection
{
    /**
     * Constructor
     *
     * @param list<Point> $points An array of at least two points with
     * which to build the LineString
     */
    public function __construct(array $points = [])
    {
        if (count($points) == 1) {
            throw new \RuntimeException("Cannot construct a LineString with a single point");
        }

        // Call the Collection constructor to build the LineString
        parent::__construct($points);
    }

    // The boundary of a linestring is itself
    public function boundary(): GeometryInterface
    {
        return $this;
    }

    public function startPoint(): ?Point
    {
        return $this->pointN(1);
    }

    public function endPoint(): ?Point
    {
        return $this->pointN($this->numPoints());
    }

    public function isClosed(): bool
    {
        return ($this->startPoint()->equals($this->endPoint()));
    }

    public function isRing(): bool
    {
        return ($this->isClosed() && $this->isSimple());
    }

    public function numPoints(): int
    {
        return $this->numGeometries();
    }

    public function pointN(int $n): ?Point
    {
        return $this->geometryN($n);
    }

    public function dimension(): int
    {
        if ($this->isEmpty()) {
            return 0;
        }
        return 1;
    }

    public function area(): float
    {
        return 0;
    }

    public function length(): float
    {
        if ($this->getGeos()) {
            return $this->getGeos()->length();
        }

        $length = 0;
        foreach ($this->getPoints() as $delta => $point) {
            $previous_point = $this->geometryN($delta);
            if ($previous_point) {
                $length += sqrt(pow(($previous_point->getX() - $point->getX()), 2) + pow(($previous_point->getY() - $point->getY()), 2));
            }
        }
        return $length;
    }

    public function greatCircleLength(int $radius = 6378137): float
    {
        $length = 0;
        $points = $this->getPoints();
        for($i = 0; $i < $this->numPoints() - 1; $i++) {
            $point = $points[$i];
            $next_point = $points[$i + 1];
            if (!is_object($next_point)) {
                continue;
            }
            // Great circle method
            $lat1 = deg2rad($point->getY());
            $lat2 = deg2rad($next_point->getY());
            $lon1 = deg2rad($point->getX());
            $lon2 = deg2rad($next_point->getX());
            $dlon = $lon2 - $lon1;
            $length +=
              $radius *
                atan2(
                    sqrt(
                        pow(cos($lat2) * sin($dlon), 2) +
                        pow(cos($lat1) * sin($lat2) - sin($lat1) * cos($lat2) * cos($dlon), 2),
                    ),
                    sin($lat1) * sin($lat2) +
                    cos($lat1) * cos($lat2) * cos($dlon),
                );
        }
        // Returns length in meters.
        return $length;
    }

    public function haversineLength(): float
    {
        $degrees = 0;
        $points = $this->getPoints();
        for($i = 0; $i < $this->numPoints() - 1; $i++) {
            $point = $points[$i];
            $next_point = $points[$i + 1];
            if (!is_object($next_point)) {
                continue;
            }
            $degree = rad2deg(
                acos(
                    sin(deg2rad($point->getY())) * sin(deg2rad($next_point->getY())) +
                    cos(deg2rad($point->getY())) * cos(deg2rad($next_point->getY())) *
                      cos(deg2rad(abs($point->getX() - $next_point->getX()))),
                ),
            );
            $degrees += $degree;
        }
        // Returns degrees
        return $degrees;
    }

    /**
     * @return list<LineString>
     */
    public function explode(): array
    {
        $parts = [];
        $points = $this->getPoints();

        foreach ($points as $i => $point) {
            if (isset($points[$i + 1])) {
                $parts[] = new LineString([$point, $points[$i + 1]]);
            }
        }
        return $parts;
    }

    public function isSimple(): bool
    {
        if ($this->getGeos()) {
            return $this->getGeos()->isSimple();
        }

        $segments = $this->explode();

        foreach ($segments as $i => $segment) {
            foreach ($segments as $j => $check_segment) {
                if ($i != $j) {
                    if ($segment->lineSegmentIntersect($check_segment)) {
                        return false;
                    }
                }
            }
        }
        return true;
    }

    /**
     * Utility function to check if any line sigments intersect
     * Derived from http://stackoverflow.com/questions/563198/how-do-you-detect-where-two-line-segments-intersect
     */
    public function lineSegmentIntersect(LineString $segment): bool
    {
        $p0_x = $this->startPoint()->getX();
        $p0_y = $this->startPoint()->getY();
        $p1_x = $this->endPoint()->getX();
        $p1_y = $this->endPoint()->getY();
        $p2_x = $segment->startPoint()->getX();
        $p2_y = $segment->startPoint()->getY();
        $p3_x = $segment->endPoint()->getX();
        $p3_y = $segment->endPoint()->getY();

        $s1_x = $p1_x - $p0_x;
        $s1_y = $p1_y - $p0_y;
        $s2_x = $p3_x - $p2_x;
        $s2_y = $p3_y - $p2_y;

        $fps = (-$s2_x * $s1_y) + ($s1_x * $s2_y);
        $fpt = (-$s2_x * $s1_y) + ($s1_x * $s2_y);

        if ($fps == 0 || $fpt == 0) {
            return false;
        }

        $s = (-$s1_y * ($p0_x - $p2_x) + $s1_x * ($p0_y - $p2_y)) / $fps;
        $t = ($s2_x * ($p0_y - $p2_y) - $s2_y * ($p0_x - $p2_x)) / $fpt;

        if ($s > 0 && $s < 1 && $t > 0 && $t < 1) {
            // Collision detected
            return true;
        }

        return false;
    }

    public function geometryType(): string
    {
        return 'LineString';
    }
}
