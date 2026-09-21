<?php

declare(strict_types=1);

namespace Drupal\brebo_inzet\Controller;

use Drupal\brebo_inzet\Service\OnSiteInvitationManager;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

final class OnSiteInviteController extends ControllerBase {

  public function __construct(
    private readonly OnSiteInvitationManager $invitationManager,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('brebo_inzet.onsite_invitation_manager'));
  }

  public function page(UserInterface $user): array {
    try {
      $invitation = $this->invitationManager->invite($user);
    }
    catch (\Throwable $e) {
      return [
        '#markup' => '<p>' . $this->t('OnSite-uitnodiging kan niet worden gemaakt: @message', ['@message' => $e->getMessage()]) . '</p>',
      ];
    }

    $digits = preg_replace('/\D+/', '', $invitation['mobile']) ?? '';
    // WhatsApp click-to-chat requires an international recipient number.
    if (preg_match('/^06\\d{8}$/', $digits) === 1) {
      $digits = '31' . substr($digits, 1);
    }
    if (preg_match('/^316\\d{8}$/', $digits) !== 1) {
      return [
        '#markup' => '<p>' . $this->t('OnSite-uitnodiging kan niet worden gemaakt: gebruik een Nederlands mobiel nummer als 06xxxxxxxx of +316xxxxxxxx.') . '</p>',
      ];
    }
    $message = (string) $this->t('BREBO OnSite: open deze persoonlijke link op je iPhone om OnSite eenmalig aan Office te koppelen:');
    $message .= ' ' . $invitation['install_url'];
    $message .= "\n\n" . (string) $this->t("Belangrijk: zet locatievoorziening aan en geef BREBO OnSite locatietoegang 'Altijd'. Alleen dan kan automatische aanwezigheid op toegewezen projectlocaties werken.");
    $whatsAppUrl = 'https://wa.me/' . $digits . '?text=' . rawurlencode((string) $message);

    return [
      'intro' => [
        '#markup' => '<p>' . $this->t('Stuur de persoonlijke activatielink via WhatsApp naar @name. De link is eenmalig en 7 dagen geldig.', [
          '@name' => $user->getDisplayName(),
        ]) . '</p>',
      ],
      'whatsapp' => [
        '#type' => 'link',
        '#title' => $this->t('Deel via WhatsApp'),
        '#url' => Url::fromUri($whatsAppUrl),
        '#attributes' => [
          'class' => ['button', 'button--primary'],
          'target' => '_blank',
          'rel' => 'noopener noreferrer',
        ],
      ],
      'link' => [
        '#type' => 'details',
        '#title' => $this->t('Persoonlijke activatielink'),
        'url' => [
          '#markup' => '<p><code>' . htmlspecialchars($invitation['install_url'], ENT_QUOTES, 'UTF-8') . '</code></p>',
        ],
      ],
      '#cache' => ['max-age' => 0],
    ];
  }

}
