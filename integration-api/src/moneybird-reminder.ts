export interface SalesReminderCommand {
  idempotency_key: string;
  reminder: { id: string; contact_id: string; workflow_id: string; document_style_id: string; identity_id: string; email_address: string; reminder_text: string };
}

export function parseSalesReminder(value: unknown): SalesReminderCommand {
  if (!value || typeof value !== 'object' || Array.isArray(value)) throw new Error('Invalid reminder');
  const command = value as Record<string, unknown>;
  if (Object.keys(command).some(k => !['idempotency_key', 'reminder'].includes(k)) || typeof command.idempotency_key !== 'string' || !/^[a-zA-Z0-9:_-]{16,96}$/.test(command.idempotency_key)) throw new Error('Invalid command');
  if (!command.reminder || typeof command.reminder !== 'object' || Array.isArray(command.reminder)) throw new Error('Invalid reminder');
  const r = command.reminder as Record<string, unknown>;
  const fields = ['id', 'contact_id', 'workflow_id', 'document_style_id', 'identity_id'];
  if (Object.keys(r).some(k => ![...fields, 'email_address', 'reminder_text'].includes(k))) throw new Error('Invalid fields');
  for (const k of fields) if (typeof r[k] !== 'string' || !/^\d{1,30}$/.test(r[k] as string)) throw new Error('Invalid provider identifier');
  if (typeof r.email_address !== 'string' || r.email_address.length > 254 || !/^[^\s@,;<>]+@[^\s@,;<>]+\.[^\s@,;<>]+$/.test(r.email_address) || typeof r.reminder_text !== 'string' || !r.reminder_text.trim() || r.reminder_text.length > 10000) throw new Error('Invalid delivery');
  return value as SalesReminderCommand;
}

/** One POST only. Does not read, import, create or modify invoice documents. */
export async function sendSalesReminder(command: SalesReminderCommand, env: Pick<Env, 'MONEYBIRD_ACCESS_TOKEN' | 'MONEYBIRD_ADMINISTRATION_ID'>): Promise<void> {
  if (!/^\d+$/.test(env.MONEYBIRD_ADMINISTRATION_ID) || !env.MONEYBIRD_ACCESS_TOKEN) throw new Error('Unconfigured provider');
  const r = command.reminder;
  const response = await fetch(`https://moneybird.com/api/v2/${env.MONEYBIRD_ADMINISTRATION_ID}/sales_invoices/send_reminders.json`, {
    method: 'POST', redirect: 'manual', signal: AbortSignal.timeout(15000),
    headers: { Authorization: `Bearer ${env.MONEYBIRD_ACCESS_TOKEN}`, 'Content-Type': 'application/json', Accept: 'application/json' },
    body: JSON.stringify({ sales_invoice_reminders: [{ contact_id: r.contact_id, workflow_id: r.workflow_id, document_style_id: r.document_style_id, identity_id: r.identity_id, sales_invoice_ids: [r.id], email_address: r.email_address, reminder_text: r.reminder_text, delivery_method: 'Email' }] }),
  });
  if (response.status !== 200) throw new Error('Reminder outcome requires reconciliation');
}
