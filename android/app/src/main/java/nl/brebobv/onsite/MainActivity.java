package nl.brebobv.onsite;

import android.Manifest;
import android.app.*;
import android.os.*;
import android.content.*;
import android.content.pm.PackageManager;
import android.graphics.Color;
import android.net.Uri;
import android.provider.Settings;
import android.text.InputType;
import android.view.*;
import android.widget.*;
import org.json.*;
import java.util.concurrent.Executors;

public final class MainActivity extends Activity {
    private LinearLayout root;
    private TextView status;
    private boolean busy;
    private String pendingToken;
    private String displayedBootstrap;
    private final java.util.concurrent.ExecutorService io=Executors.newSingleThreadExecutor();
    private final Handler handler=new Handler(Looper.getMainLooper());
    private final Runnable tick=new Runnable(){ public void run(){ updateStatus();handler.postDelayed(this,3000); }};

    public void onCreate(Bundle state) {
        super.onCreate(state);
        if(state!=null)pendingToken=state.getString("pending_token");
        readLink(getIntent()); render();
    }
    public void onSaveInstanceState(Bundle out) { super.onSaveInstanceState(out);out.putString("pending_token",pendingToken); }
    protected void onNewIntent(Intent i) { super.onNewIntent(i);setIntent(i);readLink(i);render(); }
    private void readLink(Intent i) {
        Uri u=i.getData();
        if(u!=null && "brebo-onsite".equals(u.getScheme()) && "activate".equals(u.getHost())) {
            String token=u.getQueryParameter("token");
            if(token!=null && token.matches("[A-Za-z0-9_-]{20,256}"))pendingToken=token;
        }
        i.setData(null);
    }
    protected void onResume() {
        super.onResume();
        if(!Zones.allowed(this))Zones.stop(this);
        SyncWorker.schedule(this);
        handler.post(tick); render();
    }
    protected void onPause() { super.onPause();handler.removeCallbacks(tick); }
    protected void onDestroy() { super.onDestroy();io.shutdown(); }
    private int dp(int v) { return Math.round(v*getResources().getDisplayMetrics().density); }
    private TextView text(String value,int size,int color) {
        TextView t=new TextView(this);t.setText(value);t.setTextSize(size);t.setTextColor(color);t.setPadding(0,dp(6),0,dp(6));root.addView(t);return t;
    }
    private void button(String label,Runnable action) {
        Button b=new Button(this);b.setText(label);b.setAllCaps(false);b.setTextColor(Color.WHITE);
        b.setBackgroundTintList(android.content.res.ColorStateList.valueOf(Color.rgb(24,94,76)));
        LinearLayout.LayoutParams p=new LinearLayout.LayoutParams(-1,dp(54));p.topMargin=dp(12);root.addView(b,p);
        b.setEnabled(!busy);b.setOnClickListener(v->action.run());
    }
    private boolean linked() {
        try { return new Vault(this).token()!=null; }catch(Exception e) { return false; }
    }
    private void render() {
        displayedBootstrap=Zones.prefs(this).getString("bootstrap","");
        ScrollView scroll=new ScrollView(this);scroll.setFillViewport(true);scroll.setBackgroundColor(Color.rgb(245,247,244));
        root=new LinearLayout(this);root.setOrientation(LinearLayout.VERTICAL);root.setPadding(dp(24),dp(24),dp(24),dp(28));
        scroll.addView(root);
        scroll.setOnApplyWindowInsetsListener((v,insets)->{
            if(Build.VERSION.SDK_INT>=30) {
                android.graphics.Insets bars=insets.getInsets(WindowInsets.Type.systemBars()|WindowInsets.Type.ime());
                v.setPadding(bars.left,bars.top,bars.right,bars.bottom);
            } else v.setPadding(insets.getSystemWindowInsetLeft(),insets.getSystemWindowInsetTop(),insets.getSystemWindowInsetRight(),insets.getSystemWindowInsetBottom());
            return insets;
        });
        setContentView(scroll);
        text("BREBO  /  OP LOCATIE",12,Color.rgb(38,102,82));
        TextView title=text("OnSite",38,Color.rgb(16,43,42));title.setTypeface(null,android.graphics.Typeface.BOLD);
        text(BuildConfig.DEBUG?"Android · testversie 0.1":"Android",13,Color.DKGRAY);
        status=text("",15,Color.rgb(16,43,42));
        if(!linked()) {
            text("Welkom op de bouwplaats",24,Color.rgb(16,43,42));
            text("Koppel dit toestel met de persoonlijke activatielink die je via Office hebt ontvangen.",16,Color.DKGRAY);
            EditText field=new EditText(this);field.setHint("Activatiecode of volledige link");
            field.setSingleLine(true);field.setInputType(InputType.TYPE_CLASS_TEXT|InputType.TYPE_TEXT_VARIATION_PASSWORD);
            if(pendingToken!=null)field.setText(pendingToken);
            root.addView(field,new LinearLayout.LayoutParams(-1,dp(60)));
            button("Toestel koppelen",()->{
                String token=field.getText().toString().trim();
                if(token.startsWith("brebo-onsite://")) {
                    Uri u=Uri.parse(token);
                    if(!"activate".equals(u.getHost())) { message("Ongeldige activatielink.");return; }
                    token=u.getQueryParameter("token");
                }
                if(token==null || !token.matches("[A-Za-z0-9_-]{20,256}")) {message("Vul de activatiecode uit je persoonlijke link in.");return;}
                activate(token);
            });
        } else {
            JSONObject bootstrap=null;
            try { bootstrap=new JSONObject(Zones.prefs(this).getString("bootstrap","{}")); }catch(Exception ignored){}
            String employee=bootstrap==null?"":bootstrap.optJSONObject("employee")==null?"":bootstrap.optJSONObject("employee").optString("display_name","");
            text(employee.trim().isEmpty()?"Je toestel is gekoppeld":employee,24,Color.rgb(16,43,42));
            text("AUTOMATISCHE AANWEZIGHEID",12,Color.rgb(38,102,82));
            text("OnSite herkent aankomst en vertrek bij je toegewezen projectzones. Office ontvangt alleen de projectzone en het tijdstip van de melding.",16,Color.DKGRAY);
            button(Zones.allowed(this)?"Locatie-instellingen openen":"Automatische aanwezigheid instellen",()->{if(Zones.allowed(this))settings();else enable();});
            if(Zones.prefs(this).getBoolean("consent",false))
                button("Automatische aanwezigheid pauzeren",()->{
                    Zones.prefs(this).edit().putBoolean("consent",false).commit();Zones.stop(this);render();
                });
            button("Projecten en meldingen synchroniseren",()->{SyncWorker.schedule(this);message("Synchronisatie aangevraagd.");});
            text("MIJN PROJECTZONES",12,Color.rgb(38,102,82));
            try {
                java.util.List<Contract.Zone> zones=Contract.zones(bootstrap);
                if(zones.isEmpty())text("Office heeft nog geen projectzones aan je toegewezen.",16,Color.DKGRAY);
                for(Contract.Zone z:zones) {
                    text(z.name(),20,Color.rgb(16,43,42));
                    text("Project "+z.projectId()+" · straal "+Math.round(z.radius())+" m",14,Color.DKGRAY);
                }
            }catch(Exception e) { text("Projecten worden bij de volgende synchronisatie opgehaald.",16,Color.DKGRAY); }
            button("Toestel ontkoppelen",()->new AlertDialog.Builder(this).setTitle("Toestel ontkoppelen?")
                .setMessage("Locatiebewaking stopt. Niet-verstuurde meldingen worden van dit toestel verwijderd. Synchroniseer eerst als je deze wilt bewaren.")
                .setNegativeButton("Terug",null).setPositiveButton("Ontkoppelen",(d,w)->{
                    busy=true;
                    io.execute(()-> {
                        synchronized(SyncWorker.LOCK) {
                            Zones.prefs(this).edit().clear().commit();Zones.stop(this);new Vault(this).clear();
                            try(LocalStore store=new LocalStore(this)){store.clear();}
                            androidx.work.WorkManager.getInstance(this).cancelUniqueWork("onsite-refresh");
                        }
                        runOnUiThread(()->{busy=false;render();});
                    });
                }).show());
        }
        text("Locatiemeldingen kunnen vertraagd aankomen. Controleer je aanwezigheid bij twijfel met de uitvoerder.",13,Color.DKGRAY);
        updateStatus();
    }
    private void activate(String token) {
        try(LocalStore store=new LocalStore(this)) {
            if(store.count()>0) {
                new AlertDialog.Builder(this).setTitle("Meldingen van de vorige koppeling")
                    .setMessage("Er staan nog meldingen van een vorige toestelkoppeling op dit toestel. Bij opnieuw koppelen worden deze verwijderd. Stem dit zo nodig eerst af met de uitvoerder.")
                    .setNegativeButton("Terug",null).setPositiveButton("Verwijderen en koppelen",(d,w)->{
                        synchronized(SyncWorker.LOCK) {
                            try(LocalStore old=new LocalStore(this)){old.clear();}
                            Zones.prefs(this).edit().clear().commit();
                        }
                        activate(token);
                    }).show();
                return;
            }
        }
        busy=true;render();
        io.execute(()->{
            String error=null;
            try {
                synchronized(SyncWorker.LOCK) {
                    JSONObject result=new Api().call("activate",null,new JSONObject().put("activation_token",token));
                    Zones.prefs(this).edit().clear().commit();
                    new Vault(this).save(result.getString("device_token"));
                    pendingToken=null;
                    SyncWorker.schedule(this);
                }
            }catch(Exception e){error=e instanceof Api.Failure?"Activatie niet gelukt. Controleer of je persoonlijke link nog geldig is.":"Geen verbinding met Office. Probeer opnieuw.";}
            String message=error;
            runOnUiThread(()->{busy=false;render();if(message!=null)message(message);});
        });
    }
    private void enable() {
        if(!Zones.locationEnabled(this)) {
            new AlertDialog.Builder(this).setTitle("Telefoonlocatie staat uit")
                .setMessage("Schakel eerst de locatie van je telefoon in en keer daarna terug naar OnSite.")
                .setPositiveButton("Locatie inschakelen",(d,w)->startActivity(new Intent(Settings.ACTION_LOCATION_SOURCE_SETTINGS)))
                .setNegativeButton("Later",null).show();return;
        }
        new AlertDialog.Builder(this).setTitle("Aanwezigheid automatisch herkennen")
            .setMessage("OnSite gebruikt je locatie ook wanneer de app gesloten is om aankomst en vertrek bij je projectzones te herkennen. Er wordt geen routehistorie naar Office verstuurd. Je kunt dit op ieder moment pauzeren.\n\nKies nauwkeurige locatie en daarna ‘Altijd toestaan’ bij de Android-locatie-instellingen.")
            .setNegativeButton("Nu niet",null).setPositiveButton("Instellen",(d,w)->{
                Zones.prefs(this).edit().putBoolean("consent",true).commit();
                if(checkSelfPermission(Manifest.permission.ACCESS_FINE_LOCATION)!=PackageManager.PERMISSION_GRANTED)
                    requestPermissions(new String[]{Manifest.permission.ACCESS_FINE_LOCATION,Manifest.permission.ACCESS_COARSE_LOCATION},31);
                else background();
            }).show();
    }
    private void background() {
        if(Build.VERSION.SDK_INT==29 && checkSelfPermission(Manifest.permission.ACCESS_BACKGROUND_LOCATION)!=PackageManager.PERMISSION_GRANTED)
            requestPermissions(new String[]{Manifest.permission.ACCESS_BACKGROUND_LOCATION},32);
        else if(Build.VERSION.SDK_INT>=30 && checkSelfPermission(Manifest.permission.ACCESS_BACKGROUND_LOCATION)!=PackageManager.PERMISSION_GRANTED)
            settings();
        else { SyncWorker.schedule(this);render(); }
    }
    private void settings() {
        startActivity(new Intent(Settings.ACTION_APPLICATION_DETAILS_SETTINGS,Uri.parse("package:"+getPackageName())));
    }
    public void onRequestPermissionsResult(int request,String[] p,int[] results) {
        super.onRequestPermissionsResult(request,p,results);
        if(request==31 && checkSelfPermission(Manifest.permission.ACCESS_FINE_LOCATION)==PackageManager.PERMISSION_GRANTED)
            new AlertDialog.Builder(this).setTitle("Ook op de achtergrond")
                .setMessage("Open de app-instellingen, kies Machtigingen → Locatie → Altijd toestaan.")
                .setPositiveButton("Doorgaan",(d,w)->background()).setNegativeButton("Later",null).show();
        if(request==32)SyncWorker.schedule(this);
        render();
    }
    private void updateStatus() {
        if(status==null)return;
        if(!java.util.Objects.equals(displayedBootstrap,Zones.prefs(this).getString("bootstrap",""))) {render();return;}
        boolean registered=Zones.allowed(this)&&Zones.prefs(this).getBoolean("registered",false)
            && System.currentTimeMillis()-Zones.prefs(this).getLong("registered_at",0)<Zones.TTL;
        String label=!linked()?"Nog niet gekoppeld":registered?"● Automatische aanwezigheid actief":"○ Automatische aanwezigheid niet actief";
        try(LocalStore store=new LocalStore(this)) {
            int n=store.count(), rejected=store.rejected();
            if(n>0)label+="\n"+n+" melding(en) op dit toestel"+(rejected>0?" · "+rejected+" door Office geweigerd":" · wachten op verzending");
        }
        String error=Zones.prefs(this).getString("error","");
        if(!error.trim().isEmpty())label+="\n"+error;
        status.setText(label);
    }
    private void message(String s) { new AlertDialog.Builder(this).setMessage(s).setPositiveButton("OK",null).show(); }
}
