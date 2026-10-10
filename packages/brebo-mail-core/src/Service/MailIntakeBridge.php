<?php

declare(strict_types=1);

namespace Brebo\Mail\Service;

use Brebo\Mail\Contract\MailIntakeSinkInterface;
use Brebo\Mail\Domain\NormalizedMailMessage;

final class MailIntakeBridge {

  public function __construct(private readonly MailIntakeSinkInterface $sink) {}

  /** @return array<string,mixed> */
  public function ingest(NormalizedMailMessage $message): array {
    return $this->sink->ingest($message);
  }
}
