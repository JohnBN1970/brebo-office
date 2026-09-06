<?php

declare(strict_types=1);

namespace Drupal\brebo_data_intake\Service;

use Drupal\brebo_data_intake\Contract\IntakeDestinationInterface;
use Drupal\brebo_data_intake\ValueObject\IntakeDestinationResult;
use RuntimeException;

/** Resolves exactly one owning destination for a classified intake envelope. */
final class IntakeDestinationDispatcher {

  /** @param iterable<IntakeDestinationInterface> $destinations */
  public function __construct(private readonly iterable $destinations) {}

  /** @param array<string, mixed> $envelope */
  public function dispatch(array $envelope): IntakeDestinationResult {
    $classification = strtolower(trim((string) ($envelope['classification'] ?? '')));
    if ($classification === '') {
      throw new RuntimeException('Intake destination dispatch requires a classification.');
    }

    $matches = [];
    foreach ($this->destinations as $destination) {
      if ($destination->supports($classification)) {
        $matches[] = $destination;
      }
    }

    if ($matches === []) {
      return new IntakeDestinationResult(IntakeDestinationResult::REVIEW_REQUIRED, 'no_destination_for_classification');
    }
    if (count($matches) > 1) {
      return new IntakeDestinationResult(IntakeDestinationResult::REVIEW_REQUIRED, 'ambiguous_destination_for_classification');
    }

    return $matches[0]->route($envelope);
  }

}
