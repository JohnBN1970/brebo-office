package nl.brebobv.onsite;

import org.json.*;
import java.net.*;
import java.io.*;
import java.nio.charset.StandardCharsets;

final class Api {
    static final class Failure extends IOException {
        final int status;
        Failure(int status) { super("Office-verzoek mislukt (" + status + ")."); this.status = status; }
    }
    static URL endpoint(String base, String path) throws MalformedURLException {
        URL url = new URL(base);
        if (!"https".equals(url.getProtocol()) || url.getUserInfo() != null || url.getQuery() != null || url.getRef() != null)
            throw new MalformedURLException("Alleen een HTTPS Office-adres is toegestaan.");
        return new URL(base.replaceAll("/+$", "") + "/api/onsite/v1/" + path);
    }
    JSONObject call(String path, String token, JSONObject body) throws Exception {
        HttpURLConnection c = (HttpURLConnection) endpoint(BuildConfig.API_BASE_URL, path).openConnection();
        c.setInstanceFollowRedirects(false);
        c.setConnectTimeout(15000); c.setReadTimeout(20000);
        c.setRequestProperty("Accept", "application/json");
        if (token != null) c.setRequestProperty("Authorization", "Bearer " + token);
        try {
            if (body != null) {
                c.setRequestMethod("POST"); c.setDoOutput(true);
                c.setRequestProperty("Content-Type", "application/json");
                try (OutputStream out = c.getOutputStream()) { out.write(body.toString().getBytes(StandardCharsets.UTF_8)); }
            }
            int code = c.getResponseCode();
            if (code < 200 || code >= 300) throw new Failure(code);
            try (InputStream in = c.getInputStream()) {
                ByteArrayOutputStream buffer = new ByteArrayOutputStream();
                byte[] chunk = new byte[8192]; int n;
                while ((n=in.read(chunk))!=-1) { buffer.write(chunk,0,n); if(buffer.size()>1024*1024) throw new IOException("Office-antwoord is te groot."); }
                byte[] bytes = buffer.toByteArray();
                if (bytes.length > 1024 * 1024) throw new IOException("Office-antwoord is te groot.");
                return new JSONObject(new String(bytes, StandardCharsets.UTF_8));
            }
        } finally { c.disconnect(); }
    }
}

