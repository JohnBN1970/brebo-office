import Foundation

enum OpeningShape: String, Codable {
    case rectangular
    case unsupported

    static let pocSupported: OpeningShape = .rectangular
}
