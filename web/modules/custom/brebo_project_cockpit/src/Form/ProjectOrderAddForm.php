<?php

declare(strict_types=1);

namespace Drupal\brebo_project_cockpit\Form;

use Drupal\brebo_finance\Service\CommitmentManager;
use Drupal\Core\Database\Connection;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/** Creates a controlled outgoing project order against the working budget. */
final class ProjectOrderAddForm extends FormBase {

  public function __construct(
    private readonly CommitmentManager $commitmentManager,
    private readonly Connection $database,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('brebo_finance.commitment_manager'),
      $container->get('database'),
    );
  }

  public function getFormId(): string {
    return 'brebo_project_cockpit_order_add';
  }

  public function buildForm(array $form, FormStateInterface $form_state, ?NodeInterface $node = NULL): array {
    if (!$node instanceof NodeInterface || $node->bundle() !== 'brebo_project') {
      throw new \InvalidArgumentException('Een BREBO-project is verplicht.');
    }
    $projectId = (int) $node->id();
    $form_state->set('project_id', $projectId);

    $budgetLines = $this->workingBudgetLines($projectId);
    $options = [];
    foreach ($budgetLines as $line) {
      $options[(int) $line['id']] = sprintf(
        '%s · %s · € %s',
        (string) ($line['cost_code'] ?? $line['line_number'] ?? $line['id']),
        (string) ($line['description'] ?? 'Begrotingsregel'),
        number_format((float) ($line['amount_ex_vat'] ?? 0), 2, ',', '.'),
      );
    }

    $form['intro'] = [
      '#markup' => '<p><strong>Uitgaande order.</strong> Deze order wordt als concept aangemaakt en tegen de vastgestelde werkbegroting geboekt. Een order is géén voorwaarde voor de losse-factuurroute in Finance.</p>',
    ];
    $form['supplier_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Leverancier / opdrachtnemer'),
      '#required' => TRUE,
      '#maxlength' => 255,
    ];
    $form['supplier_ref'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Leveranciersreferentie'),
      '#required' => FALSE,
      '#maxlength' => 255,
    ];
    $form['budget_line_id'] = [
      '#type' => 'select',
      '#title' => $this->t('Werkbegrotingsregel'),
      '#options' => $options,
      '#empty_option' => $options === [] ? $this->t('Geen vergrendelde werkbegroting beschikbaar') : $this->t('- Kies regel -'),
      '#required' => TRUE,
      '#disabled' => $options === [],
    ];
    $form['description'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Omschrijving'),
      '#required' => TRUE,
      '#maxlength' => 255,
    ];
    $form['quantity'] = [
      '#type' => 'number',
      '#title' => $this->t('Aantal'),
      '#required' => TRUE,
      '#default_value' => 1,
      '#step' => '0.0001',
      '#min' => '0.0001',
    ];
    $form['unit'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Eenheid'),
      '#default_value' => 'post',
      '#maxlength' => 32,
    ];
    $form['unit_price_ex_vat'] = [
      '#type' => 'number',
      '#title' => $this->t('Prijs per eenheid excl. btw'),
      '#required' => TRUE,
      '#step' => '0.0001',
      '#min' => '0.0001',
    ];
    $form['vat_rate'] = [
      '#type' => 'select',
      '#title' => $this->t('Btw'),
      '#options' => ['21' => '21%', '9' => '9%', '0' => '0%'],
      '#default_value' => '21',
      '#required' => TRUE,
    ];
    $form['vat_reverse_charge'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Btw verlegd'),
    ];
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Conceptorder aanmaken'),
      '#button_type' => 'primary',
      '#disabled' => $options === [],
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $projectId = (int) $form_state->get('project_id');
    if ($projectId <= 0) {
      throw new \RuntimeException('Projectcontext ontbreekt.');
    }

    $userId = (int) $this->currentUser()->id();
    $commitmentId = $this->commitmentManager->createDraft(
      $projectId,
      (string) $form_state->getValue('supplier_name'),
      trim((string) $form_state->getValue('supplier_ref')) ?: NULL,
      $userId,
    );

    $this->commitmentManager->addLine(
      $commitmentId,
      (int) $form_state->getValue('budget_line_id'),
      (string) $form_state->getValue('description'),
      (string) $form_state->getValue('quantity'),
      (string) $form_state->getValue('unit'),
      (string) $form_state->getValue('unit_price_ex_vat'),
      (string) $form_state->getValue('vat_rate'),
      (bool) $form_state->getValue('vat_reverse_charge'),
      '0',
      $userId,
    );

    $this->messenger()->addStatus($this->t('Conceptorder @id is aangemaakt.', ['@id' => $commitmentId]));
    $form_state->setRedirect('brebo_project_cockpit.orders', ['node' => $projectId]);
  }

  /** @return array<int, array<string, mixed>> */
  private function workingBudgetLines(int $projectId): array {
    $budget = $this->database->select('brebo_finance_budget', 'b')
      ->fields('b', ['id'])
      ->condition('project_nid', $projectId)
      ->condition('budget_type', 'working')
      ->condition('status', 'locked')
      ->orderBy('id', 'DESC')
      ->range(0, 1)
      ->execute()
      ->fetchField();
    if ($budget === FALSE) {
      return [];
    }

    return $this->database->select('brebo_finance_budget_line', 'l')
      ->fields('l')
      ->condition('budget_id', (int) $budget)
      ->orderBy('sort_order', 'ASC')
      ->orderBy('id', 'ASC')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);
  }

}
