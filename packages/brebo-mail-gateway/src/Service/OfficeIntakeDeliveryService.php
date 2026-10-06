<?php

declare(strict_types=1);

namespace Brebo\MailGateway\Service;

use Brebo\Mail\Domain\NormalizedMailMessage;
use Brebo\MailGateway\Contract\OfficeIntakeClientInterface;

final class OfficeIntakeDeliveryService {

  public function __construct(private readonly OfficeIntakeClientInterface $client) {}

  /** @return array<string,mixed> */
  public function deliver(NormalizedMailMessage $message): array {
    return $this->client->deliver($message);
  }
}
