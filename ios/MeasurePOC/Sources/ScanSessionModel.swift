import Foundation

struct ScanSessionModel {
    enum State: String {
        case ready
        case scanning
        case analysing
        case complete
    }

    var state: State = .ready
    var startedAt: Date?
    var meshAnchorCount: Int = 0
    var depthFrameCount: Int = 0

    mutating func start() {
        state = .scanning
        startedAt = Date()
        meshAnchorCount = 0
        depthFrameCount = 0
    }

    mutating func registerDepthFrame() {
        guard state == .scanning else { return }
        depthFrameCount += 1
    }

    mutating func updateMeshAnchorCount(_ count: Int) {
        guard state == .scanning else { return }
        meshAnchorCount = count
    }

    mutating func stopForAnalysis() {
        guard state == .scanning else { return }
        state = .analysing
    }
}
