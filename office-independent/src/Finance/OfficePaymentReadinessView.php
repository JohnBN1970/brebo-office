<?php
declare(strict_types=1);
namespace Brebo\Office\Finance;

/** Presentation-only status. Never renders a bank send action. */
final class OfficePaymentReadinessView {
  private const LABELS = [
    'operator_authorized'=>'Bevoegdheid medewerker',
    'bank_payments_enabled'=>'Bankverzending geactiveerd',
    'canonical_schema_verified'=>'Office-betaalschema geverifieerd',
    'mandate_schema_present'=>'Betaalmandaten beschikbaar',
    'mandate_audit_schema_present'=>'Auditregistratie betaalmandaten beschikbaar',
    'mandate_audit_transactional_verified'=>'Auditregistratie betaalmandaten transactioneel',
    'artifact_schema_present'=>'Versleutelde SEPA-opslag beschikbaar',
    'submission_schema_present'=>'Verzendregistratie beschikbaar',
    'submission_uniqueness_verified'=>'Unieke batch- en verzendsleutels',
    'artifact_uniqueness_verified'=>'Uniek versleuteld bestand per betaalbatch',
    'mandate_uniqueness_verified'=>'Uniek betaalmandaat per medewerker',
    'payment_column_types_verified'=>'MySQL-veldtypen gecontroleerd',
    'payment_transactional_tables_verified'=>'InnoDB-transactietabellen gecontroleerd',
    'canonical_batch_schema_present'=>'Vrijgegeven betaalbatches beschikbaar',
    'bank_provider_contract_verified'=>'Bankprovider-contract geverifieerd',
  ];

  public static function render(?array $state): string {
    if ($state === null || !is_array($state['checks'] ?? null)) {
      return '<p class="notice" role="status">Bankgereedheid nog niet gecontroleerd. Verzenden is niet beschikbaar.</p>';
    }
    $html = '<h3>Technische gereedheidscontrole</h3><ul>';
    foreach (self::LABELS as $key=>$label) {
      $ok = ($state['checks'][$key] ?? false) === true;
      $html .= '<li>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8')
        . ': <strong>' . ($ok ? 'Gecontroleerd' : 'Niet gereed') . '</strong></li>';
    }
    $html .= '</ul>';
    // A caller-provided ready flag cannot override a failed or missing gate.
    $allChecksPassed = true;
    foreach (array_keys(self::LABELS) as $key) {
      if (($state['checks'][$key] ?? false) !== true) $allChecksPassed = false;
    }
    $html .= '<p class="notice" role="status">'
      . (($state['ready'] ?? false) === true && $allChecksPassed
        ? 'Technische basiscontrole geslaagd. Bankverzending vanuit dit scherm blijft uitgeschakeld.'
        : 'Bankverzending geblokkeerd: een of meer voorwaarden ontbreken.')
      . '</p>';
    return $html;
  }
}
