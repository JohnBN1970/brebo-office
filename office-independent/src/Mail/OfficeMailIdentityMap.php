<?php
declare(strict_types=1);

namespace Brebo\Office\Mail;

use PDO;
use RuntimeException;

/**
 * Explicit, verified cross-system identity mapping.
 * No fallback to coincidentally equal numeric user IDs.
 */
final class OfficeMailIdentityMap {
  public function __construct(private readonly PDO $db) {}

  public function drupalUidForOfficeOperator(int $operatorId): int {
    if ($operatorId <= 0) {
      throw new RuntimeException('Ongeldige Office-gebruiker.');
    }
    $stmt = $this->db->prepare(
      'SELECT drupal_uid FROM office_mail_identity_map '
      . 'WHERE office_operator_id = :operator AND verified_at IS NOT NULL AND revoked_at IS NULL'
    );
    $stmt->execute([':operator' => $operatorId]);
    $uid = $stmt->fetchColumn();
    if ($uid === FALSE || (int) $uid <= 0) {
      throw new RuntimeException('Geen geverifieerde persoonlijke mailboxidentiteit.');
    }
    return (int) $uid;
  }
}
