import Foundation

struct ScanQualityGate {
    enum Verdict: Equatable {
        case insufficientCoverage
        case readyForGeometry
    }

    let minimumDepthFrames: Int
    let minimumMeshAnchors: Int

    init(minimumDepthFrames: Int = 30, minimumMeshAnchors: Int = 1) {
        self.minimumDepthFrames = minimumDepthFrames
        self.minimumMeshAnchors = minimumMeshAnchors
    }

    func evaluate(depthFrames: Int, meshAnchors: Int) -> Verdict {
        guard depthFrames >= minimumDepthFrames,
              meshAnchors >= minimumMeshAnchors else {
            return .insufficientCoverage
        }
        return .readyForGeometry
    }
}
