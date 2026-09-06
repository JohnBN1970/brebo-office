import ARKit

struct ScanCapability {
    let supportsSceneDepth: Bool
    let supportsMeshReconstruction: Bool

    static var currentDevice: ScanCapability {
        ScanCapability(
            supportsSceneDepth: ARWorldTrackingConfiguration.supportsFrameSemantics(.sceneDepth),
            supportsMeshReconstruction: ARWorldTrackingConfiguration.supportsSceneReconstruction(.mesh)
        )
    }

    var isSupportedForMeasurePOC: Bool {
        supportsSceneDepth && supportsMeshReconstruction
    }
}
