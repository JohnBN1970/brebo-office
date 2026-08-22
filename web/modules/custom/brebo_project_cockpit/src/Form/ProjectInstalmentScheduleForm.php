<?php

declare(strict_types=1);

namespace Drupal\brebo_project_cockpit\Form;

use Drupal\brebo_finance\Service\BillingControlManager;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/** Creates a project billing schedule from a reusable template. */
final class ProjectInstalmentScheduleForm extends FormBase {

  private const TEMPLATE_CONFIG = 'brebo_project_cockpit.instalment_templates';

  public function __construct(
    private readonly Connection $database,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly BillingControlManager $billingManager,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('database'),
      $container->get('config.factory'),
      $container->get('brebo_finance.billing_control_manager'),
    );
  }

  public function getFormId(): string {
    return 'brebo_project_cockpit_instalment_schedule_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, ?NodeInterface $node = NULL): array {
    if ($node === NULL || $node->bundle() !== 'brebo_project') {
      throw new \InvalidArgumentException('BREBO project required.');
    }

    $projectId = (int) $node->id();
    $contract = $this->database->select('brebo_finance_project_contract', 'c')
      ->fields('c')
      ->condition('project_nid', $projectId)
      ->execute()
      ->fetchAssoc();

    if ($contract === FALSE || ($contract['status'] ?? '') !== 'approved') {
      $form['warning'] = ['#markup' => '<p><strong>' . $this->t('Een goedgekeurd projectcontract is vereist voordat een termijnschema kan worden aangemaakt.') . '</strong></p>'];
      $form['back'] = ['#type' => 'link', '#title' => $this->t('Terug naar Facturen'), '#url' => Url::fromRoute('brebo_project_cockpit.invoices', ['node' => $projectId]), '#attributes' => ['class' => ['button']]];
      return $form;
    }

    $existing = (int) $this->database->select('brebo_finance_billing_instalment', 'i')
      ->condition('project_nid', $projectId)
      ->countQuery()
      ->execute()
      ->fetchField();

    if ($existing > 0) {
      $form['warning'] = ['#markup' => '<p><strong>' . $this->t('Voor dit project bestaat al een termijnschema. Om dubbele verplichtingen te voorkomen kan een sjabloon alleen op een leeg termijnschema worden toegepast.') . '</strong></p>'];
      $form['back'] = ['#type' => 'link', '#title' => $this->t('Terug naar Facturen'), '#url' => Url::fromRoute('brebo_project_cockpit.invoices', ['node' => $projectId]), '#attributes' => ['class' => ['button']]];
      return $form;
    }

    $templates = $this->availableTemplates();
    $options = [];
    foreach ($templates as $id => $template) {
      $options[$id] = (string) $template['name'] . ' · ' . implode(' / ', array_map(static fn(float $v): string => rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.') . '%', $template['percentages']));
    }

    $form['project'] = ['#markup' => '<p><strong>' . $this->t('Project:') . '</strong> ' . $node->label() . '<br><strong>' . $this->t('Contractsom excl. btw:') . '</strong> € ' . number_format((float) ($contract['amount_ex_vat'] ?? 0), 2, ',', '.') . '</p>'];
    $form['template'] = ['#type' => 'select', '#title' => $this->t('Termijnsjabloon'), '#options' => $options, '#required' => TRUE];
    $form['first_date'] = ['#type' => 'date', '#title' => $this->t('Datum eerste termijn'), '#required' => TRUE, '#default_value' => date('Y-m-d')];
    $form['interval_months'] = ['#type' => 'number', '#title' => $this->t('Tussenruimte in maanden'), '#required' => TRUE, '#min' => 0, '#max' => 24, '#default_value' => 1, '#description' => $this->t('Gebruik 0 wanneer alle termijnen dezelfde geplande datum krijgen; data blijven later per termijn aanpasbaar.')];
    $form['vat_rate'] = [
      '#type' => 'select',
      '#title' => $this->t('Tijdelijk btw-regime op termijnkop'),
      '#options' => ['21' => '21%', '9' => '9%', '0' => '0%'],
      '#default_value' => '21',
      '#description' => $this->t('Dit veld bestaat vanwege de huidige termijnkop. Gemengde btw wordt in de volgende stap op regelniveau vastgelegd; gebruik dit schema daarom nog niet als fiscale bron voor projecten met gemengde btw.'),
    ];

    $form['actions']['submit'] = ['#type' => 'submit', '#value' => $this->t('Termijnschema aanmaken'), '#button_type' => 'primary'];
    $form['actions']['templates'] = ['#type' => 'link', '#title' => $this->t('Eigen sjablonen beheren'), '#url' => Url::fromRoute('brebo_project_cockpit.instalment_templates', ['node' => $projectId]), '#attributes' => ['class' => ['button']]];
    $form['actions']['cancel'] = ['#type' => 'link', '#title' => $this->t('Annuleren'), '#url' => Url::fromRoute('brebo_project_cockpit.invoices', ['node' => $projectId]), '#attributes' => ['class' => ['button']]];

    $form_state->set('project_id', $projectId);
    $form_state->set('contract', $contract);
    $form_state->set('templates', $templates);
    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $projectId = (int) $form_state->get('project_id');
    $contract = $form_state->get('contract');
    $templates = $form_state->get('templates');
    $templateId = (string) $form_state->getValue('template');
    $template = $templates[$templateId] ?? NULL;
    if (!is_array($contract) || !is_array($template)) {
      throw new \RuntimeException('Contract or instalment template unavailable.');
    }

    $percentages = array_values(array_map('floatval', $template['percentages'] ?? []));
    $labels = array_values(array_map('strval', $template['labels'] ?? []));
    $contractAmount = round((float) $contract['amount_ex_vat'], 4);
    $firstDate = new \DateTimeImmutable((string) $form_state->getValue('first_date'));
    $interval = max(0, (int) $form_state->getValue('interval_months'));
    $vatRate = (string) $form_state->getValue('vat_rate');
    $actor = (int) $this->currentUser()->id();

    $createdTotal = 0.0;
    $transaction = $this->database->startTransaction();
    try {
      foreach ($percentages as $index => $percentage) {
        $isLast = $index === array_key_last($percentages);
        $amount = $isLast ? round($contractAmount - $createdTotal, 4) : round($contractAmount * ($percentage / 100), 4);
        $createdTotal = round($createdTotal + $amount, 4);
        $date = $interval > 0 ? $firstDate->modify('+' . ($index * $interval) . ' months') : $firstDate;
        $label = $labels[$index] ?? ('Termijn ' . ($index + 1));

        $this->billingManager->registerInstalment([
          'project_nid' => $projectId,
          'contract_id' => (int) $contract['id'],
          'instalment_number' => sprintf('T%02d', $index + 1),
          'description' => $label,
          'trigger_type' => 'calendar_date',
          'trigger_ref' => $date->format('Y-m-d'),
          'amount_ex_vat' => number_format($amount, 4, '.', ''),
          'vat_rate' => $vatRate,
          'vat_code' => 'NL_' . $vatRate,
          'planned_invoice_date' => $date->format('Y-m-d'),
          'evidence' => [
            'source' => 'instalment_template',
            'template_id' => $templateId,
            'template_name' => (string) $template['name'],
            'percentage' => $percentage,
          ],
        ], $actor);
      }
    }
    catch (\Throwable $exception) {
      $transaction->rollBack();
      throw $exception;
    }

    $this->messenger()->addStatus($this->t('@count termijnen zijn aangemaakt vanuit sjabloon “@name”.', ['@count' => count($percentages), '@name' => $template['name']]));
    $form_state->setRedirect('brebo_project_cockpit.invoices', ['node' => $projectId]);
  }

  /**
   * @return array<string, array{name: string, percentages: list<float>, labels: list<string>}>
   */
  private function availableTemplates(): array {
    $templates = InstalmentTemplateForm::standardTemplates();
    $custom = $this->configFactory->get(self::TEMPLATE_CONFIG)->get('templates') ?? [];
    if (!is_array($custom)) {
      return $templates;
    }
    foreach ($custom as $id => $template) {
      if (!is_array($template) || empty($template['active'])) {
        continue;
      }
      $templates[(string) $id] = [
        'name' => (string) ($template['name'] ?? $id),
        'percentages' => array_values(array_map('floatval', $template['percentages'] ?? [])),
        'labels' => array_values(array_map('strval', $template['labels'] ?? [])),
      ];
    }
    return $templates;
  }

}
