const { test } = require('node:test');
const assert = require('node:assert/strict');
const vm = require('node:vm');
const { execFileSync } = require('node:child_process');
const path = require('node:path');
const source = path.resolve(__dirname, '../../office-independent/src/Inzet/OnSiteOfficeActivationPage.php');
const script = execFileSync(process.env.PHP_BINARY || 'php', ['-r', `require ${JSON.stringify(source)}; echo \\Brebo\\Office\\Inzet\\OnSiteOfficeActivationPage::script();`], { encoding: 'utf8' });
function open(hash) {
  const button = { hidden: true };
  const status = {};
  vm.runInNewContext(script, {
    URLSearchParams, encodeURIComponent,
    window: { location: { hash } },
    document: { getElementById: id => id === 'open-onsite' ? button : status },
  });
  return { button, status };
}
test('personal fragment produces the registered app link on explicit button only', () => {
  const token = 'a'.repeat(43);
  const { button, status } = open('#activation=' + token);
  assert.equal(button.href, 'brebo-onsite://activate?token=' + token);
  assert.equal(button.hidden, false);
  assert.match(status.textContent, /Office-testversie/);
});
for (const hash of ['', '#activation=short', '#activation=%3Cscript%3E', '#activation=' + 'a'.repeat(44)]) {
  test('invalid or missing credential never enables app navigation: ' + hash.slice(0, 25), () => {
    const { button, status } = open(hash);
    assert.equal(button.hidden, true);
    assert.equal(button.href, undefined);
    assert.match(status.textContent, /ontbreekt of is ongeldig/);
  });
}
test('fragment cannot select an arbitrary app scheme or server', () => {
  const { button } = open('#activation=' + 'a'.repeat(43) + '&server=https://evil.example&scheme=evil');
  assert.equal(button.href, 'brebo-onsite://activate?token=' + 'a'.repeat(43));
});
