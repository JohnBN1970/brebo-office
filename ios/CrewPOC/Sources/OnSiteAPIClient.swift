import Foundation

struct OnSiteVerifiedIdentity: Decodable {
    let deviceToken: String
    let employeeId: String
    let displayName: String
    let language: String

    enum CodingKeys: String, CodingKey {
        case deviceToken = "device_token"
        case employee
    }

    enum EmployeeKeys: String, CodingKey {
        case id
        case displayName = "display_name"
        case language
    }

    init(from decoder: Decoder) throws {
        let container = try decoder.container(keyedBy: CodingKeys.self)
        deviceToken = try container.decode(String.self, forKey: .deviceToken)
        let employee = try container.nestedContainer(keyedBy: EmployeeKeys.self, forKey: .employee)
        employeeId = try employee.decode(String.self, forKey: .id)
        displayName = try employee.decode(String.self, forKey: .displayName)
        language = try employee.decode(String.self, forKey: .language)
    }
}



struct OnSiteBootstrap: Decodable, Equatable {
    let employee: OnSiteBootstrapEmployee
    let projects: [OnSiteBootstrapProject]
}

struct OnSiteBootstrapEmployee: Decodable, Equatable {
    let id: String
    let displayName: String
    let language: String

    enum CodingKeys: String, CodingKey {
        case id
        case displayName = "display_name"
        case language
    }
}

struct OnSiteBootstrapProject: Decodable, Identifiable, Equatable {
    let id: String
    let name: String
    let zones: [OnSiteBootstrapZone]
}

struct OnSiteBootstrapZone: Decodable, Identifiable, Equatable {
    let id: String
    let name: String
    let latitude: Double
    let longitude: Double
    let radiusMetres: Double

    enum CodingKeys: String, CodingKey {
        case id, name, latitude, longitude
        case radiusMetres = "radius_metres"
    }
}

struct OnSiteChallenge: Decodable {
    let challengeId: String
    let expiresIn: Int

    enum CodingKeys: String, CodingKey {
        case challengeId = "challenge_id"
        case expiresIn = "expires_in"
    }
}

enum OnSiteAPIError: LocalizedError {
    case invalidResponse
    case server(String)

    var errorDescription: String? {
        switch self {
        case .invalidResponse:
            return "Office gaf geen geldige reactie."
        case .server(let message):
            return message
        }
    }
}

struct OnSiteAPIClient {
    private let baseURL: URL
    private let session: URLSession

    init(baseURL: URL = OnSiteConfiguration.apiBaseURL, session: URLSession = .shared) {
        self.baseURL = baseURL
        self.session = session
    }

    func requestCode(mobile: String) async throws -> OnSiteChallenge {
        try await post(path: "/api/onsite/v1/link/request", body: ["mobile": mobile])
    }

    func verifyCode(challengeId: String, code: String) async throws -> OnSiteVerifiedIdentity {
        try await post(path: "/api/onsite/v1/link/verify", body: [
            "challenge_id": challengeId,
            "code": code,
        ])
    }

    func bootstrap(deviceToken: String) async throws -> OnSiteBootstrap {
        let url = baseURL.appending(path: "/api/onsite/v1/bootstrap")
        var request = URLRequest(url: url)
        request.httpMethod = "GET"
        request.setValue("Bearer \(deviceToken)", forHTTPHeaderField: "Authorization")

        let (data, response) = try await session.data(for: request)
        guard let http = response as? HTTPURLResponse else {
            throw OnSiteAPIError.invalidResponse
        }
        guard 200..<300 ~= http.statusCode else {
            let payload = (try? JSONSerialization.jsonObject(with: data)) as? [String: Any]
            let error = payload?["error"] as? String ?? "bootstrap_failed"
            throw OnSiteAPIError.server(error)
        }
        return try JSONDecoder().decode(OnSiteBootstrap.self, from: data)
    }

    private func post<T: Decodable>(path: String, body: [String: String]) async throws -> T {
        let url = baseURL.appending(path: path)
        var request = URLRequest(url: url)
        request.httpMethod = "POST"
        request.setValue("application/json", forHTTPHeaderField: "Content-Type")
        request.httpBody = try JSONSerialization.data(withJSONObject: body)

        let (data, response) = try await session.data(for: request)
        guard let http = response as? HTTPURLResponse else {
            throw OnSiteAPIError.invalidResponse
        }

        guard 200..<300 ~= http.statusCode else {
            let payload = (try? JSONSerialization.jsonObject(with: data)) as? [String: Any]
            let error = payload?["error"] as? String ?? "verification_failed"
            throw OnSiteAPIError.server(error)
        }

        return try JSONDecoder().decode(T.self, from: data)
    }
}

enum OnSiteConfiguration {
    static var apiBaseURL: URL {
        if let configured = Bundle.main.object(forInfoDictionaryKey: "OnSiteAPIBaseURL") as? String,
           let url = URL(string: configured) {
            return url
        }
        return URL(string: "https://sboffice.brebobv.nl")!
    }
}
