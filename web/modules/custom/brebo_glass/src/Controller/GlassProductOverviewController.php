<?php

declare(strict_types=1);

namespace Drupal\brebo_glass\Controller;

use Drupal\brebo_glass\Service\GlassProductRepository;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Displays the controlled glass product catalog.
 */
final class GlassProductOverviewController extends ControllerBase {

  public function __construct(
    private readonly GlassProductRepository $repository,
    private readonly DateFormatterInterface $dateFormatter,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('brebo_glass.product_repository'),
      $container->get('date.formatter'),
    );
  }

  public function overview(): array {
    $products = $this->repository->findAll();
    $canVerify = $this->currentUser()->hasPermission('verify brebo glass products');

    $rows = [];
    foreach ($products as $product) {
      $verified = (int) $product['verified'] === 1;
      $active = (int) $product['active'] === 1;
      $operation = '-';

      if (!$verified && $canVerify) {
        $operation = Link::fromTextAndUrl(
          $this->t('Verifiëren'),
          Url::fromRoute('brebo_glass.product_verify', ['product_id' => (int) $product['id']]),
        );
      }

      $rows[] = [
        'code' => $product['product_code'],
        'label' => $product['label'],
        'type' => $product['glass_type'],
        'composition' => $product['composition'],
        'wind_resistance' => number_format((float) $product['wind_resistance_kpa'], 3, ',', '.') . ' kN/m²',
        'maximum_dimensions' => $product['max_width_mm'] . ' × ' . $product['max_height_mm'] . ' mm',
        'weight' => number_format((float) $product['weight_kg_m2'], 2, ',', '.') . ' kg/m²',
        'classes' => implode(' / ', array_filter([
          trim((string) ($product['safety_class'] ?? '')),
          trim((string) ($product['fire_class'] ?? '')),
        ])) ?: '-',
        'source' => $product['source_reference'],
        'status' => $verified
          ? ($active ? $this->t('Geverifieerd en actief') : $this->t('Geverifieerd, niet actief'))
          : $this->t('Concept — verificatie vereist'),
        'verified_by' => !empty($product['verified_by'])
          ? $this->t('Gebruiker #@uid', ['@uid' => $product['verified_by']])
          : '-',
        'verified_at' => !empty($product['verified_at'])
          ? $this->dateFormatter->format((int) $product['verified_at'], 'short')
          : '-',
        'operation' => $operation,
      ];
    }

    $build['intro'] = [
      '#markup' => '<p>' . $this->t('Alleen geverifieerde en actieve producten worden meegenomen in de automatische glaskeuze. Een concept kan niet worden toegepast voordat bron, berekening en productdocumentatie afzonderlijk zijn gecontroleerd.') . '</p>',
    ];
    $build['actions'] = [
      '#type' => 'link',
      '#title' => $this->t('Nieuw glasproduct'),
      '#url' => Url::fromRoute('brebo_glass.product_add'),
      '#attributes' => ['class' => ['button', 'button--primary']],
      '#access' => $this->currentUser()->hasPermission('administer brebo glass'),
    ];
    $build['table'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Code'),
        $this->t('Product'),
        $this->t('Glastype'),
        $this->t('Opbouw'),
        $this->t('Windweerstand'),
        $this->t('Maximale maat'),
        $this->t('Gewicht'),
        $this->t('Veiligheid / brand'),
        $this->t('Bron'),
        $this->t('Status'),
        $this->t('Geverifieerd door'),
        $this->t('Geverifieerd op'),
        $this->t('Actie'),
      ],
      '#rows' => $rows,
      '#empty' => $this->t('Er zijn nog geen glasproducten geregistreerd.'),
      '#sticky' => TRUE,
    ];

    return $build;
  }

}
