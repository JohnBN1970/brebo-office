<?php

declare(strict_types=1);

namespace Brebo\MailGateway\Service;

use Brebo\MailGateway\Contract\GatewayStateReaderInterface;
use RuntimeException;

final class MailStackConfigPublisher {

  public function __construct(
    private readonly GatewayStateReaderInterface $state,
    private readonly MailStackProjectionBuilder $builder,
    private readonly MailStackConfigBundleRenderer $renderer,
    private readonly string $outputDirectory,
  ) {}

  /** @return array<string,string> */
  public function publish(): array {
    $projection = $this->builder->build(
      $this->state->domains(),
      $this->state->mailboxes(),
      $this->state->aliases(),
    );
    $bundle = $this->renderer->render($projection);

    $directory = rtrim($this->outputDirectory, DIRECTORY_SEPARATOR);
    if ($directory === '') {
      throw new RuntimeException('Mailstack outputdirectory ontbreekt.');
    }
    if (!is_dir($directory) && !mkdir($directory, 0700, TRUE) && !is_dir($directory)) {
      throw new RuntimeException('Mailstack outputdirectory kan niet worden aangemaakt.');
    }

    foreach ($bundle as $name => $content) {
      $path = $directory . DIRECTORY_SEPARATOR . $name;
      $temp = $path . '.tmp';
      if (file_put_contents($temp, $content, LOCK_EX) === FALSE) {
        throw new RuntimeException('Mailstack output kon niet worden geschreven: ' . $name);
      }
      chmod($temp, 0600);
      if (!rename($temp, $path)) {
        @unlink($temp);
        throw new RuntimeException('Mailstack output kon niet atomair worden gepubliceerd: ' . $name);
      }
    }

    return $bundle;
  }
}
