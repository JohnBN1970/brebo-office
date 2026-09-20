<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Form;

use Drupal\Core\Database\Connection;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Edits version-bound commercial calculation parameters.
 */
final class CalculationParametersForm extends FormBase {

  public function __construct(
    private readonly Connection $database,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('database'));
  }

  public function getFormId(): string {
    return 'brebo_calculation_parameters_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, ?NodeInterface $node = NULL): array {
    if (!$node instanceof NodeInterface || $node->bundle() !== 'brebo_calculation') {
      throw new \InvalidArgumentException('Calculation expected.');
    }
    $version = $this->latestVersion((int) $node->id());
    if ($version === NULL) {
      return ['#markup' => '<p>Deze calculatie heeft nog geen domeinversie.</p>'];
    }

    $locked = $version['locked_at'] !== NULL || (string) $version['status'] !== 'draft';
    $form['calculation_id'] = ['#type' => 'hidden', '#value' => (int) $node->id()];
    $form['version'] = ['#type' => 'hidden', '#value' => (string) $version['version']];
    $form['content_hash'] = ['#type' => 'hidden', '#value' => (string) ($version['content_hash'] ?? '')];

    $form['meta'] = [
      '#markup' => '<div class="brebo-calc-workbench__meta"><span><strong>Versie</strong> ' . htmlspecialchars((string) $version['version']) . '</span><span><strong>Status</strong> ' . htmlspecialchars((string) $version['status']) . '</span><span class="' . ($locked ? 'is-locked' : 'is-open') . '">' . ($locked ? '🔒 Vergrendeld' : '● Bewerkbaar') . '</span></div>',
    ];

    $form['pricing_mode'] = [
      '#type' => 'select', '#title' => 'Calculatiemodus',
      '#options' => ['closed' => 'Gesloten', 'open' => 'Open', 'semi_open' => 'Intern open / extern gesloten', 'cost_plus' => 'Regie / cost-plus'],
      '#default_value' => $version['pricing_mode'], '#disabled' => $locked,
    ];
    $form['commercial_method'] = [
      '#type' => 'radios', '#title' => 'Commerciële prijsopbouw',
      '#options' => ['tail_costs' => 'Staartkosten', 'single_margin' => 'Enkele marge'],
      '#default_value' => $version['commercial_method'], '#disabled' => $locked,
    ];

    $form['tail_costs'] = ['#type' => 'details', '#title' => 'Staartkosten', '#open' => TRUE];
    foreach (['general_cost_pct' => 'Algemene kosten (AK) %', 'risk_pct' => 'Risico / onvoorzien %', 'profit_pct' => 'Winst %'] as $key => $label) {
      $form['tail_costs'][$key] = [
        '#type' => 'number', '#title' => $label, '#default_value' => (float) $version[$key],
        '#min' => 0, '#step' => '0.01', '#disabled' => $locked,
        '#states' => ['visible' => [':input[name="commercial_method"]' => ['value' => 'tail_costs']]],
      ];
    }
    $form['single_margin_pct'] = [
      '#type' => 'number', '#title' => 'Enkele marge %', '#default_value' => (float) $version['single_margin_pct'],
      '#min' => 0, '#step' => '0.01', '#disabled' => $locked,
      '#states' => ['visible' => [':input[name="commercial_method"]' => ['value' => 'single_margin']]],
    ];
    $form['commercial_adjustment'] = [
      '#type' => 'number', '#title' => 'Commerciële correctie €', '#default_value' => (float) $version['commercial_adjustment'],
      '#step' => '0.01', '#disabled' => $locked,
      '#description' => 'Expliciete eindcorrectie op de berekende verkoopprijs. Negatief is toegestaan.',
    ];
    $form['price_date'] = [
      '#type' => 'date', '#title' => 'Prijspeildatum', '#default_value' => $version['price_date'] ?: NULL, '#disabled' => $locked,
    ];
    $form['price_level'] = [
      '#type' => 'textfield', '#title' => 'Prijsniveau / referentie', '#default_value' => (string) ($version['price_level'] ?? ''),
      '#maxlength' => 64, '#disabled' => $locked,
    ];

    if (!$locked) {
      $form['actions'] = ['#type' => 'actions'];
      $form['actions']['submit'] = ['#type' => 'submit', '#value' => 'Parameters opslaan', '#button_type' => 'primary'];
    }
    else {
      $form['locked_notice'] = ['#markup' => '<p><strong>Deze versie is vastgesteld/vergrendeld.</strong> Parameters kunnen niet meer worden gewijzigd.</p>'];
    }

    $form['#attached']['library'][] = 'brebo_calculation/workbench';
    $form['#cache']['max-age'] = 0;
    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    foreach (['general_cost_pct', 'risk_pct', 'profit_pct', 'single_margin_pct'] as $field) {
      if ((float) $form_state->getValue($field) < 0) {
        $form_state->setErrorByName($field, 'Percentage kan niet negatief zijn.');
      }
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $calculationId = (int) $form_state->getValue('calculation_id');
    $versionName = (string) $form_state->getValue('version');
    $current = $this->database->select('brebo_calculation_version', 'v')
      ->fields('v')
      ->condition('calculation_id', $calculationId)
      ->condition('version', $versionName)
      ->execute()->fetchAssoc();
    if (!$current || $current['locked_at'] !== NULL || (string) $current['status'] !== 'draft') {
      throw new \RuntimeException('Deze calculatieversie is inmiddels vergrendeld of gewijzigd.');
    }
    if ((string) ($current['content_hash'] ?? '') !== (string) $form_state->getValue('content_hash')) {
      throw new \RuntimeException('De calculatieversie is ondertussen gewijzigd. Open Parameters opnieuw.');
    }

    $values = [
      'pricing_mode' => (string) $form_state->getValue('pricing_mode'),
      'commercial_method' => (string) $form_state->getValue('commercial_method'),
      'general_cost_pct' => (float) $form_state->getValue(['tail_costs', 'general_cost_pct']),
      'risk_pct' => (float) $form_state->getValue(['tail_costs', 'risk_pct']),
      'profit_pct' => (float) $form_state->getValue(['tail_costs', 'profit_pct']),
      'single_margin_pct' => (float) $form_state->getValue('single_margin_pct'),
      'commercial_adjustment' => (float) $form_state->getValue('commercial_adjustment'),
      'price_date' => ($date = (string) $form_state->getValue('price_date')) !== '' ? $date : NULL,
      'price_level' => ($level = trim((string) $form_state->getValue('price_level'))) !== '' ? $level : NULL,
    ];
    $values['content_hash'] = hash('sha256', json_encode([$calculationId, $versionName, $values], JSON_THROW_ON_ERROR));

    $updated = $this->database->update('brebo_calculation_version')
      ->fields($values)
      ->condition('calculation_id', $calculationId)
      ->condition('version', $versionName)
      ->condition('status', 'draft')
      ->isNull('locked_at')
      ->condition('content_hash', (string) ($current['content_hash'] ?? ''))
      ->execute();
    if ($updated !== 1) {
      throw new \RuntimeException('Parameters konden niet veilig worden opgeslagen omdat de calculatie ondertussen wijzigde.');
    }

    $this->messenger()->addStatus('Calculatieparameters opgeslagen. De commerciële uitkomst wordt met deze versieparameters herberekend.');
    $form_state->setRedirect('brebo_calculation.parameters', ['node' => $calculationId]);
  }

  /** @return array<string,mixed>|null */
  private function latestVersion(int $calculationId): ?array {
    $record = $this->database->select('brebo_calculation_version', 'v')->fields('v')
      ->condition('calculation_id', $calculationId)->orderBy('id', 'DESC')->range(0, 1)->execute()->fetchAssoc();
    return $record ?: NULL;
  }

}
