<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Spreadsheet-like inline editor for calculation lines.
 */
final class CalculationGridForm extends FormBase {

  /**
   * Constructs the inline calculation editor.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static($container->get('entity_type.manager'));
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'brebo_office_calculation_grid_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?NodeInterface $node = NULL): array {
    if (!$node instanceof NodeInterface || $node->bundle() !== 'brebo_calculation') {
      return ['message' => ['#markup' => '<p>Calculatie niet gevonden.</p>']];
    }
    if (!$node->access('update')) {
      return ['message' => ['#markup' => '<p>U heeft geen recht om deze calculatie te wijzigen.</p>']];
    }

    $storage = $this->entityTypeManager->getStorage('node');
    $element_ids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'brebo_calc_element')
      ->condition('field_brebo_calculation_ref.target_id', $node->id())
      ->sort('field_brebo_element_sequence')
      ->execute();
    $elements = $storage->loadMultiple($element_ids);

    $component_ids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'brebo_calc_component')
      ->condition('field_brebo_calculation_ref.target_id', $node->id())
      ->sort('field_brebo_component_sequence')
      ->execute();
    $components = $storage->loadMultiple($component_ids);

    $zone_ids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'brebo_building_zone')
      ->sort('field_brebo_zone_code')
      ->execute();
    $zones = $storage->loadMultiple($zone_ids);

    $line_ids = $element_ids
      ? $storage->getQuery()
        ->accessCheck(TRUE)
        ->condition('type', 'brebo_calc_line')
        ->condition('field_brebo_calc_element_ref.target_id', array_values($element_ids), 'IN')
        ->execute()
      : [];
    $lines = $storage->loadMultiple($line_ids);
    $lines_by_element = [];
    foreach ($lines as $line) {
      if ($line instanceof NodeInterface) {
        $lines_by_element[(int) $line->get('field_brebo_calc_element_ref')->target_id][] = $line;
      }
    }
    foreach ($lines_by_element as &$recipe_lines) {
      uasort($recipe_lines, static function (NodeInterface $left, NodeInterface $right): int {
        return (int) ($left->get('field_brebo_line_sequence')->value ?? 0)
          <=> (int) ($right->get('field_brebo_line_sequence')->value ?? 0);
      });
    }
    unset($recipe_lines);

    $element_options = [];
    foreach ($elements as $element) {
      if ($element instanceof NodeInterface) {
        $code = (string) ($element->get('field_brebo_element_code')->value ?? '');
        $element_options[(int) $element->id()] = trim($code . ' — ' . $element->label(), ' —');
      }
    }
    $component_options = [];
    foreach ($components as $component) {
      if ($component instanceof NodeInterface) {
        $code = (string) ($component->get('field_brebo_component_code')->value ?? '');
        $component_options[(int) $component->id()] = trim($code . ' — ' . $component->label(), ' —');
      }
    }
    $zone_options = [0 => $this->t('- Nog geen technische zone -')];
    foreach ($zones as $zone) {
      if ($zone instanceof NodeInterface) {
        $code = (string) ($zone->get('field_brebo_zone_code')->value ?? '');
        $zone_options[(int) $zone->id()] = trim($code . ' — ' . $zone->label(), ' —');
      }
    }

    $form['calculation_id'] = ['#type' => 'hidden', '#value' => $node->id()];
    $form['calculation_version_token'] = [
      '#type' => 'hidden',
      '#value' => $this->calculationVersionToken((int) $node->id()),
    ];
    $form['intro'] = [
      '#markup' => '<p class="brebo-calc-grid__intro"><strong>Receptenwerkbank:</strong> ieder recept levert een resultaat op een technische zone; de werkregels zijn de ingrediënten.</p>',
    ];

    $form['structure_actions'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['brebo-calc-structure-actions']],
    ];
    $form['structure_actions']['ingredient'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['brebo-calc-quick-add']],
    ];
    $form['structure_actions']['ingredient']['new_line_recipe'] = [
      '#type' => 'select',
      '#title' => $this->t('Ingrediënt toevoegen aan recept'),
      '#options' => $element_options,
      '#empty_option' => $this->t('- Kies recept -'),
    ];
    $form['structure_actions']['ingredient']['add_line'] = [
      '#type' => 'submit',
      '#value' => $this->t('Werkregel toevoegen'),
      '#submit' => ['::addIngredient'],
      '#limit_validation_errors' => [['structure_actions', 'ingredient', 'new_line_recipe'], ['calculation_id']],
      '#button_type' => 'primary',
      '#disabled' => !$element_options,
    ];

    $form['structure_actions']['recipe'] = [
      '#type' => 'details',
      '#title' => $this->t('Nieuw recept toevoegen'),
      '#attributes' => ['class' => ['brebo-calc-new-recipe']],
    ];
    $form['structure_actions']['recipe']['component'] = [
      '#type' => 'select',
      '#title' => $this->t('NLSfB-hoofdcomponent'),
      '#options' => $component_options,
      '#empty_option' => $this->t('- Kies hoofdcomponent -'),
    ];
    $form['structure_actions']['recipe']['code'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Receptcode'),
      '#maxlength' => 64,
      '#placeholder' => '41.15.10',
    ];
    $form['structure_actions']['recipe']['description'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Recepthoofdregel'),
      '#maxlength' => 255,
      '#placeholder' => $this->t('Bijvoorbeeld schilderwerk bestaand kozijn'),
    ];
    $form['structure_actions']['recipe']['zone'] = [
      '#type' => 'select',
      '#title' => $this->t('Technische zone'),
      '#options' => $zone_options,
    ];
    $form['structure_actions']['recipe']['quantity'] = [
      '#type' => 'number',
      '#title' => $this->t('Recepthoeveelheid'),
      '#default_value' => '1.0000',
      '#step' => '0.0001',
      '#min' => 0,
    ];
    $form['structure_actions']['recipe']['unit'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Recepteenheid'),
      '#default_value' => 'post',
      '#maxlength' => 16,
    ];
    $form['structure_actions']['recipe']['add_recipe'] = [
      '#type' => 'submit',
      '#value' => $this->t('Recept toevoegen'),
      '#submit' => ['::addRecipe'],
      '#limit_validation_errors' => [['structure_actions', 'recipe'], ['calculation_id']],
      '#button_type' => 'primary',
      '#disabled' => !$component_options,
    ];

    $form['grid'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Volgorde'),
        $this->t('Recept'),
        $this->t('Status'),
        $this->t('Omschrijving'),
        $this->t('Posttype'),
        $this->t('Kostensoort'),
        $this->t('Aantal'),
        $this->t('Werkelijk'),
        $this->t('Eenheid'),
        $this->t('Urenbasis'),
        $this->t('Norm/u'),
        $this->t('Totaaluren'),
        $this->t('Uurtarief'),
        $this->t('Eenheidsprijs'),
        $this->t('Contract'),
        $this->t('Prognose'),
      ],
      '#attributes' => ['class' => ['brebo-calc-grid', 'brebo-calc-recipe-grid']],
      '#empty' => $this->t('Nog geen calculatierecepten.'),
    ];

    $component_bands = [];
    $next_component_band = 0;
    foreach ($elements as $element) {
      if (!$element instanceof NodeInterface || !$element->access('update')) {
        continue;
      }
      $element_id = (int) $element->id();
      $component = $element->get('field_brebo_calc_component_ref')->entity;
      $component_code = $component instanceof NodeInterface
        ? (string) $component->get('field_brebo_component_code')->value
        : '—';
      if (!array_key_exists($component_code, $component_bands)) {
        $component_bands[$component_code] = $next_component_band++;
      }
      $zone = $element->hasField('field_brebo_technical_zone_ref')
        ? $element->get('field_brebo_technical_zone_ref')->entity
        : NULL;
      $zone_label = $zone instanceof NodeInterface
        ? trim((string) $zone->get('field_brebo_zone_code')->value . ' — ' . $zone->label(), ' —')
        : (string) $this->t('Zone nog te bepalen');
      $recipe_quantity = (float) ($element->get('field_brebo_recipe_quantity')->value ?? 0);
      $recipe_unit = (string) ($element->get('field_brebo_recipe_unit')->value ?? 'post');
      $recipe_contract = 0.0;
      $recipe_forecast = 0.0;
      $recipe_hours = 0.0;
      foreach ($lines_by_element[$element_id] ?? [] as $recipe_line) {
        $quantity = (float) ($recipe_line->get('field_brebo_contract_quantity')->value ?? 0);
        $actual = $recipe_line->get('field_brebo_actual_quantity')->value;
        $unit_price = (float) ($recipe_line->get('field_brebo_unit_price')->value ?? 0);
        $post_type = (string) ($recipe_line->get('field_brebo_line_post_type')->value ?? 'Vaste post');
        $forecast_quantity = $post_type === 'Verrekenpost' && $actual !== NULL && $actual !== ''
          ? (float) $actual
          : $quantity;
        $recipe_contract += $quantity * $unit_price;
        $recipe_forecast += $forecast_quantity * $unit_price;
        $recipe_hours += (float) ($recipe_line->get('field_brebo_budget_hours')->value ?? 0);
      }
      $recipe_unit_price = $recipe_quantity > 0 ? $recipe_contract / $recipe_quantity : 0.0;
      $recipe_key = 'recipe_' . $element_id;
      $form['grid'][$recipe_key]['heading'] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['brebo-recipe-heading'],
          'data-recipe-heading' => $element_id,
        ],
        '#wrapper_attributes' => ['colspan' => 16],
        'identity' => [
          '#type' => 'container',
          '#attributes' => ['class' => ['brebo-recipe-heading__identity']],
          'toggle' => [
            '#type' => 'html_tag',
            '#tag' => 'button',
            '#value' => '▾',
            '#attributes' => [
              'type' => 'button',
              'class' => ['brebo-recipe-toggle'],
              'data-recipe-toggle' => $element_id,
              'aria-expanded' => 'true',
              'aria-label' => $this->t('Ingrediënten in- of uitklappen'),
            ],
          ],
          'code' => [
            '#markup' => '<span class="brebo-recipe-heading__code"><small>NLSfB ' . htmlspecialchars($component_code) . '</small><strong>'
              . htmlspecialchars((string) $element->get('field_brebo_element_code')->value) . '</strong></span>',
          ],
          'title' => [
            '#markup' => '<span class="brebo-recipe-heading__title"><small>Recepthoofdregel</small><strong>'
              . htmlspecialchars((string) $element->label()) . '</strong></span>',
          ],
        ],
        'zone' => [
          '#type' => 'select',
          '#title' => $this->t('Technische zone'),
          '#default_value' => $zone instanceof NodeInterface ? (int) $zone->id() : 0,
          '#options' => $zone_options,
        ],
        'quantity' => [
          '#type' => 'number',
          '#title' => $this->t('Recepthoeveelheid'),
          '#default_value' => $recipe_quantity,
          '#step' => '0.0001',
          '#min' => 0,
        ],
        'unit' => [
          '#type' => 'textfield',
          '#title' => $this->t('Eenheid'),
          '#default_value' => $recipe_unit,
          '#maxlength' => 16,
        ],
        'hours' => [
          '#markup' => '<span><small>Uren</small><strong data-recipe-hours="' . $element_id . '">' . number_format($recipe_hours, 2, ',', '.') . '</strong></span>',
        ],
        'price' => [
          '#markup' => '<span><small>Kostprijs/recept</small><strong data-recipe-price="' . $element_id . '">€ ' . number_format($recipe_unit_price, 2, ',', '.') . '</strong></span>',
        ],
        'total' => [
          '#markup' => '<span><small>Totaal</small><strong data-recipe-total="' . $element_id . '">€ ' . number_format($recipe_contract, 2, ',', '.') . '</strong></span>',
        ],
      ];
      $form['grid'][$recipe_key]['#attributes']['data-recipe-header'] = $element_id;
      $form['grid'][$recipe_key]['#attributes']['class'] = [
        'brebo-recipe-row',
        'brebo-calc-row--nlsfb-' . ($component_bands[$component_code] % 2 === 0 ? 'even' : 'odd'),
      ];

      foreach ($lines_by_element[$element_id] ?? [] as $line) {
        if (!$line instanceof NodeInterface || !$line->access('update')) {
          continue;
        }
        $id = (int) $line->id();
        $quantity = (float) ($line->get('field_brebo_contract_quantity')->value ?? 0);
        $actual = $line->get('field_brebo_actual_quantity')->value;
        $unit_price = (float) ($line->get('field_brebo_unit_price')->value ?? 0);
        $post_type = (string) ($line->get('field_brebo_line_post_type')->value ?? 'Vaste post');
        $forecast_quantity = $post_type === 'Verrekenpost' && $actual !== NULL && $actual !== ''
          ? (float) $actual
          : $quantity;

        $form['grid'][$id]['sequence'] = $this->input('number', $line, 'field_brebo_line_sequence', ['#step' => 10, '#min' => 0]);
        $form['grid'][$id]['recipe'] = [
          '#type' => 'select',
          '#title' => '',
          '#title_display' => 'invisible',
          '#default_value' => $element_id,
          '#options' => $element_options,
        ];
        $line_status = (string) ($line->get('field_brebo_line_status')->value ?? 'Niet beoordeeld');
        $form['grid'][$id]['status'] = [
          '#type' => 'select',
          '#title' => '',
          '#title_display' => 'invisible',
          '#default_value' => $line_status,
          '#options' => [
            'Niet beoordeeld' => $this->t('⚪ Niet beoordeeld'),
            'Groen' => $this->t('🟢 Gereed'),
            'Oranje' => $this->t('🟠 Aandacht'),
            'Rood' => $this->t('🔴 Geblokkeerd'),
            'N.v.t.' => $this->t('⚫ N.v.t.'),
          ],
          '#attributes' => ['class' => ['brebo-line-status']],
        ];
        $form['grid'][$id]['description'] = [
          '#type' => 'container',
          '#attributes' => ['class' => ['brebo-calculation-description']],
          'input' => $this->input('textfield', $line, 'field_brebo_line_description', [
            '#size' => 34,
            '#parents' => ['grid', $id, 'description'],
          ]),
        ];
        if (\Drupal::moduleHandler()->moduleExists('brebo_article')) {
          $snapshot = \Drupal::database()->select('brebo_calculation_article_snapshot', 'snapshot')
            ->fields('snapshot')
            ->condition('calculation_line_id', $id)
            ->execute()
            ->fetchAssoc() ?: [];
          $article_label = $snapshot
            ? (string) $snapshot['article_code'] . ' · ' . (string) $snapshot['supplier_name']
            : (string) $this->t('Artikel zoeken');

          foreach ([
            'article_id', 'supplier_article_id', 'price_id', 'catalog_import_id',
            'article_code', 'supplier_name', 'supplier_article_no', 'price_date',
          ] as $snapshot_key) {
            $form['grid'][$id]['description'][$snapshot_key] = [
              '#type' => 'hidden',
              '#default_value' => (string) ($snapshot[$snapshot_key] ?? ''),
              '#parents' => ['grid', $id, $snapshot_key],
            ];
          }
          $form['grid'][$id]['description']['article_picker'] = [
            '#type' => 'html_tag',
            '#tag' => 'button',
            '#value' => $article_label,
            '#attributes' => [
              'type' => 'button',
              'class' => array_values(array_filter([
                'brebo-article-select',
                $snapshot ? 'has-article' : NULL,
              ])),
              'data-brebo-article-picker' => '',
              'aria-label' => $this->t('Artikel zoeken voor deze calculatieregel'),
            ],
          ];
        }
        $form['grid'][$id]['post_type'] = $this->select($line, 'field_brebo_line_post_type', [
          'Vaste post', 'Stelpost', 'Verrekenpost', 'Optie', 'Alternatief', 'Meer-/minderwerk',
        ]);
        $form['grid'][$id]['category'] = $this->select($line, 'field_brebo_cost_category', [
          'Arbeid', 'Materiaal', 'Materieel', 'Onderaanneming', 'Overig',
        ]);
        $form['grid'][$id]['quantity'] = $this->input('number', $line, 'field_brebo_contract_quantity');
        $form['grid'][$id]['actual'] = $this->input('number', $line, 'field_brebo_actual_quantity', ['#required' => FALSE]);
        $form['grid'][$id]['unit'] = $this->input('textfield', $line, 'field_brebo_unit', ['#size' => 6]);
        $form['grid'][$id]['hours_mode'] = $this->select($line, 'field_brebo_hours_input_mode', ['Normuren', 'Totaaluren']);
        $form['grid'][$id]['norm_hours'] = $this->input('number', $line, 'field_brebo_norm_hours', ['#required' => FALSE]);
        $form['grid'][$id]['budget_hours'] = $this->input('number', $line, 'field_brebo_budget_hours', ['#required' => FALSE]);
        $form['grid'][$id]['labor_rate'] = $this->input('number', $line, 'field_brebo_labor_rate', ['#required' => FALSE]);
        $form['grid'][$id]['unit_price'] = $this->input('number', $line, 'field_brebo_unit_price');
        $form['grid'][$id]['contract'] = ['#markup' => '<span class="brebo-calc-grid__money" data-live-contract>€ ' . number_format($quantity * $unit_price, 2, ',', '.') . '</span>'];
        $form['grid'][$id]['forecast'] = ['#markup' => '<span class="brebo-calc-grid__money" data-live-forecast>€ ' . number_format($forecast_quantity * $unit_price, 2, ',', '.') . '</span>'];
        $form['grid'][$id]['#attributes']['data-recipe-id'] = $element_id;
        $form['grid'][$id]['#attributes']['data-line-status'] = strtolower(str_replace([' ', '.'], '-', $line_status));
        $form['grid'][$id]['#attributes']['class'] = [
          'brebo-calc-row',
          'brebo-calc-ingredient-row',
          'brebo-calc-row--nlsfb-' . ($component_bands[$component_code] % 2 === 0 ? 'even' : 'odd'),
          'brebo-calc-row--' . strtolower(str_replace([' ', '.'], '-', $post_type)),
        ];
      }
    }

    $form['actions'] = ['#type' => 'actions', '#attributes' => ['class' => ['brebo-calc-grid__actions']]];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Alle gewijzigde ingrediënten opslaan'),
      '#button_type' => 'primary',
    ];
    $form['#attributes']['class'][] = 'brebo-calc-grid-form';
    $form['#attached']['library'][] = 'brebo_office/calculation-grid';
    if (\Drupal::moduleHandler()->moduleExists('brebo_article')) {
      $form['#attached']['library'][] = 'brebo_article/article-picker';
    }
    $form['#cache']['max-age'] = 0;
    return $form;
  }

  /**
   * Builds a compact inline input.
   */
  private function input(string $type, NodeInterface $line, string $field, array $extra = []): array {
    $value = $line->hasField($field) ? $line->get($field)->value : NULL;
    return $extra + [
      '#type' => $type,
      '#title' => '',
      '#title_display' => 'invisible',
      '#default_value' => $value,
      '#required' => in_array($field, [
        'field_brebo_line_description',
        'field_brebo_contract_quantity',
        'field_brebo_unit',
        'field_brebo_unit_price',
      ], TRUE),
      '#step' => $type === 'number' ? '0.0001' : NULL,
      '#min' => $type === 'number' ? 0 : NULL,
    ];
  }

  /**
   * Builds a compact inline select.
   */
  private function select(NodeInterface $line, string $field, array $options): array {
    $current = $line->hasField($field) ? (string) $line->get($field)->value : (string) reset($options);
    return [
      '#type' => 'select',
      '#title' => '',
      '#title_display' => 'invisible',
      '#default_value' => $current,
      '#options' => array_combine($options, $options),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $calculation_id = (int) $form_state->getValue('calculation_id');
    $submitted_token = (string) $form_state->getValue('calculation_version_token');
    if ($calculation_id > 0
      && $submitted_token !== ''
      && !hash_equals($submitted_token, $this->calculationVersionToken($calculation_id))) {
      $form_state->setErrorByName(
        'calculation_version_token',
        $this->t('Deze calculatie is na het openen door iemand anders gewijzigd. Uw invoer is niet opgeslagen. Vernieuw de pagina en controleer de nieuwste versie.')
      );
      return;
    }

    foreach ($form_state->getValue('grid', []) as $id => $values) {
      if ((float) ($values['quantity'] ?? 0) < 0) {
        $form_state->setErrorByName("grid][$id][quantity", $this->t('Hoeveelheid mag niet negatief zijn.'));
      }
      if (($values['hours_mode'] ?? 'Normuren') === 'Totaaluren'
        && (float) ($values['quantity'] ?? 0) <= 0
        && (float) ($values['budget_hours'] ?? 0) > 0) {
        $form_state->setErrorByName("grid][$id][quantity", $this->t('Voor terugrekening van totaaluren is een hoeveelheid groter dan nul nodig.'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $calculation_id = (int) $form_state->getValue('calculation_id');
    $storage = $this->entityTypeManager->getStorage('node');
    $changed = 0;

    foreach ($form_state->getValue('grid', []) as $line_id => $values) {
      if (str_starts_with((string) $line_id, 'recipe_')) {
        $recipe_id = (int) substr((string) $line_id, 7);
        $recipe = $storage->load($recipe_id);
        $heading = $values['heading'] ?? [];
        if ($recipe instanceof NodeInterface
          && $recipe->bundle() === 'brebo_calc_element'
          && (int) $recipe->get('field_brebo_calculation_ref')->target_id === $calculation_id
          && $recipe->access('update')) {
          $zone_id = (int) ($heading['zone'] ?? 0);
          $quantity = (string) ($heading['quantity'] ?? '0');
          $unit = trim((string) ($heading['unit'] ?? 'post')) ?: 'post';
          $current_zone_id = (int) ($recipe->get('field_brebo_technical_zone_ref')->target_id ?? 0);
          $recipe_dirty = $current_zone_id !== $zone_id
            || (string) $recipe->get('field_brebo_recipe_quantity')->value !== $quantity
            || (string) $recipe->get('field_brebo_recipe_unit')->value !== $unit;
          if ($recipe_dirty) {
            $recipe->set('field_brebo_technical_zone_ref', $zone_id > 0 ? ['target_id' => $zone_id] : NULL);
            $recipe->set('field_brebo_recipe_quantity', $quantity);
            $recipe->set('field_brebo_recipe_unit', $unit);
            $recipe->setNewRevision(TRUE);
            $recipe->setRevisionLogMessage('Receptlocatie en hoeveelheid vanuit de calculatiewerkbank bijgewerkt.');
            $recipe->save();
            $changed++;
          }
        }
        continue;
      }

      $line = $storage->load((int) $line_id);
      if (!$line instanceof NodeInterface || $line->bundle() !== 'brebo_calc_line' || !$line->access('update')) {
        continue;
      }
      $element = $line->get('field_brebo_calc_element_ref')->entity;
      if (!$element instanceof NodeInterface
        || (int) $element->get('field_brebo_calculation_ref')->target_id !== $calculation_id) {
        continue;
      }

      $target_recipe_id = (int) ($values['recipe'] ?? $element->id());
      $target_recipe = $storage->load($target_recipe_id);
      if (!$target_recipe instanceof NodeInterface
        || $target_recipe->bundle() !== 'brebo_calc_element'
        || (int) $target_recipe->get('field_brebo_calculation_ref')->target_id !== $calculation_id) {
        $target_recipe_id = (int) $element->id();
      }

      $mapping = [
        'sequence' => 'field_brebo_line_sequence',
        'recipe' => 'field_brebo_calc_element_ref',
        'status' => 'field_brebo_line_status',
        'description' => 'field_brebo_line_description',
        'post_type' => 'field_brebo_line_post_type',
        'category' => 'field_brebo_cost_category',
        'quantity' => 'field_brebo_contract_quantity',
        'actual' => 'field_brebo_actual_quantity',
        'unit' => 'field_brebo_unit',
        'hours_mode' => 'field_brebo_hours_input_mode',
        'norm_hours' => 'field_brebo_norm_hours',
        'budget_hours' => 'field_brebo_budget_hours',
        'labor_rate' => 'field_brebo_labor_rate',
        'unit_price' => 'field_brebo_unit_price',
      ];
      $dirty = FALSE;
      foreach ($mapping as $key => $field_name) {
        $new_value = $key === 'recipe' ? $target_recipe_id : ($values[$key] ?? NULL);
        if ($new_value === '') {
          $new_value = NULL;
        }
        $old_value = $line->get($field_name)->value;
        if ((string) $old_value !== (string) $new_value) {
          $line->set($field_name, $new_value);
          $dirty = TRUE;
        }
      }
      if ($dirty) {
        $line->setNewRevision(TRUE);
        $line->setRevisionLogMessage('Calculatieregel inline bijgewerkt vanuit de calculatiewerkbank.');
        $line->save();
        $changed++;
      }

      // Validate the selected identifiers against the article database and
      // preserve an immutable price snapshot for this calculation line.
      $article_id = (int) ($values['article_id'] ?? 0);
      $supplier_article_id = (int) ($values['supplier_article_id'] ?? 0);
      $price_id = (int) ($values['price_id'] ?? 0);
      if (\Drupal::moduleHandler()->moduleExists('brebo_article')
        && $article_id > 0 && $supplier_article_id > 0 && $price_id > 0) {
        $database = \Drupal::database();
        $article_query = $database->select('brebo_supplier_article', 'sa');
        $article_query->join('brebo_article', 'a', 'a.id = sa.article_id');
        $article_query->join('brebo_supplier', 's', 's.id = sa.supplier_id');
        $article_query->join('brebo_article_price', 'p', 'p.supplier_article_id = sa.id');
        $article_query->fields('a', ['id', 'code', 'description', 'base_unit']);
        $article_query->addField('sa', 'id', 'supplier_article_id');
        $article_query->fields('sa', ['supplier_article_no', 'use_unit']);
        $article_query->addField('s', 'name', 'supplier_name');
        $article_query->addField('p', 'id', 'price_id');
        $article_query->fields('p', ['net_price', 'valid_from', 'catalog_import_id']);
        $article_query->condition('a.id', $article_id);
        $article_query->condition('sa.id', $supplier_article_id);
        $article_query->condition('p.id', $price_id);
        if ($selected = $article_query->execute()->fetchAssoc()) {
          $database->merge('brebo_calculation_article_snapshot')
            ->key(['calculation_line_id' => (int) $line->id()])
            ->fields([
              'article_id' => (int) $selected['id'],
              'supplier_article_id' => (int) $selected['supplier_article_id'],
              'price_id' => (int) $selected['price_id'],
              'article_code' => (string) $selected['code'],
              'supplier_name' => (string) $selected['supplier_name'],
              'supplier_article_no' => (string) $selected['supplier_article_no'],
              'description' => (string) $selected['description'],
              'unit' => (string) ($selected['use_unit'] ?: $selected['base_unit']),
              'unit_price' => (string) $selected['net_price'],
              'price_date' => (string) $selected['valid_from'],
              'catalog_import_id' => (int) $selected['catalog_import_id'],
              'selected_by' => (int) $this->currentUser()->id(),
              'selected_at' => \Drupal::time()->getRequestTime(),
            ])
            ->execute();
        }
      }
    }

    $this->messenger()->addStatus($this->formatPlural(
      $changed,
      '1 calculatieonderdeel opgeslagen.',
      '@count calculatieonderdelen opgeslagen.',
    ));
    $form_state->setRedirect('brebo_office_core.calculation_dashboard', ['node' => $calculation_id], ['fragment' => 'tab-calc-lines']);
  }


  /**
   * Creates a stable version token for the complete editable calculation.
   */
  private function calculationVersionToken(int $calculation_id): string {
    $storage = $this->entityTypeManager->getStorage('node');
    $entity_ids = [$calculation_id];

    $component_ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'brebo_calc_component')
      ->condition('field_brebo_calculation_ref.target_id', $calculation_id)
      ->execute();
    $element_ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'brebo_calc_element')
      ->condition('field_brebo_calculation_ref.target_id', $calculation_id)
      ->execute();
    $line_ids = $element_ids
      ? $storage->getQuery()
        ->accessCheck(FALSE)
        ->condition('type', 'brebo_calc_line')
        ->condition('field_brebo_calc_element_ref.target_id', array_values($element_ids), 'IN')
        ->execute()
      : [];

    $entity_ids = array_values(array_unique(array_map('intval', array_merge(
      $entity_ids,
      array_values($component_ids),
      array_values($element_ids),
      array_values($line_ids),
    ))));
    sort($entity_ids, SORT_NUMERIC);

    $versions = [];
    foreach ($storage->loadMultiple($entity_ids) as $entity) {
      if ($entity instanceof NodeInterface) {
        $versions[(int) $entity->id()] = $entity->getChangedTime();
      }
    }
    ksort($versions, SORT_NUMERIC);

    return hash('sha256', json_encode($versions, JSON_THROW_ON_ERROR));
  }


  /**
   * Adds a blank ingredient to the selected recipe.
   */
  public function addIngredient(array &$form, FormStateInterface $form_state): void {
    $calculation_id = (int) $form_state->getValue('calculation_id');
    $recipe_id = (int) $form_state->getValue([
      'structure_actions',
      'ingredient',
      'new_line_recipe',
    ]);
    $storage = $this->entityTypeManager->getStorage('node');
    $recipe = $storage->load($recipe_id);
    if (!$recipe instanceof NodeInterface
      || $recipe->bundle() !== 'brebo_calc_element'
      || (int) $recipe->get('field_brebo_calculation_ref')->target_id !== $calculation_id) {
      $this->messenger()->addError($this->t('Kies eerst een geldig recept.'));
      $form_state->setRebuild();
      return;
    }

    $last_ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'brebo_calc_line')
      ->condition('field_brebo_calc_element_ref.target_id', $recipe_id)
      ->sort('field_brebo_line_sequence', 'DESC')
      ->range(0, 1)
      ->execute();
    $last = $last_ids ? $storage->load(reset($last_ids)) : NULL;
    $sequence = $last instanceof NodeInterface
      ? (int) ($last->get('field_brebo_line_sequence')->value ?? 0) + 10
      : 10;

    $line = $storage->create([
      'type' => 'brebo_calc_line',
      'title' => 'Nieuwe werkregel',
      'status' => 1,
      'uid' => $this->currentUser()->id(),
      'field_brebo_calc_element_ref' => ['target_id' => $recipe_id],
      'field_brebo_line_sequence' => $sequence,
      'field_brebo_line_post_type' => 'Vaste post',
      'field_brebo_cost_category' => 'Overig',
      'field_brebo_line_description' => 'Nieuwe werkregel',
      'field_brebo_contract_quantity' => '1.0000',
      'field_brebo_unit' => 'post',
      'field_brebo_unit_price' => '0.0000',
      'field_brebo_hours_input_mode' => 'Normuren',
      'field_brebo_line_status' => 'Niet beoordeeld',
    ]);
    $line->save();

    $this->messenger()->addStatus($this->t('Nieuwe werkregel aan @recipe toegevoegd.', [
      '@recipe' => $recipe->label(),
    ]));
    $form_state->setRedirect('brebo_office_core.calculation_dashboard', ['node' => $calculation_id], ['fragment' => 'tab-calc-lines']);
  }

  /**
   * Adds a zone-bound recipe header to the calculation.
   */
  public function addRecipe(array &$form, FormStateInterface $form_state): void {
    $calculation_id = (int) $form_state->getValue('calculation_id');
    $values = $form_state->getValue(['structure_actions', 'recipe'], []);
    $component_id = (int) ($values['component'] ?? 0);
    $code = trim((string) ($values['code'] ?? ''));
    $description = trim((string) ($values['description'] ?? ''));
    $storage = $this->entityTypeManager->getStorage('node');
    $component = $storage->load($component_id);

    if (!$component instanceof NodeInterface
      || $component->bundle() !== 'brebo_calc_component'
      || (int) $component->get('field_brebo_calculation_ref')->target_id !== $calculation_id
      || $code === ''
      || $description === '') {
      $this->messenger()->addError($this->t('Kies een hoofdcomponent en vul receptcode en recepthoofdregel in.'));
      $form_state->setRebuild();
      return;
    }

    $last_ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'brebo_calc_element')
      ->condition('field_brebo_calculation_ref.target_id', $calculation_id)
      ->sort('field_brebo_element_sequence', 'DESC')
      ->range(0, 1)
      ->execute();
    $last = $last_ids ? $storage->load(reset($last_ids)) : NULL;
    $sequence = $last instanceof NodeInterface
      ? (int) ($last->get('field_brebo_element_sequence')->value ?? 0) + 10
      : 10;

    $recipe_values = [
      'type' => 'brebo_calc_element',
      'title' => $description,
      'status' => 1,
      'uid' => $this->currentUser()->id(),
      'field_brebo_calculation_ref' => ['target_id' => $calculation_id],
      'field_brebo_calc_component_ref' => ['target_id' => $component_id],
      'field_brebo_element_code' => $code,
      'field_brebo_element_sequence' => $sequence,
      'field_brebo_element_scope' => $description,
      'field_brebo_recipe_quantity' => (string) ($values['quantity'] ?? '1.0000'),
      'field_brebo_recipe_unit' => trim((string) ($values['unit'] ?? 'post')) ?: 'post',
    ];
    $zone_id = (int) ($values['zone'] ?? 0);
    if ($zone_id > 0) {
      $zone = $storage->load($zone_id);
      if ($zone instanceof NodeInterface && $zone->bundle() === 'brebo_building_zone') {
        $recipe_values['field_brebo_technical_zone_ref'] = ['target_id' => $zone_id];
      }
    }

    $recipe = $storage->create($recipe_values);
    $recipe->save();

    $this->messenger()->addStatus($this->t('Recept @recipe toegevoegd.', [
      '@recipe' => $description,
    ]));
    $form_state->setRedirect('brebo_office_core.calculation_dashboard', ['node' => $calculation_id], ['fragment' => 'tab-calc-lines']);
  }

}
