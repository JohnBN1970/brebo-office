<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Form;

use Drupal\brebo_finance\Service\ReceivablesDunningManager;
use Drupal\Core\Database\Connection;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/** Bulk workbench for controlled receivables actions. */
final class ReceivablesBulkActionForm extends FormBase {

  public function __construct(
    private readonly Connection $database,
    private readonly ReceivablesDunningManager $dunningManager,
    private readonly MailManagerInterface $mailManager,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('database'),
      new ReceivablesDunningManager($container->get('database'), $container->get('keyvalue'), $container->get('config.factory')),
      $container->get('plugin.manager.mail'),
    );
  }

  public function getFormId(): string { return 'brebo_finance_receivables_bulk_action_form'; }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $options = [];
    if ($this->database->schema()->tableExists('brebo_finance_sales_invoice')) {
      $rows = $this->database->select('brebo_finance_sales_invoice', 'i')->fields('i', ['id'])->orderBy('due_date')->range(0, 250)->execute()->fetchCol();
      foreach ($rows as $id) {
        $invoiceId = (int) $id;
        $state = $this->dunningManager->state($invoiceId);
        $step = $this->dunningManager->nextStep($invoiceId);
        if ($step === NULL || $state['blocked_reason'] !== NULL) continue;
        $options[$invoiceId] = sprintf('%s · %s · € %s · %s', $state['invoice_number'], $this->stepLabel($step), number_format((float) $state['outstanding_amount_inc_vat'], 2, ',', '.'), $state['due_date']);
      }
    }
    $form['intro'] = ['#markup' => '<p><strong>Bulk debiteurenwerkbak.</strong> Alleen facturen waarvoor Office op dit moment exact één toegestane vervolgstap heeft, zijn selecteerbaar. Voor iedere geselecteerde factuur wordt vlak vóór uitvoering opnieuw gecontroleerd op betaling, betwisting, hold en betalingsregeling.</p>'];
    $form['invoices'] = ['#type' => 'checkboxes', '#title' => $this->t('Verzendklare dossiers'), '#options' => $options, '#required' => TRUE];
    $form['recipient_domain_note'] = ['#markup' => '<p><em>Herinneringen worden in deze eerste bulk-slice nog niet automatisch verzonden: de centrale debiteur-e-mail moet eerst betrouwbaar uit de factuur/relatie worden opgelost. Incasso-gereed markeren kan wel bulkmatig.</em></p>'];
    $form['mode'] = ['#type' => 'radios', '#title' => $this->t('Uitvoering'), '#options' => ['preview' => $this->t('Alleen preflight / controle'), 'execute' => $this->t('Toegestane acties uitvoeren')], '#default_value' => 'preview'];
    $form['actions']['submit'] = ['#type' => 'submit', '#value' => $this->t('Bulkcontrole uitvoeren'), '#button_type' => 'primary'];
    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $ids = array_values(array_filter(array_map('intval', (array) $form_state->getValue('invoices', []))));
    $execute = $form_state->getValue('mode') === 'execute';
    $ready = $blocked = $executed = 0;
    foreach ($ids as $invoiceId) {
      $state = $this->dunningManager->state($invoiceId);
      $step = $this->dunningManager->nextStep($invoiceId);
      if ($step === NULL || $state['blocked_reason'] !== NULL) { $blocked++; continue; }
      $ready++;
      if (!$execute) continue;
      // Mail steps intentionally remain preview-only until canonical recipient resolution is wired.
      if ($step !== 'collection_ready') continue;
      $this->dunningManager->recordStep($invoiceId, 'collection_ready', ['source' => 'bulk_workbench'], (int) $this->currentUser()->id());
      $executed++;
    }
    $this->messenger()->addStatus($this->t('Bulkcontrole: @ready gereed, @blocked inmiddels geblokkeerd, @executed uitgevoerd.', ['@ready' => $ready, '@blocked' => $blocked, '@executed' => $executed]));
  }

  private function stepLabel(string $step): string {
    return match ($step) {
      'reminder' => 'Herinnering gereed', 'demand' => 'Aanmaning gereed', 'final_notice' => 'Laatste sommatie gereed', 'collection_ready' => 'Incasso gereed', default => $step,
    };
  }
}
