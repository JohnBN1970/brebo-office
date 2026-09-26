<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Service;

/**
 * Canonical BREBO kozijn price engine.
 *
 * Owns internal purchasing/calibration logic. Public clients receive only the
 * deliberately limited indication projection from toPublicIndication().
 */
final class KozijnPriceEngine {

  public const MODEL_VERSION = '2026-09-21.2';
  private const BREBO_DISCOUNT_PERCENT = 49.0;

  private const CALIBRATION = [
    'ideal4000' => ['fixed_1200x1200' => 242.17, 'dk_delta' => 118.10],
    'ideal7000_nl' => ['fixed_1200x1200' => 325.71, 'fixed_980x1360' => 311.06, 'dk_delta_mean' => 119.62],
  ];

  public function __construct(private readonly KozijnPriceObservationProviderInterface $observations) {}

  /** @param array<string,mixed> $configuration */
  public function estimate(array $configuration): array {
    $width = (int) ($configuration['width_mm'] ?? 0);
    $height = (int) ($configuration['height_mm'] ?? 0);
    $system = (string) ($configuration['system'] ?? 'ideal7000_nl');
    $type = (string) ($configuration['type'] ?? '');
    $fields = (int) ($configuration['fields'] ?? 1);

    if ($width < 400 || $height < 400 || $fields !== 1 || !in_array($type, ['vast', 'draai-kiep'], TRUE)) {
      return $this->unsupported('uncalibrated_geometry_or_function');
    }

    $points = $this->observations->approved($system, $type, $fields);
    if ($points === []) {
      return $this->unsupported('no_approved_calibration');
    }

    $gross = $this->estimateFromApprovedPoints($system, $width, $height, $type, $points);
    if ($gross === NULL) {
      return $this->unsupported('uncalibrated_system');
    }
    if ($gross <= 0) {
      return $this->unsupported('outside_calibrated_price_domain');
    }

    $band = max(15.0, $gross * 0.08);
    $discountFactor = 1 - self::BREBO_DISCOUNT_PERCENT / 100;
    $grossLow = max(0, $gross - $band);
    $grossHigh = $gross + $band;
    $net = $gross * $discountFactor;

    return [
      'supported' => TRUE,
      'model_version' => self::MODEL_VERSION,
      'reliability' => 'C',
      'supplier_gross' => round($gross, 2),
      'supplier_gross_low' => round($grossLow, 2),
      'supplier_gross_high' => round($grossHigh, 2),
      'net_purchase_low' => round($grossLow * $discountFactor, 2),
      'net_purchase_expected' => round($net, 2),
      'net_purchase_high' => round($grossHigh * $discountFactor, 2),
      'brebo_discount_percent' => self::BREBO_DISCOUNT_PERCENT,
    ];
  }

  /** @param array<string,mixed> $estimate */
  public function toPublicIndication(array $estimate): array {
    if (($estimate['supported'] ?? FALSE) !== TRUE) {
      return [
        'status' => 'insufficient_calibration',
        'model_version' => $estimate['model_version'] ?? self::MODEL_VERSION,
        'message' => 'Voor deze configuratie is nog geen betrouwbare prijsindicatie beschikbaar.',
      ];
    }

    // Never expose supplier price, purchase price, discount or margin data.
    return [
      'status' => 'indicative',
      'model_version' => $estimate['model_version'],
      'reliability' => $estimate['reliability'],
      'message' => 'Configuratie herkend; verkoopprijsopbouw volgt uit de commerciële calculatielaag.',
    ];
  }

  /** @param array<int,array<string,mixed>> $points */
  private function estimateFromApprovedPoints(string $system, int $width, int $height, string $type, array $points): ?float {
    foreach ($points as $point) {
      if ((int) $point['width_mm'] === $width && (int) $point['height_mm'] === $height) {
        return (float) $point['supplier_gross'];
      }
    }

    if ($system === 'ideal4000') {
      $fixed = $this->observations->approved($system, 'vast', 1);
      $anchor = $this->findPoint($fixed, 1200, 1200);
      $dk = $this->findPoint($points, 1200, 1200);
      if ($anchor === NULL) return NULL;
      $areaDelta = (($width * $height) - 1_440_000) / 1_000_000;
      return $anchor + (85.0 * $areaDelta) + ($type === 'draai-kiep' && $dk !== NULL ? $dk - $anchor : 0.0);
    }

    if ($system === 'ideal7000_nl') {
      $fixed = $this->observations->approved($system, 'vast', 1);
      $v1 = $this->findPoint($fixed, 1200, 1200);
      $v2 = $this->findPoint($fixed, 980, 1360);
      if ($v1 === NULL || $v2 === NULL) return NULL;
      $perimeter = 2 * ($width + $height) / 1000;
      $base = $v1 + (($perimeter - 4.8) * (($v1 - $v2) / (4.8 - 4.68)));
      if ($type === 'vast') return $base;
      $dk1 = $this->findPoint($points, 1200, 1200);
      $dk2 = $this->findPoint($points, 980, 1360);
      if ($dk1 === NULL || $dk2 === NULL) return NULL;
      return $base + ((($dk1 - $v1) + ($dk2 - $v2)) / 2);
    }

    return NULL;
  }

  /** @param array<int,array<string,mixed>> $points */
  private function findPoint(array $points, int $width, int $height): ?float {
    foreach ($points as $point) {
      if ((int) $point['width_mm'] === $width && (int) $point['height_mm'] === $height) {
        return (float) $point['supplier_gross'];
      }
    }
    return NULL;
  }

  private function legacyIdeal4000(int $width, int $height, string $type): float {
    $areaDelta = (($width * $height) - 1_440_000) / 1_000_000;
    return self::CALIBRATION['ideal4000']['fixed_1200x1200'] + (85.0 * $areaDelta)
      + ($type === 'draai-kiep' ? self::CALIBRATION['ideal4000']['dk_delta'] : 0.0);
  }

  private function legacyIdeal7000Nl(int $width, int $height, string $type): float {
    $p1 = 4.8; $v1 = self::CALIBRATION['ideal7000_nl']['fixed_1200x1200'];
    $p2 = 4.68; $v2 = self::CALIBRATION['ideal7000_nl']['fixed_980x1360'];
    $perimeter = 2 * ($width + $height) / 1000;
    $fixed = $v1 + (($perimeter - $p1) * (($v1 - $v2) / ($p1 - $p2)));
    return $fixed + ($type === 'draai-kiep' ? self::CALIBRATION['ideal7000_nl']['dk_delta_mean'] : 0.0);
  }

  private function unsupported(string $reason): array {
    return ['supported' => FALSE, 'model_version' => self::MODEL_VERSION, 'reliability' => 'E', 'reason' => $reason];
  }

}
