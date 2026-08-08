<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Creates a frozen work budget from an approved calculation.
 */
final class GenerateWorkBudgetForm extends ConfirmFormBase {

  private ?NodeInterface $calculation = NULL;

  public function getFormId(): string {
    return 'brebo_office_generate_work_budget';
  }

  public function getQuestion(): string {
    return (string) $this->t('Werkbegroting maken van @calculation?', [
      '@calculation' => $this->calculation?->label() ?? $this->t('deze calculatie'),
    ]);
  }

  public function getDescription(): string {
    return (string) $this->t('BREBO Office bevriest de uitvoeringsbasis en maakt per calculatieregel een werkbegrotingsregel. Verkoopprijzen, opslagen en marge worden niet naar de uitvoerdersregels gekopieerd.');
  }

  public function getConfirmText(): string {
    return (string) $this->t('Werkbegroting maken');
  }

  public function getCancelUrl(): Url {
    return $this->calculation
      ? Url::fromRoute('brebo_office_core.calculation_dashboard', ['node' => $this->calculation->id()])
      : Url::fromRoute('brebo_office_core.calculations');
  }

  public function buildForm(array $form, FormStateInterface $form_state, ?NodeInterface $node = NULL): array {
    if (!$node instanceof NodeInterface || $node->bundle() !== 'brebo_calculation') {
      throw new NotFoundHttpException();
    }
    if (!$node->access('update')) {
      throw new AccessDeniedHttpException();
    }

    $status = (string) $node->get('field_brebo_calc_status')->value;
    if (!in_array($status, ['Vastgesteld', 'Definitief budget'], TRUE)) {
      throw new AccessDeniedHttpException('Alleen een vastgestelde calculatie of definitief budget kan worden overgedragen.');
    }

    $this->calculation = $node;
    return parent::buildForm($form, $form_state);
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $calculation = $this->calculation;
    if (!$calculation instanceof NodeInterface) {
      return;
    }

    $storage = \Drupal::entityTypeManager()->getStorage('node');
    $version = (string) $calculation->get('field_brebo_calc_version')->value;
    $existing = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'brebo_work_budget')
      ->condition('field_brebo_calculation_ref.target_id', $calculation->id())
      ->condition('field_brebo_budget_version', $version)
      ->execute();

    if ($existing) {
      $budget = $storage->load(reset($existing));
      $this->messenger()->addWarning($this->t('Voor calculatieversie @version bestaat al een werkbegroting.', [
        '@version' => $version,
      ]));
      if ($budget instanceof NodeInterface) {
        $form_state->setRedirect('brebo_office_core.work_budget_dashboard', ['node' => $budget->id()]);
      }
      return;
    }

    $package = $calculation->get('field_brebo_package_ref')->entity;
    if (!$package instanceof NodeInterface || $package->bundle() !== 'brebo_work_package') {
      $this->messenger()->addError($this->t('De calculatie heeft geen geldig werkpakket.'));
      return;
    }

    $budget = $storage->create([
      'type' => 'brebo_work_budget',
      'title' => 'Werkbegroting ' . $calculation->label() . ' — v' . $version,
      'field_brebo_calculation_ref' => ['target_id' => $calculation->id()],
      'field_brebo_package_ref' => ['target_id' => $package->id()],
      'field_brebo_budget_version' => $version,
      'field_brebo_budget_status' => 'Bevroren',
      'field_brebo_baseline_date' => date('Y-m-d', \Drupal::time()->getCurrentTime()),
      'status' => 1,
    ]);
    $budget->setNewRevision(TRUE);
    $budget->setRevisionLogMessage('Automatisch gemaakt vanuit vastgestelde calculatie ' . $calculation->label() . '.');
    $budget->save();

    $element_ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'brebo_calc_element')
      ->condition('field_brebo_calculation_ref.target_id', $calculation->id())
      ->execute();

    $line_ids = [];
    if ($element_ids) {
      $line_ids = $storage->getQuery()
        ->accessCheck(FALSE)
        ->condition('type', 'brebo_calc_line')
        ->condition('field_brebo_calc_element_ref.target_id', array_values($element_ids), 'IN')
        ->sort('field_brebo_line_sequence')
        ->execute();
    }

    $created = 0;
    foreach ($storage->loadMultiple($line_ids) as $line) {
      if (!$line instanceof NodeInterface) {
        continue;
      }
      $post_type = (string) $line->get('field_brebo_line_post_type')->value;
      if (in_array($post_type, ['Optie', 'Alternatief'], TRUE)) {
        continue;
      }

      $category = (string) $line->get('field_brebo_cost_category')->value;
      $description = (string) $line->get('field_brebo_line_description')->value;
      $values = [
        'type' => 'brebo_work_budget_line',
        'title' => $description,
        'field_brebo_work_budget_ref' => ['target_id' => $budget->id()],
        'field_brebo_calc_line_ref' => ['target_id' => $line->id()],
        'field_brebo_budget_hours' => $line->get('field_brebo_budget_hours')->value,
        'field_brebo_actual_hours' => '0.0000',
        'field_brebo_execution_status' => 'Te plannen',
        'status' => 1,
      ];

      if ($category === 'Materiaal') {
        $values['field_brebo_material_description'] = $description;
        $values['field_brebo_material_quantity'] = $line->get('field_brebo_contract_quantity')->value;
        $values['field_brebo_material_unit'] = $line->get('field_brebo_unit')->value;
      }

      $work_line = $storage->create($values);
      $work_line->setNewRevision(TRUE);
      $work_line->setRevisionLogMessage('Automatisch overgenomen uit calculatieregel ' . $line->id() . '.');
      $work_line->save();
      $created++;
    }

    $this->messenger()->addStatus($this->t('Werkbegroting v@version is bevroren met @count uitvoeringsregels.', [
      '@version' => $version,
      '@count' => $created,
    ]));
    $form_state->setRedirect('brebo_office_core.work_budget_dashboard', ['node' => $budget->id()]);
  }

}
