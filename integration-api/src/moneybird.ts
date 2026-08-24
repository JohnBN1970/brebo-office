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

export interface MoneybirdConnectionResult {
  administration_id: string;
  administration_name: string | null;
}

export interface MoneybirdPurchaseInvoiceResult {
  id: string;
  contact_id: string | null;
  supplier_name: string | null;
  reference: string | null;
  date: string | null;
  due_date: string | null;
  state: string;
  currency: string;
  total_price_excl_tax: string | null;
  total_price_incl_tax: string | null;
  version: string | number | null;
  origin: string | null;
}

export async function checkMoneybirdConnection(env: Env): Promise<MoneybirdConnectionResult> {
  const administrationId = env.MONEYBIRD_ADMINISTRATION_ID;
  const response = await moneybirdFetch("https://moneybird.com/api/v2/administrations.json", env, { method: "GET" });
  const value: unknown = await response.json();
  if (!Array.isArray(value)) throw new MoneybirdResponseError(502, "Invalid Moneybird response.");

  const administration = value.find((candidate) => {
    if (!candidate || typeof candidate !== "object") return false;
    return String((candidate as Record<string, unknown>).id ?? "") === administrationId;
  });
  if (!administration || typeof administration !== "object") {
    throw new MoneybirdResponseError(404, "Configured Moneybird administration is not accessible.");
  }

  const record = administration as Record<string, unknown>;
  return {
    administration_id: administrationId,
    administration_name: typeof record.name === "string" ? record.name : null,
  };
}

export async function listPurchaseInvoices(env: Env): Promise<MoneybirdPurchaseInvoiceResult[]> {
  const base = `https://moneybird.com/api/v2/${encodeURIComponent(env.MONEYBIRD_ADMINISTRATION_ID)}`;
  const result: MoneybirdPurchaseInvoiceResult[] = [];
  const perPage = 100;

  for (let page = 1; page <= 100; page++) {
    const response = await moneybirdFetch(`${base}/documents/purchase_invoices.json?per_page=${perPage}&page=${page}&filter=${encodeURIComponent("period:this_year,state:all")}`, env, { method: "GET" });
    const value: unknown = await response.json();
    if (!Array.isArray(value)) throw new MoneybirdResponseError(502, "Invalid Moneybird purchase invoice response.");
    for (const candidate of value) result.push(purchaseInvoiceResult(candidate));
    if (value.length < perPage) break;
  }

  return result;
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

function purchaseInvoiceResult(value: unknown): MoneybirdPurchaseInvoiceResult {
  if (!value || typeof value !== "object") throw new MoneybirdResponseError(502, "Invalid Moneybird purchase invoice response.");
  const invoice = value as Record<string, unknown>;
  if (invoice.id === undefined || invoice.id === null) throw new MoneybirdResponseError(502, "Invalid Moneybird purchase invoice response.");
  const contact = invoice.contact && typeof invoice.contact === "object" ? invoice.contact as Record<string, unknown> : null;
  const company = typeof contact?.company_name === "string" ? contact.company_name.trim() : "";
  const person = [contact?.firstname, contact?.lastname].filter((part) => typeof part === "string" && part.trim() !== "").join(" ").trim();
  return {
    id: String(invoice.id),
    contact_id: invoice.contact_id === undefined || invoice.contact_id === null ? null : String(invoice.contact_id),
    supplier_name: company || person || null,
    reference: typeof invoice.reference === "string" ? invoice.reference : null,
    date: typeof invoice.date === "string" ? invoice.date : null,
    due_date: typeof invoice.due_date === "string" ? invoice.due_date : null,
    state: typeof invoice.state === "string" ? invoice.state : "unknown",
    currency: typeof invoice.currency === "string" ? invoice.currency : "EUR",
    total_price_excl_tax: typeof invoice.total_price_excl_tax === "string" ? invoice.total_price_excl_tax : null,
    total_price_incl_tax: typeof invoice.total_price_incl_tax === "string" ? invoice.total_price_incl_tax : null,
    version: typeof invoice.version === "string" || typeof invoice.version === "number" ? invoice.version : null,
    origin: typeof invoice.origin === "string" ? invoice.origin : null,
  };
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
