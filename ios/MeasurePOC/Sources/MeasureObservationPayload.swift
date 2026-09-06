import Foundation

struct MeasureObservationPayload: Codable {
    let key: String
    let provenance: String
    let method: String
    let value: MeasurementValue
    let confidence: Double?
    let uncertaintyMm: Double?

    struct MeasurementValue: Codable {
        let value: Double
        let unit: String
    }

    static func distance(_ millimetres: Double) -> MeasureObservationPayload {
        MeasureObservationPayload(
            key: "poc.distance",
            provenance: "measured",
            method: "ios_arkit_lidar_poc",
            value: MeasurementValue(value: millimetres, unit: "mm"),
            confidence: nil,
            uncertaintyMm: nil
        )
    }
}
