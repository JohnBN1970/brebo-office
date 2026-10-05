<?php

declare(strict_types=1);

namespace Brebo\MailGateway\Service;

use Brebo\MailGateway\Domain\MailStackProjection;

final class DovecotProjectionRenderer {

  /** @return array{users:string} */
  public function render(MailStackProjection $projection): array {
    $users = [];
    foreach ($projection->mailboxes as $address) {
      $users[] = $address . ':*::::::';
    }
    return [
      'users' => implode("\n", $users) . ($users !== [] ? "\n" : ''),
    ];
  }
}
