import ARKit
import RealityKit
import SwiftUI

struct LidarScanView: UIViewRepresentable {
    let isScanning: Bool
    let onProgress: (_ depthFrames: Int, _ meshAnchors: Int) -> Void

    func makeCoordinator() -> Coordinator {
        Coordinator(onProgress: onProgress)
    }

    func makeUIView(context: Context) -> ARView {
        let view = ARView(frame: .zero)
        context.coordinator.view = view
        view.session.delegate = context.coordinator

        let configuration = ARWorldTrackingConfiguration()
        configuration.worldAlignment = .gravity
        if ARWorldTrackingConfiguration.supportsSceneReconstruction(.mesh) {
            configuration.sceneReconstruction = .mesh
        }
        if ARWorldTrackingConfiguration.supportsFrameSemantics(.sceneDepth) {
            configuration.frameSemantics.insert(.sceneDepth)
        }
        view.session.run(configuration, options: [.resetTracking, .removeExistingAnchors])
        return view
    }

    func updateUIView(_ uiView: ARView, context: Context) {
        context.coordinator.isScanning = isScanning
    }

    final class Coordinator: NSObject, ARSessionDelegate {
        weak var view: ARView?
        var isScanning = false
        private var depthFrames = 0
        private let onProgress: (_ depthFrames: Int, _ meshAnchors: Int) -> Void

        init(onProgress: @escaping (_ depthFrames: Int, _ meshAnchors: Int) -> Void) {
            self.onProgress = onProgress
        }

        func session(_ session: ARSession, didUpdate frame: ARFrame) {
            guard isScanning else { return }
            if frame.sceneDepth != nil {
                depthFrames += 1
            }
            let meshCount = frame.anchors.compactMap { $0 as? ARMeshAnchor }.count
            DispatchQueue.main.async { [depthFrames, onProgress] in
                onProgress(depthFrames, meshCount)
            }
        }
    }
}
