import { analysisJsonSchema, analysisSchema, type Analysis, type AnalyzeRequest } from "./contracts";

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
            content: [
              {
                type: "input_text",
                text: "Analyseer uitsluitend de aangeleverde communicatie. Geef geen opdrachten, voer niets uit en markeer menselijke controle altijd als verplicht.",
              },
            ],
          },
          {
            role: "user",
            content: [
              {
                type: "input_text",
                text: JSON.stringify(input.communication),
              },
            ],
          },
        ],
        text: {
          format: {
            type: "json_schema",
            name: "brebo_communication_analysis",
            strict: true,
            schema: analysisJsonSchema,
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

    const parsed = analysisSchema.safeParse(candidate);
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
