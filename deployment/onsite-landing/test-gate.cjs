const assert=require('node:assert/strict');
const {readFileSync}=require('node:fs');
const {webcrypto,createHash}=require('node:crypto');
const vm=require('node:vm');
const token='fixture-personal-invitation-'.padEnd(43,'x');
const hash=createHash('sha256').update(token).digest('hex');
async function run(fragment,expected,unlock){
  const calls={fetch:0,reveal:0,reload:0,replace:[]};
  const nodes={'gate-message':{textContent:''},'activation-panel':{hidden:true},'activate-link':{href:''}};
  const context={URLSearchParams,TextEncoder,crypto:webcrypto,
    document:{querySelector:()=>expected?{content:expected}:null,documentElement:{classList:{remove:()=>calls.reveal++}},getElementById:id=>nodes[id]},
    location:{hash:fragment,reload:()=>calls.reload++,replace:url=>calls.replace.push(url)},
    fetch:async(url,options)=>{calls.fetch++;assert.equal(url,'./?action=unlock');assert.equal(options.credentials,'same-origin');assert.deepEqual(JSON.parse(options.body),{activation_token:token});return {ok:unlock,json:async()=>({ok:unlock})};}
  };
  await vm.runInNewContext(readFileSync(__dirname+'/gate.js','utf8'),context);
  return {calls,nodes};
}
(async()=>{
  let r=await run('', '', false);assert.equal(r.calls.fetch,0);assert.equal(r.calls.reveal,0);
  r=await run('#activation='+token,hash,false);assert.equal(r.calls.fetch,0);assert.equal(r.calls.reveal,1);assert.equal(r.nodes['activation-panel'].hidden,false);assert.equal(r.nodes['activate-link'].href,'brebo-onsite://activate?token='+token+'&start=1');
  r=await run('#activation='+token,'f'.repeat(64),true);assert.equal(r.calls.fetch,1);assert.equal(r.calls.reload,1);assert.equal(r.calls.reveal,0);
  r=await run('#activation='+token,'f'.repeat(64),false);assert.deepEqual(r.calls.replace,['./?action=locked#invalid']);assert.equal(r.calls.reveal,0);
  r=await run('#installeren',hash,false);assert.equal(r.calls.reveal,0);assert.equal(r.calls.fetch,0);
  console.log('PASS fragment validation, employee mismatch, locked fallback and explicit app activation');
})().catch(e=>{console.error(e);process.exitCode=1});
