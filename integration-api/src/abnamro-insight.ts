export class AbnAmroInsightConfigurationError extends Error {}
export class AbnAmroInsightResponseError extends Error {
  constructor(public readonly status: number, message: string) { super(message); }
}

export type AbnAccountActivity = {
  transaction_id: string;
  account_number: string;
  amount: number;
  currency: string;
  status: string;
  book_date?: string;
  transaction_timestamp?: string;
  counterparty_account_number?: string;
  counterparty_name?: string;
  description_lines: string[];
};

function required(env: Env, name: keyof Env): string {
  const value = String(env[name] ?? "").trim();
  if (!value) throw new AbnAmroInsightConfigurationError(`Missing ABN AMRO configuration: ${String(name)}`);
  return value;
}

function accountNumber(value: string): string {
  const normalized = value.replace(/[^A-Za-z0-9]/g, "").toUpperCase();
  if (!/^[A-Z]{2}[0-9]{2}[A-Z0-9]{11,30}$/.test(normalized)) {
    throw new AbnAmroInsightConfigurationError("ABN AMRO account number is invalid.");
  }
  return normalized;
}

async function bankGet(path: string, env: Env): Promise<Record<string, unknown>> {
  const baseUrl = required(env, "ABN_AMRO_INSIGHT_BASE_URL").replace(/\/$/, "");
  const accessToken = required(env, "ABN_AMRO_ACCESS_TOKEN");
  const apiKey = required(env, "ABN_AMRO_API_KEY");
  const response = await fetch(`${baseUrl}${path}`, {
    method: "GET",
    headers: {
      "Authorization": `Bearer ${accessToken}`,
      "API-Key": apiKey,
      "Accept": "application/json",
    },
  });
  if (!response.ok) {
    throw new AbnAmroInsightResponseError(response.status, `ABN AMRO account insight failed (${response.status}).`);
  }
  const raw = await response.json();
  if (!raw || typeof raw !== "object" || Array.isArray(raw)) {
    throw new AbnAmroInsightResponseError(502, "ABN AMRO returned an invalid account insight response.");
  }
  return raw as Record<string, unknown>;
}

export async function getAccountBalance(iban: string, env: Env): Promise<Record<string, unknown>> {
  return bankGet(`/v1/accounts/${encodeURIComponent(accountNumber(iban))}/balances`, env);
}

/**
 * Reads booked account activity. ABN AMRO deprecated the legacy /transactions
 * endpoint at the end of 2025, so BREBO deliberately uses /activities.
 */
export async function getBookedActivities(iban: string, env: Env, dateFrom?: string, nextPageKey?: string): Promise<{activities: AbnAccountActivity[]; next_page_key?: string}> {
  const query = new URLSearchParams({ activityType: "BOOKED" });
  if (dateFrom) query.set("dateFrom", dateFrom);
  if (nextPageKey) query.set("nextPageKey", nextPageKey);
  const account = accountNumber(iban);
  const raw = await bankGet(`/v1/accounts/${encodeURIComponent(account)}/activities?${query.toString()}`, env);
  const container = raw.activities && typeof raw.activities === "object" && !Array.isArray(raw.activities)
    ? raw.activities as Record<string, unknown>
    : raw;
  const rows = Array.isArray(container.transactions) ? container.transactions : [];
  const activities = rows.flatMap((entry): AbnAccountActivity[] => {
    if (!entry || typeof entry !== "object" || Array.isArray(entry)) return [];
    const row = entry as Record<string, unknown>;
    const transactionId = String(row.transactionId ?? row.id ?? "").trim();
    if (!transactionId) return [];
    const bookDate = row.bookDate ? String(row.bookDate) : "";
    const transactionTimestamp = row.transactionTimestamp ? String(row.transactionTimestamp) : "";
    const counterpartyAccountNumber = row.counterPartyAccountNumber ? String(row.counterPartyAccountNumber) : "";
    const counterpartyName = row.counterPartyName ? String(row.counterPartyName) : "";
    return [{
      transaction_id: transactionId,
      account_number: account,
      amount: Number(row.amount ?? 0),
      currency: String(row.currency ?? "EUR"),
      status: String(row.status ?? "UNKNOWN"),
      ...(bookDate ? { book_date: bookDate } : {}),
      ...(transactionTimestamp ? { transaction_timestamp: transactionTimestamp } : {}),
      ...(counterpartyAccountNumber ? { counterparty_account_number: counterpartyAccountNumber } : {}),
      ...(counterpartyName ? { counterparty_name: counterpartyName } : {}),
      description_lines: Array.isArray(row.descriptionLines) ? row.descriptionLines.map(String) : [],
    }];
  });
  const next = String(raw.nextPageKey ?? container.nextPageKey ?? "").trim();
  return { activities, ...(next ? { next_page_key: next } : {}) };
}
