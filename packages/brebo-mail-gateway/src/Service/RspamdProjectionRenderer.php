<?php

declare(strict_types=1);

namespace Brebo\MailGateway\Service;

use Brebo\MailGateway\Domain\MailStackProjection;

final class RspamdProjectionRenderer {

  /** @return array{dkim_map:string} */
  public function render(MailStackProjection $projection): array {
    $lines = [];
    foreach ($projection->dkim as $domain => $dkim) {
      $lines[] = $domain . ' ' . $dkim['selector'] . ' ' . $dkim['private_key_reference'];
    }
    return [
      'dkim_map' => implode("\n", $lines) . ($lines !== [] ? "\n" : ''),
    ];
  }
}
