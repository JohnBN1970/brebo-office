import { afterEach, describe, expect, it, vi } from 'vitest';
import { parseSalesReminder, sendSalesReminder } from '../src/moneybird-reminder';
const command = { idempotency_key: 'office-sales-reminder-0123456789', reminder: { id: '123', contact_id: '4', workflow_id: '5', document_style_id: '6', identity_id: '7', email_address: 'test@example.com', reminder_text: 'Herinnering' } };
describe('Office-issued invoice reminders', () => {
  afterEach(() => vi.unstubAllGlobals());
  it('posts exactly one reminder and never reads invoice documents', async () => {
    const provider = vi.fn().mockResolvedValue(new Response('200', { status: 200 })); vi.stubGlobal('fetch', provider);
    await sendSalesReminder(parseSalesReminder(command), { MONEYBIRD_ADMINISTRATION_ID: '999', MONEYBIRD_ACCESS_TOKEN: 'secret' });
    expect(provider).toHaveBeenCalledTimes(1);
    const [url, init] = provider.mock.calls[0]!;
    expect(url).toBe('https://moneybird.com/api/v2/999/sales_invoices/send_reminders.json');
    expect(init.method).toBe('POST'); expect(init.redirect).toBe('manual');
    expect(JSON.parse(init.body).sales_invoice_reminders[0]).toMatchObject({ sales_invoice_ids: ['123'], email_address: 'test@example.com', reminder_text: 'Herinnering' });
  });
  it('rejects injected fields, invalid IDs and extra recipients', () => {
    expect(() => parseSalesReminder({ ...command, url: 'https://evil.example' })).toThrow();
    expect(() => parseSalesReminder({ ...command, reminder: { ...command.reminder, id: '../other' } })).toThrow();
    expect(() => parseSalesReminder({ ...command, reminder: { ...command.reminder, email_address: 'a@example.com,b@example.com' } })).toThrow();
  });
  it('does not follow redirects or retry uncertain provider responses', async () => {
    const provider = vi.fn().mockResolvedValue(new Response(null, { status: 302, headers: { Location: 'https://evil.example' } })); vi.stubGlobal('fetch', provider);
    await expect(sendSalesReminder(command, { MONEYBIRD_ADMINISTRATION_ID: '999', MONEYBIRD_ACCESS_TOKEN: 'secret' })).rejects.toThrow(/reconciliation/);
    expect(provider).toHaveBeenCalledTimes(1);
  });
});
