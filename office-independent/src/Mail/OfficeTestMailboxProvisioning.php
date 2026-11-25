<?php
declare(strict_types=1);

namespace Brebo\Office\Mail;

use Brebo\Mail\Contract\MailGatewayInterface;
use RuntimeException;

/** Test-only orchestration; a gateway reference is not proof of delivery. */
final class OfficeTestMailboxProvisioning {
    public function __construct(private readonly MailGatewayInterface $gateway) {}

    public function stage(string $address): array {
        if (getenv('OFFICE_TEST_MAIL_PROVISIONING_ENABLED') !== 'enabled') {
            throw new RuntimeException('Testmailboxbeheer is niet vrijgegeven.');
        }
        $address = strtolower(trim($address));
        if (!preg_match('/\A[a-z0-9][a-z0-9._-]{0,63}@testmail\.brebobv\.nl\z/D', $address)
            || filter_var($address, FILTER_VALIDATE_EMAIL) === false) {
            throw new RuntimeException('Alleen het geïsoleerde testdomein is toegestaan.');
        }
        if (!($this->gateway->health()['available'] ?? false)) {
            throw new RuntimeException('Mailgateway niet beschikbaar.');
        }
        $reference = $this->gateway->provisionMailbox(['address' => $address]);
        if (trim($reference) === '') {
            throw new RuntimeException('Provisioningreferentie ontbreekt.');
        }
        return [
            'address' => $address,
            'reference' => $reference,
            'status' => 'staged',
            'active' => false,
        ];
    }
}
