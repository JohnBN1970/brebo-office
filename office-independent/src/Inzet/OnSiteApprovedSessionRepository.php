<?php
declare(strict_types=1);
namespace Brebo\Office\Inzet;
use PDO;
/** Compatibility facade: approval readers share one Office ledger. */
final class OnSiteApprovedSessionRepository {
  public function __construct(private PDO $db) {}
  public function forEmployee(string $verifiedPersonId): array {
    require_once __DIR__.'/OnSiteHoursApprovalRepository.php';
    return (new OnSiteHoursApprovalRepository($this->db))->approvedSessionsForPerson($verifiedPersonId);
  }
}
