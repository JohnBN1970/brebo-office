<?php

declare(strict_types=1);

namespace Brebo\Mail\Service;

use Brebo\Mail\Contract\DnsResolverInterface;
use Brebo\Mail\Contract\MailDomainRepositoryInterface;
use Brebo\Mail\Domain\DkimDescriptor;
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
      $dkimTxt = [];
      $selector = trim((string) ($domain['dkim_selector'] ?? ''));
      $publicKey = trim((string) ($domain['dkim_public_key'] ?? ''));
      if ($selector !== '' && $publicKey !== '') {
        $descriptor = new DkimDescriptor($selector, $publicKey);
        $dkimTxt = $this->dns->txt($descriptor->recordName($name));
      }
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
      $this->dkimStatus(
        $name,
        $selector ?? '',
        $publicKey ?? '',
        $dkimTxt ?? [],
      ),
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
      'dkim_txt' => $dkimTxt,
    ];
  }

  /** @param string[] $published */
  private function dkimStatus(string $domain, string $selector, string $publicKey, array $published): string {
    if ($selector === '' || $publicKey === '') {
      return 'unknown';
    }
    $expected = (new DkimDescriptor($selector, $publicKey))->recordValue();
    $normalize = static fn(string $value): string => preg_replace('/\s+/', '', mb_strtolower(trim($value))) ?? '';
    $expectedNormalized = $normalize($expected);
    foreach ($published as $value) {
      if ($normalize($value) === $expectedNormalized) {
        return 'ok';
      }
    }
    return $published === [] ? 'missing' : 'invalid';
  }
}

