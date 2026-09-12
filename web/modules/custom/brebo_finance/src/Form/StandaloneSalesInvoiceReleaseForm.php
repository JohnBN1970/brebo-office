<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Form;

use Drupal\brebo_finance\Service\SalesInvoiceNumberManager;
use Drupal\brebo_finance\Service\SalesInvoiceOutputBuilder;
use Drupal\brebo_mail_intake\Service\OutboundAttachmentService;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/** Finalizes, sends and registers a standalone BREBO sales invoice. */
final class StandaloneSalesInvoiceReleaseForm extends ConfirmFormBase {

  private ?array $draft = NULL;

  public function __construct(
    private readonly Connection $database,
    private readonly QueueFactory $queueFactory,
    private readonly KeyValueFactoryInterface $keyValueFactory,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly MailManagerInterface $mailManager,
    private readonly SalesInvoiceNumberManager $numberManager,
    private readonly SalesInvoiceOutputBuilder $outputBuilder,
    private readonly OutboundAttachmentService $attachmentService,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('database'),
      $container->get('queue'),
      $container->get('keyvalue'),
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.mail'),
      new SalesInvoiceNumberManager(
        $container->get('config.factory'),
        $container->get('keyvalue'),
        $container->get('lock'),
      ),
      new SalesInvoiceOutputBuilder(
        $container->get('database'),
        $container->get('keyvalue'),
        $container->get('entity_type.manager'),
        $container->get('brebo_office_core.simple_pdf_renderer'),
      ),
      $container->get('brebo_mail_intake.outbound_attachments'),
    );
  }

  public function getFormId(): string {
    return 'brebo_finance_standalone_sales_invoice_release_form';
  }

  public function getQuestion(): string {
    return (string) $this->t('Factuurconcept @number definitief vrijgeven en verzenden?', ['@number' => $this->draft['draft_number'] ?? '']);
  }

  public function getConfirmText(): string {
    return (string) $this->t('Definitief vrijgeven & verzenden');
  }

  public function getCancelUrl(): Url {
    return Url::fromRoute('brebo_finance.sales_workspace');
  }

  public function buildForm(array $form, FormStateInterface $form_state, ?int $draft = NULL): array {
    $draftId = (int) ($draft ?? 0);
    foreach (['brebo_finance_sales_invoice_draft', 'brebo_finance_sales_invoice_draft_line', 'brebo_finance_sales_invoice_outbox'] as $table) {
      if (!$this->database->schema()->tableExists($table)) {
        throw new \RuntimeException('Required sales-invoice release storage is unavailable. Run database updates first.');
      }
    }

    $row = $this->database->select('brebo_finance_sales_invoice_draft', 'd')
      ->fields('d')
      ->condition('id', $draftId)
      ->condition('project_nid', 0)
      ->execute()
      ->fetchAssoc();
    if ($row === FALSE) {
      throw new \InvalidArgumentException('Standalone invoice draft not found.');
    }
    $this->draft = $row;
    if (($row['status'] ?? '') !== 'draft') {
      $form['warning'] = ['#markup' => '<p><strong>' . $this->t('Dit factuurconcept is al definitief vrijgegeven of verwerkt en kan niet opnieuw worden vrijgegeven.') . '</strong></p>'];
      return $form;
    }

    $context = $this->context($draftId);
    $organization = $this->loadOrganization((int) ($context['customer_organization_nid'] ?? 0));
    $moneybirdContactId = $organization->hasField('field_brebo_moneybird_contact_id')
      ? trim((string) $organization->get('field_brebo_moneybird_contact_id')->value)
      : '';
    if ($moneybirdContactId === '') {
      throw new \RuntimeException('De gekozen debiteur heeft nog geen Moneybird contact-ID. Koppel de relatie eerst voordat deze factuur definitief wordt vrijgegeven.');
    }
    $recipient = $organization->hasField('field_brebo_org_email')
      ? trim((string) $organization->get('field_brebo_org_email')->value)
      : '';

    $lines = $this->loadLines($draftId);
    if ($lines === []) {
      throw new \RuntimeException('Invoice draft contains no lines.');
    }

    $form['summary'] = [
      '#markup' => '<p><strong>' . $this->t('Debiteur:') . '</strong> ' . htmlspecialchars((string) $organization->label())
        . '<br><strong>' . $this->t('Klantreferentie:') . '</strong> ' . htmlspecialchars((string) ($context['customer_ref'] ?? '—'))
        . '<br><strong>' . $this->t('Bedrag incl. btw:') . '</strong> € ' . number_format((float) $row['amount_inc_vat'], 2, ',', '.')
        . '<br><strong>' . $this->t('Regels:') . '</strong> ' . count($lines)
        . '</p><p>' . $this->t('BREBO Office kent nu zelf het definitieve factuurnummer toe, bevriest de inhoud, maakt de definitieve PDF en verstuurt deze naar de klant. Daarna wordt dezelfde factuur administratief naar Moneybird gesynchroniseerd.') . '</p>',
    ];
    $form['recipient'] = [
      '#type' => 'email',
      '#title' => $this->t('Verzenden naar'),
      '#required' => TRUE,
      '#default_value' => $recipient,
    ];
    $form['subject'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Onderwerp'),
      '#required' => TRUE,
      '#maxlength' => 255,
      '#default_value' => 'Factuur BREBO',
      '#description' => $this->t('Het definitieve factuurnummer wordt bij vrijgave automatisch aan het onderwerp toegevoegd.'),
    ];
    $form['message'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Bericht'),
      '#rows' => 5,
      '#default_value' => "Geachte heer/mevrouw,\n\nBijgaand ontvangt u onze factuur.\n\nMet kleurrijke groet,\nBREBO Bouw en Advies BV",
    ];
    $options = $this->attachmentService->documentOptions();
    if ($options !== []) {
      $form['document_attachments'] = [
        '#type' => 'checkboxes',
        '#title' => $this->t('Aanvullende bijlagen'),
        '#options' => $options,
        '#default_value' => array_map('intval', (array) ($context['review_attachment_document_ids'] ?? [])),
        '#description' => $this->t('De definitieve factuur-PDF gaat altijd mee. Selecteer hier aanvullende BREBO-documenten voor de klant.'),
      ];
    }

    $form_state->set('draft_id', $draftId);
    $form_state->set('moneybird_contact_id', $moneybirdContactId);
    return parent::buildForm($form, $form_state);
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $draftId = (int) $form_state->get('draft_id');
    $draft = $this->database->select('brebo_finance_sales_invoice_draft', 'd')
      ->fields('d')
      ->condition('id', $draftId)
      ->condition('project_nid', 0)
      ->execute()
      ->fetchAssoc();
    if ($draft === FALSE || ($draft['status'] ?? '') !== 'draft') {
      throw new \RuntimeException('Invoice draft is no longer releasable.');
    }

    $context = $this->context($draftId);
    $lines = $this->loadLines($draftId);
    if ($lines === []) {
      throw new \RuntimeException('Invoice draft contains no lines.');
    }

    $invoiceNumber = trim((string) ($context['final_invoice_number'] ?? ''));
    if ($invoiceNumber === '') {
      $invoiceYear = (int) substr((string) $draft['invoice_date'], 0, 4);
      $invoiceNumber = $this->numberManager->reserveNextAutomatic($invoiceYear > 0 ? $invoiceYear : NULL);
      $context['final_invoice_number'] = $invoiceNumber;
      $context['final_number_reserved_at'] = time();
      $this->saveContext($draftId, $context);
    }

    $pdf = $this->outputBuilder->finalPdf($draftId, $invoiceNumber);
    $selected = array_values(array_filter(array_map('intval', (array) $form_state->getValue('document_attachments', []))));
    $extraAttachments = $this->attachmentService->resolveDocumentIds($selected);
    $attachments = [[
      'filecontent' => $pdf['content'],
      'filename' => $pdf['filename'],
      'filemime' => 'application/pdf',
    ], ...$extraAttachments];

    $recipient = trim((string) $form_state->getValue('recipient'));
    $subjectBase = trim((string) $form_state->getValue('subject'));
    $subject = trim($subjectBase . ' ' . $invoiceNumber);
    $message = trim((string) $form_state->getValue('message'));
    $mail = $this->mailManager->mail(
      'brebo_mail_intake',
      'outbound',
      $recipient,
      'nl',
      [
        'subject' => $subject,
        'body' => $message,
        'body_html' => '',
        'attachments' => $attachments,
      ],
    );
    if (empty($mail['result'])) {
      $this->messenger()->addError($this->t('De definitieve factuur kon niet worden verzonden. Nummer @number blijft voor dit concept gereserveerd; probeer de vrijgave opnieuw.', ['@number' => $invoiceNumber]));
      return;
    }

    $this->numberManager->markUsed($invoiceNumber);
    $now = time();
    $context['final_invoice_number'] = $invoiceNumber;
    $context['final_pdf_hash'] = $pdf['hash'];
    $context['final_pdf_filename'] = $pdf['filename'];
    $context['final_sent_at'] = $now;
    $context['final_recipient'] = $recipient;
    $context['final_attachment_document_ids'] = $selected;
    $context['final_attachments'] = array_map(static fn(array $attachment): array => [
      'filename' => (string) $attachment['filename'],
      'hash' => hash('sha256', (string) $attachment['filecontent']),
    ], $attachments);
    $this->saveContext($draftId, $context);

    $payload = $this->buildPayload($draft, $lines, (string) $form_state->get('moneybird_contact_id'), $context, $invoiceNumber);
    $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
    $hash = hash('sha256', $json);
    $idempotencyKey = 'sales-invoice:' . $invoiceNumber . ':' . $hash;
    $actor = (int) $this->currentUser()->id();
    $outboxId = 0;

    $transaction = $this->database->startTransaction();
    try {
      $existing = $this->database->select('brebo_finance_sales_invoice_outbox', 'o')
        ->fields('o', ['id'])
        ->condition('draft_id', $draftId)
        ->execute()
        ->fetchField();
      if ($existing !== FALSE) {
        throw new \RuntimeException('This invoice draft already has a registration command.');
      }

      $outboxId = (int) $this->database->insert('brebo_finance_sales_invoice_outbox')->fields([
        'draft_id' => $draftId,
        'project_nid' => 0,
        'command_type' => 'sales_invoice.register',
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

      $this->database->update('brebo_finance_sales_invoice_draft')->fields([
        'status' => 'sent',
        'changed' => $now,
        'changed_by' => $actor,
      ])->condition('id', $draftId)->condition('status', 'draft')->execute();
    }
    catch (\Throwable $exception) {
      $transaction->rollBack();
      throw $exception;
    }

    $this->queueFactory->get('brebo_finance_sales_invoice_outbox')->createItem(['outbox_id' => $outboxId]);
    $this->messenger()->addStatus($this->t('Factuur @number is definitief door BREBO uitgegeven en naar @recipient verzonden. De Moneybird-registratie staat in de wachtrij.', [
      '@number' => $invoiceNumber,
      '@recipient' => $recipient,
    ]));
    $form_state->setRedirect('brebo_finance.sales_workspace');
  }

  /** @return array<string,mixed> */
  private function context(int $draftId): array {
    $context = $this->keyValueFactory->get('brebo_finance.sales_invoice_draft_context')->get((string) $draftId, []);
    if (!is_array($context) || empty($context['customer_organization_nid'])) {
      throw new \RuntimeException('Standalone invoice draft has no canonical debtor context.');
    }
    return $context;
  }

  private function saveContext(int $draftId, array $context): void {
    $this->keyValueFactory->get('brebo_finance.sales_invoice_draft_context')->set((string) $draftId, $context);
  }

  private function loadOrganization(int $organizationId): NodeInterface {
    $organization = $organizationId > 0 ? $this->entityTypeManager->getStorage('node')->load($organizationId) : NULL;
    if (!$organization instanceof NodeInterface || $organization->bundle() !== 'brebo_organization') {
      throw new \RuntimeException('Canonical debtor organisation is unavailable.');
    }
    return $organization;
  }

  /** @return array<int,array<string,mixed>> */
  private function loadLines(int $draftId): array {
    return array_values($this->database->select('brebo_finance_sales_invoice_draft_line', 'l')
      ->fields('l')
      ->condition('draft_id', $draftId)
      ->orderBy('line_number')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC));
  }

  /** @param array<int,array<string,mixed>> $lines
   *  @param array<string,mixed> $context
   *  @return array<string,mixed>
   */
  private function buildPayload(array $draft, array $lines, string $moneybirdContactId, array $context, string $invoiceNumber): array {
    return [
      'schema' => 'brebo.sales_invoice.register.v1',
      'source' => [
        'system' => 'brebo-office',
        'draft_id' => (int) $draft['id'],
        'draft_number' => (string) $draft['draft_number'],
        'invoice_number' => $invoiceNumber,
        'project_nid' => 0,
      ],
      'invoice' => [
        'invoice_id' => $invoiceNumber,
        'contact_id' => $moneybirdContactId,
        'reference' => trim((string) ($context['customer_ref'] ?? '')),
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
