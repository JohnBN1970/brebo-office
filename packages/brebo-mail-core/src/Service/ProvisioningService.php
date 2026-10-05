<?php

declare(strict_types=1);

namespace Brebo\Mail\Service;

use Brebo\Mail\Contract\MailGatewayInterface;
use Brebo\Mail\Contract\ProvisioningJobRepositoryInterface;
use RuntimeException;
use Throwable;

final class ProvisioningService {
  public function __construct(
    private readonly ProvisioningJobRepositoryInterface $jobs,
    private readonly MailGatewayInterface $gateway,
  ) {}

  public function enqueue(string $resourceType, int $resourceId, string $operation): int {
    if (!in_array($resourceType, ['domain', 'mailbox', 'alias'], TRUE)) {
      throw new RuntimeException('Onbekend provisioning resource type.');
    }
    if (!in_array($operation, ['create', 'update', 'delete'], TRUE)) {
      throw new RuntimeException('Onbekende provisioning operatie.');
    }
    return $this->jobs->enqueue($resourceType, $resourceId, $operation);
  }

  /** @param array<string,mixed> $payload */
  public function execute(int $jobId, array $payload): string {
    $job = $this->jobs->load($jobId);
    if (!$job) {
      throw new RuntimeException('Provisioning job niet gevonden.');
    }

    $this->jobs->markProvisioning($jobId);

    try {
      $reference = match ((string) $job['resource_type']) {
        'domain' => $this->gateway->provisionDomain($payload),
        'mailbox' => $this->gateway->provisionMailbox($payload),
        'alias' => $this->gateway->provisionAlias(
          (string) ($payload['alias'] ?? ''),
          (string) ($payload['target'] ?? ''),
        ),
        default => throw new RuntimeException('Onbekend provisioning resource type.'),
      };
      $this->jobs->markActive($jobId, $reference);
      return $reference;
    }
    catch (Throwable $e) {
      $this->jobs->markError($jobId, $e->getMessage());
      throw $e;
    }
  }

  /** @return array<int,array<string,mixed>> */
  public function recentJobs(int $limit = 50): array {
    return $this->jobs->recent($limit);
  }

  /** @return array{provider:string,available:bool,message:string} */
  public function gatewayHealth(): array {
    return $this->gateway->health();
  }
}
