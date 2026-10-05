<?php

declare(strict_types=1);

namespace Brebo\MailGateway\Infrastructure;

use Brebo\MailGateway\Contract\MailboxStoreInterface;

final class InMemoryMailboxStore implements MailboxStoreInterface {
  /** @var array<string,array<string,array<int,array<string,mixed>>>> */
  private array $messages = [];

  public function append(string $mailboxAddress, string $folder, array $message): string {
    $mailboxAddress = mb_strtolower(trim($mailboxAddress));
    $folder = trim($folder) ?: 'INBOX';
    $reference = 'store-test:' . substr(hash('sha256', $mailboxAddress . ':' . $folder . ':' . json_encode($message, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . ':' . count($this->messages[$mailboxAddress][$folder] ?? [])), 0, 24);
    $this->messages[$mailboxAddress][$folder][] = $message + ['reference' => $reference];
    return $reference;
  }

  public function recent(string $mailboxAddress, string $folder = 'INBOX', int $limit = 50): array {
    $items = $this->messages[mb_strtolower(trim($mailboxAddress))][$folder] ?? [];
    return array_slice(array_reverse($items), 0, max(1, min($limit, 200)));
  }

  public function health(): array {
    return ['available' => TRUE, 'message' => 'In-memory mailbox store actief.'];
  }
}
