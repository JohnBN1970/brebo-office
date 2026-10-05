<?php

declare(strict_types=1);

namespace Brebo\MailGateway\Service;

use Brebo\MailGateway\Domain\MailStackProjection;

final class MailStackProjectionBuilder {

  /** @param array<int,array<string,mixed>> $domains
   *  @param array<int,array<string,mixed>> $mailboxes
   *  @param array<int,array<string,mixed>> $aliases
   */
  public function build(array $domains, array $mailboxes, array $aliases): MailStackProjection {
    $domainNames = [];
    $dkim = [];
    foreach ($domains as $domain) {
      $name = mb_strtolower(trim((string) ($domain['domain'] ?? '')));
      if ($name === '') {
        continue;
      }
      $domainNames[] = $name;
      $selector = trim((string) ($domain['dkim_selector'] ?? ''));
      $privateRef = trim((string) ($domain['dkim_private_key_reference'] ?? ''));
      if ($selector !== '' && $privateRef !== '') {
        $dkim[$name] = [
          'selector' => $selector,
          'private_key_reference' => $privateRef,
        ];
      }
    }

    $mailboxAddresses = [];
    foreach ($mailboxes as $mailbox) {
      $address = mb_strtolower(trim((string) ($mailbox['address'] ?? '')));
      if ($address !== '') {
        $mailboxAddresses[] = $address;
      }
    }

    $aliasMap = [];
    foreach ($aliases as $alias) {
      $address = mb_strtolower(trim((string) ($alias['address'] ?? '')));
      $target = mb_strtolower(trim((string) ($alias['target_address'] ?? '')));
      if ($address !== '' && $target !== '') {
        $aliasMap[$address] = $target;
      }
    }

    sort($domainNames);
    sort($mailboxAddresses);
    ksort($aliasMap);
    ksort($dkim);

    return new MailStackProjection(
      array_values(array_unique($domainNames)),
      array_values(array_unique($mailboxAddresses)),
      $aliasMap,
      $dkim,
    );
  }
}
