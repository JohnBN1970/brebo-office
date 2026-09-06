<?php

declare(strict_types=1);

namespace Drupal\brebo_measure\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\brebo_building_data\Service\BuildingObjectRepository;
use Drupal\Core\Database\Connection;
use InvalidArgumentException;
use UnexpectedValueException;

/** Persistence gateway for the BREBO Measure domain. */
final class MeasureRepository {
  private const PROVENANCE = ['measured', 'detected', 'selected', 'calculated'];

  public function __construct(
    private readonly Connection $database,
    private readonly BuildingObjectRepository $objects,
    private readonly TimeInterface $time,
  ) {}

  public function createOpening(int $buildingObjectId, string $code, string $label, array $metadata = []): int {
    $object = $this->objects->load($buildingObjectId);
    $code = trim($code);
    $label = trim($label);
    if ($code === '' || $label === '') {
      throw new InvalidArgumentException('Opening code and label are required.');
    }
    $now = $this->time->getRequestTime();
    return (int) $this->database->insert('brebo_measure_opening')->fields([
      'building_nid' => (int) $object['building_nid'],
      'building_object_id' => $buildingObjectId,
      'opening_code' => $code,
      'label' => $label,
      'status' => 'active',
      'metadata' => $this->encode($metadata),
      'created' => $now,
      'changed' => $now,
    ])->execute();
  }

  public function createAssignment(int $openingId, ?int $assignedUid = NULL, array $requirements = []): int {
    $this->load('brebo_measure_opening', $openingId, 'Opening');
    $now = $this->time->getRequestTime();
    return (int) $this->database->insert('brebo_measure_assignment')->fields([
      'opening_id' => $openingId,
      'status' => 'draft',
      'assigned_uid' => $assignedUid,
      'requirements' => $this->encode($requirements),
      'created' => $now,
      'changed' => $now,
    ])->execute();
  }

  public function createCapture(int $assignmentId, string $sourceType, array $context = []): int {
    $assignment = $this->load('brebo_measure_assignment', $assignmentId, 'Measurement assignment');
    $sourceType = trim($sourceType);
    if ($sourceType === '') {
      throw new InvalidArgumentException('Measurement source type is required.');
    }
    $query = $this->database->select('brebo_measure_capture', 'c');
    $query->addExpression('MAX(version)', 'max_version');
    $maxVersion = $query->condition('assignment_id', $assignmentId)->execute()->fetchField();
    $now = $this->time->getRequestTime();
    return (int) $this->database->insert('brebo_measure_capture')->fields([
      'assignment_id' => $assignmentId,
      'opening_id' => (int) $assignment['opening_id'],
      'version' => ((int) $maxVersion) + 1,
      'status' => 'draft',
      'source_type' => $sourceType,
      'device_id' => $this->nullable($context['device_id'] ?? NULL),
      'device_model' => $this->nullable($context['device_model'] ?? NULL),
      'software_version' => $this->nullable($context['software_version'] ?? NULL),
      'operator_uid' => isset($context['operator_uid']) ? (int) $context['operator_uid'] : NULL,
      'geometry' => $this->encode($context['geometry'] ?? []),
      'quality' => $this->encode($context['quality'] ?? []),
      'captured_at' => NULL,
      'created' => $now,
      'changed' => $now,
    ])->execute();
  }

  public function addObservation(int $captureId, string $key, string $provenance, mixed $value, ?string $method = NULL, ?float $confidence = NULL, ?float $uncertaintyMm = NULL): int {
    if (!in_array($provenance, self::PROVENANCE, TRUE)) {
      throw new InvalidArgumentException('Unknown measurement provenance.');
    }
    if ($confidence !== NULL && ($confidence < 0.0 || $confidence > 1.0)) {
      throw new InvalidArgumentException('Confidence must be between 0 and 1.');
    }
    $capture = $this->load('brebo_measure_capture', $captureId, 'Measurement capture');
    if ($capture['status'] !== 'draft') {
      throw new UnexpectedValueException('Observations can only be added to draft captures.');
    }
    return (int) $this->database->insert('brebo_measure_observation')->fields([
      'capture_id' => $captureId,
      'observation_key' => trim($key),
      'provenance' => $provenance,
      'method' => $this->nullable($method),
      'value_json' => $this->encode($value),
      'confidence' => $confidence,
      'uncertainty_mm' => $uncertaintyMm,
      'created' => $this->time->getRequestTime(),
    ])->execute();
  }

  public function loadOpening(int $id): array {
    return $this->load('brebo_measure_opening', $id, 'Opening');
  }

  public function loadCapture(int $id): array {
    return $this->load('brebo_measure_capture', $id, 'Measurement capture');
  }

  private function load(string $table, int $id, string $label): array {
    $row = $this->database->select($table, 'x')->fields('x')->condition('id', $id)->execute()->fetchAssoc();
    if ($row === FALSE) {
      throw new UnexpectedValueException($label . ' does not exist.');
    }
    return $row;
  }

  private function encode(mixed $value): string {
    return json_encode($value, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
  }

  private function nullable(mixed $value): ?string {
    $value = trim((string) ($value ?? ''));
    return $value === '' ? NULL : $value;
  }
}
