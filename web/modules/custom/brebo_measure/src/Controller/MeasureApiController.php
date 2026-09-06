<?php

declare(strict_types=1);

namespace Drupal\brebo_measure\Controller;

use Drupal\brebo_measure\Service\MeasureRepository;
use Drupal\Core\Controller\ControllerBase;
use InvalidArgumentException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Throwable;

/** Minimal Office API for the BREBO Measure capture workflow. */
final class MeasureApiController extends ControllerBase {

  public function __construct(private readonly MeasureRepository $measure) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('brebo_measure.repository'));
  }

  public function opening(int $opening_id): JsonResponse {
    return $this->response(['opening' => $this->measure->loadOpening($opening_id)]);
  }

  public function createAssignment(Request $request, int $opening_id): JsonResponse {
    return $this->execute(function () use ($request, $opening_id): array {
      $data = $this->payload($request);
      $id = $this->measure->createAssignment(
        $opening_id,
        isset($data['assigned_uid']) ? (int) $data['assigned_uid'] : NULL,
        is_array($data['requirements'] ?? NULL) ? $data['requirements'] : [],
      );
      return ['assignment_id' => $id, 'opening_id' => $opening_id];
    }, 201);
  }

  public function createCapture(Request $request, int $assignment_id): JsonResponse {
    return $this->execute(function () use ($request, $assignment_id): array {
      $data = $this->payload($request);
      $sourceType = (string) ($data['source_type'] ?? '');
      unset($data['source_type']);
      $id = $this->measure->createCapture($assignment_id, $sourceType, $data);
      return ['capture_id' => $id, 'assignment_id' => $assignment_id];
    }, 201);
  }

  public function addObservation(Request $request, int $capture_id): JsonResponse {
    return $this->execute(function () use ($request, $capture_id): array {
      $data = $this->payload($request);
      $key = trim((string) ($data['key'] ?? ''));
      if ($key === '') {
        throw new InvalidArgumentException('Observation key is required.');
      }
      $id = $this->measure->addObservation(
        $capture_id,
        $key,
        (string) ($data['provenance'] ?? ''),
        $data['value'] ?? NULL,
        isset($data['method']) ? (string) $data['method'] : NULL,
        isset($data['confidence']) ? (float) $data['confidence'] : NULL,
        isset($data['uncertainty_mm']) ? (float) $data['uncertainty_mm'] : NULL,
      );
      return ['observation_id' => $id, 'capture_id' => $capture_id];
    }, 201);
  }

  private function payload(Request $request): array {
    $content = trim($request->getContent());
    if ($content === '') {
      return [];
    }
    $data = json_decode($content, TRUE, 512, JSON_THROW_ON_ERROR);
    if (!is_array($data)) {
      throw new InvalidArgumentException('JSON object expected.');
    }
    return $data;
  }

  private function execute(callable $operation, int $successStatus): JsonResponse {
    try {
      return $this->response($operation(), $successStatus);
    }
    catch (InvalidArgumentException $exception) {
      return $this->response(['error' => $exception->getMessage()], 400);
    }
    catch (Throwable $exception) {
      return $this->response(['error' => $exception->getMessage()], 422);
    }
  }

  private function response(array $data, int $status = 200): JsonResponse {
    return new JsonResponse($data, $status, ['Cache-Control' => 'private, no-store']);
  }

}
