<?php

declare(strict_types=1);

namespace Drupal\brebo_project_cockpit\Form;

use Drupal\brebo_finance\Service\CommitmentManager;
use Drupal\brebo_office_core\Service\IntegrationApiClientInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/** Builds an AI-assisted, human-reviewed outgoing order concept. */
final class ProjectAiOrderDraftForm extends FormBase {

  private ?NodeInterface $project = NULL;

  public function __construct(
    private readonly IntegrationApiClientInterface $integrationApiClient,
    private readonly CommitmentManager $commitmentManager,
    private readonly Connection $database,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('brebo_office_core.integration_api_client'),
      $container->get('brebo_finance.commitment_manager'),
      $container->get('database'),
    );
  }

  public function getFormId(): string {
    return 'brebo_project_cockpit_ai_order_draft';
  }

  public function buildForm(array $form, FormStateInterface $form_state, ?NodeInterface $node = NULL): array {
    if (!$node instanceof NodeInterface || $node->bundle() !== 'brebo_project') {
      throw new \InvalidArgumentException('Een BREBO-project is verplicht.');
    }
    $this->project = $node;
    $projectId = (int) $node->id();
    $budgetLines = $this->workingBudgetLines($projectId);
    $proposal = $form_state->get('ai_order_proposal');

    $form['intro'] = [
      '#markup' => '<p><strong>AI order voorbereiden.</strong> Lever broninformatie aan, bijvoorbeeld een leveranciersofferte, e-mail, calculatieregel of afgesproken scope. AI maakt uitsluitend een concept. Er wordt niets verzonden en zonder jouw expliciete bevestiging ontstaat geen order.</p>',
    ];
    $form['supplier_hint'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Leverancier / opdrachtnemer (optioneel)'),
      '#default_value' => (string) ($form_state->getValue('supplier_hint') ?? ''),
      '#maxlength' => 255,
    ];
    $form['source_text'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Broninformatie'),
      '#description' => $this->t('Plak hier de offerte, e-mail, prijsafspraak of andere gecontroleerde broninformatie.'),
      '#default_value' => (string) ($form_state->getValue('source_text') ?? ''),
      '#rows' => 14,
      '#required' => TRUE,
    ];

    if ($proposal === NULL) {
      $form['budget_info'] = [
        '#markup' => '<p>' . $this->t('@count beschikbare werkbegrotingsregel(s) worden als harde grens aan AI meegegeven.', ['@count' => count($budgetLines)]) . '</p>',
      ];
      $form['actions'] = ['#type' => 'actions'];
      $form['actions']['analyze'] = [
        '#type' => 'submit',
        '#name' => 'analyze',
        '#value' => $this->t('AI concept laten maken'),
        '#button_type' => 'primary',
        '#disabled' => $budgetLines === [],
      ];
      return $form;
    }

    $form['proposal'] = [
      '#type' => 'details',
      '#title' => $this->t('AI-concept — menselijke controle verplicht'),
      '#open' => TRUE,
    ];
    $form['proposal']['supplier'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Leverancier / opdrachtnemer'),
      '#default_value' => (string) ($proposal['supplier_name'] ?? ''),
      '#required' => TRUE,
    ];
    $form['proposal']['supplier_ref'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Leveranciersreferentie'),
      '#default_value' => (string) ($proposal['supplier_ref'] ?? ''),
    ];

    $budgetOptions = [];
    foreach ($budgetLines as $line) {
      $budgetOptions[(int) $line['id']] = sprintf(
        '%s · %s · resterend € %s',
        (string) ($line['code'] ?? $line['id']),
        (string) ($line['description'] ?? ''),
        number_format((float) ($line['remaining_ex_vat'] ?? 0), 2, ',', '.'),
      );
    }

    $form['proposal']['lines'] = ['#type' => 'container'];
    foreach ((array) ($proposal['lines'] ?? []) as $index => $line) {
      $key = 'line_' . $index;
      $form['proposal']['lines'][$key] = [
        '#type' => 'details',
        '#title' => $this->t('Orderregel @nr', ['@nr' => $index + 1]),
        '#open' => TRUE,
      ];
      $form['proposal']['lines'][$key]['budget_line_id'] = [
        '#type' => 'select',
        '#title' => $this->t('Werkbegrotingsregel'),
        '#options' => $budgetOptions,
        '#default_value' => (int) ($line['budget_line_id'] ?? 0),
        '#required' => TRUE,
      ];
      foreach ([
        'description' => ['Omschrijving', (string) ($line['description'] ?? '')],
        'quantity' => ['Aantal', (string) ($line['quantity'] ?? '1')],
        'unit' => ['Eenheid', (string) ($line['unit'] ?? 'post')],
        'unit_price_ex_vat' => ['Prijs per eenheid excl. btw', (string) ($line['unit_price_ex_vat'] ?? '')],
      ] as $field => [$label, $default]) {
        $form['proposal']['lines'][$key][$field] = [
          '#type' => in_array($field, ['quantity', 'unit_price_ex_vat'], TRUE) ? 'number' : 'textfield',
          '#title' => $this->t($label),
          '#default_value' => $default,
          '#required' => TRUE,
          '#step' => in_array($field, ['quantity', 'unit_price_ex_vat'], TRUE) ? '0.0001' : NULL,
        ];
      }
      $form['proposal']['lines'][$key]['vat_rate'] = [
        '#type' => 'select',
        '#title' => $this->t('Btw'),
        '#options' => ['21' => '21%', '9' => '9%', '0' => '0%'],
        '#default_value' => (string) ($line['vat_rate'] ?? '21'),
      ];
      $form['proposal']['lines'][$key]['vat_reverse_charge'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Btw verlegd'),
        '#default_value' => !empty($line['vat_reverse_charge']),
      ];
    }

    $riskItems = [];
    foreach ((array) ($proposal['risks'] ?? []) as $risk) {
      $riskItems[] = ['#markup' => $this->t('@risk', ['@risk' => (string) $risk])];
    }
    $form['proposal']['rationale'] = [
      '#type' => 'item',
      '#title' => $this->t('Onderbouwing'),
      '#markup' => nl2br(htmlspecialchars((string) ($proposal['rationale'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')),
    ];
    $form['proposal']['confidence'] = [
      '#type' => 'item',
      '#title' => $this->t('AI-betrouwbaarheid'),
      '#markup' => $this->t('@confidence%', ['@confidence' => number_format((float) ($proposal['confidence'] ?? 0), 1, ',', '.')]),
    ];
    $form['proposal']['risks'] = [
      '#theme' => 'item_list',
      '#title' => $this->t('Risico’s / onzekerheden'),
      '#items' => $riskItems ?: [['#markup' => $this->t('Geen expliciete risico’s door AI gemeld; menselijke controle blijft verplicht.')]],
    ];
    $form['review_confirmation'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Ik heb leverancier, scope, hoeveelheden, prijzen, btw en budgetkoppeling gecontroleerd.'),
      '#required' => TRUE,
    ];
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['create'] = [
      '#type' => 'submit',
      '#name' => 'create',
      '#value' => $this->t('Gecontroleerd conceptorder aanmaken'),
      '#button_type' => 'primary',
    ];
    $form['actions']['restart'] = [
      '#type' => 'submit',
      '#name' => 'restart',
      '#value' => $this->t('Nieuw AI-voorstel'),
      '#limit_validation_errors' => [],
    ];
    $form['#tree'] = TRUE;
    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $trigger = (string) ($form_state->getTriggeringElement()['#name'] ?? '');
    if ($trigger === 'analyze' && trim((string) $form_state->getValue('source_text')) === '') {
      $form_state->setErrorByName('source_text', $this->t('Broninformatie is verplicht.'));
    }
    if ($trigger === 'create') {
      $proposalValues = (array) $form_state->getValue('proposal');
      foreach ((array) ($proposalValues['lines'] ?? []) as $key => $line) {
        $budgetLineId = (int) ($line['budget_line_id'] ?? 0);
        $remaining = $this->remainingForBudgetLine($budgetLineId);
        $amount = (float) ($line['quantity'] ?? 0) * (float) ($line['unit_price_ex_vat'] ?? 0);
        if ($amount <= 0) {
          $form_state->setErrorByName("proposal][lines][$key][unit_price_ex_vat", $this->t('De orderregel moet een positief bedrag hebben.'));
        }
        if ($remaining !== NULL && $amount > $remaining + 0.005) {
          $form_state->setErrorByName("proposal][lines][$key][unit_price_ex_vat", $this->t('Deze regel overschrijdt het resterende werkbegrotingsbedrag.'));
        }
      }
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    if (!$this->project instanceof NodeInterface) {
      throw new \RuntimeException('Projectcontext ontbreekt.');
    }
    $trigger = (string) ($form_state->getTriggeringElement()['#name'] ?? '');
    if ($trigger === 'restart') {
      $form_state->set('ai_order_proposal', NULL);
      $form_state->setRebuild(TRUE);
      return;
    }
    if ($trigger === 'analyze') {
      $result = $this->integrationApiClient->draftOrder([
        'project_id' => (int) $this->project->id(),
        'source_text' => (string) $form_state->getValue('source_text'),
        'supplier_hint' => (string) $form_state->getValue('supplier_hint'),
        'budget_lines' => $this->workingBudgetLines((int) $this->project->id()),
      ]);
      if (($result['state'] ?? NULL) !== 'completed' || !is_array($result['draft'] ?? NULL)) {
        $this->messenger()->addWarning($this->t('AI-orderconcept kon niet worden gemaakt. Status: @state.', ['@state' => (string) ($result['state'] ?? 'onbekend')]));
        return;
      }
      $form_state->set('ai_order_proposal', $result['draft']);
      $form_state->setRebuild(TRUE);
      return;
    }
    if ($trigger !== 'create') {
      return;
    }

    $values = (array) $form_state->getValue('proposal');
    $userId = (int) $this->currentUser()->id();
    $orderId = $this->commitmentManager->createDraft(
      (int) $this->project->id(),
      (string) ($values['supplier'] ?? ''),
      trim((string) ($values['supplier_ref'] ?? '')) ?: NULL,
      $userId,
    );
    foreach ((array) ($values['lines'] ?? []) as $line) {
      $this->commitmentManager->addLine(
        $orderId,
        (int) ($line['budget_line_id'] ?? 0),
        (string) ($line['description'] ?? ''),
        (string) ($line['quantity'] ?? ''),
        (string) ($line['unit'] ?? ''),
        (string) ($line['unit_price_ex_vat'] ?? ''),
        (string) ($line['vat_rate'] ?? '21'),
        !empty($line['vat_reverse_charge']),
        '0',
        $userId,
      );
    }
    $this->messenger()->addStatus($this->t('Gecontroleerd AI-conceptorder @id is aangemaakt. De order is nog niet verzonden.', ['@id' => $orderId]));
    $form_state->setRedirect('brebo_project_cockpit.orders', ['node' => (int) $this->project->id()]);
  }

  /** @return array<int, array<string, mixed>> */
  private function workingBudgetLines(int $projectId): array {
    if (!$this->database->schema()->tableExists('brebo_finance_budget') || !$this->database->schema()->tableExists('brebo_finance_budget_line')) {
      return [];
    }
    $budgetId = $this->database->select('brebo_finance_budget', 'b')
      ->fields('b', ['id'])
      ->condition('project_nid', $projectId)
      ->condition('budget_type', 'working')
      ->condition('status', 'locked')
      ->orderBy('id', 'DESC')
      ->range(0, 1)
      ->execute()
      ->fetchField();
    if ($budgetId === FALSE) {
      return [];
    }
    $rows = $this->database->select('brebo_finance_budget_line', 'l')
      ->fields('l')
      ->condition('budget_id', (int) $budgetId)
      ->orderBy('sort_order', 'ASC')
      ->orderBy('id', 'ASC')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);
    foreach ($rows as &$row) {
      $row['code'] = (string) ($row['cost_code'] ?? $row['line_number'] ?? $row['id']);
      $row['remaining_ex_vat'] = max(0.0, (float) ($row['amount_ex_vat'] ?? 0) - $this->committedForBudgetLine((int) $row['id']));
    }
    unset($row);
    return $rows;
  }

  private function committedForBudgetLine(int $budgetLineId): float {
    if (!$this->database->schema()->tableExists('brebo_finance_commitment_line') || !$this->database->schema()->tableExists('brebo_finance_commitment')) {
      return 0.0;
    }
    $query = $this->database->select('brebo_finance_commitment_line', 'l');
    $query->join('brebo_finance_commitment', 'c', 'c.id = l.commitment_id');
    $query->condition('l.budget_line_id', $budgetLineId);
    $query->condition('c.status', ['cancelled'], 'NOT IN');
    $query->addExpression('COALESCE(SUM(l.amount_ex_vat), 0)', 'committed_total');
    return (float) $query->execute()->fetchField();
  }

  private function remainingForBudgetLine(int $budgetLineId): ?float {
    foreach ($this->workingBudgetLines((int) ($this->project?->id() ?? 0)) as $line) {
      if ((int) $line['id'] === $budgetLineId) {
        return (float) $line['remaining_ex_vat'];
      }
    }
    return NULL;
  }

}
