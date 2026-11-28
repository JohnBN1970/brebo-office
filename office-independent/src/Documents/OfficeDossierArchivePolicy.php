<?php
declare(strict_types=1);
namespace Brebo\Office\Documents;

/** Pure lifecycle policy. No file deletion or status publication side effects. */
final class OfficeDossierArchivePolicy
{
    private const NEXT = [
        'active' => ['inventory'],
        'inventory' => ['compressing', 'archive_failed'],
        'compressing' => ['verifying', 'archive_failed'],
        'verifying' => ['archived', 'archive_failed'],
        'archive_failed' => ['inventory', 'active'],
        'archived' => ['restoring'],
        'restoring' => ['restore_verifying', 'restore_failed'],
        'restore_verifying' => ['active', 'restore_failed'],
        'restore_failed' => ['restoring'],
    ];

    public static function canTransition(string $from, string $to, array $evidence = []): bool
    {
        if (!in_array($to, self::NEXT[$from] ?? [], true)) return false;
        $required = match ($to) {
            'inventory' => ['authorized', 'lifecycle_eligible'],
            'archived' => ['manifest_verified', 'content_verified', 'encrypted_storage_verified', 'restore_test_verified'],
            'restoring' => ['authorized'],
            'active' => $from === 'restore_verifying'
                ? ['manifest_verified', 'content_verified', 'document_links_restored']
                : ['source_files_verified'],
            default => [],
        };
        foreach ($required as $key) {
            if (($evidence[$key] ?? null) !== true) return false;
        }
        return true;
    }
}
