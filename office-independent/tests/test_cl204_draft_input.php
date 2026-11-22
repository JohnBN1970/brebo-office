<?php
declare(strict_types=1);
require_once __DIR__.'/../src/Process/OfficeCl204DraftInput.php';
require_once __DIR__.'/../src/Process/OfficeCl204FormView.php';

use Brebo\Office\Process\OfficeCl204DraftInput;
use Brebo\Office\Process\OfficeCl204FormView;
use Brebo\Office\Process\OfficeCl204Projectopname;

function check(bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException($message);
}
function mustReject(array $input, string $message): void {
    try { OfficeCl204DraftInput::parse($input); }
    catch (InvalidArgumentException $e) { return; }
    throw new RuntimeException($message);
}
$controls = [];
foreach (OfficeCl204Projectopname::controls() as $key => $_) {
    $controls[$key] = ['status'=>'open'];
}
$post = ['cl204'=>['controls'=>$controls], 'cl204_disciplines'=>"Bouwkundig\r\nGlas\nBouwkundig"];
$existing = ['observations'=>[['observation'=>'scheur', 'evidence_refs'=>['document:12']]],'release_status'=>'V3'];
$parsed = OfficeCl204DraftInput::parse($post, $existing);
check(count($parsed['controls'])===17,'17 controls required');
check($parsed['disciplines']===['Bouwkundig','Glas'],'discipline lines must deduplicate');
check($parsed['observations']===$existing['observations'],'evidence must survive editing');
check($parsed['release_status']==='V3','release status must not be modified');
$html = OfficeCl204FormView::render($parsed);
check(str_contains($html,'name="cl204[controls][opnameopdracht][status]"'),'view must expose controls');
check(str_contains($html,'Bouwkundig'."\n".'Glas'),'view must show discipline lines');
$escaped = OfficeCl204FormView::render(['disciplines'=>['<script>alert(1)</script>']]);
check(!str_contains($escaped,'<script>'),'untrusted disciplines must be HTML-escaped');
mustReject(['cl204'=>['controls'=>$controls,'release_status'=>'V1']],'client release status must be rejected');
$bad=$post;
$bad['cl204']['controls']['opnameopdracht']['status']='approved';
mustReject($bad,'unknown control status must be rejected');
$bad=$post;
$bad['cl204']['controls']['opnameopdracht']['extra']='forged';
mustReject($bad,'unknown control properties must be rejected');
$bad=$post;
$bad['cl204_disciplines']=str_repeat('x',2001);
mustReject($bad,'oversized disciplines must be rejected');
echo "CL-204 draft parser and view OK\n";
