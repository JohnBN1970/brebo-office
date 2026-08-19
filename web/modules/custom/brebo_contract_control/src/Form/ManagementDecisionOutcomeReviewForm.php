<?php

declare(strict_types=1);

namespace Drupal\brebo_contract_control\Form;

use Drupal\brebo_contract_control\Service\ManagementControlCenterService;
use Drupal\brebo_contract_control\Service\ManagementDecisionRecordService;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/** Captures actual 30/90-day outcomes for a management decision. */
final class ManagementDecisionOutcomeReviewForm extends FormBase {
  public function __construct(private readonly ManagementDecisionRecordService $records, private readonly ManagementControlCenterService $controlCenter) {}
  public static function create(ContainerInterface $container): static { return new static($container->get('brebo_contract_control.management_decision_records'), $container->get('brebo_contract_control.management_control_center')); }
  public function getFormId(): string { return 'brebo_management_decision_outcome_review'; }

  public function buildForm(array $form, FormStateInterface $form_state, int $record_id = 0, int $review_days = 30): array {
    if (!in_array($review_days, [30, 90], TRUE)) { throw new \InvalidArgumentException('Ongeldige reviewperiode.'); }
    $form_state->set('record_id', $record_id); $form_state->set('review_days', $review_days);
    $headline = (array) (($this->controlCenter->dashboard())['headline'] ?? []);
    $form_state->set('headline', $headline);
    $form['intro'] = ['#markup' => '<h1>' . $review_days . '-dagen outcome review</h1><p>Leg de werkelijkheid vast. De actuele Control Center-KPI’s worden als objectieve meetmoment-snapshot opgeslagen.</p>'];
    $form['actual'] = ['#type' => 'details', '#title' => $this->t('Actuele gemeten KPI’s'), '#open' => TRUE];
    foreach ($headline as $key => $value) { if (is_scalar($value)) { $form['actual'][$key] = ['#markup' => '<div><strong>' . htmlspecialchars((string) $key) . ':</strong> ' . htmlspecialchars((string) $value) . '</div>']; } }
    $form['assessment'] = ['#type' => 'select', '#title' => $this->t('Beoordeling resultaat'), '#required' => TRUE, '#options' => ['better_than_expected' => $this->t('Beter dan verwacht'), 'as_expected' => $this->t('Volgens verwachting'), 'worse_than_expected' => $this->t('Slechter dan verwacht'), 'inconclusive' => $this->t('Nog niet overtuigend')]];
    $form['explanation'] = ['#type' => 'textarea', '#title' => $this->t('Toelichting / oorzaak'), '#required' => TRUE, '#rows' => 5];
    $form['actions'] = ['#type' => 'actions', 'submit' => ['#type' => 'submit', '#value' => $this->t('Leg outcome vast'), '#button_type' => 'primary']];
    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $outcome = ['assessment' => (string) $form_state->getValue('assessment'), 'explanation' => trim((string) $form_state->getValue('explanation')), 'actual_headline' => (array) $form_state->get('headline')];
    $this->records->recordOutcome((int) $form_state->get('record_id'), (int) $form_state->get('review_days'), $outcome, (int) $this->currentUser()->id());
    $this->messenger()->addStatus($this->t('@days-dagen outcome is vastgelegd.', ['@days' => $form_state->get('review_days')]));
    $form_state->setRedirect('brebo_contract_control.management_control_center');
  }
}
