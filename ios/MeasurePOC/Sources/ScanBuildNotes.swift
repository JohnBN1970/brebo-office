import Foundation

enum ScanBuildNotes {
    static var display: String {
        "\(BuildIdentity.product) \(BuildIdentity.channel) v\(BuildIdentity.version)"
    }
}
