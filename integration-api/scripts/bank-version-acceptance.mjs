import { createHash, createHmac, randomUUID } from 'node:crypto';
import { readFile, writeFile } from 'node:fs/promises';
import { resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const bankPath = '/v1/accounting/bank-data';
const versionHost = /^[a-z0-9-]+-brebo-integration-api\.john-boon\.workers\.dev$/;

export function extractVersionUrl(output) {
  const candidates = output.match(/https:\/\/[a-z0-9.-]+/g) ?? [];
  const candidate = candidates.find(value => versionHost.test(new URL(value).hostname));
  if (!candidate) throw new Error('No bank Worker version URL returned; active settings were not changed.');
  return candidate;
}

export function deploymentFingerprint(value) {
  if (!Array.isArray(value) || value.length === 0) throw new Error('Missing active deployment evidence.');
  const latest = [...value].sort((a, b) => String(a.created_on).localeCompare(String(b.created_on))).at(-1);
  if (!latest || typeof latest.id !== 'string' || typeof latest.created_on !== 'string'
      || !Array.isArray(latest.versions) || latest.versions.length === 0) throw new Error('Invalid active deployment evidence.');
  const versions = latest.versions.map(row => {
    if (typeof row.version_id !== 'string' || typeof row.percentage !== 'number') throw new Error('Invalid deployment versions.');
    return { version_id: row.version_id, percentage: row.percentage };
  }).sort((a,b) => a.version_id.localeCompare(b.version_id));
  return JSON.stringify({ id: latest.id, versions });
}

function keys(value, allowed) {
  return value !== null && typeof value === 'object' && !Array.isArray(value)
    && Object.keys(value).every(key => allowed.includes(key));
}

export function validateBankPayload(value) {
  if (!keys(value, ['status','request_id','provider','bank']) || value.status !== 'ok' || value.provider !== 'moneybird'
      || !keys(value.bank, ['balances_available','period','accounts','transactions'])) throw new Error('Invalid bank-only response.');
  const bank = value.bank;
  if (bank.balances_available !== false || bank.period !== 'this_month' || !Array.isArray(bank.accounts)
      || !Array.isArray(bank.transactions) || bank.transactions.length > 100) throw new Error('Unverified bank scope.');
  const accounts = new Set();
  for (const row of bank.accounts) {
    if (!keys(row, ['id','name','identifier','currency']) || typeof row.id !== 'string' || !/^\d+$/.test(row.id)
        || accounts.has(row.id) || typeof row.name !== 'string' || typeof row.identifier !== 'string'
        || typeof row.currency !== 'string' || !/^[A-Z]{3}$/.test(row.currency)) throw new Error('Invalid bank account evidence.');
    accounts.add(row.id);
  }
  const mutations = new Set();
  for (const row of bank.transactions) {
    if (!keys(row, ['id','financial_account_id','amount','date','message']) || typeof row.id !== 'string'
        || !/^\d+$/.test(row.id) || mutations.has(row.id) || !accounts.has(row.financial_account_id)
        || typeof row.amount !== 'string' || !/^-?\d+(?:\.\d{1,4})?$/.test(row.amount)
        || typeof row.date !== 'string' || !/^\d{4}-\d{2}-\d{2}$/.test(row.date)
        || typeof row.message !== 'string') throw new Error('Invalid bank mutation evidence.');
    mutations.add(row.id);
  }
  return { accounts: bank.accounts.length, transactions: bank.transactions.length, period: bank.period, balances_available: false };
}

export async function checkBankVersion(baseUrl, secret, fetcher = fetch) {
  const base = new URL(baseUrl);
  if (base.protocol !== 'https:' || !versionHost.test(base.hostname) || base.port || base.username || base.password
      || base.search || base.hash || base.pathname !== '/') throw new Error('Only the bank Worker version URL is permitted.');
  if (typeof secret !== 'string' || secret.length < 24) throw new Error('Bank acceptance secret is not configured.');
  const url = base.origin + bankPath;
  const unsigned = await fetcher(url, { method:'GET', redirect:'error', signal:AbortSignal.timeout(15000) });
  await unsigned.body?.cancel();
  if (unsigned.status !== 401) throw new Error(`Unsigned bank request returned HTTP ${unsigned.status}; expected 401.`);
  const timestamp = String(Math.floor(Date.now()/1000));
  const requestId = randomUUID();
  const canonical = ['GET',bankPath,createHash('sha256').update('').digest('hex'),timestamp,requestId].join('\n');
  const headers = { Accept:'application/json','X-BREBO-Request-Id':requestId,'X-BREBO-Timestamp':timestamp,
    'X-BREBO-Signature':'v1='+createHmac('sha256',secret).update(canonical).digest('hex') };
  const signed = await fetcher(url, { method:'GET', redirect:'error', signal:AbortSignal.timeout(15000), headers });
  if (signed.status !== 200) {
    await signed.body?.cancel();
    throw new Error(`Signed bank request returned HTTP ${signed.status}; no bank data logged.`);
  }
  if (!signed.headers.get('cache-control')?.includes('no-store')) throw new Error('Bank response must disable caching.');
  let payload;
  try { payload = await signed.json(); } catch { throw new Error('Bank response is not valid JSON.'); }
  return { unsigned_http_status:unsigned.status, signed_http_status:signed.status, ...validateBankPayload(payload) };
}

async function main() {
  const [mode, first, second] = process.argv.slice(2);
  if (mode === 'extract-url') {
    const url = extractVersionUrl(await readFile(first, 'utf8'));
    await writeFile(second, url+'\n', { mode:0o600 });
    console.log('BANK_VERSION_URL='+url);
  } else if (mode === 'upload-error') {
    const output = await readFile(first,'utf8');
    console.error('BANK_VERSION_UPLOAD_PROVIDER_CODE='+(output.match(/code:\s*(\d+)/)?.[1] ?? 'unavailable'));
    for (const [label,pattern] of [['AUTH',/authentication|unauthorized|permission/i],['MIGRATION',/migration/i],['PREVIEW',/preview/i]]) {
      if (pattern.test(output)) console.error('BANK_VERSION_UPLOAD_ERROR_CATEGORY='+label);
    }
  } else if (mode === 'compare-deployments') {
    const before = deploymentFingerprint(JSON.parse(await readFile(first,'utf8')));
    const after = deploymentFingerprint(JSON.parse(await readFile(second,'utf8')));
    if (before !== after) throw new Error('Active deployment changed during acceptance; investigate before proceeding.');
    console.log('ACTIVE_WORKER_DEPLOYMENT_UNCHANGED=1');
  } else if (mode === 'check') {
    const result = await checkBankVersion((await readFile(first,'utf8')).trim(), process.env.BREBO_SHARED_SECRET);
    console.log(JSON.stringify(result));
    console.log('BANK_VERSION_READ_ACCEPTANCE_OK=1');
  } else throw new Error('Unknown bank acceptance mode.');
}
if (process.argv[1] && resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
  main().catch(error => { console.error(error.message); process.exitCode = 1; });
}
