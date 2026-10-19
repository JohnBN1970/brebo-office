<?php
declare(strict_types=1);
/**
 * Source-level contract regression: documents incompatibilities until the
 * canonical token issuer and presence policy are explicitly decided.
 */
$root = dirname(__DIR__, 2);
$read = static function (string $path) use ($root): string {
    $source = file_get_contents($root . '/' . $path);
    if (!is_string($source)) throw new RuntimeException('Missing source: ' . $path);
    return $source;
};
$invite = $read('web/modules/custom/brebo_inzet/src/Service/OnSiteInvitationManager.php');
$drupalActivation = $read('web/modules/custom/brebo_inzet/src/Service/OnSiteActivationManager.php');
$officeActivation = $read('office-independent/public/onsite-activate.php');
$swift = $read('ios/CrewPOC/Sources/OnSiteAPIClient.swift');
$drupalPresence = $read('web/modules/custom/brebo_inzet/src/Controller/OnSitePresenceController.php');
$inviteController = $read('web/modules/custom/brebo_inzet/src/Controller/OnSiteInviteController.php');
if (str_contains($inviteController, "locatietoegang 'Altijd'")
    || str_contains($inviteController, 'expliciete klokactie')) {
    throw new RuntimeException('Invitation must not promise background location tracking');
}
if (!str_contains($inviteController, 'automatisch in- en uitklokken')
    || !str_contains($inviteController, 'nog niet vrijgegeven')) {
    throw new RuntimeException('Invitation must state the automatic target without promising release');
}
foreach ([
    [$invite, '$this->activationManager->issue($uid)'],
    [$drupalActivation, '$this->store->set('],
    [$drupalActivation, '$this->store->delete('],
    [$officeActivation, 'OnSiteActivationService'],
    [$officeActivation, 'OnSiteDeviceTokenRepository'],
    [$swift, '/api/onsite/v1/activate'],
    [$swift, '/api/onsite/v1/bootstrap'],
    [$swift, '/api/onsite/v1/presence'],
    [$drupalPresence, 'explicit_clock_action_required'],
] as [$source,$expected]) {
    if (!str_contains($source,$expected)) throw new RuntimeException('OnSite contract changed: '.$expected);
}
if (str_contains($swift, '"trigger": "clock_action"')) {
    throw new RuntimeException('Presence contract changed: review and update the integration decision');
}
echo "OnSite invitation/activation contract boundaries checked; identity and token source remain blockers\n";
