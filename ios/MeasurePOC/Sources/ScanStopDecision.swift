import Foundation

enum ScanStopDecision: Equatable {
    case keepScanning
    case mayStopAndAnalyse

    static func make(depthFrames: Int, meshAnchors: Int, gate: ScanQualityGate = ScanQualityGate()) -> ScanStopDecision {
        switch gate.evaluate(depthFrames: depthFrames, meshAnchors: meshAnchors) {
        case .insufficientCoverage:
            return .keepScanning
        case .readyForGeometry:
            return .mayStopAndAnalyse
        }
    }
}
