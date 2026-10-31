<?php

declare(strict_types=1);

namespace Brebo\Office\Communication;

use PDO;

/**
 * Explicit per-operator mailbox grants. Absence of a grant denies access.
 * Provision grants separately after verifying Office operator identity.
 */
final class OfficeCommunicationMailboxAccess
{
    public function __construct(private readonly PDO $db) {}

    public function canRead(string $operatorId, int $mailboxId): bool
    {
        if ($operatorId === '' || $mailboxId < 1) {
            return false;
        }
        $stmt = $this->db->prepare(
            'SELECT 1 FROM office_communication_mailbox_access a
             INNER JOIN office_communication_mailboxes m ON m.id = a.mailbox_id
             WHERE a.operator_id = :operator AND a.mailbox_id = :mailbox
               AND a.can_read = 1 AND m.enabled = 1 LIMIT 1'
        );
        $stmt->execute([':operator' => $operatorId, ':mailbox' => $mailboxId]);
        return $stmt->fetchColumn() !== false;
    }
}
