import Foundation

struct ScanSessionID: Codable, Hashable, CustomStringConvertible {
    let value: UUID

    init(value: UUID = UUID()) {
        self.value = value
    }

    var description: String { value.uuidString.lowercased() }
}
