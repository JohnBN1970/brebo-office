import SwiftUI

struct ScanContentView: View {
    @State private var scanning = false
    @State private var depthFrames = 0
    @State private var meshAnchors = 0

    var body: some View {
        ZStack(alignment: .bottom) {
            LidarScanView(isScanning: scanning) { frames, anchors in
                depthFrames = frames
                meshAnchors = anchors
            }
            .ignoresSafeArea()

            VStack(spacing: 10) {
                Text(scanning ? "Sparing scannen" : "BREBO Measure")
                    .font(.headline)
                Text("Depth frames: \(depthFrames)  •  Mesh: \(meshAnchors)")
                    .font(.caption)
                    .monospacedDigit()
                Text(scanning ? "Beweeg rustig rondom de volledige sparing" : "Geen meetpunten aantikken")
                    .font(.subheadline)

                Button(scanning ? "Scan stoppen" : "Start scan") {
                    scanning.toggle()
                    if scanning {
                        depthFrames = 0
                        meshAnchors = 0
                    }
                }
                .buttonStyle(.borderedProminent)
            }
            .padding()
            .frame(maxWidth: .infinity)
            .background(.ultraThinMaterial)
        }
    }
}
