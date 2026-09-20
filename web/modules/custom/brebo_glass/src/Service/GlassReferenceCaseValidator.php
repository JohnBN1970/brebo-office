<?php

declare(strict_types=1);

namespace Drupal\brebo_glass\Service;

/**
 * Validates BREBO Glass Engine results against independent reference cases.
 */
final class GlassReferenceCaseValidator {

  public const VALIDATOR_VERSION = '2026-09-10.1';

  /**
   * @return array<string, mixed>
   */
  public function compare(array $engineResult, array $referenceCase): array {
    $required = ['reference_id', 'reference_source', 'reference_version', 'input', 'results'];
    foreach ($required as $key) {
      if (!array_key_exists($key, $referenceCase)) {
        return $this->blocked('incomplete_reference_case', ['Ontbrekende referentie-eigenschap: ' . $key . '.']);
      }
    }

    if (($referenceCase['verified'] ?? FALSE) !== TRUE) {
      return $this->blocked('unverified_reference_case', ['Referentiecase is niet geverifieerd.']);
    }

    $engineInput = $engineResult['input_snapshot'] ?? NULL;
    if (!is_array($engineInput) || $engineInput !== $referenceCase['input']) {
      return $this->blocked('input_mismatch', ['Engine-invoer en referentie-invoer zijn niet identiek.']);
    }

    $engineValues = $engineResult['results'] ?? [];
    $referenceValues = $referenceCase['results'];
    $metrics = ['stress_mpa', 'deflection_mm', 'utilisation_ratio'];
    $comparisons = [];

    foreach ($metrics as $metric) {
      $actual = $engineValues[$metric] ?? NULL;
      $expected = $referenceValues[$metric] ?? NULL;
      if (!is_numeric($actual) || !is_numeric($expected)) {
        return $this->blocked('missing_numeric_result', ['Numeriek resultaat ontbreekt voor ' . $metric . '.']);
      }
      $abs = abs((float) $actual - (float) $expected);
      $denominator = max(abs((float) $expected), 1.0e-9);
      $comparisons[$metric] = [
        'actual' => (float) $actual,
        'expected' => (float) $expected,
        'absolute_difference' => $abs,
        'relative_difference' => $abs / $denominator,
      ];
    }

    return [
      'status' => 'compared',
      'validator_version' => self::VALIDATOR_VERSION,
      'reference_id' => (string) $referenceCase['reference_id'],
      'reference_source' => (string) $referenceCase['reference_source'],
      'reference_version' => (string) $referenceCase['reference_version'],
      'comparisons' => $comparisons,
      'validated' => FALSE,
      'production_release' => FALSE,
      'note' => 'Deze vergelijking meet afwijkingen maar verleent zelf geen validatie. Acceptatiegrenzen worden per rekendomein apart vastgesteld.',
    ];
  }

  /** @return array<string, mixed> */
  private function blocked(string $gate, array $reasons): array {
    return [
      'status' => 'blocked',
      'gate' => $gate,
      'validator_version' => self::VALIDATOR_VERSION,
      'reasons' => $reasons,
      'validated' => FALSE,
      'production_release' => FALSE,
    ];
  }

}
