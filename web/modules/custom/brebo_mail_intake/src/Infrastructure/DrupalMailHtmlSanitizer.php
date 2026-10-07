<?php

declare(strict_types=1);

namespace Drupal\brebo_mail_intake\Infrastructure;

use Drupal\brebo_mail_intake\Contract\MailHtmlSanitizerInterface;
use Drupal\Component\Utility\Xss;

/** Drupal XSS sanitizer adapter for outbound mail HTML. */
final class DrupalMailHtmlSanitizer implements MailHtmlSanitizerInterface {

  public function sanitize(string $html, array $allowedTags): string {
    return Xss::filter($html, $allowedTags);
  }

}
