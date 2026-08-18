<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Form;

use Drupal\brebo_calculation\Service\RecipeManager;
use Drupal\brebo_calculation\Service\RecipeMaterialSelector;
use Drupal\Core\Database\Connection;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/** Edits one placed recipe instance inside a calculation. */
final class RecipeInstanceEditForm extends FormBase {
  public function __construct(private readonly Connection $database, private readonly RecipeManager $recipeManager, private readonly RecipeMaterialSelector $materialSelector) {}
  public static function create(ContainerInterface $container): static { return new static($container->get('database'), $container->get('brebo_calculation.recipe_manager'), $container->get('brebo_calculation.recipe_material_selector')); }
  public function getFormId(): string { return 'brebo_calculation_recipe_instance_edit_form'; }

  public function buildForm(array $form, FormStateInterface $form_state, ?NodeInterface $node = NULL, ?int $recipe_instance = NULL): array {
    if (!$node instanceof NodeInterface || $node->bundle() !== 'brebo_calculation' || !$recipe_instance) return ['message' => ['#markup' => '<p>Recept niet gevonden.</p>']];
    $instance = $this->database->select('brebo_calculation_recipe_instance', 'i')->fields('i')->condition('id', $recipe_instance)->condition('calculation_id', (int) $node->id())->execute()->fetchAssoc();
    if (!$instance) return ['message' => ['#markup' => '<p>Recept niet gevonden in deze calculatie.</p>']];

    $form['#tree'] = TRUE;
    $form['#attached']['library'][] = 'brebo_article/article-picker';
    $form['recipe_instance'] = ['#type' => 'hidden', '#value' => (int) $instance['id']];
    $form['heading'] = ['#markup' => '<div class="brebo-recipe-edit__heading"><strong>' . htmlspecialchars((string) $instance['name']) . '</strong><br><small>Versievaste snapshot in deze calculatie</small></div>'];
    $form['quantity'] = ['#type' => 'number', '#title' => $this->t('Recepthoeveelheid'), '#default_value' => (float) $instance['quantity'], '#step' => '0.0001', '#min' => 0, '#required' => TRUE];
    $form['unit'] = ['#markup' => '<p><strong>Eenheid:</strong> ' . htmlspecialchars((string) ($instance['unit'] ?? '')) . '</p>'];

    $parameters = $this->database->select('brebo_calculation_recipe_instance_parameter', 'p')->fields('p')->condition('recipe_instance_id', (int) $instance['id'])->orderBy('id')->execute()->fetchAll(\PDO::FETCH_ASSOC);
    if ($parameters) {
      $form['parameters'] = ['#type' => 'table', '#caption' => $this->t('Receptparameters'), '#header' => [$this->t('Parameter'), $this->t('Invoer'), $this->t('Berekend')]];
      foreach ($parameters as $parameter) {
        $key = (string) $parameter['parameter_key'];
        $form['parameters'][$key] = [
          'key' => ['#markup' => htmlspecialchars($key)],
          'value' => ['#type' => 'number', '#default_value' => (string) ($parameter['value'] ?? ''), '#step' => '0.0001', '#attributes' => ['aria-label' => $this->t('Waarde voor @parameter', ['@parameter' => $key])]],
          'calculated' => ['#markup' => '<strong>' . htmlspecialchars((string) ($parameter['calculated_value'] ?? '')) . '</strong>'],
        ];
      }
    }

    $lines = $this->database->select('brebo_calculation_recipe_instance_line', 'l')->fields('l')->condition('recipe_instance_id', (int) $instance['id'])->orderBy('sort_order')->orderBy('id')->execute()->fetchAll(\PDO::FETCH_ASSOC);
    $form['lines'] = ['#type' => 'table', '#caption' => $this->t('Receptregels'), '#header' => [$this->t('Omschrijving / artikel'), $this->t('Type'), $this->t('Aantal'), $this->t('Eenheid'), $this->t('Prijs'), $this->t('Totaal'), $this->t('Actie')]];
    foreach ($lines as $line) {
      $lineId = (int) $line['id'];
      $quantity = ($line['manual_quantity'] !== NULL && $line['manual_quantity'] !== '') ? (float) $line['manual_quantity'] : (float) $line['calculated_quantity'];
      $quantity *= 1 + ((float) $line['waste_pct'] / 100);
      $unitCost = (float) ($line['unit_cost'] ?? 0);
      $isMaterial = in_array(strtolower((string) $line['line_type']), ['material', 'materiaal'], TRUE);
      $selected = $isMaterial ? $this->materialSelector->selectedArticle($lineId) : NULL;
      $row = ['#attributes' => ['class' => $isMaterial ? ['brebo-calc-ingredient-row'] : []], 'description' => ['#markup' => htmlspecialchars((string) $line['description'])], 'type' => ['#markup' => htmlspecialchars((string) $line['line_type'])], 'quantity' => ['#markup' => number_format($quantity, 4, ',', '.')], 'unit' => ['#markup' => htmlspecialchars((string) ($line['unit'] ?? ''))], 'price' => ['#markup' => '€ ' . number_format($unitCost, 2, ',', '.')], 'total' => ['#markup' => '<strong>€ ' . number_format($quantity * $unitCost, 2, ',', '.') . '</strong>'], 'action' => ['#markup' => '']];
      if ($isMaterial) {
        $row['description'] = ['#type' => 'container', 'text' => ['#markup' => '<div><strong>' . htmlspecialchars((string) $line['description']) . '</strong>' . ($selected ? '<br><small>Prijsdatum: ' . htmlspecialchars((string) $selected['price_date']) . '</small>' : '<br><small>Nog geen artikel uit de centrale artikelstam gekozen.</small>') . '</div>'], 'picker' => ['#type' => 'button', '#value' => $selected ? $this->t('Ander artikel kiezen') : $this->t('Artikel kiezen'), '#attributes' => ['data-brebo-article-picker' => '1', 'class' => ['button', 'button--small']]], 'article_id' => ['#type' => 'hidden', '#default_value' => $selected['article_id'] ?? ''], 'supplier_article_id' => ['#type' => 'hidden', '#default_value' => $selected['supplier_article_id'] ?? ''], 'price_id' => ['#type' => 'hidden', '#default_value' => $selected['price_id'] ?? ''], 'catalog_import_id' => ['#type' => 'hidden', '#default_value' => $selected['catalog_import_id'] ?? ''], 'article_code' => ['#type' => 'hidden'], 'supplier_name' => ['#type' => 'hidden'], 'supplier_article_no' => ['#type' => 'hidden'], 'price_date' => ['#type' => 'hidden', '#default_value' => $selected['price_date'] ?? ''], 'category' => ['#type' => 'hidden', '#default_value' => 'Materiaal'], 'description' => ['#type' => 'hidden', '#default_value' => (string) $line['description']], 'unit' => ['#type' => 'hidden', '#default_value' => (string) ($line['unit'] ?? '')], 'unit_price' => ['#type' => 'hidden', '#default_value' => $unitCost]];
        $row['action'] = ['#type' => 'submit', '#value' => $this->t('Artikel opslaan'), '#submit' => ['::selectMaterial'], '#recipe_line_id' => $lineId, '#attributes' => ['data-brebo-article-save' => '1', 'class' => ['visually-hidden']], '#limit_validation_errors' => [['lines', 'line_' . $lineId], ['recipe_instance']]];
      }
      $form['lines']['line_' . $lineId] = $row;
    }

    $form['custom_line'] = ['#type' => 'details', '#title' => $this->t('Regel toevoegen aan recept'), '#open' => FALSE, '#attributes' => ['class' => ['brebo-calc-ingredient-row']]];
    $form['custom_line']['line_type'] = ['#type' => 'select', '#title' => $this->t('Kostensoort'), '#options' => ['material' => $this->t('Materiaal'), 'labour' => $this->t('Arbeid'), 'equipment' => $this->t('Materieel'), 'subcontracting' => $this->t('Onderaanneming'), 'other' => $this->t('Overig')], '#default_value' => 'material'];
    $form['custom_line']['picker'] = ['#type' => 'button', '#value' => $this->t('Materiaal uit artikelstam kiezen'), '#attributes' => ['data-brebo-article-picker' => '1', 'class' => ['button', 'button--small']]];
    $form['custom_line']['description'] = ['#type' => 'textfield', '#title' => $this->t('Omschrijving')];
    $form['custom_line']['quantity'] = ['#type' => 'number', '#title' => $this->t('Aantal'), '#step' => '0.0001', '#min' => 0];
    $form['custom_line']['unit'] = ['#type' => 'textfield', '#title' => $this->t('Eenheid'), '#maxlength' => 32];
    $form['custom_line']['unit_cost'] = ['#type' => 'number', '#title' => $this->t('Eenheidsprijs'), '#step' => '0.0001', '#min' => 0];
    foreach (['article_id', 'supplier_article_id', 'price_id', 'catalog_import_id', 'article_code', 'supplier_name', 'supplier_article_no', 'price_date', 'category'] as $hidden) { $form['custom_line'][$hidden] = ['#type' => 'hidden', '#default_value' => $hidden === 'category' ? 'Materiaal' : '']; }
    $form['custom_line']['add'] = ['#type' => 'submit', '#value' => $this->t('+ Regel toevoegen'), '#submit' => ['::addCustomLine'], '#limit_validation_errors' => [['custom_line'], ['recipe_instance']]];
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['save'] = ['#type' => 'submit', '#value' => $this->t('Recept opslaan en herberekenen'), '#button_type' => 'primary'];
    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $instanceId = (int) $form_state->getValue('recipe_instance');
    $this->recipeManager->updateQuantity($instanceId, (float) $form_state->getValue('quantity'), $this->currentUser());
    $parameterRows = (array) $form_state->getValue('parameters');
    $parameterValues = [];
    foreach ($parameterRows as $key => $row) { if (is_array($row) && array_key_exists('value', $row)) $parameterValues[(string) $key] = (string) $row['value']; }
    if ($parameterValues) $this->recipeManager->updateParameters($instanceId, $parameterValues, $this->currentUser());
    $this->messenger()->addStatus($this->t('Receptparameters en hoeveelheid opgeslagen; onderliggende regels zijn herberekend.'));
    $form_state->setRebuild(TRUE);
  }

  public function selectMaterial(array &$form, FormStateInterface $form_state): void { $trigger = $form_state->getTriggeringElement(); $lineId = (int) ($trigger['#recipe_line_id'] ?? 0); $values = (array) $form_state->getValue(['lines', 'line_' . $lineId, 'description']); $this->materialSelector->select($lineId, ['article_id' => $values['article_id'] ?? NULL, 'supplier_article_id' => $values['supplier_article_id'] ?? NULL, 'price_id' => $values['price_id'] ?? NULL, 'catalog_import_id' => $values['catalog_import_id'] ?? NULL], $this->currentUser()); $this->messenger()->addStatus($this->t('Artikel en prijs aan receptregel gekoppeld.')); $form_state->setRebuild(TRUE); }

  public function addCustomLine(array &$form, FormStateInterface $form_state): void {
    $instanceId = (int) $form_state->getValue('recipe_instance');
    $values = (array) $form_state->getValue('custom_line');
    $lineType = (string) ($values['line_type'] ?? 'material');
    $lineId = $this->recipeManager->addCustomLine($instanceId, ['description' => (string) ($values['description'] ?? ''), 'line_type' => $lineType, 'quantity' => (float) ($values['quantity'] ?? 0), 'unit' => trim((string) ($values['unit'] ?? '')) ?: NULL, 'unit_cost' => isset($values['unit_cost']) && $values['unit_cost'] !== '' ? (float) $values['unit_cost'] : NULL], $this->currentUser());
    if (in_array(strtolower($lineType), ['material', 'materiaal'], TRUE) && !empty($values['article_id']) && !empty($values['supplier_article_id']) && !empty($values['price_id']) && !empty($values['catalog_import_id'])) { $this->materialSelector->select($lineId, ['article_id' => $values['article_id'], 'supplier_article_id' => $values['supplier_article_id'], 'price_id' => $values['price_id'], 'catalog_import_id' => $values['catalog_import_id']], $this->currentUser()); }
    $this->messenger()->addStatus($this->t('Regel aan recept toegevoegd.'));
    $form_state->setRebuild(TRUE);
  }
}
