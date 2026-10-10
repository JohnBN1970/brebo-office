import type { CalculationAccessRecord } from "./calc-publication-access";

/** Office must resolve these facts from its own trusted identity and calculation registry. */
export interface OfficeCalculationAuthority {
  resolve(calculationId: number): Promise<{
    calculation_id: number;
    active: boolean;
    readers: number[];
    publishers: number[];
  } | null>;
}

/** Private DO method contract; deliberately not exposed as an HTTP endpoint. */
export interface CalculationAccessWriter {
  replace(record: CalculationAccessRecord, expectedRevision: number | null): Promise<number>;
}

/** Synchronize only records obtained from the trusted Office authority, never caller-supplied ACLs. */
export async function syncCalculationAccess(
  calculationId: number,
  expectedRevision: number | null,
  authority: OfficeCalculationAuthority,
  writer: CalculationAccessWriter,
): Promise<number> {
  if (!Number.isSafeInteger(calculationId) || calculationId <= 0) throw new TypeError("Invalid calculation ID");
  const record = await authority.resolve(calculationId);
  if (!record || record.calculation_id !== calculationId) throw new Error("Office calculation authority unavailable");
  const validIds = (value: unknown): value is number[] => Array.isArray(value) && value.every((id: unknown) => typeof id === "number" && Number.isSafeInteger(id) && id > 0) && new Set(value).size === value.length;
  if (typeof record.active !== "boolean" || !validIds(record.readers) || !validIds(record.publishers)) throw new Error("Invalid Office calculation authority record");
  return writer.replace({ calculation_id: calculationId, active: record.active, readers: record.readers, publishers: record.publishers }, expectedRevision);
}
