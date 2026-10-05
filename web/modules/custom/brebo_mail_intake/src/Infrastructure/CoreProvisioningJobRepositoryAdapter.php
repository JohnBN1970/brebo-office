<?php

declare(strict_types=1);

namespace Drupal\brebo_mail_intake\Infrastructure;

use Brebo\Mail\Contract\ProvisioningJobRepositoryInterface;
use Drupal\Core\Database\Connection;

final class CoreProvisioningJobRepositoryAdapter implements ProvisioningJobRepositoryInterface {

  public function __construct(private readonly Connection $database) {}

  public function enqueue(string $resourceType, int $resourceId, string $operation): int {
    $now = time();
    return (int) $this->database->insert('brebo_mail_provisioning_job')->fields([
      'resource_type' => $resourceType,
      'resource_id' => $resourceId,
      'operation' => $operation,
      'status' => 'pending',
      'provider_reference' => '',
      'error_message' => '',
      'created' => $now,
      'changed' => $now,
    ])->execute();
  }

  public function load(int $jobId): ?array {
    $row = $this->database->select('brebo_mail_provisioning_job', 'j')
      ->fields('j')
      ->condition('id', $jobId)
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();
    return $row === FALSE ? NULL : $row;
  }

  public function markProvisioning(int $jobId): void {
    $this->update($jobId, ['status' => 'provisioning', 'error_message' => '']);
  }

  public function markActive(int $jobId, string $providerReference): void {
    $this->update($jobId, [
      'status' => 'active',
      'provider_reference' => $providerReference,
      'error_message' => '',
    ]);
  }

  public function markError(int $jobId, string $message): void {
    $this->update($jobId, [
      'status' => 'error',
      'error_message' => mb_substr($message, 0, 1000),
    ]);
  }

  private function update(int $jobId, array $fields): void {
    $fields['changed'] = time();
    $this->database->update('brebo_mail_provisioning_job')
      ->fields($fields)
      ->condition('id', $jobId)
      ->execute();
  }
}
