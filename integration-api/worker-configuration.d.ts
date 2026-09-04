/* eslint-disable */
// Generated shape for Wrangler bindings. Regenerate with `wrangler types --include-runtime=false` before deployment.
interface __BaseEnv_Env {
	API_VERSION: "1.1.0-draft";
	WORKER_VERSION: "0.2.0";
	OPENAI_MODEL: "gpt-5-mini";
	MAX_INPUT_CHARS: "12000";
	MAX_BODY_BYTES: "32768";
	MAX_CLOCK_SKEW_SECONDS: "300";
	REPLAY_TTL_SECONDS: "600";
	OUTBOUND_IDEMPOTENCY_TTL_SECONDS: "2592000";
	OPENAI_TIMEOUT_MS: "30000";
	OPENAI_MAX_OUTPUT_TOKENS: "2000";
	RATE_WINDOW_SECONDS: "60";
	MAX_ANALYSES_PER_WINDOW: "30";
	MONTHLY_TOKEN_BUDGET: "2000000";
	MONEYBIRD_ADMINISTRATION_ID: string;
	MONEYBIRD_ACCESS_TOKEN: string;
	ABN_AMRO_PAYMENT_ENDPOINT: string;
	ABN_AMRO_INSIGHT_BASE_URL: string;
	ABN_AMRO_ACCESS_TOKEN: string;
	ABN_AMRO_API_KEY: string;
	OPENAI_API_KEY: string;
	BREBO_SHARED_SECRET: string;
	REPLAY_GUARD: DurableObjectNamespace<import("./src/index").ReplayGuard>;
	USAGE_GUARD: DurableObjectNamespace<import("./src/index").UsageGuard>;
	SALES_INVOICE_DISPATCH_GUARD: DurableObjectNamespace<import("./src/index").SalesInvoiceDispatchGuard>;
}
declare namespace Cloudflare {
	interface GlobalProps { mainModule: typeof import("./src/index"); durableNamespaces: "ReplayGuard" | "UsageGuard" | "SalesInvoiceDispatchGuard"; }
	interface Env extends __BaseEnv_Env {}
}
interface Env extends __BaseEnv_Env {}
type StringifyValues<EnvType extends Record<string, unknown>> = { [Binding in keyof EnvType]: EnvType[Binding] extends string ? EnvType[Binding] : string; };
declare namespace NodeJS {
	interface ProcessEnv extends StringifyValues<Pick<Cloudflare.Env, "API_VERSION" | "WORKER_VERSION" | "OPENAI_MODEL" | "MAX_INPUT_CHARS" | "MAX_BODY_BYTES" | "MAX_CLOCK_SKEW_SECONDS" | "REPLAY_TTL_SECONDS" | "OUTBOUND_IDEMPOTENCY_TTL_SECONDS" | "OPENAI_TIMEOUT_MS" | "OPENAI_MAX_OUTPUT_TOKENS" | "RATE_WINDOW_SECONDS" | "MAX_ANALYSES_PER_WINDOW" | "MONTHLY_TOKEN_BUDGET" | "MONEYBIRD_ADMINISTRATION_ID" | "MONEYBIRD_ACCESS_TOKEN" | "ABN_AMRO_PAYMENT_ENDPOINT" | "ABN_AMRO_INSIGHT_BASE_URL" | "ABN_AMRO_ACCESS_TOKEN" | "ABN_AMRO_API_KEY" | "OPENAI_API_KEY" | "BREBO_SHARED_SECRET">> {}
}
