<?php

declare(strict_types=1);

namespace Drupal\brebo_glass\Service;

/**
 * Chooses the permitted calculation route without inventing engineering data.
 */
final class GlassCalculationRouteResolver {

  public const ENGINE_VERSION = '2026-09-09.1';

  /**
   * @return array<string, mixed>
   */
  public function resolve(array $input): array {
    $type = (string) ($input['glass_type'] ?? '');
    $orientation = isset($input['orientation_deg']) ? (float) $input['orientation_deg'] : NULL;
    $support = (string) ($input['support'] ?? '');
    $application = (string) ($input['application'] ?? 'standard');

    $missing = [];
    foreach (['width_mm', 'height_mm', 'glass_type', 'orientation_deg', 'support', 'application'] as $key) {
      if (!isset($input[$key]) || $input[$key] === '') {
        $missing[] = $key;
      }
    }
    if ($missing !== []) {
      return $this->result('blocked', 'needs_input', ['Ontbrekende invoer: ' . implode(', ', $missing) . '.']);
    }

    if ((float) $input['width_mm'] <= 0 || (float) $input['height_mm'] <= 0) {
      return $this->result('blocked', 'invalid_input', ['Breedte en hoogte moeten positief zijn.']);
    }

    $tableEligibleTypes = ['float', 'pvb_laminated', 'insulating_double'];
    $tableEligible = in_array($type, $tableEligibleTypes, TRUE)
      && $orientation !== NULL && $orientation >= 80.0 && $orientation <= 100.0
      && $support === 'four_sided'
      && !in_array($application, ['overhead', 'fall_protection'], TRUE);

    if ($tableEligible) {
      return $this->result(
        'ready',
        'verified_table_route',
        ['Situatie kan via een geverifieerde tabelroute worden beoordeeld zodra de bijbehorende numerieke brondata beschikbaar is.'],
      );
    }

    $supportedTypes = ['float', 'pvb_laminated', 'tempered', 'heat_strengthened', 'insulating_double', 'insulating_triple'];
    $supportedSupports = ['two_sided', 'three_sided', 'four_sided', 'clamped'];
    if (!in_array($type, $supportedTypes, TRUE) || !in_array($support, $supportedSupports, TRUE)) {
      return $this->result('blocked', 'outside_defined_scope', ['Situatie valt buiten het vastgelegde rekendomein.']);
    }

    return $this->result(
      'technical_calculation_required',
      'constructive_route',
      ['Een constructieve glasberekening is vereist; automatische productievrijgave blijft geblokkeerd totdat de BREBO Glass Engine voor dit domein is gevalideerd.'],
    );
  }

  /** @return array<string, mixed> */
  private function result(string $status, string $route, array $reasons): array {
    return [
      'status' => $status,
      'route' => $route,
      'engine_version' => self::ENGINE_VERSION,
      'reasons' => $reasons,
      'production_release' => FALSE,
    ];
  }

}
