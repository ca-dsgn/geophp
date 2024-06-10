<?php

namespace Tochka\GeoPHP\Geometry;

/**
 * Point: The most basic geometry type. All other geometries are built out of Points.
 * @api
 * @psalm-immutable
 */
readonly class Point extends Geometry
{
    private ?float $x;
    private ?float $y;

    /**
     * Constructor
     *
     * @param float|string|int|null $x The x coordinate (or longitude)
     * @param float|string|int|null $y The y coordinate (or latitude)
     */
    public function __construct(float|string|int|null $x = null, float|string|int|null $y = null, ?\GEOSGeometry $geos = null, ?int $srid = null)
    {
        // Convert to floatval in case they are passed in as a string or integer etc.
        $this->x = $x !== null ? floatval($x) : null;
        $this->y = $y !== null ? floatval($y) : null;

        parent::__construct($geos, $srid);
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
        return null;
    }

    /**
     * @return Point A point's centroid is itself
     */
    public function getCentroid(): Point
    {
        return $this;
    }

    public function getBBox(): ?BoundBox
    {
        if ($this->x === null || $this->y === null) {
            return null;
        }

        return new BoundBox($this->x, $this->x, $this->y, $this->y);
    }

    public function asArray(): array
    {
        return [$this->x, $this->y];
    }

    public function getArea(): float
    {
        return 0;
    }

    public function length(): float
    {
        return 0;
    }

    public function greatCircleLength(int $radius = 6378137): float
    {
        return 0;
    }

    public function haversineLength(): float
    {
        return 0;
    }

    // The boundary of a point is itself
    public function boundary(): GeometryInterface
    {
        return $this;
    }

    public function dimension(): int
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

    public function equals(GeometryInterface $geometry): bool
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
            return (abs($this->getX() - $geometry->getX()) <= 1.0E-9 && abs($this->getY() - $geometry->getY()) <= 1.0E-9);
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

    public function geometryN(int $n): null
    {
        return null;
    }

    public function startPoint(): null
    {
        return null;
    }

    public function endPoint(): null
    {
        return null;
    }

    public function isRing(): false
    {
        return false;
    }

    public function isClosed(): false
    {
        return false;
    }

    public function pointN(int $n): null
    {
        return null;
    }

    public function exteriorRing(): null
    {
        return null;
    }

    public function numInteriorRings(): null
    {
        return null;
    }

    public function interiorRingN(int $n): null
    {
        return null;
    }

    public function pointOnSurface(): null
    {
        return null;
    }

    public function explode(): null
    {
        return null;
    }
}
