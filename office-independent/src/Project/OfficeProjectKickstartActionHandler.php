<?php
declare(strict_types=1);
namespace Brebo\Office\Project;

use DomainException;

/**
 * Authenticated Office controller adapter. The caller must derive all values
 * in $context from the server-side session and project ACL, never POST data.
 * CSRF is checked here before any mutation.
 */
final class OfficeProjectKickstartActionHandler
{
    public function __construct(
        private readonly OfficeProjectKickstartRepository $dossiers,
        private readonly OfficeProjectKickstartQuestionsRepository $questions
    ) {}

    public function handle(array $post, array $context): array
    {
        $actorId = (int)($context['actor_user_id'] ?? 0);
        $dossierId = filter_var($post['dossier_id'] ?? null, FILTER_VALIDATE_INT);
        $revision = filter_var($post['expected_revision'] ?? null, FILTER_VALIDATE_INT);
        $csrf = $context['csrf_token'] ?? null;
        if ($actorId < 1 || !is_int($dossierId) || $dossierId < 1
            || !is_int($revision) || $revision < 1
            || !is_string($csrf) || $csrf === ''
            || !is_string($post['csrf_token'] ?? null)
            || !hash_equals($csrf, $post['csrf_token'])) {
            throw new DomainException('Invalid session, revision or CSRF token');
        }
        // Scope binding must be established by the Office controller after
        // fetching the dossier's project_id and scope from the database.
        if (($context['authorized_dossier_id'] ?? null) !== $dossierId) {
            throw new DomainException('Dossier outside authorized scope');
        }
        $canEdit = ($context['can_edit'] ?? false) === true;
        $canSubmit = ($context['can_submit'] ?? false) === true;
        $canApprove = ($context['can_approve'] ?? false) === true;
        $action = (string)($post['kickstart_action'] ?? '');
        if ($action === 'save_checklist') {
            $raw = $post['items'] ?? [];
            if (!is_array($raw)) throw new DomainException('Invalid checklist');
            $items = [];
            foreach (OfficeProjectKickstartPolicy::REQUIRED_ITEMS as $key) {
                $value = $raw[$key] ?? null;
                if ($value !== null && $value !== '1') throw new DomainException('Invalid checklist input');
                $items[$key] = $value === '1';
            }
            if (array_diff(array_keys($raw), OfficeProjectKickstartPolicy::REQUIRED_ITEMS) !== []) {
                throw new DomainException('Unexpected checklist key');
            }
            $critical = filter_var($post['critical_open_points'] ?? null, FILTER_VALIDATE_INT);
            if (!is_int($critical) || $critical < 0) throw new DomainException('Invalid critical count');
            return $this->dossiers->saveChecklist($dossierId,$revision,$actorId,$canEdit,$items,$critical);
        }
        if ($action === 'submit_for_approval') {
            return $this->dossiers->submitForApproval($dossierId,$revision,$actorId,$canSubmit);
        }
        if ($action === 'approve' || $action === 'reject') {
            $note = trim((string)($post['note'] ?? ''));
            if (mb_strlen($note) > 2000) throw new DomainException('Approval note too long');
            return $this->dossiers->approve($dossierId,$revision,$actorId,$canApprove,$action,$note);
        }
        if ($action === 'add_question') {
            $owner = $post['owner_user_id'] ?? '';
            $ownerId = $owner === '' ? null : filter_var($owner,FILTER_VALIDATE_INT);
            if ($ownerId === false) throw new DomainException('Invalid owner');
            $date = (string)($post['due_date'] ?? '');
            return $this->questions->add(
                $dossierId,$revision,$actorId,$canEdit,
                (string)($post['question'] ?? ''),(string)($post['answer'] ?? ''),
                (string)($post['decision'] ?? ''),$ownerId,$date === '' ? null : $date,
                ($post['critical'] ?? null) === '1'
            );
        }
        throw new DomainException('Unknown kickstart action');
    }
}
