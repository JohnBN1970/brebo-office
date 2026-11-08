<?php
declare(strict_types=1);

namespace Brebo\Office\Dashboard;

/**
 * Presentation preferences only. Never use widget preferences as authorization.
 * Callers MUST pass server-authorized widget IDs after enforcing data access.
 */
final class OfficePersonalDashboard {
  private const MAX_WIDGETS = 6;

  /** @var array<string, list<string>> */
  private const ROLE_WIDGETS = [
    'directie' => ['projects', 'finance', 'planning', 'tasks', 'quality', 'risk', 'purchase', 'hours', 'invoices', 'documents'],
    'projectleider' => ['projects', 'finance', 'planning', 'tasks', 'quality', 'risk', 'hours', 'documents'],
    'werkvoorbereider' => ['projects', 'planning', 'tasks', 'purchase', 'documents'],
    'uitvoerder' => ['planning', 'tasks', 'quality', 'hours', 'documents'],
    'administratie' => ['tasks', 'purchase', 'invoices', 'documents'],
  ];

  /** @return list<string> */
  public static function permittedWidgets(array $roles, array $serverAuthorizedWidgets): array {
    $roleWidgets = [];
    foreach ($roles as $role) {
      if (!is_string($role)) continue;
      foreach (self::ROLE_WIDGETS[mb_strtolower($role, 'UTF-8')] ?? [] as $id) {
        $roleWidgets[$id] = true;
      }
    }
    $authorized = [];
    foreach ($serverAuthorizedWidgets as $id) {
      if (is_string($id) && isset($roleWidgets[$id])) $authorized[$id] = true;
    }
    return array_keys($authorized);
  }

  /**
   * Normalize untrusted user preferences against permissions enforced by the server.
   * @return list<string>
   */
  public static function layout(array $preferred, array $permitted): array {
    $allowed = array_fill_keys($permitted, true);
    $result = [];
    foreach ($preferred as $id) {
      if (!is_string($id) || !isset($allowed[$id]) || isset($result[$id])) continue;
      $result[$id] = true;
      if (count($result) === self::MAX_WIDGETS) break;
    }
    if (!$result) {
      foreach ($permitted as $id) {
        if (!is_string($id) || isset($result[$id])) continue;
        $result[$id] = true;
        if (count($result) === self::MAX_WIDGETS) break;
      }
    }
    return array_keys($result);
  }

  /** @param array<string,array{label:string,value:string,note?:string}> $data */
  public static function render(array $layout, array $data): string {
    $escape = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $html = '<section class="office-personal-dashboard" aria-label="Mijn dashboard"><div class="office-personal-kpis">';
    foreach (array_slice($layout, 0, self::MAX_WIDGETS) as $id) {
      if (!is_string($id) || !isset($data[$id])) continue;
      $widget = $data[$id];
      $html .= '<article class="office-personal-kpi"><span>'.$escape($widget['label']).'</span><strong>'.$escape($widget['value']).'</strong><small>'.$escape($widget['note'] ?? '').'</small></article>';
    }
    return $html.'</div></section>';
  }
}
