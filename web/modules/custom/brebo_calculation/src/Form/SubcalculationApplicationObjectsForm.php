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

/** Manage concrete canonical objects for one subcalculation application. */
final class SubcalculationApplicationObjectsForm extends FormBase {

  public function __construct(
    private readonly Connection $database,
    private readonly SubcalculationManager $manager,
    private readonly ObjectExceptionLineManager $exceptionLineManager,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('database'),
      $container->get('brebo_calculation.subcalculation_manager'),
      $container->get('brebo_calculation.object_exception_line_manager'),
    );
  }

  public function getFormId(): string { return 'brebo_calculation_subcalculation_application_objects_form'; }

  public function buildForm(array $form, FormStateInterface $form_state, ?NodeInterface $node = NULL, ?int $subcalculation = NULL, ?int $application = NULL): array {
    if (!$node instanceof NodeInterface || !$subcalculation || !$application) {
      throw new \InvalidArgumentException('Calculation, subcalculation and application expected.');
    }
    $app = $this->database->select('brebo_calculation_subcalculation_application', 'a')->fields('a')->condition('id', $application)->condition('subcalculation_id', $subcalculation)->execute()->fetchAssoc();
    $sub = $this->database->select('brebo_calculation_subcalculation', 's')->fields('s')->condition('id', $subcalculation)->condition('calculation_id', (int) $node->id())->execute()->fetchAssoc();
    if (!$app || !$sub) {
      throw new \InvalidArgumentException('Application does not belong to this calculation.');
    }

    $unitTotals = $this->manager->totals($subcalculation);
    $applicationTotals = $this->manager->applicationTotals($application);
    $form['application_id'] = ['#type' => 'hidden', '#value' => $application];
    $form['summary'] = ['#markup' => '<div class="brebo-calc-workbench__meta"><span><strong>' . htmlspecialchars((string) $sub['label']) . '</strong></span><span>' . htmlspecialchars((string) ($app['application_ref'] ?: 'Toepassing ' . $application)) . '</span><span>Standaard: <strong>€ ' . number_format($applicationTotals['base'], 2, ',', '.') . '</strong></span><span>Legacy-afwijking: <strong>€ ' . number_format($applicationTotals['exceptions'], 2, ',', '.') . '</strong></span></div>'];

    $objects = $this->database->select('brebo_calculation_subcalculation_application_object', 'o')->fields('o')->condition('application_id', $application)->orderBy('object_type')->orderBy('object_ref')->execute()->fetchAllAssoc('id', \PDO::FETCH_ASSOC);
    $form['objects'] = ['#type' => 'table', '#header' => ['Object', 'Factor', 'Standaard', 'Afwijkingsregels', 'Legacy-afwijking', 'Objecttotaal', 'Toelichting', 'Actie'], '#empty' => 'Nog geen concrete objecten gekoppeld.'];
    foreach ($objects as $id => $object) {
      $factor = (float) $object['factor'];
      $base = $unitTotals['direct'] * $factor;
      $legacyException = ((float) $object['exception_labour'] + (float) $object['exception_material'] + (float) $object['exception_equipment'] + (float) $object['exception_subcontracting'] + (float) $object['exception_other']) * $factor;
      $lineTotals = $this->exceptionLineManager->objectLineTotals((int) $id);
      $lineException = $lineTotals['direct'] * $factor;
      $objectTotal = $base + $legacyException + $lineException;
      $form['objects']['object_' . $id] = [
        'object' => ['#markup' => '<strong>' . htmlspecialchars((string) $object['object_ref']) . '</strong><br><small>' . htmlspecialchars((string) $object['object_type']) . '</small>'],
        'factor' => ['#markup' => number_format($factor, 4, ',', '.')],
        'base' => ['#markup' => '€ ' . number_format($base, 2, ',', '.')],
        'line_exception' => ['#markup' => '<strong>€ ' . number_format($lineException, 2, ',', '.') . '</strong>'],
        'legacy_exception' => ['#markup' => '€ ' . number_format($legacyException, 2, ',', '.')],
        'total' => ['#markup' => '<strong>€ ' . number_format($objectTotal, 2, ',', '.') . '</strong>'],
        'note' => ['#markup' => htmlspecialchars((string) ($object['exception_payload'] ?: '—'))],
        'action' => ['#type' => 'link', '#title' => 'Afwijkingsregels', '#url' => Url::fromRoute('brebo_calculation.object_exception_lines', ['node' => $node->id(), 'subcalculation' => $subcalculation, 'application' => $application, 'object' => $id])],
      ];
    }

    $form['legacy_notice'] = ['#markup' => '<p><small>Nieuwe financiële afwijkingen worden als afzonderlijke, auditbare calculatieregels vastgelegd. De losse financiële afwijkingsvelden blijven tijdelijk beschikbaar voor bestaande data en worden als legacy-afwijking apart getoond.</small></p>'];

    $form['add'] = ['#type' => 'details', '#title' => 'Concreet object koppelen', '#open' => !$objects];
    $form['add']['object_type'] = ['#type' => 'select', '#title' => 'Objecttype', '#options' => ['dwelling' => 'Woning / verblijfsobject', 'facade_section' => 'Geveldeel / gevelvak', 'frame' => 'Kozijn / pui', 'balcony' => 'Balkon', 'gallery_section' => 'Galerijvak', 'roof_section' => 'Dakvlak / dakdeel', 'stairwell' => 'Trappenhuis', 'building_part' => 'Bouwdeel', 'location' => 'Locatie', 'other' => 'Ander canoniek object'], '#default_value' => 'facade_section'];
    $form['add']['object_ref'] = ['#type' => 'textfield', '#title' => 'Canonieke objectreferentie', '#required' => TRUE];
    $form['add']['factor'] = ['#type' => 'number', '#title' => 'Objectfactor', '#default_value' => 1, '#min' => 0, '#step' => '0.0001', '#required' => TRUE];
    $form['add']['is_exception'] = ['#type' => 'checkbox', '#title' => 'Dit object wijkt af van de standaard deelcalculatie'];
    $form['add']['exception_payload'] = ['#type' => 'textarea', '#title' => 'Afwijking / toelichting'];
    $form['add']['submit'] = ['#type' => 'submit', '#value' => 'Object koppelen', '#button_type' => 'primary'];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['back'] = ['#type' => 'link', '#title' => 'Terug naar toepassingen', '#url' => Url::fromRoute('brebo_calculation.subcalculation_applications', ['node' => $node->id(), 'subcalculation' => $subcalculation])];
    $form['#attached']['library'][] = 'brebo_calculation/workbench';
    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $values = (array) $form_state->getValue('add');
    $this->manager->addApplicationObject((int) $form_state->getValue('application_id'), (string) $values['object_type'], (string) $values['object_ref'], (float) $values['factor'], !empty($values['is_exception']), trim((string) ($values['exception_payload'] ?? '')) ?: NULL, $this->currentUser());
    $this->messenger()->addStatus('Concreet object aan de toepassing gekoppeld. Voeg financiële afwijkingen toe via Afwijkingsregels.');
    $form_state->setRebuild(TRUE);
  }
}
