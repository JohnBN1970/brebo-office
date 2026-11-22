<?php
declare(strict_types=1);
namespace Brebo\Office\Process;

use InvalidArgumentException;

/**
 * Fail-closed authorization adapter for the existing Office rights engine.
 * Never derive a grant from a browser role preview or a submitted form field.
 */
final class OfficeProcessFormAuthorization
{
    private const ACTIONS = ['create','view','revise','submit','start_review','approve','reject','return','reopen'];

    /**
     * $resolver must be a server-side Office permission callback returning
     * true only after checking the real actor, resource context and mandate.
     */
    public static function permitted(
        callable $resolver,
        string $actor,
        string $action,
        string $contextType,
        int $contextId,
        string $documentCode
    ): bool {
        require_once __DIR__.'/OfficeProcessFormCatalog.php';
        if (trim($actor)==='' || !in_array($action,self::ACTIONS,true)
            || !preg_match('/^[a-z_]{2,32}$/',$contextType)
            || $contextId<1 || OfficeProcessFormCatalog::get($documentCode)===null) {
            return false;
        }
        try {
            return $resolver($actor,$action,$contextType,$contextId,$documentCode) === true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function assertPermitted(
        callable $resolver,
        string $actor,
        string $action,
        string $contextType,
        int $contextId,
        string $documentCode
    ): void {
        if (!self::permitted($resolver,$actor,$action,$contextType,$contextId,$documentCode)) {
            throw new InvalidArgumentException('Geen bevoegdheid voor deze formulierhandeling');
        }
    }
}
