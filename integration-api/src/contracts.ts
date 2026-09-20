import { z } from "zod";

const boundedString = (max: number) => z.string().max(max);

export const analyzeRequestSchema = z
  .object({
    test_mode: z.boolean(),
    contains_real_data: z.boolean(),
    human_review_required: z.literal(true),
    communication: z
      .object({
        id: z.number().int().positive().nullable().optional(),
        project_id: z.number().int().positive().nullable().optional(),
        channel: z.string().min(1).max(50),
        subject: z.string().min(1).max(200),
        message: z.string().min(1).max(12_000),
      })
      .strict(),
  })
  .strict()
  .superRefine((value, ctx) => {
    if (value.test_mode && value.contains_real_data) {
      ctx.addIssue({
        code: "custom",
        message: "Test mode cannot contain real data.",
        path: ["contains_real_data"],
      });
    }
  });

export const analysisSchema = z
  .object({
    classification: z.string().min(1).max(100),
    summary: boundedString(4_000),
    decisions: z.array(boundedString(1_000)).max(50),
    risks: z.array(boundedString(1_000)).max(50),
    suggested_actions: z.array(boundedString(1_000)).max(50),
    confidence: z.number().min(0).max(100),
    human_review_required: z.literal(true),
  })
  .strict();

export type AnalyzeRequest = z.infer<typeof analyzeRequestSchema>;
export type Analysis = z.infer<typeof analysisSchema>;

export const analysisJsonSchema = {
  type: "object",
  additionalProperties: false,
  required: [
    "classification",
    "summary",
    "decisions",
    "risks",
    "suggested_actions",
    "confidence",
    "human_review_required",
  ],
  properties: {
    classification: { type: "string", minLength: 1, maxLength: 100 },
    summary: { type: "string", maxLength: 4_000 },
    decisions: {
      type: "array",
      maxItems: 50,
      items: { type: "string", maxLength: 1_000 },
    },
    risks: {
      type: "array",
      maxItems: 50,
      items: { type: "string", maxLength: 1_000 },
    },
    suggested_actions: {
      type: "array",
      maxItems: 50,
      items: { type: "string", maxLength: 1_000 },
    },
    confidence: { type: "number", minimum: 0, maximum: 100 },
    human_review_required: { type: "boolean", const: true },
  },
} as const;

const orderBudgetLineSchema = z.object({
  id: z.number().int().positive(),
  code: z.string().max(100),
  description: z.string().max(500),
  remaining_ex_vat: z.number().nonnegative(),
}).strict();

export const orderDraftRequestSchema = z.object({
  contains_real_data: z.literal(true),
  human_review_required: z.literal(true),
  project_id: z.number().int().positive(),
  source_text: z.string().min(1).max(16_000),
  supplier_hint: z.string().max(255).optional(),
  budget_lines: z.array(orderBudgetLineSchema).min(1).max(250),
}).strict();

export const orderDraftSchema = z.object({
  supplier_name: z.string().min(1).max(255),
  supplier_ref: z.string().max(255),
  lines: z.array(z.object({
    budget_line_id: z.number().int().positive(),
    description: z.string().min(1).max(500),
    quantity: z.number().positive(),
    unit: z.string().min(1).max(32),
    unit_price_ex_vat: z.number().positive(),
    vat_rate: z.union([z.literal(0), z.literal(9), z.literal(21)]),
    vat_reverse_charge: z.boolean(),
  }).strict()).min(1).max(100),
  rationale: z.string().max(4_000),
  risks: z.array(z.string().max(1_000)).max(50),
  confidence: z.number().min(0).max(100),
  human_review_required: z.literal(true),
}).strict();

export type OrderDraftRequest = z.infer<typeof orderDraftRequestSchema>;
export type OrderDraft = z.infer<typeof orderDraftSchema>;

export const orderDraftJsonSchema = {
  type: "object",
  additionalProperties: false,
  required: ["supplier_name", "supplier_ref", "lines", "rationale", "risks", "confidence", "human_review_required"],
  properties: {
    supplier_name: { type: "string", minLength: 1, maxLength: 255 },
    supplier_ref: { type: "string", maxLength: 255 },
    lines: {
      type: "array",
      minItems: 1,
      maxItems: 100,
      items: {
        type: "object",
        additionalProperties: false,
        required: ["budget_line_id", "description", "quantity", "unit", "unit_price_ex_vat", "vat_rate", "vat_reverse_charge"],
        properties: {
          budget_line_id: { type: "integer", minimum: 1 },
          description: { type: "string", minLength: 1, maxLength: 500 },
          quantity: { type: "number", exclusiveMinimum: 0 },
          unit: { type: "string", minLength: 1, maxLength: 32 },
          unit_price_ex_vat: { type: "number", exclusiveMinimum: 0 },
          vat_rate: { type: "integer", enum: [0, 9, 21] },
          vat_reverse_charge: { type: "boolean" },
        },
      },
    },
    rationale: { type: "string", maxLength: 4_000 },
    risks: { type: "array", maxItems: 50, items: { type: "string", maxLength: 1_000 } },
    confidence: { type: "number", minimum: 0, maximum: 100 },
    human_review_required: { type: "boolean", const: true },
  },
} as const;
