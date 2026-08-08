<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\user\Form\UserLoginForm;

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
      $status = $node->hasField('field_brebo_status') && !$node->get('field_brebo_status')->isEmpty()
        ? $node->get('field_brebo_status')->value
        : '—';
      $rows[] = [
        Link::fromTextAndUrl($node->label(), $node->toUrl())->toRenderable(),
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

}
