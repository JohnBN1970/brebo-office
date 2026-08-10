<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Service;

use Drupal\node\NodeInterface;

/**
 * Guards the retired direct AI processing route.
 *
 * AI processing must run through the central BREBO Integration API. This
 * compatibility service remains temporarily registered so stale references
 * fail closed instead of calling an external AI provider from Drupal.
 */
final class CommunicationAiProcessor {

  public function isConfigured(): bool {
    return FALSE;
  }

  public function process(NodeInterface $communication): void {
    throw new \RuntimeException('Directe AI-verwerking is geblokkeerd. Gebruik de centrale BREBO Integration API.');
  }

}
