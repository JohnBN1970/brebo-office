<?php

declare(strict_types=1);

namespace Brebo\MailGateway\Contract;

interface MailboxStoreInterface {
  /** @param array<string,mixed> $message */
  public function append(string $mailboxAddress, string $folder, array $message): string;

  /** @return array<int,array<string,mixed>> */
  public function recent(string $mailboxAddress, string $folder = 'INBOX', int $limit = 50): array;

  /** @return array{available:bool,message:string} */
  public function health(): array;
}
