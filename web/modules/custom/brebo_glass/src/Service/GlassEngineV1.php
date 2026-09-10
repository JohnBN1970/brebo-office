<?php

declare(strict_types=1);

namespace Drupal\brebo_glass\Service;

/**
 * Fail-closed contract for the future constructive BREBO Glass Engine.
 *
 * V1 deliberately performs no unverified structural calculation. It records
 * the exact engineering inputs, validates domain completeness, and returns a
 * traceable non-release result until a calculation method has been validated
 * against independent reference cases.
 */
final class GlassEngineV1 {

  public const ENGINE_ID = 'brebo_glass_engine';
  public const ENGINE_VERSION = '1.0.0-contract';
  public const CALCULATION_METHOD_STATUS = 'not_validated';

  /**
   * @return array<string, mixed>
   */
  public function evaluate(array $input): array {
    $required = [
      'width_mm',
      'height_mm',
      'glass_type',
      'orientation_deg',
      'support',
      'application',
      'load_case',
      'design_pressure_kpa',
      'design_pressure_source',
      'norm_reference',
    ];

    $missing = [];
    foreach ($required as $key) {
      if (!array_key_exists($key, $input) || $input[$key] === '' || $input[$key] === NULL) {
        $missing[] = $key;
      }
    }

    $snapshot = [
      'width_mm' => isset($input['width_mm']) ? (float) $input['width_mm'] : NULL,
      'height_mm' => isset($input['height_mm']) ? (float) $input['height_mm'] : NULL,
      'glass_type' => (string) ($input['glass_type'] ?? ''),
      'orientation_deg' => isset($input['orientation_deg']) ? (float) $input['orientation_deg'] : NULL,
      'support' => (string) ($input['support'] ?? ''),
      'application' => (string) ($input['application'] ?? ''),
      'load_case' => (string) ($input['load_case'] ?? ''),
      'design_pressure_kpa' => isset($input['design_pressure_kpa']) ? (float) $input['design_pressure_kpa'] : NULL,
      'design_pressure_source' => (string) ($input['design_pressure_source'] ?? ''),
      'norm_reference' => (string) ($input['norm_reference'] ?? ''),
      'candidate_composition' => $input['candidate_composition'] ?? NULL,
    ];

    if ($missing !== []) {
      return $this->result('blocked', 'incomplete_input', $snapshot, [
        'Ontbrekende constructieve invoer: ' . implode(', ', $missing) . '.',
      ]);
    }

    if ($snapshot['width_mm'] <= 0 || $snapshot['height_mm'] <= 0 || $snapshot['design_pressure_kpa'] < 0) {
      return $this->result('blocked', 'invalid_input', $snapshot, [
        'Afmetingen moeten positief zijn en ontwerpdruk mag niet negatief zijn.',
      ]);
    }

    return $this->result('validation_required', 'calculation_method_not_validated', $snapshot, [
      'Constructieve invoer is compleet en traceerbaar.',
      'De BREBO Glass Engine mag nog geen spanning, doorbuiging, benuttingsgraad of glasdikte vrijgeven totdat de rekenmethode voor dit domein onafhankelijk is gevalideerd.',
    ]);
  }

  /**
   * @return array<string, mixed>
   */
  private function result(string $status, string $gate, array $input, array $reasons): array {
    return [
      'engine_id' => self::ENGINE_ID,
      'engine_version' => self::ENGINE_VERSION,
      'calculation_method_status' => self::CALCULATION_METHOD_STATUS,
      'status' => $status,
      'gate' => $gate,
      'input_snapshot' => $input,
      'results' => [
        'stress_mpa' => NULL,
        'deflection_mm' => NULL,
        'utilisation_ratio' => NULL,
        'recommended_composition' => NULL,
      ],
      'reasons' => $reasons,
      'validated' => FALSE,
      'production_release' => FALSE,
    ];
  }

}
