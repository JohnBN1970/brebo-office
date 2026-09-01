<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/** Generic list/Kanban presentation for BREBO objects with workflow status. */
final class StatusPortfolioController extends ControllerBase {

  private const STATUS_FIELDS = [
    'brebo_building_zone' => 'field_brebo_status',
    'brebo_cluster' => 'field_brebo_status',
    'brebo_dwelling' => 'field_brebo_status',
    'brebo_product_position' => 'field_brebo_status',
    'brebo_verification' => 'field_brebo_control_result',
    'brebo_deviation' => 'field_brebo_deviation_status',
    'brebo_work_package' => 'field_brebo_package_status',
    'brebo_release_gate' => 'field_brebo_gate_result',
    'brebo_work_budget' => 'field_brebo_budget_status',
    'brebo_rfq' => 'field_brebo_rfq_status',
    'brebo_supplier_quote' => 'field_brebo_quote_status',
    'brebo_budget_change' => 'field_brebo_change_status',
    'brebo_route_item' => 'field_brebo_route_status',
    'brebo_project_scope' => 'field_brebo_scope_status',
    'brebo_organization' => 'field_brebo_org_status',
    'brebo_contact' => 'field_brebo_contact_active',
    'brebo_action' => 'field_brebo_action_status',
    'brebo_risk' => 'field_brebo_risk_status',
    'brebo_signal' => 'field_brebo_signal_status',
  ];

  public function __construct(
    private readonly EntityTypeManagerInterface $statusEntityTypeManager,
    private readonly DateFormatterInterface $statusDateFormatter,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('entity_type.manager'), $container->get('date.formatter'));
  }

  public function overview(string $bundle, Request $request): array {
    if (!isset(self::STATUS_FIELDS[$bundle])) return ['#markup' => ''];
    $view = $request->query->get('view') === 'kanban' ? 'kanban' : 'list';
    $storage = $this->statusEntityTypeManager->getStorage('node');
    $query = $storage->getQuery()->accessCheck(TRUE)->condition('type', $bundle)->sort('changed', 'DESC');
    if ($view === 'list') $query->pager(25); else $query->range(0, 500);

    $rows = [];
    $columns = [];
    foreach ($storage->loadMultiple($query->execute()) as $node) {
      if (!$node instanceof NodeInterface) continue;
      $status = $this->status($node, $bundle);
      $viewUrl = $this->viewUrl($bundle, $node);
      $editUrl = Url::fromRoute('entity.node.edit_form', ['node' => $node->id()]);
      $changed = $this->statusDateFormatter->format($node->getChangedTime(), 'short');
      $rows[] = $this->row($node, $bundle, $status, $viewUrl, $editUrl, $changed);
      $columns[$status][] = $this->card($node, $viewUrl, $editUrl, $changed);
    }
    ksort($columns, SORT_NATURAL | SORT_FLAG_CASE);

    $routeName = (string) $request->attributes->get('_route');
    $build = [
      '#type' => 'container', '#attributes' => ['class' => ['brebo-status-overview']], '#attached' => ['library' => ['brebo_office_core/status-list-kanban']],
      'actions' => ['#type' => 'container', '#attributes' => ['class' => ['brebo-status-overview__actions']],
        'add' => ['#type' => 'link', '#title' => $this->newLabel($bundle), '#url' => Url::fromRoute('node.add', ['node_type' => $bundle]), '#attributes' => ['class' => ['button']], '#access' => !in_array($bundle, ['brebo_work_budget', 'brebo_route_item'], TRUE)],
        'switch' => ['#type' => 'container', '#attributes' => ['class' => ['brebo-status-overview__switch'], 'aria-label' => $this->t('Weergave')],
          'list' => ['#type' => 'link', '#title' => $this->t('Lijst'), '#url' => Url::fromRoute($routeName, [], ['query' => ['view' => 'list']]), '#attributes' => ['class' => $view === 'list' ? ['is-active'] : []]],
          'kanban' => ['#type' => 'link', '#title' => $this->t('Kanban'), '#url' => Url::fromRoute($routeName, [], ['query' => ['view' => 'kanban']]), '#attributes' => ['class' => $view === 'kanban' ? ['is-active'] : []]],
        ],
      ],
    ];
    if ($view === 'list') {
      $build['table'] = ['#type' => 'table', '#header' => $this->header($bundle), '#rows' => $rows, '#empty' => $this->t('Nog geen gegevens aangemaakt.')];
      $build['pager'] = ['#type' => 'pager'];
    }
    else {
      $board = ['#type' => 'container', '#attributes' => ['class' => ['brebo-status-kanban']]];
      foreach ($columns as $status => $cards) {
        $board['status_' . md5($status)] = ['#type' => 'container', '#attributes' => ['class' => ['brebo-status-kanban__column']],
          'header' => ['#markup' => '<div class="brebo-status-kanban__header"><span>' . htmlspecialchars($status, ENT_QUOTES, 'UTF-8') . '</span><span class="brebo-status-kanban__count">' . count($cards) . '</span></div>'],
          'cards' => ['#type' => 'container', '#attributes' => ['class' => ['brebo-status-kanban__cards']]] + $cards,
        ];
      }
      if ($columns === []) $board['empty'] = ['#markup' => '<div class="brebo-status-kanban__empty">' . $this->t('Nog geen gegevens aangemaakt.') . '</div>'];
      $build['kanban'] = $board;
    }
    $build['#cache'] = ['contexts' => ['user.permissions', 'url.query_args:view', 'url.query_args:pagers'], 'tags' => ['node_list:' . $bundle]];
    return $build;
  }

  private function status(NodeInterface $node, string $bundle): string {
    if ($bundle === 'brebo_contact') return $node->hasField('field_brebo_contact_active') && (bool) $node->get('field_brebo_contact_active')->value ? (string) $this->t('Actief') : (string) $this->t('Inactief');
    $field = self::STATUS_FIELDS[$bundle];
    if (!$node->hasField($field) || $node->get($field)->isEmpty()) return (string) $this->t('Geen status');
    return trim((string) ($node->get($field)->value ?? '')) ?: (string) $this->t('Geen status');
  }

  private function viewUrl(string $bundle, NodeInterface $node): Url {
    return match ($bundle) {
      'brebo_organization' => Url::fromRoute('brebo_office_core.organization_dashboard', ['node' => $node->id()]),
      'brebo_contact' => Url::fromRoute('brebo_office_core.contact_dashboard', ['node' => $node->id()]),
      'brebo_work_package' => Url::fromRoute('brebo_office_core.work_package_dashboard', ['node' => $node->id()]),
      'brebo_work_budget' => Url::fromRoute('brebo_office_core.work_budget_dashboard', ['node' => $node->id()]),
      'brebo_dwelling' => Url::fromRoute('brebo_office_core.dwelling_dossier', ['node' => $node->id()]),
      default => $node->toUrl(),
    };
  }

  private function row(NodeInterface $node, string $bundle, string $status, Url $viewUrl, Url $editUrl, string $changed): array {
    $name = ['data' => Link::fromTextAndUrl($node->label(), $viewUrl)->toRenderable()];
    $edit = ['data' => Link::fromTextAndUrl($this->t('Bewerken'), $editUrl)->toRenderable()];
    if ($bundle === 'brebo_organization') return [$name, $this->fieldValue($node, 'field_brebo_org_type'), $status, $this->fieldValue($node, 'field_brebo_org_email'), $this->fieldValue($node, 'field_brebo_org_phone'), $changed, $edit];
    if ($bundle === 'brebo_contact') {
      $organization = $node->hasField('field_brebo_org_ref') ? $node->get('field_brebo_org_ref')->entity : NULL;
      $organizationCell = '—';
      if ($organization instanceof NodeInterface && $organization->bundle() === 'brebo_organization') $organizationCell = Link::fromTextAndUrl($organization->label(), Url::fromRoute('brebo_office_core.organization_dashboard', ['node' => $organization->id()]))->toRenderable();
      return [$name, ['data' => $organizationCell], $this->fieldValue($node, 'field_brebo_contact_role'), $this->fieldValue($node, 'field_brebo_contact_email'), $this->fieldValue($node, 'field_brebo_contact_phone'), $status, $changed, $edit];
    }
    return [$name, $status, $changed, $edit];
  }

  private function header(string $bundle): array {
    return match ($bundle) {
      'brebo_organization' => [$this->t('Organisatie'), $this->t('Type'), $this->t('Status'), $this->t('E-mail'), $this->t('Telefoon'), $this->t('Gewijzigd'), $this->t('Actie')],
      'brebo_contact' => [$this->t('Contactpersoon'), $this->t('Organisatie'), $this->t('Rol'), $this->t('E-mail'), $this->t('Telefoon'), $this->t('Status'), $this->t('Gewijzigd'), $this->t('Actie')],
      default => [$this->t('Naam'), $this->t('Status'), $this->t('Gewijzigd'), $this->t('Actie')],
    };
  }

  private function card(NodeInterface $node, Url $viewUrl, Url $editUrl, string $changed): array {
    return ['#type' => 'container', '#attributes' => ['class' => ['brebo-status-kanban__card']],
      'title' => ['#type' => 'link', '#title' => $node->label(), '#url' => $viewUrl, '#attributes' => ['class' => ['brebo-status-kanban__title']]],
      'meta' => ['#markup' => '<div class="brebo-status-kanban__meta"><span>' . $this->t('Gewijzigd: @date', ['@date' => $changed]) . '</span></div>'],
      'actions' => ['#type' => 'container', '#attributes' => ['class' => ['brebo-status-kanban__actions']], 'open' => ['#type' => 'link', '#title' => $this->t('Openen'), '#url' => $viewUrl], 'edit' => ['#type' => 'link', '#title' => $this->t('Bewerken'), '#url' => $editUrl]],
    ];
  }

  private function newLabel(string $bundle): string {
    return match ($bundle) {
      'brebo_organization' => (string) $this->t('Nieuwe organisatie'),
      'brebo_contact' => (string) $this->t('Nieuwe contactpersoon'),
      default => (string) $this->t('Nieuw @type', ['@type' => mb_strtolower((string) $this->statusEntityTypeManager->getStorage('node_type')->load($bundle)?->label())]),
    };
  }

  private function fieldValue(NodeInterface $node, string $field): string {
    if (!$node->hasField($field) || $node->get($field)->isEmpty()) return '—';
    return (string) ($node->get($field)->value ?? '—');
  }

}
