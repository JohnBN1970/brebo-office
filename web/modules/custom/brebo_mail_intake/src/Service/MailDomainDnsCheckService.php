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
    $rootTxt = $this->dns->txt($name);
    $dmarcTxt = $this->dns->txt('_dmarc.' . $name);
    $mx = $this->dns->mx($name);

    $verified = in_array($verification, $rootTxt, TRUE);
    $spf = FALSE;
    foreach ($rootTxt as $value) {
      if (str_starts_with(mb_strtolower($value), 'v=spf1')) {
        $spf = TRUE;
        break;
      }
    }
    $dmarc = FALSE;
    foreach ($dmarcTxt as $value) {
      if (str_starts_with(mb_strtolower($value), 'v=dmarc1')) {
        $dmarc = TRUE;
        break;
      }
    }

    $checks = [
      'mx_status' => $mx !== [] ? 'ok' : 'missing',
      'spf_status' => $spf ? 'ok' : 'missing',
      'dkim_status' => (string) ($domain['dkim_status'] ?? 'unknown'),
      'dmarc_status' => $dmarc ? 'ok' : 'missing',
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
    ];
  }

}
