<?php

declare(strict_types=1);

namespace Brebo\Mail\Contract;

interface MailboxDirectoryInterface {
  /** @return string[] */
  public function activeAddresses(): array;
}
