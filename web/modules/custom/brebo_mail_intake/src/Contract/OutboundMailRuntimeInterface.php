<?php

declare(strict_types=1);

namespace Drupal\brebo_mail_intake\Contract;

interface OutboundMailRuntimeInterface {

  public function currentUserId(): int;

  public function canSend(): bool;

  public function smtpEnabled(): bool;

  public function defaultFrom(): string;

  public function requestTime(): int;

  /**
   * @param array<string,mixed> $params
   */
  public function send(string $to, array $params, string $from): bool;

}
