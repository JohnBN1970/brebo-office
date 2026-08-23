export class MoneybirdResponseError extends Error {
  constructor(
    readonly status: number,
    message = "Moneybird request failed.",
  ) {
    super(message);
    this.name = "MoneybirdResponseError";
  }
}

export interface SalesInvoiceDispatch {
  idempotency_key: string;
  sales_invoice: Record<string, unknown>;
  sending?: Record<string, unknown>;
}

export interface MoneybirdSalesInvoiceResult {
  id: string;
  invoice_id: string | null;
  state: string;
  invoice_date: string | null;
  due_date: string | null;
  sent_at: string | null;
}

export async function createAndSendSalesInvoice(
  command: SalesInvoiceDispatch,
  env: Env,
): Promise<MoneybirdSalesInvoiceResult> {
  const base = `https://moneybird.com/api/v2/${encodeURIComponent(env.MONEYBIRD_ADMINISTRATION_ID)}`;
  const created = await moneybirdFetch(`${base}/sales_invoices.json`, env, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ sales_invoice: command.sales_invoice }),
  });

  const createdInvoice = invoiceResult(await created.json());
  const sent = await moneybirdFetch(`${base}/sales_invoices/${encodeURIComponent(createdInvoice.id)}/send_invoice.json`, env, {
    method: "PATCH",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ sales_invoice_sending: command.sending ?? {} }),
  });
  return invoiceResult(await sent.json());
}

async function moneybirdFetch(url: string, env: Env, init: RequestInit): Promise<Response> {
  const response = await fetch(url, {
    ...init,
    headers: {
      ...init.headers,
      Authorization: `Bearer ${env.MONEYBIRD_ACCESS_TOKEN}`,
      Accept: "application/json",
    },
  });
  if (!response.ok) {
    throw new MoneybirdResponseError(response.status);
  }
  return response;
}

function invoiceResult(value: unknown): MoneybirdSalesInvoiceResult {
  if (!value || typeof value !== "object") throw new MoneybirdResponseError(502, "Invalid Moneybird response.");
  const invoice = value as Record<string, unknown>;
  if (typeof invoice.id !== "string") throw new MoneybirdResponseError(502, "Invalid Moneybird response.");
  return {
    id: invoice.id,
    invoice_id: typeof invoice.invoice_id === "string" ? invoice.invoice_id : null,
    state: typeof invoice.state === "string" ? invoice.state : "unknown",
    invoice_date: typeof invoice.invoice_date === "string" ? invoice.invoice_date : null,
    due_date: typeof invoice.due_date === "string" ? invoice.due_date : null,
    sent_at: typeof invoice.sent_at === "string" ? invoice.sent_at : null,
  };
}
