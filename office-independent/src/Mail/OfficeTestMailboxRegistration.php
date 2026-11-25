<?php
declare(strict_types=1);
namespace Brebo\Office\Mail;

use PDO;
use RuntimeException;

final class OfficeTestMailboxRegistration {
    public function __construct(private readonly PDO $db, private readonly OfficeTestMailboxProvisioning $provisioning) {}

    public function reconcile(string $address, \Brebo\Mail\Contract\GatewayHttpClientInterface $client): array {
        $address = strtolower(trim($address));
        if ($this->db->query('SELECT DATABASE()')->fetchColumn() !== 'brebo_office_test'
            || getenv('OFFICE_TEST_MAIL_PROVISIONING_ENABLED') !== 'enabled'
            || !str_ends_with($address, '@testmail.brebobv.nl')
            || !filter_var($address, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Herstel niet toegestaan.');
        }
        $stmt = $this->db->prepare("SELECT id FROM office_mail_mailboxes WHERE address=:address AND active=0 AND gateway_ref IS NULL AND credential_ref='' AND mailbox_type='functional'");
        $stmt->execute(['address'=>$address]);
        $id = $stmt->fetchColumn();
        if ($id === false) throw new RuntimeException('Geen open reservering.');
        $result = $client->post('/v1/mailboxes/lookup', ['address'=>$address]);
        $reference = $result['reference'] ?? null;
        if (($result['address'] ?? null) !== $address || !is_string($reference) || $reference === '' || strlen($reference)>190) {
            throw new RuntimeException('Gatewayregistratie niet bevestigd.');
        }
        $stmt = $this->db->prepare("UPDATE office_mail_mailboxes SET gateway_ref=:reference WHERE id=:id AND active=0 AND gateway_ref IS NULL AND credential_ref=''");
        $stmt->execute(['reference'=>$reference,'id'=>$id]);
        if ($stmt->rowCount() !== 1) throw new RuntimeException('Herstel niet bevestigd.');
        return ['address'=>$address,'reference'=>$reference,'status'=>'staged','active'=>false];
    }

    public function register(string $address): array {
        if ($this->db->query('SELECT DATABASE()')->fetchColumn() !== 'brebo_office_test') {
            throw new RuntimeException('Wrong database');
        }
        $address = strtolower(trim($address));
        if (getenv('OFFICE_TEST_MAIL_PROVISIONING_ENABLED') !== 'enabled'
            || !preg_match('/\A[a-z0-9][a-z0-9._-]{0,63}@testmail\.brebobv\.nl\z/D', $address)
            || filter_var($address, FILTER_VALIDATE_EMAIL) === false) {
            throw new RuntimeException('Testmailbox niet toegestaan.');
        }
        // Persist an inactive reservation before contacting the gateway.
        // A NULL gateway_ref means reconciliation is required, never active.
        $stmt = $this->db->prepare("INSERT INTO office_mail_mailboxes
            (address,label,mailbox_type,credential_ref,gateway_ref,active)
            VALUES (:address,:label,'functional','',NULL,0)");
        $stmt->execute(['address'=>$address,'label'=>$address]);
        $id = $this->db->lastInsertId();
        try {
            $result = $this->provisioning->stage($address);
            $reference = $result['reference'];
            if (strlen($reference) > 190) {
                throw new RuntimeException('Gatewayreferentie te lang.');
            }
            $stmt = $this->db->prepare('UPDATE office_mail_mailboxes SET gateway_ref = :reference
                WHERE id = :id AND active = 0 AND gateway_ref IS NULL');
            $stmt->execute(['id'=>$id,'reference'=>$reference]);
            if ($stmt->rowCount() !== 1) {
                throw new RuntimeException('Registratie niet bevestigd.');
            }
            return $result;
        } catch (\Throwable $e) {
            // Keep reservation: gateway may have committed despite a timeout.
            throw new RuntimeException('Registratie vereist controle.', 0, $e);
        }
    }
}
