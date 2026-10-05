<?php

declare(strict_types=1);

namespace Brebo\Mail\Service;

use Brebo\Mail\Contract\DnsResolverInterface;
use Brebo\Mail\Contract\MailDomainRepositoryInterface;
use Brebo\Mail\Domain\MailDomainDnsPolicy;
use RuntimeException;

final class MailDomainDnsCheckService {
  public function __construct(
    private readonly MailDomainRepositoryInterface $repository,
    private readonly DnsResolverInterface $dns,
    private readonly MailDomainDnsPolicy $policy,
  ) {}

  /** @return array<string,mixed> */
  public function check(int $domainId): array {
    $domain = $this->repository->load($domainId);
    if (!$domain) {
      throw new RuntimeException('Maildomein niet gevonden.');
    }

    $name = mb_strtolower(trim((string) $domain['domain']));
    $verification = 'brebo-domain-verification=' . (string) $domain['verification_token'];

    try {
      $rootTxt = $this->dns->txt($name);
      $dmarcTxt = $this->dns->txt('_dmarc.' . $name);
      $mx = $this->dns->mx($name);
    }
    catch (RuntimeException $e) {
      return [
        'domain' => $name,
        'verified' => (string) ($domain['status'] ?? '') === 'verified',
        'checks' => [
          'mx_status' => (string) ($domain['mx_status'] ?? 'unknown'),
          'spf_status' => (string) ($domain['spf_status'] ?? 'unknown'),
          'dkim_status' => (string) ($domain['dkim_status'] ?? 'unknown'),
          'dmarc_status' => (string) ($domain['dmarc_status'] ?? 'unknown'),
        ],
        'lookup_error' => $e->getMessage(),
      ];
    }

    $result = $this->policy->evaluate(
      $verification,
      $rootTxt,
      $dmarcTxt,
      $mx,
      (string) ($domain['dkim_status'] ?? 'unknown'),
    );
    $this->repository->setDnsChecks($domainId, $result->checks);
    $this->repository->setStatus($domainId, $result->verified ? 'verified' : 'pending');

    return [
      'domain' => $name,
      'verified' => $result->verified,
      'checks' => $result->checks,
      'mx' => $mx,
      'root_txt' => $rootTxt,
      'dmarc_txt' => $dmarcTxt,
    ];
  }
}
