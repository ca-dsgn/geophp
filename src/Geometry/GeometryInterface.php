<?php

namespace Tochka\GeoPHP\Geometry;

/**
 * @api
 */
interface GeometryInterface
{
    public function getGeos(): \GEOSGeometry;
    public function setGeos(\GEOSGeometry $geos): void;
    public function getArea(): float;
    public function boundary(): GeometryInterface;
    public function getCentroid(): ?Point;
    public function length(): float;
    public function getX(): ?float;
    public function getY(): ?float;
    public function getZ(): ?float;
    public function getM(): ?float;
    public function numGeometries(): int;
    public function geometryN($n);
    public function startPoint();
    public function endPoint();
    public function isRing();
    public function isClosed();
    public function numPoints(): int;
    public function pointN(int $n);
    public function exteriorRing();
    public function numInteriorRings();
    public function interiorRingN(int $n);
    public function dimension();
    public function equals(GeometryInterface $geometry): bool;
    public function isEmpty(): bool;
    public function isSimple(): bool;
    public function envelope(): GeometryInterface;

    public function geometryType(): string;

    public function getSRID(): int;

    public function setSRID(int $srid): void;

    public function coordinateDimension(): int;

    public function isMeasured(): bool;

    public function is3D(): bool;

    public function hasZ(): bool;

    public function pointOnSurface(): ?GeometryInterface;

    public function equalsExact(Geometry $geometry): bool;

    public function relate(Geometry $geometry, string $pattern = null): string|bool;

    public function checkValidity(): array;

    public function buffer(float $distance): ?GeometryInterface;

    public function convexHull(): ?GeometryInterface;

    public function intersection(GeometryInterface $geometry): ?GeometryInterface;

    public function difference(GeometryInterface $geometry): ?GeometryInterface;

    public function symDifference(GeometryInterface $geometry): ?GeometryInterface;

    /**
     * Can pass in a geometry or an array of geometries
     * @param GeometryInterface|array<GeometryInterface> $geometry
     * @throws \Exception
     */
    public function union(GeometryInterface|array $geometry): ?GeometryInterface;

    public function simplify(float $tolerance, bool $preserveTopology = false): ?GeometryInterface;

    public function disjoint(GeometryInterface $geometry): bool;

    public function touches(GeometryInterface $geometry): bool;

    public function intersects(GeometryInterface $geometry): bool;

    public function crosses(GeometryInterface $geometry): bool;

    public function within(GeometryInterface $geometry): bool;

    public function contains(GeometryInterface $geometry): bool;

    public function overlaps(GeometryInterface $geometry): bool;

    public function covers(GeometryInterface $geometry): bool;

    public function coveredBy(GeometryInterface $geometry): bool;

    public function distance(GeometryInterface $geometry): float;

    public function hausdorffDistance(GeometryInterface $geometry): float;

    public function project(GeometryInterface $point): ?GeometryInterface;


}
