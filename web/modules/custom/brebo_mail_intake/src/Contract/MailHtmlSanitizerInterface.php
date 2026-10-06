<?php

declare(strict_types=1);

namespace Drupal\brebo_mail_intake\Contract;

interface MailHtmlSanitizerInterface {

  /** @param string[] $allowedTags */
  public function sanitize(string $html, array $allowedTags): string;

}
