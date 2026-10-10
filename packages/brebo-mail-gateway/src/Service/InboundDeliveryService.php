<?php

declare(strict_types=1);

namespace Brebo\MailGateway\Service;

use Brebo\Mail\Domain\NormalizedMailMessage;
use Brebo\MailGateway\Contract\MailboxStoreInterface;
use InvalidArgumentException;
use RuntimeException;

final class InboundDeliveryService {

  public function __construct(
    private readonly MailboxStoreInterface $store,
    private readonly OfficeIntakeDeliveryService $office,
  ) {}

  /** @param array<string,mixed> $message
   *  @return array{mailbox_reference:string,office:array<string,mixed>}
   */
  public function deliver(string $mailboxAddress, array $message): array {
    $mailboxAddress = mb_strtolower(trim($mailboxAddress));
    if (filter_var($mailboxAddress, FILTER_VALIDATE_EMAIL) === FALSE) {
      throw new InvalidArgumentException('Ongeldig mailboxadres.');
    }

    $sourceId = trim((string) ($message['source_id'] ?? ''));
    if ($sourceId === '') {
      $sourceId = 'gateway:' . hash('sha256', implode("\n", [
        trim((string) ($message['from'] ?? '')),
        $mailboxAddress,
        trim((string) ($message['subject'] ?? '')),
        trim((string) ($message['received_at'] ?? '')),
        trim((string) ($message['text'] ?? $message['body'] ?? '')),
      ]));
    }

    $normalized = new NormalizedMailMessage(
      $sourceId,
      (string) ($message['from'] ?? ''),
      $mailboxAddress,
      (string) ($message['subject'] ?? ''),
      (string) ($message['text'] ?? $message['body'] ?? ''),
      (string) ($message['html'] ?? $message['body_html'] ?? ''),
      (string) ($message['received_at'] ?? ''),
      (string) ($message['thread_id'] ?? ''),
    );

    // Office is the permanent mailbox/archive layer. Do not acknowledge local
    // delivery as complete when Office intake has rejected the message.
    $office = $this->office->deliver($normalized);
    if (($office['status'] ?? '') !== 'ok') {
      throw new RuntimeException('Office intake heeft inkomende mail niet bevestigd.');
    }

    $mailboxReference = $this->store->append(
      $mailboxAddress,
      'INBOX',
      $message + ['source_id' => $sourceId],
    );

    return [
      'mailbox_reference' => $mailboxReference,
      'office' => $office,
    ];
  }
}
