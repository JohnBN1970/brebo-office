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
    private readonly ConfigFactoryInterface $templateConfigFactory,
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

    $commercial = $this->commercialScheduleForProject($node);
    if ($commercial === NULL) {
      $form['warning'] = ['#markup' => '<p><strong>' . $this->t('Er is geen commercieel termijnschema gevonden in een geaccepteerde offerte of projectsnapshot. Leg dit eerst commercieel vast; Finance mag hier geen nieuwe verdeling bedenken.') . '</strong></p>'];
      $form['back'] = ['#type' => 'link', '#title' => $this->t('Terug naar Facturen'), '#url' => Url::fromRoute('brebo_project_cockpit.invoices', ['node' => $projectId]), '#attributes' => ['class' => ['button']]];
      return $form;
    }

    $defaultDays = isset($contract['payment_term_days']) && is_numeric($contract['payment_term_days'])
      ? max(0, (int) $contract['payment_term_days'])
      : $this->globalPaymentTermDays();

    $form['project'] = ['#markup' => '<p><strong>' . $this->t('Project:') . '</strong> ' . $node->label() . '<br><strong>' . $this->t('Contractsom excl. btw:') . '</strong> € ' . number_format((float) ($contract['amount_ex_vat'] ?? 0), 2, ',', '.') . '<br><strong>' . $this->t('Standaard betaaltermijn:') . '</strong> ' . ($defaultDays === 0 ? $this->t('Per omgaande') : $this->t('@days dagen', ['@days' => $defaultDays])) . '</p>'];
    $form['commercial_source'] = ['#type' => 'item', '#title' => $this->t('Commerciële bron'), '#markup' => '<strong>' . htmlspecialchars((string) $commercial['source_label'], ENT_QUOTES, 'UTF-8') . '</strong><br><small>' . htmlspecialchars(implode(' / ', array_map(static fn(float $v): string => rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.') . '%', $commercial['percentages'])), ENT_QUOTES, 'UTF-8') . '</small>'];
    $form['first_date'] = ['#type' => 'date', '#title' => $this->t('Datum eerste termijn'), '#required' => TRUE, '#default_value' => date('Y-m-d')];
    $form['interval_months'] = ['#type' => 'number', '#title' => $this->t('Tussenruimte in maanden'), '#required' => TRUE, '#min' => 0, '#max' => 24, '#default_value' => 1, '#description' => $this->t('Gebruik 0 wanneer alle termijnen dezelfde geplande datum krijgen; data blijven later per termijn aanpasbaar.')];
    $form['payment_term'] = [
      '#type' => 'select',
      '#title' => $this->t('Betaaltermijn voor nieuwe termijnen'),
      '#options' => [
        'default' => $this->t('Overnemen uit projectcontract (@days dagen)', ['@days' => $defaultDays]),
        '0' => $this->t('Per omgaande'),
        '5' => $this->t('5 dagen'),
        '8' => $this->t('8 dagen'),
        '14' => $this->t('14 dagen'),
        '30' => $this->t('30 dagen'),
        'custom' => $this->t('Afwijkend aantal dagen'),
      ],
      '#default_value' => 'default',
      '#description' => $this->t('Deze waarde wordt in iedere termijn opgeslagen. Afwijkende termijnen kunnen daarna afzonderlijk in de termijnstaat worden aangepast.'),
    ];
    $form['custom_payment_term_days'] = [
      '#type' => 'number',
      '#title' => $this->t('Afwijkend aantal dagen'),
      '#min' => 0,
      '#max' => 365,
      '#states' => ['visible' => [':input[name="payment_term"]' => ['value' => 'custom']]],
    ];
    $form['vat_rate'] = [
      '#type' => 'select',
      '#title' => $this->t('Tijdelijk btw-regime op termijnkop'),
      '#options' => ['21' => '21%', '9' => '9%', '0' => '0%'],
      '#default_value' => '21',
      '#description' => $this->t('Dit veld bestaat vanwege de huidige termijnkop. Gemengde btw wordt in de volgende stap op regelniveau vastgelegd; gebruik dit schema daarom nog niet als fiscale bron voor projecten met gemengde btw.'),
    ];

    $form['actions']['submit'] = ['#type' => 'submit', '#value' => $this->t('Termijnschema aanmaken'), '#button_type' => 'primary'];
    $form['actions']['cancel'] = ['#type' => 'link', '#title' => $this->t('Annuleren'), '#url' => Url::fromRoute('brebo_project_cockpit.invoices', ['node' => $projectId]), '#attributes' => ['class' => ['button']]];

    $form_state->set('project_id', $projectId);
    $form_state->set('contract', $contract);
    $form_state->set('commercial_schedule', $commercial);
    $form_state->set('default_payment_term_days', $defaultDays);
    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    if ((string) $form_state->getValue('payment_term') === 'custom' && $form_state->getValue('custom_payment_term_days') === '') {
      $form_state->setErrorByName('custom_payment_term_days', $this->t('Vul het afwijkende aantal betalingsdagen in.'));
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $projectId = (int) $form_state->get('project_id');
    $contract = $form_state->get('contract');
    $commercial = $form_state->get('commercial_schedule');
    if (!is_array($contract) || !is_array($commercial)) {
      throw new \RuntimeException('Contract or commercial instalment schedule unavailable.');
    }

    $choice = (string) $form_state->getValue('payment_term');
    $paymentDays = match ($choice) {
      'custom' => max(0, (int) $form_state->getValue('custom_payment_term_days')),
      'default' => max(0, (int) $form_state->get('default_payment_term_days')),
      default => is_numeric($choice) ? max(0, (int) $choice) : max(0, (int) $form_state->get('default_payment_term_days')),
    };

    $percentages = array_values(array_map('floatval', $commercial['percentages'] ?? []));
    $labels = array_values(array_map('strval', $commercial['labels'] ?? []));
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
            'source' => (string) $commercial['source'],
            'source_ref' => (string) $commercial['source_ref'],
            'source_label' => (string) $commercial['source_label'],
            'content_hash' => (string) $commercial['content_hash'],
            'percentage' => $percentage,
            'payment_term_days' => $paymentDays,
            'payment_term_source' => $choice === 'default' ? 'project_contract' : 'instalment_schedule',
          ],
        ], $actor);
      }
    }
    catch (\Throwable $exception) {
      $transaction->rollBack();
      throw $exception;
    }

    $this->messenger()->addStatus($this->t('@count termijnen zijn aangemaakt vanuit sjabloon “@name” met @term als betaaltermijn. Afwijkingen kunnen per termijn in de termijnstaat worden aangepast.', [
      '@count' => count($percentages),
      '@name' => $commercial['source_label'],
      '@term' => $paymentDays === 0 ? 'per omgaande' : $paymentDays . ' dagen',
    ]));
    $form_state->setRedirect('brebo_project_cockpit.invoices', ['node' => $projectId]);
  }

  private function globalPaymentTermDays(): int {
    $value = $this->templateConfigFactory->get('brebo_finance.sales')->get('numbering.default_payment_term_days');
    return is_numeric($value) ? max(0, (int) $value) : 14;
  }

  /**
   * Resolves the immutable commercial schedule: accepted offer first, project snapshot second.
   *
   * @return array{source:string,source_ref:string,source_label:string,content_hash:string,percentages:list<float>,labels:list<string>}|null
   */
  private function commercialScheduleForProject(NodeInterface $project): ?array {
    if ($project->hasField('field_brebo_project_opp_ref') && !$project->get('field_brebo_project_opp_ref')->isEmpty()) {
      $opportunity = $project->get('field_brebo_project_opp_ref')->entity;
      if ($opportunity instanceof NodeInterface && $opportunity->hasField('field_brebo_opp_offer_ref')) {
        $offer = $opportunity->get('field_brebo_opp_offer_ref')->entity;
        if ($offer instanceof NodeInterface
          && (string) ($offer->get('field_brebo_offer_status')->value ?? '') === 'Geaccepteerd') {
          $snapshot = json_decode((string) ($offer->get('field_brebo_offer_snapshot')->value ?? ''), TRUE);
          $schedule = is_array($snapshot) ? ($snapshot['commercial_instalment_schedule'] ?? NULL) : NULL;
          if (is_array($schedule) && is_array($schedule['schedule'] ?? NULL)) {
            return $this->normalizeCommercialSchedule(
              (array) $schedule['schedule'],
              'accepted_offer',
              (string) $offer->id(),
              (string) $offer->label(),
              (string) ($schedule['content_hash'] ?? ''),
            );
          }
        }
      }
    }

    if (!$this->database->schema()->tableExists('brebo_project_commercial_instalment_schedule')) {
      return NULL;
    }
    $row = $this->database->select('brebo_project_commercial_instalment_schedule', 's')
      ->fields('s', ['schedule_payload', 'content_hash'])
      ->condition('project_nid', (int) $project->id())
      ->execute()->fetchAssoc();
    if (!is_array($row)) {
      return NULL;
    }
    $payload = json_decode((string) $row['schedule_payload'], TRUE);
    return is_array($payload)
      ? $this->normalizeCommercialSchedule($payload, 'project_commercial_schedule', (string) $project->id(), 'Commercieel projecttermijnschema', (string) $row['content_hash'])
      : NULL;
  }

  /**
   * @param array<string, mixed> $payload
   *
   * @return array{source:string,source_ref:string,source_label:string,content_hash:string,percentages:list<float>,labels:list<string>}|null
   */
  private function normalizeCommercialSchedule(array $payload, string $source, string $sourceRef, string $sourceLabel, string $contentHash): ?array {
    $percentages = array_values(array_map('floatval', $payload['percentages'] ?? []));
    $labels = array_values(array_map('strval', $payload['labels'] ?? []));
    if ($percentages === [] || abs(array_sum($percentages) - 100.0) > 0.001 || $contentHash === '') {
      return NULL;
    }
    return [
      'source' => $source,
      'source_ref' => $sourceRef,
      'source_label' => $sourceLabel,
      'content_hash' => $contentHash,
      'percentages' => $percentages,
      'labels' => $labels,
    ];
  }

}
