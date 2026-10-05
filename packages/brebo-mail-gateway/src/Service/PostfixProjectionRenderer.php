<?php

declare(strict_types=1);

namespace Brebo\MailGateway\Service;

use Brebo\MailGateway\Domain\MailStackProjection;

final class PostfixProjectionRenderer {

  /** @return array{virtual_domains:string,virtual_mailboxes:string,virtual_aliases:string} */
  public function render(MailStackProjection $projection): array {
    $domains = implode("\n", array_map(
      static fn(string $domain): string => $domain . ' OK',
      $projection->domains,
    ));

    $mailboxes = implode("\n", array_map(
      static fn(string $address): string => $address . ' 1',
      $projection->mailboxes,
    ));

    $aliases = [];
    foreach ($projection->aliases as $alias => $target) {
      $aliases[] = $alias . ' ' . $target;
    }

    return [
      'virtual_domains' => $domains . ($domains !== '' ? "\n" : ''),
      'virtual_mailboxes' => $mailboxes . ($mailboxes !== '' ? "\n" : ''),
      'virtual_aliases' => implode("\n", $aliases) . ($aliases !== [] ? "\n" : ''),
    ];
  }
}
