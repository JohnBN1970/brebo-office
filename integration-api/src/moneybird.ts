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

export interface MoneybirdSalesInvoiceReceivableResult extends MoneybirdSalesInvoiceResult {
  contact_id: string | null;
  currency: string;
  total_price_excl_tax: string | null;
  total_price_incl_tax: string | null;
  paid_amount: string;
  version: string | number | null;
}

export interface MoneybirdConnectionResult {
  administration_id: string;
  administration_name: string | null;
}

export interface MoneybirdSupplierContactResult {
  id: string | null;
  company_name: string | null;
  firstname: string | null;
  lastname: string | null;
  address1: string | null;
  address2: string | null;
  zipcode: string | null;
  city: string | null;
  country: string | null;
  phone: string | null;
  email: string | null;
  customer_id: string | null;
  tax_number: string | null;
  chamber_of_commerce: string | null;
  delivery_method: string | null;
  direct_debit: boolean;
  sepa_active: boolean;
  sepa_iban: string | null;
  sepa_iban_account_name: string | null;
  sepa_bic: string | null;
  sepa_mandate_id: string | null;
  sepa_mandate_date: string | null;
  sepa_sequence_type: string | null;
}

export interface MoneybirdPurchaseInvoiceResult {
  id: string;
  contact_id: string | null;
  supplier_name: string | null;
  supplier_contact: MoneybirdSupplierContactResult | null;
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

export async function listSalesInvoices(env: Env): Promise<MoneybirdSalesInvoiceReceivableResult[]> {
  const base = `https://moneybird.com/api/v2/${encodeURIComponent(env.MONEYBIRD_ADMINISTRATION_ID)}`;
  const result: MoneybirdSalesInvoiceReceivableResult[] = [];
  const perPage = 100;

  for (let page = 1; page <= 100; page++) {
    const response = await moneybirdFetch(`${base}/sales_invoices.json?per_page=${perPage}&page=${page}&filter=${encodeURIComponent("period:this_year,state:all")}`, env, { method: "GET" });
    const value: unknown = await response.json();
    if (!Array.isArray(value)) throw new MoneybirdResponseError(502, "Invalid Moneybird sales invoice response.");
    for (const candidate of value) result.push(salesInvoiceReceivableResult(candidate));
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
  const company = stringValue(contact?.company_name)?.trim() ?? "";
  const firstname = stringValue(contact?.firstname)?.trim() ?? "";
  const lastname = stringValue(contact?.lastname)?.trim() ?? "";
  const person = [firstname, lastname].filter(Boolean).join(" ").trim();
  return {
    id: String(invoice.id),
    contact_id: invoice.contact_id === undefined || invoice.contact_id === null ? null : String(invoice.contact_id),
    supplier_name: company || person || null,
    supplier_contact: contact ? supplierContactResult(contact) : null,
    reference: stringValue(invoice.reference),
    date: stringValue(invoice.date),
    due_date: stringValue(invoice.due_date),
    state: stringValue(invoice.state) ?? "unknown",
    currency: stringValue(invoice.currency) ?? "EUR",
    total_price_excl_tax: stringValue(invoice.total_price_excl_tax),
    total_price_incl_tax: stringValue(invoice.total_price_incl_tax),
    version: typeof invoice.version === "string" || typeof invoice.version === "number" ? invoice.version : null,
    origin: stringValue(invoice.origin),
  };
}

function salesInvoiceReceivableResult(value: unknown): MoneybirdSalesInvoiceReceivableResult {
  if (!value || typeof value !== "object") throw new MoneybirdResponseError(502, "Invalid Moneybird sales invoice response.");
  const invoice = value as Record<string, unknown>;
  if (invoice.id === undefined || invoice.id === null) throw new MoneybirdResponseError(502, "Invalid Moneybird sales invoice response.");
  const payments = Array.isArray(invoice.payments) ? invoice.payments : [];
  let paid = 0;
  for (const candidate of payments) {
    if (!candidate || typeof candidate !== "object") continue;
    const payment = candidate as Record<string, unknown>;
    const amount = Number(payment.price ?? payment.amount ?? 0);
    if (Number.isFinite(amount) && amount > 0) paid += amount;
  }
  return {
    id: String(invoice.id),
    invoice_id: stringValue(invoice.invoice_id),
    state: stringValue(invoice.state) ?? "unknown",
    invoice_date: stringValue(invoice.invoice_date),
    due_date: stringValue(invoice.due_date),
    sent_at: stringValue(invoice.sent_at),
    contact_id: invoice.contact_id === undefined || invoice.contact_id === null ? null : String(invoice.contact_id),
    currency: stringValue(invoice.currency) ?? "EUR",
    total_price_excl_tax: stringValue(invoice.total_price_excl_tax),
    total_price_incl_tax: stringValue(invoice.total_price_incl_tax),
    paid_amount: paid.toFixed(4),
    version: typeof invoice.version === "string" || typeof invoice.version === "number" ? invoice.version : null,
  };
}

function supplierContactResult(contact: Record<string, unknown>): MoneybirdSupplierContactResult {
  return {
    id: contact.id === undefined || contact.id === null ? null : String(contact.id),
    company_name: stringValue(contact.company_name),
    firstname: stringValue(contact.firstname),
    lastname: stringValue(contact.lastname),
    address1: stringValue(contact.address1),
    address2: stringValue(contact.address2),
    zipcode: stringValue(contact.zipcode),
    city: stringValue(contact.city),
    country: stringValue(contact.country),
    phone: stringValue(contact.phone),
    email: stringValue(contact.email),
    customer_id: stringValue(contact.customer_id),
    tax_number: stringValue(contact.tax_number),
    chamber_of_commerce: stringValue(contact.chamber_of_commerce),
    delivery_method: stringValue(contact.delivery_method),
    direct_debit: contact.direct_debit === true,
    sepa_active: contact.sepa_active === true,
    sepa_iban: stringValue(contact.sepa_iban),
    sepa_iban_account_name: stringValue(contact.sepa_iban_account_name),
    sepa_bic: stringValue(contact.sepa_bic),
    sepa_mandate_id: stringValue(contact.sepa_mandate_id),
    sepa_mandate_date: stringValue(contact.sepa_mandate_date),
    sepa_sequence_type: stringValue(contact.sepa_sequence_type),
  };
}

function stringValue(value: unknown): string | null {
  return typeof value === "string" && value.trim() !== "" ? value.trim() : null;
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
