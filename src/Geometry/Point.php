<?php

namespace Tochka\GeoPHP\Geometry;

/**
 * Point: The most basic geometry type. All other geometries are built out of Points.
 * @api
 */
class Point extends Geometry
{
    private ?float $x;
    private ?float $y;
    private ?float $z;

    /**
     * Constructor
     *
     * @param float|string|int|null $x The x coordinate (or longitude)
     * @param float|string|int|null $y The y coordinate (or latitude)
     * @param float|string|int|null $z The z coordinate (or altitude) - optional
     */
    public function __construct(float|string|int $x = null, float|string|int $y = null, float|string|int $z = null)
    {
        // Convert to floatval in case they are passed in as a string or integer etc.
        $this->x = $x !== null ? floatval($x) : null;
        $this->y = $y !== null ? floatval($y) : null;
        $this->z = $z !== null ? floatval($z) : null;
    }

    public function geometryType(): string
    {
        return 'Point';
    }

    /**
     * Get X (longitude) coordinate
     *
     * @return float|null The X coordinate
     */
    public function getX(): ?float
    {
        return $this->x;
    }

    /**
     * Returns Y (latitude) coordinate
     *
     * @return float|null The Y coordinate
     */
    public function getY(): ?float
    {
        return $this->y;
    }

    /**
     * Returns Z (altitude) coordinate
     *
     * @return float|null The Z coordinate or NULL is not a 3D point
     */
    public function getZ(): ?float
    {
        return $this->z;
    }

    /**
     * inverts x and y coordinates
     * Useful with old applications still using lng lat
     *
     * @return void
     */
    public function invertxy(): void
    {
        $x = $this->x;
        $this->x = $this->y;
        $this->y = $x;
    }


    // A point's centroid is itself
    public function getCentroid(): Point
    {
        return $this;
    }

    public function getBBox(): BoundBox
    {
        return new BoundBox($this->getX(), $this->getX(), $this->getY(), $this->getY());
    }

    public function asArray(): array
    {
        $result = [];
        if ($this->x !== null && $this->y !== null) {
            $result[] = $this->x;
            $result[] = $this->y;
        }
        if ($this->z !== null) {
            $result[] = $this->z;
        }

        return $result;
    }

    public function getArea(): float
    {
        return 0;
    }

    public function length(): float
    {
        return 0;
    }

    public function greatCircleLength()
    {
        return 0;
    }

    public function haversineLength()
    {
        return 0;
    }

    // The boundary of a point is itself
    public function boundary(): GeometryInterface
    {
        return $this;
    }

    public function dimension()
    {
        return 0;
    }

    public function isEmpty(): bool
    {
        return $this->x === null && $this->y === null;
    }

    public function numPoints(): int
    {
        return 1;
    }

    /**
     * @return list<Point>
     */
    public function getPoints(): array
    {
        return [$this];
    }

    public function equals(Geometry $geometry): bool
    {
        if (!$geometry instanceof Point) {
            return false;
        }

        if (!$this->isEmpty() && !$geometry->isEmpty()) {
            /**
             * @see: http://php.net/manual/en/function.bccomp.php
             * @see: http://php.net/manual/en/language.types.float.php
             * @see: http://tubalmartin.github.io/spherical-geometry-php/#LatLng
             */
            return (abs($this->x() - $geometry->x()) <= 1.0E-9 && abs($this->y() - $geometry->y()) <= 1.0E-9);
        } elseif ($this->isEmpty() && $geometry->isEmpty()) {
            return true;
        } else {
            return false;
        }
    }

    public function isSimple(): bool
    {
        return true;
    }

    // Not valid for this geometry type
    public function numGeometries(): int
    {
        return 0;
    }
    public function geometryN($n)
    {
        return null;
    }
    public function startPoint()
    {
        return null;
    }
    public function endPoint()
    {
        return null;
    }
    public function isRing()
    {
        return null;
    }
    public function isClosed()
    {
        return null;
    }
    public function pointN($n)
    {
        return null;
    }
    public function exteriorRing()
    {
        return null;
    }
    public function numInteriorRings()
    {
        return null;
    }
    public function interiorRingN($n)
    {
        return null;
    }
    public function pointOnSurface()
    {
        return null;
    }
    public function explode()
    {
        return null;
    }
}
