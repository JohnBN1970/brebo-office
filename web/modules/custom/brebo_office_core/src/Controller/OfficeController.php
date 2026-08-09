<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\user\Form\UserLoginForm;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Provides BREBO Office dashboard and object overviews.
 */
final class OfficeController extends ControllerBase {

  /**
   * Returns a login form for guests and the dashboard shell for users.
   */
  public function dashboard(): array {
    if ($this->currentUser()->isAnonymous()) {
      return $this->formBuilder()->getForm(UserLoginForm::class);
    }

    return [
      '#markup' => '',
      '#cache' => [
        'contexts' => ['user.roles:authenticated'],
        'tags' => ['node_list'],
      ],
    ];
  }

  /**
   * Returns a compact overview for one BREBO object type.
   */
  public function objectList(string $bundle): array {
    $storage = $this->entityTypeManager()->getStorage('node');
    $ids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', $bundle)
      ->sort('changed', 'DESC')
      ->pager(25)
      ->execute();

    $rows = [];
    foreach ($storage->loadMultiple($ids) as $node) {
      if (!$node instanceof NodeInterface) {
        continue;
      }
      $status_field = match ($bundle) {
        'brebo_verification' => 'field_brebo_control_result',
        'brebo_deviation' => 'field_brebo_deviation_status',
        'brebo_work_package' => 'field_brebo_package_status',
        'brebo_release_gate' => 'field_brebo_gate_result',
        'brebo_calculation' => 'field_brebo_calc_status',
        'brebo_work_budget' => 'field_brebo_budget_status',
        'brebo_rfq' => 'field_brebo_rfq_status',
        'brebo_supplier_quote' => 'field_brebo_quote_status',
        'brebo_budget_change' => 'field_brebo_change_status',
        'brebo_route_item' => 'field_brebo_route_status',
        default => 'field_brebo_status',
      };
      $status = $this->fieldValue($node, $status_field);
      $view_url = match ($bundle) {
        'brebo_building' => Url::fromRoute('brebo_office_core.building_dashboard', ['node' => $node->id()]),
        'brebo_project' => Url::fromRoute('brebo_office_core.project_dashboard', ['node' => $node->id()]),
        'brebo_work_package' => Url::fromRoute('brebo_office_core.work_package_dashboard', ['node' => $node->id()]),
        'brebo_calculation' => Url::fromRoute('brebo_office_core.calculation_dashboard', ['node' => $node->id()]),
        'brebo_work_budget' => Url::fromRoute('brebo_office_core.work_budget_dashboard', ['node' => $node->id()]),
        'brebo_dwelling' => Url::fromRoute('brebo_office_core.dwelling_dossier', ['node' => $node->id()]),
        default => $node->toUrl(),
      };
      $rows[] = [
        ['data' => Link::fromTextAndUrl($node->label(), $view_url)->toRenderable()],
        $status,
        \Drupal::service('date.formatter')->format($node->getChangedTime(), 'short'),
        ['data' => Link::fromTextAndUrl($this->t('Bewerken'), Url::fromRoute('entity.node.edit_form', ['node' => $node->id()]))->toRenderable()],
      ];
    }

    return [
      'actions' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['brebo-list-actions']],
        'add' => [
          '#type' => 'link',
          '#title' => $this->t('Nieuw @type', ['@type' => mb_strtolower((string) $this->entityTypeManager()->getStorage('node_type')->load($bundle)?->label())]),
          '#url' => Url::fromRoute('node.add', ['node_type' => $bundle]),
          '#attributes' => ['class' => ['button']],
          '#access' => !in_array($bundle, ['brebo_work_budget', 'brebo_route_item'], TRUE),
        ],
      ],
      'table' => [
        '#type' => 'table',
        '#header' => [$this->t('Naam'), $this->t('Status'), $this->t('Gewijzigd'), $this->t('Actie')],
        '#rows' => $rows,
        '#empty' => $this->t('Nog geen objecten aangemaakt.'),
      ],
      'pager' => ['#type' => 'pager'],
      '#cache' => [
        'contexts' => ['user.permissions', 'url.query_args:pagers'],
        'tags' => ['node_list:' . $bundle],
      ],
    ];
  }

  /**
   * Returns the title of a dwelling dossier.
   */
  public function dwellingDossierTitle(NodeInterface $node): string {
    $this->assertDwelling($node);
    return (string) $node->label();
  }

  /**
   * Builds a control-ready dossier for a dwelling and its product positions.
   */
  public function dwellingDossier(NodeInterface $node): array {
    $this->assertDwelling($node);

    $storage = $this->entityTypeManager()->getStorage('node');
    $position_ids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'brebo_product_position')
      ->condition('field_brebo_dwelling_ref.target_id', $node->id())
      ->execute();
    $positions = $storage->loadMultiple($position_ids);

    $verifications = [];
    $latest_controls = [];
    $open_blocks = 0;
    if ($position_ids) {
      $verification_ids = $storage->getQuery()
        ->accessCheck(TRUE)
        ->condition('type', 'brebo_verification')
        ->condition('field_brebo_position_ref.target_id', array_values($position_ids), 'IN')
        ->sort('created', 'DESC')
        ->execute();

      foreach ($verification_ids as $verification_id) {
        $verification = $storage->load($verification_id);
        if (!$verification instanceof NodeInterface) {
          continue;
        }
        $verifications[] = $verification;
        $position_id = (int) $verification->get('field_brebo_position_ref')->target_id;
        $latest_controls[$position_id] ??= $verification;
        if ((bool) $verification->get('field_brebo_blocks_release')->value) {
          $open_blocks++;
        }
      }
    }

    usort($positions, function (NodeInterface $left, NodeInterface $right): int {
      return [
        $this->fieldValue($left, 'field_brebo_facade'),
        $this->fieldValue($left, 'field_brebo_position_code'),
      ] <=> [
        $this->fieldValue($right, 'field_brebo_facade'),
        $this->fieldValue($right, 'field_brebo_position_code'),
      ];
    });

    $groups = [];
    $not_applicable = 0;
    $with_photos = 0;

    foreach ($positions as $position) {
      if (!$position instanceof NodeInterface) {
        continue;
      }

      $facade = $this->fieldValue($position, 'field_brebo_facade');
      if ($facade === '—') {
        $facade = (string) $this->t('Niet ingedeeld');
      }

      $is_not_applicable = !$position->get('field_brebo_not_applicable')->isEmpty()
        && (bool) $position->get('field_brebo_not_applicable')->value;
      $photo_count = $position->hasField('field_brebo_photos')
        ? count($position->get('field_brebo_photos'))
        : 0;
      $not_applicable += $is_not_applicable ? 1 : 0;
      $with_photos += $photo_count > 0 ? 1 : 0;

      $status = $is_not_applicable
        ? (string) $this->t('N.V.T.')
        : $this->fieldValue($position, 'field_brebo_status');
      $photo_label = $photo_count === 1
        ? (string) $this->t('1 foto')
        : (string) $this->t('@count foto’s', ['@count' => $photo_count]);
      $latest_control = $latest_controls[(int) $position->id()] ?? NULL;
      $control_result = $latest_control instanceof NodeInterface
        ? $this->fieldValue($latest_control, 'field_brebo_control_result')
        : (string) $this->t('Niet gecontroleerd');

      $groups[$facade][] = [
        ['data' => Link::fromTextAndUrl(
          $this->fieldValue($position, 'field_brebo_position_code'),
          $position->toUrl()
        )->toRenderable()],
        $this->fieldValue($position, 'field_brebo_quantity'),
        $this->fieldValue($position, 'field_brebo_width_mm'),
        $this->fieldValue($position, 'field_brebo_height_mm'),
        $this->fieldValue($position, 'field_brebo_glass_build'),
        $this->fieldValue($position, 'field_brebo_requirement'),
        $status,
        $photo_label,
        $control_result,
        ['data' => Link::fromTextAndUrl(
          $this->t('Controle toevoegen'),
          Url::fromRoute('node.add', ['node_type' => 'brebo_verification'], [
            'query' => ['position' => $position->id()],
          ])
        )->toRenderable()],
        ['data' => Link::fromTextAndUrl(
          $this->t('Bewerken'),
          Url::fromRoute('entity.node.edit_form', ['node' => $position->id()])
        )->toRenderable()],
      ];
    }

    $build = [
      'actions' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['brebo-list-actions']],
        'edit' => [
          '#type' => 'link',
          '#title' => $this->t('Woning bewerken'),
          '#url' => Url::fromRoute('entity.node.edit_form', ['node' => $node->id()]),
          '#attributes' => ['class' => ['button']],
        ],
        'add' => [
          '#type' => 'link',
          '#title' => $this->t('Productpositie toevoegen'),
          '#url' => Url::fromRoute('node.add', ['node_type' => 'brebo_product_position']),
          '#attributes' => ['class' => ['button']],
        ],
      ],
      'summary' => [
        '#type' => 'table',
        '#header' => [$this->t('Woningcode'), $this->t('Adres'), $this->t('Status'), $this->t('Posities'), $this->t('Controles'), $this->t('Open blokkeringen'), $this->t('Met foto’s'), $this->t('N.V.T.')],
        '#rows' => [[
          $this->fieldValue($node, 'field_brebo_dwelling_code'),
          $this->fieldValue($node, 'field_brebo_address'),
          $this->fieldValue($node, 'field_brebo_status'),
          count($positions),
          count($verifications),
          $open_blocks,
          $with_photos,
          $not_applicable,
        ]],
      ],
      'heading' => [
        '#type' => 'html_tag',
        '#tag' => 'h2',
        '#value' => $this->t('Glasposities'),
      ],
    ];

    foreach ($groups as $facade => $rows) {
      $key = 'facade_' . count($build);
      $build[$key] = [
        '#type' => 'details',
        '#title' => $this->t('@facade — @count posities', [
          '@facade' => $facade,
          '@count' => count($rows),
        ]),
        '#open' => TRUE,
        'table' => [
          '#type' => 'table',
          '#header' => [
            $this->t('Positiecode'),
            $this->t('Aantal'),
            $this->t('Breedte (mm)'),
            $this->t('Hoogte (mm)'),
            $this->t('Glasopbouw'),
            $this->t('Eis'),
            $this->t('Status'),
            $this->t('Foto’s'),
            $this->t('Laatste controle'),
            $this->t('Controle'),
            $this->t('Actie'),
          ],
          '#rows' => $rows,
        ],
      ];
    }

    if ($verifications) {
      $control_rows = [];
      foreach ($verifications as $verification) {
        $position = $verification->get('field_brebo_position_ref')->entity;
        $control_rows[] = [
          ['data' => Link::fromTextAndUrl($verification->label(), $verification->toUrl())->toRenderable()],
          $position instanceof NodeInterface ? $this->fieldValue($position, 'field_brebo_position_code') : '—',
          $this->fieldValue($verification, 'field_brebo_control_result'),
          $this->fieldValue($verification, 'field_brebo_control_source'),
          \Drupal::service('date.formatter')->format($verification->getCreatedTime(), 'short'),
          $verification->getOwner()?->getDisplayName() ?? '—',
          (bool) $verification->get('field_brebo_blocks_release')->value
            ? (string) $this->t('Ja')
            : (string) $this->t('Nee'),
          ['data' => (string) $verification->get('field_brebo_control_result')->value === 'Afwijking'
            ? Link::fromTextAndUrl(
              $this->t('Afwijking openen'),
              Url::fromRoute('node.add', ['node_type' => 'brebo_deviation'], [
                'query' => ['control' => $verification->id()],
              ])
            )->toRenderable()
            : '—'],
          ['data' => Link::fromTextAndUrl(
            $this->t('Bewerken'),
            Url::fromRoute('entity.node.edit_form', ['node' => $verification->id()])
          )->toRenderable()],
        ];
      }

      $build['controls_heading'] = [
        '#type' => 'html_tag',
        '#tag' => 'h2',
        '#value' => $this->t('Controlehistorie'),
      ];
      $build['controls'] = [
        '#type' => 'table',
        '#header' => [
          $this->t('Controle'),
          $this->t('Positie'),
          $this->t('Resultaat'),
          $this->t('Bron'),
          $this->t('Datum'),
          $this->t('Controleur'),
          $this->t('Blokkering'),
          $this->t('Afwijking'),
          $this->t('Actie'),
        ],
        '#rows' => $control_rows,
      ];
    }

    if (!$groups) {
      $build['empty'] = [
        '#markup' => '<p>' . $this->t('Aan deze woning zijn nog geen productposities gekoppeld.') . '</p>',
      ];
    }

    $build['#cache'] = [
      'contexts' => ['user.permissions'],
      'tags' => array_merge($node->getCacheTags(), [
        'node_list:brebo_product_position',
        'node_list:brebo_verification',
        'node_list:brebo_deviation',
      ]),
    ];

    return $build;
  }







  /**
   * Returns the work package dashboard title.
   */
  public function workPackageDashboardTitle(NodeInterface $node): string {
    if ($node->bundle() !== 'brebo_work_package') {
      throw new NotFoundHttpException();
    }
    return (string) $node->label();
  }

  /**
   * Builds the work package and release-gate dashboard.
   */
  public function workPackageDashboard(NodeInterface $node): array {
    if ($node->bundle() !== 'brebo_work_package') {
      throw new NotFoundHttpException();
    }

    $storage = $this->entityTypeManager()->getStorage('node');
    $gate_ids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'brebo_release_gate')
      ->condition('field_brebo_package_ref.target_id', $node->id())
      ->sort('field_brebo_gate_type')
      ->execute();
    $gates = $storage->loadMultiple($gate_ids);
    $positions = $node->get('field_brebo_package_positions')->referencedEntities();
    $blocked = 0;
    $approved = 0;
    $gate_rows = [];

    foreach ($gates as $gate) {
      if (!$gate instanceof NodeInterface) {
        continue;
      }
      $is_blocking = (bool) $gate->get('field_brebo_gate_blocks')->value;
      $blocked += $is_blocking ? 1 : 0;
      $approved += $this->fieldValue($gate, 'field_brebo_gate_result') === 'Akkoord' ? 1 : 0;
      $gate_rows[] = [
        $this->fieldValue($gate, 'field_brebo_gate_type'),
        (bool) $gate->get('field_brebo_gate_applicable')->value
          ? (string) $this->t('Ja')
          : (string) $this->t('Nee'),
        $this->fieldValue($gate, 'field_brebo_gate_result'),
        $is_blocking ? (string) $this->t('Geblokkeerd') : (string) $this->t('Vrij'),
        $this->fieldValue($gate, 'field_brebo_gate_assessment'),
        ['data' => Link::fromTextAndUrl(
          $this->t('Beoordelen'),
          Url::fromRoute('entity.node.edit_form', ['node' => $gate->id()])
        )->toRenderable()],
      ];
    }

    $position_rows = [];
    foreach ($positions as $position) {
      if (!$position instanceof NodeInterface) {
        continue;
      }
      $position_rows[] = [
        ['data' => Link::fromTextAndUrl($position->label(), $position->toUrl())->toRenderable()],
        $this->fieldValue($position, 'field_brebo_position_code'),
        $this->fieldValue($position, 'field_brebo_product_type'),
        $this->fieldValue($position, 'field_brebo_position_location'),
        $this->fieldValue($position, 'field_brebo_status'),
      ];
    }

    $project = $node->get('field_brebo_project_ref')->entity;
    $cluster = $node->get('field_brebo_cluster_ref')->entity;
    $owner = $node->get('field_brebo_package_owner')->entity;
    $release_ready = count($gates) > 0 && $blocked === 0;

    $effective_setting = function (string $field_name) use ($node, $project): array {
      if ($node->hasField($field_name) && !$node->get($field_name)->isEmpty()) {
        $value = (string) $node->get($field_name)->value;
        return [$value, (string) $this->t('Afwijkend werkpakket')];
      }
      if ($project instanceof NodeInterface) {
        return [$this->fieldValue($project, $field_name), (string) $this->t('Overgenomen van project')];
      }
      return ['—', (string) $this->t('Niet vastgesteld')];
    };

    $package_operating_rows = [];
    foreach ([
      'BREBO-rol' => 'field_brebo_service_role',
      'Verrekenwijze' => 'field_brebo_settlement_method',
      'Inkooppositie' => 'field_brebo_procurement_position',
      'Mandaatgrens' => 'field_brebo_mandate_limit',
      'Plafondtype' => 'field_brebo_ceiling_type',
      'Plafondbedrag' => 'field_brebo_ceiling_amount',
    ] as $label => $field_name) {
      [$value, $source] = $effective_setting($field_name);
      $package_operating_rows[] = [$this->t($label), $value, $source];
    }

    return [
      'actions' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['brebo-list-actions']],
        'edit' => [
          '#type' => 'link',
          '#title' => $this->t('Werkpakket bewerken'),
          '#url' => Url::fromRoute('entity.node.edit_form', ['node' => $node->id()]),
          '#attributes' => ['class' => ['button']],
        ],
        'add_gate' => [
          '#type' => 'link',
          '#title' => $this->t('Vrijgavepoort toevoegen'),
          '#url' => Url::fromRoute('node.add', ['node_type' => 'brebo_release_gate'], [
            'query' => ['package' => $node->id()],
          ]),
          '#attributes' => ['class' => ['button']],
        ],
      ],
      'package' => [
        '#type' => 'table',
        '#header' => [
          $this->t('Code'), $this->t('Project'), $this->t('Cluster'),
          $this->t('Discipline'), $this->t('Verantwoordelijke'), $this->t('Status'),
        ],
        '#rows' => [[
          $this->fieldValue($node, 'field_brebo_package_code'),
          $project ? $project->label() : '—',
          $cluster ? $cluster->label() : '—',
          $this->fieldValue($node, 'field_brebo_discipline'),
          $owner ? $owner->label() : '—',
          $this->fieldValue($node, 'field_brebo_package_status'),
        ]],
      ],
      'operating_model_heading' => [
        '#markup' => '<h2>' . $this->t('Besturing van dit werkpakket') . '</h2>',
      ],
      'operating_model' => [
        '#type' => 'table',
        '#header' => [$this->t('Instelling'), $this->t('Effectieve waarde'), $this->t('Bron')],
        '#rows' => $package_operating_rows,
      ],
      'summary' => [
        '#type' => 'table',
        '#header' => [
          $this->t('Productposities'), $this->t('Poorten'),
          $this->t('Akkoord'), $this->t('Blokkerend'), $this->t('Vrijgave mogelijk'),
        ],
        '#rows' => [[
          count($positions), count($gates), $approved, $blocked,
          $release_ready ? $this->t('Ja') : $this->t('Nee'),
        ]],
      ],
      'gates_heading' => ['#markup' => '<h2>' . $this->t('Vrijgavepoorten') . '</h2>'],
      'gates' => [
        '#type' => 'table',
        '#header' => [
          $this->t('Domein'), $this->t('Van toepassing'), $this->t('Resultaat'),
          $this->t('Vrijgave'), $this->t('Onderbouwing'), $this->t('Actie'),
        ],
        '#rows' => $gate_rows,
        '#empty' => $this->t('Nog geen vrijgavepoorten.'),
      ],
      'positions_heading' => ['#markup' => '<h2>' . $this->t('Productposities') . '</h2>'],
      'positions' => [
        '#type' => 'table',
        '#header' => [
          $this->t('Positie'), $this->t('Code'), $this->t('Producttype'),
          $this->t('Locatie'), $this->t('Status'),
        ],
        '#rows' => $position_rows,
        '#empty' => $this->t('Geen productposities gekoppeld.'),
      ],
      '#cache' => [
        'contexts' => ['user.permissions'],
        'tags' => [
          'node_list:brebo_work_package',
          'node_list:brebo_release_gate',
          'node_list:brebo_product_position',
        ],
      ],
    ];
  }

  /**
   * Returns the building dossier title.
   */
  public function buildingDashboardTitle(NodeInterface $node): string {
    if ($node->bundle() !== 'brebo_building') {
      throw new NotFoundHttpException();
    }
    return (string) $node->label();
  }

  /**
   * Builds the permanent building dossier and its project history.
   */
  public function buildingDashboard(NodeInterface $node): array {
    if ($node->bundle() !== 'brebo_building') {
      throw new NotFoundHttpException();
    }

    $storage = $this->entityTypeManager()->getStorage('node');
    $project_ids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'brebo_project')
      ->condition('field_brebo_building_refs.target_id', $node->id())
      ->sort('changed', 'DESC')
      ->execute();
    $cluster_ids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'brebo_cluster')
      ->condition('field_brebo_building_ref.target_id', $node->id())
      ->execute();
    $dwelling_ids = $cluster_ids
      ? $storage->getQuery()
        ->accessCheck(TRUE)
        ->condition('type', 'brebo_dwelling')
        ->condition('field_brebo_cluster_ref.target_id', array_values($cluster_ids), 'IN')
        ->execute()
      : [];
    $position_ids = $dwelling_ids
      ? $storage->getQuery()
        ->accessCheck(TRUE)
        ->condition('type', 'brebo_product_position')
        ->condition('field_brebo_dwelling_ref.target_id', array_values($dwelling_ids), 'IN')
        ->execute()
      : [];

    $project_rows = [];
    foreach ($storage->loadMultiple($project_ids) as $project) {
      if ($project instanceof NodeInterface) {
        $project_rows[] = [
          ['data' => Link::fromTextAndUrl(
            $project->label(),
            Url::fromRoute('brebo_office_core.project_dashboard', ['node' => $project->id()])
          )->toRenderable()],
          $this->fieldValue($project, 'field_brebo_project_code'),
          $this->fieldValue($project, 'field_brebo_project_kind'),
          $this->fieldValue($project, 'field_brebo_status'),
          \Drupal::service('date.formatter')->format($project->getChangedTime(), 'short'),
        ];
      }
    }

    $cluster_rows = [];
    foreach ($storage->loadMultiple($cluster_ids) as $cluster) {
      if ($cluster instanceof NodeInterface) {
        $cluster_rows[] = [
          ['data' => Link::fromTextAndUrl($cluster->label(), $cluster->toUrl())->toRenderable()],
          $this->fieldValue($cluster, 'field_brebo_cluster_code'),
          $this->fieldValue($cluster, 'field_brebo_status'),
        ];
      }
    }

    $latitude = $this->fieldValue($node, 'field_brebo_latitude');
    $longitude = $this->fieldValue($node, 'field_brebo_longitude');
    $has_coordinates = $latitude !== '—' && $longitude !== '—';

    $build = [
      'actions' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['brebo-list-actions']],
        'edit' => [
          '#type' => 'link',
          '#title' => $this->t('Gebouw bewerken'),
          '#url' => Url::fromRoute('entity.node.edit_form', ['node' => $node->id()]),
          '#attributes' => ['class' => ['button']],
        ],
        'geocode' => [
          '#type' => 'link',
          '#title' => $has_coordinates ? $this->t('Locatie opnieuw bepalen') : $this->t('Locatie bepalen'),
          '#url' => Url::fromRoute('brebo_office_core.building_geocode', ['node' => $node->id()]),
          '#attributes' => ['class' => ['button']],
        ],
        'project' => [
          '#type' => 'link',
          '#title' => $this->t('Nieuw project koppelen'),
          '#url' => Url::fromRoute('node.add', ['node_type' => 'brebo_project'], [
            'query' => ['building' => $node->id()],
          ]),
          '#attributes' => ['class' => ['button']],
        ],
      ],
      'dashboard_tabs' => [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['brebo-project-tabs'],
          'role' => 'tablist',
          'aria-label' => $this->t('Gebouwdossier'),
          'data-brebo-tabs' => 'true',
        ],
        'overview' => [
          '#type' => 'html_tag',
          '#tag' => 'button',
          '#value' => $this->t('Overzicht'),
          '#attributes' => [
            'type' => 'button', 'role' => 'tab', 'aria-selected' => 'true',
            'data-brebo-tab' => 'overview', 'class' => ['is-active'],
          ],
        ],
        'history' => [
          '#type' => 'html_tag',
          '#tag' => 'button',
          '#value' => $this->t('Projecthistorie'),
          '#attributes' => [
            'type' => 'button', 'role' => 'tab', 'aria-selected' => 'false',
            'data-brebo-tab' => 'history', 'class' => [],
          ],
        ],
        'structure' => [
          '#type' => 'html_tag',
          '#tag' => 'button',
          '#value' => $this->t('Gebouwstructuur'),
          '#attributes' => [
            'type' => 'button', 'role' => 'tab', 'aria-selected' => 'false',
            'data-brebo-tab' => 'structure', 'class' => [],
          ],
        ],
        '#attached' => ['library' => ['brebo_office/project-tabs']],
      ],
      'identity' => [
        '#type' => 'table',
        '#header' => [
          $this->t('Gebouwcode'), $this->t('Adres'), $this->t('Plaats'),
          $this->t('Land'), $this->t('Status'),
        ],
        '#rows' => [[
          $this->fieldValue($node, 'field_brebo_building_code'),
          $this->fieldValue($node, 'field_brebo_address'),
          $this->fieldValue($node, 'field_brebo_city'),
          $this->fieldValue($node, 'field_brebo_country'),
          $this->fieldValue($node, 'field_brebo_status'),
        ]],
      ],
      'map' => [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['brebo-building-map', $has_coordinates ? 'has-location' : 'needs-location'],
          'data-brebo-building-map' => $has_coordinates ? 'true' : 'false',
          'data-latitude' => $has_coordinates ? $latitude : '',
          'data-longitude' => $has_coordinates ? $longitude : '',
          'aria-label' => $this->t('Luchtfoto van @building', ['@building' => $node->label()]),
        ],
        'tiles' => $has_coordinates ? [
          '#type' => 'container',
          '#attributes' => ['class' => ['brebo-building-map__tiles'], 'aria-hidden' => 'true'],
        ] : [],
        'overlay' => [
          '#type' => 'container',
          '#attributes' => ['class' => ['brebo-building-map__overlay']],
          'eyebrow' => ['#markup' => '<p class="brebo-eyebrow">' . $this->t('GEBOUWENKAART · PDOK LUCHTFOTO') . '</p>'],
          'title' => ['#markup' => '<h2>' . ($has_coordinates
            ? $this->t('Officiële locatie vastgelegd')
            : $this->t('Satellietlocatie nog vast te leggen')) . '</h2>'],
          'description' => ['#markup' => '<p>' . ($has_coordinates
            ? $this->t('Coördinaten: @lat, @lon. De rode markering toont de vastgelegde gebouwlocatie.', ['@lat' => $latitude, '@lon' => $longitude])
            : $this->t('Zoek het BAG-adres en kies de juiste locatie. Daarna verschijnt hier automatisch de actuele Nederlandse luchtfoto.')) . '</p>'],
        ],
        'attribution' => $has_coordinates ? [
          '#markup' => '<p class="brebo-building-map__attribution">Luchtfoto: PDOK / Beeldmateriaal Nederland · Locatie: BAG</p>',
        ] : [],
        '#attached' => $has_coordinates ? ['library' => ['brebo_office/building-map']] : [],
      ],
      'summary' => [
        '#type' => 'table',
        '#header' => [
          $this->t('Projecten'), $this->t('Clusters'), $this->t('Woningen'), $this->t('Productposities'),
        ],
        '#rows' => [[count($project_ids), count($cluster_ids), count($dwelling_ids), count($position_ids)]],
      ],
      'projects_heading' => ['#markup' => '<h2>' . $this->t('Projecthistorie') . '</h2>'],
      'projects' => [
        '#type' => 'table',
        '#header' => [
          $this->t('Project'), $this->t('Code'), $this->t('Projectsoort'),
          $this->t('Status'), $this->t('Gewijzigd'),
        ],
        '#rows' => $project_rows,
        '#empty' => $this->t('Aan dit gebouw zijn nog geen projecten gekoppeld.'),
      ],
      'clusters_heading' => ['#markup' => '<h2>' . $this->t('Gebouwstructuur') . '</h2>'],
      'clusters' => [
        '#type' => 'table',
        '#header' => [$this->t('Cluster'), $this->t('Code'), $this->t('Status')],
        '#rows' => $cluster_rows,
        '#empty' => $this->t('Dit gebouw bevat nog geen clusters of gebouwdelen.'),
      ],
      '#cache' => [
        'contexts' => ['user.permissions'],
        'tags' => [
          'node:' . $node->id(),
          'node_list:brebo_project',
          'node_list:brebo_cluster',
          'node_list:brebo_dwelling',
          'node_list:brebo_product_position',
        ],
      ],
    ];

    $tab_panels = [
      'identity' => 'overview',
      'map' => 'overview',
      'summary' => 'overview',
      'projects_heading' => 'history',
      'projects' => 'history',
      'clusters_heading' => 'structure',
      'clusters' => 'structure',
    ];
    foreach ($tab_panels as $key => $tab_id) {
      $content = $build[$key];
      $build[$key] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['brebo-tab-panel'],
          'data-brebo-tab-panel' => $tab_id,
          'role' => 'tabpanel',
        ],
        'content' => $content,
      ];
    }

    return $build;
  }

  /**
   * Returns the project dashboard title.
   */
  public function projectDashboardTitle(NodeInterface $node): string {
    if ($node->bundle() !== 'brebo_project') {
      throw new NotFoundHttpException();
    }
    return (string) $node->label();
  }

  /**
   * Builds the project-wide steering dashboard.
   */
  public function projectDashboard(NodeInterface $node): array {
    if ($node->bundle() !== 'brebo_project') {
      throw new NotFoundHttpException();
    }

    $storage = $this->entityTypeManager()->getStorage('node');
    $buildings = $node->hasField('field_brebo_building_refs')
      ? $node->get('field_brebo_building_refs')->referencedEntities()
      : [];
    $building_rows = [];
    foreach ($buildings as $building) {
      if ($building instanceof NodeInterface) {
        $building_rows[] = [
          ['data' => Link::fromTextAndUrl(
            $building->label(),
            Url::fromRoute('brebo_office_core.building_dashboard', ['node' => $building->id()])
          )->toRenderable()],
          $this->fieldValue($building, 'field_brebo_building_code'),
          $this->fieldValue($building, 'field_brebo_address'),
          $this->fieldValue($building, 'field_brebo_city'),
          $this->fieldValue($building, 'field_brebo_status'),
        ];
      }
    }

    $cluster_ids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'brebo_cluster')
      ->condition('field_brebo_project_ref.target_id', $node->id())
      ->execute();
    $clusters = $storage->loadMultiple($cluster_ids);

    $dwelling_ids = $cluster_ids
      ? $storage->getQuery()
        ->accessCheck(TRUE)
        ->condition('type', 'brebo_dwelling')
        ->condition('field_brebo_cluster_ref.target_id', array_values($cluster_ids), 'IN')
        ->execute()
      : [];
    $dwellings = $storage->loadMultiple($dwelling_ids);

    $position_ids = $dwelling_ids
      ? $storage->getQuery()
        ->accessCheck(TRUE)
        ->condition('type', 'brebo_product_position')
        ->condition('field_brebo_dwelling_ref.target_id', array_values($dwelling_ids), 'IN')
        ->execute()
      : [];
    $positions = $storage->loadMultiple($position_ids);

    $control_ids = $position_ids
      ? $storage->getQuery()
        ->accessCheck(TRUE)
        ->condition('type', 'brebo_verification')
        ->condition('field_brebo_position_ref.target_id', array_values($position_ids), 'IN')
        ->execute()
      : [];
    $controls = $storage->loadMultiple($control_ids);

    $open_deviations = $control_ids
      ? (int) $storage->getQuery()
        ->accessCheck(TRUE)
        ->condition('type', 'brebo_deviation')
        ->condition('field_brebo_control_ref.target_id', array_values($control_ids), 'IN')
        ->condition('field_brebo_deviation_status', 'Gesloten', '<>')
        ->count()
        ->execute()
      : 0;

    $positions_by_dwelling = [];
    $position_types = [];
    foreach ($positions as $position) {
      if (!$position instanceof NodeInterface) {
        continue;
      }
      $dwelling_id = (int) $position->get('field_brebo_dwelling_ref')->target_id;
      $positions_by_dwelling[$dwelling_id][] = (int) $position->id();
      $type = $this->fieldValue($position, 'field_brebo_product_type');
      $position_types[$type] = ($position_types[$type] ?? 0) + 1;
    }

    $controls_by_position = [];
    $blocked_by_position = [];
    $approved = 0;
    foreach ($controls as $control) {
      if (!$control instanceof NodeInterface) {
        continue;
      }
      $position_id = (int) $control->get('field_brebo_position_ref')->target_id;
      $controls_by_position[$position_id][] = (int) $control->id();
      if ((bool) $control->get('field_brebo_blocks_release')->value) {
        $blocked_by_position[$position_id] = TRUE;
      }
      if ($this->fieldValue($control, 'field_brebo_control_result') === 'Akkoord') {
        $approved++;
      }
    }

    $cluster_rows = [];
    foreach ($clusters as $cluster) {
      if (!$cluster instanceof NodeInterface) {
        continue;
      }
      $cluster_dwelling_ids = [];
      foreach ($dwellings as $dwelling) {
        if ($dwelling instanceof NodeInterface
          && (int) $dwelling->get('field_brebo_cluster_ref')->target_id === (int) $cluster->id()) {
          $cluster_dwelling_ids[] = (int) $dwelling->id();
        }
      }
      $cluster_position_count = 0;
      foreach ($cluster_dwelling_ids as $dwelling_id) {
        $cluster_position_count += count($positions_by_dwelling[$dwelling_id] ?? []);
      }
      $cluster_rows[] = [
        ['data' => Link::fromTextAndUrl($cluster->label(), $cluster->toUrl())->toRenderable()],
        $this->fieldValue($cluster, 'field_brebo_cluster_code'),
        $this->fieldValue($cluster, 'field_brebo_status'),
        count($cluster_dwelling_ids),
        $cluster_position_count,
      ];
    }

    $dwelling_rows = [];
    foreach ($dwellings as $dwelling) {
      if (!$dwelling instanceof NodeInterface) {
        continue;
      }
      $dwelling_position_ids = $positions_by_dwelling[(int) $dwelling->id()] ?? [];
      $control_count = 0;
      $blocked_count = 0;
      foreach ($dwelling_position_ids as $position_id) {
        $control_count += count($controls_by_position[$position_id] ?? []);
        $blocked_count += isset($blocked_by_position[$position_id]) ? 1 : 0;
      }
      $cluster = $dwelling->get('field_brebo_cluster_ref')->entity;
      $dwelling_rows[] = [
        ['data' => Link::fromTextAndUrl(
          $dwelling->label(),
          Url::fromRoute('brebo_office_core.dwelling_dossier', ['node' => $dwelling->id()])
        )->toRenderable()],
        $cluster instanceof NodeInterface ? $cluster->label() : '—',
        $this->fieldValue($dwelling, 'field_brebo_status'),
        count($dwelling_position_ids),
        $control_count,
        $blocked_count,
      ];
    }

    $type_rows = [];
    ksort($position_types);
    foreach ($position_types as $type => $quantity) {
      $type_rows[] = [$type, $quantity];
    }

    $ceiling_type = $this->fieldValue($node, 'field_brebo_ceiling_type');
    $ceiling_amount = $this->fieldValue($node, 'field_brebo_ceiling_amount');
    $mandate_limit = $this->fieldValue($node, 'field_brebo_mandate_limit');
    $procurement_allowed = !$node->get('field_brebo_procurement_allowed')->isEmpty()
      && (bool) $node->get('field_brebo_procurement_allowed')->value;

    $route_ids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'brebo_route_item')
      ->condition('field_brebo_project_ref.target_id', $node->id())
      ->sort('field_brebo_route_sequence', 'ASC')
      ->execute();
    $route_rows = [];
    $route_counts = ['Gereed' => 0, 'Actief' => 0, 'Geblokkeerd' => 0, 'N.V.T.' => 0];
    foreach ($storage->loadMultiple($route_ids) as $route_item) {
      if (!$route_item instanceof NodeInterface) {
        continue;
      }
      $status = $this->fieldValue($route_item, 'field_brebo_route_status');
      $applicable = (bool) $route_item->get('field_brebo_route_applicable')->value;
      if (!$applicable || in_array($status, ['N.V.T.', 'Vervallen'], TRUE)) {
        $route_counts['N.V.T.']++;
      }
      elseif ($status === 'Gereed') {
        $route_counts['Gereed']++;
      }
      elseif ($status === 'Geblokkeerd') {
        $route_counts['Geblokkeerd']++;
      }
      else {
        $route_counts['Actief']++;
      }
      $owner = $route_item->get('field_brebo_route_owner')->entity;
      $route_rows[] = [
        $this->fieldValue($route_item, 'field_brebo_route_sequence'),
        $this->fieldValue($route_item, 'field_brebo_route_code'),
        $this->fieldValue($route_item, 'field_brebo_route_kind'),
        $this->fieldValue($route_item, 'field_brebo_route_description'),
        $applicable ? $this->t('Ja') : $this->t('Nee'),
        $status,
        $owner ? $owner->label() : '—',
        $this->fieldValue($route_item, 'field_brebo_route_due'),
        ['data' => Link::fromTextAndUrl(
          $this->t('Bijwerken'),
          Url::fromRoute('entity.node.edit_form', ['node' => $route_item->id()])
        )->toRenderable()],
      ];
    }

    $route_total = count($route_rows);
    $blocked_positions = count($blocked_by_position);
    $quality_attention = $open_deviations + $blocked_positions;
    $object_total = count($clusters) + count($dwellings) + count($positions);

    $route_signal = $route_total === 0
      ? ['neutral', $this->t('Nog niet ingericht')]
      : ($route_counts['Geblokkeerd'] > 0
        ? ['danger', $this->t('@count geblokkeerd', ['@count' => $route_counts['Geblokkeerd']])]
        : ($route_counts['Actief'] > 0
          ? ['attention', $this->t('@count actief', ['@count' => $route_counts['Actief']])]
          : ['good', $this->t('Route op orde')]));
    $quality_signal = $quality_attention > 0
      ? ['danger', $this->t('@count signaal/signalen', ['@count' => $quality_attention])]
      : (count($controls) === 0
        ? ['neutral', $this->t('Nog niet gecontroleerd')]
        : ($approved < count($controls)
          ? ['attention', $this->t('Controle loopt')]
          : ['good', $this->t('Aantoonbaar op orde')]));
    $evidence_signal = count($controls) === 0
      ? ['neutral', $this->t('Nog geen bewijs')]
      : ($approved === count($controls)
        ? ['good', $this->t('Volledig akkoord')]
        : ['attention', $this->t('@approved van @total akkoord', [
          '@approved' => $approved,
          '@total' => count($controls),
        ])]);
    $object_signal = $object_total === 0
      ? ['neutral', $this->t('Nog geen objecten')]
      : (count($clusters) > 0 && count($dwellings) > 0 && count($positions) > 0
        ? ['good', $this->t('Objectketen gevuld')]
        : ['attention', $this->t('Objectketen onvolledig')]);
    $deviation_signal = $open_deviations > 0
      ? ['danger', $this->t('@count open', ['@count' => $open_deviations])]
      : ['good', $this->t('Geen open afwijkingen')];
    $release_signal = $blocked_positions > 0
      ? ['danger', $this->t('@count positie(s) geblokkeerd', ['@count' => $blocked_positions])]
      : (count($positions) === 0
        ? ['neutral', $this->t('Nog niet beoordeeld')]
        : (count($controls) === 0
          ? ['attention', $this->t('Controle vereist')]
          : ['good', $this->t('Geen blokkering')]));

    $status_card = function (
      string $label,
      string $value,
      string $description,
      array $signal,
      Url $url
    ): array {
      return [
        '#type' => 'link',
        '#title' => [
          'top' => [
            '#type' => 'container',
            '#attributes' => ['class' => ['brebo-signal-card__top']],
            'label' => ['#markup' => '<span class="brebo-signal-card__label">' . $label . '</span>'],
            'light' => [
              '#markup' => '<span class="brebo-traffic-light brebo-traffic-light--' . $signal[0] . '" aria-hidden="true"></span>',
            ],
          ],
          'value' => ['#markup' => '<strong>' . $value . '</strong>'],
          'description' => ['#markup' => '<span class="brebo-signal-card__description">' . $description . '</span>'],
          'status' => [
            '#markup' => '<span class="brebo-signal-card__status brebo-signal-card__status--' . $signal[0] . '">' . $signal[1] . '</span>',
          ],
        ],
        '#url' => $url,
        '#attributes' => [
          'class' => ['brebo-signal-card', 'brebo-signal-card--' . $signal[0]],
          'aria-label' => $label . ': ' . $signal[1],
        ],
      ];
    };

    $build = [
      'actions' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['brebo-list-actions']],
        'edit' => [
          '#type' => 'link',
          '#title' => $this->t('Projectinstellingen bewerken'),
          '#url' => Url::fromRoute('entity.node.edit_form', ['node' => $node->id()]),
          '#attributes' => ['class' => ['button']],
        ],
        'regie_actions' => [
          '#type' => 'link',
          '#title' => $this->t('Centrale regie-acties'),
          '#url' => Url::fromRoute('brebo_office_core.regie_actions'),
          '#attributes' => ['class' => ['button']],
        ],
        'budget_change' => [
          '#type' => 'link',
          '#title' => $this->t('Budgetwijzigingsbesluit toevoegen'),
          '#url' => Url::fromRoute('node.add', ['node_type' => 'brebo_budget_change'], [
            'query' => ['project' => $node->id()],
          ]),
          '#attributes' => ['class' => ['button']],
        ],
      ],
      'dashboard_tabs' => [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['brebo-project-tabs'],
          'role' => 'tablist',
          'aria-label' => $this->t('Projectdashboard'),
          'data-brebo-tabs' => 'true',
        ],
        'overview' => [
          '#type' => 'html_tag',
          '#tag' => 'button',
          '#value' => $this->t('Overzicht'),
          '#attributes' => [
            'type' => 'button',
            'role' => 'tab',
            'aria-selected' => 'true',
            'data-brebo-tab' => 'overview',
            'class' => ['is-active'],
          ],
        ],
        'governance' => [
          '#type' => 'html_tag',
          '#tag' => 'button',
          '#value' => $this->t('Besturing'),
          '#attributes' => [
            'type' => 'button',
            'role' => 'tab',
            'aria-selected' => 'false',
            'data-brebo-tab' => 'governance',
            'class' => [],
          ],
        ],
        'route' => [
          '#type' => 'html_tag',
          '#tag' => 'button',
          '#value' => $this->t('Route & acties'),
          '#attributes' => [
            'type' => 'button',
            'role' => 'tab',
            'aria-selected' => 'false',
            'data-brebo-tab' => 'route',
            'class' => [],
          ],
        ],
        'objects' => [
          '#type' => 'html_tag',
          '#tag' => 'button',
          '#value' => $this->t('Objecten'),
          '#attributes' => [
            'type' => 'button',
            'role' => 'tab',
            'aria-selected' => 'false',
            'data-brebo-tab' => 'objects',
            'class' => [],
          ],
        ],
        '#attached' => ['library' => ['brebo_office/project-tabs']],
      ],
      'project' => [
        '#type' => 'table',
        '#header' => [
          $this->t('Projectcode'),
          $this->t('Opdrachtgever'),
          $this->t('Locatie'),
          $this->t('Status'),
        ],
        '#rows' => [[
          $this->fieldValue($node, 'field_brebo_project_code'),
          $this->fieldValue($node, 'field_brebo_client'),
          $this->fieldValue($node, 'field_brebo_location'),
          $this->fieldValue($node, 'field_brebo_status'),
        ]],
      ],
      'buildings_heading' => [
        '#markup' => '<h2>' . $this->t('Gekoppelde gebouwen') . '</h2>',
      ],
      'buildings' => [
        '#type' => 'table',
        '#header' => [
          $this->t('Gebouw'), $this->t('Code'), $this->t('Adres'),
          $this->t('Plaats'), $this->t('Status'),
        ],
        '#rows' => $building_rows,
        '#empty' => $this->t('Aan dit project is nog geen permanent gebouw gekoppeld.'),
      ],
      'operating_model_heading' => [
        '#markup' => '<h2>' . $this->t('Projectbesturing') . '</h2>',
      ],
      'operating_model' => [
        '#type' => 'table',
        '#header' => [
          $this->t('Projectsoort'),
          $this->t('BREBO-rol'),
          $this->t('Verrekenwijze'),
          $this->t('Inkooppositie'),
        ],
        '#rows' => [[
          $this->fieldValue($node, 'field_brebo_project_kind'),
          $this->fieldValue($node, 'field_brebo_service_role'),
          $this->fieldValue($node, 'field_brebo_settlement_method'),
          $this->fieldValue($node, 'field_brebo_procurement_position'),
        ]],
      ],
      'procurement' => [
        '#type' => 'table',
        '#header' => [
          $this->t('Selecteert'),
          $this->t('Contracteert'),
          $this->t('Kostendrager'),
          $this->t('Factuurontvanger'),
          $this->t('Inkoop toegestaan'),
        ],
        '#rows' => [[
          $this->fieldValue($node, 'field_brebo_selector_party'),
          $this->fieldValue($node, 'field_brebo_contracting_party'),
          $this->fieldValue($node, 'field_brebo_cost_bearer'),
          $this->fieldValue($node, 'field_brebo_invoice_recipient'),
          $procurement_allowed ? $this->t('Ja') : $this->t('Nee'),
        ]],
      ],
      'authority' => [
        '#type' => 'table',
        '#header' => [
          $this->t('Mandaatgrens'),
          $this->t('Plafondtype'),
          $this->t('Plafondbedrag'),
          $this->t('Disciplines'),
        ],
        '#rows' => [[
          $mandate_limit,
          $ceiling_type,
          $ceiling_type === 'Geen plafond' ? $this->t('Niet van toepassing') : $ceiling_amount,
          $this->fieldValue($node, 'field_brebo_disciplines'),
        ]],
      ],
      'route_heading' => [
        '#markup' => '<h2>' . $this->t('Automatische projectroute') . '</h2>',
      ],
      'route_summary' => [
        '#type' => 'table',
        '#header' => [
          $this->t('Routeonderdelen'), $this->t('Gereed'), $this->t('Actief'),
          $this->t('Geblokkeerd'), $this->t('N.V.T. of vervallen'),
        ],
        '#rows' => [[
          count($route_rows), $route_counts['Gereed'], $route_counts['Actief'],
          $route_counts['Geblokkeerd'], $route_counts['N.V.T.'],
        ]],
      ],
      'route' => [
        '#type' => 'table',
        '#header' => [
          $this->t('Volgorde'), $this->t('Code'), $this->t('Soort'),
          $this->t('Vereist resultaat'), $this->t('Van toepassing'),
          $this->t('Status'), $this->t('Verantwoordelijke'),
          $this->t('Streefdatum'), $this->t('Actie'),
        ],
        '#rows' => $route_rows,
        '#empty' => $this->t('Nog geen projectroute geactiveerd. Sla het project opnieuw op of voer de database-update uit.'),
      ],
      'summary' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['brebo-project-signals']],
        'route' => $status_card(
          (string) $this->t('Projectroute'),
          $route_counts['Gereed'] . ' / ' . $route_total,
          (string) $this->t('Routeonderdelen gereed'),
          $route_signal,
          Url::fromRoute('brebo_office_core.project_dashboard', ['node' => $node->id()], ['fragment' => 'tab-route'])
        ),
        'quality' => $status_card(
          (string) $this->t('Kwaliteit'),
          (string) count($controls),
          (string) $this->t('Uitgevoerde controles'),
          $quality_signal,
          Url::fromRoute('brebo_office_core.quality_dashboard')
        ),
        'evidence' => $status_card(
          (string) $this->t('Bewijs'),
          $approved . ' / ' . count($controls),
          (string) $this->t('Controles met akkoord'),
          $evidence_signal,
          Url::fromRoute('brebo_office_core.project_dashboard', ['node' => $node->id()], ['fragment' => 'tab-objects'])
        ),
        'objects' => $status_card(
          (string) $this->t('Objectmodel'),
          (string) count($positions),
          (string) $this->t('@clusters clusters · @dwellings woningen', [
            '@clusters' => count($clusters),
            '@dwellings' => count($dwellings),
          ]),
          $object_signal,
          Url::fromRoute('brebo_office_core.project_dashboard', ['node' => $node->id()], ['fragment' => 'tab-objects'])
        ),
        'deviations' => $status_card(
          (string) $this->t('Afwijkingen'),
          (string) $open_deviations,
          (string) $this->t('Openstaande afwijkingen'),
          $deviation_signal,
          Url::fromRoute('brebo_office_core.quality_dashboard')
        ),
        'release' => $status_card(
          (string) $this->t('Vrijgave'),
          (string) $blocked_positions,
          (string) $this->t('Geblokkeerde posities'),
          $release_signal,
          Url::fromRoute('brebo_office_core.project_dashboard', ['node' => $node->id()], ['fragment' => 'tab-objects'])
        ),
      ],
      'clusters_heading' => ['#markup' => '<h2>' . $this->t('Clusters') . '</h2>'],
      'clusters' => [
        '#type' => 'table',
        '#header' => [$this->t('Cluster'), $this->t('Code'), $this->t('Status'), $this->t('Woningen'), $this->t('Productposities')],
        '#rows' => $cluster_rows,
        '#empty' => $this->t('Nog geen clusters.'),
      ],
      'dwellings_heading' => ['#markup' => '<h2>' . $this->t('Woningen') . '</h2>'],
      'dwellings' => [
        '#type' => 'table',
        '#header' => [$this->t('Woning'), $this->t('Cluster'), $this->t('Status'), $this->t('Posities'), $this->t('Controles'), $this->t('Geblokkeerd')],
        '#rows' => $dwelling_rows,
        '#empty' => $this->t('Nog geen woningen.'),
      ],
      'types_heading' => ['#markup' => '<h2>' . $this->t('Producttypen') . '</h2>'],
      'types' => [
        '#type' => 'table',
        '#header' => [$this->t('Producttype'), $this->t('Aantal posities')],
        '#rows' => $type_rows,
        '#empty' => $this->t('Nog geen productposities.'),
      ],
      '#cache' => [
        'contexts' => ['user.permissions'],
        'tags' => [
          'node_list:brebo_cluster',
          'node_list:brebo_dwelling',
          'node_list:brebo_product_position',
          'node_list:brebo_verification',
          'node_list:brebo_deviation',
          'node_list:brebo_route_item',
        ],
      ],
    ];

    $tab_panels = [
      'project' => 'overview',
      'buildings_heading' => 'overview',
      'buildings' => 'overview',
      'summary' => 'overview',
      'operating_model_heading' => 'governance',
      'operating_model' => 'governance',
      'procurement' => 'governance',
      'authority' => 'governance',
      'route_heading' => 'route',
      'route_summary' => 'route',
      'route' => 'route',
      'clusters_heading' => 'objects',
      'clusters' => 'objects',
      'dwellings_heading' => 'objects',
      'dwellings' => 'objects',
      'types_heading' => 'objects',
      'types' => 'objects',
    ];
    foreach ($tab_panels as $key => $tab_id) {
      if (!isset($build[$key])) {
        continue;
      }
      $content = $build[$key];
      $build[$key] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['brebo-tab-panel'],
          'data-brebo-tab-panel' => $tab_id,
          'role' => 'tabpanel',
        ],
        'content' => $content,
      ];
    }

    return $build;
  }

  /**
   * Builds the cross-project route action list for daily steering.
   */
  public function regieActions(): array {
    $storage = $this->entityTypeManager()->getStorage('node');
    $ids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'brebo_route_item')
      ->condition('field_brebo_route_applicable', 1)
      ->condition('field_brebo_route_status', ['Gereed', 'N.V.T.', 'Vervallen'], 'NOT IN')
      ->sort('field_brebo_route_due', 'ASC')
      ->sort('field_brebo_route_sequence', 'ASC')
      ->execute();

    $today = date('Y-m-d');
    $rows = [];
    $overdue = 0;
    $blocked = 0;
    $unassigned = 0;
    $mine = 0;
    foreach ($storage->loadMultiple($ids) as $item) {
      if (!$item instanceof NodeInterface) {
        continue;
      }
      $project = $item->get('field_brebo_project_ref')->entity;
      $owner = $item->get('field_brebo_route_owner')->entity;
      $due = $this->fieldValue($item, 'field_brebo_route_due');
      $status = $this->fieldValue($item, 'field_brebo_route_status');
      $is_overdue = $due !== '—' && $due < $today;
      $is_blocked = $status === 'Geblokkeerd';
      $is_unassigned = !$owner;
      $is_mine = $owner && (int) $owner->id() === (int) $this->currentUser()->id();
      $overdue += $is_overdue ? 1 : 0;
      $blocked += $is_blocked ? 1 : 0;
      $unassigned += $is_unassigned ? 1 : 0;
      $mine += $is_mine ? 1 : 0;

      $attention = [];
      if ($is_blocked) {
        $attention[] = (string) $this->t('Geblokkeerd');
      }
      if ($is_overdue) {
        $attention[] = (string) $this->t('Te laat');
      }
      if ($is_unassigned) {
        $attention[] = (string) $this->t('Niet toegewezen');
      }
      if (!$attention) {
        $attention[] = (string) $this->t('Binnen termijn');
      }

      $rows[] = [
        ['data' => $project instanceof NodeInterface
          ? Link::fromTextAndUrl(
            $project->label(),
            Url::fromRoute('brebo_office_core.project_dashboard', ['node' => $project->id()])
          )->toRenderable()
          : '—'],
        $this->fieldValue($item, 'field_brebo_route_code'),
        $this->fieldValue($item, 'field_brebo_route_kind'),
        $this->fieldValue($item, 'field_brebo_route_description'),
        $owner ? $owner->label() : '—',
        $due,
        $status,
        implode(', ', $attention),
        ['data' => Link::fromTextAndUrl(
          $this->t('Bijwerken'),
          Url::fromRoute('entity.node.edit_form', ['node' => $item->id()])
        )->toRenderable()],
      ];
    }

    return [
      'intro' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['messages', 'messages--status']],
        'text' => [
          '#markup' => $this->t('Centrale actielijst over alle actieve projectroutes. Afgeronde, N.V.T.-verklaarde en vervallen onderdelen worden niet getoond.'),
        ],
      ],
      'summary' => [
        '#type' => 'table',
        '#header' => [
          $this->t('Open'), $this->t('Mijn acties'), $this->t('Te laat'),
          $this->t('Geblokkeerd'), $this->t('Niet toegewezen'),
        ],
        '#rows' => [[count($rows), $mine, $overdue, $blocked, $unassigned]],
      ],
      'heading' => ['#markup' => '<h2>' . $this->t('Open regie-acties') . '</h2>'],
      'actions' => [
        '#type' => 'table',
        '#header' => [
          $this->t('Project'), $this->t('Code'), $this->t('Soort'),
          $this->t('Vereist resultaat'), $this->t('Verantwoordelijke'),
          $this->t('Streefdatum'), $this->t('Status'),
          $this->t('Aandacht'), $this->t('Actie'),
        ],
        '#rows' => $rows,
        '#empty' => $this->t('Er zijn geen open route-acties.'),
      ],
      '#cache' => [
        'contexts' => ['user.permissions', 'user'],
        'tags' => ['node_list:brebo_route_item', 'node_list:brebo_project'],
      ],
    ];
  }

  /**
   * Builds the central BREBO quality dashboard.
   */
  public function qualityDashboard(): array {
    $storage = $this->entityTypeManager()->getStorage('node');

    $count = static function (string $bundle, array $conditions = []) use ($storage): int {
      $query = $storage->getQuery()
        ->accessCheck(TRUE)
        ->condition('type', $bundle);
      foreach ($conditions as [$field, $value, $operator]) {
        $query->condition($field, $value, $operator);
      }
      return (int) $query->count()->execute();
    };

    $control_total = $count('brebo_verification');
    $approved = $count('brebo_verification', [
      ['field_brebo_control_result', 'Akkoord', '='],
    ]);
    $control_deviations = $count('brebo_verification', [
      ['field_brebo_control_result', 'Afwijking', '='],
    ]);
    $blocked = $count('brebo_verification', [
      ['field_brebo_blocks_release', 1, '='],
    ]);
    $open_total = $count('brebo_deviation', [
      ['field_brebo_deviation_status', 'Gesloten', '<>'],
    ]);

    $open_ids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'brebo_deviation')
      ->condition('field_brebo_deviation_status', 'Gesloten', '<>')
      ->sort('field_brebo_due_date', 'ASC')
      ->execute();

    $today = date('Y-m-d');
    $overdue = 0;
    $rows = [];
    foreach ($storage->loadMultiple($open_ids) as $deviation) {
      if (!$deviation instanceof NodeInterface) {
        continue;
      }
      $control = $deviation->get('field_brebo_control_ref')->entity;
      $position = $control instanceof NodeInterface
        ? $control->get('field_brebo_position_ref')->entity
        : NULL;
      $deadline = $this->fieldValue($deviation, 'field_brebo_due_date');
      $is_overdue = $deadline !== '—' && $deadline < $today;
      $overdue += $is_overdue ? 1 : 0;
      $responsible = $deviation->get('field_brebo_responsible')->entity;

      $rows[] = [
        ['data' => Link::fromTextAndUrl($deviation->label(), $deviation->toUrl())->toRenderable()],
        $position instanceof NodeInterface
          ? $this->fieldValue($position, 'field_brebo_position_code')
          : '—',
        $this->fieldValue($deviation, 'field_brebo_deviation_status'),
        $responsible ? $responsible->label() : '—',
        $deadline,
        $is_overdue ? (string) $this->t('Te laat') : (string) $this->t('Binnen termijn'),
        ['data' => Link::fromTextAndUrl(
          $this->t('Bewerken'),
          Url::fromRoute('entity.node.edit_form', ['node' => $deviation->id()])
        )->toRenderable()],
        ['data' => $position instanceof NodeInterface
          ? Link::fromTextAndUrl(
            $this->t('Hercontrole toevoegen'),
            Url::fromRoute('node.add', ['node_type' => 'brebo_verification'], [
              'query' => ['position' => $position->id()],
            ])
          )->toRenderable()
          : '—'],
      ];
    }

    return [
      'summary' => [
        '#type' => 'table',
        '#header' => [
          $this->t('Controles'),
          $this->t('Akkoord'),
          $this->t('Afwijkend'),
          $this->t('Open afwijkingen'),
          $this->t('Vrijgave geblokkeerd'),
          $this->t('Te laat'),
        ],
        '#rows' => [[
          $control_total,
          $approved,
          $control_deviations,
          $open_total,
          $blocked,
          $overdue,
        ]],
      ],
      'heading' => [
        '#type' => 'html_tag',
        '#tag' => 'h2',
        '#value' => $this->t('Open afwijkingen'),
      ],
      'deviations' => [
        '#type' => 'table',
        '#header' => [
          $this->t('Afwijking'),
          $this->t('Productpositie'),
          $this->t('Status'),
          $this->t('Verantwoordelijke'),
          $this->t('Deadline'),
          $this->t('Termijn'),
          $this->t('Actie'),
          $this->t('Hercontrole'),
        ],
        '#rows' => $rows,
        '#empty' => $this->t('Er zijn geen open afwijkingen.'),
      ],
      '#cache' => [
        'contexts' => ['user.permissions'],
        'tags' => [
          'node_list:brebo_verification',
          'node_list:brebo_deviation',
        ],
      ],
    ];
  }



  /**
   * Builds a live, printable quality report from current project data.
   */
  public function qualityReport(): array {
    $storage = $this->entityTypeManager()->getStorage('node');
    $verification_ids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'brebo_verification')
      ->sort('created', 'ASC')
      ->execute();
    $deviation_ids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'brebo_deviation')
      ->sort('created', 'ASC')
      ->execute();

    $controls = $storage->loadMultiple($verification_ids);
    $deviations = $storage->loadMultiple($deviation_ids);
    $approved = 0;
    $rejected = 0;
    $not_applicable = 0;
    $blocked = 0;
    $control_rows = [];
    $evidence = [];

    foreach ($controls as $control) {
      if (!$control instanceof NodeInterface) {
        continue;
      }
      $result = $this->fieldValue($control, 'field_brebo_control_result');
      $approved += $result === 'Akkoord' ? 1 : 0;
      $rejected += $result === 'Afwijking' ? 1 : 0;
      $not_applicable += $result === 'N.V.T.' ? 1 : 0;
      $is_blocked = (bool) $control->get('field_brebo_blocks_release')->value;
      $blocked += $is_blocked ? 1 : 0;

      $position = $control->get('field_brebo_position_ref')->entity;
      $dwelling = $position instanceof NodeInterface
        ? $position->get('field_brebo_dwelling_ref')->entity
        : NULL;
      $cluster = $dwelling instanceof NodeInterface
        ? $dwelling->get('field_brebo_cluster_ref')->entity
        : NULL;
      $project = $cluster instanceof NodeInterface
        ? $cluster->get('field_brebo_project_ref')->entity
        : NULL;

      $control_rows[] = [
        $project instanceof NodeInterface ? $project->label() : '—',
        $dwelling instanceof NodeInterface
          ? $this->fieldValue($dwelling, 'field_brebo_dwelling_code')
          : '—',
        $position instanceof NodeInterface
          ? $this->fieldValue($position, 'field_brebo_position_code')
          : '—',
        $this->fieldValue($control, 'field_brebo_control_type'),
        $this->fieldValue($control, 'field_brebo_control_source'),
        $result,
        $control->getOwner()?->getDisplayName() ?? '—',
        \Drupal::service('date.formatter')->format($control->getCreatedTime(), 'short'),
        $is_blocked ? (string) $this->t('Geblokkeerd') : (string) $this->t('Vrij'),
      ];

      if ($control->hasField('field_brebo_evidence')
        && !$control->get('field_brebo_evidence')->isEmpty()) {
        $evidence['control_' . $control->id()] = [
          '#type' => 'details',
          '#title' => $this->t('@control — bewijs', ['@control' => $control->label()]),
          '#open' => TRUE,
          'images' => $control->get('field_brebo_evidence')->view([
            'type' => 'image',
            'label' => 'hidden',
            'settings' => ['image_style' => 'medium'],
          ]),
        ];
      }
    }

    $open_deviations = 0;
    $deviation_rows = [];
    foreach ($deviations as $deviation) {
      if (!$deviation instanceof NodeInterface) {
        continue;
      }
      $status = $this->fieldValue($deviation, 'field_brebo_deviation_status');
      $open_deviations += $status !== 'Gesloten' ? 1 : 0;
      $control = $deviation->get('field_brebo_control_ref')->entity;
      $position = $control instanceof NodeInterface
        ? $control->get('field_brebo_position_ref')->entity
        : NULL;
      $responsible = $deviation->get('field_brebo_responsible')->entity;
      $recheck = $deviation->get('field_brebo_recheck_ref')->entity;

      $deviation_rows[] = [
        $deviation->label(),
        $position instanceof NodeInterface
          ? $this->fieldValue($position, 'field_brebo_position_code')
          : '—',
        $status,
        $responsible ? $responsible->label() : '—',
        $this->fieldValue($deviation, 'field_brebo_due_date'),
        $this->fieldValue($deviation, 'field_brebo_corrective_action'),
        $recheck instanceof NodeInterface
          ? $this->fieldValue($recheck, 'field_brebo_control_result')
          : '—',
      ];

      if ($deviation->hasField('field_brebo_recovery_evidence')
        && !$deviation->get('field_brebo_recovery_evidence')->isEmpty()) {
        $evidence['deviation_' . $deviation->id()] = [
          '#type' => 'details',
          '#title' => $this->t('@deviation — herstelbewijs', [
            '@deviation' => $deviation->label(),
          ]),
          '#open' => TRUE,
          'images' => $deviation->get('field_brebo_recovery_evidence')->view([
            'type' => 'image',
            'label' => 'hidden',
            'settings' => ['image_style' => 'medium'],
          ]),
        ];
      }
    }

    $build = [
      'metadata' => [
        '#type' => 'table',
        '#rows' => [
          [$this->t('Rapport'), $this->t('BREBO kwaliteitsrapportage')],
          [$this->t('Status'), $this->t('Live – actuele databasegegevens')],
          [$this->t('Gegenereerd'), \Drupal::service('date.formatter')->format(time(), 'long')],
          [$this->t('Gegenereerd door'), $this->currentUser()->getDisplayName()],
        ],
      ],
      'summary_heading' => [
        '#type' => 'html_tag',
        '#tag' => 'h2',
        '#value' => $this->t('Managementsamenvatting'),
      ],
      'summary' => [
        '#type' => 'table',
        '#header' => [
          $this->t('Controles'),
          $this->t('Akkoord'),
          $this->t('Afwijkend'),
          $this->t('N.V.T.'),
          $this->t('Open afwijkingen'),
          $this->t('Geblokkeerde vrijgaven'),
        ],
        '#rows' => [[
          count($controls),
          $approved,
          $rejected,
          $not_applicable,
          $open_deviations,
          $blocked,
        ]],
      ],
      'controls_heading' => [
        '#type' => 'html_tag',
        '#tag' => 'h2',
        '#value' => $this->t('Controleoverzicht'),
      ],
      'controls' => [
        '#type' => 'table',
        '#header' => [
          $this->t('Project'),
          $this->t('Woning'),
          $this->t('Positie'),
          $this->t('Type'),
          $this->t('Bron en versie'),
          $this->t('Resultaat'),
          $this->t('Controleur'),
          $this->t('Datum'),
          $this->t('Vrijgave'),
        ],
        '#rows' => $control_rows,
        '#empty' => $this->t('Er zijn nog geen controles geregistreerd.'),
      ],
      'deviations_heading' => [
        '#type' => 'html_tag',
        '#tag' => 'h2',
        '#value' => $this->t('Afwijkingen en herstel'),
      ],
      'deviations' => [
        '#type' => 'table',
        '#header' => [
          $this->t('Afwijking'),
          $this->t('Positie'),
          $this->t('Status'),
          $this->t('Verantwoordelijke'),
          $this->t('Deadline'),
          $this->t('Herstelactie'),
          $this->t('Hercontrole'),
        ],
        '#rows' => $deviation_rows,
        '#empty' => $this->t('Er zijn geen afwijkingen geregistreerd.'),
      ],
    ];

    if ($evidence) {
      $build['evidence_heading'] = [
        '#type' => 'html_tag',
        '#tag' => 'h2',
        '#value' => $this->t('Foto- en herstelbewijs'),
      ];
      $build['evidence'] = $evidence;
    }

    $build['#cache'] = ['max-age' => 0];
    return $build;
  }



  /**
   * Returns the calculation dashboard title.
   */
  public function calculationDashboardTitle(NodeInterface $node): string {
    if ($node->bundle() !== 'brebo_calculation') {
      throw new NotFoundHttpException();
    }
    return (string) $node->label();
  }

  /**
   * Builds a calculation dashboard with hierarchical adjustments.
   */
  public function calculationDashboard(NodeInterface $node): array {
    if ($node->bundle() !== 'brebo_calculation') {
      throw new NotFoundHttpException();
    }

    $storage = $this->entityTypeManager()->getStorage('node');
    $element_ids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'brebo_calc_element')
      ->condition('field_brebo_calculation_ref.target_id', $node->id())
      ->sort('field_brebo_element_sequence')
      ->execute();
    $elements = $storage->loadMultiple($element_ids);

    $line_ids = $element_ids
      ? $storage->getQuery()
        ->accessCheck(TRUE)
        ->condition('type', 'brebo_calc_line')
        ->condition('field_brebo_calc_element_ref.target_id', array_values($element_ids), 'IN')
        ->execute()
      : [];
    $lines = $storage->loadMultiple($line_ids);

    $package = $node->get('field_brebo_package_ref')->entity;
    $target_ids = array_values(array_unique(array_merge(
      [(int) $node->id()],
      $package instanceof NodeInterface ? [(int) $package->id()] : [],
      array_map('intval', array_values($element_ids)),
      array_map('intval', array_values($line_ids)),
    )));
    $adjustment_ids = $target_ids
      ? $storage->getQuery()
        ->accessCheck(TRUE)
        ->condition('type', 'brebo_calc_adjustment')
        ->condition('field_brebo_adjust_target.target_id', $target_ids, 'IN')
        ->sort('field_brebo_adjust_sequence')
        ->execute()
      : [];

    $adjustments_by_target = [];
    foreach ($storage->loadMultiple($adjustment_ids) as $adjustment) {
      if ($adjustment instanceof NodeInterface) {
        $target_id = (int) $adjustment->get('field_brebo_adjust_target')->target_id;
        $adjustments_by_target[$target_id][] = $adjustment;
      }
    }

    foreach ($adjustments_by_target as &$target_adjustments) {
      usort($target_adjustments, static function (NodeInterface $left, NodeInterface $right): int {
        return (int) $left->get('field_brebo_adjust_sequence')->value
          <=> (int) $right->get('field_brebo_adjust_sequence')->value;
      });
    }
    unset($target_adjustments);

    $lines_by_element = [];
    foreach ($lines as $line) {
      if ($line instanceof NodeInterface) {
        $element_id = (int) $line->get('field_brebo_calc_element_ref')->target_id;
        $lines_by_element[$element_id][] = $line;
      }
    }

    $contract_element_totals = [];
    $forecast_element_totals = [];
    $calculation_contract_breakdown = [];
    $calculation_forecast_breakdown = [];
    $element_rows = [];
    $line_rows = [];
    $post_totals = [];

    foreach ($elements as $element) {
      if (!$element instanceof NodeInterface) {
        continue;
      }
      $contract_current = 0.0;
      $forecast_current = 0.0;
      $contract_breakdown = [];
      $forecast_breakdown = [];

      foreach ($lines_by_element[(int) $element->id()] ?? [] as $line) {
        $quantity = (float) $line->get('field_brebo_contract_quantity')->value;
        $actual_raw = $line->get('field_brebo_actual_quantity')->value;
        $post_type = $this->fieldValue($line, 'field_brebo_line_post_type');
        $forecast_quantity = $post_type === 'Verrekenpost' && $actual_raw !== NULL && $actual_raw !== ''
          ? (float) $actual_raw
          : $quantity;
        $unit_price = (float) $line->get('field_brebo_unit_price')->value;
        $category = $this->fieldValue($line, 'field_brebo_cost_category');
        $contract_direct = $quantity * $unit_price;
        $forecast_direct = $forecast_quantity * $unit_price;

        [$contract_line_total] = $this->applyAdjustments(
          $contract_direct,
          [$category => $contract_direct],
          $adjustments_by_target[(int) $line->id()] ?? [],
        );
        [$forecast_line_total] = $this->applyAdjustments(
          $forecast_direct,
          [$category => $forecast_direct],
          $adjustments_by_target[(int) $line->id()] ?? [],
        );

        $contract_current += $contract_line_total;
        $forecast_current += $forecast_line_total;
        $markup_applicable = !$line->hasField('field_brebo_markup_applicable')
          || (bool) $line->get('field_brebo_markup_applicable')->value;
        if ($markup_applicable) {
          $contract_breakdown[$category] = ($contract_breakdown[$category] ?? 0.0) + $contract_direct;
          $forecast_breakdown[$category] = ($forecast_breakdown[$category] ?? 0.0) + $forecast_direct;
        }
        $post_totals[$post_type] = ($post_totals[$post_type] ?? 0.0) + $forecast_line_total;

        $line_rows[] = [
          $this->fieldValue($element, 'field_brebo_element_code'),
          $this->fieldValue($line, 'field_brebo_line_description'),
          $post_type,
          $category,
          $this->money($contract_line_total),
          $this->money($forecast_line_total),
          $this->money($forecast_line_total - $contract_line_total),
          ['data' => Link::fromTextAndUrl(
            $this->t('Bewerken'),
            Url::fromRoute('entity.node.edit_form', ['node' => $line->id()])
          )->toRenderable()],
        ];
      }

      [$contract_element_total] = $this->applyAdjustments(
        $contract_current,
        $contract_breakdown,
        $adjustments_by_target[(int) $element->id()] ?? [],
      );
      [$forecast_element_total] = $this->applyAdjustments(
        $forecast_current,
        $forecast_breakdown,
        $adjustments_by_target[(int) $element->id()] ?? [],
      );
      $contract_element_totals[] = $contract_element_total;
      $forecast_element_totals[] = $forecast_element_total;
      foreach ($contract_breakdown as $category => $value) {
        $calculation_contract_breakdown[$category] = ($calculation_contract_breakdown[$category] ?? 0.0) + $value;
      }
      foreach ($forecast_breakdown as $category => $value) {
        $calculation_forecast_breakdown[$category] = ($calculation_forecast_breakdown[$category] ?? 0.0) + $value;
      }
      $element_rows[] = [
        ['data' => Link::fromTextAndUrl($element->label(), $element->toUrl())->toRenderable()],
        $this->fieldValue($element, 'field_brebo_element_code'),
        count($lines_by_element[(int) $element->id()] ?? []),
        $this->money($contract_element_total),
        $this->money($forecast_element_total),
        $this->money($forecast_element_total - $contract_element_total),
      ];
    }

    $contract_total = array_sum($contract_element_totals);
    $forecast_total = array_sum($forecast_element_totals);
    if ($package instanceof NodeInterface) {
      [$contract_total] = $this->applyAdjustments(
        $contract_total,
        $calculation_contract_breakdown,
        $adjustments_by_target[(int) $package->id()] ?? [],
      );
      [$forecast_total] = $this->applyAdjustments(
        $forecast_total,
        $calculation_forecast_breakdown,
        $adjustments_by_target[(int) $package->id()] ?? [],
      );
    }
    [$contract_total] = $this->applyAdjustments(
      $contract_total,
      $calculation_contract_breakdown,
      $adjustments_by_target[(int) $node->id()] ?? [],
    );
    [$forecast_total] = $this->applyAdjustments(
      $forecast_total,
      $calculation_forecast_breakdown,
      $adjustments_by_target[(int) $node->id()] ?? [],
    );

    $post_rows = [];
    ksort($post_totals);
    foreach ($post_totals as $post_type => $value) {
      $post_rows[] = [$post_type, $this->money($value)];
    }

    return [
      'actions' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['brebo-list-actions']],
        'edit' => [
          '#type' => 'link', '#title' => $this->t('Calculatie bewerken'),
          '#url' => Url::fromRoute('entity.node.edit_form', ['node' => $node->id()]),
          '#attributes' => ['class' => ['button']],
        ],
        'add_adjustment' => [
          '#type' => 'link', '#title' => $this->t('Opslag toevoegen'),
          '#url' => Url::fromRoute('node.add', ['node_type' => 'brebo_calc_adjustment'], [
            'query' => ['target' => $node->id()],
          ]),
          '#attributes' => ['class' => ['button']],
        ],
        'generate_work_budget' => [
          '#type' => 'link', '#title' => $this->t('Werkbegroting maken'),
          '#url' => Url::fromRoute('brebo_office_core.generate_work_budget', ['node' => $node->id()]),
          '#attributes' => ['class' => ['button']],
          '#access' => in_array($this->fieldValue($node, 'field_brebo_calc_status'), ['Vastgesteld', 'Definitief budget'], TRUE)
            && $node->access('update'),
        ],
      ],
      'calculation' => [
        '#type' => 'table',
        '#header' => [$this->t('Code'), $this->t('Versie'), $this->t('Status'), $this->t('Prijspeildatum'), $this->t('Werkpakket')],
        '#rows' => [[
          $this->fieldValue($node, 'field_brebo_calc_code'),
          $this->fieldValue($node, 'field_brebo_calc_version'),
          $this->fieldValue($node, 'field_brebo_calc_status'),
          $this->fieldValue($node, 'field_brebo_price_date'),
          $package ? $package->label() : '—',
        ]],
      ],
      'summary' => [
        '#type' => 'table',
        '#header' => [$this->t('Elementen'), $this->t('Regels'), $this->t('Contractwaarde'), $this->t('Actuele prognose'), $this->t('Verschil')],
        '#rows' => [[
          count($elements), count($lines), $this->money($contract_total),
          $this->money($forecast_total), $this->money($forecast_total - $contract_total),
        ]],
      ],
      'elements_heading' => ['#markup' => '<h2>' . $this->t('Elementen') . '</h2>'],
      'elements' => [
        '#type' => 'table',
        '#header' => [$this->t('Element'), $this->t('Code'), $this->t('Regels'), $this->t('Contract'), $this->t('Prognose'), $this->t('Verschil')],
        '#rows' => $element_rows, '#empty' => $this->t('Nog geen calculatie-elementen.'),
      ],
      'posts_heading' => ['#markup' => '<h2>' . $this->t('Posttypen') . '</h2>'],
      'posts' => [
        '#type' => 'table', '#header' => [$this->t('Posttype'), $this->t('Actuele prognose')],
        '#rows' => $post_rows, '#empty' => $this->t('Nog geen calculatieregels.'),
      ],
      'lines_heading' => ['#markup' => '<h2>' . $this->t('Calculatieregels') . '</h2>'],
      'lines' => [
        '#type' => 'table',
        '#header' => [
          $this->t('Element'), $this->t('Omschrijving'), $this->t('Posttype'), $this->t('Kostensoort'),
          $this->t('Contract'), $this->t('Prognose'), $this->t('Verschil'), $this->t('Actie'),
        ],
        '#rows' => $line_rows, '#empty' => $this->t('Nog geen calculatieregels.'),
      ],
      '#cache' => [
        'contexts' => ['user.permissions'],
        'tags' => [
          'node_list:brebo_calculation', 'node_list:brebo_calc_element',
          'node_list:brebo_calc_line', 'node_list:brebo_calc_adjustment',
        ],
      ],
    ];
  }

  /**
   * Returns the work-budget dashboard title.
   */
  public function workBudgetDashboardTitle(NodeInterface $node): string {
    if ($node->bundle() !== 'brebo_work_budget') {
      throw new NotFoundHttpException();
    }
    return (string) $node->label();
  }

  /**
   * Builds the execution-only work-budget dashboard.
   */
  public function workBudgetDashboard(NodeInterface $node): array {
    if ($node->bundle() !== 'brebo_work_budget') {
      throw new NotFoundHttpException();
    }

    $storage = $this->entityTypeManager()->getStorage('node');
    $line_ids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'brebo_work_budget_line')
      ->condition('field_brebo_work_budget_ref.target_id', $node->id())
      ->execute();
    $lines = $storage->loadMultiple($line_ids);

    uasort($lines, static function (NodeInterface $left, NodeInterface $right): int {
      $left_source = $left->get('field_brebo_calc_line_ref')->entity;
      $right_source = $right->get('field_brebo_calc_line_ref')->entity;
      $left_sequence = $left_source instanceof NodeInterface
        ? (int) $left_source->get('field_brebo_line_sequence')->value
        : 0;
      $right_sequence = $right_source instanceof NodeInterface
        ? (int) $right_source->get('field_brebo_line_sequence')->value
        : 0;
      return $left_sequence <=> $right_sequence;
    });

    $budget_hours = 0.0;
    $actual_hours = 0.0;
    $execution_rows = [];
    $material_groups = [];

    foreach ($lines as $line) {
      if (!$line instanceof NodeInterface) {
        continue;
      }
      $source = $line->get('field_brebo_calc_line_ref')->entity;
      $responsible = $line->get('field_brebo_responsible_user')->entity;
      $line_budget_hours = (float) $line->get('field_brebo_budget_hours')->value;
      $line_actual_hours = (float) $line->get('field_brebo_actual_hours')->value;
      $budget_hours += $line_budget_hours;
      $actual_hours += $line_actual_hours;

      $description = $source instanceof NodeInterface
        ? $this->fieldValue($source, 'field_brebo_line_description')
        : $line->label();
      $category = $source instanceof NodeInterface
        ? $this->fieldValue($source, 'field_brebo_cost_category')
        : '—';
      $post_type = $source instanceof NodeInterface
        ? $this->fieldValue($source, 'field_brebo_line_post_type')
        : '—';

      $execution_rows[] = [
        $description,
        $category,
        $post_type,
        number_format($line_budget_hours, 2, ',', '.'),
        number_format($line_actual_hours, 2, ',', '.'),
        number_format($line_budget_hours - $line_actual_hours, 2, ',', '.'),
        $this->fieldValue($line, 'field_brebo_required_date'),
        $responsible ? $responsible->label() : '—',
        $this->fieldValue($line, 'field_brebo_execution_status'),
        ['data' => Link::fromTextAndUrl(
          $this->t('Bijwerken'),
          Url::fromRoute('entity.node.edit_form', ['node' => $line->id()])
        )->toRenderable()],
      ];

      if (!$line->get('field_brebo_material_description')->isEmpty()) {
        $code = $this->fieldValue($line, 'field_brebo_material_code');
        $specification = $this->fieldValue($line, 'field_brebo_material_spec');
        $unit = $this->fieldValue($line, 'field_brebo_material_unit');
        $waste = (float) $line->get('field_brebo_waste_percent')->value;
        $pack = (float) $line->get('field_brebo_pack_quantity')->value;
        $supplier = $this->fieldValue($line, 'field_brebo_preferred_supplier');
        $location = $this->fieldValue($line, 'field_brebo_delivery_location');
        $key = json_encode([$code, $specification, $unit, $waste, $pack, $supplier, $location]);
        $material_groups[$key] ??= [
          'code' => $code,
          'description' => $this->fieldValue($line, 'field_brebo_material_description'),
          'specification' => $specification,
          'unit' => $unit,
          'waste' => $waste,
          'pack' => $pack,
          'supplier' => $supplier,
          'location' => $location,
          'net' => 0.0,
          'sources' => 0,
          'status' => $this->fieldValue($line, 'field_brebo_order_status'),
        ];
        $material_groups[$key]['net'] += (float) $line->get('field_brebo_material_quantity')->value;
        $material_groups[$key]['sources']++;
      }
    }

    $material_rows = [];
    foreach ($material_groups as $material) {
      $gross = $material['net'] * (1 + ($material['waste'] / 100));
      $packages = $material['pack'] > 0 ? (int) ceil($gross / $material['pack']) : NULL;
      $material_rows[] = [
        $material['code'],
        $material['description'],
        $material['specification'],
        number_format($material['net'], 2, ',', '.'),
        number_format($material['waste'], 2, ',', '.') . '%',
        number_format($gross, 2, ',', '.'),
        $material['unit'],
        $material['pack'] > 0 ? number_format($material['pack'], 2, ',', '.') : '—',
        $packages ?? '—',
        $material['supplier'],
        $material['location'],
        $material['status'],
        $material['sources'],
      ];
    }

    $calculation = $node->get('field_brebo_calculation_ref')->entity;
    $package = $node->get('field_brebo_package_ref')->entity;

    return [
      'actions' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['brebo-list-actions']],
        'edit' => [
          '#type' => 'link',
          '#title' => $this->t('Werkbegroting beheren'),
          '#url' => Url::fromRoute('entity.node.edit_form', ['node' => $node->id()]),
          '#attributes' => ['class' => ['button']],
        ],
        'all' => [
          '#type' => 'link',
          '#title' => $this->t('Alle werkbegrotingen'),
          '#url' => Url::fromRoute('brebo_office_core.work_budgets'),
          '#attributes' => ['class' => ['button']],
        ],
      ],
      'identity' => [
        '#type' => 'table',
        '#header' => [
          $this->t('Versie'), $this->t('Status'), $this->t('Peildatum'),
          $this->t('Broncalculatie'), $this->t('Werkpakket'),
        ],
        '#rows' => [[
          $this->fieldValue($node, 'field_brebo_budget_version'),
          $this->fieldValue($node, 'field_brebo_budget_status'),
          $this->fieldValue($node, 'field_brebo_baseline_date'),
          ['data' => $calculation instanceof NodeInterface
            ? Link::fromTextAndUrl(
              $calculation->label(),
              Url::fromRoute('brebo_office_core.calculation_dashboard', ['node' => $calculation->id()])
            )->toRenderable()
            : '—'],
          ['data' => $package instanceof NodeInterface
            ? Link::fromTextAndUrl(
              $package->label(),
              Url::fromRoute('brebo_office_core.work_package_dashboard', ['node' => $package->id()])
            )->toRenderable()
            : '—'],
        ]],
      ],
      'summary' => [
        '#type' => 'table',
        '#header' => [
          $this->t('Uitvoeringsregels'), $this->t('Budgeturen'),
          $this->t('Werkelijke uren'), $this->t('Resterende uren'),
          $this->t('Materiaalregels'),
        ],
        '#rows' => [[
          count($execution_rows),
          number_format($budget_hours, 2, ',', '.'),
          number_format($actual_hours, 2, ',', '.'),
          number_format($budget_hours - $actual_hours, 2, ',', '.'),
          count($material_rows),
        ]],
      ],
      'execution_heading' => ['#markup' => '<h2>' . $this->t('Uitvoerderslijst') . '</h2>'],
      'execution' => [
        '#type' => 'table',
        '#header' => [
          $this->t('Werkzaamheden'), $this->t('Kostensoort'), $this->t('Posttype'),
          $this->t('Budgeturen'), $this->t('Werkelijk'), $this->t('Resterend'),
          $this->t('Benodigd op'), $this->t('Verantwoordelijke'),
          $this->t('Status'), $this->t('Actie'),
        ],
        '#rows' => $execution_rows,
        '#empty' => $this->t('Deze werkbegroting bevat nog geen uitvoeringsregels.'),
      ],
      'materials_heading' => ['#markup' => '<h2>' . $this->t('Cumulatieve materiaal- en bestellijst — werkvoorbereiding') . '</h2>'],
      'materials' => [
        '#type' => 'table',
        '#header' => [
          $this->t('Code'), $this->t('Materiaal'), $this->t('Specificatie'),
          $this->t('Netto'), $this->t('Verlies'), $this->t('Bruto'),
          $this->t('Eenheid'), $this->t('Per verpakking'), $this->t('Verpakkingen'),
          $this->t('Leverancier'), $this->t('Afleverlocatie'), $this->t('Bestelstatus'),
          $this->t('Bronregels'),
        ],
        '#rows' => $material_rows,
        '#empty' => $this->t('Er zijn nog geen materiaalregels opgenomen.'),
      ],
      'commercial_notice' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['messages', 'messages--status']],
        'text' => [
          '#markup' => $this->t('Deze uitvoerdersweergave bevat bewust geen verkoopprijzen, opslagen of marges.'),
        ],
      ],
      '#cache' => [
        'contexts' => ['user.permissions'],
        'tags' => [
          'node:' . $node->id(),
          'node_list:brebo_work_budget_line',
        ],
      ],
    ];
  }


  /**
   * Applies ordered fixed or percentage adjustments to a value.
   */
  private function applyAdjustments(float $current, array $direct_breakdown, array $adjustments): array {
    $adjustment_total = 0.0;
    foreach ($adjustments as $adjustment) {
      if (!$adjustment instanceof NodeInterface) {
        continue;
      }
      $method = $this->fieldValue($adjustment, 'field_brebo_adjust_method');
      $direction = $this->fieldValue($adjustment, 'field_brebo_adjust_direction');
      $base_name = $this->fieldValue($adjustment, 'field_brebo_adjust_base');
      $value = (float) $adjustment->get('field_brebo_adjust_value')->value;
      $cumulative = (bool) $adjustment->get('field_brebo_adjust_cumulative')->value;
      $sign = $direction === 'Korting' ? -1.0 : 1.0;

      if ($method === 'Vast bedrag') {
        $amount = $value;
      }
      else {
        $base = $base_name === 'Alle directe kosten'
          ? ($cumulative ? $current : array_sum($direct_breakdown))
          : ($direct_breakdown[$base_name] ?? 0.0);
        $amount = $base * ($value / 100);
      }
      $signed_amount = $sign * $amount;
      $current += $signed_amount;
      $adjustment_total += $signed_amount;
    }
    return [$current, $adjustment_total];
  }

  /**
   * Formats a monetary value for the Dutch interface.
   */
  private function money(float $value): string {
    return '€ ' . number_format($value, 2, ',', '.');
  }

  /**
   * Returns a scalar field value or a visible empty-state marker.
   */
  private function fieldValue(NodeInterface $node, string $field_name): string {
    return $node->hasField($field_name) && !$node->get($field_name)->isEmpty()
      ? (string) $node->get($field_name)->value
      : '—';
  }

  /**
   * Ensures only BREBO dwelling nodes use the dossier route.
   */
  private function assertDwelling(NodeInterface $node): void {
    if ($node->bundle() !== 'brebo_dwelling') {
      throw new NotFoundHttpException();
    }
  }

}
