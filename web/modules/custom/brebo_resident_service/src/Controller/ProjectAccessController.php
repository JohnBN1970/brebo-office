<?php

declare(strict_types=1);

namespace Drupal\brebo_resident_service\Controller;

use Drupal\brebo_resident_service\Service\AccessContactResolver;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ProjectAccessController extends ControllerBase {

  public function __construct(
    private readonly Connection $database,
    private readonly AccessContactResolver $resolver,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('database'), $container->get('brebo_resident_service.access_contact_resolver'));
  }

  public function title(NodeInterface $node): string {
    return $this->t('Toegang & aanspreekpunten — @project', ['@project' => $node->label()]);
  }

  public function cockpit(NodeInterface $node): array {
    if ($node->bundle() !== 'brebo_project') {
      throw new NotFoundHttpException();
    }
    $projectId = (int) $node->id();
    $projectAccess = $this->resolver->resolve(NULL, NULL, NULL, $projectId);

    $counts = ['ready' => 0, 'to_arrange' => 0, 'no_contact' => 0, 'refused' => 0, 'blocked' => 0, 'unknown' => 0, 'not_needed' => 0];
    $buildingCounts = [];
    $rows = [];

    $query = $this->database->select('brebo_residence', 'r')->fields('r', ['id', 'building_nid', 'address_line', 'project_id']);
    $query->condition('r.project_id', $projectId)->orderBy('r.building_nid')->orderBy('r.street')->orderBy('r.house_number')->orderBy('r.addition');
    foreach ($query->execute() as $residence) {
      $effective = $this->resolver->resolve((int) $residence->building_nid, NULL, (int) $residence->id, $projectId);
      $status = $effective['access_status'] ?? 'unknown';
      $ready = $effective ? $this->resolver->isReady($effective) : FALSE;
      $bucket = $ready ? 'ready' : (array_key_exists($status, $counts) ? $status : 'unknown');
      $counts[$bucket]++;

      $buildingId = (int) $residence->building_nid;
      $buildingCounts[$buildingId] ??= ['total' => 0, 'ready' => 0, 'attention' => 0];
      $buildingCounts[$buildingId]['total']++;
      $buildingCounts[$buildingId][$ready ? 'ready' : 'attention']++;

      $address = Link::fromTextAndUrl((string) $residence->address_line, Url::fromRoute('brebo_resident_service.residence_detail', ['residence_id' => (int) $residence->id]))->toRenderable();
      $rows[] = [
        ['data' => $address],
        $buildingId,
        $effective['contact_name'] ?? '—',
        $effective['contact_role'] ?? '—',
        $effective['access_via'] ?? '—',
        ucfirst(str_replace('_', ' ', $status)),
        $effective['inherited_from'] ?? '—',
        $ready ? $this->t('Gereed') : $this->t('Aandacht'),
      ];
    }

    $buildingRows = [];
    foreach ($buildingCounts as $buildingId => $stats) {
      $label = $this->database->select('node_field_data', 'n')->fields('n', ['title'])->condition('nid', $buildingId)->execute()->fetchField() ?: ('#' . $buildingId);
      $buildingRows[] = [
        Link::fromTextAndUrl((string) $label, Url::fromRoute('brebo_resident_service.building_residents', ['node' => $buildingId]))->toRenderable(),
        $stats['total'], $stats['ready'], $stats['attention'],
      ];
    }

    $items = [
      $this->t('Gereed: @n', ['@n' => $counts['ready']]),
      $this->t('Nog regelen: @n', ['@n' => $counts['to_arrange']]),
      $this->t('Geen contact: @n', ['@n' => $counts['no_contact']]),
      $this->t('Geweigerd: @n', ['@n' => $counts['refused']]),
      $this->t('Geblokkeerd: @n', ['@n' => $counts['blocked']]),
      $this->t('Onbekend: @n', ['@n' => $counts['unknown']]),
    ];
    if ($projectAccess) {
      $items[] = $this->t('Projectaanspreekpunt: @name (@role)', ['@name' => $projectAccess['contact_name'] ?: '—', '@role' => $projectAccess['contact_role'] ?: '—']);
    }

    return [
      'actions' => [
        '#type' => 'container',
        'add' => Link::fromTextAndUrl($this->t('Projectaanspreekpunt / toegangsregel toevoegen'), Url::fromRoute('brebo_resident_service.access_contact_add', ['scope_type' => 'project', 'scope_id' => $projectId]))->toRenderable(),
      ],
      'summary' => ['#theme' => 'item_list', '#items' => $items],
      'buildings' => ['#type' => 'table', '#caption' => $this->t('Per gebouw'), '#header' => ['Gebouw', 'In scope', 'Gereed', 'Aandacht'], '#rows' => $buildingRows, '#empty' => $this->t('Geen woningen aan dit project gekoppeld.')],
      'residences' => ['#type' => 'table', '#caption' => $this->t('Toegang per woning'), '#header' => ['Adres', 'Gebouw', 'Aanspreekpunt', 'Rol', 'Toegang via', 'Status', 'Bron regel', 'Startgereed'], '#rows' => $rows, '#empty' => $this->t('Geen woningen aan dit project gekoppeld.')],
      '#cache' => ['max-age' => 0],
    ];
  }
}
