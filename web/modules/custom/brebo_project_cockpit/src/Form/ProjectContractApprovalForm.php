<?php

declare(strict_types=1);

namespace Drupal\brebo_project_cockpit\Form;

use Drupal\Core\Database\Connection;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/** Approves a project contract and freezes its commercial instalment evidence. */
final class ProjectContractApprovalForm extends ConfirmFormBase {

  public function __construct(private readonly Connection $database) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('database'));
  }

  public function getFormId(): string {
    return 'brebo_project_contract_approval_form';
  }

  public function getQuestion(): string {
    return (string) $this->t('Projectcontract goedkeuren en commerciële afspraken bevriezen?');
  }

  public function getConfirmText(): string {
    return (string) $this->t('Contract goedkeuren');
  }

  public function getCancelUrl(): Url {
    return Url::fromRoute('brebo_project_cockpit.contracts', ['node' => (int) $this->getRequest()->attributes->get('node')->id()]);
  }

  public function buildForm(array $form, FormStateInterface $form_state, ?NodeInterface $node = NULL): array {
    if (!$node instanceof NodeInterface || $node->bundle() !== 'brebo_project') {
      throw new \InvalidArgumentException('BREBO project required.');
    }
    $projectId = (int) $node->id();
    $contract = $this->loadContract($projectId);
    if ($contract === []) {
      $form['missing'] = ['#markup' => '<p><strong>' . $this->t('Registreer eerst een conceptprojectcontract.') . '</strong></p>'];
      return $form;
    }
    if (($contract['status'] ?? '') === 'approved') {
      $form['approved'] = ['#markup' => '<p><strong>' . $this->t('Dit projectcontract is al goedgekeurd.') . '</strong></p>'];
      return $form;
    }
    $schedule = $this->commercialSchedule($projectId);
    if ($schedule === NULL) {
      $form['missing_schedule'] = ['#markup' => '<p><strong>' . $this->t('Goedkeuren kan nog niet: leg eerst het commerciële projecttermijnschema vast.') . '</strong></p>'];
      return $form;
    }
    $form_state->set('project_id', $projectId);
    $form_state->set('contract_id', (int) $contract['id']);
    $form_state->set('contract_hash', $this->contractHash($contract));
    $form_state->set('schedule_hash', (string) $schedule['content_hash']);
    $form['summary'] = ['#markup' => '<p><strong>' . $this->t('Contract:') . '</strong> ' . htmlspecialchars((string) $contract['contract_number'], ENT_QUOTES, 'UTF-8') . '<br><strong>' . $this->t('Contractsom excl. btw:') . '</strong> € ' . number_format((float) $contract['amount_ex_vat'], 2, ',', '.') . '<br><strong>' . $this->t('Termijnschema:') . '</strong> ' . htmlspecialchars(implode(' / ', array_map(static fn(float $v): string => rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.') . '%', $schedule['percentages'])), ENT_QUOTES, 'UTF-8') . '</p>'];
    return parent::buildForm($form, $form_state);
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $projectId = (int) $form_state->get('project_id');
    $contract = $this->loadContract($projectId);
    $schedule = $this->commercialSchedule($projectId);
    if ($contract === [] || ($contract['status'] ?? '') === 'approved' || $schedule === NULL
      || !hash_equals((string) $form_state->get('contract_hash'), $this->contractHash($contract))
      || !hash_equals((string) $form_state->get('schedule_hash'), (string) $schedule['content_hash'])) {
      $this->messenger()->addError($this->t('Contract of termijnschema is gewijzigd sinds dit scherm werd geopend. Er is niets goedgekeurd; controleer de actuele gegevens opnieuw.'));
      $form_state->setRedirect('brebo_project_cockpit.contracts', ['node' => $projectId]);
      return;
    }

    $payload = json_encode([
      'version' => 1,
      'percentages' => $schedule['percentages'],
      'labels' => $schedule['labels'],
      'payment_term_days' => $schedule['payment_term_days'],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $now = time();
    $uid = (int) $this->currentUser()->id();
    $contractHash = hash('sha256', json_encode([
      'contract_number' => (string) $contract['contract_number'],
      'client_ref' => (string) ($contract['client_ref'] ?? ''),
      'contract_date' => (string) ($contract['contract_date'] ?? ''),
      'amount_ex_vat' => (string) $contract['amount_ex_vat'],
      'vat_amount' => (string) $contract['vat_amount'],
      'amount_inc_vat' => (string) $contract['amount_inc_vat'],
      'currency' => (string) $contract['currency'],
      'g_account_applicable' => (int) $contract['g_account_applicable'],
      'payment_term_days' => isset($contract['payment_term_days']) ? (int) $contract['payment_term_days'] : NULL,
      'instalment_schedule_content_hash' => (string) $schedule['content_hash'],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

    $this->database->update('brebo_finance_project_contract')->fields([
      'status' => 'approved',
      'content_hash' => $contractHash,
      'instalment_schedule_payload' => $payload,
      'instalment_schedule_source' => 'project_commercial_schedule',
      'instalment_schedule_source_ref' => (string) $projectId,
      'instalment_schedule_content_hash' => (string) $schedule['content_hash'],
      'approved' => $now,
      'approved_by' => $uid,
      'changed' => $now,
      'changed_by' => $uid,
    ])->condition('id', (int) $contract['id'])->condition('status', 'draft')->execute();

    $this->messenger()->addStatus($this->t('Projectcontract goedgekeurd. Contractwaarheid en commercieel termijnschema zijn bevroren.'));
    $form_state->setRedirect('brebo_project_cockpit.contracts', ['node' => $projectId]);
  }

  private function loadContract(int $projectId): array {
    $row = $this->database->select('brebo_finance_project_contract', 'c')->fields('c')->condition('project_nid', $projectId)->execute()->fetchAssoc();
    return is_array($row) ? $row : [];
  }

  /** @return array{content_hash:string,percentages:list<float>,labels:list<string>,payment_term_days:int}|null */
  private function commercialSchedule(int $projectId): ?array {
    if (!$this->database->schema()->tableExists('brebo_project_commercial_instalment_schedule')) return NULL;
    $row = $this->database->select('brebo_project_commercial_instalment_schedule', 's')->fields('s', ['schedule_payload', 'content_hash'])->condition('project_nid', $projectId)->execute()->fetchAssoc();
    if (!is_array($row) || (string) ($row['content_hash'] ?? '') === '') return NULL;
    $payload = json_decode((string) $row['schedule_payload'], TRUE);
    if (!is_array($payload)) return NULL;
    $percentages = array_values(array_map('floatval', $payload['percentages'] ?? []));
    $labels = array_values(array_map('strval', $payload['labels'] ?? []));
    if ($percentages === [] || abs(array_sum($percentages) - 100.0) > 0.001) return NULL;
    if ($labels === []) $labels = array_map(static fn(int $i): string => 'Termijn ' . ($i + 1), array_keys($percentages));
    if (count($labels) !== count($percentages)) return NULL;
    return ['content_hash' => (string) $row['content_hash'], 'percentages' => $percentages, 'labels' => $labels, 'payment_term_days' => max(0, (int) ($payload['payment_term_days'] ?? 14))];
  }

  private function contractHash(array $contract): string {
    $copy = $contract;
    unset($copy['changed'], $copy['changed_by']);
    return hash('sha256', json_encode($copy, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
  }

}
