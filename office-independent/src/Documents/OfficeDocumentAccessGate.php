<?php
declare(strict_types=1);

namespace Brebo\Office\Documents;

use Closure;
use InvalidArgumentException;
use RuntimeException;

/**
 * Authorization boundary for document actions.
 * Caller must provide a server-side authenticated actor and a trusted
 * context authorization callback. No request-supplied grants are accepted.
 */
final class OfficeDocumentAccessGate
{
    /** @param Closure(string,string,string,string):bool $canAccessContext */
    public function __construct(private readonly Closure $canAccessContext) {}

    /**
     * @param list<array{context_type:string,context_id:string}> $contexts
     * @param string $actor Authenticated server-side actor identity
     */
    public function requireAccess(string $actor, array $contexts, string $action): void
    {
        if ($actor === '' || !in_array($action, ['read', 'upload', 'link'], true)) {
            throw new InvalidArgumentException('invalid_document_access_request');
        }
        if ($contexts === []) {
            throw new RuntimeException('document_access_denied');
        }
        foreach ($contexts as $context) {
            $type = $context['context_type'] ?? '';
            $id = $context['context_id'] ?? '';
            if (!in_array($type, ['building', 'project', 'organization', 'contact', 'brebo'], true) || !is_string($id) || $id === '') {
                throw new RuntimeException('document_access_denied');
            }
            // Require access to EVERY linked context to prevent cross-dossier leakage.
            if (($this->canAccessContext)($actor, $type, $id, $action) !== true) {
                throw new RuntimeException('document_access_denied');
            }
        }
    }
}
