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
      $status = $this->fieldValue($node, 'field_brebo_status');
      $view_url = $bundle === 'brebo_dwelling'
        ? Url::fromRoute('brebo_office_core.dwelling_dossier', ['node' => $node->id()])
        : $node->toUrl();
      $rows[] = [
        Link::fromTextAndUrl($node->label(), $view_url)->toRenderable(),
        $status,
        \Drupal::service('date.formatter')->format($node->getChangedTime(), 'short'),
        Link::fromTextAndUrl($this->t('Bewerken'), Url::fromRoute('entity.node.edit_form', ['node' => $node->id()]))->toRenderable(),
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
        $facade = $this->t('Niet ingedeeld')->render();
      }

      $is_not_applicable = !$position->get('field_brebo_not_applicable')->isEmpty()
        && (bool) $position->get('field_brebo_not_applicable')->value;
      $photo_count = $position->hasField('field_brebo_photos')
        ? count($position->get('field_brebo_photos'))
        : 0;
      $not_applicable += $is_not_applicable ? 1 : 0;
      $with_photos += $photo_count > 0 ? 1 : 0;

      $status = $is_not_applicable
        ? $this->t('N.V.T.')->render()
        : $this->fieldValue($position, 'field_brebo_status');
      $photo_label = $photo_count === 1
        ? $this->t('1 foto')->render()
        : $this->t('@count foto’s', ['@count' => $photo_count])->render();

      $groups[$facade][] = [
        Link::fromTextAndUrl(
          $this->fieldValue($position, 'field_brebo_position_code'),
          $position->toUrl()
        )->toRenderable(),
        $this->fieldValue($position, 'field_brebo_quantity'),
        $this->fieldValue($position, 'field_brebo_width_mm'),
        $this->fieldValue($position, 'field_brebo_height_mm'),
        $this->fieldValue($position, 'field_brebo_glass_build'),
        $this->fieldValue($position, 'field_brebo_requirement'),
        $status,
        $photo_label,
        Link::fromTextAndUrl(
          $this->t('Bewerken'),
          Url::fromRoute('entity.node.edit_form', ['node' => $position->id()])
        )->toRenderable(),
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
        '#header' => [$this->t('Woningcode'), $this->t('Adres'), $this->t('Status'), $this->t('Posities'), $this->t('Met foto’s'), $this->t('N.V.T.')],
        '#rows' => [[
          $this->fieldValue($node, 'field_brebo_dwelling_code'),
          $this->fieldValue($node, 'field_brebo_address'),
          $this->fieldValue($node, 'field_brebo_status'),
          count($positions),
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
            $this->t('Actie'),
          ],
          '#rows' => $rows,
        ],
      ];
    }

    if (!$groups) {
      $build['empty'] = [
        '#markup' => '<p>' . $this->t('Aan deze woning zijn nog geen productposities gekoppeld.') . '</p>',
      ];
    }

    $build['#cache'] = [
      'contexts' => ['user.permissions'],
      'tags' => array_merge($node->getCacheTags(), ['node_list:brebo_product_position']),
    ];

    return $build;
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
