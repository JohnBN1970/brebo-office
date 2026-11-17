<?php
declare(strict_types=1);
namespace Brebo\Office\Intake;
/** Central presentation assets shared by all Office pages. */
final class OfficeTheme {
  public static function styles(): string {
    return '<style data-office-theme="brebo-2026">'.file_get_contents(__DIR__.'/../../resources/office-theme.css').'</style>';
  }
  public static function script(): string { return (string)file_get_contents(__DIR__.'/../../resources/office-theme.js'); }
  public static function controls(): string {
    $html='<div class="office-theme-picker" role="group" aria-label="Weergave">';
    foreach (['day'=>['Dag','☀'], 'night'=>['Nacht','☾'], 'system'=>['Systeem','<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="3" y="4" width="18" height="13" rx="2"/><path d="M12 17v4m-5 0h10"/></svg>']] as $key=>[$label,$icon]) {
      $html.='<button type="button" data-office-mode="'.$key.'" aria-label="'.$label.'" title="'.$label.'" aria-pressed="false"><span aria-hidden="true">'.$icon.'</span></button>';
    }
    return $html.'</div>';
  }
  /** Add only the exact shared script hash; preserve all other CSP directives. */
  public static function policy(string $policy): string {
    $hash="'sha256-".base64_encode(hash('sha256',self::script(),true))."'";
    foreach (['script-src','script-src-elem'] as $directive) {
      $pattern='/(?:^|;\s*)'.preg_quote($directive,'/').'\s+([^;]*)/';
      if (preg_match($pattern,$policy)) {
        $policy=preg_replace_callback($pattern,static fn($m)=>str_replace("'none'",'', $m[0]).' '.$hash,$policy);
      } elseif ($directive==='script-src') $policy.='; script-src '.$hash;
    }
    return $policy;
  }
}
