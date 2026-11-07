import { resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

// Read-only API diagnostics. Never return token/account IDs, settings or provider messages.
export async function diagnoseCloudflareAccess({ token, accountId }, fetcher = fetch) {
  if (typeof token !== 'string' || token.length === 0 || typeof accountId !== 'string' || !/^[a-f0-9]{32}$/i.test(accountId)) {
    return { diagnosis:'configuration_incomplete', token_configured:typeof token==='string'&&token.length>0,
      account_id_configured:typeof accountId==='string'&&/^[a-f0-9]{32}$/i.test(accountId), requests:[] };
  }
  const worker = `/accounts/${accountId}/workers/scripts/brebo-integration-api`;
  const endpoints = [
    ['user_token_verify','/user/tokens/verify'],
    ['account_token_verify',`/accounts/${accountId}/tokens/verify`],
    ['account_read',`/accounts/${accountId}`],
    ['worker_settings',worker+'/script-settings'],
    ['worker_versions',worker+'/versions'],
    ['worker_deployments',worker+'/deployments'],
  ];
  const requests = await Promise.all(endpoints.map(async ([check,path]) => {
    const facts = { check, http_status:null, api_success:false, json_response:false, error_codes:[], network_error:false };
    try {
      const response = await fetcher('https://api.cloudflare.com/client/v4'+path, {
        method:'GET', redirect:'error', signal:AbortSignal.timeout(20000),
        headers:{ Authorization:'Bearer '+token, Accept:'application/json' },
      });
      facts.http_status = response.status;
      let payload;
      try { payload = await response.json(); facts.json_response = true; } catch { /* Never echo response text. */ }
      facts.api_success = payload?.success === true && response.status===200;
      if (Array.isArray(payload?.errors)) facts.error_codes = payload.errors
        .map(row=>row?.code).filter(code=>Number.isInteger(code)&&code>=1000&&code<10000000).slice(0,10);
      if (check.endsWith('_token_verify') && facts.api_success && ['active','disabled','expired'].includes(payload?.result?.status)) {
        facts.token_status = payload.result.status;
      }
    } catch { facts.network_error = true; }
    return facts;
  }));
  const tokenChecks = requests.filter(row=>row.check.endsWith('_token_verify'));
  const tokenActive = tokenChecks.some(row=>row.token_status==='active') ? true
    : tokenChecks.some(row=>['disabled','expired'].includes(row.token_status)) ? false : null;
  const workerChecks = requests.filter(row=>row.check.startsWith('worker_'));
  let diagnosis = 'inconclusive';
  if (tokenActive === false) diagnosis = 'token_inactive';
  else if (workerChecks.some(row=>row.network_error||row.http_status>=500)) diagnosis = 'worker_api_unavailable';
  else if (workerChecks.some(row=>[401,403].includes(row.http_status))) diagnosis = 'worker_access_denied';
  else if (workerChecks.every(row=>row.api_success)) diagnosis = 'access_ready';
  return { diagnosis, token_configured:true, account_id_configured:true, token_active:tokenActive,
    worker_settings_readable:requests.find(row=>row.check==='worker_settings').api_success,
    worker_versions_readable:requests.find(row=>row.check==='worker_versions').api_success,
    worker_deployments_readable:requests.find(row=>row.check==='worker_deployments').api_success,
    requests };
}

if (process.argv[1] && resolve(process.argv[1])===fileURLToPath(import.meta.url)) {
  const result = await diagnoseCloudflareAccess({token:process.env.CLOUDFLARE_API_TOKEN,accountId:process.env.CLOUDFLARE_ACCOUNT_ID});
  console.log(JSON.stringify(result,null,2));
  console.log('CLOUDFLARE_ACCESS_DIAGNOSIS='+result.diagnosis);
  // Completion means the diagnostic ran, not that authentication or API availability passed.
}
