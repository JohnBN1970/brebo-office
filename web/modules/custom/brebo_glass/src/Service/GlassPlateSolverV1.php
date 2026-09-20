<?php

declare(strict_types=1);

namespace Drupal\brebo_glass\Service;

/**
 * Restricted single-pane plate solver using externally verified coefficients.
 *
 * This service deliberately contains no built-in normative coefficients.
 * All coefficients and material properties must be supplied with provenance.
 * Output remains non-releasable until validated against independent reference
 * cases for the exact calculation domain.
 */
final class GlassPlateSolverV1 {

  public const SOLVER_ID = 'brebo_glass_plate_solver_v1';
  public const SOLVER_VERSION = '0.1.0-prototype';

  /**
   * @return array<string, mixed>
   */
  public function solve(array $input): array {
    $required = [
      'width_mm', 'height_mm', 'thickness_mm', 'design_pressure_kpa',
      'youngs_modulus_mpa', 'poisson_ratio',
      'deflection_coefficient', 'stress_coefficient',
      'coefficient_source', 'coefficient_source_version',
      'material_source', 'material_source_version',
      'support', 'load_case',
    ];

    $missing = [];
    foreach ($required as $key) {
      if (!array_key_exists($key, $input) || $input[$key] === '' || $input[$key] === NULL) {
        $missing[] = $key;
      }
    }
    if ($missing !== []) {
      return $this->blocked('incomplete_input', ['Ontbrekende solver-invoer: ' . implode(', ', $missing) . '.'], $input);
    }

    $width = (float) $input['width_mm'];
    $height = (float) $input['height_mm'];
    $thickness = (float) $input['thickness_mm'];
    $pressureMpa = (float) $input['design_pressure_kpa'] / 1000.0;
    $e = (float) $input['youngs_modulus_mpa'];
    $nu = (float) $input['poisson_ratio'];
    $kDeflection = (float) $input['deflection_coefficient'];
    $kStress = (float) $input['stress_coefficient'];

    if ($width <= 0 || $height <= 0 || $thickness <= 0 || $pressureMpa < 0 || $e <= 0) {
      return $this->blocked('invalid_input', ['Afmetingen, dikte en E-modulus moeten positief zijn; ontwerpdruk mag niet negatief zijn.'], $input);
    }
    if ($nu <= -1.0 || $nu >= 0.5 || $kDeflection <= 0 || $kStress <= 0) {
      return $this->blocked('invalid_coefficients', ['Poissongetal of plaatcoëfficiënten vallen buiten geldige invoergrenzen.'], $input);
    }

    // Use the short span as characteristic plate dimension. The numerical
    // coefficients must already correspond to the exact aspect ratio/support.
    $a = min($width, $height);
    $flexuralRigidity = ($e * pow($thickness, 3)) / (12.0 * (1.0 - pow($nu, 2)));
    $deflection = $kDeflection * $pressureMpa * pow($a, 4) / $flexuralRigidity;
    $stress = $kStress * $pressureMpa * pow($a, 2) / pow($thickness, 2);

    return [
      'solver_id' => self::SOLVER_ID,
      'solver_version' => self::SOLVER_VERSION,
      'status' => 'prototype_result',
      'domain' => [
        'support' => (string) $input['support'],
        'load_case' => (string) $input['load_case'],
        'aspect_ratio' => max($width, $height) / min($width, $height),
      ],
      'provenance' => [
        'coefficient_source' => (string) $input['coefficient_source'],
        'coefficient_source_version' => (string) $input['coefficient_source_version'],
        'material_source' => (string) $input['material_source'],
        'material_source_version' => (string) $input['material_source_version'],
      ],
      'results' => [
        'flexural_rigidity_nmm' => $flexuralRigidity,
        'deflection_mm' => $deflection,
        'stress_mpa' => $stress,
      ],
      'validated' => FALSE,
      'production_release' => FALSE,
      'reasons' => [
        'Numeriek resultaat is uitsluitend een prototype-uitkomst op basis van aangeleverde, traceerbare plaatcoëfficiënten.',
        'Geen productievrijgave totdat dit exacte rekendomein tegen onafhankelijke referentieberekeningen is gevalideerd.',
      ],
    ];
  }

  /** @return array<string, mixed> */
  private function blocked(string $gate, array $reasons, array $input): array {
    return [
      'solver_id' => self::SOLVER_ID,
      'solver_version' => self::SOLVER_VERSION,
      'status' => 'blocked',
      'gate' => $gate,
      'input_snapshot' => $input,
      'results' => ['flexural_rigidity_nmm' => NULL, 'deflection_mm' => NULL, 'stress_mpa' => NULL],
      'validated' => FALSE,
      'production_release' => FALSE,
      'reasons' => $reasons,
    ];
  }

}
