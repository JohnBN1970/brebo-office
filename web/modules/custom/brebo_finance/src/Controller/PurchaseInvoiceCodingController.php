<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Controller;

use Drupal\Core\Database\Connection;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\brebo_finance\Service\PurchaseInvoiceCodingManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** API for controlled purchase-invoice capture and coding. */
final class PurchaseInvoiceCodingController implements ContainerInjectionInterface {

  public function __construct(
    private readonly Connection $database,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly PurchaseInvoiceCodingManager $codingManager,
    private readonly AccountProxyInterface $currentUser,
  ) {}

  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('database'),
      $container->get('entity_type.manager'),
      $container->get('brebo_finance.purchase_invoice_coding_manager'),
      $container->get('current_user'),
    );
  }

  public function overview(int $invoice_id): JsonResponse {
    $invoice = $this->invoice($invoice_id);
    $projectNid = (int) $invoice['project_nid'];
    if ($projectNid > 0) {
      $this->assertProjectAccess($projectNid);
    }
    $lines = $this->database->select('brebo_finance_purchase_invoice_line', 'l')->fields('l')->condition('invoice_id', $invoice_id)->orderBy('line_number')->execute()->fetchAll(\PDO::FETCH_ASSOC);
    $commitments = [];
    if ($projectNid > 0) {
      $query = $this->database->select('brebo_finance_commitment_line', 'cl');
      $query->join('brebo_finance_commitment', 'c', 'c.id = cl.commitment_id');
      $query->addField('cl', 'id');
      $query->addField('cl', 'line_number');
      $query->addField('cl', 'description');
      $query->addField('cl', 'amount_ex_vat');
      $query->addField('cl', 'unit_price_ex_vat');
      $query->addField('cl', 'vat_code');
      $query->addField('c', 'id', 'commitment_id');
      $query->addField('c', 'commitment_number');
      $query->addField('c', 'supplier_name');
      $commitments = $query->condition('c.project_nid', $projectNid)->condition('c.status', ['cancelled'], 'NOT IN')->orderBy('c.id', 'DESC')->orderBy('cl.line_number')->execute()->fetchAll(\PDO::FETCH_ASSOC);
    }
    $lineTotal = array_reduce($lines, static fn(float $sum, array $line): float => $sum + (float) ($line['amount_inc_vat'] ?? 0), 0.0);
    return $this->json([
      'invoice' => $invoice,
      'projects' => $this->projectChoices(),
      'lines' => $lines,
      'commitment_lines' => $commitments,
      'reconciliation' => [
        'header_amount_inc_vat' => (float) ($invoice['amount_inc_vat'] ?? 0),
        'line_amount_inc_vat' => $lineTotal,
        'difference_inc_vat' => round((float) ($invoice['amount_inc_vat'] ?? 0) - $lineTotal, 4),
        'balanced' => abs((float) ($invoice['amount_inc_vat'] ?? 0) - $lineTotal) < 0.005,
      ],
    ]);
  }

  public function assignProject(int $invoice_id, Request $request): JsonResponse {
    $data = $this->payload($request);
    $projectNid = (int) ($data['project_nid'] ?? 0);
    $this->assertProjectAccess($projectNid);
    $this->codingManager->assignProject($invoice_id, $projectNid, (int) $this->currentUser->id());
    return $this->json(['ok' => TRUE, 'invoice_id' => $invoice_id, 'project_nid' => $projectNid]);
  }

  public function upsertLine(int $invoice_id, int $line_number, Request $request): JsonResponse {
    $invoice = $this->invoice($invoice_id);
    if ((int) $invoice['project_nid'] > 0) {
      $this->assertProjectAccess((int) $invoice['project_nid']);
    }
    $lineId = $this->codingManager->upsertLine($invoice_id, $line_number, $this->payload($request), (int) $this->currentUser->id());
    return $this->json(['ok' => TRUE, 'invoice_id' => $invoice_id, 'line_id' => $lineId], 201);
  }

  public function linkCommitmentLine(int $invoice_id, int $invoice_line_id, Request $request): JsonResponse {
    $invoice = $this->invoice($invoice_id);
    $this->assertProjectAccess((int) $invoice['project_nid']);
    $data = $this->payload($request);
    $commitmentLineId = (int) ($data['commitment_line_id'] ?? 0);
    $this->codingManager->linkCommitmentLine($invoice_id, $invoice_line_id, $commitmentLineId, (int) $this->currentUser->id());
    return $this->json(['ok' => TRUE, 'invoice_id' => $invoice_id, 'invoice_line_id' => $invoice_line_id, 'commitment_line_id' => $commitmentLineId]);
  }

  private function invoice(int $invoiceId): array {
    $invoice = $this->database->select('brebo_finance_purchase_invoice', 'i')->fields('i')->condition('id', $invoiceId)->execute()->fetchAssoc();
    if ($invoice === FALSE) {
      throw new NotFoundHttpException('Purchase invoice does not exist.');
    }
    return $invoice;
  }

  /** @return array<int,array{id:int,label:string}> */
  private function projectChoices(): array {
    $storage = $this->entityTypeManager->getStorage('node');
    $ids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'brebo_project')
      ->condition('status', 1)
      ->sort('title', 'ASC')
      ->range(0, 500)
      ->execute();
    if ($ids === []) {
      return [];
    }
    $projects = [];
    foreach ($storage->loadMultiple($ids) as $project) {
      if (!$project->access('view', $this->currentUser)) {
        continue;
      }
      $projects[] = ['id' => (int) $project->id(), 'label' => (string) $project->label()];
    }
    return $projects;
  }

  private function assertProjectAccess(int $projectNid): void {
    if ($projectNid <= 0) {
      throw new BadRequestHttpException('A valid BREBO project is required.');
    }
    $project = $this->entityTypeManager->getStorage('node')->load($projectNid);
    if ($project === NULL || $project->bundle() !== 'brebo_project') {
      throw new NotFoundHttpException('BREBO project does not exist.');
    }
    if (!$project->access('view', $this->currentUser)) {
      throw new AccessDeniedHttpException('No access to this BREBO project.');
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
