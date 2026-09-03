<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\brebo_finance\Service\PaymentReleaseManager;
use Drupal\brebo_finance\Service\PurchaseInvoiceControlViewBuilder;
use Drupal\brebo_finance\Service\ThreeWayMatchManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** Controlled invoice matching and payment actions for Office. */
final class PurchaseInvoiceActionController extends ControllerBase {

  public function __construct(
    private readonly Connection $database,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ThreeWayMatchManager $matchManager,
    private readonly PaymentReleaseManager $paymentReleaseManager,
    private readonly PurchaseInvoiceControlViewBuilder $controlViewBuilder,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('database'),
      $container->get('entity_type.manager'),
      $container->get('brebo_finance.three_way_match_manager'),
      $container->get('brebo_finance.payment_release_manager'),
      $container->get('brebo_finance.purchase_invoice_control_view_builder'),
    );
  }

  public function state(int $invoice_id): JsonResponse {
    $this->assertInvoiceAccess($invoice_id);
    $state = $this->controlViewBuilder->build($invoice_id);
    $state['permissions'] = [
      'manage_finance' => $this->currentUser()->hasPermission('manage brebo finance'),
      'manage_procurement' => $this->currentUser()->hasPermission('manage brebo procurement'),
      'approve_finance' => $this->currentUser()->hasPermission('approve brebo finance'),
    ];
    return $this->json($state);
  }

  public function matchLine(int $invoice_id, int $invoice_line_id): JsonResponse {
    $this->assertInvoiceAccess($invoice_id);
    $this->assertLineBelongsToInvoice($invoice_id, $invoice_line_id);
    try {
      $result = $this->matchManager->matchLine($invoice_line_id, (int) $this->currentUser()->id());
    }
    catch (\InvalidArgumentException|\RuntimeException|\UnexpectedValueException $e) {
      throw new BadRequestHttpException($e->getMessage(), $e);
    }
    return $this->json(['ok' => TRUE, 'invoice_id' => $invoice_id, 'invoice_line_id' => $invoice_line_id] + $result);
  }

  public function requestRelease(int $invoice_id, Request $request): JsonResponse {
    $this->assertInvoiceAccess($invoice_id);
    $data = $this->payload($request);
    try {
      $releaseId = $this->paymentReleaseManager->prepare(
        $invoice_id,
        (string) ($data['release_number'] ?? ''),
        (string) ($data['requested_payment_date'] ?? ''),
        (int) $this->currentUser()->id(),
      );
    }
    catch (\InvalidArgumentException|\RuntimeException|\UnexpectedValueException $e) {
      throw new BadRequestHttpException($e->getMessage(), $e);
    }
    return $this->json(['ok' => TRUE, 'invoice_id' => $invoice_id, 'payment_release_id' => $releaseId], 201);
  }

  public function decideRelease(int $invoice_id, int $release_id, Request $request): JsonResponse {
    $this->assertInvoiceAccess($invoice_id);
    $this->assertReleaseBelongsToInvoice($invoice_id, $release_id);
    $data = $this->payload($request);
    try {
      $this->paymentReleaseManager->decide(
        $release_id,
        (string) ($data['decision'] ?? ''),
        (string) ($data['note'] ?? ''),
        (int) $this->currentUser()->id(),
      );
    }
    catch (\InvalidArgumentException|\RuntimeException|\UnexpectedValueException $e) {
      throw new BadRequestHttpException($e->getMessage(), $e);
    }
    return $this->json(['ok' => TRUE, 'invoice_id' => $invoice_id, 'payment_release_id' => $release_id, 'decision' => (string) ($data['decision'] ?? '')]);
  }

  public function executeRelease(int $invoice_id, int $release_id, Request $request): JsonResponse {
    $this->assertInvoiceAccess($invoice_id);
    $this->assertReleaseBelongsToInvoice($invoice_id, $release_id);
    $data = $this->payload($request);
    try {
      $this->paymentReleaseManager->markExecuted(
        $release_id,
        (string) ($data['moneybird_payment_ref'] ?? ''),
        (int) $this->currentUser()->id(),
      );
    }
    catch (\InvalidArgumentException|\RuntimeException|\UnexpectedValueException $e) {
      throw new BadRequestHttpException($e->getMessage(), $e);
    }
    return $this->json(['ok' => TRUE, 'invoice_id' => $invoice_id, 'payment_release_id' => $release_id, 'status' => 'executed']);
  }

  private function assertInvoiceAccess(int $invoiceId): array {
    $invoice = $this->database->select('brebo_finance_purchase_invoice', 'i')->fields('i')->condition('id', $invoiceId)->execute()->fetchAssoc();
    if ($invoice === FALSE) {
      throw new NotFoundHttpException('Purchase invoice does not exist.');
    }
    $projectNid = (int) ($invoice['project_nid'] ?? 0);
    if ($projectNid <= 0) {
      throw new BadRequestHttpException('Invoice must be coded to a BREBO project first.');
    }
    $project = $this->entityTypeManager->getStorage('node')->load($projectNid);
    if ($project === NULL || $project->bundle() !== 'brebo_project') {
      throw new NotFoundHttpException('BREBO project does not exist.');
    }
    if (!$project->access('view', $this->currentUser())) {
      throw new AccessDeniedHttpException('No access to this BREBO project.');
    }
    return $invoice;
  }

  private function assertLineBelongsToInvoice(int $invoiceId, int $lineId): void {
    $exists = $this->database->select('brebo_finance_purchase_invoice_line', 'l')->fields('l', ['id'])->condition('id', $lineId)->condition('invoice_id', $invoiceId)->execute()->fetchField();
    if (!$exists) {
      throw new NotFoundHttpException('Invoice line does not belong to this invoice.');
    }
  }

  private function assertReleaseBelongsToInvoice(int $invoiceId, int $releaseId): void {
    $exists = $this->database->select('brebo_finance_payment_release', 'r')->fields('r', ['id'])->condition('id', $releaseId)->condition('invoice_id', $invoiceId)->execute()->fetchField();
    if (!$exists) {
      throw new NotFoundHttpException('Payment release does not belong to this invoice.');
    }
  }

  private function payload(Request $request): array {
    try {
      $data = json_decode($request->getContent(), TRUE, 512, JSON_THROW_ON_ERROR);
    }
    catch (\JsonException) {
      throw new BadRequestHttpException('Invalid JSON payload.');
    }
    if (!is_array($data)) {
      throw new BadRequestHttpException('JSON object required.');
    }
    return $data;
  }

  private function json(array $data, int $status = 200): JsonResponse {
    $response = new JsonResponse($data, $status);
    $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
    $response->headers->set('X-Content-Type-Options', 'nosniff');
    return $response;
  }
}
