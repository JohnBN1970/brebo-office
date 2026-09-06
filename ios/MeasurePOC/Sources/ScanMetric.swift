import Foundation

enum ScanMetric: String, CaseIterable, Codable {
    case captureDurationSeconds
    case depthFrameCount
    case meshAnchorCount
    case widthDeviationMm
    case heightDeviationMm
    case uncertaintyMm
    case humanCorrections
    case revisitRequired
}
