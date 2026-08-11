import { cloudflareTest } from "@cloudflare/vitest-pool-workers";
import { defineConfig } from "vitest/config";

export default defineConfig({
  plugins: [
    cloudflareTest({
        wrangler: { configPath: "./wrangler.jsonc" },
        miniflare: {
          bindings: {
            OPENAI_API_KEY: "test-openai-key",
            BREBO_SHARED_SECRET: "test-shared-secret-with-sufficient-length",
            OPENAI_TIMEOUT_MS: "25",
          },
          outboundService: async (request) => {
            const payload: unknown = await request.json();
            const userText = extractUserText(payload);
            if (userText.includes("Timeout")) {
              await new Promise((resolve) => setTimeout(resolve, 100));
            }
            if (!isPrivacySafeProviderRequest(payload)) {
              return Response.json({ error: "unsafe provider request" }, { status: 400 });
            }
            const output = userText.includes("Providerfout")
              ? {}
              : {
                  classification: "test",
                  summary: "Fictieve testanalyse.",
                  decisions: [],
                  risks: [],
                  suggested_actions: ["Menselijk controleren"],
                  confidence: 95,
                  human_review_required: true,
                };
            return Response.json({
              output: [{ content: [{ type: "output_text", text: JSON.stringify(output) }] }],
            });
          },
        },
    }),
  ],
});

function extractUserText(value: unknown): string {
  if (!value || typeof value !== "object" || !("input" in value) || !Array.isArray(value.input)) return "";
  const user = value.input[1];
  if (!user || typeof user !== "object" || !("content" in user) || !Array.isArray(user.content)) return "";
  const item = user.content[0];
  return item && typeof item === "object" && "text" in item && typeof item.text === "string"
    ? item.text
    : "";
}

function isPrivacySafeProviderRequest(value: unknown): boolean {
  return Boolean(value && typeof value === "object" && "store" in value && value.store === false);
}
