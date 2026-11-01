<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Inzet/OfficePersonnelProfileInput.php';

use Brebo\Office\Inzet\OfficePersonnelProfileInput as Profile;

function check(bool $condition, string $message): void {
  if (!$condition) throw new RuntimeException($message);
}
function rejects(callable $callback): void {
  try { $callback(); } catch (InvalidArgumentException $exception) { return; }
  throw new RuntimeException('Expected invalid input rejection');
}
check(Profile::phone('NL', '06 12345678') === '+31612345678', 'Dutch mobile');
check(Profile::phone('BE', '0470 12 34 56') === '+32470123456', 'Belgian mobile');
check(Profile::phone('PL', '+48 501 234 567') === '+48501234567', 'International number');
rejects(fn() => Profile::phone('NL', '+32470123456'));
rejects(fn() => Profile::phone('NL', 'abc'));
rejects(fn() => Profile::phone('NL', '06'));
check(Profile::language('PL', null) === 'pl', 'Nationality default');
check(Profile::language('PL', 'nl') === 'nl', 'Explicit preference overrides nationality');
check(Profile::language('BE', null) === 'nl', 'Belgian default');
rejects(fn() => Profile::language('PL', 'xx'));
check(Profile::skills(['Glaszetten', ' glaszetten ', 'Kozijnmontage']) === ['Glaszetten', 'Kozijnmontage'], 'Unique skills');
rejects(fn() => Profile::skills(['']));
echo "Personnel profile input contract: PASS\n";
