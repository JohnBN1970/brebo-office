<?php
declare(strict_types=1);

namespace Brebo\Office\Calculation;

use DomainException;

/**
 * Explicit policy for draft line review and commercial release.
 * The caller must resolve the operator's verified organizational rank
 * from the Office authorization system; never trust a posted role or rank.
 */
final class OfficeCalculationLineApproval {
  public const DRAFT='draft';
  public const REVIEWED='reviewed';
  public const RELEASED='released';

  public static function review(array $line,int $actorId,string $currentFingerprint,string $note='',?int $authorId=null,?int $verifiedAuthorRank=null,?int $verifiedReviewerRank=null): array {
    self::assertActor($actorId);
    self::assertFingerprint($currentFingerprint);
    // Fail closed: a review requires verified hierarchy, not browser-supplied ranks.
    if ($authorId===null || $authorId<1 || $verifiedAuthorRank===null
      || $verifiedReviewerRank===null || $actorId===$authorId
      || $verifiedReviewerRank<=$verifiedAuthorRank) {
      throw new DomainException('higher_independent_reviewer_required');
    }
    if (($line['author_id']??$authorId)!==$authorId) {
      throw new DomainException('line_author_mismatch');
    }
    $line['author_id']=$authorId;
    if (($line['status']??self::DRAFT)===self::RELEASED) {
      throw new DomainException('released_line_requires_new_revision');
    }
    $line['status']=self::REVIEWED;
    $line['fingerprint']=$currentFingerprint;
    $line['reviewed_by']=$actorId;
    $line['reviewed_at']=gmdate('c');
    $line['review_note']=trim($note);
    unset($line['released_by'],$line['released_at'],$line['release_note']);
    return $line;
  }

  public static function release(array $line,int $actorId,bool $verifiedHeadSalesOrAbove,string $currentFingerprint,string $note=''): array {
    self::assertActor($actorId);
    self::assertFingerprint($currentFingerprint);
    if (!$verifiedHeadSalesOrAbove) throw new DomainException('head_sales_release_required');
    if (($line['status']??null)!==self::REVIEWED
      || !hash_equals((string)($line['fingerprint']??''),$currentFingerprint)) {
      throw new DomainException('line_review_outdated');
    }
    if ((int)($line['reviewed_by']??0)===$actorId || (int)($line['author_id']??0)===$actorId) {
      throw new DomainException('four_eyes_release_required');
    }
    $line['status']=self::RELEASED;
    $line['released_by']=$actorId;
    $line['released_at']=gmdate('c');
    $line['release_note']=trim($note);
    return $line;
  }

  public static function invalidateAfterEdit(array $line,string $newFingerprint): array {
    self::assertFingerprint($newFingerprint);
    if (($line['fingerprint']??null)===$newFingerprint) return $line;
    $line['status']=self::DRAFT;
    $line['fingerprint']=$newFingerprint;
    unset($line['reviewed_by'],$line['reviewed_at'],$line['review_note'],$line['released_by'],$line['released_at'],$line['release_note']);
    return $line;
  }

  private static function assertActor(int $actorId): void {
    if ($actorId<1) throw new DomainException('invalid_actor');
  }

  private static function assertFingerprint(string $fingerprint): void {
    if (!preg_match('/^[a-f0-9]{64}$/D',$fingerprint)) throw new DomainException('invalid_line_fingerprint');
  }
}
