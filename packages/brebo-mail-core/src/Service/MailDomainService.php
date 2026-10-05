<?php

declare(strict_types=1);

namespace Brebo\Mail\Service;

use Brebo\Mail\Contract\MailDomainRepositoryInterface;
use Brebo\Mail\Domain\DkimDescriptor;
use InvalidArgumentException;
use RuntimeException;

final class MailDomainService {
  public function __construct(private readonly MailDomainRepositoryInterface $repository) {}

  /** @return array<int,array<string,mixed>> */
  public function all(): array {
    return $this->repository->all();
  }

  public function register(string $domain): int {
    $domain = mb_strtolower(trim($domain));
    if ($domain === '' || filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === FALSE || !str_contains($domain, '.')) {
      throw new InvalidArgumentException('Ongeldig maildomein.');
    }
    foreach ($this->repository->all() as $existing) {
      if (mb_strtolower((string) ($existing['domain'] ?? '')) === $domain) {
        throw new InvalidArgumentException('Dit maildomein is al geregistreerd.');
      }
    }
    return $this->repository->create($domain, bin2hex(random_bytes(16)));
  }

  /** @return array{type:string,name:string,value:string} */
  public function verificationRecord(int $domainId): array {
    $domain = $this->repository->load($domainId);
    if (!$domain) {
      throw new RuntimeException('Maildomein niet gevonden.');
    }
    return [
      'type' => 'TXT',
      'name' => '@',
      'value' => 'brebo-domain-verification=' . (string) $domain['verification_token'],
    ];
  }
  public function registerDkim(int $domainId, string $selector, string $publicKey): void {
    $domain = $this->repository->load($domainId);
    if (!$domain) {
      throw new RuntimeException('Maildomein niet gevonden.');
    }
    $descriptor = new DkimDescriptor($selector, $publicKey);
    $this->repository->setDkim($domainId, $descriptor->selector, $descriptor->publicKey);
  }

  /** @return array{type:string,name:string,value:string}|null */
  public function dkimRecord(int $domainId): ?array {
    $domain = $this->repository->load($domainId);
    if (!$domain) {
      throw new RuntimeException('Maildomein niet gevonden.');
    }
    $selector = trim((string) ($domain['dkim_selector'] ?? ''));
    $publicKey = trim((string) ($domain['dkim_public_key'] ?? ''));
    if ($selector === '' || $publicKey === '') {
      return NULL;
    }
    $descriptor = new DkimDescriptor($selector, $publicKey);
    return [
      'type' => 'TXT',
      'name' => $descriptor->recordName((string) $domain['domain']),
      'value' => $descriptor->recordValue(),
    ];
  }

}
