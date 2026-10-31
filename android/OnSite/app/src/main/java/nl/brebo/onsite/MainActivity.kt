package nl.brebo.onsite

import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.compose.foundation.layout.*
import androidx.compose.material3.*
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp

class MainActivity : ComponentActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContent { OnSiteHome() }
    }
}

@Composable
private fun OnSiteHome() {
    MaterialTheme {
        Surface(modifier = Modifier.fillMaxSize()) {
            Column(modifier = Modifier.padding(24.dp)) {
                Text("BREBO OnSite", style = MaterialTheme.typography.headlineMedium)
                Spacer(modifier = Modifier.height(12.dp))
                Text("Android basisversie. Activatie en achtergrondregistratie zijn nog niet beschikbaar.")
            }
        }
    }
}
