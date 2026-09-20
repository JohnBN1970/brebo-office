<?php

declare(strict_types=1);

namespace Drupal\brebo_project_cockpit\Form;

use Drupal\Core\Database\Connection;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/** Edits the payment term carried by one project instalment. */
final class ProjectInstalmentPaymentTermForm extends FormBase {

  public function __construct(private readonly Connection $database) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('database'));
  }

  public function getFormId(): string {
    return 'brebo_project_cockpit_instalment_payment_term_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, ?NodeInterface $node = NULL, ?int $instalment = NULL): array {
    if ($node === NULL || $node->bundle() !== 'brebo_project' || $instalment === NULL) {
      throw new \InvalidArgumentException('BREBO project en termijn zijn verplicht.');
    }
    $row = $this->database->select('brebo_finance_billing_instalment', 'i')
      ->fields('i')
      ->condition('id', $instalment)
      ->condition('project_nid', (int) $node->id())
      ->execute()
      ->fetchAssoc();
    if ($row === FALSE) throw new \InvalidArgumentException('Termijn niet gevonden voor dit project.');
    if (in_array((string) ($row['status'] ?? ''), ['invoiced', 'paid'], TRUE)) {
      throw new \RuntimeException('De betaaltermijn van een reeds gefactureerde termijn kan niet meer worden gewijzigd.');
    }

    $evidence = json_decode((string) ($row['evidence_payload'] ?? ''), TRUE);
    $evidence = is_array($evidence) ? $evidence : [];
    $days = isset($evidence['payment_term_days']) && is_numeric($evidence['payment_term_days'])
      ? max(0, (int) $evidence['payment_term_days'])
      : $this->projectDefault((int) $node->id());
    $preset = in_array($days, [0, 5, 8, 14, 30], TRUE) ? (string) $days : 'custom';

    $form['summary'] = ['#markup' => '<p><strong>' . $this->t('Termijn:') . '</strong> ' . htmlspecialchars((string) $row['instalment_number']) . ' · ' . htmlspecialchars((string) $row['description']) . '<br><strong>' . $this->t('Geplande factuurdatum:') . '</strong> ' . htmlspecialchars((string) $row['planned_invoice_date']) . '</p>'];
    $form['payment_term'] = [
      '#type' => 'select',
      '#title' => $this->t('Betaaltermijn'),
      '#options' => [
        '0' => $this->t('Per omgaande'),
        '5' => $this->t('5 dagen'),
        '8' => $this->t('8 dagen'),
        '14' => $this->t('14 dagen'),
        '30' => $this->t('30 dagen'),
        'custom' => $this->t('Afwijkend aantal dagen'),
      ],
      '#default_value' => $preset,
      '#required' => TRUE,
    ];
    $form['custom_payment_term_days'] = [
      '#type' => 'number',
      '#title' => $this->t('Afwijkend aantal dagen'),
      '#min' => 0,
      '#max' => 365,
      '#default_value' => $preset === 'custom' ? $days : NULL,
      '#states' => ['visible' => [':input[name="payment_term"]' => ['value' => 'custom']]],
    ];
    $form['actions']['submit'] = ['#type' => 'submit', '#value' => $this->t('Betaaltermijn opslaan'), '#button_type' => 'primary'];
    $form['actions']['cancel'] = ['#type' => 'link', '#title' => $this->t('Annuleren'), '#url' => Url::fromRoute('brebo_project_cockpit.invoices', ['node' => (int) $node->id()]), '#attributes' => ['class' => ['button']]];
    $form_state->set('project_id', (int) $node->id());
    $form_state->set('instalment_id', (int) $instalment);
    $form_state->set('evidence', $evidence);
    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    if ((string) $form_state->getValue('payment_term') === 'custom' && $form_state->getValue('custom_payment_term_days') === '') {
      $form_state->setErrorByName('custom_payment_term_days', $this->t('Vul het afwijkende aantal betalingsdagen in.'));
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $choice = (string) $form_state->getValue('payment_term');
    $days = $choice === 'custom'
      ? max(0, (int) $form_state->getValue('custom_payment_term_days'))
      : max(0, (int) $choice);
    $evidence = (array) $form_state->get('evidence');
    $evidence['payment_term_days'] = $days;
    $evidence['payment_term_source'] = 'instalment_override';
    $evidence['payment_term_changed_at'] = time();

    $this->database->update('brebo_finance_billing_instalment')->fields([
      'evidence_payload' => json_encode($evidence, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
      'changed' => time(),
      'changed_by' => (int) $this->currentUser()->id(),
    ])->condition('id', (int) $form_state->get('instalment_id'))->condition('project_nid', (int) $form_state->get('project_id'))->execute();

    $this->messenger()->addStatus($days === 0
      ? $this->t('Betaaltermijn van deze termijn is ingesteld op per omgaande.')
      : $this->t('Betaaltermijn van deze termijn is ingesteld op @days dagen.', ['@days' => $days]));
    $form_state->setRedirect('brebo_project_cockpit.invoices', ['node' => (int) $form_state->get('project_id')]);
  }

  private function projectDefault(int $projectId): int {
    $value = $this->database->select('brebo_finance_project_contract', 'c')->fields('c', ['payment_term_days'])->condition('project_nid', $projectId)->execute()->fetchField();
    if (is_numeric($value)) return max(0, (int) $value);
    $global = \Drupal::config('brebo_finance.sales')->get('numbering.default_payment_term_days');
    return is_numeric($global) ? max(0, (int) $global) : 14;
  }
}
