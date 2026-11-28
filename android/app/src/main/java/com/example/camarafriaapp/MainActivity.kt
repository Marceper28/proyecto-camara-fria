package com.example.camarafriaapp

import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.PasswordVisualTransformation
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.android.volley.DefaultRetryPolicy
import com.android.volley.Request
import com.android.volley.toolbox.JsonObjectRequest
import com.android.volley.toolbox.Volley
import kotlinx.coroutines.delay
import org.json.JSONObject

data class UsuarioSesion(val id: Int, val nombre: String, val correo: String, val rol: String)
data class LecturaTemp(val temperatura: String, val fecha: String)
data class RegistroAcceso(val uid: String, val permitido: Boolean, val fecha: String)

class MainActivity : ComponentActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContent {
            MaterialTheme {
                Surface(
                    modifier = Modifier.fillMaxSize(),
                    color = Color(0xFFF2F4F8)
                ) {
                    AppPrincipal()
                }
            }
        }
    }
}

@Composable
fun AppPrincipal() {
    // Usa localhost con adb reverse | Cambia a 192.168.1.134 al probar en celular por Wi-Fi
    val baseUrl = "http://localhost:8080/proyecto-camara-fria/backend/api"

    var sesion by remember { mutableStateOf<UsuarioSesion?>(null) }

    if (sesion == null) {
        LoginScreen(
            baseUrl = baseUrl,
            onLoginSuccess = { usuario -> sesion = usuario }
        )
    } else {
        CamaraFriaScreen(
            baseUrl = baseUrl,
            usuario = sesion!!,
            onLogout = { sesion = null }
        )
    }
}

@Composable
fun LoginScreen(baseUrl: String, onLoginSuccess: (UsuarioSesion) -> Unit) {
    val context = LocalContext.current
    val queue = remember { Volley.newRequestQueue(context) }

    var correo by remember { mutableStateOf("") }
    var password by remember { mutableStateOf("") }
    var mensajeError by remember { mutableStateOf("") }
    var cargando by remember { mutableStateOf(false) }

    fun iniciarSesion() {
        if (correo.isBlank() || password.isBlank()) {
            mensajeError = "Ingresa correo y contraseña"
            return
        }
        cargando = true
        mensajeError = ""

        val body = JSONObject().apply {
            put("correo", correo.trim())
            put("password", password)
        }

        val request = JsonObjectRequest(
            Request.Method.POST, "$baseUrl/login.php", body,
            { response ->
                cargando = false
                if (response.optString("status") == "ok") {
                    val userObj = response.getJSONObject("usuario")
                    onLoginSuccess(
                        UsuarioSesion(
                            id = userObj.optInt("id_usuario", 0),
                            nombre = userObj.optString("nombre", "Usuario"),
                            correo = userObj.optString("correo", correo),
                            rol = userObj.optString("rol", "operador")
                        )
                    )
                } else {
                    mensajeError = response.optString("mensaje", "Credenciales inválidas")
                }
            },
            { error ->
                cargando = false
                val responseData = error.networkResponse?.data
                if (responseData != null) {
                    try {
                        val errorJson = JSONObject(String(responseData, Charsets.UTF_8))
                        mensajeError = errorJson.optString("mensaje", "Correo o contraseña incorrectos")
                    } catch (e: Exception) {
                        mensajeError = "Error (${error.networkResponse?.statusCode})"
                    }
                } else {
                    mensajeError = "Sin conexión (${error.javaClass.simpleName})"
                }
            }
        )

        request.retryPolicy = DefaultRetryPolicy(10000, 1, 1.0f)
        queue.add(request)
    }

    Column(
        modifier = Modifier
            .fillMaxSize()
            .padding(24.dp),
        verticalArrangement = Arrangement.Center,
        horizontalAlignment = Alignment.CenterHorizontally
    ) {
        Text(
            text = "Cámara Fría",
            fontSize = 28.sp,
            fontWeight = FontWeight.Bold,
            color = Color(0xFF1A237E)
        )
        Text(
            text = "Inicia sesión para monitorear",
            fontSize = 14.sp,
            color = Color.Gray
        )

        Spacer(modifier = Modifier.height(24.dp))

        Card(
            modifier = Modifier.fillMaxWidth(),
            shape = RoundedCornerShape(16.dp),
            colors = CardDefaults.cardColors(containerColor = Color.White)
        ) {
            Column(modifier = Modifier.padding(20.dp)) {
                OutlinedTextField(
                    value = correo,
                    onValueChange = { correo = it },
                    label = { Text("Correo electrónico") },
                    singleLine = true,
                    modifier = Modifier.fillMaxWidth()
                )

                Spacer(modifier = Modifier.height(12.dp))

                OutlinedTextField(
                    value = password,
                    onValueChange = { password = it },
                    label = { Text("Contraseña") },
                    singleLine = true,
                    visualTransformation = PasswordVisualTransformation(),
                    modifier = Modifier.fillMaxWidth()
                )

                if (mensajeError.isNotEmpty()) {
                    Spacer(modifier = Modifier.height(10.dp))
                    Text(
                        text = mensajeError,
                        color = Color(0xFFD32F2F),
                        fontSize = 13.sp
                    )
                }

                Spacer(modifier = Modifier.height(20.dp))

                Button(
                    onClick = { iniciarSesion() },
                    enabled = !cargando,
                    modifier = Modifier
                        .fillMaxWidth()
                        .height(48.dp)
                ) {
                    Text(if (cargando) "Verificando..." else "Entrar")
                }
            }
        }
    }
}

@Composable
fun CamaraFriaScreen(baseUrl: String, usuario: UsuarioSesion, onLogout: () -> Unit) {
    val context = LocalContext.current
    val queue = remember { Volley.newRequestQueue(context) }
    val urlApi = "$baseUrl/obtener_datos.php"

    var tempActual by remember { mutableStateOf("--.-") }
    var estadoTexto by remember { mutableStateOf("Cargando datos...") }
    var enAlerta by remember { mutableStateOf(false) }
    var listaLecturas by remember { mutableStateOf(listOf<LecturaTemp>()) }
    var listaAccesos by remember { mutableStateOf(listOf<RegistroAcceso>()) }

    fun cargarDatos() {
        val request = JsonObjectRequest(
            Request.Method.GET, urlApi, null,
            { response ->
                try {
                    val disp = response.optJSONObject("dispositivo")
                    val tempMaxAlerta = disp?.optDouble("temp_max_alerta", -10.0)?.toFloat() ?: -10.0f

                    val arrTemp = response.getJSONArray("lecturas")
                    val nuevasLecturas = mutableListOf<LecturaTemp>()
                    for (i in 0 until arrTemp.length()) {
                        val obj = arrTemp.getJSONObject(i)
                        nuevasLecturas.add(
                            LecturaTemp(
                                temperatura = obj.getString("temperatura"),
                                fecha = obj.optString("fecha_hora", "")
                            )
                        )
                    }
                    listaLecturas = nuevasLecturas

                    if (nuevasLecturas.isNotEmpty()) {
                        tempActual = nuevasLecturas[0].temperatura
                        val valor = tempActual.toFloatOrNull() ?: -15f
                        enAlerta = valor > tempMaxAlerta
                        estadoTexto = if (enAlerta) {
                            "ALERTA: Supera límite (${tempMaxAlerta}°C)"
                        } else {
                            "Estado: Normal (Límite ${tempMaxAlerta}°C)"
                        }
                    } else {
                        estadoTexto = "Conectado (sin lecturas aún)"
                    }

                    val arrAcc = response.getJSONArray("accesos")
                    val nuevosAccesos = mutableListOf<RegistroAcceso>()
                    for (i in 0 until arrAcc.length()) {
                        val obj = arrAcc.getJSONObject(i)
                        nuevosAccesos.add(
                            RegistroAcceso(
                                uid = obj.getString("uid_tarjeta"),
                                permitido = obj.optInt("permitido", 0) == 1,
                                fecha = obj.optString("fecha_hora", "")
                            )
                        )
                    }
                    listaAccesos = nuevosAccesos
                } catch (e: Exception) {
                    estadoTexto = "Error JSON: ${e.message}"
                }
            },
            { error ->
                val tipo = error.javaClass.simpleName
                val codigo = error.networkResponse?.statusCode ?: "sin código"
                estadoTexto = "Fallo: $tipo ($codigo)"
            }
        )

        request.retryPolicy = DefaultRetryPolicy(
            10000,
            DefaultRetryPolicy.DEFAULT_MAX_RETRIES,
            DefaultRetryPolicy.DEFAULT_BACKOFF_MULT
        )

        queue.add(request)
    }

    LaunchedEffect(Unit) {
        while (true) {
            cargarDatos()
            delay(5000)
        }
    }

    Column(
        modifier = Modifier
            .fillMaxSize()
            .padding(16.dp)
    ) {
        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.SpaceBetween,
            verticalAlignment = Alignment.CenterVertically
        ) {
            Column {
                Text(
                    text = "Monitor Cámara Fría",
                    fontSize = 22.sp,
                    fontWeight = FontWeight.Bold,
                    color = Color(0xFF1A237E)
                )
                Text(
                    text = "${usuario.nombre} (${usuario.rol})",
                    fontSize = 13.sp,
                    color = Color.Gray
                )
            }
            OutlinedButton(onClick = onLogout) {
                Text("Salir")
            }
        }

        Spacer(modifier = Modifier.height(12.dp))

        Card(
            modifier = Modifier.fillMaxWidth(),
            shape = RoundedCornerShape(16.dp),
            colors = CardDefaults.cardColors(
                containerColor = if (enAlerta) Color(0xFFD32F2F) else Color(0xFF1565C0)
            )
        ) {
            Column(
                modifier = Modifier.padding(20.dp),
                horizontalAlignment = Alignment.CenterHorizontally
            ) {
                Text(
                    text = "$tempActual °C",
                    fontSize = 44.sp,
                    fontWeight = FontWeight.Bold,
                    color = Color.White
                )
                Spacer(modifier = Modifier.height(4.dp))
                Text(
                    text = estadoTexto,
                    fontSize = 16.sp,
                    color = Color.White
                )
            }
        }

        Spacer(modifier = Modifier.height(12.dp))

        Button(
            onClick = { cargarDatos() },
            modifier = Modifier.fillMaxWidth()
        ) {
            Text("Actualizar ahora")
        }

        Spacer(modifier = Modifier.height(16.dp))

        LazyColumn(modifier = Modifier.fillMaxSize()) {
            item {
                Text(
                    text = "Historial de Temperaturas",
                    fontSize = 18.sp,
                    fontWeight = FontWeight.Bold,
                    modifier = Modifier.padding(vertical = 6.dp)
                )
            }
            items(listaLecturas) { item ->
                Card(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(vertical = 4.dp),
                    colors = CardDefaults.cardColors(containerColor = Color.White)
                ) {
                    Row(
                        modifier = Modifier
                            .fillMaxWidth()
                            .padding(12.dp),
                        horizontalArrangement = Arrangement.SpaceBetween
                    ) {
                        Text("${item.temperatura} °C", fontWeight = FontWeight.Bold)
                        Text(item.fecha, color = Color.Gray, fontSize = 13.sp)
                    }
                }
            }

            item {
                Spacer(modifier = Modifier.height(12.dp))
                Text(
                    text = "Registros de Acceso RFID",
                    fontSize = 18.sp,
                    fontWeight = FontWeight.Bold,
                    modifier = Modifier.padding(vertical = 6.dp)
                )
            }
            items(listaAccesos) { acc ->
                Card(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(vertical = 4.dp),
                    colors = CardDefaults.cardColors(containerColor = Color.White)
                ) {
                    Row(
                        modifier = Modifier
                            .fillMaxWidth()
                            .padding(12.dp),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Column {
                            Text("UID: ${acc.uid}", fontWeight = FontWeight.Bold)
                            Text(acc.fecha, color = Color.Gray, fontSize = 12.sp)
                        }
                        Text(
                            text = if (acc.permitido) "PERMITIDO" else "DENEGADO",
                            color = if (acc.permitido) Color(0xFF2E7D32) else Color(0xFFC62828),
                            fontWeight = FontWeight.Bold
                        )
                    }
                }
            }
        }
    }
}