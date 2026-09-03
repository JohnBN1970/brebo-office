<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\brebo_finance\Service\PaymentReleaseManager;
use Drupal\brebo_finance\Service\PerformanceReceiptManager;
use Drupal\brebo_finance\Service\PurchaseInvoiceControlViewBuilder;
use Drupal\brebo_finance\Service\ThreeWayMatchManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** Controlled invoice matching, performance and payment actions for Office. */
final class PurchaseInvoiceActionController extends ControllerBase {

  public function __construct(
    private readonly Connection $database,
    private readonly EntityTypeManagerInterface $financeEntityTypeManager,
    private readonly ThreeWayMatchManager $matchManager,
    private readonly PaymentReleaseManager $paymentReleaseManager,
    private readonly PerformanceReceiptManager $performanceReceiptManager,
    private readonly PurchaseInvoiceControlViewBuilder $controlViewBuilder,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('database'),
      $container->get('entity_type.manager'),
      $container->get('brebo_finance.three_way_match_manager'),
      $container->get('brebo_finance.payment_release_manager'),
      $container->get('brebo_finance.performance_receipt_manager'),
      $container->get('brebo_finance.purchase_invoice_control_view_builder'),
    );
  }

  public function actionState(int $invoice_id): JsonResponse {
    $this->assertInvoiceAccess($invoice_id);
    $state = $this->controlViewBuilder->build($invoice_id);
    $state['permissions'] = [
      'manage_finance' => $this->currentUser()->hasPermission('manage brebo finance'),
      'manage_procurement' => $this->currentUser()->hasPermission('manage brebo procurement'),
      'approve_finance' => $this->currentUser()->hasPermission('approve brebo finance'),
    ];
    return $this->json($state);
  }

  public function registerPerformance(int $invoice_id, int $invoice_line_id, Request $request): JsonResponse {
    $this->assertInvoiceAccess($invoice_id);
    $line = $this->invoiceLine($invoice_id, $invoice_line_id);
    $commitmentLineId = (int) ($line['commitment_line_id'] ?? 0);
    if ($commitmentLineId <= 0) {
      throw new BadRequestHttpException('Invoice line must be linked to a commitment line first.');
    }
    $data = $this->payload($request);
    foreach (['amount_ex_vat', 'description', 'evidence', 'building_nid', 'object_id'] as $field) {
      if (!array_key_exists($field, $data)) {
        throw new BadRequestHttpException('Missing field: ' . $field);
      }
    }
    if (!is_array($data['evidence'])) {
      throw new BadRequestHttpException('Evidence must be an array.');
    }
    try {
      $receiptId = $this->performanceReceiptManager->register(
        $commitmentLineId,
        (string) $data['amount_ex_vat'],
        (string) $data['description'],
        $data['evidence'],
        (int) $data['building_nid'],
        (int) $data['object_id'],
        (int) $this->currentUser()->id(),
      );
    }
    catch (\InvalidArgumentException | \RuntimeException $exception) {
      throw new BadRequestHttpException($exception->getMessage(), $exception);
    }
    return $this->json(['receipt_id' => $receiptId], 201);
  }

  public function verifyPerformance(int $invoice_id, int $receipt_id, Request $request): JsonResponse {
    $this->assertInvoiceAccess($invoice_id);
    $this->assertReceiptBelongsToInvoice($invoice_id, $receipt_id);
    $data = $this->payload($request);
    foreach (['building_evidence_complete', 'quality_accepted', 'note'] as $field) {
      if (!array_key_exists($field, $data)) {
        throw new BadRequestHttpException('Missing field: ' . $field);
      }
    }
    try {
      $this->performanceReceiptManager->verify(
        $receipt_id,
        (bool) $data['building_evidence_complete'],
        (bool) $data['quality_accepted'],
        (string) $data['note'],
        (int) $this->currentUser()->id(),
      );
    }
    catch (\InvalidArgumentException | \RuntimeException $exception) {
      throw new BadRequestHttpException($exception->getMessage(), $exception);
    }
    return $this->json(['status' => 'verified']);
  }

  public function matchLine(int $invoice_id, int $invoice_line_id): JsonResponse {
    $this->assertInvoiceAccess($invoice_id);
    $this->invoiceLine($invoice_id, $invoice_line_id);
    try {
      $result = $this->matchManager->matchLine($invoice_line_id, (int) $this->currentUser()->id());
    }
    catch (\InvalidArgumentException | \RuntimeException $exception) {
      throw new BadRequestHttpException($exception->getMessage(), $exception);
    }
    return $this->json($result);
  }

  public function requestRelease(int $invoice_id, Request $request): JsonResponse {
    $this->assertInvoiceAccess($invoice_id);
    $data = $this->payload($request);
    foreach (['release_number', 'requested_payment_date'] as $field) {
      if (!array_key_exists($field, $data)) {
        throw new BadRequestHttpException('Missing field: ' . $field);
      }
    }
    try {
      $releaseId = $this->paymentReleaseManager->prepare(
        $invoice_id,
        (string) $data['release_number'],
        (string) $data['requested_payment_date'],
        (int) $this->currentUser()->id(),
      );
    }
    catch (\InvalidArgumentException | \RuntimeException $exception) {
      throw new BadRequestHttpException($exception->getMessage(), $exception);
    }
    return $this->json(['release_id' => $releaseId], 201);
  }

  public function decideRelease(int $invoice_id, int $release_id, Request $request): JsonResponse {
    $this->assertInvoiceAccess($invoice_id);
    $this->assertReleaseBelongsToInvoice($invoice_id, $release_id);
    $data = $this->payload($request);
    foreach (['decision', 'note'] as $field) {
      if (!array_key_exists($field, $data)) {
        throw new BadRequestHttpException('Missing field: ' . $field);
      }
    }
    try {
      $this->paymentReleaseManager->decide(
        $release_id,
        (string) $data['decision'],
        (string) $data['note'],
        (int) $this->currentUser()->id(),
      );
    }
    catch (\InvalidArgumentException | \RuntimeException $exception) {
      throw new BadRequestHttpException($exception->getMessage(), $exception);
    }
    return $this->json(['status' => 'decided']);
  }

  public function executeRelease(int $invoice_id, int $release_id, Request $request): JsonResponse {
    $this->assertInvoiceAccess($invoice_id);
    $this->assertReleaseBelongsToInvoice($invoice_id, $release_id);
    $data = $this->payload($request);
    if (!array_key_exists('moneybird_payment_ref', $data)) {
      throw new BadRequestHttpException('Missing field: moneybird_payment_ref');
    }
    try {
      $this->paymentReleaseManager->markExecuted(
        $release_id,
        (string) $data['moneybird_payment_ref'],
        (int) $this->currentUser()->id(),
      );
    }
    catch (\InvalidArgumentException | \RuntimeException $exception) {
      throw new BadRequestHttpException($exception->getMessage(), $exception);
    }
    return $this->json(['status' => 'executed']);
  }

  /** @return array<string, mixed> */
  private function assertInvoiceAccess(int $invoiceId): array {
    $invoice = $this->database->select('brebo_finance_purchase_invoice', 'i')
      ->fields('i')
      ->condition('id', $invoiceId)
      ->execute()
      ->fetchAssoc();
    if (!$invoice) {
      throw new NotFoundHttpException('Purchase invoice not found.');
    }
    $projectNid = (int) ($invoice['project_nid'] ?? 0);
    if ($projectNid <= 0) {
      throw new BadRequestHttpException('Purchase invoice must be coded to a project first.');
    }
    $project = $this->financeEntityTypeManager->getStorage('node')->load($projectNid);
    if (!$project || $project->bundle() !== 'brebo_project') {
      throw new NotFoundHttpException('Project not found.');
    }
    if (!$project->access('view', $this->currentUser())) {
      throw new AccessDeniedHttpException('Project access denied.');
    }
    return $invoice;
  }

  /** @return array<string, mixed> */
  private function invoiceLine(int $invoiceId, int $lineId): array {
    $line = $this->database->select('brebo_finance_purchase_invoice_line', 'il')
      ->fields('il')
      ->condition('id', $lineId)
      ->condition('invoice_id', $invoiceId)
      ->execute()
      ->fetchAssoc();
    if (!$line) {
      throw new NotFoundHttpException('Purchase invoice line not found.');
    }
    return $line;
  }

  private function assertReceiptBelongsToInvoice(int $invoiceId, int $receiptId): void {
    $query = $this->database->select('brebo_finance_performance_receipt', 'pr');
    $query->innerJoin('brebo_finance_purchase_invoice_line', 'il', 'il.commitment_line_id = pr.commitment_line_id');
    $exists = $query
      ->fields('pr', ['id'])
      ->condition('pr.id', $receiptId)
      ->condition('il.invoice_id', $invoiceId)
      ->range(0, 1)
      ->execute()
      ->fetchField();
    if (!$exists) {
      throw new NotFoundHttpException('Performance receipt not found for this invoice.');
    }
  }

  private function assertReleaseBelongsToInvoice(int $invoiceId, int $releaseId): void {
    $exists = $this->database->select('brebo_finance_payment_release', 'pr')
      ->fields('pr', ['id'])
      ->condition('id', $releaseId)
      ->condition('invoice_id', $invoiceId)
      ->execute()
      ->fetchField();
    if (!$exists) {
      throw new NotFoundHttpException('Payment release not found for this invoice.');
    }
  }

  /** @return array<string, mixed> */
  private function payload(Request $request): array {
    $data = json_decode($request->getContent(), TRUE);
    if (!is_array($data)) {
      throw new BadRequestHttpException('JSON object required.');
    }
    return $data;
  }

  /** @param array<string, mixed> $data */
  private function json(array $data, int $status = 200): JsonResponse {
    $response = new JsonResponse($data, $status);
    $response->headers->set('Cache-Control', 'no-store');
    $response->headers->set('X-Content-Type-Options', 'nosniff');
    return $response;
  }
}
