<?php

declare(strict_types=1);

namespace Brebo\MailGateway\Contract;

use Brebo\Mail\Domain\NormalizedMailMessage;

interface OfficeIntakeClientInterface {

  /** @return array<string,mixed> */
  public function deliver(NormalizedMailMessage $message): array;
}
