<?php

declare(strict_types=1);

namespace Brebo\Mail\Service;

use Brebo\Mail\Contract\MailboxDirectoryInterface;
use Brebo\Mail\Domain\NormalizedMailMessage;
use RuntimeException;

final class MailIntakeAdmissionService {

  public function __construct(
    private readonly MailboxDirectoryInterface $directory,
    private readonly MailIntakeBridge $bridge,
  ) {}

  /** @return array<string,mixed> */
  public function ingest(NormalizedMailMessage $message): array {
    $active = array_map(
      static fn(string $address): string => mb_strtolower(trim($address)),
      $this->directory->activeAddresses(),
    );

    preg_match_all('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $message->to, $matches);
    $recipients = array_map(
      static fn(string $address): string => mb_strtolower(trim($address)),
      $matches[0] ?? [],
    );

    if ($recipients === [] || array_intersect($recipients, $active) === []) {
      throw new RuntimeException('Mail intake geweigerd: geen actieve Office-mailbox als ontvanger.');
    }

    return $this->bridge->ingest($message);
  }
}
