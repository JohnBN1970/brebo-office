<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Controller;

use Drupal\brebo_finance\Service\PerformanceEvidenceReviewManager;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/** API for reviewing individual performance evidence items. */
final class PerformanceEvidenceReviewController extends ControllerBase {
  public function __construct(private readonly PerformanceEvidenceReviewManager $manager) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('brebo_finance.performance_evidence_review_manager'));
  }

  public function summary(int $receipt_id): JsonResponse {
    return new JsonResponse($this->manager->summary($receipt_id), 200, ['Cache-Control' => 'private, no-store']);
  }

  public function review(int $receipt_id, Request $request): JsonResponse {
    try { $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR); }
    catch (\JsonException) { throw new BadRequestHttpException('Invalid JSON body.'); }
    if (!is_array($data)) throw new BadRequestHttpException('JSON body must be an object.');
    foreach (['evidence_ref', 'decision', 'note'] as $field) if (!array_key_exists($field, $data)) throw new BadRequestHttpException('Missing field: ' . $field);
    $this->manager->review($receipt_id, (string) $data['evidence_ref'], (string) $data['decision'], (string) $data['note'], (int) $this->currentUser()->id());
    return new JsonResponse($this->manager->summary($receipt_id), 200, ['Cache-Control' => 'private, no-store']);
  }
}
