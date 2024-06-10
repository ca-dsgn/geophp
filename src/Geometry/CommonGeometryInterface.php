<?php

namespace Tochka\GeoPHP\Geometry;

/**
 * @api
 */
interface CommonGeometryInterface
{
    public function getBBox(): ?BoundBox;

    /**
     * @return list<Point>
     */
    public function getPoints(): array;

    /**
     * @return list<GeometryInterface>|null
     */
    public function explode(): ?array;
    public function greatCircleLength(): float; //meters
    public function haversineLength(): float; //degrees

    public function asArray(): array;
    public function asText(): string;
    public function asBinary(): string;

    public function out(string $format, mixed ...$args): string;
}
