<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Form;

use Drupal\brebo_calculation\Service\ObjectExceptionLineManager;
use Drupal\brebo_calculation\Service\SubcalculationManager;
use Drupal\Core\Database\Connection;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/** Workbench for auditable calculation lines on one object deviation. */
final class ObjectExceptionLinesForm extends FormBase {

  public function __construct(private readonly Connection $database, private readonly ObjectExceptionLineManager $lineManager, private readonly SubcalculationManager $subcalculationManager) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('database'), $container->get('brebo_calculation.object_exception_line_manager'), $container->get('brebo_calculation.subcalculation_manager'));
  }

  public function getFormId(): string { return 'brebo_calculation_object_exception_lines_form'; }

  public function buildForm(array $form, FormStateInterface $form_state, ?NodeInterface $node = NULL, ?int $subcalculation = NULL, ?int $application = NULL, ?int $object = NULL): array {
    if (!$node instanceof NodeInterface || !$subcalculation || !$application || !$object) {
      throw new \InvalidArgumentException('Calculation context expected.');
    }
    $query = $this->database->select('brebo_calculation_subcalculation_application_object', 'o');
    $query->join('brebo_calculation_subcalculation_application', 'a', 'a.id = o.application_id');
    $query->join('brebo_calculation_subcalculation', 's', 's.id = a.subcalculation_id');
    $query->fields('o');
    $query->addField('a', 'subcalculation_id');
    $query->addField('s', 'calculation_id');
    $context = $query->condition('o.id', $object)->condition('o.application_id', $application)->condition('a.subcalculation_id', $subcalculation)->condition('s.calculation_id', (int) $node->id())->execute()->fetchAssoc();
    if (!$context) { throw new \InvalidArgumentException('Object does not belong to this calculation context.'); }

    $unit = $this->subcalculationManager->totals($subcalculation);
    $factor = (float) $context['factor'];
    $lineTotals = $this->lineManager->objectLineTotals($object);
    $base = $unit['direct'] * $factor;
    $exception = $lineTotals['direct'] * $factor;
    $form['object_id'] = ['#type' => 'hidden', '#value' => $object];
    $form['summary'] = ['#markup' => '<div class="brebo-calc-workbench__meta"><span><strong>' . htmlspecialchars((string) $context['object_ref']) . '</strong></span><span>Factor <strong>' . number_format($factor, 4, ',', '.') . '</strong></span><span>Standaard <strong>€ ' . number_format($base, 2, ',', '.') . '</strong></span><span>Afwijkingsregels <strong>€ ' . number_format($exception, 2, ',', '.') . '</strong></span><span>Objecttotaal <strong>€ ' . number_format($base + $exception, 2, ',', '.') . '</strong></span></div>'];

    $lines = $this->database->select('brebo_calculation_subcalculation_object_exception_line', 'l')->fields('l')->condition('application_object_id', $object)->orderBy('sort_order')->orderBy('id')->execute()->fetchAll(\PDO::FETCH_ASSOC);
    $form['lines'] = ['#type' => 'table', '#header' => ['Code', 'Omschrijving', 'Hoeveelheid', 'Arbeid', 'Materiaal', 'Materieel', 'OA', 'Overig', 'Regeltotaal', 'Prijsbron'], '#empty' => 'Nog geen afwijkingsregels.'];
    foreach ($lines as $line) {
      $q = (float) $line['quantity'];
      $carriers = [];
      foreach (['labour', 'material', 'equipment', 'subcontracting', 'other'] as $carrier) { $carriers[$carrier] = $q * (float) $line[$carrier . '_unit_cost']; }
      $total = array_sum($carriers);
      $form['lines']['line_' . $line['id']] = [
        'code' => ['#markup' => htmlspecialchars((string) ($line['code'] ?: '—'))],
        'description' => ['#markup' => '<strong>' . htmlspecialchars((string) $line['description']) . '</strong>'],
        'quantity' => ['#markup' => number_format($q, 4, ',', '.') . ' ' . htmlspecialchars((string) ($line['unit'] ?: ''))],
        'labour' => ['#markup' => '€ ' . number_format($carriers['labour'], 2, ',', '.')],
        'material' => ['#markup' => '€ ' . number_format($carriers['material'], 2, ',', '.')],
        'equipment' => ['#markup' => '€ ' . number_format($carriers['equipment'], 2, ',', '.')],
        'subcontracting' => ['#markup' => '€ ' . number_format($carriers['subcontracting'], 2, ',', '.')],
        'other' => ['#markup' => '€ ' . number_format($carriers['other'], 2, ',', '.')],
        'total' => ['#markup' => '<strong>€ ' . number_format($total, 2, ',', '.') . '</strong>'],
        'source' => ['#markup' => htmlspecialchars((string) ($line['price_source_ref'] ?: '—'))],
      ];
    }

    $form['add'] = ['#type' => 'details', '#title' => 'Afwijkingsregel toevoegen', '#open' => !$lines];
    $form['add']['code'] = ['#type' => 'textfield', '#title' => 'Code', '#size' => 16];
    $form['add']['description'] = ['#type' => 'textfield', '#title' => 'Omschrijving', '#required' => TRUE];
    $form['add']['quantity'] = ['#type' => 'number', '#title' => 'Hoeveelheid', '#default_value' => 1, '#min' => 0, '#step' => '0.0001', '#required' => TRUE];
    $form['add']['unit'] = ['#type' => 'textfield', '#title' => 'Eenheid', '#size' => 12];
    foreach (['labour_unit_cost' => 'Arbeid / eenheid', 'material_unit_cost' => 'Materiaal / eenheid', 'equipment_unit_cost' => 'Materieel / eenheid', 'subcontracting_unit_cost' => 'Onderaanneming / eenheid', 'other_unit_cost' => 'Overig / eenheid'] as $key => $label) {
      $form['add'][$key] = ['#type' => 'number', '#title' => $label, '#default_value' => 0, '#min' => 0, '#step' => '0.01', '#field_prefix' => '€ '];
    }
    $form['add']['price_source_ref'] = ['#type' => 'textfield', '#title' => 'Prijsbron', '#description' => 'Bijvoorbeeld offerte, e-mail, prijslijst of interne bronreferentie.'];
    $form['add']['note'] = ['#type' => 'textarea', '#title' => 'Interne notitie'];
    $form['add']['submit'] = ['#type' => 'submit', '#value' => 'Afwijkingsregel toevoegen', '#button_type' => 'primary'];
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['back'] = ['#type' => 'link', '#title' => 'Terug naar concrete objecten', '#url' => Url::fromRoute('brebo_calculation.subcalculation_application_objects', ['node' => $node->id(), 'subcalculation' => $subcalculation, 'application' => $application])];
    $form['#attached']['library'][] = 'brebo_calculation/workbench';
    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->lineManager->addLine((int) $form_state->getValue('object_id'), (array) $form_state->getValue('add'), $this->currentUser());
    $this->messenger()->addStatus('Afwijkingsregel toegevoegd en doorgerekend.');
    $form_state->setRebuild(TRUE);
  }
}
