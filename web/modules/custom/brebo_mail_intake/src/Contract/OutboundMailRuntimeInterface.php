<?php

declare(strict_types=1);

namespace Drupal\brebo_mail_intake\Contract;

/** Framework boundary for outbound-mail actor, transport, config and sanitization. */
interface OutboundMailRuntimeInterface {

  public function currentUserId(): int;

  public function canSend(): bool;

  public function transportEnabled(): bool;

  public function sanitizeHtml(string $html, array $allowedTags): string;

  /** @param array<string,mixed> $params */
  public function send(string $to, array $params, string $from): bool;

  public function requestTime(): int;

}
