<?php

declare(strict_types=1);

namespace Drupal\brebo_mail_intake\Infrastructure;

use Drupal\brebo_mail_intake\Contract\MailIntakeFailureStoreInterface;
use Drupal\Core\State\StateInterface;

/** Drupal State adapter for the technical mail-intake failure registry. */
final class DrupalStateMailIntakeFailureStore implements MailIntakeFailureStoreInterface {

  private const STATE_KEY = 'brebo_mail_intake.technical_failures';

  public function __construct(private readonly StateInterface $state) {}

  public function all(): array {
    $items = $this->state->get(self::STATE_KEY, []);
    return is_array($items) ? $items : [];
  }

  public function save(array $items): void {
    $this->state->set(self::STATE_KEY, $items);
  }

}
