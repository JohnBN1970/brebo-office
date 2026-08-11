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
