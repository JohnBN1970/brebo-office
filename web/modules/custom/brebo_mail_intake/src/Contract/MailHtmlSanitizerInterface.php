<?php

declare(strict_types=1);

namespace Drupal\brebo_mail_intake\Contract;

/** Sanitizes approved rich-text fragments before rendering outbound mail. */
interface MailHtmlSanitizerInterface {

  /** @param list<string> $allowedTags */
  public function sanitize(string $html, array $allowedTags): string;

}
