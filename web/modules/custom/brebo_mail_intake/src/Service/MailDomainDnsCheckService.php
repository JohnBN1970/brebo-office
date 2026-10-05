<?php

declare(strict_types=1);

namespace Drupal\brebo_mail_intake\Service;

use Drupal\brebo_mail_intake\Contract\DnsResolverInterface;
use Drupal\brebo_mail_intake\Contract\MailDomainRepositoryInterface;

final class MailDomainDnsCheckService {

  public function __construct(
    private readonly MailDomainRepositoryInterface $repository,
    private readonly DnsResolverInterface $dns,
  ) {}

  /** @return array<string,mixed> */
  public function check(int $domainId): array {
    $domain = $this->repository->load($domainId);
    if (!$domain) {
      throw new \RuntimeException('Maildomein niet gevonden.');
    }

    $name = mb_strtolower(trim((string) $domain['domain']));
    $verification = 'brebo-domain-verification=' . (string) $domain['verification_token'];

    try {
      $rootTxt = $this->dns->txt($name);
      $dmarcTxt = $this->dns->txt('_dmarc.' . $name);
      $mx = $this->dns->mx($name);
    }
    catch (\RuntimeException $e) {
      return [
        'domain' => $name,
        'verified' => (string) ($domain['status'] ?? '') === 'verified',
        'verification_record' => ['type' => 'TXT', 'name' => '@', 'value' => $verification],
        'mx' => [],
        'root_txt' => [],
        'dmarc_txt' => [],
        'checks' => [
          'mx_status' => (string) ($domain['mx_status'] ?? 'unknown'),
          'spf_status' => (string) ($domain['spf_status'] ?? 'unknown'),
          'dkim_status' => (string) ($domain['dkim_status'] ?? 'unknown'),
          'dmarc_status' => (string) ($domain['dmarc_status'] ?? 'unknown'),
        ],
        'lookup_error' => $e->getMessage(),
      ];
    }

    $verified = in_array($verification, $rootTxt, TRUE);

    $spfRecords = array_values(array_filter(
      $rootTxt,
      static fn(string $value): bool => preg_match('/^v=spf1(?:\s|$)/i', trim($value)) === 1,
    ));
    $spf = count($spfRecords) === 1;

    $dmarcRecords = array_values(array_filter(
      $dmarcTxt,
      static fn(string $value): bool => preg_match('/^v=dmarc1(?:;|\s|$)/i', trim($value)) === 1,
    ));
    $dmarc = count($dmarcRecords) === 1;

    $checks = [
      'mx_status' => $mx !== [] ? 'ok' : 'missing',
      'spf_status' => $spf ? 'ok' : 'invalid',
      'dkim_status' => (string) ($domain['dkim_status'] ?? 'unknown'),
      'dmarc_status' => $dmarc ? 'ok' : 'invalid',
    ];
    $this->repository->setDnsChecks($domainId, $checks);
    $this->repository->setStatus($domainId, $verified ? 'verified' : 'pending');

    return [
      'domain' => $name,
      'verified' => $verified,
      'verification_record' => ['type' => 'TXT', 'name' => '@', 'value' => $verification],
      'mx' => $mx,
      'root_txt' => $rootTxt,
      'dmarc_txt' => $dmarcTxt,
      'checks' => $checks,
      'spf_records' => $spfRecords,
      'dmarc_records' => $dmarcRecords,
    ];
  }

}
