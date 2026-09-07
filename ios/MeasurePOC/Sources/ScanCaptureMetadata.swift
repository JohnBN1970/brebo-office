import Foundation

struct ScanCaptureMetadata: Codable {
    let capturedAt: Date
    let coordinateSystem: String
    let units: String

    static func current() -> ScanCaptureMetadata {
        ScanCaptureMetadata(
            capturedAt: Date(),
            coordinateSystem: "arkit_world_gravity_aligned",
            units: "metres_internal_millimetres_output"
        )
    }
}
