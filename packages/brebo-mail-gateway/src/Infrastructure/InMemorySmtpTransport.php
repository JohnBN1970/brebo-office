<?php

declare(strict_types=1);

namespace Brebo\MailGateway\Infrastructure;

use Brebo\MailGateway\Contract\SmtpTransportInterface;

final class InMemorySmtpTransport implements SmtpTransportInterface {
  /** @var array<int,array<string,mixed>> */
  private array $messages = [];

  public function send(array $message): string {
    $reference = 'smtp-test:' . substr(hash('sha256', json_encode($message, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . ':' . count($this->messages)), 0, 24);
    $this->messages[] = $message + ['reference' => $reference];
    return $reference;
  }

  public function health(): array {
    return ['available' => TRUE, 'message' => 'In-memory SMTP testtransport actief.'];
  }

  /** @return array<int,array<string,mixed>> */
  public function messages(): array {
    return $this->messages;
  }
}
