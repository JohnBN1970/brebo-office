package nl.brebobv.onsite;
import org.json.*;
import org.junit.Test;
import java.time.Instant;
import static org.junit.Assert.*;

public class ContractTest {
    private JSONObject bootstrap(double lat,double radius) throws Exception {
        return new JSONObject("{\"employee\":{\"id\":\"42\"},\"projects\":[{\"id\":\"123\",\"zones\":[{\"id\":\"7\",\"name\":\"Bouwplaats\",\"latitude\":"+lat+",\"longitude\":4.47,\"radius_metres\":"+radius+"}]}]}");
    }
    @Test public void validBootstrapAndEnvelopeMatchOffice() throws Exception {
        Contract.Zone zone=Contract.zones(bootstrap(51.36,100)).get(0);
        String id="b8fca137-2971-4ff6-a531-35b796de6acb";
        JSONObject envelope=Contract.event(zone,"in",id,Instant.parse("2026-10-24T08:00:00Z"));
        assertEquals(2,envelope.length());
        assertEquals(id,envelope.getString("event_id"));
        JSONObject event=envelope.getJSONObject("event");
        assertEquals(4,event.length());
        assertEquals("123",event.getString("project_id"));
        assertEquals("7",event.getString("zone_id"));
        assertEquals("in",event.getString("kind"));
        assertFalse(event.has("latitude"));
        assertFalse(event.has("employee_id"));
        assertEquals(envelope.toString(),Contract.event(zone,"in",id,Instant.parse("2026-10-24T08:00:00Z")).toString());
    }
    @Test public void timestampFitsOfficeMicrosecondLimit() throws Exception {
        JSONObject envelope=Contract.event(Contract.zones(bootstrap(51,100)).get(0),"out","stable-id",Instant.parse("2026-10-24T08:00:00.123456789Z"));
        assertEquals("2026-10-24T08:00:00.123Z",envelope.getJSONObject("event").getString("occurred_at"));
    }
    @Test(expected=JSONException.class) public void rejectsInvalidLatitude() throws Exception { Contract.zones(bootstrap(91,100)); }
    @Test(expected=JSONException.class) public void rejectsInvalidRadius() throws Exception { Contract.zones(bootstrap(51,0)); }
    @Test(expected=JSONException.class) public void rejectsDuplicateZones() throws Exception {
        JSONObject b=bootstrap(51,100);
        JSONArray a=b.getJSONArray("projects").getJSONObject(0).getJSONArray("zones");
        a.put(a.getJSONObject(0));
        Contract.zones(b);
    }
    @Test(expected=JSONException.class) public void rejectsUnsupportedTransition() throws Exception {
        Contract.event(Contract.zones(bootstrap(51,100)).get(0),"location", "x",Instant.now());
    }
    @Test public void endpointPreservesOfficeTestPrefix() throws Exception {
        assertEquals("https://example.test/office-test/api/onsite/v1/bootstrap",Api.endpoint("https://example.test/office-test/","bootstrap").toString());
        assertEquals("https://example.test/api/onsite/v1/activate",Api.endpoint("https://example.test","activate").toString());
    }
    @Test(expected=java.net.MalformedURLException.class) public void refusesCleartext() throws Exception { Api.endpoint("http://example.test","activate"); }
    @Test(expected=java.net.MalformedURLException.class) public void refusesEmbeddedCredentials() throws Exception { Api.endpoint("https://secret@example.test","activate"); }
}
