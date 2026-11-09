import { test } from 'node:test';
import assert from 'node:assert/strict';
import { diagnoseCloudflareAccess } from './cloudflare-access-check.mjs';

const credentials={token:'PRIVATE-TOKEN-MARKER',accountId:'a'.repeat(32)};
function provider(overrides={}) {
  const calls=[];
  return {calls,fetcher:async(url,init)=>{
    calls.push({url,init});
    for(const [suffix,result] of Object.entries(overrides)) if(url.endsWith(suffix)) return result();
    return Response.json({success:true,result:url.endsWith('/tokens/verify')?{status:'active',id:'PRIVATE-ID-MARKER'}:{name:'PRIVATE-ACCOUNT-MARKER',bindings:[{value:credentials.token}]}});
  }};
}
test('uses only GET on the fixed Cloudflare API and strips all private provider content',async()=>{
  const p=provider(); const r=await diagnoseCloudflareAccess(credentials,p.fetcher);
  assert.equal(r.diagnosis,'access_ready'); assert.equal(r.token_active,true); assert.equal(p.calls.length,6);
  assert(p.calls.every(c=>c.url.startsWith('https://api.cloudflare.com/client/v4/')&&c.init.method==='GET'&&c.init.redirect==='error'));
  assert(!JSON.stringify(r).includes('PRIVATE-')); assert(!JSON.stringify(r).includes(credentials.accountId));
});
test('distinguishes an active token from an unavailable deployments API',async()=>{
  const p=provider({'/deployments':()=>new Response('no healthy upstream PRIVATE-MARKER',{status:503})});
  const r=await diagnoseCloudflareAccess(credentials,p.fetcher);
  assert.equal(r.token_active,true);assert.equal(r.diagnosis,'worker_api_unavailable');
  assert.equal(r.worker_versions_readable,true);assert.equal(r.worker_deployments_readable,false);
});
test('supports account tokens without mistaking user verification denial for an invalid token',async()=>{
  const p=provider({'/user/tokens/verify':()=>Response.json({success:false,errors:[{code:10000,message:'PRIVATE-MARKER'}]},{status:403})});
  const r=await diagnoseCloudflareAccess(credentials,p.fetcher);
  assert.equal(r.token_active,true);assert.equal(r.diagnosis,'access_ready');
});
test('does not require general account-read permission when Worker access is valid',async()=>{
  const p=provider({['/accounts/'+credentials.accountId]:()=>Response.json({success:false},{status:403})});
  assert.equal((await diagnoseCloudflareAccess(credentials,p.fetcher)).diagnosis,'access_ready');
});
test('identifies an explicitly expired token',async()=>{
  const p=provider({'/tokens/verify':()=>Response.json({success:true,result:{status:'expired'}})});
  assert.equal((await diagnoseCloudflareAccess(credentials,p.fetcher)).diagnosis,'token_inactive');
});
test('identifies Worker permission denial separately from token status',async()=>{
  const p=provider({'/versions':()=>Response.json({success:false,errors:[{code:10000,message:'PRIVATE-MARKER'}]},{status:403})});
  const r=await diagnoseCloudflareAccess(credentials,p.fetcher);
  assert.equal(r.token_active,true);assert.equal(r.diagnosis,'worker_access_denied');
  assert(!JSON.stringify(r).includes('PRIVATE-MARKER'));
});
test('makes no request with missing credentials or an invalid account ID',async()=>{
  let calls=0; const fetcher=async()=>{calls++;throw Error('must not run');};
  assert.equal((await diagnoseCloudflareAccess({token:'',accountId:credentials.accountId},fetcher)).diagnosis,'configuration_incomplete');
  assert.equal((await diagnoseCloudflareAccess({...credentials,accountId:'../other'},fetcher)).diagnosis,'configuration_incomplete');
  assert.equal(calls,0);
});
test('suppresses network error details and untrusted response metadata',async()=>{
  const r=await diagnoseCloudflareAccess(credentials,async()=>{throw Error('PRIVATE-TOKEN-MARKER');});
  assert.equal(r.diagnosis,'worker_api_unavailable');assert(!JSON.stringify(r).includes('PRIVATE-'));
});
