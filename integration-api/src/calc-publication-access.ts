/** Office-native, fail-closed calculation access decisions. No Drupal dependency. */
export interface CalculationAccessRecord {
  calculation_id: number;
  active: boolean;
  readers: number[];
  publishers: number[];
}

/** This source must be populated from authoritative Office identities and calculation ownership. */
export interface CalculationAccessSource {
  find(calculationId: number): Promise<CalculationAccessRecord | null>;
}

export class OfficeCalculationAccess {
  constructor(private readonly source: CalculationAccessSource) {}

  async canRead(calculationId: number, actorId: number): Promise<boolean> {
    const record = await this.load(calculationId, actorId);
    return record !== null && (record.readers.includes(actorId) || record.publishers.includes(actorId));
  }

  async canPublish(calculationId: number, actorId: number): Promise<boolean> {
    const record = await this.load(calculationId, actorId);
    return record !== null && record.publishers.includes(actorId);
  }

  private async load(calculationId: number, actorId: number): Promise<CalculationAccessRecord | null> {
    if (!Number.isSafeInteger(calculationId) || calculationId <= 0 || !Number.isSafeInteger(actorId) || actorId <= 0) return null;
    const record = await this.source.find(calculationId);
    if (!record || record.calculation_id !== calculationId || record.active !== true || !validActors(record.readers) || !validActors(record.publishers)) return null;
    return record;
  }
}

function validActors(value: unknown): value is number[] {
  return Array.isArray(value) && value.every((id: unknown) => typeof id === "number" && Number.isSafeInteger(id) && id > 0);
}
