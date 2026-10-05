<?php

declare(strict_types=1);

namespace Brebo\MailGateway\Infrastructure;

use Brebo\MailGateway\Contract\SmtpTransportInterface;
use Brebo\MailGateway\Contract\StdinCommandRunnerInterface;
use RuntimeException;

final class PostfixSendmailTransport implements SmtpTransportInterface {

  public function __construct(
    private readonly StdinCommandRunnerInterface $runner,
    private readonly bool $enabled,
  ) {}

  public function send(array $message): string {
    if (!$this->enabled) {
      throw new RuntimeException('Live SMTP transport is niet geactiveerd.');
    }

    $from = mb_strtolower(trim((string) ($message['from'] ?? '')));
    $to = mb_strtolower(trim((string) ($message['to'] ?? '')));
    $subject = str_replace(["", "
"], '', (string) ($message['subject'] ?? ''));
    $text = (string) ($message['text'] ?? '');

    if (filter_var($from, FILTER_VALIDATE_EMAIL) === FALSE || filter_var($to, FILTER_VALIDATE_EMAIL) === FALSE) {
      throw new RuntimeException('Ongeldig SMTP adres.');
    }

    $raw = "From: {$from}
To: {$to}
Subject: {$subject}

{$text}
";
    $result = $this->runner->runWithInput('sendmail', ['-f', $from, '--', $to], $raw);
    if ($result['exit_code'] !== 0) {
      throw new RuntimeException('Postfix sendmail transport failed: ' . trim($result['stderr']));
    }

    return 'postfix:' . substr(hash('sha256', $raw . microtime(TRUE)), 0, 24);
  }

  public function health(): array {
    return [
      'available' => $this->enabled,
      'message' => $this->enabled ? 'Postfix sendmail transport geactiveerd.' : 'Postfix sendmail transport uitgeschakeld.',
    ];
  }
}
