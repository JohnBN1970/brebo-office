<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_project_cockpit\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * Guards the project cockpit against reintroducing heavy subpage preprocessing.
 *
 * @group brebo_project_cockpit
 */
final class ProjectSubpageNavigationTest extends KernelTestBase {

  protected static $modules = ['system'];

  public function testModuleKeepsSubpagesRenderIndependent(): void {
    $source = file_get_contents(DRUPAL_ROOT . '/modules/custom/brebo_project_cockpit/brebo_project_cockpit.module');
    self::assertIsString($source);
    self::assertStringNotContainsString("brebo_project_context_cockpit'] = _brebo_project_cockpit_context_shell", $source);
    self::assertStringNotContainsString('function brebo_project_cockpit_page_attachments', $source);
    self::assertStringContainsString("'brebo_document_data.project_dossier'", $source);
    self::assertStringContainsString("'brebo_project_cockpit.budget'", $source);
    self::assertStringContainsString("'brebo_project_cockpit.procurement'", $source);
    self::assertStringContainsString("'brebo_project_cockpit.contracts'", $source);
    self::assertStringContainsString("'brebo_project_cockpit.invoices'", $source);
    self::assertStringContainsString("'brebo_inzet.project_dashboard'", $source);
    self::assertStringContainsString("'brebo_project_cockpit.quality'", $source);
    self::assertStringContainsString("'brebo_project_cockpit.completion'", $source);
  }

}
