<?php

declare(strict_types=1);

namespace Drupal\brebo_data_intake\ValueObject;

use InvalidArgumentException;

/** Immutable, validated result returned by an intake destination. */
final readonly class IntakeDestinationResult {

  public const string CREATED = 'created';
  public const string DUPLICATE = 'duplicate';
  public const string ROUTED = 'routed';
  public const string ACCEPTED = 'accepted';
  public const string PROCESSED = 'processed';
  public const string REVIEW_REQUIRED = 'review_required';
  public const string UNAVAILABLE = 'unavailable';

  private const array STATES = [
    self::CREATED,
    self::DUPLICATE,
    self::ROUTED,
    self::ACCEPTED,
    self::PROCESSED,
    self::REVIEW_REQUIRED,
    self::UNAVAILABLE,
  ];

  public function __construct(
    public string $state,
    public ?string $reason = NULL,
    public array $context = [],
  ) {
    if (!in_array($state, self::STATES, TRUE)) {
      throw new InvalidArgumentException(sprintf('Unsupported BREBO intake destination state: %s.', $state));
    }
    if (!$this->isTerminal() && ($reason === NULL || trim($reason) === '')) {
      throw new InvalidArgumentException('A non-terminal intake destination result requires a reason.');
    }
  }

  public function isTerminal(): bool {
    return in_array($this->state, [self::CREATED, self::DUPLICATE, self::ROUTED, self::ACCEPTED, self::PROCESSED], TRUE);
  }

  /** @return array<string, mixed> */
  public function toArray(): array {
    return ['state' => $this->state] + ($this->reason !== NULL ? ['reason' => $this->reason] : []) + $this->context;
  }

}
