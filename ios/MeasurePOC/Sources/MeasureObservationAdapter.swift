import Foundation

enum MeasureObservationAdapter {
    static func payloads(from evidence: [ScanEvidence]) -> [MeasureObservationPayload] {
        evidence.map { item in
            MeasureObservationPayload(
                key: item.key,
                provenance: item.provenance.rawValue,
                method: "ios_arkit_lidar_automatic_scan",
                value: .init(value: item.valueMm, unit: "mm"),
                confidence: item.confidence,
                uncertaintyMm: item.uncertaintyMm
            )
        }
    }
}
