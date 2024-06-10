<?php

namespace Tochka\GeoPHP\Adapters;

use Tochka\GeoPHP\Geometry\GeometryInterface;

/**
 * EWKB (Extended Well Known Binary) Adapter
 *
 * @api
 * @psalm-immutable
 */
readonly class EWKB extends WKB
{
    /**
     * Read WKB binary string into geometry objects
     *
     * @param string $input An Extended-WKB binary string
     * @throws \Exception
     */
    public function read(string $input, bool $isHexString = false, ?\GEOSGeometry $geos = null, ?int $srid = null): GeometryInterface
    {
        if ($isHexString) {
            $input = pack('H*', $input);
        }

        // Open the wkb up in memory so we can examine the SRID
        /** @psalm-suppress ImpureFunctionCall */
        $mem = fopen('php://memory', 'r+');
        /** @psalm-suppress ImpureFunctionCall */
        fwrite($mem, $input);
        /** @psalm-suppress ImpureFunctionCall */
        fseek($mem, 0);
        /** @psalm-suppress ImpureFunctionCall */
        $baseInfo = unpack("corder/ctype/cz/cm/cs", fread($mem, 5));
        if ($baseInfo['s']) {
            /** @psalm-suppress ImpureFunctionCall */
            $srid = current(unpack("Lsrid", fread($mem, 4)));
        } else {
            $srid = null;
        }
        /** @psalm-suppress ImpureFunctionCall */
        fclose($mem);

        // Run the wkb through the normal WKB reader to get the geometry
        return parent::read($input, geos: $geos, srid: $srid);
    }
}
