<?php

declare(strict_types=1);

namespace Drupal\brebo_inzet\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Render\Markup;
use Drupal\Core\Site\Settings;
use Symfony\Component\HttpFoundation\Request;

/**
 * Mobile landing page linked from the personal OnSite invitation.
 */
final class OnSiteInstallController extends ControllerBase {

  public function page(Request $request): array {
    $language = preg_replace('/[^A-Za-z0-9-]/', '', (string) $request->query->get('lang', 'nl')) ?: 'nl';
    $iosUrl = (string) Settings::get('brebo_onsite_ios_install_url', '');
    $androidUrl = (string) Settings::get('brebo_onsite_android_install_url', '');
    $activation = preg_replace('/[^A-Za-z0-9_-]/', '', (string) $request->query->get('activation', '')) ?: '';
    $activationUrl = $activation !== ''
      ? 'brebo-onsite://activate?token=' . rawurlencode($activation)
      : '';

    return [
      'viewport_lock' => [
        '#markup' => Markup::create('<style>html,body{margin:0!important;padding:0!important;height:100%!important;overflow:hidden!important;overscroll-behavior:none!important;}body{position:fixed!important;inset:0!important;width:100%!important;} .brebo-onsite-install{position:fixed!important;inset:0!important;width:min(100%,520px)!important;height:100dvh!important;min-height:0!important;margin:0 auto!important;overflow:hidden!important;overscroll-behavior:none!important;}@media (max-height:720px){.brebo-onsite-install{padding-top:12px!important;padding-bottom:10px!important;}}</style>'),
      ],
      '#type' => 'container',
      '#attributes' => [
        'class' => ['brebo-onsite-install'],
        'style' => 'max-width:520px;min-height:100dvh;margin:0 auto;padding:clamp(16px,3dvh,28px) 24px clamp(14px,2.5dvh,24px);box-sizing:border-box;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,sans-serif;text-align:center;background:linear-gradient(180deg,#0b4f8a 0%,#0a3f73 56%,#082f58 100%);color:#fff;position:relative;overflow-x:hidden;',
        'lang' => $language,
      ],
      'title' => [
        '#markup' => Markup::create('<div style="display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:clamp(16px,2.8dvh,28px);"><div style="display:flex;align-items:center;gap:12px;text-align:left;"><div style="width:54px;height:54px;border-radius:14px;background:#fff;color:#0b4f8a;display:flex;align-items:center;justify-content:center;font-size:34px;font-weight:800;">B</div><div><div style="font-size:28px;font-weight:800;letter-spacing:.2px;">BREBO</div><div style="font-size:12px;letter-spacing:2.4px;opacity:.9;">BOUW &amp; ADVIES</div></div></div><div style="text-align:right;font-size:11px;line-height:1.6;letter-spacing:2px;opacity:.85;">INZICHT<br>REGIE<br>REALISATIE</div></div><h1 style="margin:0 0 clamp(8px,1.5dvh,14px);color:#fff;font-size:clamp(32px,4.5dvh,38px);line-height:1.1;">BREBO OnSite</h1><p style="margin:0 auto 12px;max-width:420px;color:#fff;font-size:22px;line-height:1.35;">Activeer OnSite op dit toestel.</p><p style="margin:0 auto clamp(14px,2.5dvh,24px);max-width:420px;color:#fff;font-size:16px;line-height:1.55;">Deze persoonlijke link koppelt automatisch je toestel aan jouw BREBO-account. Projecten en projectzones komen rechtstreeks uit BREBO Office.</p>'),
      ],
      'activate' => $activationUrl !== '' ? [
        '#markup' => Markup::create('<a href="' . htmlspecialchars($activationUrl, ENT_QUOTES, 'UTF-8') . '" style="display:block;padding:clamp(14px,2dvh,18px);margin:clamp(14px,2.2dvh,22px) 0 clamp(16px,2.5dvh,24px);background:linear-gradient(90deg,#6f35a7,#8a4ac7);color:white;text-decoration:none;border-radius:18px;font-size:19px;font-weight:800;box-shadow:0 10px 28px rgba(26,5,46,.28);">' . htmlspecialchars((string) $this->t('Open BREBO OnSite'), ENT_QUOTES, 'UTF-8') . '</a>'),
      ] : [],
      'ios' => $iosUrl !== '' ? [
        '#type' => 'link',
        '#title' => $this->t('Installeer op iPhone'),
        '#url' => \Drupal\Core\Url::fromUri($iosUrl),
        '#attributes' => [
          'style' => 'display:block;padding:16px;margin:20px 0;background:#5b2c83;color:white;text-decoration:none;border-radius:12px;font-weight:700;',
        ],
      ] : [],
      'android' => $androidUrl !== '' ? [
        '#type' => 'link',
        '#title' => $this->t('Installeer op Android'),
        '#url' => \Drupal\Core\Url::fromUri($androidUrl),
        '#attributes' => [
          'style' => 'display:block;padding:16px;margin:20px 0;background:#5b2c83;color:white;text-decoration:none;border-radius:12px;font-weight:700;',
        ],
      ] : [],
      'note' => [
        '#markup' => Markup::create('<div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px;margin-top:6px;"><div style="padding:14px 8px;border-radius:16px;background:rgba(255,255,255,.09);"><div style="font-size:26px;">⌖</div><strong style="display:block;margin-top:7px;font-size:13px;line-height:1.25;">Automatische<br>aanwezigheid</strong><span style="display:block;margin-top:6px;font-size:11px;line-height:1.35;opacity:.86;">Alleen op toegewezen locaties</span></div><div style="padding:14px 8px;border-radius:16px;background:rgba(255,255,255,.09);"><div style="font-size:24px;">▣</div><strong style="display:block;margin-top:7px;font-size:13px;line-height:1.25;">Direct je<br>projecten</strong><span style="display:block;margin-top:6px;font-size:11px;line-height:1.35;opacity:.86;">Uit BREBO Office</span></div><div style="padding:14px 8px;border-radius:16px;background:rgba(255,255,255,.09);"><div style="font-size:24px;">✓</div><strong style="display:block;margin-top:7px;font-size:13px;line-height:1.25;">Veilig en<br>persoonlijk</strong><span style="display:block;margin-top:6px;font-size:11px;line-height:1.35;opacity:.86;">Jouw link, jouw toestel</span></div></div><div style="margin-top:clamp(18px,3dvh,30px);font-size:clamp(20px,3dvh,24px);font-style:italic;font-family:Georgia,serif;opacity:.82;text-align:left;line-height:1.25;transform:rotate(-3deg);">Samen bouwen<br>aan morgen.</div>'),
      ],
      '#cache' => ['max-age' => 0],
    ];
  }

}
