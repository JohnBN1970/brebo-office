<?php
declare(strict_types=1);

namespace Brebo\Office\Ui;

/**
 * Shared Office 2.0 horizontal module tabs. Navigation only: never an ACL gate.
 */
final class OfficeModuleTabs {
  /**
   * @param list<array{key:string,label:string,url:string,enabled:bool}> $tabs
   */
  public static function render(array $tabs, string $active): string {
    $escape = static fn(string $v): string => htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $items = [];
    foreach ($tabs as $tab) {
      if (!($tab['enabled'] ?? false)) {
        continue;
      }
      $key = (string)($tab['key'] ?? '');
      $label = (string)($tab['label'] ?? '');
      $url = (string)($tab['url'] ?? '');
      // Never render untrusted external, script or protocol-relative destinations.
      if ($key === '' || $label === '' || $url === '' || $url[0] !== '/' || str_starts_with($url, '//')
        || preg_match('/[\x00-\x1F\x7F]/', $url)) {
        continue;
      }
      $selected = hash_equals($key, $active);
      $items[] = '<a class="office-module-tab' . ($selected ? ' is-active' : '') . '" href="' . $escape($url)
        . '"' . ($selected ? ' aria-current="page"' : '') . '>' . $escape($label) . '</a>';
    }
    if ($items === []) return '';
    return '<nav class="office-module-tabs" aria-label="Moduleonderdelen">' . implode('', $items) . '</nav>';
  }

  public static function css(): string {
    return '.office-module-tabs{display:flex;gap:6px;overflow-x:auto;max-width:100%;border-bottom:1px solid #dce4ed;padding:0 0 9px;margin:0 0 22px;scrollbar-width:thin}'
      . '.office-module-tab{flex:none;display:inline-block;padding:10px 14px;border-radius:8px 8px 0 0;text-decoration:none;color:#34516b;font-weight:650;white-space:nowrap}'
      . '.office-module-tab:hover,.office-module-tab:focus-visible{background:#eaf0f7}'
      . '.office-module-tab.is-active{color:#153d5e;background:#e8f1fa;box-shadow:inset 0 -3px #155c87}'
      . '.office-module-tab:focus-visible{outline:3px solid #e9a72c;outline-offset:2px}';
  }
}
