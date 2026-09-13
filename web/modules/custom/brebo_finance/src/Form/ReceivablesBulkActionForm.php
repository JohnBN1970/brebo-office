<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Form;

use Drupal\brebo_finance\Service\CollectionDebtorProfileRepository;
use Drupal\brebo_finance\Service\CollectionDossierBuilder;
use Drupal\brebo_finance\Service\CollectionTransferManager;
use Drupal\brebo_finance\Service\NlLegalCollectionProvider;
use Drupal\brebo_finance\Service\ReceivablesDunningManager;
use Drupal\brebo_finance\Service\SalesInvoiceDebtorResolver;
use Drupal\Core\Database\Connection;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/** Bulk workbench for controlled receivables actions and collection handoff. */
final class ReceivablesBulkActionForm extends FormBase {

  public function __construct(
    private readonly Connection $database,
    private readonly ReceivablesDunningManager $dunningManager,
    private readonly MailManagerInterface $mailManager,
    private readonly SalesInvoiceDebtorResolver $debtorResolver,
    private readonly CollectionDebtorProfileRepository $profiles,
    private readonly CollectionTransferManager $transferManager,
  ) {}

  public static function create(ContainerInterface $container): static {
    $dunning = new ReceivablesDunningManager($container->get('database'), $container->get('keyvalue'), $container->get('config.factory'));
    $resolver = new SalesInvoiceDebtorResolver($container->get('database'), $container->get('keyvalue'), $container->get('entity_type.manager'));
    $profiles = new CollectionDebtorProfileRepository($container->get('keyvalue'));
    $builder = new CollectionDossierBuilder($container->get('database'), $dunning);
    $provider = new NlLegalCollectionProvider($container->get('http_client'), $container->get('config.factory'));
    $transfer = new CollectionTransferManager($resolver, $profiles, $builder, $provider, $container->get('keyvalue'));
    return new static($container->get('database'), $dunning, $container->get('plugin.manager.mail'), $resolver, $profiles, $transfer);
  }

  public function getFormId(): string { return 'brebo_finance_receivables_bulk_action_form'; }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $options = []; $profileRows = [];
    if ($this->database->schema()->tableExists('brebo_finance_sales_invoice')) {
      $rows = $this->database->select('brebo_finance_sales_invoice', 'i')->fields('i', ['id'])->orderBy('due_date')->range(0, 250)->execute()->fetchCol();
      foreach ($rows as $id) {
        $invoiceId = (int) $id;
        $state = $this->dunningManager->state($invoiceId);
        if ($state['blocked_reason'] !== NULL) continue;
        $step = $this->dunningManager->nextStep($invoiceId);
        $collectionReady = (string) $state['status'] === 'gereed_voor_incasso';
        if ($step === NULL && !$collectionReady) continue;
        $label = $collectionReady ? 'Overdragen aan incasso' : $this->stepLabel((string) $step);
        $options[$invoiceId] = sprintf('%s · %s · € %s · %s', $state['invoice_number'], $label, number_format((float) $state['outstanding_amount_inc_vat'], 2, ',', '.'), $state['due_date']);
        if ($collectionReady) {
          try {
            $relation = $this->debtorResolver->resolve($invoiceId);
            $complete = $this->profiles->complete((int) $relation['organization_id']);
          }
          catch (\Throwable) { $complete = FALSE; }
          if (!$complete) {
            $profileRows[] = [(string) $state['invoice_number'], ['data' => ['#type' => 'link', '#title' => $this->t('NAW aanvullen'), '#url' => Url::fromRoute('brebo_finance.collection_debtor_profile', ['invoice' => $invoiceId])]]];
          }
        }
      }
    }
    $form['intro'] = ['#markup' => '<p><strong>Bulk debiteurenwerkbak.</strong> Office controleert iedere factuur opnieuw vlak vóór uitvoering. Herinneringen gaan alleen naar het centrale relatie-e-mailadres. Incasso-overdracht vereist een compleet gestructureerd debiteurprofiel en een beschikbare provider.</p>'];
    if ($profileRows !== []) $form['profiles'] = ['#type' => 'table', '#caption' => $this->t('Incassodossiers met ontbrekende gestructureerde NAW'), '#header' => [$this->t('Factuur'), $this->t('Actie')], '#rows' => $profileRows];
    $form['invoices'] = ['#type' => 'checkboxes', '#title' => $this->t('Beschikbare dossiers'), '#options' => $options, '#required' => TRUE];
    $form['note'] = ['#type' => 'textarea', '#title' => $this->t('Aanvullende notitie'), '#rows' => 3, '#description' => $this->t('Wordt alleen toegevoegd aan herinnering/aanmaning/sommatie, niet aan incasso-overdracht.')];
    $form['mode'] = ['#type' => 'radios', '#title' => $this->t('Uitvoering'), '#options' => ['preview' => $this->t('Alleen preflight / controle'), 'execute' => $this->t('Toegestane acties uitvoeren')], '#default_value' => 'preview'];
    $form['actions']['submit'] = ['#type' => 'submit', '#value' => $this->t('Bulkcontrole uitvoeren'), '#button_type' => 'primary'];
    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $ids = array_values(array_filter(array_map('intval', (array) $form_state->getValue('invoices', []))));
    $execute = $form_state->getValue('mode') === 'execute'; $note = trim((string) $form_state->getValue('note'));
    $ready = $blocked = $executed = $failed = 0;
    foreach ($ids as $invoiceId) {
      $state = $this->dunningManager->state($invoiceId);
      if ($state['blocked_reason'] !== NULL) { $blocked++; continue; }

      if ((string) $state['status'] === 'gereed_voor_incasso') {
        try {
          $relation = $this->debtorResolver->resolve($invoiceId);
          if (!$this->profiles->complete((int) $relation['organization_id'])) { $blocked++; continue; }
          $ready++;
          if ($execute) { $this->transferManager->transfer($invoiceId, (int) $this->currentUser()->id()); $executed++; }
        }
        catch (\Throwable) { $failed++; }
        continue;
      }

      $step = $this->dunningManager->nextStep($invoiceId);
      if ($step === NULL) { $blocked++; continue; }
      $recipient = NULL;
      if ($step !== 'collection_ready') {
        try { $recipient = $this->debtorResolver->resolve($invoiceId); }
        catch (\Throwable) { $blocked++; continue; }
      }
      $ready++;
      if (!$execute) continue;
      if ($step === 'collection_ready') {
        $this->dunningManager->recordStep($invoiceId, 'collection_ready', ['source' => 'bulk_workbench'], (int) $this->currentUser()->id()); $executed++; continue;
      }

      [$subject, $body] = $this->messageFor($step, $state, $note);
      $mail = $this->mailManager->mail('brebo_mail_intake', 'outbound', (string) $recipient['email'], 'nl', ['subject' => $subject, 'body' => $body, 'body_html' => '', 'attachments' => []]);
      if (empty($mail['result'])) { $failed++; continue; }
      $this->dunningManager->recordStep($invoiceId, $step, ['source' => 'bulk_workbench', 'recipient' => (string) $recipient['email'], 'debtor_organization_id' => (int) $recipient['organization_id'], 'subject' => $subject, 'body_hash' => hash('sha256', $body)], (int) $this->currentUser()->id());
      $executed++;
    }
    $this->messenger()->addStatus($this->t('Bulkcontrole: @ready gereed, @blocked geblokkeerd, @executed uitgevoerd, @failed fouten.', ['@ready' => $ready, '@blocked' => $blocked, '@executed' => $executed, '@failed' => $failed]));
  }

  /** @param array<string,mixed> $state */
  private function messageFor(string $step, array $state, string $note): array {
    $number = (string) $state['invoice_number']; $due = (string) $state['due_date']; $amount = number_format((float) $state['outstanding_amount_inc_vat'], 2, ',', '.');
    $label = match ($step) { 'reminder' => 'Betalingsherinnering', 'demand' => 'Aanmaning', 'final_notice' => 'Laatste sommatie', default => 'Betalingsbericht' };
    $body = "Geachte heer/mevrouw,\n\nVolgens onze administratie staat factuur {$number} met vervaldatum {$due} nog open voor € {$amount}.\n\nWij verzoeken u het openstaande bedrag te voldoen. Indien betaling inmiddels heeft plaatsgevonden, kunt u dit bericht als niet verzonden beschouwen.";
    if ($note !== '') $body .= "\n\n{$note}"; $body .= "\n\nMet kleurrijke groet,\nBREBO Bouw en Advies BV";
    return [$label . ' BREBO - factuur ' . $number, $body];
  }

  private function stepLabel(string $step): string { return match ($step) { 'reminder' => 'Herinnering gereed', 'demand' => 'Aanmaning gereed', 'final_notice' => 'Laatste sommatie gereed', 'collection_ready' => 'Incasso gereed', default => $step }; }
}
