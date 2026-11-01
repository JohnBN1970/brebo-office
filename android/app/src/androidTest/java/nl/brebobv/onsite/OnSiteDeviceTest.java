package nl.brebobv.onsite;

import android.content.*;
import android.net.Uri;
import android.widget.EditText;
import androidx.test.core.app.ActivityScenario;
import androidx.test.ext.junit.runners.AndroidJUnit4;
import androidx.test.platform.app.InstrumentationRegistry;
import androidx.test.uiautomator.*;
import androidx.work.WorkManager;
import org.json.JSONObject;
import org.junit.*;
import org.junit.runner.RunWith;
import java.util.concurrent.TimeUnit;
import static org.junit.Assert.*;

@RunWith(AndroidJUnit4.class)
public class OnSiteDeviceTest {
    private Context context;
    private UiDevice device;
    private final Contract.Zone zone = new Contract.Zone("123:7","123","7","Testzone",51.36,4.47,100);

    @Before public void prepare() throws Exception {
        context=InstrumentationRegistry.getInstrumentation().getTargetContext();
        device=UiDevice.getInstance(InstrumentationRegistry.getInstrumentation());
        // Emulator tests must never contact the live Office environment.
        assertEquals("https://127.0.0.1:1",BuildConfig.API_BASE_URL);
        clean();
    }
    @After public void finish() throws Exception { clean(); }
    private void clean() throws Exception {
        WorkManager.getInstance(context).cancelAllWork().getResult().get(10,TimeUnit.SECONDS);
        new Vault(context).clear();
        Zones.prefs(context).edit().clear().commit();
        try(LocalStore store=new LocalStore(context)){store.clear();}
    }
    @Test public void welcomeScreenStartsAndCanBeCaptured() throws Exception {
        try(ActivityScenario<MainActivity> activity=ActivityScenario.launch(MainActivity.class)) {
            assertTrue(device.wait(Until.hasObject(By.text("Welkom op de bouwplaats")),5000));
            assertTrue(device.hasObject(By.text("Toestel koppelen")));
            assertNull(new Vault(context).token());
            // The test runner uninstalls the app afterwards, so retain the capture outside app storage.
            device.executeShellCommand("screencap -p /sdcard/Download/onsite-welcome.png");
            assertTrue(device.executeShellCommand("ls /sdcard/Download/onsite-welcome.png").contains("onsite-welcome.png"));
        }
    }
    @Test public void invalidActivationIsExplainedWithoutLinking() throws Exception {
        try(ActivityScenario<MainActivity> activity=ActivityScenario.launch(MainActivity.class)) {
            device.wait(Until.findObject(By.text("Toestel koppelen")),5000).click();
            assertTrue(device.wait(Until.hasObject(By.text("Vul de activatiecode uit je persoonlijke link in.")),5000));
            assertNull(new Vault(context).token());
            device.findObject(By.text("OK")).click();
        }
    }
    @Test public void activationLinkRequiresConfirmationAndSurvivesRecreation() throws Exception {
        String token="emulator-token-for-confirmation-only";
        Intent link=new Intent(context,MainActivity.class)
            .setAction(Intent.ACTION_VIEW).setData(Uri.parse("brebo-onsite://activate?token="+token));
        try(ActivityScenario<MainActivity> activity=ActivityScenario.launch(MainActivity.class)) {
            activity.onActivity(a->{
                Intent launchMetadata=new Intent(a.getIntent());
                a.onNewIntent(link);
                assertEquals(token,findInput(a.findViewById(android.R.id.content)).getText().toString());
                assertNull(a.getIntent().getData());
                // ActivityScenario filters lifecycle events by the original launch Intent.
                // Restore only its routing metadata; the app's saved pending token is unchanged.
                a.setIntent(launchMetadata);
            });
            activity.recreate();
            activity.onActivity(a->{
                android.view.ViewGroup content=a.findViewById(android.R.id.content);
                assertEquals(token,findInput(content).getText().toString());
            });
            assertNull(new Vault(context).token());
        }
    }
    private EditText findInput(android.view.View v) {
        if(v instanceof EditText)return (EditText)v;
        if(v instanceof android.view.ViewGroup) {
            android.view.ViewGroup group=(android.view.ViewGroup)v;
            for(int i=0;i<group.getChildCount();i++){EditText input=findInput(group.getChildAt(i));if(input!=null)return input;}
        }
        return null;
    }
    @Test public void explicitPersonalLinkStartsActivationWithoutTypingCode() throws Exception {
        String token="emulator-only-auto-activation-token";
        Intent link=new Intent(context,MainActivity.class).setAction(Intent.ACTION_VIEW)
            .setData(Uri.parse("brebo-onsite://activate?token="+token+"&start=1"));
        try(ActivityScenario<MainActivity> activity=ActivityScenario.launch(MainActivity.class)) {
            activity.onActivity(a->{
                Intent routing=new Intent(a.getIntent());
                a.onNewIntent(link);
                a.setIntent(routing);
            });
            // The deliberately unreachable emulator API proves the activation was attempted.
            assertTrue(device.wait(Until.hasObject(By.text("Geen verbinding met Office. Probeer opnieuw.")),10000));
            assertNull(new Vault(context).token());
            device.findObject(By.text("OK")).click();
        }
    }
    @Test public void personalLinkCannotSilentlyReplaceLinkedEmployee() throws Exception {
        new Vault(context).save("existing-emulator-device-token");
        Intent link=new Intent(context,MainActivity.class).setAction(Intent.ACTION_VIEW)
            .setData(Uri.parse("brebo-onsite://activate?token=emulator-only-auto-activation-token&start=1"));
        try(ActivityScenario<MainActivity> activity=ActivityScenario.launch(MainActivity.class)) {
            activity.onActivity(a->{
                Intent routing=new Intent(a.getIntent());
                a.onNewIntent(link);
                a.setIntent(routing);
            });
            assertTrue(device.wait(Until.hasObject(By.text("Dit toestel is al gekoppeld. Ontkoppel het eerst als je een andere medewerker wilt activeren.")),5000));
            assertEquals("existing-emulator-device-token",new Vault(context).token());
            device.findObject(By.text("OK")).click();
        }
    }
    @Test public void deviceTokenRoundTripsThroughAndroidKeystore() throws Exception {
        String token="emulator-only-device-token";
        new Vault(context).save(token);
        assertEquals(token,new Vault(context).token());
        String stored=context.getSharedPreferences("vault",0).getString("token","");
        assertFalse(stored.contains(token));
        new Vault(context).clear();
        assertNull(new Vault(context).token());
    }
    @Test public void offlineEventAndUuidSurviveDatabaseReopen() throws Exception {
        String id;
        try(LocalStore store=new LocalStore(context)) {
            store.enqueue(zone,"in");
            id=store.pending().get(0).getString("event_id");
        }
        try(LocalStore reopened=new LocalStore(context)) {
            assertEquals(1,reopened.count());
            JSONObject event=reopened.pending().get(0);
            assertEquals(id,event.getString("event_id"));
            assertEquals("in",event.getJSONObject("event").getString("kind"));
            reopened.ack(id);
            assertEquals(0,reopened.count());
        }
    }
    @Test public void duplicateArrivalIsSuppressedButDepartureIsRetained() throws Exception {
        try(LocalStore store=new LocalStore(context)) {
            store.enqueue(zone,"in");store.enqueue(zone,"in");store.enqueue(zone,"out");
            assertEquals(2,store.count());
            assertEquals("in",store.pending().get(0).getJSONObject("event").getString("kind"));
            assertEquals("out",store.pending().get(1).getJSONObject("event").getString("kind"));
        }
    }
    @Test public void officeRejectedEventRemainsVisibleAndStopsAutomaticRetries() throws Exception {
        try(LocalStore store=new LocalStore(context)) {
            store.enqueue(zone,"in");
            store.reject(store.pending().get(0).getString("event_id"),403);
            assertEquals(1,store.count());
            assertEquals(1,store.rejected());
            assertTrue(store.pending().isEmpty());
        }
    }
    @Test public void monitoringCannotStartWithoutUserConsent() throws Exception {
        assertFalse(Zones.allowed(context));
        Zones.register(context,new JSONObject("{\"employee\":{\"id\":\"42\"},\"projects\":[]}"));
        assertFalse(Zones.prefs(context).getBoolean("registered",false));
        assertFalse(Zones.prefs(context).getBoolean("consent",false));
    }
}
