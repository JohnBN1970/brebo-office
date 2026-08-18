<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Displays the operational vehicle and equipment planning.
 */
final class WorkforceResourceController extends ControllerBase {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('entity_type.manager'));
  }

  public function overview(): array {
    $storage = $this->entityTypeManager->getStorage('node');
    $today = new \DateTimeImmutable('today');
    $resourceIds = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'brebo_work_resource')
      ->sort('title', 'ASC')
      ->execute();

    $available = 0;
    $blocked = 0;
    $warning = 0;
    $resourceRows = [];
    foreach ($storage->loadMultiple($resourceIds) as $resource) {
      if (!$resource instanceof NodeInterface) {
        continue;
      }
      $status = (string) ($resource->get('field_brebo_resource_status')->value ?? '—');
      $inspection = (string) ($resource->get('field_brebo_resource_inspection')->value ?? '');
      $maintenance = (string) ($resource->get('field_brebo_resource_maintenance')->value ?? '');
      $signals = [];
      if (!(bool) ($resource->get('field_brebo_resource_active')->value ?? FALSE)
        || in_array($status, ['Defect', 'Onderhoud', 'Buiten dienst'], TRUE)) {
        $signals[] = $this->t('Niet planbaar');
        $blocked++;
      }
      elseif (($inspection !== '' && new \DateTimeImmutable($inspection) < $today)
        || ($maintenance !== '' && new \DateTimeImmutable($maintenance) < $today)) {
        $signals[] = $this->t('Keuring/onderhoud verlopen');
        $blocked++;
      }
      elseif (($inspection !== '' && new \DateTimeImmutable($inspection) <= $today->modify('+30 days'))
        || ($maintenance !== '' && new \DateTimeImmutable($maintenance) <= $today->modify('+14 days'))) {
        $signals[] = $this->t('Actie binnenkort nodig');
        $warning++;
        $available++;
      }
      else {
        $available++;
      }
      $location = $resource->get('field_brebo_resource_location')->entity;
      $skill = $resource->get('field_brebo_resource_skill')->entity;
      $resourceRows[] = [
        ['data' => Link::fromTextAndUrl($resource->label(), $resource->toUrl())->toRenderable()],
        (string) ($resource->get('field_brebo_resource_code')->value ?? '—'),
        (string) ($resource->get('field_brebo_resource_type')->value ?? '—'),
        $status,
        $location instanceof NodeInterface ? $location->label() : '—',
        $inspection ?: '—',
        $maintenance ?: '—',
        $skill instanceof NodeInterface ? $skill->label() : '—',
        $signals === [] ? $this->t('Akkoord') : implode(', ', $signals),
      ];
    }

    $bookingIds = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'brebo_resource_booking')
      ->condition('field_brebo_booking_end', (new \DateTimeImmutable())->format('Y-m-d\TH:i:s'), '>=')
      ->condition('field_brebo_booking_status', ['Geannuleerd', 'Retour'], 'NOT IN')
      ->sort('field_brebo_booking_start', 'ASC')
      ->range(0, 100)
      ->execute();
    $bookingRows = [];
    $bookingBlocks = 0;
    foreach ($storage->loadMultiple($bookingIds) as $booking) {
      if (!$booking instanceof NodeInterface) {
        continue;
      }
      $resource = $booking->get('field_brebo_booking_resource')->entity;
      $shift = $booking->get('field_brebo_booking_shift')->entity;
      $building = $booking->get('field_brebo_booking_building')->entity;
      $driver = $booking->get('field_brebo_booking_driver')->entity;
      $control = (string) ($booking->get('field_brebo_booking_control')->value ?? 'Niet gecontroleerd');
      $bookingBlocks += $control === 'Blokkade' ? 1 : 0;
      $bookingRows[] = [
        ['data' => Link::fromTextAndUrl($booking->label(), $booking->toUrl())->toRenderable()],
        $resource instanceof NodeInterface ? $resource->label() : '—',
        $shift instanceof NodeInterface ? $shift->label() : '—',
        $building instanceof NodeInterface ? $building->label() : '—',
        (string) ($booking->get('field_brebo_booking_start')->value ?? '—'),
        (string) ($booking->get('field_brebo_booking_end')->value ?? '—'),
        $driver instanceof NodeInterface ? $driver->label() : '—',
        (string) ($booking->get('field_brebo_booking_status')->value ?? '—'),
        $control,
        (string) ($booking->get('field_brebo_booking_control_note')->value ?? '—'),
      ];
    }

    return [
      'actions' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['brebo-list-actions']],
        'booking' => [
          '#type' => 'link', '#title' => $this->t('Materieel reserveren'),
          '#url' => Url::fromRoute('node.add', ['node_type' => 'brebo_resource_booking']),
          '#attributes' => ['class' => ['button', 'button--primary']],
        ],
        'resource' => [
          '#type' => 'link', '#title' => $this->t('Materieel/voertuig toevoegen'),
          '#url' => Url::fromRoute('node.add', ['node_type' => 'brebo_work_resource']),
          '#attributes' => ['class' => ['button']],
        ],
        'schedule' => [
          '#type' => 'link', '#title' => $this->t('Weekrooster'),
          '#url' => Url::fromRoute('brebo_office_core.inzet_schedule'),
          '#attributes' => ['class' => ['button']],
        ],
      ],
      'summary' => [
        '#type' => 'table',
        '#attributes' => ['class' => ['brebo-calc-summary']],
        '#header' => [$this->t('Middelen'), $this->t('Planbaar'), $this->t('Waarschuwing'), $this->t('Geblokkeerd'), $this->t('Reserveringsblokkades')],
        '#rows' => [[count($resourceRows), $available, $warning, $blocked, $bookingBlocks]],
      ],
      'bookings_heading' => ['#markup' => '<h2>' . $this->t('Actuele reserveringen') . '</h2>'],
      'bookings' => [
        '#type' => 'table',
        '#header' => [
          $this->t('Reservering'), $this->t('Middel'), $this->t('Dienst'), $this->t('Gebouw'),
          $this->t('Start'), $this->t('Einde'), $this->t('Bestuurder'), $this->t('Status'),
          $this->t('Controle'), $this->t('Toelichting'),
        ],
        '#rows' => $bookingRows,
        '#empty' => $this->t('Geen actuele materieelreserveringen.'),
        '#sticky' => TRUE,
      ],
      'resources_heading' => ['#markup' => '<h2>' . $this->t('Materieel- en voertuigregister') . '</h2>'],
      'resources' => [
        '#type' => 'table',
        '#header' => [
          $this->t('Middel'), $this->t('Code'), $this->t('Type'), $this->t('Status'),
          $this->t('Standplaats'), $this->t('Keuring'), $this->t('Onderhoud'),
          $this->t('Bevoegdheid'), $this->t('Signaal'),
        ],
        '#rows' => $resourceRows,
        '#empty' => $this->t('Nog geen materieel of voertuigen geregistreerd.'),
        '#sticky' => TRUE,
      ],
      '#cache' => [
        'contexts' => ['user.permissions'],
        'tags' => ['node_list:brebo_work_resource', 'node_list:brebo_resource_booking'],
        'max-age' => 300,
      ],
    ];
  }

}
