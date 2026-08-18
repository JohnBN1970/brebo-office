<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_office_core\Unit;

use Drupal\brebo_office_core\Service\WorkforceDayStartControl;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Drupal\brebo_office_core\Service\WorkforceDayStartControl
 */
final class WorkforceDayStartControlTest extends TestCase {

  private WorkforceDayStartControl $control;

  protected function setUp(): void {
    parent::setUp();
    $this->control = new WorkforceDayStartControl();
  }

  private function complete(): array {
    return [
      'work' => 'Gevelisolatie aanbrengen',
      'access' => 'Sleutel bij uitvoerder',
      'risks' => 'Valgevaar: steiger gebruiken',
      'controls' => 'Foto vóór afwerking',
      'contacts' => 'Uitvoerder 06-12345678',
      'project_matches' => TRUE,
      'building_matches' => TRUE,
      'shift_status' => 'Gepubliceerd',
      'shift_open' => FALSE,
      'qualification_status' => 'Passend',
      'resource_controls' => ['Vrijgegeven'],
      'has_building_location' => TRUE,
    ];
  }

  public function testCompleteBriefingIsReady(): void {
    self::assertSame('Gereed', $this->control->assess($this->complete())['status']);
  }

  public function testMissingRequiredContentBlocks(): void {
    $input = $this->complete();
    $input['risks'] = '';
    $result = $this->control->assess($input);
    self::assertSame('Blokkade', $result['status']);
    self::assertContains('risico’s en maatregelen ontbreken', $result['blocks']);
  }

  public function testOpenShiftAndBlockedEquipmentBlock(): void {
    $input = $this->complete();
    $input['shift_open'] = TRUE;
    $input['resource_controls'] = ['Blokkade'];
    $result = $this->control->assess($input);
    self::assertContains('benodigde personeelsbezetting is nog niet compleet', $result['blocks']);
    self::assertContains('materieelreservering is geblokkeerd', $result['blocks']);
  }

  public function testMissingPdokLocationWarns(): void {
    $input = $this->complete();
    $input['has_building_location'] = FALSE;
    self::assertSame('Waarschuwing', $this->control->assess($input)['status']);
  }

  public function testOldAcknowledgementVersionBlocks(): void {
    $result = $this->control->assessAcknowledgement(3, 2, TRUE, '');
    self::assertSame('Blokkade', $result['status']);
  }

  public function testUnclearInstructionRequiresQuestion(): void {
    $result = $this->control->assessAcknowledgement(3, 3, FALSE, 'Welke steiger gebruiken we?');
    self::assertSame('Vraag open', $result['status']);
  }

  public function testCurrentUnderstoodVersionPasses(): void {
    $result = $this->control->assessAcknowledgement(3, 3, TRUE, '');
    self::assertSame('Begrepen', $result['status']);
  }

}
