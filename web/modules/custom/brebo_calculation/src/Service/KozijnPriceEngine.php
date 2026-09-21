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

  public const MODEL_VERSION = '2026-09-21.1';
  private const BREBO_DISCOUNT_PERCENT = 49.0;

  private const CALIBRATION = [
    'ideal4000' => ['fixed_1200x1200' => 242.17, 'dk_delta' => 118.10],
    'ideal7000_nl' => ['fixed_1200x1200' => 325.71, 'fixed_980x1360' => 311.06, 'dk_delta_mean' => 119.62],
  ];

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

    $gross = match ($system) {
      'ideal4000' => $this->ideal4000($width, $height, $type),
      'ideal7000_nl' => $this->ideal7000Nl($width, $height, $type),
      default => NULL,
    };
    if ($gross === NULL) {
      return $this->unsupported('uncalibrated_system');
    }
    if ($gross <= 0) {
      return $this->unsupported('outside_calibrated_price_domain');
    }

    $band = max(15.0, $gross * 0.08);
    $net = $gross * (1 - self::BREBO_DISCOUNT_PERCENT / 100);

    return [
      'supported' => TRUE,
      'model_version' => self::MODEL_VERSION,
      'reliability' => 'C',
      'supplier_gross' => round($gross, 2),
      'supplier_gross_low' => round(max(0, $gross - $band), 2),
      'supplier_gross_high' => round($gross + $band, 2),
      'net_purchase_expected' => round($net, 2),
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

  private function ideal4000(int $width, int $height, string $type): float {
    $areaDelta = (($width * $height) - 1_440_000) / 1_000_000;
    return self::CALIBRATION['ideal4000']['fixed_1200x1200'] + (85.0 * $areaDelta)
      + ($type === 'draai-kiep' ? self::CALIBRATION['ideal4000']['dk_delta'] : 0.0);
  }

  private function ideal7000Nl(int $width, int $height, string $type): float {
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
