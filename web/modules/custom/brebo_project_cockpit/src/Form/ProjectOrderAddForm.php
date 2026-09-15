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

/** Creates a controlled outgoing project order against the working budget. */
final class ProjectOrderAddForm extends FormBase {

  public function __construct(
    private readonly CommitmentManager $commitmentManager,
    private readonly Connection $database,
    private readonly IntegrationApiClientInterface $integrationApiClient,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('brebo_finance.commitment_manager'),
      $container->get('database'),
      $container->get('brebo_office_core.integration_api_client'),
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
    $aiMode = $form_state->get('ai_mode');
    if ($aiMode === NULL) {
      $aiMode = (string) \Drupal::request()->query->get('mode', '') === 'ai';
      $form_state->set('ai_mode', $aiMode);
    }

    if ($aiMode) {
      return $this->buildAiForm($form, $form_state, $projectId);
    }
    return $this->buildManualForm($form, $form_state, $projectId);
  }

  private function buildManualForm(array $form, FormStateInterface $form_state, int $projectId): array {
    $budgetLines = $this->workingBudgetLines($projectId);
    $options = $this->budgetOptions($budgetLines);
    $form['intro'] = ['#markup' => '<p><strong>Uitgaande order.</strong> Deze order wordt als concept aangemaakt en tegen de vastgestelde werkbegroting geboekt. Een order is géén voorwaarde voor de losse-factuurroute in Finance.</p>'];
    $form['supplier_name'] = ['#type' => 'textfield', '#title' => $this->t('Leverancier / opdrachtnemer'), '#required' => TRUE, '#maxlength' => 255];
    $form['supplier_ref'] = ['#type' => 'textfield', '#title' => $this->t('Leveranciersreferentie'), '#maxlength' => 255];
    $form['budget_line_id'] = ['#type' => 'select', '#title' => $this->t('Werkbegrotingsregel'), '#options' => $options, '#empty_option' => $options === [] ? $this->t('Geen vergrendelde werkbegroting beschikbaar') : $this->t('- Kies regel -'), '#required' => TRUE, '#disabled' => $options === []];
    $form['description'] = ['#type' => 'textfield', '#title' => $this->t('Omschrijving'), '#required' => TRUE, '#maxlength' => 255];
    $form['quantity'] = ['#type' => 'number', '#title' => $this->t('Aantal'), '#required' => TRUE, '#default_value' => 1, '#step' => '0.0001', '#min' => '0.0001'];
    $form['unit'] = ['#type' => 'textfield', '#title' => $this->t('Eenheid'), '#default_value' => 'post', '#maxlength' => 32];
    $form['unit_price_ex_vat'] = ['#type' => 'number', '#title' => $this->t('Prijs per eenheid excl. btw'), '#required' => TRUE, '#step' => '0.0001', '#min' => '0.0001'];
    $form['vat_rate'] = ['#type' => 'select', '#title' => $this->t('Btw'), '#options' => ['21' => '21%', '9' => '9%', '0' => '0%'], '#default_value' => '21', '#required' => TRUE];
    $form['vat_reverse_charge'] = ['#type' => 'checkbox', '#title' => $this->t('Btw verlegd')];
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = ['#type' => 'submit', '#name' => 'manual_create', '#value' => $this->t('Conceptorder aanmaken'), '#button_type' => 'primary', '#disabled' => $options === []];
    return $form;
  }

  private function buildAiForm(array $form, FormStateInterface $form_state, int $projectId): array {
    $budgetLines = $this->workingBudgetLines($projectId);
    $proposal = $form_state->get('ai_order_proposal');
    $form['intro'] = ['#markup' => '<p><strong>AI order voorbereiden.</strong> Lever broninformatie aan. AI maakt uitsluitend een conceptvoorstel binnen de beschikbare werkbegroting. Er wordt niets verzonden en zonder jouw expliciete controle ontstaat geen order.</p>'];
    $form['supplier_hint'] = ['#type' => 'textfield', '#title' => $this->t('Leverancier / opdrachtnemer (optioneel)'), '#maxlength' => 255, '#default_value' => (string) ($form_state->getValue('supplier_hint') ?? '')];
    $form['source_text'] = ['#type' => 'textarea', '#title' => $this->t('Broninformatie'), '#description' => $this->t('Plak hier bijvoorbeeld de leveranciersofferte, e-mail, prijsafspraak of gecontroleerde scope.'), '#rows' => 14, '#required' => TRUE, '#default_value' => (string) ($form_state->getValue('source_text') ?? '')];

    if (!is_array($proposal)) {
      $form['budget_info'] = ['#markup' => '<p>' . $this->t('@count werkbegrotingsregel(s) worden als harde budgetgrens aan AI meegegeven.', ['@count' => count($budgetLines)]) . '</p>'];
      $form['actions'] = ['#type' => 'actions'];
      $form['actions']['ai_analyze'] = ['#type' => 'submit', '#name' => 'ai_analyze', '#value' => $this->t('AI concept laten maken'), '#button_type' => 'primary', '#disabled' => $budgetLines === []];
      return $form;
    }

    $options = $this->budgetOptions($budgetLines);
    $form['proposal'] = ['#type' => 'details', '#title' => $this->t('AI-concept — menselijke controle verplicht'), '#open' => TRUE];
    $form['proposal']['supplier_name'] = ['#type' => 'textfield', '#title' => $this->t('Leverancier / opdrachtnemer'), '#default_value' => (string) ($proposal['supplier_name'] ?? ''), '#required' => TRUE, '#maxlength' => 255];
    $form['proposal']['supplier_ref'] = ['#type' => 'textfield', '#title' => $this->t('Leveranciersreferentie'), '#default_value' => (string) ($proposal['supplier_ref'] ?? ''), '#maxlength' => 255];
    $form['proposal']['lines'] = ['#type' => 'container'];
    foreach ((array) ($proposal['lines'] ?? []) as $index => $line) {
      $key = 'line_' . $index;
      $form['proposal']['lines'][$key] = ['#type' => 'details', '#title' => $this->t('Orderregel @nr', ['@nr' => $index + 1]), '#open' => TRUE];
      $form['proposal']['lines'][$key]['budget_line_id'] = ['#type' => 'select', '#title' => $this->t('Werkbegrotingsregel'), '#options' => $options, '#default_value' => (int) ($line['budget_line_id'] ?? 0), '#required' => TRUE];
      $form['proposal']['lines'][$key]['description'] = ['#type' => 'textfield', '#title' => $this->t('Omschrijving'), '#default_value' => (string) ($line['description'] ?? ''), '#required' => TRUE, '#maxlength' => 500];
      $form['proposal']['lines'][$key]['quantity'] = ['#type' => 'number', '#title' => $this->t('Aantal'), '#default_value' => (string) ($line['quantity'] ?? '1'), '#required' => TRUE, '#step' => '0.0001', '#min' => '0.0001'];
      $form['proposal']['lines'][$key]['unit'] = ['#type' => 'textfield', '#title' => $this->t('Eenheid'), '#default_value' => (string) ($line['unit'] ?? 'post'), '#required' => TRUE, '#maxlength' => 32];
      $form['proposal']['lines'][$key]['unit_price_ex_vat'] = ['#type' => 'number', '#title' => $this->t('Prijs per eenheid excl. btw'), '#default_value' => (string) ($line['unit_price_ex_vat'] ?? ''), '#required' => TRUE, '#step' => '0.0001', '#min' => '0.0001'];
      $form['proposal']['lines'][$key]['vat_rate'] = ['#type' => 'select', '#title' => $this->t('Btw'), '#options' => ['21' => '21%', '9' => '9%', '0' => '0%'], '#default_value' => (string) ($line['vat_rate'] ?? '21')];
      $form['proposal']['lines'][$key]['vat_reverse_charge'] = ['#type' => 'checkbox', '#title' => $this->t('Btw verlegd'), '#default_value' => !empty($line['vat_reverse_charge'])];
    }
    $form['proposal']['rationale'] = ['#type' => 'item', '#title' => $this->t('Onderbouwing'), '#markup' => nl2br(htmlspecialchars((string) ($proposal['rationale'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'))];
    $form['proposal']['confidence'] = ['#type' => 'item', '#title' => $this->t('AI-betrouwbaarheid'), '#markup' => $this->t('@confidence%', ['@confidence' => number_format((float) ($proposal['confidence'] ?? 0), 1, ',', '.')])];
    $riskItems = [];
    foreach ((array) ($proposal['risks'] ?? []) as $risk) {
      $riskItems[] = ['#markup' => htmlspecialchars((string) $risk, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')];
    }
    $form['proposal']['risks'] = ['#theme' => 'item_list', '#title' => $this->t('Risico’s / onzekerheden'), '#items' => $riskItems ?: [['#markup' => $this->t('Geen expliciete risico’s gemeld; menselijke controle blijft verplicht.')]]];
    $form['review_confirmation'] = ['#type' => 'checkbox', '#title' => $this->t('Ik heb leverancier, scope, hoeveelheden, prijzen, btw en budgetkoppeling gecontroleerd.'), '#required' => TRUE];
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['ai_create'] = ['#type' => 'submit', '#name' => 'ai_create', '#value' => $this->t('Gecontroleerd conceptorder aanmaken'), '#button_type' => 'primary'];
    $form['actions']['ai_restart'] = ['#type' => 'submit', '#name' => 'ai_restart', '#value' => $this->t('Nieuw AI-voorstel'), '#limit_validation_errors' => []];
    $form['#tree'] = TRUE;
    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $trigger = (string) ($form_state->getTriggeringElement()['#name'] ?? '');
    if ($trigger === 'ai_analyze' && trim((string) $form_state->getValue('source_text')) === '') {
      $form_state->setErrorByName('source_text', $this->t('Broninformatie is verplicht.'));
    }
    if ($trigger === 'ai_create') {
      $projectId = (int) $form_state->get('project_id');
      $allowed = [];
      foreach ($this->workingBudgetLines($projectId) as $line) {
        $allowed[(int) $line['id']] = (float) $line['remaining_ex_vat'];
      }
      foreach ((array) (((array) $form_state->getValue('proposal'))['lines'] ?? []) as $key => $line) {
        $budgetLineId = (int) ($line['budget_line_id'] ?? 0);
        $amount = (float) ($line['quantity'] ?? 0) * (float) ($line['unit_price_ex_vat'] ?? 0);
        if (!isset($allowed[$budgetLineId])) {
          $form_state->setErrorByName("proposal][lines][$key][budget_line_id", $this->t('Ongeldige werkbegrotingsregel.'));
        }
        elseif ($amount > $allowed[$budgetLineId] + 0.005) {
          $form_state->setErrorByName("proposal][lines][$key][unit_price_ex_vat", $this->t('Deze orderregel overschrijdt het resterende werkbegrotingsbedrag.'));
        }
      }
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $projectId = (int) $form_state->get('project_id');
    if ($projectId <= 0) {
      throw new \RuntimeException('Projectcontext ontbreekt.');
    }
    $trigger = (string) ($form_state->getTriggeringElement()['#name'] ?? 'manual_create');

    if ($trigger === 'ai_restart') {
      $form_state->set('ai_order_proposal', NULL);
      $form_state->setRebuild(TRUE);
      return;
    }
    if ($trigger === 'ai_analyze') {
      $result = $this->integrationApiClient->draftOrder([
        'project_id' => $projectId,
        'source_text' => (string) $form_state->getValue('source_text'),
        'supplier_hint' => (string) $form_state->getValue('supplier_hint'),
        'budget_lines' => $this->workingBudgetLines($projectId),
      ]);
      if (($result['state'] ?? NULL) !== 'completed' || !is_array($result['draft'] ?? NULL)) {
        $this->messenger()->addWarning($this->t('AI-orderconcept kon niet worden gemaakt. Status: @state.', ['@state' => (string) ($result['state'] ?? 'onbekend')]));
        return;
      }
      $form_state->set('ai_order_proposal', $result['draft']);
      $form_state->setRebuild(TRUE);
      return;
    }

    $userId = (int) $this->currentUser()->id();
    if ($trigger === 'ai_create') {
      $proposal = (array) $form_state->getValue('proposal');
      $commitmentId = $this->commitmentManager->createDraft($projectId, (string) ($proposal['supplier_name'] ?? ''), trim((string) ($proposal['supplier_ref'] ?? '')) ?: NULL, $userId);
      foreach ((array) ($proposal['lines'] ?? []) as $line) {
        $this->commitmentManager->addLine($commitmentId, (int) ($line['budget_line_id'] ?? 0), (string) ($line['description'] ?? ''), (string) ($line['quantity'] ?? ''), (string) ($line['unit'] ?? ''), (string) ($line['unit_price_ex_vat'] ?? ''), (string) ($line['vat_rate'] ?? '21'), !empty($line['vat_reverse_charge']), '0', $userId);
      }
      $this->messenger()->addStatus($this->t('Gecontroleerd AI-conceptorder @id is aangemaakt. De order is nog niet verzonden.', ['@id' => $commitmentId]));
      $form_state->setRedirect('brebo_project_cockpit.orders', ['node' => $projectId]);
      return;
    }

    $commitmentId = $this->commitmentManager->createDraft($projectId, (string) $form_state->getValue('supplier_name'), trim((string) $form_state->getValue('supplier_ref')) ?: NULL, $userId);
    $this->commitmentManager->addLine($commitmentId, (int) $form_state->getValue('budget_line_id'), (string) $form_state->getValue('description'), (string) $form_state->getValue('quantity'), (string) $form_state->getValue('unit'), (string) $form_state->getValue('unit_price_ex_vat'), (string) $form_state->getValue('vat_rate'), (bool) $form_state->getValue('vat_reverse_charge'), '0', $userId);
    $this->messenger()->addStatus($this->t('Conceptorder @id is aangemaakt.', ['@id' => $commitmentId]));
    $form_state->setRedirect('brebo_project_cockpit.orders', ['node' => $projectId]);
  }

  /** @return array<int, array<string, mixed>> */
  private function workingBudgetLines(int $projectId): array {
    $budget = $this->database->select('brebo_finance_budget', 'b')->fields('b', ['id'])->condition('project_nid', $projectId)->condition('budget_type', 'working')->condition('status', 'locked')->orderBy('id', 'DESC')->range(0, 1)->execute()->fetchField();
    if ($budget === FALSE) {
      return [];
    }
    $rows = $this->database->select('brebo_finance_budget_line', 'l')->fields('l')->condition('budget_id', (int) $budget)->orderBy('sort_order', 'ASC')->orderBy('id', 'ASC')->execute()->fetchAll(\PDO::FETCH_ASSOC);
    foreach ($rows as &$row) {
      $row['code'] = (string) ($row['cost_code'] ?? $row['line_number'] ?? $row['id']);
      $row['remaining_ex_vat'] = max(0.0, (float) ($row['amount_ex_vat'] ?? 0) - $this->committedForBudgetLine((int) $row['id']));
    }
    unset($row);
    return $rows;
  }

  /** @param array<int, array<string, mixed>> $budgetLines */
  private function budgetOptions(array $budgetLines): array {
    $options = [];
    foreach ($budgetLines as $line) {
      $options[(int) $line['id']] = sprintf('%s · %s · resterend € %s', (string) ($line['code'] ?? $line['id']), (string) ($line['description'] ?? 'Begrotingsregel'), number_format((float) ($line['remaining_ex_vat'] ?? 0), 2, ',', '.'));
    }
    return $options;
  }

  private function committedForBudgetLine(int $budgetLineId): float {
    if (!$this->database->schema()->tableExists('brebo_finance_commitment_line') || !$this->database->schema()->tableExists('brebo_finance_commitment')) {
      return 0.0;
    }
    $query = $this->database->select('brebo_finance_commitment_line', 'l');
    $query->join('brebo_finance_commitment', 'c', 'c.id = l.commitment_id');
    $query->condition('l.budget_line_id', $budgetLineId)->condition('c.status', ['cancelled'], 'NOT IN')->addExpression('COALESCE(SUM(l.amount_ex_vat), 0)', 'committed_total');
    return (float) $query->execute()->fetchField();
  }

}
