<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Form;

use Drupal\brebo_calculation\Service\RecipeManager;
use Drupal\Core\Database\Connection;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/** Edits one placed recipe instance inside a calculation. */
final class RecipeInstanceEditForm extends FormBase {

  public function __construct(
    private readonly Connection $database,
    private readonly RecipeManager $recipeManager,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('database'),
      $container->get('brebo_calculation.recipe_manager'),
    );
  }

  public function getFormId(): string {
    return 'brebo_calculation_recipe_instance_edit_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, ?NodeInterface $node = NULL, ?int $recipe_instance = NULL): array {
    if (!$node instanceof NodeInterface || $node->bundle() !== 'brebo_calculation' || !$recipe_instance) {
      return ['message' => ['#markup' => '<p>Recept niet gevonden.</p>']];
    }

    $instance = $this->database->select('brebo_calculation_recipe_instance', 'i')
      ->fields('i')
      ->condition('id', $recipe_instance)
      ->condition('calculation_id', (int) $node->id())
      ->execute()
      ->fetchAssoc();
    if (!$instance) {
      return ['message' => ['#markup' => '<p>Recept niet gevonden in deze calculatie.</p>']];
    }

    $form['recipe_instance'] = ['#type' => 'hidden', '#value' => (int) $instance['id']];
    $form['heading'] = ['#markup' => '<div class="brebo-recipe-edit__heading"><strong>' . htmlspecialchars((string) $instance['name']) . '</strong><br><small>Versievaste snapshot in deze calculatie</small></div>'];
    $form['quantity'] = [
      '#type' => 'number',
      '#title' => $this->t('Recepthoeveelheid'),
      '#default_value' => (float) $instance['quantity'],
      '#step' => '0.0001',
      '#min' => 0,
      '#required' => TRUE,
    ];
    $form['unit'] = ['#markup' => '<p><strong>Eenheid:</strong> ' . htmlspecialchars((string) ($instance['unit'] ?? '')) . '</p>'];

    $parameters = $this->database->select('brebo_calculation_recipe_instance_parameter', 'p')
      ->fields('p')
      ->condition('recipe_instance_id', (int) $instance['id'])
      ->orderBy('id')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);
    if ($parameters) {
      $form['parameters'] = ['#type' => 'table', '#caption' => $this->t('Receptparameters'), '#header' => [$this->t('Parameter'), $this->t('Invoer'), $this->t('Berekend')]];
      foreach ($parameters as $parameter) {
        $key = (string) $parameter['parameter_key'];
        $form['parameters'][$key] = [
          'key' => ['#markup' => htmlspecialchars($key)],
          'value' => ['#markup' => htmlspecialchars((string) ($parameter['value'] ?? ''))],
          'calculated' => ['#markup' => htmlspecialchars((string) ($parameter['calculated_value'] ?? ''))],
        ];
      }
    }

    $lines = $this->database->select('brebo_calculation_recipe_instance_line', 'l')
      ->fields('l')
      ->condition('recipe_instance_id', (int) $instance['id'])
      ->orderBy('sort_order')
      ->orderBy('id')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);
    $form['lines'] = ['#type' => 'table', '#caption' => $this->t('Receptregels'), '#header' => [$this->t('Omschrijving'), $this->t('Type'), $this->t('Aantal'), $this->t('Eenheid'), $this->t('Prijs'), $this->t('Totaal')]];
    foreach ($lines as $line) {
      $quantity = $line['manual_quantity'] !== NULL && $line['manual_quantity'] !== '' ? (float) $line['manual_quantity'] : (float) $line['calculated_quantity'];
      $quantity *= 1 + ((float) $line['waste_pct'] / 100);
      $unitCost = (float) ($line['unit_cost'] ?? 0);
      $form['lines']['line_' . (int) $line['id']] = [
        'description' => ['#markup' => htmlspecialchars((string) $line['description']) . ((int) $line['is_custom'] === 1 ? ' <small>(eigen regel)</small>' : '')],
        'type' => ['#markup' => htmlspecialchars((string) $line['line_type'])],
        'quantity' => ['#markup' => number_format($quantity, 4, ',', '.')],
        'unit' => ['#markup' => htmlspecialchars((string) ($line['unit'] ?? ''))],
        'price' => ['#markup' => '€ ' . number_format($unitCost, 2, ',', '.')],
        'total' => ['#markup' => '<strong>€ ' . number_format($quantity * $unitCost, 2, ',', '.') . '</strong>'],
      ];
    }

    $form['custom_line'] = ['#type' => 'details', '#title' => $this->t('Regel toevoegen aan recept'), '#open' => FALSE];
    $form['custom_line']['description'] = ['#type' => 'textfield', '#title' => $this->t('Omschrijving')];
    $form['custom_line']['line_type'] = ['#type' => 'select', '#title' => $this->t('Kostensoort'), '#options' => ['material' => $this->t('Materiaal'), 'labour' => $this->t('Arbeid'), 'equipment' => $this->t('Materieel'), 'subcontracting' => $this->t('Onderaanneming'), 'other' => $this->t('Overig')]];
    $form['custom_line']['quantity'] = ['#type' => 'number', '#title' => $this->t('Aantal'), '#step' => '0.0001', '#min' => 0];
    $form['custom_line']['unit'] = ['#type' => 'textfield', '#title' => $this->t('Eenheid'), '#maxlength' => 32];
    $form['custom_line']['unit_cost'] = ['#type' => 'number', '#title' => $this->t('Eenheidsprijs'), '#step' => '0.0001', '#min' => 0];
    $form['custom_line']['add'] = ['#type' => 'submit', '#value' => $this->t('+ Regel toevoegen'), '#submit' => ['::addCustomLine'], '#limit_validation_errors' => [['custom_line'], ['recipe_instance']]];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['save'] = ['#type' => 'submit', '#value' => $this->t('Hoeveelheid opslaan en herberekenen'), '#button_type' => 'primary'];
    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $instanceId = (int) $form_state->getValue('recipe_instance');
    $quantity = (float) $form_state->getValue('quantity');
    $this->recipeManager->updateQuantity($instanceId, $quantity, $this->currentUser());
    $this->messenger()->addStatus($this->t('Recepthoeveelheid aangepast en onderliggende regels herberekend.'));
    $form_state->setRebuild(TRUE);
  }

  public function addCustomLine(array &$form, FormStateInterface $form_state): void {
    $instanceId = (int) $form_state->getValue('recipe_instance');
    $values = (array) $form_state->getValue('custom_line');
    $this->recipeManager->addCustomLine($instanceId, [
      'description' => (string) ($values['description'] ?? ''),
      'line_type' => (string) ($values['line_type'] ?? 'material'),
      'quantity' => (float) ($values['quantity'] ?? 0),
      'unit' => trim((string) ($values['unit'] ?? '')) ?: NULL,
      'unit_cost' => isset($values['unit_cost']) && $values['unit_cost'] !== '' ? (float) $values['unit_cost'] : NULL,
    ], $this->currentUser());
    $this->messenger()->addStatus($this->t('Regel aan recept toegevoegd.'));
    $form_state->setRebuild(TRUE);
  }

}
