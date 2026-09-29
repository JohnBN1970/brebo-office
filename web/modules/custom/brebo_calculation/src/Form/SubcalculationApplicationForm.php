<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Form;

use Drupal\brebo_calculation\Service\SubcalculationManager;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/** Manage concrete applications of one reusable subcalculation. */
final class SubcalculationApplicationForm extends FormBase {

  public function __construct(private readonly SubcalculationManager $manager) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('brebo_calculation.subcalculation_manager'));
  }

  public function getFormId(): string { return 'brebo_calculation_subcalculation_application_form'; }

  public function buildForm(array $form, FormStateInterface $form_state, ?int $calculation = NULL, ?int $subcalculation = NULL): array {
    $calculationId = (int) $calculation;
    if ($calculationId <= 0 || !$subcalculation) {
      throw new \InvalidArgumentException('Calculation and subcalculation expected.');
    }
    $sub = $this->manager->get($subcalculation);
    if (!$sub || (int) $sub['calculation_id'] !== $calculationId) {
      throw new \InvalidArgumentException('Subcalculation does not belong to this calculation.');
    }
    $totals = $this->manager->totals($subcalculation);
    $form['subcalculation_id'] = ['#type' => 'hidden', '#value' => $subcalculation];
    $form['summary'] = ['#markup' => '<div class="brebo-calc-workbench__meta"><span><strong>' . htmlspecialchars((string) $sub['label']) . '</strong></span><span><strong>Per ' . htmlspecialchars((string) ($sub['unit_label'] ?: 'eenheid')) . '</strong> € ' . number_format($totals['direct'], 2, ',', '.') . '</span></div>'];

    $applications = $this->manager->applications($subcalculation);
    $form['applications'] = ['#type' => 'table', '#header' => ['Toepassing', 'Object/context', 'Aantal/factor', 'Gekoppeld', 'Controle', 'Afwijkingen', 'Kostprijs / eenheid', 'Totaal', 'Status', 'Actie'], '#empty' => 'Nog geen toepassingen aangemaakt.', '#attributes' => ['class' => ['brebo-subcalculation-application-table']]];

    foreach ($applications as $application) {
      $id = (int) $application['id'];
      $objectStats = $this->manager->applicationObjectStats($id);
      $objectCount = $objectStats['object_count'];
      $factorSum = $objectStats['factor_sum'];
      $exceptionCount = $this->manager->exceptionObjectCount($id);
      $quantity = (float) $application['quantity'];
      $difference = $quantity - $factorSum;
      $usesObjects = in_array((string) $application['application_type'], ['object_set', 'location_set'], TRUE);
      if (!$usesObjects) {
        $control = 'Factor-toepassing';
      }
      elseif (abs($difference) < 0.0001) {
        $control = '✓ Compleet';
      }
      elseif ($difference > 0) {
        $control = '⚠ ' . number_format($difference, 4, ',', '.') . ' ontbreekt';
      }
      else {
        $control = '⚠ ' . number_format(abs($difference), 4, ',', '.') . ' teveel';
      }
      $form['applications']['application_' . $id] = [
        'type' => ['#markup' => htmlspecialchars((string) $application['application_type'])],
        'ref' => ['#markup' => htmlspecialchars((string) ($application['application_ref'] ?: '—'))],
        'quantity' => ['#markup' => number_format($quantity, 4, ',', '.')],
        'objects' => ['#markup' => $objectCount . ' object(en), factor ' . number_format($factorSum, 4, ',', '.')],
        'control' => ['#markup' => '<strong>' . htmlspecialchars($control) . '</strong>'],
        'exceptions' => ['#markup' => $exceptionCount ? '<strong>' . $exceptionCount . '</strong>' : '0'],
        'unit_cost' => ['#markup' => '€ ' . number_format($totals['direct'], 2, ',', '.')],
        'total' => ['#markup' => '<strong>€ ' . number_format($totals['direct'] * $quantity, 2, ',', '.') . '</strong>'],
        'status' => ['#markup' => htmlspecialchars((string) $application['status'])],
        'action' => ['#type' => 'link', '#title' => 'Objecten beheren', '#url' => Url::fromRoute('brebo_calculation.subcalculation_application_objects', ['calculation' => $calculationId, 'subcalculation' => $subcalculation, 'application' => $id])],
      ];
    }

    $form['add'] = ['#type' => 'details', '#title' => 'Toepassing toevoegen', '#open' => !$applications];
    $form['add']['application_type'] = ['#type' => 'select', '#title' => 'Soort toepassing', '#options' => ['object_set' => 'Set concrete objecten', 'location_set' => 'Set locaties', 'project_factor' => 'Projectfactor', 'manual' => 'Vrije toepassing'], '#default_value' => 'object_set'];
    $form['add']['application_ref'] = ['#type' => 'textfield', '#title' => 'Naam / referentie', '#required' => TRUE, '#description' => 'Bijvoorbeeld Gevelvakken G1, Woningtype A of Kozijnmodule K3.'];
    $form['add']['project_ref'] = ['#type' => 'textfield', '#title' => 'Projectreferentie'];
    $form['add']['quantity'] = ['#type' => 'number', '#title' => 'Aantal / vermenigvuldigingsfactor', '#default_value' => 1, '#min' => 0, '#step' => '0.0001', '#required' => TRUE];
    $form['add']['submit'] = ['#type' => 'submit', '#value' => 'Toepassing aanmaken', '#button_type' => 'primary'];
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['scope'] = ['#type' => 'link', '#title' => 'Scope samenstellen', '#url' => Url::fromRoute('brebo_calculation.subcalculation_detail', ['calculation' => $calculationId, 'subcalculation' => $subcalculation])];
    $form['actions']['back'] = ['#type' => 'link', '#title' => 'Terug naar deelcalculaties', '#url' => Url::fromRoute('brebo_calculation.subcalculations', ['calculation' => $calculationId])];
    $form['#attached']['library'][] = 'brebo_calculation/workbench';
    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $id = $this->manager->createApplication((int) $form_state->getValue('subcalculation_id'), (array) $form_state->getValue('add'), (int) $this->currentUser()->id());
    $this->messenger()->addStatus($this->t('Toepassing @id aangemaakt.', ['@id' => $id]));
    $form_state->setRebuild(TRUE);
  }
}
