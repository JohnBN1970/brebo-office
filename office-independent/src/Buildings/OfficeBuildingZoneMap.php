<?php
declare(strict_types=1);
namespace Brebo\Office\Buildings;

final class OfficeBuildingZoneMap {
    private const R = 6378137.0;

    public static function point(float $lat, float $lon): array {
        if (!is_finite($lat) || !is_finite($lon) || abs($lat)>85 || abs($lon)>180) {
            throw new \InvalidArgumentException('invalid_map_coordinate');
        }
        return [self::R*deg2rad($lon),self::R*log(tan(M_PI/4+deg2rad($lat)/2))];
    }

    public static function circle(float $lat,float $lon,float $radius,array $bbox,int $width,int $height): array {
        if (!is_finite($radius) || $radius<=0 || $radius>5000 || count($bbox)!==4
            || $width<=0 || $height<=0 || $bbox[2]<=$bbox[0] || $bbox[3]<=$bbox[1]) {
            throw new \InvalidArgumentException('invalid_map_geometry');
        }
        [$x,$y]=self::point($lat,$lon);
        $scaleX=$width/($bbox[2]-$bbox[0]);
        $scaleY=$height/($bbox[3]-$bbox[1]);
        $projectedRadius=$radius/cos(deg2rad($lat));
        return ['cx'=>($x-$bbox[0])*$scaleX,'cy'=>($bbox[3]-$y)*$scaleY,
            'rx'=>$projectedRadius*$scaleX,'ry'=>$projectedRadius*$scaleY];
    }
}
