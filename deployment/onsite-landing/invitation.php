<?php
declare(strict_types=1);

// Read-only: only the app's activation endpoint may consume an invitation.
function onsiteInvitation(PDO $db, string $hash): ?array
{
    if (!preg_match('/\A[a-f0-9]{64}\z/D', $hash)) return null;
    $q = $db->prepare('SELECT i.person_id, p.display_name, i.expires_at FROM office_onsite_activation_invitations i JOIN office_inzet_people p ON p.id=i.person_id AND p.active=1 WHERE i.token_hash=:hash AND i.consumed_at IS NULL AND i.expires_at>UTC_TIMESTAMP()');
    $q->execute(['hash'=>$hash]);
    $row = $q->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function onsiteTokenHash(string $token): ?string
{
    return preg_match('/\A[A-Za-z0-9_-]{32,128}\z/D', $token) ? hash('sha256', $token) : null;
}
