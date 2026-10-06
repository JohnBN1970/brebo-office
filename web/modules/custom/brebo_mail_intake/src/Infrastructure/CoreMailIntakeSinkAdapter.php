<?php

declare(strict_types=1);

namespace Drupal\brebo_mail_intake\Infrastructure;

use Brebo\Mail\Contract\MailIntakeSinkInterface;
use Brebo\Mail\Domain\NormalizedMailMessage;
use Drupal\brebo_mail_intake\Service\MailIntakeIngestor;

final class CoreMailIntakeSinkAdapter implements MailIntakeSinkInterface {

  public function __construct(private readonly MailIntakeIngestor $ingestor) {}

  public function ingest(NormalizedMailMessage $message): array {
    return $this->ingestor->ingest($message->toArray());
  }
}
