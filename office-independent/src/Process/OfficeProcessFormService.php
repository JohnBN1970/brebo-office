<?php
declare(strict_types=1);
namespace Brebo\Office\Process;

use InvalidArgumentException;
use RuntimeException;

/**
 * Authenticated application boundary for controlled process forms.
 * The host supplies the *real* Office operator, authorization resolver and
 * validated CSRF outcome. Browser role previews cannot grant permissions.
 */
final class OfficeProcessFormService
{
    public function __construct(
        private readonly OfficeProcessFormRepository $repository,
        private readonly \Closure $permissionResolver,
        private readonly \Closure $fourEyesPolicyResolver
    ) {}

    public function create(string $code, string $version, string $contextType, int $contextId, string $actor, array $data, string $reason, bool $csrfValid): int
    {
        $this->guard($csrfValid,$actor,'create',$contextType,$contextId,$code);
        return $this->repository->create($code,$version,$contextType,$contextId,$actor,$data,$reason);
    }

    public function load(int $id, string $actor): ?array
    {
        $form=$this->repository->load($id);
        if ($form===null) return null;
        $this->guard(true,$actor,'view',(string)$form['context_type'],(int)$form['context_id'],(string)$form['document_code']);
        return $form;
    }

    public function revise(int $id, int $expectedRevision, string $actor, array $data, string $reason, bool $csrfValid): int
    {
        $form=$this->requireForm($id);
        $this->guard($csrfValid,$actor,'revise',(string)$form['context_type'],(int)$form['context_id'],(string)$form['document_code']);
        return $this->repository->revise($id,$expectedRevision,$actor,$data,$reason);
    }

    public function transition(int $id, int $expectedRevision, string $action, string $actor, string $reason, bool $csrfValid): array
    {
        $form=$this->requireForm($id);
        $this->guard($csrfValid,$actor,$action,(string)$form['context_type'],(int)$form['context_id'],(string)$form['document_code']);
        // Four-eyes is an Office blueprint setting, never a client-supplied flag.
        $fourEyes = ($this->fourEyesPolicyResolver)((string)$form['document_code'],(string)$form['context_type'],(int)$form['context_id']);
        if (!is_bool($fourEyes)) throw new RuntimeException('Vierogenbeleid niet beschikbaar');
        return $this->repository->transition($id,$expectedRevision,$action,$actor,$fourEyes,true,$reason);
    }

    private function requireForm(int $id): array
    {
        $form=$this->repository->load($id);
        if ($form===null) throw new RuntimeException('Formulier niet gevonden');
        return $form;
    }

    private function guard(bool $csrfValid, string $actor, string $action, string $contextType, int $contextId, string $code): void
    {
        if (!$csrfValid) throw new InvalidArgumentException('Ongeldige formulierbeveiliging');
        require_once __DIR__.'/OfficeProcessFormAuthorization.php';
        OfficeProcessFormAuthorization::assertPermitted($this->permissionResolver,$actor,$action,$contextType,$contextId,$code);
    }
}
