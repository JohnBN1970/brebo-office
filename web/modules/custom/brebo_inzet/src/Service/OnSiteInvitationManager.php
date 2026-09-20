<?php

declare(strict_types=1);

namespace Drupal\brebo_inzet\Service;

use Drupal\Core\Url;
use Drupal\user\UserInterface;

/**
 * Sends the first OnSite installation invitation to an Office user.
 */
final class OnSiteInvitationManager {

  public function __construct(
    private readonly OnSiteIdentityResolver $identityResolver,
    private readonly OnSiteActivationManager $activationManager,
  ) {}

  public function invite(UserInterface $user): void {
    if (!$user->isActive()) {
      throw new \InvalidArgumentException('Alleen actieve gebruikers kunnen voor OnSite worden uitgenodigd.');
    }
    if (!$user->hasField('field_brebo_mobile')) {
      throw new \RuntimeException('Gebruiker heeft geen OnSite mobiel veld.');
    }

    $mobile = $this->identityResolver->normalizeMobile((string) $user->get('field_brebo_mobile')->value);
    if ($mobile === '') {
      throw new \InvalidArgumentException('Gebruiker heeft geen geldig mobiel nummer.');
    }

    $language = $this->identityResolver->languageFor($user);
    $activationToken = $this->activationManager->issue((int) $user->id());
    $installUrl = Url::fromRoute('brebo_inzet.onsite_install', [], [
      'absolute' => TRUE,
      'query' => [
        'lang' => $language,
        'activation' => $activationToken,
      ],
    ])->toString();

    // The Office UI can present this personal URL for sharing via WhatsApp.
    // No SMS provider is required for installation or activation.
  }

}
