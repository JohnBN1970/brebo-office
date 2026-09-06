<?php

declare(strict_types=1);

namespace Drupal\brebo_measure\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\brebo_measure\Service\MeasureRepository;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/** Minimal JSON API for BREBO Measure workflow experiments. */
final class MeasureApiController extends ControllerBase {

  public function __construct(
    private readonly MeasureRepository $repository,
    private readonly AccountProxyInterface $currentUser,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('brebo_measure.repository'),
      $container->get('current_user'),
    );
  }

  public function opening(int $opening_id): JsonResponse {
    return new JsonResponse(['opening' => $this->repository->loadOpening($opening_id)]);
  }

  public function createAssignment(int $opening_id, Request $request): JsonResponse {
    $payload = $this->payload($request);
    $assignedUid = isset($payload['assigned_uid']) ? (int) $payload['assigned_uid'] : (int) $this->currentUser->id();
    $id = $this->repository->createAssignment(
      $opening_id,
      $assignedUid > 0 ? $assignedUid : NULL,
      is_array($payload['requirements'] ?? NULL) ? $payload['requirements'] : [],
    );

    return new JsonResponse(['assignment_id' => $id], 201);
  }

  public function createCapture(int $assignment_id, Request $request): JsonResponse {
    $payload = $this->payload($request);
    $sourceType = trim((string) ($payload['source_type'] ?? ''));
    $context = is_array($payload['context'] ?? NULL) ? $payload['context'] : [];
    if (!isset($context['operator_uid']) && $this->currentUser->isAuthenticated()) {
      $context['operator_uid'] = (int) $this->currentUser->id();
    }
    $id = $this->repository->createCapture($assignment_id, $sourceType, $context);

    return new JsonResponse(['capture_id' => $id], 201);
  }

  public function createObservation(int $capture_id, Request $request): JsonResponse {
    $payload = $this->payload($request);
    $id = $this->repository->addObservation(
      $capture_id,
      trim((string) ($payload['key'] ?? '')),
      trim((string) ($payload['provenance'] ?? '')),
      $payload['value'] ?? NULL,
      isset($payload['method']) ? (string) $payload['method'] : NULL,
      isset($payload['confidence']) ? (float) $payload['confidence'] : NULL,
      isset($payload['uncertainty_mm']) ? (float) $payload['uncertainty_mm'] : NULL,
    );

    return new JsonResponse(['observation_id' => $id], 201);
  }

  private function payload(Request $request): array {
    if ($request->getContent() === '') {
      return [];
    }
    $decoded = json_decode($request->getContent(), TRUE, 512, JSON_THROW_ON_ERROR);
    return is_array($decoded) ? $decoded : [];
  }

}
