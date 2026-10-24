package nl.brebobv.onsite;
import org.json.*;
import java.util.*;
import java.time.Instant;

final class Contract {
    record Zone(String key, String projectId, String id, String name, double latitude, double longitude, float radius) {}
    static List<Zone> zones(JSONObject bootstrap) throws JSONException {
        bootstrap.getJSONObject("employee").getString("id");
        List<Zone> result = new ArrayList<>();
        Set<String> seen = new HashSet<>();
        JSONArray projects = bootstrap.getJSONArray("projects");
        for (int i=0; i<projects.length(); i++) {
            JSONObject p = projects.getJSONObject(i);
            String project = reference(p.getString("id"));
            JSONArray zones = p.getJSONArray("zones");
            for (int j=0; j<zones.length(); j++) {
                JSONObject z = zones.getJSONObject(j);
                String id = reference(z.getString("id"));
                double lat=z.getDouble("latitude"), lon=z.getDouble("longitude"), r=z.getDouble("radius_metres");
                if (!Double.isFinite(lat) || !Double.isFinite(lon) || !Double.isFinite(r) ||
                    Math.abs(lat)>90 || Math.abs(lon)>180 || r<=0 || r>5000)
                    throw new JSONException("Ongeldige projectzone.");
                String key = project + ":" + id;
                if (!seen.add(key)) throw new JSONException("Dubbele projectzone.");
                result.add(new Zone(key, project, id, z.getString("name"), lat, lon, (float)r));
            }
        }
        if (result.size()>100) throw new JSONException("Meer dan 100 zones: Office moet de toewijzing beperken.");
        return result;
    }
    static String reference(String s) throws JSONException {
        if (!s.matches("[A-Za-z0-9_-]{1,64}")) throw new JSONException("Ongeldige zonereferentie.");
        return s;
    }
    static JSONObject event(Zone z, String kind, String uuid, Instant time) throws JSONException {
        if (!kind.equals("in") && !kind.equals("out")) throw new JSONException("Ongeldige overgang.");
        return new JSONObject().put("event_id",uuid).put("event",new JSONObject()
            .put("project_id",z.projectId()).put("zone_id",z.id()).put("kind",kind).put("occurred_at",time.toString()));
    }
}

