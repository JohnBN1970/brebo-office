<?php

declare(strict_types=1);

namespace Brebo\Mail\Infrastructure;

use Brebo\Mail\Contract\GatewayHttpClientInterface;
use Brebo\Mail\Contract\MailGatewayInterface;
use RuntimeException;

final class HttpMailGateway implements MailGatewayInterface {

  public function __construct(private readonly GatewayHttpClientInterface $client) {}

  public function health(): array {
    $response = $this->client->get('/v1/health');
    return [
      'provider' => (string) ($response['provider'] ?? 'brebo-gateway'),
      'available' => (bool) ($response['available'] ?? FALSE),
      'message' => (string) ($response['message'] ?? ''),
    ];
  }

  public function provisionDomain(array $domain): string {
    return $this->reference($this->client->post('/v1/domains', $domain));
  }

  public function provisionMailbox(array $mailbox): string {
    return $this->reference($this->client->post('/v1/mailboxes', $mailbox));
  }

  public function provisionAlias(string $aliasAddress, string $targetAddress): string {
    return $this->reference($this->client->post('/v1/aliases', [
      'alias' => $aliasAddress,
      'target' => $targetAddress,
    ]));
  }

  /** @param array<string,mixed> $response */
  private function reference(array $response): string {
    $reference = trim((string) ($response['reference'] ?? ''));
    if ($reference === '') {
      throw new RuntimeException('Mail gateway gaf geen provisioning-referentie terug.');
    }
    return $reference;
  }
}
