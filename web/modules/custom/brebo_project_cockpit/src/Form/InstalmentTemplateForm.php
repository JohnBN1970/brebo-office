<?php

declare(strict_types=1);

namespace Drupal\brebo_project_cockpit\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/** Manages reusable billing instalment templates. */
final class InstalmentTemplateForm extends FormBase {

  private const CONFIG_NAME = 'brebo_project_cockpit.instalment_templates';

  public function __construct(
    private readonly ConfigFactoryInterface $templateConfigFactory,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('config.factory'));
  }

  public function getFormId(): string {
    return 'brebo_project_cockpit_instalment_template_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, ?NodeInterface $node = NULL): array {
    if ($node !== NULL && $node->bundle() !== 'brebo_project') {
      throw new \InvalidArgumentException('BREBO project required.');
    }

    $custom = $this->templateConfigFactory->get(self::CONFIG_NAME)->get('templates') ?? [];

    $form['intro'] = [
      '#markup' => '<p>' . $this->t('BREBO-standaardsjablonen blijven vast beschikbaar. Eigen sjablonen worden hier opgeslagen en kunnen later op ieder project worden toegepast. Een wijziging aan een sjabloon wijzigt nooit bestaande projecttermijnen.') . '</p>',
    ];

    $rows = [];
    foreach ($this->standardTemplates() as $template) {
      $rows[] = [
        $template['name'],
        implode(' / ', array_map(static fn(float $v): string => rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.') . '%', $template['percentages'])),
        $this->t('BREBO standaard'),
      ];
    }
    foreach ($custom as $template) {
      if (!is_array($template)) {
        continue;
      }
      $percentages = array_map('floatval', $template['percentages'] ?? []);
      $rows[] = [
        (string) ($template['name'] ?? $this->t('Naamloos sjabloon')),
        implode(' / ', array_map(static fn(float $v): string => rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.') . '%', $percentages)),
        !empty($template['active']) ? $this->t('Eigen · actief') : $this->t('Eigen · inactief'),
      ];
    }

    $form['templates'] = [
      '#type' => 'table',
      '#header' => [$this->t('Sjabloon'), $this->t('Verdeling'), $this->t('Type')],
      '#rows' => $rows,
      '#empty' => $this->t('Er zijn nog geen termijnsjablonen.'),
    ];

    $form['new'] = [
      '#type' => 'details',
      '#title' => $this->t('Eigen sjabloon toevoegen'),
      '#open' => TRUE,
    ];
    $form['new']['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Naam'),
      '#required' => TRUE,
      '#maxlength' => 128,
      '#placeholder' => $this->t('Bijv. Woningcorporatie maandtermijnen'),
    ];
    $form['new']['percentages'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Percentages'),
      '#required' => TRUE,
      '#description' => $this->t('Komma-gescheiden; samen exact 100%. Bijvoorbeeld: 10,30,40,20'),
      '#placeholder' => '10,30,40,20',
    ];
    $form['new']['labels'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Omschrijvingen'),
      '#description' => $this->t('Optioneel, komma-gescheiden en evenveel regels als percentages. Bijvoorbeeld: Opdracht,Start,Voortgang,Oplevering'),
    ];
    $form['new']['active'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Sjabloon direct actief maken'),
      '#default_value' => TRUE,
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Sjabloon opslaan'),
      '#button_type' => 'primary',
    ];

    if ($node !== NULL) {
      $form['actions']['back'] = [
        '#type' => 'link',
        '#title' => $this->t('Terug naar Facturen'),
        '#url' => \Drupal\Core\Url::fromRoute('brebo_project_cockpit.invoices', ['node' => $node->id()]),
        '#attributes' => ['class' => ['button']],
      ];
    }

    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $percentages = $this->parsePercentages((string) $form_state->getValue('percentages'));
    if ($percentages === []) {
      $form_state->setErrorByName('percentages', $this->t('Voer minimaal één geldig percentage in.'));
      return;
    }
    if (abs(array_sum($percentages) - 100.0) > 0.0001) {
      $form_state->setErrorByName('percentages', $this->t('De percentages moeten samen exact 100% zijn.'));
    }

    $labels = $this->parseLabels((string) $form_state->getValue('labels'));
    if ($labels !== [] && count($labels) !== count($percentages)) {
      $form_state->setErrorByName('labels', $this->t('Het aantal omschrijvingen moet gelijk zijn aan het aantal percentages.'));
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $editable = $this->templateConfigFactory->getEditable(self::CONFIG_NAME);
    $templates = $editable->get('templates') ?? [];
    if (!is_array($templates)) {
      $templates = [];
    }

    $percentages = $this->parsePercentages((string) $form_state->getValue('percentages'));
    $labels = $this->parseLabels((string) $form_state->getValue('labels'));
    if ($labels === []) {
      $labels = array_map(static fn(int $index): string => 'Termijn ' . ($index + 1), array_keys($percentages));
    }

    $id = 'custom_' . substr(hash('sha256', strtolower(trim((string) $form_state->getValue('name'))) . '|' . microtime(TRUE)), 0, 16);
    $templates[$id] = [
      'id' => $id,
      'name' => trim((string) $form_state->getValue('name')),
      'percentages' => $percentages,
      'labels' => $labels,
      'active' => (bool) $form_state->getValue('active'),
      'created' => time(),
      'created_by' => (int) $this->currentUser()->id(),
    ];

    $editable->set('templates', $templates)->save();
    $this->messenger()->addStatus($this->t('Termijnsjabloon “@name” is opgeslagen.', ['@name' => $templates[$id]['name']]));
  }

  /**
   * @return array<string, array{name: string, percentages: list<float>, labels: list<string>}>
   */
  public static function standardTemplates(): array {
    return [
      'brebo_10_30_40_20' => ['name' => '10–30–40–20', 'percentages' => [10.0, 30.0, 40.0, 20.0], 'labels' => ['Opdracht', 'Start', 'Voortgang', 'Oplevering']],
      'brebo_20_30_30_20' => ['name' => '20–30–30–20', 'percentages' => [20.0, 30.0, 30.0, 20.0], 'labels' => ['Opdracht', 'Start', 'Voortgang', 'Oplevering']],
      'brebo_equal_4' => ['name' => '4 gelijke termijnen', 'percentages' => [25.0, 25.0, 25.0, 25.0], 'labels' => ['Termijn 1', 'Termijn 2', 'Termijn 3', 'Termijn 4']],
      'brebo_equal_5' => ['name' => '5 gelijke termijnen', 'percentages' => [20.0, 20.0, 20.0, 20.0, 20.0], 'labels' => ['Termijn 1', 'Termijn 2', 'Termijn 3', 'Termijn 4', 'Termijn 5']],
      'brebo_monthly_progress' => ['name' => 'Maandelijkse voortgang · 10 termijnen', 'percentages' => [10.0, 10.0, 10.0, 10.0, 10.0, 10.0, 10.0, 10.0, 10.0, 10.0], 'labels' => ['Maand 1', 'Maand 2', 'Maand 3', 'Maand 4', 'Maand 5', 'Maand 6', 'Maand 7', 'Maand 8', 'Maand 9', 'Maand 10']],
    ];
  }

  /** @return list<float> */
  private function parsePercentages(string $value): array {
    $result = [];
    foreach (explode(',', $value) as $part) {
      $part = str_replace(',', '.', trim($part));
      if ($part === '' || !is_numeric($part) || (float) $part <= 0) {
        continue;
      }
      $result[] = round((float) $part, 4);
    }
    return $result;
  }

  /** @return list<string> */
  private function parseLabels(string $value): array {
    if (trim($value) === '') {
      return [];
    }
    return array_values(array_filter(array_map('trim', explode(',', $value)), static fn(string $label): bool => $label !== ''));
  }

}
