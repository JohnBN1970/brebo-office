<?php

declare(strict_types=1);

namespace Drupal\brebo_project_cockpit\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Captures the commercial project instalment schedule before contract.
 *
 * This is the project truth used by offer/contract flows. It deliberately does
 * not create Finance billing instalments; those remain contractual records.
 */
final class ProjectCommercialInstalmentScheduleForm extends FormBase {

  private const TEMPLATE_CONFIG = 'brebo_project_cockpit.instalment_templates';

  public function __construct(
    private readonly Connection $database,
    private readonly ConfigFactoryInterface $projectConfigFactory,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('database'), $container->get('config.factory'));
  }

  public function getFormId(): string {
    return 'brebo_project_cockpit_commercial_instalment_schedule_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, ?NodeInterface $node = NULL): array {
    if (!$node instanceof NodeInterface || $node->bundle() !== 'brebo_project') {
      throw new \InvalidArgumentException('BREBO project required.');
    }

    $projectId = (int) $node->id();
    $existing = $this->database->select('brebo_project_commercial_instalment_schedule', 's')
      ->fields('s')
      ->condition('project_nid', $projectId)
      ->execute()
      ->fetchAssoc();

    $templates = $this->templates();
    $options = ['manual' => $this->t('Projectspecifiek schema')];
    foreach ($templates as $id => $template) {
      $options[$id] = $template['name'] . ' · ' . implode(' / ', array_map(static fn(float $v): string => rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.') . '%', $template['percentages']));
    }
    $storedTemplateId = (string) ($existing['source_template_id'] ?? '');
    if ($storedTemplateId !== '' && !isset($options[$storedTemplateId])) {
      $storedTemplateName = (string) ($existing['source_template_name'] ?? $storedTemplateId);
      $options[$storedTemplateId] = $storedTemplateName . ' · ' . $this->t('gearchiveerd projectsjabloon');
    }

    $payload = $existing ? json_decode((string) $existing['schedule_payload'], TRUE) : [];
    $percentages = is_array($payload['percentages'] ?? NULL) ? $payload['percentages'] : [];
    $labels = is_array($payload['labels'] ?? NULL) ? $payload['labels'] : [];

    $form['intro'] = ['#markup' => '<p>' . $this->t('Dit schema hoort bij het project vanaf de commerciële fase. Offerte en contract gebruiken deze projectwaarheid; pas na contractgoedkeuring worden hieruit Finance-termijnen aangemaakt.') . '</p>'];
    $form['template'] = [
      '#type' => 'select',
      '#title' => $this->t('Startpunt'),
      '#options' => $options,
      '#default_value' => (string) ($existing['source_template_id'] ?? 'manual'),
      '#description' => $this->t('Een sjabloon wordt gekopieerd naar het project. Latere wijzigingen aan het centrale sjabloon wijzigen dit project niet.'),
    ];
    $form['percentages'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Percentages'),
      '#default_value' => implode(',', $percentages),
      '#states' => ['required' => [':input[name="template"]' => ['value' => 'manual']]],
      '#description' => $this->t('Komma-gescheiden en samen exact 100%. Bij opslaan met een gekozen sjabloon wordt diens verdeling als projectsnapshot overgenomen.'),
    ];
    $form['labels'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Omschrijvingen'),
      '#default_value' => implode(',', $labels),
      '#description' => $this->t('Komma-gescheiden; evenveel omschrijvingen als percentages.'),
    ];
    $form['payment_term_days'] = [
      '#type' => 'number',
      '#title' => $this->t('Betaaltermijn'),
      '#field_suffix' => $this->t('dagen'),
      '#min' => 0,
      '#max' => 365,
      '#default_value' => (int) ($payload['payment_term_days'] ?? $this->globalPaymentTermDays()),
      '#required' => TRUE,
    ];
    $form['actions']['submit'] = ['#type' => 'submit', '#value' => $this->t('Projecttermijnschema opslaan'), '#button_type' => 'primary'];
    $form['actions']['cancel'] = ['#type' => 'link', '#title' => $this->t('Annuleren'), '#url' => Url::fromRoute('brebo_project_cockpit.overview', ['node' => $projectId]), '#attributes' => ['class' => ['button']]];

    $form_state->set('project_id', $projectId);
    $form_state->set('templates', $templates);
    $form_state->set('existing_payload', $payload);
    $form_state->set('existing_template_id', (string) ($existing['source_template_id'] ?? ''));
    $form_state->set('existing_template_name', (string) ($existing['source_template_name'] ?? ''));
    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $templateId = (string) $form_state->getValue('template');
    $templates = $form_state->get('templates');
    if ($templateId !== 'manual' && isset($templates[$templateId])) {
      return;
    }

    $rawPercentages = (string) $form_state->getValue('percentages');
    foreach (explode(',', $rawPercentages) as $token) {
      $token = trim($token);
      if ($token === '' || !is_numeric($token) || (float) $token <= 0) {
        $form_state->setErrorByName('percentages', $this->t('Ieder percentage moet een positief getal zijn; ongeldige of lege waarden zijn niet toegestaan.'));
        return;
      }
    }
    $percentages = $this->parsePercentages($rawPercentages);
    if (abs(array_sum($percentages) - 100.0) > 0.0001) {
      $form_state->setErrorByName('percentages', $this->t('De percentages moeten samen exact 100% zijn.'));
    }
    $labels = $this->parseLabels((string) $form_state->getValue('labels'));
    if ($labels !== [] && count($labels) !== count($percentages)) {
      $form_state->setErrorByName('labels', $this->t('Het aantal omschrijvingen moet gelijk zijn aan het aantal percentages.'));
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $projectId = (int) $form_state->get('project_id');
    $templates = $form_state->get('templates');
    $templateId = (string) $form_state->getValue('template');
    $template = $templateId !== 'manual' ? ($templates[$templateId] ?? NULL) : NULL;
    $existingPayload = $form_state->get('existing_payload');
    $existingTemplateId = (string) $form_state->get('existing_template_id');
    $preserveSnapshot = $templateId !== 'manual'
      && $templateId === $existingTemplateId
      && is_array($existingPayload)
      && is_array($existingPayload['percentages'] ?? NULL);

    $percentages = $preserveSnapshot
      ? array_values(array_map('floatval', $existingPayload['percentages']))
      : (is_array($template) ? $template['percentages'] : $this->parsePercentages((string) $form_state->getValue('percentages')));
    $labels = $preserveSnapshot
      ? array_values(array_map('strval', $existingPayload['labels'] ?? []))
      : (is_array($template) ? $template['labels'] : $this->parseLabels((string) $form_state->getValue('labels')));
    if ($labels === []) {
      $labels = array_map(static fn(int $i): string => 'Termijn ' . ($i + 1), array_keys($percentages));
    }

    $payload = [
      'version' => 1,
      'percentages' => array_values($percentages),
      'labels' => array_values($labels),
      'payment_term_days' => max(0, (int) $form_state->getValue('payment_term_days')),
    ];
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $now = time();
    $uid = (int) $this->currentUser()->id();
    $values = [
      'status' => 'draft',
      'source_template_id' => $templateId === 'manual' ? NULL : $templateId,
      'source_template_name' => $preserveSnapshot
        ? ((string) $form_state->get('existing_template_name') ?: NULL)
        : (is_array($template) ? (string) $template['name'] : NULL),
      'schedule_payload' => $json,
      'content_hash' => hash('sha256', $json),
      'changed' => $now,
      'changed_by' => $uid,
    ];

    $exists = (bool) $this->database->select('brebo_project_commercial_instalment_schedule', 's')
      ->condition('project_nid', $projectId)->countQuery()->execute()->fetchField();
    if ($exists) {
      $this->database->update('brebo_project_commercial_instalment_schedule')->fields($values)->condition('project_nid', $projectId)->execute();
    }
    else {
      $this->database->insert('brebo_project_commercial_instalment_schedule')->fields($values + [
        'project_nid' => $projectId,
        'created' => $now,
        'created_by' => $uid,
      ])->execute();
    }

    $this->messenger()->addStatus($this->t('Het commerciële termijnschema is als projectsnapshot opgeslagen.'));
    $form_state->setRedirect('brebo_project_cockpit.overview', ['node' => $projectId]);
  }

  private function globalPaymentTermDays(): int {
    $value = $this->projectConfigFactory->get('brebo_finance.sales')->get('numbering.default_payment_term_days');
    return is_numeric($value) ? max(0, (int) $value) : 14;
  }

  /** @return array<string, array{name: string, percentages: list<float>, labels: list<string>}> */
  private function templates(): array {
    $templates = InstalmentTemplateForm::standardTemplates();
    $custom = $this->projectConfigFactory->get(self::TEMPLATE_CONFIG)->get('templates') ?? [];
    if (is_array($custom)) {
      foreach ($custom as $id => $template) {
        if (is_array($template) && !empty($template['active'])) {
          $templates[(string) $id] = [
            'name' => (string) ($template['name'] ?? $id),
            'percentages' => array_values(array_map('floatval', $template['percentages'] ?? [])),
            'labels' => array_values(array_map('strval', $template['labels'] ?? [])),
          ];
        }
      }
    }
    return $templates;
  }

  /** @return list<float> */
  private function parsePercentages(string $value): array {
    $result = [];
    foreach (explode(',', $value) as $part) {
      $part = trim($part);
      if ($part !== '' && is_numeric($part) && (float) $part > 0) {
        $result[] = round((float) $part, 4);
      }
    }
    return $result;
  }

  /** @return list<string> */
  private function parseLabels(string $value): array {
    return trim($value) === '' ? [] : array_values(array_filter(array_map('trim', explode(',', $value)), static fn(string $v): bool => $v !== ''));
  }

}
