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
      '#type' => 'container',
      '#attributes' => [
        'style' => 'max-width:520px;min-height:100vh;margin:0 auto;padding:64px 24px 40px;box-sizing:border-box;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,sans-serif;text-align:center;background:#2196f3;color:#fff;',
        'lang' => $language,
      ],
      'title' => [
        '#markup' => '<h1 style="margin:24px 0 12px;color:#fff;font-size:36px;line-height:1.1;">BREBO OnSite</h1><p style="margin:0 auto 28px;max-width:420px;color:#fff;font-size:18px;line-height:1.5;">Activeer OnSite op deze iPhone. Je persoonlijke koppeling wordt automatisch verwerkt.</p>',
      ],
      'activate' => $activationUrl !== '' ? [
        '#markup' => Markup::create('<a href="' . htmlspecialchars($activationUrl, ENT_QUOTES, 'UTF-8') . '" style="display:block;padding:18px;margin:24px 0;background:#5b2c83;color:white;text-decoration:none;border-radius:16px;font-size:18px;font-weight:700;box-shadow:0 8px 24px rgba(39,13,58,.22);">' . htmlspecialchars((string) $this->t('Open BREBO OnSite'), ENT_QUOTES, 'UTF-8') . '</a>'),
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
        '#markup' => '<p style="margin:28px auto 0;max-width:420px;color:rgba(255,255,255,.9);font-size:15px;line-height:1.5;">Projecten en projectzones komen rechtstreeks uit BREBO Office. Je hoeft in OnSite niets handmatig in te stellen.</p>',
      ],
      '#cache' => ['max-age' => 0],
    ];
  }

}
