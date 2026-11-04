<?php
declare(strict_types=1);
namespace Brebo\Office\Finance;

/**
 * Server-rendered, read-only permission overview for Office settings.
 * Caller must authenticate and authorize the administrator before rendering.
 * No grant changes or payment operations occur here.
 */
final class OfficeFinanceBatchPermissionsView {
  public static function render(array $serverResolvedGrants): string {
    $names = [
      OfficeFinanceBatchPermissionCatalog::CREATE => 'Betaalbatches aanmaken',
      OfficeFinanceBatchPermissionCatalog::REVIEW => 'Betaalbatches controleren',
      OfficeFinanceBatchPermissionCatalog::RELEASE => 'Betaalbatches vrijgeven',
    ];
    $html = '<section class="office-finance-batch-permissions"><h2>Finance - betaalbatchrechten</h2>';
    $html .= '<p>Alleen lezen. Rechten worden beheerd via de Office-autorisatie.</p>';
    $html .= '<table><thead><tr><th scope="col">Recht</th><th scope="col">Status</th></tr></thead><tbody>';
    foreach ($names as $permission => $label) {
      $allowed = OfficeFinanceBatchPermissionCatalog::hasGrant($serverResolvedGrants, $permission);
      $html .= '<tr><th scope="row">' . htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '</th><td>' . ($allowed ? 'Toegekend' : 'Niet toegekend') . '</td></tr>';
    }
    return $html . '</tbody></table></section>';
  }
}
