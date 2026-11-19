<?php
declare(strict_types=1);

namespace Brebo\Office\Buildings;

use InvalidArgumentException;

/**
 * Physical locations within a building. These are independent from dwelling
 * types and address bulk assignment; common parts need no dwelling address.
 */
final class OfficeBuildingPhysicalLocation {
  public const CATEGORIES=[
    'front_facade'=>'Voorgevel',
    'rear_facade'=>'Achtergevel',
    'left_side_facade'=>'Linkerzijgevel',
    'right_side_facade'=>'Rechterzijgevel',
    'gable_facade'=>'Kopgevel',
    'roof'=>'Dak',
    'stairwell'=>'Trappenhuis',
    'gallery'=>'Galerij',
    'entrance'=>'Entree',
    'balcony'=>'Balkon',
    'basement'=>'Kelder / souterrain',
    'other'=>'Overige locatie'
  ];

  public static function category(string $value): string {
    if (!array_key_exists($value,self::CATEGORIES)) {
      throw new InvalidArgumentException('unknown_physical_location');
    }
    return $value;
  }

  public static function label(string $value): string {
    return self::CATEGORIES[self::category($value)];
  }
}
