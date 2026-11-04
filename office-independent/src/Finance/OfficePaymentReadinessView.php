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
    'artifact_schema_present'=>'Versleutelde SEPA-opslag beschikbaar',
    'submission_schema_present'=>'Verzendregistratie beschikbaar',
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
    $html .= '<p class="notice" role="status">'
      . (($state['ready'] ?? false) === true
        ? 'Technische basiscontrole geslaagd. Bankverzending vanuit dit scherm blijft uitgeschakeld.'
        : 'Bankverzending geblokkeerd: een of meer voorwaarden ontbreken.')
      . '</p>';
    return $html;
  }
}
