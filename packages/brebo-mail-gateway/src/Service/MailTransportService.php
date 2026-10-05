<?php

declare(strict_types=1);

namespace Brebo\MailGateway\Service;

use Brebo\MailGateway\Contract\MailboxStoreInterface;
use Brebo\MailGateway\Contract\SmtpTransportInterface;
use InvalidArgumentException;

final class MailTransportService {
  public function __construct(
    private readonly SmtpTransportInterface $smtp,
    private readonly MailboxStoreInterface $store,
  ) {}

  /** @param array<string,mixed> $message */
  public function send(array $message): string {
    $from = mb_strtolower(trim((string) ($message['from'] ?? '')));
    $to = mb_strtolower(trim((string) ($message['to'] ?? '')));
    if (filter_var($from, FILTER_VALIDATE_EMAIL) === FALSE || filter_var($to, FILTER_VALIDATE_EMAIL) === FALSE) {
      throw new InvalidArgumentException('Ongeldig afzender- of ontvangeradres.');
    }

    $reference = $this->smtp->send($message);
    $this->store->append($from, 'Sent', $message + ['transport_reference' => $reference]);
    return $reference;
  }

  /** @param array<string,mixed> $message */
  public function receive(string $mailboxAddress, array $message): string {
    if (filter_var($mailboxAddress, FILTER_VALIDATE_EMAIL) === FALSE) {
      throw new InvalidArgumentException('Ongeldig mailboxadres.');
    }
    return $this->store->append($mailboxAddress, 'INBOX', $message);
  }

  /** @return array<string,mixed> */
  public function health(): array {
    return [
      'smtp' => $this->smtp->health(),
      'mailbox_store' => $this->store->health(),
    ];
  }
}
