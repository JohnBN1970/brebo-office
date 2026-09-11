<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Form;

use Drupal\brebo_finance\Service\SalesInvoiceOutputBuilder;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/** Sends a standalone sales invoice concept to the customer for review. */
final class StandaloneSalesInvoiceReviewForm extends FormBase {

  public function __construct(
    private readonly Connection $database,
    private readonly KeyValueFactoryInterface $keyValueFactory,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly MailManagerInterface $mailManager,
    private readonly SalesInvoiceOutputBuilder $outputBuilder,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('database'),
      $container->get('keyvalue'),
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.mail'),
      new SalesInvoiceOutputBuilder(
        $container->get('database'),
        $container->get('keyvalue'),
        $container->get('entity_type.manager'),
        $container->get('brebo_office_core.simple_pdf_renderer'),
      ),
    );
  }

  public function getFormId(): string {
    return 'brebo_finance_standalone_sales_invoice_review_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, ?int $draft = NULL): array {
    $draftId = (int) ($draft ?? 0);
    $invoice = $this->loadDraft($draftId);
    $context = $this->context($draftId);
    $organization = $this->loadOrganization((int) ($context['customer_organization_nid'] ?? 0));
    $recipient = $organization->hasField('field_brebo_org_email')
      ? trim((string) $organization->get('field_brebo_org_email')->value)
      : '';
    $lines = is_array($context['lines'] ?? NULL) ? $context['lines'] : [];

    $form_state->set('draft_id', $draftId);
    $form_state->set('organization_id', (int) $organization->id());

    $form['warning'] = [
      '#markup' => '<p><strong>CONCEPT / TER BEOORDELING.</strong> Deze verzending maakt geen definitieve factuur, kent geen factuurnummer toe en maakt geen openstaande post aan.</p>',
    ];
    $form['summary'] = [
      '#type' => 'table',
      '#header' => [$this->t('Onderdeel'), $this->t('Waarde')],
      '#rows' => [
        [$this->t('Concept'), (string) $invoice['draft_number']],
        [$this->t('Debiteur'), $organization->label()],
        [$this->t('Klantreferentie'), (string) ($context['customer_ref'] ?? '—')],
        [$this->t('Omschrijving'), (string) $invoice['description']],
        [$this->t('Bedrag incl. btw'), '€ ' . number_format((float) $invoice['amount_inc_vat'], 2, ',', '.')],
        [$this->t('Aantal regels'), (string) count($lines)],
      ],
    ];
    $form['pdf_preview'] = [
      '#type' => 'link',
      '#title' => $this->t('PDF-preview openen'),
      '#url' => Url::fromRoute('brebo_finance.sales_standalone_pdf_preview', ['draft' => $draftId]),
      '#attributes' => ['class' => ['button'], 'target' => '_blank'],
    ];
    $form['recipient'] = [
      '#type' => 'email',
      '#title' => $this->t('Naar'),
      '#required' => TRUE,
      '#default_value' => $recipient,
      '#description' => $this->t('Standaard het administratieve e-mailadres van de centrale organisatie. Voor deze verzending mag je een ander klantadres kiezen.'),
    ];
    $form['subject'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Onderwerp'),
      '#required' => TRUE,
      '#maxlength' => 255,
      '#default_value' => 'Conceptfactuur ter beoordeling - ' . (string) $invoice['draft_number'],
    ];
    $form['message'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Bericht'),
      '#rows' => 6,
      '#default_value' => "Geachte heer/mevrouw,\n\nBijgaand ontvangt u onze conceptfactuur ter beoordeling. Wilt u de gegevens en referentie controleren en eventuele opmerkingen aan ons doorgeven?\n\nDit document is uitsluitend een concept en nog geen definitieve factuur.",
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Concept ter beoordeling versturen'),
      '#button_type' => 'primary',
    ];
    $form['actions']['edit'] = [
      '#type' => 'link',
      '#title' => $this->t('Terug naar concept'),
      '#url' => Url::fromRoute('brebo_finance.sales_standalone_edit', ['draft' => $draftId]),
      '#attributes' => ['class' => ['button']],
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $draftId = (int) $form_state->get('draft_id');
    $invoice = $this->loadDraft($draftId);
    $context = $this->context($draftId);
    $organization = $this->loadOrganization((int) $form_state->get('organization_id'));
    $recipient = trim((string) $form_state->getValue('recipient'));
    $subject = trim((string) $form_state->getValue('subject'));
    $intro = trim((string) $form_state->getValue('message'));
    $lines = is_array($context['lines'] ?? NULL) ? $context['lines'] : [];
    $pdf = $this->outputBuilder->conceptPdf($draftId);

    $body = [$intro, '', 'CONCEPT / TER BEOORDELING', 'Conceptnummer: ' . (string) $invoice['draft_number']];
    if (trim((string) ($context['customer_ref'] ?? '')) !== '') {
      $body[] = 'Klantreferentie: ' . trim((string) $context['customer_ref']);
    }
    $body[] = 'Omschrijving: ' . (string) $invoice['description'];
    $body[] = '';
    $body[] = 'De conceptfactuur is als PDF bijgevoegd.';
    $body[] = 'Dit is geen definitieve factuur en hieraan is nog geen factuurnummer toegekend.';

    $result = $this->mailManager->mail(
      'brebo_mail_intake',
      'outbound',
      $recipient,
      'nl',
      [
        'subject' => $subject,
        'body' => implode("\n", $body),
        'body_html' => '',
        'attachments' => [[
          'filecontent' => $pdf['content'],
          'filename' => $pdf['filename'],
          'filemime' => 'application/pdf',
        ]],
      ],
    );
    if (empty($result['result'])) {
      $this->messenger()->addError($this->t('De conceptfactuur kon niet worden verzonden. Het concept is ongewijzigd gebleven.'));
      return;
    }

    $now = time();
    $history = is_array($context['review_history'] ?? NULL) ? $context['review_history'] : [];
    $history[] = [
      'sent_at' => $now,
      'sent_by' => (int) $this->currentUser()->id(),
      'recipient' => $recipient,
      'subject' => $subject,
      'draft_number' => (string) $invoice['draft_number'],
      'content_hash' => hash('sha256', json_encode([
        'invoice' => $invoice,
        'organization_id' => (int) $organization->id(),
        'customer_ref' => (string) ($context['customer_ref'] ?? ''),
        'lines' => $lines,
      ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)),
      'pdf_hash' => $pdf['hash'],
      'pdf_filename' => $pdf['filename'],
    ];
    $context['review_status'] = 'sent_for_review';
    $context['review_last_sent_at'] = $now;
    $context['review_last_recipient'] = $recipient;
    $context['review_history'] = $history;
    $this->keyValueFactory->get('brebo_finance.sales_invoice_draft_context')->set((string) $draftId, $context);

    $this->messenger()->addStatus($this->t('Concept @number is als PDF ter beoordeling verzonden naar @recipient. Het blijft een wijzigbaar concept zonder definitief factuurnummer.', [
      '@number' => (string) $invoice['draft_number'],
      '@recipient' => $recipient,
    ]));
    $form_state->setRedirect('brebo_finance.sales_workspace');
  }

  /** @return array<string, mixed> */
  private function loadDraft(int $draftId): array {
    if ($draftId <= 0 || !$this->database->schema()->tableExists('brebo_finance_sales_invoice_draft')) {
      throw new \InvalidArgumentException('Standalone invoice draft not found.');
    }
    $draft = $this->database->select('brebo_finance_sales_invoice_draft', 'd')
      ->fields('d')
      ->condition('id', $draftId)
      ->condition('project_nid', 0)
      ->condition('status', 'draft')
      ->execute()
      ->fetchAssoc();
    if ($draft === FALSE) {
      throw new \InvalidArgumentException('Standalone invoice draft is no longer available for review.');
    }
    return $draft;
  }

  /** @return array<string, mixed> */
  private function context(int $draftId): array {
    $context = $this->keyValueFactory->get('brebo_finance.sales_invoice_draft_context')->get((string) $draftId, []);
    if (!is_array($context) || empty($context['customer_organization_nid'])) {
      throw new \RuntimeException('Standalone invoice draft has no canonical debtor context.');
    }
    return $context;
  }

  private function loadOrganization(int $organizationId): NodeInterface {
    $organization = $organizationId > 0 ? $this->entityTypeManager->getStorage('node')->load($organizationId) : NULL;
    if (!$organization instanceof NodeInterface || $organization->bundle() !== 'brebo_organization') {
      throw new \RuntimeException('Canonical debtor organisation is unavailable.');
    }
    return $organization;
  }

}
