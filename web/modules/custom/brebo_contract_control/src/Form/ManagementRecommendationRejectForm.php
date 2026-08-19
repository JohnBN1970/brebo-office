<?php

declare(strict_types=1);

namespace Drupal\brebo_contract_control\Form;

use Drupal\brebo_contract_control\Service\ManagementControlCenterService;
use Drupal\brebo_contract_control\Service\ManagementDecisionRecommendationEngine;
use Drupal\brebo_contract_control\Service\ManagementDecisionRecordService;
use Drupal\brebo_contract_control\Service\ManagementForecastIntelligenceService;
use Drupal\brebo_contract_control\Service\ManagementScenarioIntelligenceService;
use Drupal\brebo_contract_control\Service\ManagementTrendIntelligenceService;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/** Records a deliberate rejection of the current BREBO recommendation. */
final class ManagementRecommendationRejectForm extends FormBase {
  public function __construct(private readonly ManagementControlCenterService $controlCenter, private readonly ManagementTrendIntelligenceService $trends, private readonly ManagementForecastIntelligenceService $forecast, private readonly ManagementScenarioIntelligenceService $scenarios, private readonly ManagementDecisionRecommendationEngine $recommendations, private readonly ManagementDecisionRecordService $decisionRecords) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('brebo_contract_control.management_control_center'), $container->get('brebo_contract_control.management_trends'), $container->get('brebo_contract_control.management_forecast'), $container->get('brebo_contract_control.management_scenarios'), $container->get('brebo_contract_control.management_decision_recommendation'), $container->get('brebo_contract_control.management_decision_records'));
  }

  public function getFormId(): string { return 'brebo_management_recommendation_reject_form'; }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $recommendation = $this->currentRecommendation();
    $scenario = (array) ($recommendation['recommended_scenario'] ?? []);
    $form_state->set('recommendation', $recommendation);
    $form['summary'] = ['#markup' => '<h1>BREBO-advies afwijzen</h1><p><strong>' . htmlspecialchars((string) ($scenario['title'] ?? 'Advies')) . '</strong></p><p>Afwijzing wordt onderdeel van het permanente beslisdossier.</p>'];
    $form['reason'] = ['#type' => 'textarea', '#title' => $this->t('Reden van afwijzing'), '#required' => TRUE, '#rows' => 5];
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = ['#type' => 'submit', '#value' => $this->t('Wijs advies af en leg besluit vast'), '#button_type' => 'danger'];
    $form['actions']['cancel'] = ['#type' => 'link', '#title' => $this->t('Annuleren'), '#url' => Url::fromRoute('brebo_contract_control.management_control_center')];
    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $recordId = $this->decisionRecords->record('rejected', (array) $form_state->get('recommendation'), (int) $this->currentUser()->id(), NULL, trim((string) $form_state->getValue('reason')));
    $this->messenger()->addStatus($this->t('Advies afgewezen en vastgelegd als Decision Record @record.', ['@record' => $recordId]));
    $form_state->setRedirect('brebo_contract_control.management_control_center');
  }

  /** @return array<string, mixed> */
  private function currentRecommendation(): array {
    $dashboard = $this->controlCenter->dashboard();
    $headline = (array) ($dashboard['headline'] ?? []);
    $trend = $this->trends->compare($headline);
    $forecast = $this->forecast->forecast($headline, $trend, 21);
    return $this->recommendations->recommend($this->scenarios->scenarios($headline, $forecast));
  }
}
