import Foundation

enum MeasureMode: String, Codable {
    case automaticScan = "automatic_scan"
    case manualControl = "manual_control"

    static let primary: MeasureMode = .automaticScan
}
