<?php
declare(strict_types=1);
namespace Brebo\Office\Mail;

use PDO;
use RuntimeException;

final class OfficeTestMailboxRegistration {
    public function __construct(private readonly PDO $db, private readonly OfficeTestMailboxProvisioning $provisioning) {}

    public function register(string $address): array {
        if ($this->db->query('SELECT DATABASE()')->fetchColumn() !== 'brebo_office_test') {
            throw new RuntimeException('Wrong database');
        }
        $address = strtolower(trim($address));
        // Verify schema before any gateway mutation.
        $this->db->query('SELECT gateway_ref FROM office_mail_mailboxes LIMIT 0');
        $stmt = $this->db->prepare('SELECT id FROM office_mail_mailboxes WHERE address = :address');
        $stmt->execute(['address'=>$address]);
        if ($stmt->fetchColumn() !== false) {
            throw new RuntimeException('Mailbox bestaat al.');
        }
        $result = $this->provisioning->stage($address);
        $reference = $result['reference'];
        if (strlen($reference) > 190) {
            throw new RuntimeException('Gatewayreferentie te lang.');
        }
        $stmt = $this->db->prepare("INSERT INTO office_mail_mailboxes
            (address,label,mailbox_type,credential_ref,gateway_ref,active)
            VALUES (:address,:label,'functional','',:reference,0)");
        $stmt->execute(['address'=>$result['address'],'label'=>$result['address'],'reference'=>$reference]);
        return $result;
    }
}
