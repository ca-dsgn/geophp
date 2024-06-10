<?php

namespace Tochka\GeoPHP\Geometry;

/**
 * @api
 */
readonly class BoundBox
{
    public function __construct(
        public float $minX,
        public float $maxX,
        public float $minY,
        public float $maxY,
    ) {}
}
