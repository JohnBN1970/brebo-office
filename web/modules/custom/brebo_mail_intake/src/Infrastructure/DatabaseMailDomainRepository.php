<?php

declare(strict_types=1);

namespace Drupal\brebo_mail_intake\Infrastructure;

use Drupal\brebo_mail_intake\Contract\MailDomainRepositoryInterface;
use Drupal\Core\Database\Connection;

final class DatabaseMailDomainRepository implements MailDomainRepositoryInterface {

  public function __construct(private readonly Connection $database) {}

  public function all(): array {
    return array_values(array_map('get_object_vars', $this->database->select('brebo_mail_domain', 'd')
      ->fields('d')
      ->orderBy('domain')
      ->execute()
      ->fetchAll()));
  }

  public function load(int $domainId): ?array {
    $row = $this->database->select('brebo_mail_domain', 'd')
      ->fields('d')
      ->condition('id', $domainId)
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();
    return $row === FALSE ? NULL : $row;
  }

  public function create(string $domain, string $verificationToken): int {
    $now = time();
    return (int) $this->database->insert('brebo_mail_domain')
      ->fields([
        'domain' => $domain,
        'verification_token' => $verificationToken,
        'status' => 'pending',
        'mx_status' => 'unknown',
        'spf_status' => 'unknown',
        'dkim_status' => 'unknown',
        'dmarc_status' => 'unknown',
        'created' => $now,
        'changed' => $now,
      ])->execute();
  }

  public function setStatus(int $domainId, string $status): void {
    $this->database->update('brebo_mail_domain')
      ->fields(['status' => $status, 'changed' => time()])
      ->condition('id', $domainId)
      ->execute();
  }

  public function setDnsChecks(int $domainId, array $checks): void {
    $allowed = ['mx_status', 'spf_status', 'dkim_status', 'dmarc_status'];
    $fields = ['changed' => time()];
    foreach ($allowed as $field) {
      if (isset($checks[$field])) {
        $fields[$field] = $checks[$field];
      }
    }
    $this->database->update('brebo_mail_domain')->fields($fields)->condition('id', $domainId)->execute();
  }

}
