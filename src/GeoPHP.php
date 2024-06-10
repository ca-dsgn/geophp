<?php

namespace Tochka\GeoPHP;

use Tochka\GeoPHP\Adapters\EWKB;
use Tochka\GeoPHP\Adapters\EWKT;
use Tochka\GeoPHP\Adapters\GeoAdapterInterface;
use Tochka\GeoPHP\Adapters\GeoHash;
use Tochka\GeoPHP\Adapters\GeoJSON;
use Tochka\GeoPHP\Adapters\GeoRSS;
use Tochka\GeoPHP\Adapters\GoogleGeocode;
use Tochka\GeoPHP\Adapters\GPX;
use Tochka\GeoPHP\Adapters\KML;
use Tochka\GeoPHP\Adapters\WKB;
use Tochka\GeoPHP\Adapters\WKT;
use Tochka\GeoPHP\Geometry\GeometryCollection;
use Tochka\GeoPHP\Geometry\GeometryInterface;
use Tochka\GeoPHP\Geometry\LineString;
use Tochka\GeoPHP\Geometry\MultiLineString;
use Tochka\GeoPHP\Geometry\MultiPoint;
use Tochka\GeoPHP\Geometry\MultiPolygon;
use Tochka\GeoPHP\Geometry\Point;
use Tochka\GeoPHP\Geometry\Polygon;

/**
 * @api
 * @psalm-immutable
 */
class GeoPHP
{
    public static function version(): string
    {
        return '1.2';
    }

    /**
     * GeoPHP::load($data, $type, $other_args);
     * if $data is an array, all passed in values will be combined into a single geometry
     *
     * @psalm-mutation-free
     */
    public static function load(mixed $data, string $type = null, mixed ...$args): ?GeometryInterface
    {
        $typeMap = self::getAdapterMap();

        // Auto-detect type if needed
        if ($type === null) {
            // If the user is trying to load a Geometry from a Geometry... Just pass it back
            if ($data instanceof GeometryInterface) {
                return $data;
            }

            $detected = self::detectFormat($data);
            if ($detected === false) {
                return null;
            }

            $format = explode(':', $detected);
            $type = array_shift($format);
            $args = $format;
        }

        $processorType = $typeMap[$type] ?? null;

        if (!$processorType) {
            throw new \RuntimeException('geoPHP could not find an adapter of type ' . htmlentities($type));
        }

        $processor = new $processorType();

        // Data is not an array, just pass it normally
        if (!is_array($data)) {
            return $processor->read(...array_merge([$data], $args));
        } else {
            // Data is an array, combine all passed in items into a single geometry
            $geometries = [];
            foreach ($data as $item) {
                $geometries[] = $processor->read(...array_merge([$item], $args));
            }
            return self::geometryReduce($geometries);
        }
    }

    /**
     * @return array<string, class-string<GeoAdapterInterface>>
     * @psalm-mutation-free
     */
    public static function getAdapterMap(): array
    {
        return [
            'wkt' => WKT::class,
            'ewkt' => EWKT::class,
            'wkb' => WKB::class,
            'ewkb' => EWKB::class,
            'json' => GeoJSON::class,
            'geojson' => GeoJSON::class,
            'kml' => KML::class,
            'gpx' => GPX::class,
            'georss' => GeoRSS::class,
            'google_geocode' => GoogleGeocode::class,
            'geohash' => GeoHash::class,
        ];
    }

    /**
     * @return array<string, class-string<GeometryInterface>>
     * @psalm-mutation-free
     */
    public static function geometryList(): array
    {
        return [
            'point' => Point::class,
            'linestring' => LineString::class,
            'polygon' => Polygon::class,
            'multipoint' => MultiPoint::class,
            'multilinestring' => MultiLineString::class,
            'multipolygon' => MultiPolygon::class,
            'geometrycollection' => GeometryCollection::class,
        ];
    }

    /**
     * @psalm-mutation-free
     */
    public static function geosInstalled(bool $force = false): bool
    {
        /** @psalm-suppress ImpureFunctionCall */
        return $force || class_exists('geos', false);
    }

    /**
     * @throws \Exception
     * @psalm-mutation-free
     */
    public static function geosToGeometry(?\GEOSGeometry $geos, ?int $srid = null): ?GeometryInterface
    {
        if ($geos === null || !self::geosInstalled()) {
            return null;
        }

        $wkbWriter = new \GEOSWKBWriter();
        /** @psalm-suppress ImpureMethodCall */
        $wkb = $wkbWriter->writeHEX($geos);
        $wkbAdapter = new WKB();
        return $wkbAdapter->read($wkb, true, $geos, $srid);
    }

    /**
     * Reduce a geometry, or an array of geometries, into their 'lowest' available common geometry.
     * For example a GeometryCollection of only points will become a MultiPoint
     * A multi-point containing a single point will return a point.
     * An array of geometries can be passed and they will be compiled into a single geometry
     *
     * @param array<GeometryInterface>|GeometryInterface $geometry
     * @psalm-mutation-free
     */
    public static function geometryReduce(array|GeometryInterface $geometry): ?GeometryInterface
    {
        // If it's an array of one, then just parse the one
        if (is_array($geometry)) {
            if (empty($geometry)) {
                return null;
            }
            if (count($geometry) === 1) {
                return self::geometryReduce(array_shift($geometry));
            }
        }

        switch (true) {
            // If the geometry cannot even theoretically be reduced more, then pass it back
            case $geometry instanceof Point:
            case $geometry instanceof Polygon:
            case $geometry instanceof LineString:
                return $geometry;
                // If it is a mutlti-geometry, check to see if it just has one member
                // If it does, then pass the member, if not, then just pass back the geometry
            case $geometry instanceof MultiPoint:
            case $geometry instanceof MultiPolygon:
            case $geometry instanceof MultiLineString:
                $components = $geometry->getComponents();
                if (count($components) == 1) {
                    return $components[0];
                } else {
                    return $geometry;
                }
        }

        // So now we either have an array of geometries, a GeometryCollection, or an array of GeometryCollections
        if (!is_array($geometry)) {
            $geometry = [$geometry];
        }

        $geometries = [];
        $geometryTypes = [];

        foreach ($geometry as $item) {
            if ($item instanceof MultiPoint || $item instanceof MultiPolygon || $item instanceof MultiLineString || $item instanceof GeometryCollection) {
                foreach ($item->getComponents() as $component) {
                    $geometries[] = $component;
                    $geometryTypes[] = $component::class;
                }
            } else {
                $geometries[] = $item;
                $geometryTypes[] = $item::class;
            }
        }

        $geometryTypes = array_unique($geometryTypes);

        if (empty($geometryTypes)) {
            return null;
        }

        if (count($geometryTypes) === 1) {
            if (count($geometries) === 1) {
                return $geometries[0];
            } else {
                $class = self::getMultiGeometryClassName($geometryTypes[0]);
                if ($class === null) {
                    return null;
                }
                return new $class($geometries);
            }
        } else {
            return new GeometryCollection($geometries);
        }
    }

    /**
     * @param class-string<GeometryInterface> $geometryType
     * @return class-string<GeometryInterface>|null
     * @psalm-mutation-free
     */
    private static function getMultiGeometryClassName(string $geometryType): ?string
    {
        return match ($geometryType) {
            Point::class => MultiPoint::class,
            LineString::class => MultiLineString::class,
            Polygon::class => MultiPolygon::class,
            default => null,
        };
    }

    /**
     * Detect a format given a value. This function is meant to be SPEEDY.
     * It could make a mistake in XML detection if you are mixing or using namespaces in weird ways (ie, KML inside an RSS feed)
     * @psalm-mutation-free
     */
    public static function detectFormat(string $input): string|false
    {
        /** @psalm-suppress ImpureFunctionCall */
        $mem = fopen('php://memory', 'r+');
        /** @psalm-suppress ImpureFunctionCall */
        fwrite($mem, $input, 11); // Write 11 bytes - we can detect the vast majority of formats in the first 11 bytes
        /** @psalm-suppress ImpureFunctionCall */
        fseek($mem, 0);

        /** @psalm-suppress ImpureFunctionCall */
        $bytes = unpack("c*", fread($mem, 11));

        // If bytes is empty, then we were passed empty input
        /** @psalm-suppress RiskyTruthyFalsyComparison */
        if (empty($bytes)) {
            return false;
        }

        // First char is a tab, space or carriage-return. trim it and try again
        if ($bytes[1] == 9 || $bytes[1] == 10 || $bytes[1] == 32) {
            return self::detectFormat(ltrim($input));
        }

        // Detect WKB or EWKB -- first byte is 1 (little endian indicator)
        if ($bytes[1] == 1) {
            // If SRID byte is TRUE (1), it's EWKB
            if ($bytes[5]) {
                return 'ewkb';
            } else {
                return 'wkb';
            }
        }

        // Detect HEX encoded WKB or EWKB (PostGIS format) -- first byte is 48, second byte is 49 (hex '01' => first-byte = 1)
        if ($bytes[1] == 48 && $bytes[2] == 49) {
            // The shortest possible WKB string (LINESTRING EMPTY) is 18 hex-chars (9 encoded bytes) long
            // This differentiates it from a geohash, which is always shorter than 18 characters.
            if (strlen($input) >= 18) {
                //@@TODO: Differentiate between EWKB and WKB -- check hex-char 10 or 11 (SRID bool indicator at encoded byte 5)
                return 'ewkb:1';
            }
        }

        // Detect GeoJSON - first char starts with {
        if ($bytes[1] == 123) {
            return 'json';
        }

        // Detect EWKT - first char is S
        if ($bytes[1] == 83) {
            return 'ewkt';
        }

        // Detect WKT - first char starts with P (80), L (76), M (77), or G (71)
        $wkt_chars = [80, 76, 77, 71];
        if (in_array($bytes[1], $wkt_chars)) {
            return 'wkt';
        }

        // Detect XML -- first char is <
        if ($bytes[1] == 60) {
            // grab the first 256 characters
            $string = substr($input, 0, 256);
            if (str_contains($string, '<kml')) {
                return 'kml';
            }
            if (str_contains($string, '<coordinate')) {
                return 'kml';
            }
            if (str_contains($string, '<gpx')) {
                return 'gpx';
            }
            if (str_contains($string, '<georss')) {
                return 'georss';
            }
            if (str_contains($string, '<rss')) {
                return 'georss';
            }
            if (str_contains($string, '<feed')) {
                return 'georss';
            }
        }

        // We need an 8 byte string for geohash and unpacked WKB / WKT
        /** @psalm-suppress ImpureFunctionCall */
        fseek($mem, 0);
        /** @psalm-suppress ImpureFunctionCall */
        $string = trim(fread($mem, 8));

        // Detect geohash - geohash ONLY contains lowercase chars and numerics
        preg_match('/[a-z0-9]+/', $string, $matches);
        if ($matches[0] == $string) {
            return 'geohash';
        }

        // What do you get when you cross an elephant with a rhino?
        // http://youtu.be/RCBn5J83Poc
        return false;
    }
}
