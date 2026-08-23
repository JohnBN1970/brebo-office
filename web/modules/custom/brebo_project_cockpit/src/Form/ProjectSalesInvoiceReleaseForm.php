<?php

declare(strict_types=1);

namespace Drupal\brebo_project_cockpit\Form;

use Drupal\Core\Database\Connection;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/** Releases a sales-invoice draft as one immutable integration command. */
final class ProjectSalesInvoiceReleaseForm extends ConfirmFormBase {

  private ?array $draft = NULL;

  public function __construct(private readonly Connection $database) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('database'));
  }

  public function getFormId(): string {
    return 'brebo_project_cockpit_sales_invoice_release_form';
  }

  public function getQuestion(): string {
    return (string) $this->t('Factuurconcept @number definitief vrijgeven en verzenden?', ['@number' => $this->draft['draft_number'] ?? '']);
  }

  public function getConfirmText(): string {
    return (string) $this->t('Vrijgeven & verzenden');
  }

  public function getCancelUrl(): Url {
    return Url::fromRoute('brebo_project_cockpit.invoices', ['node' => (int) ($this->draft['project_nid'] ?? 0)]);
  }

  public function buildForm(array $form, FormStateInterface $form_state, ?NodeInterface $node = NULL, ?int $draft = NULL): array {
    if ($node === NULL || $node->bundle() !== 'brebo_project' || $draft === NULL) {
      throw new \InvalidArgumentException('BREBO project and invoice draft required.');
    }
    foreach (['brebo_finance_sales_invoice_draft', 'brebo_finance_sales_invoice_draft_line', 'brebo_finance_sales_invoice_outbox'] as $table) {
      if (!$this->database->schema()->tableExists($table)) {
        throw new \RuntimeException('Required sales-invoice release storage is unavailable. Run database updates first.');
      }
    }
    $row = $this->database->select('brebo_finance_sales_invoice_draft', 'd')->fields('d')->condition('id', $draft)->condition('project_nid', (int) $node->id())->execute()->fetchAssoc();
    if ($row === FALSE) {
      throw new \InvalidArgumentException('Invoice draft not found for this project.');
    }
    $this->draft = $row;
    if (($row['status'] ?? '') !== 'draft') {
      $form['warning'] = ['#markup' => '<p><strong>' . $this->t('Dit factuurconcept is al vrijgegeven of verwerkt en kan niet opnieuw worden vrijgegeven.') . '</strong></p>'];
      return $form;
    }
    $lines = $this->loadLines((int) $row['id']);
    if ($lines === []) {
      throw new \RuntimeException('Invoice draft contains no lines.');
    }
    $form['summary'] = ['#markup' => '<p><strong>' . $this->t('Bedrag incl. btw:') . '</strong> € ' . number_format((float) $row['amount_inc_vat'], 2, ',', '.') . '<br><strong>' . $this->t('Regels:') . '</strong> ' . count($lines) . '</p><p>' . $this->t('Na vrijgave wordt de inhoud bevroren. BREBO Office zet exact één idempotente opdracht klaar voor de integration API. Moneybird maakt en verzendt daarna de officiële factuur.') . '</p>'];
    $form_state->set('draft_id', (int) $row['id']);
    $form_state->set('project_id', (int) $node->id());
    return parent::buildForm($form, $form_state);
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $draftId = (int) $form_state->get('draft_id');
    $projectId = (int) $form_state->get('project_id');
    $draft = $this->database->select('brebo_finance_sales_invoice_draft', 'd')->fields('d')->condition('id', $draftId)->condition('project_nid', $projectId)->execute()->fetchAssoc();
    if ($draft === FALSE || ($draft['status'] ?? '') !== 'draft') {
      throw new \RuntimeException('Invoice draft is no longer releasable.');
    }
    $lines = $this->loadLines($draftId);
    $payload = $this->buildPayload($draft, $lines);
    $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
    $hash = hash('sha256', $json);
    $idempotencyKey = 'sales-invoice:' . $draftId . ':' . $hash;
    $actor = (int) $this->currentUser()->id();
    $now = time();
    $transaction = $this->database->startTransaction();
    try {
      $existing = $this->database->select('brebo_finance_sales_invoice_outbox', 'o')->fields('o', ['id'])->condition('draft_id', $draftId)->condition('command_type', 'sales_invoice.create_and_send')->execute()->fetchField();
      if ($existing !== FALSE) {
        throw new \RuntimeException('This invoice draft already has a release command.');
      }
      $this->database->insert('brebo_finance_sales_invoice_outbox')->fields([
        'draft_id' => $draftId,
        'project_nid' => $projectId,
        'command_type' => 'sales_invoice.create_and_send',
        'status' => 'queued',
        'idempotency_key' => $idempotencyKey,
        'payload_hash' => $hash,
        'payload' => $json,
        'attempt_count' => 0,
        'released' => $now,
        'released_by' => $actor,
        'created' => $now,
        'created_by' => $actor,
        'changed' => $now,
        'changed_by' => $actor,
      ])->execute();
      $this->database->update('brebo_finance_sales_invoice_draft')->fields(['status' => 'released', 'changed' => $now, 'changed_by' => $actor])->condition('id', $draftId)->condition('status', 'draft')->execute();
    }
    catch (\Throwable $exception) {
      $transaction->rollBack();
      throw $exception;
    }
    $this->messenger()->addStatus($this->t('Factuurconcept @number is vrijgegeven. Eén verzendopdracht staat klaar voor de BREBO integration API.', ['@number' => $draft['draft_number']]));
    $form_state->setRedirect('brebo_project_cockpit.invoices', ['node' => $projectId]);
  }

  private function loadLines(int $draftId): array {
    return array_values($this->database->select('brebo_finance_sales_invoice_draft_line', 'l')->fields('l')->condition('draft_id', $draftId)->orderBy('line_number')->execute()->fetchAll(\PDO::FETCH_ASSOC));
  }

  private function buildPayload(array $draft, array $lines): array {
    return [
      'schema' => 'brebo.sales_invoice.create_and_send.v1',
      'source' => ['system' => 'brebo-office', 'draft_id' => (int) $draft['id'], 'draft_number' => (string) $draft['draft_number'], 'project_nid' => (int) $draft['project_nid']],
      'invoice' => [
        'invoice_date' => (string) $draft['invoice_date'],
        'due_date' => (string) $draft['due_date'],
        'description' => (string) ($draft['description'] ?? ''),
        'amount_ex_vat' => (string) $draft['amount_ex_vat'],
        'vat_amount' => (string) $draft['vat_amount'],
        'amount_inc_vat' => (string) $draft['amount_inc_vat'],
        'lines' => array_map(static fn(array $line): array => [
          'line_number' => (int) $line['line_number'],
          'source_type' => (string) $line['source_type'],
          'source_id' => (int) $line['source_id'],
          'description' => (string) $line['description'],
          'amount_ex_vat' => (string) $line['amount_ex_vat'],
          'vat_code' => (string) $line['vat_code'],
          'vat_rate' => (string) $line['vat_rate'],
          'vat_amount' => (string) $line['vat_amount'],
          'amount_inc_vat' => (string) $line['amount_inc_vat'],
        ], $lines),
      ],
    ];
  }

}
