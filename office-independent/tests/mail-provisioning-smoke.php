<?php
declare(strict_types=1);
require __DIR__.'/../../packages/brebo-mail-core/src/Contract/MailGatewayInterface.php';
require __DIR__.'/../src/Mail/OfficeTestMailboxProvisioning.php';
use Brebo\Mail\Contract\MailGatewayInterface;
use Brebo\Office\Mail\OfficeTestMailboxProvisioning;
final class FakeGateway implements MailGatewayInterface {
    public bool $available = true;
    public string $reference = 'test-reference';
    public array $calls = [];
    public function health(): array {
        return ['provider'=>'fake','available'=>$this->available,'message'=>'test'];
    }
    public function provisionDomain(array $domain): string { throw new LogicException('Unexpected domain write'); }
    public function provisionAlias(string $aliasAddress,string $targetAddress): string { throw new LogicException('Unexpected alias write'); }
    public function provisionMailbox(array $mailbox): string {
        $this->calls[] = $mailbox;
        return $this->reference;
    }
}
function check(bool $ok): void { if (!$ok) throw new LogicException('Assertion failed'); }
function rejected(callable $fn): void {
    try { $fn(); } catch (RuntimeException $e) { return; }
    throw new LogicException('Expected rejection');
}
$g = new FakeGateway();
$s = new OfficeTestMailboxProvisioning($g);
putenv('OFFICE_TEST_MAIL_PROVISIONING_ENABLED=disabled');
rejected(fn()=> $s->stage('test@testmail.brebobv.nl'));
putenv('OFFICE_TEST_MAIL_PROVISIONING_ENABLED=enabled');
rejected(fn()=> $s->stage('test@brebobv.nl'));
rejected(fn()=> $s->stage('bad..name@testmail.brebobv.nl'));
$g->available = false;
rejected(fn()=> $s->stage('test@testmail.brebobv.nl'));
check($g->calls === []);
$g->available = true;
$g->reference = '';
rejected(fn()=> $s->stage('test@testmail.brebobv.nl'));
$g->reference = 'test-reference';
$result = $s->stage(' TEST@testmail.brebobv.nl ');
check($result === ['address'=>'test@testmail.brebobv.nl','reference'=>'test-reference','status'=>'staged','active'=>false]);
check($g->calls === [['address'=>'test@testmail.brebobv.nl'],['address'=>'test@testmail.brebobv.nl']]);
echo "MAIL_PROVISIONING_SMOKE=PASS\n";
