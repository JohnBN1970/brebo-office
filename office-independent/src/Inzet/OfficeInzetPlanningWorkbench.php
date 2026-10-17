<?php

declare(strict_types=1);

namespace Brebo\Office\Inzet;

/**
 * Native planning entry point. Only accepts project rows already authorized
 * by the Office session query. No synthetic workers or shifts are displayed.
 */
final class OfficeInzetPlanningWorkbench {
  /** @param list<array<string,mixed>> $projects */
  public static function render(array $projects, string $basePath): string {
    if (!in_array($basePath, ['/office', '/office-test'], true)) {
      throw new \InvalidArgumentException('Invalid Office mount');
    }
    $escape = static fn(mixed $value): string =>
      htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $html = '<section class="inzet-planning" aria-labelledby="inzet-planning-heading">'
      . '<h2 id="inzet-planning-heading">Projectteams en planning</h2>'
      . '<p>Selecteer een toegankelijk project. Personeelsplanning, leveranciersbezetting en roosterwijzigingen zijn nog niet aangesloten.</p>';
    if ($projects === []) {
      return $html . '<p>Geen projecten beschikbaar binnen jouw rechten.</p></section>';
    }
    $html .= '<table><thead><tr><th>Project</th><th>Status</th><th>Planning</th></tr></thead><tbody>';
    foreach ($projects as $project) {
      $id = filter_var($project['id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
      if ($id === false) continue;
      $url = $basePath . '/projecten/detail?id=' . $id . '#inzet';
      $html .= '<tr><td><a href="' . $escape($url) . '">'
        . $escape($project['code'] ?? '') . ' — ' . $escape($project['title'] ?? '')
        . '</a></td><td>' . $escape($project['status'] ?? '') . '</td>'
        . '<td>Niet aangesloten</td></tr>';
    }
    return $html . '</tbody></table></section>';
  }
}
