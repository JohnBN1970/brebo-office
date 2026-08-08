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
        default => 'field_brebo_status',
      };
      $status = $this->fieldValue($node, $status_field);
      $view_url = $bundle === 'brebo_dwelling'
        ? Url::fromRoute('brebo_office_core.dwelling_dossier', ['node' => $node->id()])
        : $node->toUrl();
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
