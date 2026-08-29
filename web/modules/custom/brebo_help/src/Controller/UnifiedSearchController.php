<?php

declare(strict_types=1);

namespace Drupal\brebo_help\Controller;

use Drupal\brebo_help\HelpSearchIndex;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

final class UnifiedSearchController extends ControllerBase {

  public function __construct(private readonly RequestStack $requestStack) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('request_stack'));
  }

  public function index(): array {
    $query = trim((string) $this->requestStack->getCurrentRequest()?->query->get('q', ''));
    $results = HelpSearchIndex::search($query);
    $cards = '';
    foreach ($results as $result) {
      $cards .= '<article class="brebo-help__card"><p class="brebo-help__meta">' . ucfirst($result['type']) . '</p><h3>' . Link::fromTextAndUrl($result['title'], $result['url'])->toString() . '</h3><p>' . $result['summary'] . '</p></article>';
    }
    $body = $query === ''
      ? '<p class="brebo-help__empty">Vul een zoekterm in. De zoekopdracht doorzoekt handleidingen, rollen, dagelijkse taken, begrippen en vragen.</p>'
      : ($cards !== '' ? '<div class="brebo-help__grid">' . $cards . '</div>' : '<p class="brebo-help__empty">Geen helpresultaten gevonden. Probeer een andere zoekterm.</p>');

    return [
      '#attached' => ['library' => ['brebo_help/help']],
      '#markup' => '<section class="brebo-help"><p class="brebo-help__intro">Zoek vanuit één plek in heel BREBO Help.</p><form class="brebo-help__search" method="get"><label class="visually-hidden" for="brebo-help-all-search">Zoeken in heel Help</label><input id="brebo-help-all-search" name="q" type="search" value="' . htmlspecialchars($query, ENT_QUOTES) . '" placeholder="Bijv. werkbegroting, restpunt, projectleider, foutmelding"><button type="submit">Zoeken</button></form>' . $body . '<p class="brebo-help__back">' . Link::fromTextAndUrl('← Terug naar Help', Url::fromRoute('brebo_help.center'))->toString() . '</p></section>',
    ];
  }
}
