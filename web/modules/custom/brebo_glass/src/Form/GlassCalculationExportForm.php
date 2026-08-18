<?php

declare(strict_types=1);

namespace Drupal\brebo_glass\Form;

use Drupal\brebo_glass\Service\GlassCalculationExporter;
use Drupal\brebo_glass\Service\GlassPositionRepository;
use Drupal\Core\Database\Connection;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/** Sends an approved glass position to an editable calculation paragraph. */
final class GlassCalculationExportForm extends FormBase {

  private int $positionId = 0;

  public function __construct(
    private readonly Connection $database,
    private readonly GlassPositionRepository $positions,
    private readonly GlassCalculationExporter $exporter,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('database'),
      $container->get('brebo_glass.position_repository'),
      $container->get('brebo_glass.calculation_exporter'),
    );
  }

  public function getFormId(): string {
    return 'brebo_glass_calculation_export_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, ?int $position_id = NULL): array {
    $this->positionId = (int) $position_id;
    $position = $this->positions->find($this->positionId);
    if (!$position) {
      throw new \InvalidArgumentException('Glaspositie bestaat niet.');
    }
    if ((string) $position['technical_status'] !== 'approved') {
      $form['blocked'] = ['#markup' => '<p>Deze glaspositie moet eerst technisch worden vrijgegeven.</p>'];
      return $form;
    }

    $options = $this->paragraphOptions();
    $form['position'] = [
      '#type' => 'item',
      '#title' => $this->t('Glaspositie'),
      '#markup' => $this->t('@code — @composition — @qty st.', [
        '@code' => (string) $position['position_code'],
        '@composition' => (string) $position['composition'],
        '@qty' => (int) $position['quantity'],
      ]),
    ];
    $form['target'] = [
      '#type' => 'select',
      '#title' => $this->t('Conceptcalculatie en paragraaf'),
      '#options' => $options,
      '#empty_option' => $this->t('- Kies calculatieparagraaf -'),
      '#required' => TRUE,
      '#description' => $this->t('Alleen ontgrendelde conceptversies en bestaande paragrafen zijn beschikbaar.'),
    ];
    if (!$options) {
      $form['target']['#disabled'] = TRUE;
      $form['warning'] = ['#markup' => '<p>Er is geen open conceptcalculatie met een paragraaf beschikbaar.</p>'];
      return $form;
    }
    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Doorzetten naar calculatie'),
      '#button_type' => 'primary',
    ];
    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $target = (string) $form_state->getValue('target');
    $parts = explode('|', $target, 3);
    if (count($parts) !== 3) {
      $this->messenger()->addError($this->t('Ongeldige calculatiekeuze.'));
      return;
    }
    [$calculationId, $version, $paragraphKey] = $parts;
    try {
      $ids = $this->exporter->export(
        $this->positionId,
        (int) $calculationId,
        $version,
        $paragraphKey,
        $this->currentUser(),
      );
      $this->messenger()->addStatus($this->formatPlural(count($ids), '1 calculatieregel aangemaakt.', '@count calculatieregels aangemaakt.'));
      $form_state->setRedirect('brebo_glass.position_overview');
    }
    catch (\Throwable $e) {
      $this->messenger()->addError($e->getMessage());
    }
  }

  /** @return array<string, string> */
  private function paragraphOptions(): array {
    $query = $this->database->select('brebo_calculation_structure', 's');
    $query->innerJoin('brebo_calculation_version', 'v', 'v.calculation_id = s.calculation_id AND v.version = s.version');
    $query->addField('s', 'calculation_id');
    $query->addField('s', 'version');
    $query->addField('s', 'node_key');
    $query->addField('s', 'code');
    $query->addField('s', 'label');
    $query->condition('s.node_type', 'paragraph');
    $query->condition('v.status', 'draft');
    $query->isNull('v.locked_at');
    $query->orderBy('s.calculation_id')->orderBy('s.sort_order');

    $options = [];
    foreach ($query->execute() as $row) {
      $key = $row->calculation_id . '|' . $row->version . '|' . $row->node_key;
      $label = trim((string) $row->code . ' ' . (string) $row->label);
      $options[$key] = sprintf('Calculatie %d · %s · %s', $row->calculation_id, $row->version, $label);
    }
    return $options;
  }

}
