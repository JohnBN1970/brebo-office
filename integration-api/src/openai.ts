import {
  analysisJsonSchema,
  analysisSchema,
  orderDraftJsonSchema,
  orderDraftSchema,
  type Analysis,
  type AnalyzeRequest,
  type OrderDraft,
  type OrderDraftRequest,
} from "./contracts";

type ProviderResponse = {
  output?: Array<{
    content?: Array<{
      type?: string;
      text?: string;
    }>;
  }>;
};

export class ProviderTimeoutError extends Error {}
export class ProviderResponseError extends Error {}

export async function analyzeWithOpenAI(input: AnalyzeRequest, env: Env): Promise<Analysis> {
  return structuredResponse(
    env,
    "brebo_communication_analysis",
    analysisJsonSchema,
    analysisSchema,
    "Analyseer uitsluitend de aangeleverde communicatie. Geef geen opdrachten, voer niets uit en markeer menselijke controle altijd als verplicht.",
    input.communication,
  );
}

export async function draftOrderWithOpenAI(input: OrderDraftRequest, env: Env): Promise<OrderDraft> {
  const budgetIds = new Set(input.budget_lines.map((line) => line.id));
  const draft = await structuredResponse(
    env,
    "brebo_order_draft",
    orderDraftJsonSchema,
    orderDraftSchema,
    [
      "Maak uitsluitend een conceptorder op basis van de aangeleverde bron en werkbegrotingsregels.",
      "Verzin geen leverancier, aantallen, prijzen of scope die niet redelijk uit de bron volgt.",
      "Kies uitsluitend budget_line_id waarden die exact in budget_lines voorkomen.",
      "Overschrijd per voorgestelde regel nooit het resterende budget van de gekozen budgetregel.",
      "Geef onzekerheden expliciet terug in risks en zet human_review_required altijd op true.",
      "Dit voorstel mag nooit zelfstandig worden verzonden of definitief gemaakt.",
    ].join(" "),
    input,
  );

  for (const line of draft.lines) {
    if (!budgetIds.has(line.budget_line_id)) {
      throw new ProviderResponseError("Provider selected an unknown budget line.");
    }
    const budgetLine = input.budget_lines.find((candidate) => candidate.id === line.budget_line_id);
    const lineAmount = line.quantity * line.unit_price_ex_vat;
    if (!budgetLine || lineAmount > budgetLine.remaining_ex_vat + 0.005) {
      throw new ProviderResponseError("Provider proposal exceeds the available working budget.");
    }
  }

  return draft;
}

async function structuredResponse<T>(
  env: Env,
  schemaName: string,
  jsonSchema: object,
  validator: { safeParse(candidate: unknown): { success: true; data: T } | { success: false } },
  systemText: string,
  userPayload: unknown,
): Promise<T> {
  const controller = new AbortController();
  const timeout = setTimeout(() => controller.abort(), numberSetting(env.OPENAI_TIMEOUT_MS, 30_000));

  try {
    const response = await fetch("https://api.openai.com/v1/responses", {
      method: "POST",
      headers: {
        Authorization: `Bearer ${env.OPENAI_API_KEY}`,
        "Content-Type": "application/json",
      },
      body: JSON.stringify({
        model: env.OPENAI_MODEL,
        store: false,
        max_output_tokens: numberSetting(env.OPENAI_MAX_OUTPUT_TOKENS, 2_000),
        input: [
          {
            role: "system",
            content: [{ type: "input_text", text: systemText }],
          },
          {
            role: "user",
            content: [{ type: "input_text", text: JSON.stringify(userPayload) }],
          },
        ],
        text: {
          format: {
            type: "json_schema",
            name: schemaName,
            strict: true,
            schema: jsonSchema,
          },
        },
      }),
      signal: controller.signal,
    });

    if (!response.ok) {
      throw new ProviderResponseError(`Provider returned HTTP ${response.status}.`);
    }

    const provider = await response.json<ProviderResponse>();
    const text = provider.output
      ?.flatMap((item) => item.content ?? [])
      .find((item) => item.type === "output_text")?.text;

    if (!text) {
      throw new ProviderResponseError("Provider response contained no structured output.");
    }

    let candidate: unknown;
    try {
      candidate = JSON.parse(text);
    } catch {
      throw new ProviderResponseError("Provider output was not valid JSON.");
    }

    const parsed = validator.safeParse(candidate);
    if (!parsed.success) {
      throw new ProviderResponseError("Provider output did not match the contract.");
    }
    return parsed.data;
  } catch (error) {
    if (error instanceof DOMException && error.name === "AbortError") {
      throw new ProviderTimeoutError("Provider request timed out.");
    }
    throw error;
  } finally {
    clearTimeout(timeout);
  }
}

function numberSetting(value: string, fallback: number): number {
  const parsed = Number.parseInt(value, 10);
  return Number.isFinite(parsed) && parsed > 0 ? parsed : fallback;
}
