<?php

declare(strict_types=1);

namespace Brebo\Office\Inzet;

/**
 * Pure personnel-profile input contract. Does not create an identity, account,
 * project membership or OnSite invitation.
 */
final class OfficePersonnelProfileInput {
  public const LANGUAGES = ['nl', 'en', 'pl', 'ro', 'tr', 'de'];
  public const DIAL_CODES = ['NL' => '31', 'BE' => '32', 'DE' => '49', 'PL' => '48', 'RO' => '40', 'TR' => '90'];

  public static function phone(string $country, string $nationalNumber): string {
    $country = strtoupper(trim($country));
    if (!isset(self::DIAL_CODES[$country])) {
      throw new \InvalidArgumentException('Unsupported telephone country');
    }
    $digits = preg_replace('/[\s().-]+/', '', trim($nationalNumber));
    if (!is_string($digits) || !preg_match('/^\+?[0-9]+$/D', $digits)) {
      throw new \InvalidArgumentException('Invalid telephone number');
    }
    $dial = self::DIAL_CODES[$country];
    if (str_starts_with($digits, '+')) {
      if (!str_starts_with($digits, '+' . $dial)) {
        throw new \InvalidArgumentException('Telephone country mismatch');
      }
      $digits = substr($digits, strlen($dial) + 1);
    } elseif (str_starts_with($digits, '00' . $dial)) {
      $digits = substr($digits, strlen($dial) + 2);
    }
    $digits = ltrim($digits, '0');
    $phone = '+' . $dial . $digits;
    if (!preg_match('/^\+[1-9][0-9]{7,14}$/D', $phone)) {
      throw new \InvalidArgumentException('Invalid international telephone number');
    }
    return $phone;
  }

  public static function language(string $nationality, ?string $preferredLanguage): string {
    $defaults = ['NL' => 'nl', 'BE' => 'nl', 'GB' => 'en', 'PL' => 'pl', 'RO' => 'ro', 'TR' => 'tr', 'DE' => 'de'];
    $language = strtolower(trim($preferredLanguage ?? ''));
    if ($language === '') {
      $language = $defaults[strtoupper(trim($nationality))] ?? 'nl';
    }
    if (!in_array($language, self::LANGUAGES, true)) {
      throw new \InvalidArgumentException('Unsupported preferred language');
    }
    return $language;
  }

  /** @param list<string> $skills @return list<string> */
  public static function skills(array $skills): array {
    $clean = [];
    foreach ($skills as $skill) {
      if (!is_string($skill) || trim($skill) === '' || mb_strlen(trim($skill)) > 120) {
        throw new \InvalidArgumentException('Invalid skill');
      }
      $value = trim($skill);
      $clean[mb_strtolower($value)] = $value;
    }
    if (count($clean) > 100) throw new \InvalidArgumentException('Too many skills');
    return array_values($clean);
  }
}
