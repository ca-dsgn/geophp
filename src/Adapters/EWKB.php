<?php

namespace Tochka\GeoPHP\Adapters;

use Tochka\GeoPHP\Geometry\GeometryInterface;

/**
 * EWKB (Extended Well Known Binary) Adapter
 *
 * @api
 */
class EWKB extends WKB
{
    /**
     * Read WKB binary string into geometry objects
     *
     * @param string $input An Extended-WKB binary string
     * @throws \Exception
     */
    public function read(string $input, bool $isHexString = false): GeometryInterface
    {
        if ($isHexString) {
            $input = pack('H*', $input);
        }

        // Open the wkb up in memory so we can examine the SRID
        $mem = fopen('php://memory', 'r+');
        fwrite($mem, $input);
        fseek($mem, 0);
        $baseInfo = unpack("corder/ctype/cz/cm/cs", fread($mem, 5));
        if ($baseInfo['s']) {
            $srid = current(unpack("Lsrid", fread($mem, 4)));
        } else {
            $srid = null;
        }
        fclose($mem);

        // Run the wkb through the normal WKB reader to get the geometry
        $geometry = parent::read($input);

        // If there is an SRID, add it to the geometry
        if ($srid) {
            $geometry->setSRID($srid);
        }

        return $geometry;
    }
}
