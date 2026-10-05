<?php

declare(strict_types=1);

namespace Drupal\brebo_mail_intake\Infrastructure;

use Brebo\Mail\Contract\DnsResolverInterface;
use RuntimeException;

final class CoreNativeDnsResolverAdapter implements DnsResolverInterface {

  public function txt(string $name): array {
    $records = dns_get_record($name, DNS_TXT);
    if ($records === FALSE) {
      throw new RuntimeException('DNS TXT lookup failed for ' . $name . '.');
    }

    $values = [];
    foreach ($records as $record) {
      $value = trim((string) ($record['txt'] ?? ''));
      if ($value !== '') {
        $values[] = $value;
      }
    }
    return array_values(array_unique($values));
  }

  public function mx(string $name): array {
    $records = dns_get_record($name, DNS_MX);
    if ($records === FALSE) {
      throw new RuntimeException('DNS MX lookup failed for ' . $name . '.');
    }

    $values = [];
    foreach ($records as $record) {
      $host = rtrim(mb_strtolower(trim((string) ($record['target'] ?? ''))), '.');
      if ($host !== '') {
        $values[] = ['host' => $host, 'priority' => (int) ($record['pri'] ?? 0)];
      }
    }
    usort($values, static fn(array $a, array $b): int => $a['priority'] <=> $b['priority']);
    return $values;
  }
}
