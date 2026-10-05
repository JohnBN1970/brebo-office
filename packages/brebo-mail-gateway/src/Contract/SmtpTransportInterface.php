<?php

declare(strict_types=1);

namespace Brebo\MailGateway\Contract;

interface SmtpTransportInterface {
  /** @param array<string,mixed> $message */
  public function send(array $message): string;

  /** @return array{available:bool,message:string} */
  public function health(): array;
}
