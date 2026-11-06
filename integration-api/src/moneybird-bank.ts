import { MoneybirdResponseError } from "./moneybird";

export class MoneybirdBankResponseError extends MoneybirdResponseError {
  constructor(status: number, readonly stage: 'accounts' | 'transactions') { super(status); }
}

export interface MoneybirdBankData {
  balances_available: false;
  period: "this_month";
  accounts: Array<{ id: string; name: string; identifier: string; currency: string }>;
  transactions: Array<{ id: string; financial_account_id: string; amount: string; date: string; message: string }>;
}

/** Bank evidence only. No invoices, payment matching, writes or inferred balances. */
export async function getMoneybirdBankData(env: Env): Promise<MoneybirdBankData> {
  const base = `https://moneybird.com/api/v2/${encodeURIComponent(env.MONEYBIRD_ADMINISTRATION_ID)}`;
  const accountsRaw = await bankJson(`${base}/financial_accounts.json`, env, 'accounts');
  if (!Array.isArray(accountsRaw)) throw invalidResponse();
  const accounts: MoneybirdBankData["accounts"] = [];
  const accountIds = new Set<string>();
  for (const candidate of accountsRaw) {
    const row = record(candidate);
    if (row.type !== "BankAccount") continue;
    if (typeof row.active !== "boolean") throw invalidResponse();
    if (row.active !== true) continue;
    const id = identifier(row.id);
    if (typeof row.currency !== "string" || !/^[A-Z]{3}$/.test(row.currency) || accountIds.has(id)) throw invalidResponse();
    accountIds.add(id);
    accounts.push({ id, name: text(row.name), identifier: text(row.identifier), currency: row.currency });
  }

  // Moneybird rejects lists over 100; never paginate or silently truncate this endpoint.
  const filter = encodeURIComponent("period:this_month,state:all");
  const mutationsRaw = await bankJson(`${base}/financial_mutations.json?filter=${filter}`, env, 'transactions');
  if (!Array.isArray(mutationsRaw) || mutationsRaw.length > 100) throw invalidResponse();
  const transactions: MoneybirdBankData["transactions"] = [];
  const mutationIds = new Set<string>();
  for (const candidate of mutationsRaw) {
    const row = record(candidate);
    const accountId = identifier(row.financial_account_id);
    if (!accountIds.has(accountId)) continue;
    const id = identifier(row.id);
    if (mutationIds.has(id) || typeof row.amount !== "string" || !/^-?\d+(?:\.\d{1,4})?$/.test(row.amount)
        || typeof row.date !== "string" || !validDate(row.date)) throw invalidResponse();
    mutationIds.add(id);
    transactions.push({ id, financial_account_id: accountId, amount: row.amount, date: row.date, message: text(row.message) });
  }
  return { balances_available: false, period: "this_month", accounts, transactions };
}

async function bankJson(url: string, env: Env, stage: 'accounts' | 'transactions'): Promise<unknown> {
  const response = await fetch(url, {
    // Workers supports manual redirects; reject all non-2xx below without forwarding credentials.
    method: "GET", redirect: "manual", signal: AbortSignal.timeout(4000),
    headers: { Authorization: `Bearer ${env.MONEYBIRD_ACCESS_TOKEN}`, Accept: "application/json" },
  });
  if (!response.ok) throw new MoneybirdBankResponseError(response.status, stage);
  try { return await response.json(); } catch { throw invalidResponse(); }
}
function record(value: unknown): Record<string, unknown> {
  if (!value || typeof value !== "object" || Array.isArray(value)) throw invalidResponse();
  return value as Record<string, unknown>;
}
function identifier(value: unknown): string {
  // Moneybird IDs exceed JS integer precision: require their documented string form.
  if (typeof value !== "string" || !/^\d+$/.test(value)) throw invalidResponse();
  return value;
}
function text(value: unknown): string {
  if (value === null || value === undefined) return "";
  if (typeof value !== "string") throw invalidResponse();
  return value;
}
function validDate(value: string): boolean {
  if (!/^\d{4}-\d{2}-\d{2}$/.test(value)) return false;
  const date = new Date(`${value}T00:00:00Z`);
  return !Number.isNaN(date.getTime()) && date.toISOString().slice(0, 10) === value;
}
function invalidResponse(): MoneybirdResponseError {
  return new MoneybirdResponseError(502, "Invalid Moneybird bank response.");
}
