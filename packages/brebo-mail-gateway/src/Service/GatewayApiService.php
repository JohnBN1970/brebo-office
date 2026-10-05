<?php

declare(strict_types=1);

namespace Brebo\MailGateway\Service;

use Brebo\MailGateway\Contract\DkimKeyGeneratorInterface;
use Brebo\MailGateway\Contract\GatewayProvisioningRepositoryInterface;
use InvalidArgumentException;

final class GatewayApiService {

  public function __construct(
    private readonly GatewayProvisioningRepositoryInterface $repository,
    private readonly DkimKeyGeneratorInterface $dkim,
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
    $reference = $this->repository->provisionDomain($payload);
    $dkim = $this->dkim->generate($domain);

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
    return ['reference' => $this->repository->provisionMailbox($payload)];
  }

  /** @return array{reference:string} */
  public function provisionAlias(string $aliasAddress, string $targetAddress): array {
    $aliasAddress = mb_strtolower(trim($aliasAddress));
    $targetAddress = mb_strtolower(trim($targetAddress));
    if (filter_var($aliasAddress, FILTER_VALIDATE_EMAIL) === FALSE || filter_var($targetAddress, FILTER_VALIDATE_EMAIL) === FALSE) {
      throw new InvalidArgumentException('Ongeldig alias- of doeladres.');
    }
    return ['reference' => $this->repository->provisionAlias($aliasAddress, $targetAddress)];
  }
}
