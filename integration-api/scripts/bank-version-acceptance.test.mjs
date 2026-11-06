import { test } from 'node:test';
import assert from 'node:assert/strict';
import { checkBankVersion, deploymentFingerprint, extractVersionUrl, validateBankPayload, zeroTrafficPlan, trafficState, activationTrafficState, checkPromotedBank } from './bank-version-acceptance.mjs';

const versionUrl = 'https://12345678-brebo-integration-api.john-boon.workers.dev';
const baselineId='11111111-1111-4111-8111-111111111111';
const candidateId='22222222-2222-4222-8222-222222222222';
const baseline=[{id:'baseline',created_on:'2026-11-06',versions:[{version_id:baselineId,percentage:100}]}];
const valid = () => ({status:'ok',request_id:'test',provider:'moneybird',bank:{balances_available:false,period:'this_month',
  accounts:[{id:'123',name:'Test',identifier:'NL00TEST',currency:'EUR'}],
  transactions:[{id:'456',financial_account_id:'123',amount:'-12.3400',date:'2026-11-06',message:'PRIVATE-BANK-MARKER'}]}});

test('extracts only the owned version URL, never production or another Worker', () => {
  assert.equal(extractVersionUrl('Preview URL: '+versionUrl),versionUrl);
  assert.throws(()=>extractVersionUrl('https://brebo-integration-api.john-boon.workers.dev'));
  assert.throws(()=>extractVersionUrl('https://bank.evil.test'));
});
test('compares the latest deployment and traffic regardless of list order', () => {
  const old={id:'old',created_on:'2026-10-01',versions:[{version_id:'v0',percentage:100}]};
  const current={id:'current',created_on:'2026-11-01',versions:[{version_id:'v1',percentage:100}]};
  assert.equal(deploymentFingerprint([old,current]),deploymentFingerprint([current,old]));
  assert.notEqual(deploymentFingerprint([current]),deploymentFingerprint([{...current,versions:[{version_id:'v2',percentage:100}]}]));
  assert.throws(()=>deploymentFingerprint([]));
});
test('reports only counts and verified scope from fixture bank data', () => {
  assert.deepEqual(validateBankPayload(valid()),{accounts:1,transactions:1,period:'this_month',balances_available:false});
});
for (const [name,change] of [
  ['invoice root',p=>{p.sales_invoices=[];}],
  ['invoice link',p=>{p.bank.transactions[0].payments=[];}],
  ['balances',p=>{p.bank.balances_available=true;}],
  ['wrong period',p=>{p.bank.period='this_year';}],
  ['numeric amount',p=>{p.bank.transactions[0].amount=12.34;}],
  ['unknown account',p=>{p.bank.transactions[0].financial_account_id='999';}],
  ['duplicate mutation',p=>{p.bank.transactions.push(p.bank.transactions[0]);}],
]) test('rejects '+name,()=>{const p=valid();change(p);assert.throws(()=>validateBankPayload(p));});
test('sends only GET to the owned version URL and never logs private bank contents', async () => {
  const calls=[];
  const result=await checkBankVersion(versionUrl,'test-secret-with-sufficient-length',async(url,init)=>{
    calls.push({url,init});
    return calls.length===1?new Response('',{status:401}):Response.json(valid(),{headers:{'Cache-Control':'no-store, private'}});
  });
  assert.equal(calls.length,2);
  assert(calls.every(call=>call.url===versionUrl+'/v1/accounting/bank-data'&&call.init.method==='GET'&&call.init.redirect==='error'));
  assert.match(calls[1].init.headers['X-BREBO-Signature'],/^v1=[a-f0-9]{64}$/);
  assert(!JSON.stringify(result).includes('PRIVATE-BANK-MARKER'));
});
test('rejects production, unowned URLs and provider failures without echoing bodies', async () => {
  let calls=0;
  const fetcher=async()=>{calls++;return new Response('PRIVATE-PROVIDER-MARKER',{status:503});};
  await assert.rejects(checkBankVersion('https://brebo-integration-api.john-boon.workers.dev','secret'.repeat(6),fetcher));
  assert.equal(calls,0);
  await assert.rejects(checkBankVersion(versionUrl,'secret'.repeat(6),fetcher),error=>!error.message.includes('PRIVATE-PROVIDER-MARKER'));
});
test('requires a single baseline at 100% before preparing a zero-traffic plan',()=>{
  assert.deepEqual(zeroTrafficPlan(baseline,'Worker Version ID: '+candidateId),{baseline_version_id:baselineId,candidate_version_id:candidateId});
  assert.throws(()=>zeroTrafficPlan([{...baseline[0],versions:[{version_id:baselineId,percentage:90}]}],'Worker Version ID: '+candidateId));
  assert.throws(()=>zeroTrafficPlan(baseline,'Worker Version ID: '+baselineId));
});
test('verifies exact zero-traffic allocation and refuses unexpected deployment changes',()=>{
  const plan=zeroTrafficPlan(baseline,'Worker Version ID: '+candidateId);
  assert.equal(trafficState(baseline,plan),'baseline');
  assert.equal(trafficState([{...baseline[0],versions:[{version_id:baselineId,percentage:100},{version_id:candidateId,percentage:0}]}],plan),'zero-test');
  assert.throws(()=>trafficState([{...baseline[0],versions:[{version_id:baselineId,percentage:99},{version_id:candidateId,percentage:1}]}],plan));
});
test('allows the production URL only with a validated per-request version override',async()=>{
  const calls=[];
  await checkBankVersion('https://brebo-integration-api.john-boon.workers.dev','secret'.repeat(6),async(url,init)=>{
    calls.push(init);
    return calls.length===1?new Response('',{status:401}):Response.json(valid(),{headers:{'Cache-Control':'no-store'}});
  },candidateId);
  assert.equal(calls.length,2);
  assert(calls.every(call=>call.method==='GET'&&call.headers['Cloudflare-Workers-Version-Overrides']===`brebo-integration-api="${candidateId}"`));
});
test('reports only whitelisted provider failure metadata, never error text or credentials',async()=>{
  let calls=0;
  await assert.rejects(checkBankVersion(versionUrl,'secret'.repeat(6),async()=>{
    calls++;
    return calls===1?new Response('',{status:401}):Response.json({error:{category:'provider_http_error',provider_status:403,stage:'accounts',message:'PRIVATE-ERROR-MARKER'}},{status:502});
  }),error=>error.message.includes('provider_status=403')&&error.message.includes('stage=accounts')&&error.message.includes('category=provider_http_error')&&!error.message.includes('PRIVATE-ERROR-MARKER'));
});

test('permits promotion only for the exact accepted candidate at 100 percent', async()=>{
  const plan=zeroTrafficPlan(baseline,'Worker Version ID: '+candidateId);
  const promoted=[{...baseline[0],versions:[{version_id:candidateId,percentage:100}]}];
  assert.equal(activationTrafficState(promoted,plan),'promoted');
  assert.throws(()=>trafficState(promoted,plan));
  assert.throws(()=>activationTrafficState([{...baseline[0],versions:[{version_id:candidateId,percentage:90}]}],plan));
  let calls=0;
  const fetcher=async(url,init)=>{
    calls++;
    assert.equal(url,'https://brebo-integration-api.john-boon.workers.dev/v1/accounting/bank-data');
    assert.equal(init.headers['Cloudflare-Workers-Version-Overrides'],undefined);
    return calls===1?new Response('',{status:401}):Response.json(valid(),{headers:{'Cache-Control':'no-store'}});
  };
  await assert.rejects(checkPromotedBank(baseline,plan,'secret'.repeat(6),fetcher));
  assert.equal(calls,0);
  await checkPromotedBank(promoted,plan,'secret'.repeat(6),fetcher);
  assert.equal(calls,2);
});
