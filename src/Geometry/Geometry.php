<?php

namespace Tochka\GeoPHP\Geometry;

use Tochka\GeoPHP\GeoPHP;

/**
 * Geometry abstract class
 *
 * @api
 */
abstract class Geometry implements GeometryInterface, CommonGeometryInterface
{
    private \GEOSGeometry|null $geos = null;
    private ?int $srid = null;

    public function getSRID(): int
    {
        return $this->srid;
    }

    /**
     * @throws \Exception
     */
    public function setSRID(int $srid): void
    {
        if ($this->getGeos()) {
            $this->getGeos()->setSRID($srid);
        }

        $this->srid = $srid;
    }

    /**
     * @throws \Exception
     */
    public function envelope(): GeometryInterface
    {
        if ($this->isEmpty()) {
            return new Polygon();
        }

        if ($this->getGeos()) {
            return GeoPHP::geosToGeometry($this->getGeos()->envelope());
        }

        $bbox = $this->getBBox();
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
        $type_map = GeoPHP::getAdapterMap();
        $processor_type = $type_map[$format];
        $processor = new $processor_type();

        array_unshift($args, $this);

        return $processor->write($args);
    }


    public function getGeos(): \GEOSGeometry
    {
        // If it's already been set, just return it
        if ($this->geos && GeoPHP::geosInstalled()) {
            return $this->geos;
        }
        // It hasn't been set yet, generate it
        if (GeoPHP::geosInstalled()) {

            $reader = new \GEOSWKBReader();
            $this->geos = $reader->readHEX($this->out('wkb', true));
        } else {
            $this->geos = null;
        }
        return $this->geos;
    }

    public function setGeos(\GEOSGeometry $geos): void
    {
        $this->geos = $geos;
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
            return GeoPHP::geosToGeometry($this->getGeos()->pointOnSurface());
        }

        return null;
    }

    /**
     * @throws \Exception
     */
    public function equalsExact(Geometry $geometry): bool
    {
        if ($this->getGeos()) {
            return $this->getGeos()->equalsExact($geometry->getGeos());
        }

        return false;
    }

    /**
     * @throws \Exception
     */
    public function relate(Geometry $geometry, string $pattern = null): string|bool
    {
        if ($this->getGeos()) {
            if ($pattern !== null) {
                return $this->getGeos()->relate($geometry->getGeos(), $pattern);
            } else {
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
            return GeoPHP::geosToGeometry($this->getGeos()->convexHull());
        }

        return null;
    }

    /**
     * @throws \Exception
     */
    public function intersection(GeometryInterface $geometry): ?GeometryInterface
    {
        if ($this->getGeos()) {
            return GeoPHP::geosToGeometry($this->getGeos()->intersection($geometry->getGeos()));
        }

        return null;
    }

    /**
     * @throws \Exception
     */
    public function difference(GeometryInterface $geometry): ?GeometryInterface
    {
        if ($this->getGeos()) {
            return GeoPHP::geosToGeometry($this->getGeos()->difference($geometry->getGeos()));
        }

        return null;
    }

    /**
     * @throws \Exception
     */
    public function symDifference(GeometryInterface $geometry): ?GeometryInterface
    {
        if ($this->getGeos()) {
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
                    $geom = $geom->union($item->geos());
                }
                return GeoPHP::geosToGeometry($geom);
            } else {
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
            return GeoPHP::geosToGeometry($this->getGeos()->simplify($tolerance, $preserveTopology));
        }

        return null;
    }

    /**
     * @throws \Exception
     */
    public function disjoint(GeometryInterface $geometry): bool
    {
        if ($this->getGeos()) {
            return $this->getGeos()->disjoint($geometry->getGeos());
        }

        return false;
    }

    /**
     * @throws \Exception
     */
    public function touches(GeometryInterface $geometry): bool
    {
        if ($this->getGeos()) {
            return $this->getGeos()->touches($geometry->getGeos());
        }

        return false;
    }

    /**
     * @throws \Exception
     */
    public function intersects(GeometryInterface $geometry): bool
    {
        if ($this->getGeos()) {
            return $this->getGeos()->intersects($geometry->getGeos());
        }

        return false;
    }

    /**
     * @throws \Exception
     */
    public function crosses(GeometryInterface $geometry): bool
    {
        if ($this->getGeos()) {
            return $this->getGeos()->crosses($geometry->getGeos());
        }

        return false;
    }

    /**
     * @throws \Exception
     */
    public function within(GeometryInterface $geometry): bool
    {
        if ($this->getGeos()) {
            return $this->getGeos()->within($geometry->getGeos());
        }

        return false;
    }

    /**
     * @throws \Exception
     */
    public function contains(GeometryInterface $geometry): bool
    {
        if ($this->getGeos()) {
            return $this->getGeos()->contains($geometry->getGeos());
        }

        return false;
    }

    /**
     * @throws \Exception
     */
    public function overlaps(GeometryInterface $geometry): bool
    {
        if ($this->getGeos()) {
            return $this->getGeos()->overlaps($geometry->getGeos());
        }

        return false;
    }

    /**
     * @throws \Exception
     */
    public function covers(GeometryInterface $geometry): bool
    {
        if ($this->getGeos()) {
            return $this->getGeos()->covers($geometry->getGeos());
        }

        return false;
    }

    /**
     * @throws \Exception
     */
    public function coveredBy(GeometryInterface $geometry): bool
    {
        if ($this->getGeos()) {
            return $this->getGeos()->coveredBy($geometry->getGeos());
        }

        return false;
    }

    /**
     * @throws \Exception
     */
    public function distance(GeometryInterface $geometry): float
    {
        if ($this->getGeos()) {
            return $this->getGeos()->distance($geometry->getGeos());
        }

        return INF;
    }

    /**
     * @throws \Exception
     */
    public function hausdorffDistance(GeometryInterface $geometry): float
    {
        if ($this->getGeos()) {
            return $this->getGeos()->hausdorffDistance($geometry->getGeos());
        }

        return INF;
    }

    /**
     * @throws \Exception
     */
    public function project(GeometryInterface $point): ?GeometryInterface
    {
        if ($this->getGeos()) {
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
