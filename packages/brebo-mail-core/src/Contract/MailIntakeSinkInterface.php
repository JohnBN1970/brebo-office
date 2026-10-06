<?php

declare(strict_types=1);

namespace Brebo\Mail\Contract;

use Brebo\Mail\Domain\NormalizedMailMessage;

interface MailIntakeSinkInterface {

  /** @return array<string,mixed> */
  public function ingest(NormalizedMailMessage $message): array;
}
