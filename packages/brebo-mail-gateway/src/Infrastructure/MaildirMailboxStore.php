<?php

declare(strict_types=1);

namespace Brebo\MailGateway\Infrastructure;

use Brebo\MailGateway\Contract\MailboxStoreInterface;
use RuntimeException;

final class MaildirMailboxStore implements MailboxStoreInterface {

  public function __construct(
    private readonly string $root,
    private readonly bool $enabled,
  ) {}

  public function append(string $mailboxAddress, string $folder, array $message): string {
    if (!$this->enabled) {
      throw new RuntimeException('Live mailbox store is niet geactiveerd.');
    }
    $mailboxAddress = mb_strtolower(trim($mailboxAddress));
    if (filter_var($mailboxAddress, FILTER_VALIDATE_EMAIL) === FALSE) {
      throw new RuntimeException('Ongeldig mailboxadres.');
    }

    $folder = trim($folder) ?: 'INBOX';
    $path = $this->folderPath($mailboxAddress, $folder) . DIRECTORY_SEPARATOR . 'new';
    if (!is_dir($path) && !mkdir($path, 0700, TRUE) && !is_dir($path)) {
      throw new RuntimeException('Maildir map kan niet worden aangemaakt.');
    }

    $raw = isset($message['raw']) && is_string($message['raw']) && $message['raw'] !== ''
      ? $message['raw']
      : $this->renderMessage($message);
    $name = sprintf('%d.%s.brebo', hrtime(TRUE), bin2hex(random_bytes(6)));
    $file = $path . DIRECTORY_SEPARATOR . $name;
    if (file_put_contents($file, $raw, LOCK_EX) === FALSE) {
      throw new RuntimeException('Bericht kon niet in Maildir worden opgeslagen.');
    }
    chmod($file, 0600);

    return 'maildir:' . $mailboxAddress . ':' . $folder . ':' . $name;
  }

  public function recent(string $mailboxAddress, string $folder = 'INBOX', int $limit = 50): array {
    $path = $this->folderPath($mailboxAddress, $folder) . DIRECTORY_SEPARATOR . 'new';
    if (!is_dir($path)) {
      return [];
    }
    $files = glob($path . DIRECTORY_SEPARATOR . '*') ?: [];
    rsort($files);
    $items = [];
    foreach (array_slice($files, 0, max(1, min($limit, 200))) as $file) {
      $items[] = ['reference' => 'maildir-file:' . basename($file), 'raw' => (string) file_get_contents($file)];
    }
    return $items;
  }

  public function health(): array {
    return [
      'available' => $this->enabled && $this->root !== '',
      'message' => $this->enabled ? 'Maildir store geactiveerd.' : 'Maildir store uitgeschakeld.',
    ];
  }

  private function folderPath(string $mailboxAddress, string $folder): string {
    $safeMailbox = preg_replace('/[^a-z0-9@._+-]+/', '_', $mailboxAddress) ?: 'mailbox';
    $safeFolder = preg_replace('/[^A-Za-z0-9._-]+/', '_', $folder) ?: 'INBOX';
    return rtrim($this->root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $safeMailbox . DIRECTORY_SEPARATOR . $safeFolder;
  }

  private function renderMessage(array $message): string {
    $from = str_replace(["", "
"], '', (string) ($message['from'] ?? ''));
    $to = str_replace(["", "
"], '', (string) ($message['to'] ?? ''));
    $subject = str_replace(["", "
"], '', (string) ($message['subject'] ?? ''));
    $text = (string) ($message['text'] ?? '');
    return "From: {$from}
To: {$to}
Subject: {$subject}

{$text}
";
  }
}
