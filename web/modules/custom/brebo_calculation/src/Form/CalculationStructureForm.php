<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Form;

use Drupal\brebo_calculation\Service\CalculationStructureManager;
use Drupal\Core\Database\Connection;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/** AJAX structure editor for calculation main groups and paragraphs. */
final class CalculationStructureForm extends FormBase {

  public function __construct(
    private readonly Connection $database,
    private readonly CalculationStructureManager $structureManager,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('database'),
      $container->get('brebo_calculation.structure_manager'),
    );
  }

  public function getFormId(): string {
    return 'brebo_calculation_structure_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, ?NodeInterface $node = NULL): array {
    if (!$node instanceof NodeInterface || $node->bundle() !== 'brebo_calculation') {
      return ['message' => ['#markup' => '<p>Calculatie niet gevonden.</p>']];
    }

    $version = $this->latestVersion((int) $node->id());
    if ($version === NULL) {
      return ['message' => ['#markup' => '<p>Deze calculatie heeft nog geen domeinversie.</p>']];
    }

    $editable = $version['status'] === 'draft'
      && $version['locked_at'] === NULL
      && $node->access('update')
      && $this->currentUser()->hasPermission('edit brebo calculation workbench');

    $form['#tree'] = TRUE;
    $form['#attached']['library'][] = 'brebo_calculation/workbench';
    $form['calculation_id'] = ['#type' => 'hidden', '#value' => (int) $node->id()];
    $form['version'] = ['#type' => 'hidden', '#value' => (string) $version['version']];

    $form['editor'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'brebo-calculation-structure-editor', 'class' => ['brebo-calc-structure-editor']],
    ];

    if ($message = $form_state->get('ajax_message')) {
      $form['editor']['message'] = [
        '#markup' => '<div class="messages messages--status">' . htmlspecialchars((string) $message) . '</div>',
      ];
    }

    $structure = $this->database->select('brebo_calculation_structure', 's')
      ->fields('s')
      ->condition('calculation_id', (int) $node->id())
      ->condition('version', $version['version'])
      ->orderBy('sort_order')
      ->orderBy('depth')
      ->execute()->fetchAll(\PDO::FETCH_ASSOC);

    $mainGroups = [];
    foreach ($structure as $item) {
      if ($item['node_type'] === 'main_group') {
        $mainGroups[(string) $item['node_key']] = trim((string) ($item['code'] ?: '') . ' — ' . (string) $item['label'], ' —');
      }
    }

    $form['editor']['create'] = [
      '#type' => 'details',
      '#title' => $this->t('Structuur toevoegen'),
      '#open' => TRUE,
      '#attributes' => ['class' => ['brebo-calc-structure-create']],
    ];
    $form['editor']['create']['main_group'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Nieuwe hoofdgroep'),
    ];
    $form['editor']['create']['main_group']['code'] = ['#type' => 'textfield', '#title' => $this->t('Code'), '#size' => 12, '#disabled' => !$editable];
    $form['editor']['create']['main_group']['label'] = ['#type' => 'textfield', '#title' => $this->t('Omschrijving'), '#size' => 40, '#disabled' => !$editable];
    $form['editor']['create']['main_group']['add'] = [
      '#type' => 'submit',
      '#value' => $this->t('+ Hoofdgroep'),
      '#submit' => ['::addMainGroup'],
      '#limit_validation_errors' => [['editor', 'create', 'main_group']],
      '#disabled' => !$editable,
      '#ajax' => $this->ajaxDefinition($this->t('Hoofdgroep toevoegen…')),
    ];

    $form['editor']['create']['paragraph'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Nieuwe paragraaf'),
    ];
    $form['editor']['create']['paragraph']['parent'] = ['#type' => 'select', '#title' => $this->t('Hoofdgroep'), '#options' => $mainGroups, '#empty_option' => $this->t('- kies -'), '#disabled' => !$editable];
    $form['editor']['create']['paragraph']['code'] = ['#type' => 'textfield', '#title' => $this->t('Code'), '#size' => 12, '#disabled' => !$editable];
    $form['editor']['create']['paragraph']['label'] = ['#type' => 'textfield', '#title' => $this->t('Omschrijving'), '#size' => 40, '#disabled' => !$editable];
    $form['editor']['create']['paragraph']['location_ref'] = ['#type' => 'textfield', '#title' => $this->t('Locatie'), '#size' => 24, '#description' => $this->t('Bijvoorbeeld building_zone:123'), '#disabled' => !$editable];
    $form['editor']['create']['paragraph']['add'] = [
      '#type' => 'submit',
      '#value' => $this->t('+ Paragraaf'),
      '#submit' => ['::addParagraph'],
      '#limit_validation_errors' => [['editor', 'create', 'paragraph']],
      '#disabled' => !$editable || !$mainGroups,
      '#ajax' => $this->ajaxDefinition($this->t('Paragraaf toevoegen…')),
    ];

    $form['editor']['structure'] = [
      '#type' => 'table',
      '#header' => [$this->t('Volgorde'), $this->t('Code'), $this->t('Omschrijving'), $this->t('Type'), $this->t('Locatie'), $this->t('Acties')],
      '#empty' => $this->t('Nog geen calculatiestructuur. Voeg eerst een hoofdgroep toe.'),
      '#attributes' => ['class' => ['brebo-calc-structure-table']],
    ];

    foreach ($structure as $item) {
      $key = (string) $item['node_key'];
      $rowKey = 'node_' . md5($key);
      $form['editor']['structure'][$rowKey] = [
        '#attributes' => [
          'class' => ['brebo-calc-structure-row', 'depth-' . (int) $item['depth']],
          'data-structure-key' => $key,
        ],
        'sort_order' => [
          '#type' => 'number',
          '#default_value' => (int) $item['sort_order'],
          '#step' => 10,
          '#disabled' => !$editable,
          '#attributes' => ['class' => ['brebo-calc-structure-sort']],
        ],
        'code' => ['#markup' => htmlspecialchars((string) ($item['code'] ?: '—'))],
        'label' => ['#markup' => str_repeat('&nbsp;&nbsp;&nbsp;', (int) $item['depth']) . '<strong>' . htmlspecialchars((string) $item['label']) . '</strong>'],
        'type' => ['#markup' => htmlspecialchars((string) $item['node_type'])],
        'location' => ['#markup' => htmlspecialchars((string) ($item['location_ref'] ?: '—'))],
        'operations' => [
          '#type' => 'container',
          '#attributes' => ['class' => ['brebo-calc-row-operations']],
          'up' => [
            '#type' => 'submit', '#value' => '↑', '#title' => $this->t('Omhoog'), '#submit' => ['::moveStructure'], '#node_key' => $key, '#delta' => -10,
            '#disabled' => !$editable, '#limit_validation_errors' => [], '#ajax' => $this->ajaxDefinition($this->t('Verplaatsen…')),
          ],
          'down' => [
            '#type' => 'submit', '#value' => '↓', '#title' => $this->t('Omlaag'), '#submit' => ['::moveStructure'], '#node_key' => $key, '#delta' => 10,
            '#disabled' => !$editable, '#limit_validation_errors' => [], '#ajax' => $this->ajaxDefinition($this->t('Verplaatsen…')),
          ],
        ],
      ];
    }

    $form['editor']['actions'] = ['#type' => 'actions'];
    $form['editor']['actions']['save_order'] = [
      '#type' => 'submit',
      '#value' => $this->t('Volgorde opslaan'),
      '#submit' => ['::saveOrder'],
      '#disabled' => !$editable,
      '#ajax' => $this->ajaxDefinition($this->t('Volgorde opslaan…')),
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {}

  public function addMainGroup(array &$form, FormStateInterface $form_state): void {
    $values = (array) $form_state->getValue(['editor', 'create', 'main_group']);
    $this->structureManager->addMainGroup(
      (int) $form_state->getValue('calculation_id'),
      (string) $form_state->getValue('version'),
      (string) ($values['code'] ?? ''),
      (string) ($values['label'] ?? ''),
      $this->currentUser(),
    );
    $form_state->set('ajax_message', 'Hoofdgroep toegevoegd.');
    $form_state->setRebuild(TRUE);
  }

  public function addParagraph(array &$form, FormStateInterface $form_state): void {
    $values = (array) $form_state->getValue(['editor', 'create', 'paragraph']);
    $this->structureManager->addParagraph(
      (int) $form_state->getValue('calculation_id'),
      (string) $form_state->getValue('version'),
      (string) ($values['parent'] ?? ''),
      (string) ($values['code'] ?? ''),
      (string) ($values['label'] ?? ''),
      trim((string) ($values['location_ref'] ?? '')) ?: NULL,
      $this->currentUser(),
    );
    $form_state->set('ajax_message', 'Paragraaf toegevoegd.');
    $form_state->setRebuild(TRUE);
  }

  public function saveOrder(array &$form, FormStateInterface $form_state): void {
    foreach ((array) $form_state->getValue(['editor', 'structure']) as $rowKey => $values) {
      if (!is_array($values) || !str_starts_with((string) $rowKey, 'node_')) {
        continue;
      }
      $element = $form['editor']['structure'][$rowKey] ?? NULL;
      $key = $element['#attributes']['data-structure-key'] ?? NULL;
      if (!is_string($key)) {
        continue;
      }
      $this->structureManager->reorder(
        (int) $form_state->getValue('calculation_id'),
        (string) $form_state->getValue('version'),
        $key,
        (int) ($values['sort_order'] ?? 0),
        $this->currentUser(),
      );
    }
    $form_state->set('ajax_message', 'Structuurvolgorde opgeslagen.');
    $form_state->setRebuild(TRUE);
  }

  public function moveStructure(array &$form, FormStateInterface $form_state): void {
    $trigger = $form_state->getTriggeringElement();
    $key = (string) ($trigger['#node_key'] ?? '');
    $delta = (int) ($trigger['#delta'] ?? 0);
    $current = $this->database->select('brebo_calculation_structure', 's')
      ->fields('s', ['sort_order'])
      ->condition('calculation_id', (int) $form_state->getValue('calculation_id'))
      ->condition('version', (string) $form_state->getValue('version'))
      ->condition('node_key', $key)
      ->execute()->fetchField();
    $this->structureManager->reorder(
      (int) $form_state->getValue('calculation_id'),
      (string) $form_state->getValue('version'),
      $key,
      (int) $current + $delta,
      $this->currentUser(),
    );
    $form_state->set('ajax_message', 'Structuur verplaatst.');
    $form_state->setRebuild(TRUE);
  }

  public function ajaxRefresh(array &$form, FormStateInterface $form_state): array {
    return $form['editor'];
  }

  /** @return array<string,mixed>|null */
  private function latestVersion(int $calculationId): ?array {
    $record = $this->database->select('brebo_calculation_version', 'v')
      ->fields('v')
      ->condition('calculation_id', $calculationId)
      ->orderBy('id', 'DESC')
      ->range(0, 1)
      ->execute()->fetchAssoc();
    return $record ?: NULL;
  }

  /** @return array<string,mixed> */
  private function ajaxDefinition(string $message): array {
    return [
      'callback' => '::ajaxRefresh',
      'wrapper' => 'brebo-calculation-structure-editor',
      'progress' => ['type' => 'throbber', 'message' => $message],
    ];
  }

}
