<?php

declare(strict_types=1);

namespace Drupal\brebo_project_cockpit\Form;

use Drupal\brebo_finance\Service\SalesInvoiceOutputBuilder;
use Drupal\brebo_mail_intake\Service\OutboundAttachmentService;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/** Sends a project invoice concept to the customer for review. */
final class ProjectSalesInvoiceReviewForm extends FormBase {

  public function __construct(
    private readonly Connection $database,
    private readonly KeyValueFactoryInterface $keyValueFactory,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly MailManagerInterface $mailManager,
    private readonly SalesInvoiceOutputBuilder $outputBuilder,
    private readonly OutboundAttachmentService $attachmentService,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('database'),
      $container->get('keyvalue'),
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.mail'),
      new SalesInvoiceOutputBuilder($container->get('database'), $container->get('keyvalue'), $container->get('entity_type.manager'), $container->get('brebo_office_core.simple_pdf_renderer')),
      $container->get('brebo_mail_intake.outbound_attachments'),
    );
  }

  public function getFormId(): string {
    return 'brebo_project_cockpit_sales_invoice_review_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, ?NodeInterface $node = NULL, ?int $draft = NULL): array {
    if ($node === NULL || $node->bundle() !== 'brebo_project' || $draft === NULL) throw new \InvalidArgumentException('BREBO project and invoice draft required.');
    $projectId = (int) $node->id();
    $invoice = $this->loadDraft((int) $draft, $projectId);
    $context = $this->context((int) $draft, $projectId);
    $organization = $this->loadOrganization((int) $context['customer_organization_nid']);
    $recipient = $organization->hasField('field_brebo_org_email') ? trim((string) $organization->get('field_brebo_org_email')->value) : '';

    $form_state->set('draft_id', (int) $draft);
    $form_state->set('project_id', $projectId);
    $form['warning'] = ['#markup' => '<p><strong>CONCEPT / TER BEOORDELING.</strong> Deze verzending geeft geen definitief factuurnummer uit en maakt geen openstaande post aan.</p>'];
    $form['summary'] = ['#markup' => '<p><strong>Project:</strong> ' . htmlspecialchars((string) $node->label()) . '<br><strong>Debiteur:</strong> ' . htmlspecialchars((string) $organization->label()) . '<br><strong>Concept:</strong> ' . htmlspecialchars((string) $invoice['draft_number']) . '<br><strong>Bedrag incl. btw:</strong> € ' . number_format((float) $invoice['amount_inc_vat'], 2, ',', '.') . '</p>'];
    $form['recipient'] = ['#type' => 'email', '#title' => $this->t('Naar'), '#required' => TRUE, '#default_value' => $recipient];
    $form['subject'] = ['#type' => 'textfield', '#title' => $this->t('Onderwerp'), '#required' => TRUE, '#maxlength' => 255, '#default_value' => 'Conceptfactuur ter beoordeling - ' . (string) $invoice['draft_number']];
    $form['message'] = ['#type' => 'textarea', '#title' => $this->t('Bericht'), '#rows' => 6, '#default_value' => "Geachte heer/mevrouw,\n\nBijgaand ontvangt u onze conceptfactuur ter beoordeling. Wilt u de gegevens en referentie controleren en eventuele opmerkingen aan ons doorgeven?\n\nDit document is uitsluitend een concept en nog geen definitieve factuur."];
    $options = $this->attachmentService->documentOptions();
    if ($options !== []) {
      $form['document_attachments'] = ['#type' => 'checkboxes', '#title' => $this->t('Aanvullende bijlagen'), '#options' => $options, '#default_value' => array_map('intval', (array) ($context['review_attachment_document_ids'] ?? [])), '#description' => $this->t('De conceptfactuur-PDF gaat altijd mee.')];
    }
    $form['actions']['submit'] = ['#type' => 'submit', '#value' => $this->t('Concept ter beoordeling versturen'), '#button_type' => 'primary'];
    $form['actions']['cancel'] = ['#type' => 'link', '#title' => $this->t('Terug'), '#url' => Url::fromRoute('brebo_project_cockpit.invoices', ['node' => $projectId]), '#attributes' => ['class' => ['button']]];
    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $draftId = (int) $form_state->get('draft_id');
    $projectId = (int) $form_state->get('project_id');
    $invoice = $this->loadDraft($draftId, $projectId);
    $context = $this->context($draftId, $projectId);
    $pdf = $this->outputBuilder->conceptPdf($draftId);
    $selected = array_values(array_filter(array_map('intval', (array) $form_state->getValue('document_attachments', []))));
    $extraAttachments = $this->attachmentService->resolveDocumentIds($selected);
    $attachments = [['filecontent' => $pdf['content'], 'filename' => $pdf['filename'], 'filemime' => 'application/pdf'], ...$extraAttachments];
    $recipient = trim((string) $form_state->getValue('recipient'));
    $subject = trim((string) $form_state->getValue('subject'));
    $intro = trim((string) $form_state->getValue('message'));
    $body = $intro . "\n\nCONCEPT / TER BEOORDELING\nConceptnummer: " . (string) $invoice['draft_number'] . "\nDit is geen definitieve factuur en er is nog geen definitief factuurnummer uitgegeven.";
    $result = $this->mailManager->mail('brebo_mail_intake', 'outbound', $recipient, 'nl', ['subject' => $subject, 'body' => $body, 'body_html' => '', 'attachments' => $attachments]);
    if (empty($result['result'])) {
      $this->messenger()->addError($this->t('De conceptfactuur kon niet worden verzonden. Het concept is ongewijzigd gebleven.'));
      return;
    }
    $now = time();
    $history = is_array($context['review_history'] ?? NULL) ? $context['review_history'] : [];
    $history[] = ['sent_at' => $now, 'recipient' => $recipient, 'subject' => $subject, 'draft_number' => (string) $invoice['draft_number'], 'pdf_hash' => $pdf['hash'], 'pdf_filename' => $pdf['filename'], 'attachment_document_ids' => $selected];
    $context['review_status'] = 'sent_for_review';
    $context['review_last_sent_at'] = $now;
    $context['review_last_recipient'] = $recipient;
    $context['review_attachment_document_ids'] = $selected;
    $context['review_history'] = $history;
    $this->keyValueFactory->get('brebo_finance.sales_invoice_draft_context')->set((string) $draftId, $context);
    $this->messenger()->addStatus($this->t('Concept @number is ter beoordeling verzonden naar @recipient en blijft wijzigbaar zonder definitief factuurnummer.', ['@number' => $invoice['draft_number'], '@recipient' => $recipient]));
    $form_state->setRedirect('brebo_project_cockpit.invoices', ['node' => $projectId]);
  }

  /** @return array<string,mixed> */
  private function loadDraft(int $draftId, int $projectId): array {
    $row = $this->database->select('brebo_finance_sales_invoice_draft', 'd')->fields('d')->condition('id', $draftId)->condition('project_nid', $projectId)->condition('status', 'draft')->execute()->fetchAssoc();
    if ($row === FALSE) throw new \InvalidArgumentException('Project invoice draft not found.');
    return $row;
  }

  /** @return array<string,mixed> */
  private function context(int $draftId, int $projectId): array {
    $context = $this->keyValueFactory->get('brebo_finance.sales_invoice_draft_context')->get((string) $draftId, []);
    if (!is_array($context) || (int) ($context['project_nid'] ?? 0) !== $projectId || empty($context['customer_organization_nid'])) throw new \RuntimeException('Projectfactuurconcept heeft geen canonieke debiteurcontext.');
    return $context;
  }

  private function loadOrganization(int $organizationId): NodeInterface {
    $organization = $this->entityTypeManager->getStorage('node')->load($organizationId);
    if (!$organization instanceof NodeInterface || $organization->bundle() !== 'brebo_organization') throw new \RuntimeException('Canonieke debiteur is niet beschikbaar.');
    return $organization;
  }
}
