<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Controller;

use Drupal\brebo_finance\Service\PerformanceReceiptManager;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/** API for submitting and independently verifying performed work. */
final class PerformanceReceiptController extends ControllerBase {
  public function __construct(private readonly PerformanceReceiptManager $manager) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('brebo_finance.performance_receipt_manager'));
  }

  public function submit(Request $request): JsonResponse {
    $data = $this->json($request);
    foreach (['commitment_line_id', 'amount_ex_vat', 'description', 'evidence'] as $field) {
      if (!array_key_exists($field, $data)) throw new BadRequestHttpException('Missing field: ' . $field);
    }
    if (!is_array($data['evidence'])) throw new BadRequestHttpException('Evidence must be an array.');
    $id = $this->manager->register((int) $data['commitment_line_id'], (string) $data['amount_ex_vat'], (string) $data['description'], $data['evidence'], (int) $this->currentUser()->id());
    return new JsonResponse(['status' => 'submitted', 'performance_receipt_id' => $id], 201, ['Cache-Control' => 'private, no-store']);
  }

  public function verify(int $receipt_id, Request $request): JsonResponse {
    $data = $this->json($request);
    foreach (['building_evidence_complete', 'quality_accepted', 'note'] as $field) {
      if (!array_key_exists($field, $data)) throw new BadRequestHttpException('Missing field: ' . $field);
    }
    $this->manager->verify($receipt_id, (bool) $data['building_evidence_complete'], (bool) $data['quality_accepted'], (string) $data['note'], (int) $this->currentUser()->id());
    return new JsonResponse(['status' => ((bool) $data['building_evidence_complete'] && (bool) $data['quality_accepted']) ? 'verified' : 'rejected'], 200, ['Cache-Control' => 'private, no-store']);
  }

  private function json(Request $request): array {
    try { $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR); }
    catch (\JsonException) { throw new BadRequestHttpException('Invalid JSON body.'); }
    if (!is_array($data)) throw new BadRequestHttpException('JSON body must be an object.');
    return $data;
  }
}
