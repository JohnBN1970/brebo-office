<?php
declare(strict_types=1);
namespace Brebo\Office\Finance;

/**
 * Boundary between an untrusted HTTP batch identifier and Office-owned data.
 * The loader must read the batch, its release seal and instructions from the
 * canonical Office database in the same consistent snapshot.
 */
final class OfficePaymentTrustedBatchResolver {
  public function __construct(private readonly \Closure $loadCanonicalBatch) {}

  public function resolve(mixed $requestedBatchId): array {
    if (!is_int($requestedBatchId) && !(is_string($requestedBatchId)
      && preg_match('/^[1-9][0-9]{0,17}$/D', $requestedBatchId))) {
      throw new \DomainException('Invalid payment batch identifier');
    }
    $id = filter_var($requestedBatchId, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
    if ($id === false) throw new \DomainException('Invalid payment batch identifier');
    $data = ($this->loadCanonicalBatch)($id);
    if (!is_array($data) || !is_array($data['batch'] ?? null)
      || (int)($data['batch']['id'] ?? 0) !== $id
      || ($data['batch']['status'] ?? null) !== 'released'
      || !is_array($data['items'] ?? null) || $data['items'] === []
      || !is_string($data['sealed_hash'] ?? null)
      || !preg_match('/^[a-f0-9]{64}$/D', $data['sealed_hash'])
      || !hash_equals((string)($data['batch']['sealed_hash'] ?? ''), $data['sealed_hash'])
      || !isset($data['batch']['created_by'], $data['batch']['released_by'])
      || (int)$data['batch']['created_by'] < 1
      || (int)$data['batch']['released_by'] < 1
      || !in_array(($data['batch']['controller_verdict'] ?? null), ['green', 'orange'], true)
      || !isset($data['batch']['control_sum'])
      || !is_numeric($data['batch']['control_sum'])
      || (float)$data['batch']['control_sum'] <= 0) {
      throw new \DomainException('Canonical released payment batch unavailable');
    }
    OfficePaymentCanonicalSeal::verify($data['batch'], $data['items']);
    return $data;
  }
}
