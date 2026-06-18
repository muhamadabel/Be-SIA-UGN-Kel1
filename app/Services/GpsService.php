<?php

namespace App\Services;

use App\Models\CampusSetting;
use Illuminate\Support\Collection;

class GpsService
{
    /**
     * Jari-jari bumi dalam meter (rata-rata).
     */
    private const EARTH_RADIUS_METERS = 6371000;

    /**
     * Menghitung jarak antara dua titik koordinat GPS (dalam meter)
     * menggunakan Haversine Formula.
     */
    public function calculateDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $lat1Rad  = deg2rad($lat1);
        $lat2Rad  = deg2rad($lat2);
        $deltaLat = deg2rad($lat2 - $lat1);
        $deltaLng = deg2rad($lng2 - $lng1);

        $a = sin($deltaLat / 2) ** 2
            + cos($lat1Rad) * cos($lat2Rad) * sin($deltaLng / 2) ** 2;

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return self::EARTH_RADIUS_METERS * $c;
    }

    /**
     * Memeriksa apakah koordinat dosen berada dalam radius sebuah kampus.
     */
    public function isWithinRadius(float $lat, float $lng, CampusSetting $setting): bool
    {
        $distance = $this->calculateDistance(
            $lat, $lng,
            (float) $setting->latitude,
            (float) $setting->longitude
        );

        return $distance <= $setting->radius_meter;
    }

    /**
     * Multi-campus logic: mengecek semua kampus aktif dan mengembalikan
     * kampus pertama yang cocok beserta jarak dosen ke kampus tersebut.
     *
     * Iterasi dilakukan pada Collection yang sudah di-load agar tidak
     * melakukan query N+1 ke database.
     *
     * @param  float       $lat
     * @param  float       $lng
     * @param  Collection  $campusSettings  Koleksi CampusSetting yang aktif
     * @return array{campus: CampusSetting, distance_meter: float}|null
     *         Mengembalikan null jika tidak ada kampus yang cocok.
     */
    public function findNearestActiveCampus(float $lat, float $lng, Collection $campusSettings): ?array
    {
        $matched = null;

        foreach ($campusSettings as $setting) {
            $distance = $this->calculateDistance(
                $lat, $lng,
                (float) $setting->latitude,
                (float) $setting->longitude
            );

            if ($distance <= $setting->radius_meter) {
                // Kembalikan kampus pertama yang cocok
                $matched = [
                    'campus'         => $setting,
                    'distance_meter' => round($distance, 2),
                ];
                break;
            }
        }

        return $matched;
    }
}
