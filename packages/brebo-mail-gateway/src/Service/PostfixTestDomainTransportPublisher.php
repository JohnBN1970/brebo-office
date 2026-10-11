<?php

declare(strict_types=1);

namespace Brebo\MailGateway\Service;

use Brebo\MailGateway\Contract\GatewayStateReaderInterface;
use RuntimeException;

final class PostfixTestDomainTransportPublisher {

  public function __construct(
    private readonly GatewayStateReaderInterface $state,
    private readonly PostfixTestDomainTransportRenderer $renderer,
    private readonly string $outputDirectory,
  ) {}

  /** @return array{transport_map:string,master_cf_fragment:string,main_cf_fragment:string} */
  public function publish(): array {
    $bundle = $this->renderer->render($this->state->domains());

    $directory = rtrim($this->outputDirectory, DIRECTORY_SEPARATOR);
    if ($directory === '') {
      throw new RuntimeException('Postfix test-transport outputdirectory ontbreekt.');
    }
    if (!is_dir($directory) && !mkdir($directory, 0700, TRUE) && !is_dir($directory)) {
      throw new RuntimeException('Postfix test-transport outputdirectory kan niet worden aangemaakt.');
    }

    foreach ($bundle as $name => $content) {
      $path = $directory . DIRECTORY_SEPARATOR . $name;
      $temp = $path . '.tmp';
      if (file_put_contents($temp, $content, LOCK_EX) === FALSE) {
        throw new RuntimeException('Postfix test-transport config kon niet worden geschreven: ' . $name);
      }
      chmod($temp, $name === 'transport_map' ? 0640 : 0600);
      if (!rename($temp, $path)) {
        @unlink($temp);
        throw new RuntimeException('Postfix test-transport config kon niet atomair worden gepubliceerd: ' . $name);
      }
    }

    return $bundle;
  }
}
