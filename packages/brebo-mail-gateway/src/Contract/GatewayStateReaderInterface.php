<?php

declare(strict_types=1);

namespace Brebo\MailGateway\Contract;

interface GatewayStateReaderInterface {
  /** @return array<int,array<string,mixed>> */
  public function domains(): array;

  /** @return array<int,array<string,mixed>> */
  public function mailboxes(): array;

  /** @return array<int,array<string,mixed>> */
  public function aliases(): array;
}
