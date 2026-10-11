<?php

declare(strict_types=1);

namespace Brebo\Office\Intake;

/** Strict parsing of operator-submitted CRM draft edits. */
final class WebsiteCrmDraftSubmissionValidator {

  /** @param array<string,mixed> $post
   *  @return array{id:int,title:string,stage:string,version:int}
   */
  public function validate(array $post): array {
    foreach (['id', 'version', 'title', 'stage'] as $key) {
      if (!isset($post[$key]) || !is_string($post[$key])) {
        throw new \InvalidArgumentException('Missing or invalid CRM draft field.');
      }
    }
    $id = $post['id'];
    $version = $post['version'];
    $title = trim($post['title']);
    $stage = trim($post['stage']);
    if (!ctype_digit($id) || strlen($id) > 18 || (int) $id < 1
      || !ctype_digit($version) || strlen($version) > 9
      || $title === '' || mb_strlen($title) > 255
      || $stage === '' || mb_strlen($stage) > 80) {
      throw new \InvalidArgumentException('Invalid CRM draft values.');
    }
    return ['id' => (int) $id, 'version' => (int) $version, 'title' => $title, 'stage' => $stage];
  }
}
