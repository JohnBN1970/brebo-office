<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_project_cockpit\Unit;

use PHPUnit\Framework\TestCase;

/** Keeps project screens calm and aligned with normal Dutch domain language. */
final class ProjectScreenHierarchyContractTest extends TestCase {

  public function testProjectScreensUseRecognisedTermsAndOrder(): void {
    $root = dirname(__DIR__, 3);

    $orders = $this->read($root . '/src/Controller/ProjectOrdersController.php');
    $contracts = $this->read($root . '/src/Controller/ProjectContractsController.php');
    $invoices = $this->read($root . '/src/Controller/ProjectInvoiceRegisterController.php');
    $invoiceFilter = $this->read($root . '/src/Form/ProjectInvoiceFilterForm.php');
    $budget = $this->read($root . '/src/Controller/ProjectBudgetController.php');
    $sections = $this->read($root . '/src/Controller/ProjectSectionController.php');

    self::assertStringContainsString('Inkooporder aanmaken', $orders);
    self::assertStringContainsString('Opdracht opdrachtgever', $orders);
    self::assertStringContainsString('Leverancier / onderaannemer', $orders);
    self::assertStringNotContainsString('Inkomende orders', $orders);
    self::assertStringNotContainsString('Uitgaande orders', $orders);
    $this->assertBefore($orders, "'actions' => [", "'kpis' => [");
    $this->assertBefore($orders, "'kpis' => [", "'assignment' => [");

    self::assertStringContainsString('Projectcontract', $contracts);
    self::assertStringContainsString('Contractverplichtingen', $contracts);
    self::assertStringContainsString('Financieel risico', $contracts);
    self::assertStringNotContainsString('Financiële exposure', $contracts);
    $this->assertBefore($contracts, "'kpis' => [", "'contract' => [");
    $this->assertBefore($contracts, "'contract' => [", "'obligations' => [");

    self::assertStringContainsString("'direction_label' => 'Inkoopfactuur'", $invoices);
    self::assertStringContainsString("'direction_label' => 'Verkoopfactuur'", $invoices);
    self::assertStringContainsString('Verkoopfactuur aanmaken', $invoices);
    self::assertStringContainsString('Openstaand', $invoices);
    $this->assertBefore($invoices, "'actions' => [", "'kpis' => [");
    $this->assertBefore($invoices, "'kpis' => [", "'filters' =>");
    $this->assertBefore($invoices, "'filters' =>", "'register' => [");

    self::assertStringContainsString("'#title' => \$this->t('Soort factuur')", $invoiceFilter);
    self::assertStringContainsString('Inkoopfacturen', $invoiceFilter);
    self::assertStringContainsString('Verkoopfacturen', $invoiceFilter);

    self::assertStringContainsString("'#caption' => \$this->t('Werkbegroting')", $budget);
    self::assertStringContainsString('Calculatie en offerte', $budget);
    self::assertStringNotContainsString('uitvoeringswaarheid', $budget);

    self::assertStringContainsString("\$this->t('Fase:')", $sections);
    self::assertStringContainsString('Mijlpalen oplevering', $sections);
    self::assertStringNotContainsString('schaduwregistratie', $sections);
  }

  private function read(string $path): string {
    $content = file_get_contents($path);
    self::assertIsString($content);
    return $content;
  }

  private function assertBefore(string $content, string $first, string $second): void {
    $firstPos = strpos($content, $first);
    $secondPos = strpos($content, $second);
    self::assertNotFalse($firstPos, sprintf('Ontbrekend schermonderdeel: %s', $first));
    self::assertNotFalse($secondPos, sprintf('Ontbrekend schermonderdeel: %s', $second));
    self::assertLessThan($secondPos, $firstPos, sprintf('%s hoort vóór %s te staan.', $first, $second));
  }

}
