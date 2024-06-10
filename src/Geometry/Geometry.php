<?php

namespace Tochka\GeoPHP\Geometry;

use Tochka\GeoPHP\GeoPHP;

/**
 * Geometry abstract class
 *
 * @api
 * @psalm-immutable
 */
abstract readonly class Geometry implements GeometryInterface
{
    private ?\GEOSGeometry $geos;

    public function __construct(
        ?\GEOSGeometry $geos = null,
        private ?int $srid = null,
    ) {
        if ($geos === null && GeoPHP::geosInstalled()) {
            try {
                $reader = new \GEOSWKBReader();
                /** @psalm-suppress ImpureMethodCall */
                $this->geos = $reader->readHEX($this->out('wkb', true));
                if ($srid !== null) {
                    /** @psalm-suppress ImpureMethodCall */
                    $this->geos->setSRID($srid);
                }
            } catch (\Throwable) {
            }
        } else {
            $this->geos = $geos;
        }
    }

    public function getSRID(): ?int
    {
        return $this->srid;
    }

    /**
     * @throws \Exception
     */
    public function envelope(): ?GeometryInterface
    {
        if ($this->isEmpty()) {
            return new Polygon();
        }

        if ($this->getGeos()) {
            /** @psalm-suppress ImpureMethodCall */
            return GeoPHP::geosToGeometry($this->getGeos()->envelope());
        }

        $bbox = $this->getBBox();
        if ($bbox === null) {
            return new Polygon();
        }

        $points = [
            new Point($bbox->maxX, $bbox->minY),
            new Point($bbox->maxX, $bbox->maxY),
            new Point($bbox->minX, $bbox->maxY),
            new Point($bbox->minX, $bbox->minY),
            new Point($bbox->maxX, $bbox->minY),
        ];

        $outerBoundary = new LineString($points);
        return new Polygon([$outerBoundary]);
    }

    public function out(string $format, mixed ...$args): string
    {
        $typeMap = GeoPHP::getAdapterMap();
        if (!array_key_exists($format, $typeMap)) {
            throw new \RuntimeException('Unknown format: ' . $format);
        }

        $processorType = $typeMap[$format];
        $processor = new $processorType();

        array_unshift($args, $this);

        return $processor->write(...$args);
    }

    public function getGeos(): ?\GEOSGeometry
    {
        return $this->geos;
    }

    public function asText(): string
    {
        return $this->out('wkt');
    }

    public function asBinary(): string
    {
        return $this->out('wkb');
    }

    /**
     * @throws \Exception
     */
    public function pointOnSurface(): ?GeometryInterface
    {
        if ($this->getGeos()) {
            /** @psalm-suppress ImpureMethodCall */
            return GeoPHP::geosToGeometry($this->getGeos()->pointOnSurface());
        }

        return null;
    }

    /**
     * @throws \Exception
     */
    public function equalsExact(Geometry $geometry): bool
    {
        if ($this->getGeos() && $geometry->getGeos()) {
            /** @psalm-suppress ImpureMethodCall */
            return $this->getGeos()->equalsExact($geometry->getGeos());
        }

        return false;
    }

    /**
     * @throws \Exception
     */
    public function relate(Geometry $geometry, string $pattern = null): string|bool
    {
        if ($this->getGeos() && $geometry->getGeos()) {
            if ($pattern !== null) {
                /** @psalm-suppress ImpureMethodCall */
                return $this->getGeos()->relate($geometry->getGeos(), $pattern);
            } else {
                /** @psalm-suppress ImpureMethodCall */
                return $this->getGeos()->relate($geometry->getGeos());
            }
        }

        return false;
    }

    /**
     * @throws \Exception
     */
    public function checkValidity(): array
    {
        if ($this->getGeos()) {
            /** @psalm-suppress ImpureMethodCall */
            return $this->getGeos()->checkValidity();
        }

        return [];
    }

    /**
     * @throws \Exception
     */
    public function buffer(float $distance): ?GeometryInterface
    {
        if ($this->getGeos()) {
            /** @psalm-suppress ImpureMethodCall */
            return GeoPHP::geosToGeometry($this->getGeos()->buffer($distance));
        }

        return null;
    }

    /**
     * @throws \Exception
     */
    public function convexHull(): ?GeometryInterface
    {
        if ($this->getGeos()) {
            /** @psalm-suppress ImpureMethodCall */
            return GeoPHP::geosToGeometry($this->getGeos()->convexHull());
        }

        return null;
    }

    /**
     * @throws \Exception
     */
    public function intersection(GeometryInterface $geometry): ?GeometryInterface
    {
        if ($this->getGeos() && $geometry->getGeos()) {
            /** @psalm-suppress ImpureMethodCall */
            return GeoPHP::geosToGeometry($this->getGeos()->intersection($geometry->getGeos()));
        }

        return null;
    }

    /**
     * @throws \Exception
     */
    public function difference(GeometryInterface $geometry): ?GeometryInterface
    {
        if ($this->getGeos() && $geometry->getGeos()) {
            /** @psalm-suppress ImpureMethodCall */
            return GeoPHP::geosToGeometry($this->getGeos()->difference($geometry->getGeos()));
        }

        return null;
    }

    /**
     * @throws \Exception
     */
    public function symDifference(GeometryInterface $geometry): ?GeometryInterface
    {
        if ($this->getGeos() && $geometry->getGeos()) {
            /** @psalm-suppress ImpureMethodCall */
            return GeoPHP::geosToGeometry($this->getGeos()->symDifference($geometry->getGeos()));
        }

        return null;
    }

    /**
     * Can pass in a geometry or an array of geometries
     * @param GeometryInterface|array<GeometryInterface> $geometry
     * @throws \Exception
     */
    public function union(GeometryInterface|array $geometry): ?GeometryInterface
    {
        if ($this->getGeos()) {
            if (is_array($geometry)) {
                $geom = $this->getGeos();
                foreach ($geometry as $item) {
                    /** @psalm-suppress ImpureMethodCall */
                    $geom = $geom->union($item->getGeos());
                }
                return GeoPHP::geosToGeometry($geom);
            } else {
                /** @psalm-suppress ImpureMethodCall */
                return GeoPHP::geosToGeometry($this->getGeos()->union($geometry->getGeos()));
            }
        }

        return null;
    }

    /**
     * @throws \Exception
     */
    public function simplify(float $tolerance, bool $preserveTopology = false): ?GeometryInterface
    {
        if ($this->getGeos()) {
            /** @psalm-suppress ImpureMethodCall */
            return GeoPHP::geosToGeometry($this->getGeos()->simplify($tolerance, $preserveTopology));
        }

        return null;
    }

    /**
     * @throws \Exception
     */
    public function disjoint(GeometryInterface $geometry): bool
    {
        if ($this->getGeos() && $geometry->getGeos()) {
            /** @psalm-suppress ImpureMethodCall */
            return $this->getGeos()->disjoint($geometry->getGeos());
        }

        return false;
    }

    /**
     * @throws \Exception
     */
    public function touches(GeometryInterface $geometry): bool
    {
        if ($this->getGeos() && $geometry->getGeos()) {
            /** @psalm-suppress ImpureMethodCall */
            return $this->getGeos()->touches($geometry->getGeos());
        }

        return false;
    }

    /**
     * @throws \Exception
     */
    public function intersects(GeometryInterface $geometry): bool
    {
        if ($this->getGeos() && $geometry->getGeos()) {
            /** @psalm-suppress ImpureMethodCall */
            return $this->getGeos()->intersects($geometry->getGeos());
        }

        return false;
    }

    /**
     * @throws \Exception
     */
    public function crosses(GeometryInterface $geometry): bool
    {
        if ($this->getGeos() && $geometry->getGeos()) {
            /** @psalm-suppress ImpureMethodCall */
            return $this->getGeos()->crosses($geometry->getGeos());
        }

        return false;
    }

    /**
     * @throws \Exception
     */
    public function within(GeometryInterface $geometry): bool
    {
        if ($this->getGeos() && $geometry->getGeos()) {
            /** @psalm-suppress ImpureMethodCall */
            return $this->getGeos()->within($geometry->getGeos());
        }

        return false;
    }

    /**
     * @throws \Exception
     */
    public function contains(GeometryInterface $geometry): bool
    {
        if ($this->getGeos() && $geometry->getGeos()) {
            /** @psalm-suppress ImpureMethodCall */
            return $this->getGeos()->contains($geometry->getGeos());
        }

        return false;
    }

    /**
     * @throws \Exception
     */
    public function overlaps(GeometryInterface $geometry): bool
    {
        if ($this->getGeos() && $geometry->getGeos()) {
            /** @psalm-suppress ImpureMethodCall */
            return $this->getGeos()->overlaps($geometry->getGeos());
        }

        return false;
    }

    /**
     * @throws \Exception
     */
    public function covers(GeometryInterface $geometry): bool
    {
        if ($this->getGeos() && $geometry->getGeos()) {
            /** @psalm-suppress ImpureMethodCall */
            return $this->getGeos()->covers($geometry->getGeos());
        }

        return false;
    }

    /**
     * @throws \Exception
     */
    public function coveredBy(GeometryInterface $geometry): bool
    {
        if ($this->getGeos() && $geometry->getGeos()) {
            /** @psalm-suppress ImpureMethodCall */
            return $this->getGeos()->coveredBy($geometry->getGeos());
        }

        return false;
    }

    /**
     * @throws \Exception
     */
    public function distance(GeometryInterface $geometry): float
    {
        if ($this->getGeos() && $geometry->getGeos()) {
            /** @psalm-suppress ImpureMethodCall */
            return $this->getGeos()->distance($geometry->getGeos());
        }

        return INF;
    }

    /**
     * @throws \Exception
     */
    public function hausdorffDistance(GeometryInterface $geometry): float
    {
        if ($this->getGeos() && $geometry->getGeos()) {
            /** @psalm-suppress ImpureMethodCall */
            return $this->getGeos()->hausdorffDistance($geometry->getGeos());
        }

        return INF;
    }

    /**
     * @throws \Exception
     */
    public function project(GeometryInterface $point): ?GeometryInterface
    {
        if ($this->getGeos() && $point->getGeos()) {
            /** @psalm-suppress ImpureMethodCall */
            return GeoPHP::geosToGeometry($this->getGeos()->project($point->getGeos()));
        }

        return null;
    }

    /**
     * geoPHP does not support Z values at the moment
     */
    public function hasZ(): false
    {
        return false;
    }

    /**
     * geoPHP does not support 3D geometries at the moment
     */
    public function is3D(): false
    {
        return false;
    }

    /**
     * geoPHP does not yet support M values
     */
    public function isMeasured(): false
    {
        return false;
    }

    /**
     * geoPHP only supports 2-dimensional space
     */
    public function coordinateDimension(): int
    {
        return 2;
    }

    /**
     * geoPHP only supports 2-dimensional space
     */
    public function getZ(): ?float
    {
        return null;
    }

    /**
     * geoPHP only supports 2-dimensional space
     */
    public function getM(): ?float
    {
        return null;
    }
}
