import Foundation

struct ScanDiagnostics: Codable {
    let deviceModel: String
    let osVersion: String
    let depthFrames: Int
    let meshAnchors: Int
    let durationSeconds: Double
    let mode: MeasureMode

    static func make(depthFrames: Int, meshAnchors: Int, durationSeconds: Double) -> ScanDiagnostics {
        ScanDiagnostics(
            deviceModel: ProcessInfo.processInfo.hostName,
            osVersion: ProcessInfo.processInfo.operatingSystemVersionString,
            depthFrames: depthFrames,
            meshAnchors: meshAnchors,
            durationSeconds: durationSeconds,
            mode: .primary
        )
    }
}
