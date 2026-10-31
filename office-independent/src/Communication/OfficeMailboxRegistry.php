<?php

declare(strict_types=1);

namespace Brebo\Office\Communication;

use InvalidArgumentException;
use PDO;
use RuntimeException;

/**
 * Register existing provider mailboxes in Office. Does not create Zoho accounts,
 * accept passwords, or enable synchronization. Access control is enforced by caller.
 */
final class OfficeMailboxRegistry
{
    public function __construct(private readonly PDO $db)
    {
        if ($db->getAttribute(PDO::ATTR_ERRMODE) !== PDO::ERRMODE_EXCEPTION) {
            throw new InvalidArgumentException('PDO ERRMODE_EXCEPTION required.');
        }
    }

    public function register(string $address, string $displayName): int
    {
        $address = strtolower(trim($address));
        $displayName = trim($displayName);
        if (strlen($address) > 191 || !filter_var($address, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Invalid mailbox address.');
        }
        if ($displayName === '' || mb_strlen($displayName) > 255) {
            throw new InvalidArgumentException('Invalid display name.');
        }
        $stmt = $this->db->prepare(
            'INSERT INTO office_communication_mailboxes
             (mailbox_key, provider, display_name, enabled)
             VALUES (:address, :provider, :name, 0)'
        );
        $stmt->execute([':address' => $address, ':provider' => 'zoho', ':name' => $displayName]);
        $id = (int) $this->db->lastInsertId();
        if ($id < 1) throw new RuntimeException('Mailbox registration failed.');
        return $id;
    }

    /** @return list<array<string,mixed>> */
    public function all(): array
    {
        $stmt = $this->db->query(
            'SELECT id, mailbox_key, provider, display_name, enabled, created_at
             FROM office_communication_mailboxes ORDER BY id DESC'
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function rename(int $mailboxId, string $displayName): void
    {
        $displayName = trim($displayName);
        if ($mailboxId < 1 || $displayName === '' || mb_strlen($displayName) > 255) {
            throw new InvalidArgumentException('Invalid mailbox update.');
        }
        $stmt = $this->db->prepare(
            'UPDATE office_communication_mailboxes SET display_name = :name WHERE id = :id'
        );
        $stmt->execute([':name' => $displayName, ':id' => $mailboxId]);
        if ($stmt->rowCount() !== 1) throw new RuntimeException('Mailbox not updated.');
    }

    public function disable(int $mailboxId): void
    {
        if ($mailboxId < 1) throw new InvalidArgumentException('Invalid mailbox ID.');
        $stmt = $this->db->prepare(
            'UPDATE office_communication_mailboxes SET enabled = 0 WHERE id = :id'
        );
        $stmt->execute([':id' => $mailboxId]);
    }
}
