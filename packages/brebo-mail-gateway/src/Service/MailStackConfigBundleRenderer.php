<?php

declare(strict_types=1);

namespace Brebo\MailGateway\Service;

use Brebo\MailGateway\Domain\MailStackProjection;

final class MailStackConfigBundleRenderer {

  public function __construct(
    private readonly PostfixProjectionRenderer $postfix,
    private readonly DovecotProjectionRenderer $dovecot,
    private readonly RspamdProjectionRenderer $rspamd,
  ) {}

  /** @return array<string,string> */
  public function render(MailStackProjection $projection): array {
    return array_merge(
      $this->postfix->render($projection),
      $this->dovecot->render($projection),
      $this->rspamd->render($projection),
    );
  }
}
