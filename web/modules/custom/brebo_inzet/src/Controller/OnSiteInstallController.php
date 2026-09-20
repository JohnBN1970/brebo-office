<?php

declare(strict_types=1);

namespace Drupal\brebo_inzet\Controller;

use Drupal\Core\Controller\ControllerBase;
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
        'style' => 'max-width:520px;margin:48px auto;padding:24px;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,sans-serif;text-align:center;',
        'lang' => $language,
      ],
      'title' => [
        '#markup' => '<h1>BREBO OnSite</h1><p>Installeer OnSite op je telefoon. Deze persoonlijke link activeert daarna automatisch je toestel.</p>',
      ],
      'activate' => $activationUrl !== '' ? [
        '#markup' => '<a href="' . htmlspecialchars($activationUrl, ENT_QUOTES, 'UTF-8') . '" style="display:block;padding:16px;margin:20px 0;background:#5b2c83;color:white;text-decoration:none;border-radius:12px;font-weight:700;">' . $this->t('Open en activeer OnSite') . '</a>',
      ] : [],
      'ios' => $iosUrl !== '' ? [
        '#type' => 'link',
        '#title' => $this->t('Installeer op iPhone'),
        '#url' => \Drupal\Core\Url::fromUri($iosUrl),
        '#attributes' => [
          'style' => 'display:block;padding:16px;margin:20px 0;background:#5b2c83;color:white;text-decoration:none;border-radius:12px;font-weight:700;',
        ],
      ] : [
        '#markup' => '<p><strong>iPhone-installatie wordt binnenkort beschikbaar.</strong></p>',
      ],
      'android' => $androidUrl !== '' ? [
        '#type' => 'link',
        '#title' => $this->t('Installeer op Android'),
        '#url' => \Drupal\Core\Url::fromUri($androidUrl),
        '#attributes' => [
          'style' => 'display:block;padding:16px;margin:20px 0;background:#5b2c83;color:white;text-decoration:none;border-radius:12px;font-weight:700;',
        ],
      ] : [],
      'note' => [
        '#markup' => '<p style="color:#666;font-size:14px;">Office bepaalt daarna automatisch je taal, projecten en projectzones. Je hoeft in OnSite niets handmatig in te stellen.</p>',
      ],
      '#cache' => ['max-age' => 0],
    ];
  }

}
