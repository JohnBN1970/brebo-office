<?php

declare(strict_types=1);

namespace Drupal\brebo_inzet\Service;

use Drupal\Core\Url;
use Drupal\user\UserInterface;

/**
 * Creates the personal OnSite installation invitation for an Office user.
 */
final class OnSiteInvitationManager {

  public function __construct(
    private readonly OnSiteIdentityResolver $identityResolver,
    private readonly OnSiteActivationManager $activationManager,
  ) {}

  /**
   * @return array{mobile: string, install_url: string, language: string}
   */
  public function invite(UserInterface $user): array {
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

    return [
      'mobile' => $mobile,
      'install_url' => $installUrl,
      'language' => $language,
    ];
  }

}
