<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Form;

use Drupal\brebo_finance\Service\ReceivablesDunningManager;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/** Executes controlled receivables actions for one sales invoice. */
final class ReceivablesActionForm extends FormBase {

  public function __construct(
    private readonly ReceivablesDunningManager $dunningManager,
    private readonly MailManagerInterface $mailManager,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('brebo_finance.receivables_dunning_manager'),
      $container->get('plugin.manager.mail'),
    );
  }

  public function getFormId(): string {
    return 'brebo_finance_receivables_action_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, ?int $invoice = NULL): array {
    if ($invoice === NULL || $invoice <= 0) {
      throw new \InvalidArgumentException('Verkoopfactuur is verplicht.');
    }
    $state = $this->dunningManager->state($invoice);
    $nextStep = $this->dunningManager->nextStep($invoice);
    $form_state->set('invoice_id', $invoice);
    $form_state->set('next_step', $nextStep);

    $form['summary'] = [
      '#markup' => '<p><strong>Factuur:</strong> ' . htmlspecialchars((string) $state['invoice_number'])
        . '<br><strong>Vervaldatum:</strong> ' . htmlspecialchars((string) $state['due_date'])
        . '<br><strong>Openstaand:</strong> € ' . number_format((float) $state['outstanding_amount_inc_vat'], 2, ',', '.')
        . '<br><strong>Debiteurenstatus:</strong> ' . htmlspecialchars((string) $state['status']) . '</p>',
    ];

    $options = [];
    if ($nextStep !== NULL) {
      $options['execute_next'] = match ($nextStep) {
        'reminder' => $this->t('Herinnering verzenden'),
        'demand' => $this->t('Aanmaning verzenden'),
        'final_notice' => $this->t('Laatste sommatie verzenden'),
        'collection_ready' => $this->t('Dossier gereed voor incasso markeren'),
        default => $this->t('Volgende debiteurenstap uitvoeren'),
      };
    }
    if ($state['hold']) {
      $options['clear_hold'] = $this->t('Hold opheffen');
    }
    else {
      $options['set_hold'] = $this->t('Hold plaatsen');
    }
    if ($state['payment_arrangement'] !== NULL) {
      $options['clear_arrangement'] = $this->t('Betalingsregeling beëindigen');
    }
    else {
      $options['set_arrangement'] = $this->t('Betalingsregeling vastleggen');
    }

    $form['action'] = [
      '#type' => 'select',
      '#title' => $this->t('Actie'),
      '#options' => $options,
      '#required' => TRUE,
    ];
    $form['recipient'] = [
      '#type' => 'email',
      '#title' => $this->t('Ontvanger'),
      '#description' => $this->t('Verplicht bij herinnering, aanmaning of laatste sommatie. Wordt alleen voor deze verzending gebruikt.'),
      '#states' => [
        'visible' => [':input[name="action"]' => ['value' => 'execute_next']],
      ],
    ];
    $form['reason'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Reden / referentie'),
      '#maxlength' => 255,
    ];
    $form['next_due_date'] = [
      '#type' => 'date',
      '#title' => $this->t('Nieuwe afgesproken betaaldatum'),
    ];
    $form['note'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Notitie'),
      '#rows' => 4,
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Uitvoeren'),
      '#button_type' => 'primary',
    ];
    $form['actions']['cancel'] = [
      '#type' => 'link',
      '#title' => $this->t('Annuleren'),
      '#url' => \Drupal\Core\Url::fromRoute('brebo_finance.sales_workspace'),
    ];
    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $action = (string) $form_state->getValue('action');
    $nextStep = $form_state->get('next_step');
    if ($action === 'execute_next' && $nextStep !== 'collection_ready' && trim((string) $form_state->getValue('recipient')) === '') {
      $form_state->setErrorByName('recipient', $this->t('Vul een ontvanger in voor deze debiteurenmail.'));
    }
    if ($action === 'set_hold' && trim((string) $form_state->getValue('reason')) === '') {
      $form_state->setErrorByName('reason', $this->t('Vul de reden van de hold in.'));
    }
    if ($action === 'set_arrangement' && (string) $form_state->getValue('next_due_date') === '') {
      $form_state->setErrorByName('next_due_date', $this->t('Vul de afgesproken betaaldatum in.'));
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $invoiceId = (int) $form_state->get('invoice_id');
    $action = (string) $form_state->getValue('action');
    $actorUid = (int) $this->currentUser()->id();

    if ($action === 'set_hold') {
      $this->dunningManager->setHold($invoiceId, TRUE, (string) $form_state->getValue('reason'), $actorUid);
      $this->messenger()->addStatus($this->t('Debiteuren-hold geplaatst.'));
    }
    elseif ($action === 'clear_hold') {
      $this->dunningManager->setHold($invoiceId, FALSE, (string) $form_state->getValue('reason'), $actorUid);
      $this->messenger()->addStatus($this->t('Debiteuren-hold opgeheven.'));
    }
    elseif ($action === 'set_arrangement') {
      $this->dunningManager->setPaymentArrangement($invoiceId, [
        'reference' => (string) $form_state->getValue('reason'),
        'agreed_at' => date('Y-m-d'),
        'next_due_date' => (string) $form_state->getValue('next_due_date'),
        'note' => (string) $form_state->getValue('note'),
      ], $actorUid);
      $this->messenger()->addStatus($this->t('Betalingsregeling vastgelegd; automatische escalatie is geblokkeerd.'));
    }
    elseif ($action === 'clear_arrangement') {
      $this->dunningManager->setPaymentArrangement($invoiceId, NULL, $actorUid);
      $this->messenger()->addStatus($this->t('Betalingsregeling beëindigd.'));
    }
    elseif ($action === 'execute_next') {
      $step = $this->dunningManager->nextStep($invoiceId);
      if ($step === NULL) {
        throw new \RuntimeException('Er staat op dit moment geen debiteurenstap klaar.');
      }
      $state = $this->dunningManager->state($invoiceId);
      if ($step === 'collection_ready') {
        $this->dunningManager->recordStep($invoiceId, $step, ['note' => (string) $form_state->getValue('note')], $actorUid);
        $this->messenger()->addStatus($this->t('Dossier is gereed voor gecontroleerde incasso-overdracht.'));
      }
      else {
        $recipient = trim((string) $form_state->getValue('recipient'));
        [$subject, $body] = $this->messageFor($step, $state, (string) $form_state->getValue('note'));
        $mail = $this->mailManager->mail('brebo_mail_intake', 'outbound', $recipient, 'nl', [
          'subject' => $subject,
          'body' => $body,
          'body_html' => '',
          'attachments' => [],
        ]);
        if (empty($mail['result'])) {
          throw new \RuntimeException('Debiteurenmail kon niet worden verzonden; de stap is niet geregistreerd.');
        }
        $this->dunningManager->recordStep($invoiceId, $step, [
          'recipient' => $recipient,
          'subject' => $subject,
          'body_hash' => hash('sha256', $body),
          'note' => (string) $form_state->getValue('note'),
        ], $actorUid);
        $this->messenger()->addStatus($this->t('Debiteurenmail verzonden en auditbaar vastgelegd.'));
      }
    }

    $form_state->setRedirect('brebo_finance.sales_workspace');
  }

  /** @param array<string,mixed> $state */
  private function messageFor(string $step, array $state, string $note): array {
    $number = (string) $state['invoice_number'];
    $due = (string) $state['due_date'];
    $amount = number_format((float) $state['outstanding_amount_inc_vat'], 2, ',', '.');
    $label = match ($step) {
      'reminder' => 'Betalingsherinnering',
      'demand' => 'Aanmaning',
      'final_notice' => 'Laatste sommatie',
      default => 'Betalingsbericht',
    };
    $body = "Geachte heer/mevrouw,\n\nVolgens onze administratie staat factuur {$number} met vervaldatum {$due} nog open voor € {$amount}.\n\nWij verzoeken u het openstaande bedrag te voldoen. Indien betaling inmiddels heeft plaatsgevonden, kunt u dit bericht als niet verzonden beschouwen.";
    if (trim($note) !== '') {
      $body .= "\n\n" . trim($note);
    }
    $body .= "\n\nMet kleurrijke groet,\nBREBO Bouw en Advies BV";
    return [$label . ' BREBO - factuur ' . $number, $body];
  }
}
