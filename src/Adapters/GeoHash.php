<?php

namespace Tochka\GeoPHP\Adapters;

use Tochka\GeoPHP\Geometry\GeometryInterface;
use Tochka\GeoPHP\Geometry\LineString;
use Tochka\GeoPHP\Geometry\Point;
use Tochka\GeoPHP\Geometry\Polygon;

/**
 * PHP Geometry GeoHash encoder/decoder.
 *
 * @api
 */
class GeoHash implements GeoAdapterInterface
{
    /**
     * base32 encoding character map.
     */
    private const TABLE = "0123456789bcdefghjkmnpqrstuvwxyz";

    /**
     * Convert the geohash to a Point. The point is 2-dimensional.
     * @param string $input a geohash
     * @return GeometryInterface the converted geohash
     */
    public function read(string $input, $as_grid = false): GeometryInterface
    {
        $ll = $this->decode($input);

        if (!$as_grid) {
            return new Point($ll['medlon'], $ll['medlat']);
        } else {
            return new Polygon([
                new LineString([
                    new Point($ll['minlon'], $ll['maxlat']),
                    new Point($ll['maxlon'], $ll['maxlat']),
                    new Point($ll['maxlon'], $ll['minlat']),
                    new Point($ll['minlon'], $ll['minlat']),
                    new Point($ll['minlon'], $ll['maxlat']),
                ]),
            ]);
        }
    }

    /**
     * Convert the geometry to geohash.
     * @param GeometryInterface $geometry
     * @param float|null $precision
     * @return string the geohash or null when the $geometry is not a Point
     */
    public function write(GeometryInterface $geometry, ?float $precision = null): string
    {
        if ($geometry->isEmpty()) {
            return '';
        }

        if ($geometry instanceof Point) {
            return $this->encodePoint($geometry, $precision);
        }

        // The geohash is the hash grid ID that fits the envelope
        $envelope = $geometry->envelope();
        $geohashes = [];
        $geohash = '';
        foreach ($envelope->getPoints() as $point) {
            $geohashes[] = $this->encodePoint($point, 0.0000001);
        }

        $i = 0;
        while ($i < strlen($geohashes[0])) {
            $char = $geohashes[0][$i];
            foreach ($geohashes as $hash) {
                if ($hash[$i] != $char) {
                    return $geohash;
                }
            }
            $geohash .= $char;
            $i++;
        }

        return $geohash;
    }

    /**
     * @param Point $point
     * @param float|null $precision
     * @return string geohash
     * @author algorithm based on code by Alexander Songe <a@songe.me>
     * @see https://github.com/asonge/php-geohash/issues/1
     */
    private function encodePoint(Point $point, ?float $precision = null): string
    {
        if ($precision === null) {
            $lap = strlen($point->getY()) - strpos($point->getY(), ".");
            $lop = strlen($point->getX()) - strpos($point->getX(), ".");
            $precision = pow(10, -max($lap - 1, $lop - 1, 0)) / 2;
        }

        $minlat = -90;
        $maxlat = 90;
        $minlon = -180;
        $maxlon = 180;
        $latE = 90;
        $lonE = 180;
        $i = 0;
        $error = 180;
        $hash = '';
        while ($error >= $precision) {
            $chr = 0;
            for ($b = 4; $b >= 0; --$b) {
                if ((1 & $b) == (1 & $i)) {
                    // even char, even bit OR odd char, odd bit...a lon
                    $next = ($minlon + $maxlon) / 2;
                    if ($point->getX() > $next) {
                        $chr |= pow(2, $b);
                        $minlon = $next;
                    } else {
                        $maxlon = $next;
                    }
                    $lonE /= 2;
                } else {
                    // odd char, even bit OR even char, odd bit...a lat
                    $next = ($minlat + $maxlat) / 2;
                    if ($point->getY() > $next) {
                        $chr |= pow(2, $b);
                        $minlat = $next;
                    } else {
                        $maxlat = $next;
                    }
                    $latE /= 2;
                }
            }
            $hash .= self::TABLE[$chr];
            $i++;
            $error = min($latE, $lonE);
        }
        return $hash;
    }

    /**
     * @param string $hash a geohash
     * @author algorithm based on code by Alexander Songe <a@songe.me>
     * @see https://github.com/asonge/php-geohash/issues/1
     */
    private function decode(string $hash): array
    {
        $ll = [];
        $minlat = -90;
        $maxlat = 90;
        $minlon = -180;
        $maxlon = 180;
        $latE = 90;
        $lonE = 180;
        for ($i = 0, $c = strlen($hash); $i < $c; $i++) {
            $v = strpos(self::TABLE, $hash[$i]);
            if (1 & $i) {
                if (16 & $v) {
                    $minlat = ($minlat + $maxlat) / 2;
                } else {
                    $maxlat = ($minlat + $maxlat) / 2;
                }
                if (8 & $v) {
                    $minlon = ($minlon + $maxlon) / 2;
                } else {
                    $maxlon = ($minlon + $maxlon) / 2;
                }
                if (4 & $v) {
                    $minlat = ($minlat + $maxlat) / 2;
                } else {
                    $maxlat = ($minlat + $maxlat) / 2;
                }
                if (2 & $v) {
                    $minlon = ($minlon + $maxlon) / 2;
                } else {
                    $maxlon = ($minlon + $maxlon) / 2;
                }
                if (1 & $v) {
                    $minlat = ($minlat + $maxlat) / 2;
                } else {
                    $maxlat = ($minlat + $maxlat) / 2;
                }
                $latE /= 8;
                $lonE /= 4;
            } else {
                if (16 & $v) {
                    $minlon = ($minlon + $maxlon) / 2;
                } else {
                    $maxlon = ($minlon + $maxlon) / 2;
                }
                if (8 & $v) {
                    $minlat = ($minlat + $maxlat) / 2;
                } else {
                    $maxlat = ($minlat + $maxlat) / 2;
                }
                if (4 & $v) {
                    $minlon = ($minlon + $maxlon) / 2;
                } else {
                    $maxlon = ($minlon + $maxlon) / 2;
                }
                if (2 & $v) {
                    $minlat = ($minlat + $maxlat) / 2;
                } else {
                    $maxlat = ($minlat + $maxlat) / 2;
                }
                if (1 & $v) {
                    $minlon = ($minlon + $maxlon) / 2;
                } else {
                    $maxlon = ($minlon + $maxlon) / 2;
                }
                $latE /= 4;
                $lonE /= 8;
            }
        }
        $ll['minlat'] = $minlat;
        $ll['minlon'] = $minlon;
        $ll['maxlat'] = $maxlat;
        $ll['maxlon'] = $maxlon;
        $ll['medlat'] = round(($minlat + $maxlat) / 2, max(1, -round(log10($latE))) - 1);
        $ll['medlon'] = round(($minlon + $maxlon) / 2, max(1, -round(log10($lonE))) - 1);
        return $ll;
    }
}
