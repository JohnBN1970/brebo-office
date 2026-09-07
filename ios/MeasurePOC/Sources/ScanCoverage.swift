import Foundation

struct ScanCoverage: Equatable {
    let depthFrames: Int
    let meshAnchors: Int

    func progress(using gate: ScanQualityGate = ScanQualityGate()) -> Double {
        let depthProgress = min(1, Double(depthFrames) / Double(max(gate.minimumDepthFrames, 1)))
        let meshProgress = min(1, Double(meshAnchors) / Double(max(gate.minimumMeshAnchors, 1)))
        return min(depthProgress, meshProgress)
    }
}
