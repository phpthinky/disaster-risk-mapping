<?php

namespace App\Services;

/**
 * GeoService — Pure geometric / geographic utility functions.
 *
 * No database access. Stateless static methods only.
 */
class GeoService
{
    /**
     * Point-in-polygon test using the ray-casting algorithm.
     *
     * @param  float  $lat   Point latitude  (y)
     * @param  float  $lng   Point longitude (x)
     * @param  array  $ring  GeoJSON outer ring: [[lng, lat], [lng, lat], …]
     *                       (GeoJSON uses [longitude, latitude] ordering)
     * @return bool   true if the point is inside the polygon ring
     */
    public static function pointInPolygon(float $lat, float $lng, array $ring): bool
    {
        $inside = false;
        $n      = count($ring);
        $j      = $n - 1;

        for ($i = 0; $i < $n; $i++) {
            // GeoJSON order: [longitude, latitude]
            $xi = (float) $ring[$i][0]; // lng of vertex i
            $yi = (float) $ring[$i][1]; // lat of vertex i
            $xj = (float) $ring[$j][0]; // lng of vertex j
            $yj = (float) $ring[$j][1]; // lat of vertex j

            // Cast a horizontal ray rightward from ($lng, $lat) and count crossings
            $crossesY = ($yi > $lat) !== ($yj > $lat);
            $crossesX = $lng < (($xj - $xi) * ($lat - $yi) / ($yj - $yi) + $xi);

            if ($crossesY && $crossesX) {
                $inside = ! $inside;
            }

            $j = $i;
        }

        return $inside;
    }

    /**
     * Check whether a GeoJSON geometry string/array represents a valid Polygon
     * and return the outer ring array, or null if invalid.
     *
     * @param  string|null  $geoJson  Raw JSON string from the coordinates column
     * @return array|null
     */
    public static function outerRing(?string $geoJson): ?array
    {
        if (! $geoJson) {
            return null;
        }

        $geometry = json_decode($geoJson, true);

        if (
            ! is_array($geometry) ||
            ($geometry['type'] ?? '') !== 'Polygon' ||
            empty($geometry['coordinates'][0])
        ) {
            return null;
        }

        return $geometry['coordinates'][0];
    }
}
