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
 * @psalm-immutable
 */
readonly class GeoHash implements GeoAdapterInterface
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
    public function read(string $input, bool $asGrid = false): GeometryInterface
    {
        $ll = $this->decode($input);

        if (!$asGrid) {
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
        if ($envelope === null) {
            return '';
        }
        $geoHashes = [];
        $geoHash = '';
        foreach ($envelope->getPoints() as $point) {
            $geoHashes[] = $this->encodePoint($point, 0.0000001);
        }

        if ($geoHashes === []) {
            return '';
        }

        $i = 0;
        while ($i < strlen($geoHashes[0])) {
            $char = $geoHashes[0][$i];
            foreach ($geoHashes as $hash) {
                if ($hash[$i] != $char) {
                    return $geoHash;
                }
            }
            $geoHash .= $char;
            $i++;
        }

        return $geoHash;
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
            if ($point->getX() === null || $point->getY() === null) {
                $precision = 0.0000001;
            } else {
                $pointLap = strpos((string) $point->getY(), '.');
                $pointLon = strpos((string) $point->getX(), '.');
                $lap = strlen((string) $point->getY()) - ($pointLap !== false ? $pointLap : 0);
                $lon = strlen((string) $point->getX()) - ($pointLon !== false ? $pointLon : 0);
                $precision = pow(10, -max($lap - 1, $lon - 1, 0)) / 2;
            }
        }

        $minLat = -90;
        $maxLat = 90;
        $minLon = -180;
        $maxLon = 180;
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
                    $next = ($minLon + $maxLon) / 2;
                    if ($point->getX() > $next) {
                        $chr |= pow(2, $b);
                        $minLon = $next;
                    } else {
                        $maxLon = $next;
                    }
                    $lonE /= 2;
                } else {
                    // odd char, even bit OR even char, odd bit...a lat
                    $next = ($minLat + $maxLat) / 2;
                    if ($point->getY() > $next) {
                        $chr |= pow(2, $b);
                        $minLat = $next;
                    } else {
                        $maxLat = $next;
                    }
                    $latE /= 2;
                }
            }
            $hash .= self::TABLE[(int) $chr];
            $i++;
            $error = min($latE, $lonE);
        }
        return $hash;
    }

    /**
     * @param string $hash a geohash
     * @return array{minlat: float, minlon: float, maxlat: float, maxlon: float, medlat: float, medlon: float}
     * @author algorithm based on code by Alexander Songe <a@songe.me>
     * @see https://github.com/asonge/php-geohash/issues/1
     */
    private function decode(string $hash): array
    {
        $result = [];
        $minLat = -90;
        $maxLat = 90;
        $minLon = -180;
        $maxLon = 180;
        $latE = 90;
        $lonE = 180;
        for ($i = 0, $c = strlen($hash); $i < $c; $i++) {
            $v = strpos(self::TABLE, $hash[$i]);
            if ($v === false) {
                continue;
            }

            if (1 & $i) {
                if (16 & $v) {
                    $minLat = ($minLat + $maxLat) / 2;
                } else {
                    $maxLat = ($minLat + $maxLat) / 2;
                }
                if (8 & $v) {
                    $minLon = ($minLon + $maxLon) / 2;
                } else {
                    $maxLon = ($minLon + $maxLon) / 2;
                }
                if (4 & $v) {
                    $minLat = ($minLat + $maxLat) / 2;
                } else {
                    $maxLat = ($minLat + $maxLat) / 2;
                }
                if (2 & $v) {
                    $minLon = ($minLon + $maxLon) / 2;
                } else {
                    $maxLon = ($minLon + $maxLon) / 2;
                }
                if (1 & $v) {
                    $minLat = ($minLat + $maxLat) / 2;
                } else {
                    $maxLat = ($minLat + $maxLat) / 2;
                }
                $latE /= 8;
                $lonE /= 4;
            } else {
                if (16 & $v) {
                    $minLon = ($minLon + $maxLon) / 2;
                } else {
                    $maxLon = ($minLon + $maxLon) / 2;
                }
                if (8 & $v) {
                    $minLat = ($minLat + $maxLat) / 2;
                } else {
                    $maxLat = ($minLat + $maxLat) / 2;
                }
                if (4 & $v) {
                    $minLon = ($minLon + $maxLon) / 2;
                } else {
                    $maxLon = ($minLon + $maxLon) / 2;
                }
                if (2 & $v) {
                    $minLat = ($minLat + $maxLat) / 2;
                } else {
                    $maxLat = ($minLat + $maxLat) / 2;
                }
                if (1 & $v) {
                    $minLon = ($minLon + $maxLon) / 2;
                } else {
                    $maxLon = ($minLon + $maxLon) / 2;
                }
                $latE /= 4;
                $lonE /= 8;
            }
        }
        $result['minlat'] = $minLat;
        $result['minlon'] = $minLon;
        $result['maxlat'] = $maxLat;
        $result['maxlon'] = $maxLon;
        $result['medlat'] = round(($minLat + $maxLat) / 2, (int) max(1, -round(log10($latE))) - 1);
        $result['medlon'] = round(($minLon + $maxLon) / 2, (int) max(1, -round(log10($lonE))) - 1);

        return $result;
    }
}
