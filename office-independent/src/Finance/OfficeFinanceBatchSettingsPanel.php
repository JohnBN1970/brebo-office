<?php
declare(strict_types=1);
namespace Brebo\Office\Finance;

use PDO;

/**
 * Safe integration boundary for the Office settings controller.
 * The caller must obtain viewer identity and the settings-admin decision
 * from the existing Office session/ACL, never from request parameters.
 * This is not an HTTP route and cannot change permissions or send payments.
 */
final class OfficeFinanceBatchSettingsPanel {
  public function __construct(private readonly PDO $db) {}

  public function render(
    string $authenticatedViewerId,
    bool $serverVerifiedSettingsAdmin,
    string $targetOperatorId
  ): string {
    if (!$serverVerifiedSettingsAdmin || $authenticatedViewerId === ''
      || $targetOperatorId === '') {
      throw new \DomainException('Office settings access denied');
    }
    $accounts = new OfficeFinanceBatchPermissionsOverview($this->db);
    if (!$accounts->forOperator($authenticatedViewerId)['active']) {
      throw new \DomainException('Office settings access denied');
    }
    $target = $accounts->forOperator($targetOperatorId);
    $safeId = htmlspecialchars($targetOperatorId, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $status = $target['active'] ? 'Actief' : 'Inactief of onbekend';
    return '<section class="office-settings-finance-batch" aria-label="Finance betaalbatchrechten">'
      . '<h2>Betaalbatchrechten</h2><p>Medewerker: ' . $safeId . ' (' . $status . ')</p>'
      . OfficeFinanceBatchPermissionsView::render($target['permissions'])
      . '</section>';
  }
}
