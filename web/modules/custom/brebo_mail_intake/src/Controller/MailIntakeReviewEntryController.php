<?php

declare(strict_types=1);

namespace Drupal\brebo_mail_intake\Controller;

/**
 * Thin static HTTP entrypoint for the Mail Intake review queue.
 */
final class MailIntakeReviewEntryController {

  /**
   * Delegates to the explicit review controller service.
   *
   * @return array<string, mixed>
   *   Render array.
   */
  public static function queue(): array {
    return \Drupal::service('brebo_mail_intake.review_controller')->queue();
  }

}
