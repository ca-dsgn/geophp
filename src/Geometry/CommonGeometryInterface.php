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
    public function explode();
    public function greatCircleLength(); //meters
    public function haversineLength(); //degrees

    public function asArray(): array;
    public function asText(): string;
    public function asBinary(): string;

    public function out(string $format, mixed ...$args): string;
}
