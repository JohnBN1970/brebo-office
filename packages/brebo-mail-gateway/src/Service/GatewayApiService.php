<?php

declare(strict_types=1);

namespace Brebo\MailGateway\Service;

use Brebo\MailGateway\Contract\DkimKeyGeneratorInterface;
use Brebo\MailGateway\Contract\GatewayProvisioningRepositoryInterface;
use Brebo\MailGateway\Contract\MailStackAdapterInterface;
use InvalidArgumentException;

final class GatewayApiService {

  public function __construct(
    private readonly GatewayProvisioningRepositoryInterface $repository,
    private readonly DkimKeyGeneratorInterface $dkim,
    private readonly MailStackAdapterInterface $mailStack,
  ) {}

  /** @return array{provider:string,available:bool,message:string} */
  public function health(): array {
    return [
      'provider' => 'brebo-mail-gateway',
      'available' => TRUE,
      'message' => 'BREBO Mail Gateway API is beschikbaar.',
    ];
  }

  /** @param array<string,mixed> $payload
   *  @return array<string,mixed>
   */
  public function provisionDomain(array $payload): array {
    $domain = mb_strtolower(trim((string) ($payload['domain'] ?? '')));
    if ($domain === '' || filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === FALSE) {
      throw new InvalidArgumentException('Ongeldig maildomein.');
    }

    $payload['domain'] = $domain;
    $dkim = $this->dkim->generate($domain);
    $payload['dkim_selector'] = $dkim['selector'];
    $payload['dkim_private_key_reference'] = $dkim['private_key_reference'];
    $reference = $this->repository->provisionDomain($payload);
    $this->mailStack->applyDomain($payload);

    return [
      'reference' => $reference,
      'dkim' => $dkim,
    ];
  }

  /** @param array<string,mixed> $payload
   *  @return array{reference:string}
   */
  public function provisionMailbox(array $payload): array {
    $address = mb_strtolower(trim((string) ($payload['address'] ?? '')));
    if (filter_var($address, FILTER_VALIDATE_EMAIL) === FALSE) {
      throw new InvalidArgumentException('Ongeldig mailboxadres.');
    }
    $payload['address'] = $address;
    $reference = $this->repository->provisionMailbox($payload);
    $this->mailStack->applyMailbox($payload);
    return ['reference' => $reference];
  }

  /** @return array{reference:string} */
  public function provisionAlias(string $aliasAddress, string $targetAddress): array {
    $aliasAddress = mb_strtolower(trim($aliasAddress));
    $targetAddress = mb_strtolower(trim($targetAddress));
    if (filter_var($aliasAddress, FILTER_VALIDATE_EMAIL) === FALSE || filter_var($targetAddress, FILTER_VALIDATE_EMAIL) === FALSE) {
      throw new InvalidArgumentException('Ongeldig alias- of doeladres.');
    }
    $reference = $this->repository->provisionAlias($aliasAddress, $targetAddress);
    $this->mailStack->applyAlias($aliasAddress, $targetAddress);
    return ['reference' => $reference];
  }
}
