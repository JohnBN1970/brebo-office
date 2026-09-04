export class AbnAmroConfigurationError extends Error {}
export class AbnAmroResponseError extends Error {
  constructor(public readonly status: number, message: string) { super(message); }
}

export type AbnPaymentSubmission = {
  idempotency_key: string;
  pain001_xml: string;
  payload_sha256: string;
};

export type AbnPaymentSubmissionResult = {
  provider: "abnamro";
  state: "submitted_for_authorization";
  bank_reference: string;
  raw_status?: string;
};

function required(env: Env, name: keyof Env): string {
  const value = String(env[name] ?? "").trim();
  if (!value) throw new AbnAmroConfigurationError(`Missing ABN AMRO configuration: ${String(name)}`);
  return value;
}

/**
 * Production adapter boundary for ABN AMRO Business Account Payment.
 *
 * Mutual-TLS credentials stay in the integration layer. The Drupal application
 * only submits an already sealed BREBO payment payload to this boundary.
 * Exact bank endpoint/payload mapping is deliberately configuration-driven so
 * production onboarding can pin the contract version without changing BREBO's
 * payment-control model.
 */
export async function submitBusinessAccountPayment(input: AbnPaymentSubmission, env: Env): Promise<AbnPaymentSubmissionResult> {
  const endpoint = required(env, "ABN_AMRO_PAYMENT_ENDPOINT");
  const accessToken = required(env, "ABN_AMRO_ACCESS_TOKEN");
  const apiKey = required(env, "ABN_AMRO_API_KEY");

  const response = await fetch(endpoint, {
    method: "POST",
    headers: {
      "Authorization": `Bearer ${accessToken}`,
      "API-Key": apiKey,
      "Content-Type": "application/xml",
      "Idempotency-Key": input.idempotency_key,
      "X-BREBO-Payload-SHA256": input.payload_sha256,
    },
    body: input.pain001_xml,
  });
  const body = await response.text();
  if (!response.ok) throw new AbnAmroResponseError(response.status, `ABN AMRO payment initiation failed (${response.status}).`);

  let parsed: Record<string, unknown> = {};
  try { parsed = body ? JSON.parse(body) as Record<string, unknown> : {}; } catch { /* Some bank responses may not be JSON. */ }
  const reference = String(parsed.paymentId ?? parsed.batchId ?? parsed.id ?? response.headers.get("Location") ?? "").trim();
  if (!reference) throw new AbnAmroResponseError(502, "ABN AMRO accepted the request without a usable payment reference.");

  return { provider: "abnamro", state: "submitted_for_authorization", bank_reference: reference, raw_status: String(parsed.status ?? "submitted") };
}
