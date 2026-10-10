<?php

declare(strict_types=1);

namespace Brebo\Mail\Domain;

use InvalidArgumentException;

final readonly class NormalizedMailMessage {

  public function __construct(
    public string $sourceId,
    public string $from,
    public string $to,
    public string $subject,
    public string $body,
    public string $bodyHtml = '',
    public string $receivedAt = '',
    public string $threadId = '',
  ) {
    if (trim($sourceId) === '') {
      throw new InvalidArgumentException('Mail source id ontbreekt.');
    }
    if (filter_var(trim($from), FILTER_VALIDATE_EMAIL) === FALSE) {
      throw new InvalidArgumentException('Ongeldig afzenderadres.');
    }
    if (trim($to) === '') {
      throw new InvalidArgumentException('Mail ontvanger ontbreekt.');
    }
    if (trim($subject) === '' || trim($body) === '') {
      throw new InvalidArgumentException('Onderwerp en body zijn verplicht.');
    }
  }

  /** @return array<string,mixed> */
  public function toArray(): array {
    return [
      'source_id' => trim($this->sourceId),
      'from' => mb_strtolower(trim($this->from)),
      'to' => trim($this->to),
      'subject' => trim($this->subject),
      'body' => trim($this->body),
      'body_html' => trim($this->bodyHtml),
      'received_at' => trim($this->receivedAt),
      'thread_id' => trim($this->threadId),
    ];
  }
}
