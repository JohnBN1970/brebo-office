<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\user\Form\UserLoginForm;

/**
 * Provides the stable BREBO Office front route.
 */
final class OfficeController extends ControllerBase {

  /**
   * Returns a login form for guests and the dashboard shell for users.
   */
  public function dashboard(): array {
    if ($this->currentUser()->isAnonymous()) {
      return $this->formBuilder()->getForm(UserLoginForm::class);
    }

    return [
      '#markup' => '',
      '#cache' => [
        'contexts' => ['user.roles:authenticated'],
      ],
    ];
  }

}
