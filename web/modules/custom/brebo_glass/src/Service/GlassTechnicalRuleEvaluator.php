<?php

declare(strict_types=1);

namespace Drupal\brebo_glass\Service;

/**
 * Performs a conservative technical pre-check before expert approval.
 */
final class GlassTechnicalRuleEvaluator {

  private const IMPACT_RISK_APPLICATIONS = [
    'door',
    'adjacent_door',
    'low_level',
    'wet_area',
    'ceiling',
  ];

  /**
   * @param array<string, mixed> $position
   *
   * @return array{state: string, issues: string[]}
   */
  public function evaluate(array $position): array {
    $issues = [];
    $blocked = FALSE;
    $application = (string) ($position['application_type'] ?? '');
    $glassType = (string) ($position['glass_type'] ?? '');
    $verified = (bool) ($position['measurement_verified'] ?? FALSE);
    $declaration = trim((string) ($position['performance_declaration_ref'] ?? ''));
    $safetyClass = trim((string) ($position['safety_class'] ?? ''));
    $fireClass = trim((string) ($position['fire_class'] ?? ''));

    if ((bool) ($position['wind_check_required'] ?? TRUE) && ($position['wind_check_state'] ?? 'blocked') !== 'passed') {
      $issues[] = 'Windbelasting is niet aantoonbaar akkoord; technische vrijgave blijft geblokkeerd.';
      $blocked = TRUE;
    }

    if ($application === '') {
      $issues[] = 'Toepassing ontbreekt; technische beoordeling is niet mogelijk.';
      $blocked = TRUE;
    }

    if (!$verified) {
      $issues[] = 'Maatvoering is nog niet handmatig gecontroleerd.';
      $blocked = TRUE;
    }

    if (in_array($application, self::IMPACT_RISK_APPLICATIONS, TRUE)) {
      if (!in_array($glassType, ['laminated', 'tempered'], TRUE)) {
        $issues[] = 'Risicotoepassing vraagt veiligheidsglas; gekozen glastype is niet als gelaagd of gehard geregistreerd.';
        $blocked = TRUE;
      }
      if ($safetyClass === '') {
        $issues[] = 'Letselveiligheidsclassificatie ontbreekt en moet door een deskundige worden vastgesteld.';
      }
    }

    if (in_array($application, ['fall_protection', 'overhead'], TRUE)) {
      if ($glassType !== 'laminated') {
        $issues[] = 'Doorval- of boventoepassing vereist restdragend gedrag; leg gelaagd glas of een onderbouwde gelijkwaardige oplossing vast.';
        $blocked = TRUE;
      }
      $issues[] = 'Constructieve onderbouwing, bevestiging en projectspecifieke belastingen moeten afzonderlijk worden gecontroleerd.';
    }

    if ($application === 'fire_separation') {
      if ($glassType !== 'fire_resistant') {
        $issues[] = 'Brandwerende scheiding is niet als brandwerend glas gespecificeerd.';
        $blocked = TRUE;
      }
      if ($fireClass === '') {
        $issues[] = 'Brandwerendheidsclassificatie en vereiste prestatie ontbreken.';
        $blocked = TRUE;
      }
    }

    if ($application !== 'standard' && $declaration === '') {
      $issues[] = 'Prestatieverklaring, productcertificaat of gelijkwaardige technische onderbouwing ontbreekt.';
    }

    if ($blocked) {
      return ['state' => 'blocked', 'issues' => $issues];
    }

    return [
      'state' => $issues === [] ? 'passed' : 'expert_review',
      'issues' => $issues,
    ];
  }

}
