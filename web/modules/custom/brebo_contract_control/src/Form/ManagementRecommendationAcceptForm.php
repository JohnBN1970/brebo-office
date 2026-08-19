<?php

declare(strict_types=1);

namespace Drupal\brebo_contract_control\Form;

use Drupal\brebo_contract_control\Service\ManagementActionEngine;
use Drupal\brebo_contract_control\Service\ManagementControlCenterService;
use Drupal\brebo_contract_control\Service\ManagementDecisionRecommendationEngine;
use Drupal\brebo_contract_control\Service\ManagementForecastIntelligenceService;
use Drupal\brebo_contract_control\Service\ManagementScenarioIntelligenceService;
use Drupal\brebo_contract_control\Service\ManagementTrendIntelligenceService;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/** Confirms and accepts the current BREBO management recommendation. */
final class ManagementRecommendationAcceptForm extends FormBase {

  public function __construct(
    private readonly ManagementControlCenterService $controlCenter,
    private readonly ManagementTrendIntelligenceService $trends,
    private readonly ManagementForecastIntelligenceService $forecast,
    private readonly ManagementScenarioIntelligenceService $scenarios,
    private readonly ManagementDecisionRecommendationEngine $recommendations,
    private readonly ManagementActionEngine $actions,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('brebo_contract_control.management_control_center'),
      $container->get('brebo_contract_control.management_trends'),
      $container->get('brebo_contract_control.management_forecast'),
      $container->get('brebo_contract_control.management_scenarios'),
      $container->get('brebo_contract_control.management_decision_recommendation'),
      $container->get('brebo_contract_control.management_actions'),
    );
  }

  public function getFormId(): string {
    return 'brebo_management_recommendation_accept_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $recommendation = $this->currentRecommendation();
    $scenario = (array) ($recommendation['recommended_scenario'] ?? []);
    $form_state->set('recommendation', $recommendation);

    $form['summary'] = [
      '#markup' => '<h1>BREBO-advies accepteren</h1><p><strong>' . htmlspecialchars((string) ($scenario['title'] ?? 'Geen uitvoerbaar advies')) . '</strong></p><p>' . htmlspecialchars((string) ($recommendation['recommendation'] ?? '')) . '</p><p>Decision score: ' . htmlspecialchars((string) ($scenario['decision_score'] ?? 'n.v.t.')) . ' · Confidence: ' . htmlspecialchars((string) ($recommendation['confidence'] ?? 'low')) . '</p>',
    ];
    $form['governance'] = [
      '#markup' => '<div class="brebo-control-muted">Door te accepteren wordt een managementactie aangemaakt op naam van de huidige gebruiker. Het advies blijft beslisondersteuning; de menselijke beslissing wordt als acceptatiemoment vastgelegd.</div>',
    ];
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Accepteer advies en maak actie'),
      '#button_type' => 'primary',
      '#disabled' => empty($scenario) || (($scenario['key'] ?? '') === 'do_nothing'),
    ];
    $form['actions']['cancel'] = [
      '#type' => 'link',
      '#title' => $this->t('Annuleren'),
      '#url' => \Drupal\Core\Url::fromRoute('brebo_contract_control.management_control_center'),
    ];
    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $recommendation = (array) $form_state->get('recommendation');
    $result = $this->actions->createFromRecommendation($recommendation, (int) $this->currentUser()->id());
    if ($result['created'] ?? FALSE) {
      $this->messenger()->addStatus($this->t('BREBO-advies geaccepteerd. Managementactie @id is aangemaakt.', ['@id' => $result['action_id']]));
    }
    else {
      $this->messenger()->addWarning($this->t('Voor dit advies bestaat al een open managementactie.'));
    }
    $form_state->setRedirect('brebo_contract_control.management_control_center');
  }

  /** @return array<string, mixed> */
  private function currentRecommendation(): array {
    $dashboard = $this->controlCenter->dashboard();
    $headline = (array) ($dashboard['headline'] ?? []);
    $trend = $this->trends->compare($headline);
    $forecast = $this->forecast->forecast($headline, $trend, 21);
    $scenarioData = $this->scenarios->scenarios($headline, $forecast);
    return $this->recommendations->recommend($scenarioData);
  }
}
