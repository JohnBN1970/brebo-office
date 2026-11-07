import {createHash,createHmac,randomUUID} from 'node:crypto';
const account=process.env.CLOUDFLARE_ACCOUNT_ID, token=process.env.CLOUDFLARE_API_TOKEN, secret=process.env.BREBO_SHARED_SECRET;
if(!account||!token||!secret) throw new Error('Preflight credentials missing');
const root=`https://api.cloudflare.com/client/v4/accounts/${encodeURIComponent(account)}/workers/scripts/brebo-integration-api`;
async function cf(path){const r=await fetch(root+path,{headers:{Authorization:`Bearer ${token}`},redirect:'manual'});if(!r.ok)throw new Error(`Cloudflare read failed (${r.status})`);const d=await r.json();if(!d.success)throw new Error('Cloudflare read rejected');return d.result;}
const deployment=await cf('/deployments');
const latest=deployment.deployments?.[0];
if(!latest||!Array.isArray(latest.versions))throw new Error('Active deployment not identified');
for(const allocation of latest.versions){const version=await cf('/versions/'+encodeURIComponent(allocation.version_id));console.log(JSON.stringify({event:'SALES_ACTIVE_WORKER_VERSION',version:allocation.version_id,percentage:allocation.percentage,created_on:version.metadata?.created_on,tag:version.annotations?.['workers/tag']??null,message:version.annotations?.['workers/message']??null}));}
async function check(path){const time=String(Math.floor(Date.now()/1000)),id=randomUUID(),canonical=['GET',path,createHash('sha256').update('').digest('hex'),time,id].join('\n');const r=await fetch('https://brebo-integration-api.john-boon.workers.dev'+path,{headers:{'X-BREBO-Request-Id':id,'X-BREBO-Timestamp':time,'X-BREBO-Signature':'v1='+createHmac('sha256',secret).update(canonical).digest('hex')},redirect:'manual'});let d=null;try{d=await r.json();}catch{}return {status:r.status,ok:r.status===200&&d?.status==='ok',administration_ok:d?.administration?.administration_id==='470422197045823237'};}
for(const path of ['/v1/accounting/connection','/v1/accounting/bank-data','/v1/accounting/sales-configuration'])console.log(JSON.stringify({event:'SALES_READ_ONLY_ROUTE_CHECK',path,...await check(path)}));
console.log('SALES_WORKER_PREFLIGHT_NO_DEPLOYMENT=1');
