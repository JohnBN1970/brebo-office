import Foundation
import Security

enum OnSiteSecureStore {
    private static let service = "nl.brebo.onsite"
    private static let account = "device-token"

    static func saveDeviceToken(_ token: String) throws {
        let data = Data(token.utf8)
        let update: [String: Any] = [
            kSecValueData as String: data,
            kSecAttrAccessible as String: kSecAttrAccessibleAfterFirstUnlockThisDeviceOnly,
        ]
        let status = SecItemUpdate(query as CFDictionary, update as CFDictionary)
        if status == errSecItemNotFound {
            var item = query
            item[kSecValueData as String] = data
            item[kSecAttrAccessible as String] = kSecAttrAccessibleAfterFirstUnlockThisDeviceOnly
            guard SecItemAdd(item as CFDictionary, nil) == errSecSuccess else {
                throw OnSiteAPIError.server("Apparaatkoppeling kon niet veilig worden opgeslagen. Vraag een nieuwe code in Office.")
            }
        } else if status != errSecSuccess {
            throw OnSiteAPIError.server("Apparaatkoppeling kon niet veilig worden opgeslagen.")
        }
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
        ]
    }
}
