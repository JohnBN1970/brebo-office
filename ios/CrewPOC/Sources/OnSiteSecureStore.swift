import Foundation
import Security

enum OnSiteSecureStore {
    private static let service = "nl.brebo.onsite"
    private static let account = "device-token"

    static func saveDeviceToken(_ token: String) {
        let data = Data(token.utf8)
        SecItemDelete(query as CFDictionary)
        var item = query
        item[kSecValueData as String] = data
        SecItemAdd(item as CFDictionary, nil)
    }

    static func deviceToken() -> String? {
        var item = query
        item[kSecReturnData as String] = true
        item[kSecMatchLimit as String] = kSecMatchLimitOne

        var result: CFTypeRef?
        guard SecItemCopyMatching(item as CFDictionary, &result) == errSecSuccess,
              let data = result as? Data else {
            return nil
        }
        return String(data: data, encoding: .utf8)
    }

    static func clear() {
        SecItemDelete(query as CFDictionary)
    }

    private static var query: [String: Any] {
        [
            kSecClass as String: kSecClassGenericPassword,
            kSecAttrService as String: service,
            kSecAttrAccount as String: account,
            kSecAttrAccessible as String: kSecAttrAccessibleAfterFirstUnlockThisDeviceOnly,
        ]
    }
}
