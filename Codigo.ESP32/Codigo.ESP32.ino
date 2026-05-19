// ============================================================
// esp32_incendio.ino — Sistema de Monitoramento de Incêndio
//
// Bibliotecas necessárias (instale pelo Library Manager):
//   - WiFi, HTTPClient   (embutidas no ESP32)
//   - ArduinoJson        (by Benoit Blanchon) >= 6.x
//   - Wire               (embutida)
//   - Adafruit GFX Library
//   - Adafruit SSD1306
// ============================================================

#include <WiFi.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>
#include <Wire.h>
#include <Adafruit_GFX.h>
#include <Adafruit_SSD1306.h>

// ── Configuração ──────────────────────────────────────────────
const char* WIFI_SSID     = "Jose";
const char* WIFI_PASSWORD = "brasil2021";
const char* SERVER_URL    = "http://18.229.134.103/ze";
const char* DEVICE_ID     = "esp32-02";

// ── Pinos ─────────────────────────────────────────────────────
#define PIN_POT        34
#define PIN_BTN        15
#define PIN_LED_GREEN  18
#define PIN_LED_ALARM   5
#define PIN_LED_SPR    12

// ── OLED ──────────────────────────────────────────────────────
#define OLED_SDA      22
#define OLED_SCL      23
#define SCREEN_WIDTH  128
#define SCREEN_HEIGHT  64
#define OLED_ADDR    0x3C

Adafruit_SSD1306 display(SCREEN_WIDTH, SCREEN_HEIGHT, &Wire, -1);

// ── Limiar ────────────────────────────────────────────────────
#define TEMP_THRESHOLD 60.0f

// ── Intervalos (ms) ───────────────────────────────────────────
const unsigned long POLL_INTERVAL  = 2000;
const unsigned long BLINK_INTERVAL =  100;
const unsigned long OLED_INTERVAL  =  250;

// ── Estado do sistema ─────────────────────────────────────────

bool  alarmActive  = false;   // estado atual do alarme
bool  blinkState   = false;   // controla o piscar do LED de alarme
float temperature  = 0.0f;

// Alarme persistente — ativado pelo botão físico ou pelo supervisório.
// Não desliga automaticamente com a temperatura: requer reset do supervisório.
bool buttonAlarm = false;

// Supressão de re-ativação por temperatura após reset do supervisório.
// Evita que a temp alta imediatamente re-acione o alarme por SUPPRESS_MS ms.

bool tempAlarmBlocked = false;

int  lastBtnState = HIGH;

unsigned long lastPoll  = 0;
unsigned long lastBlink = 0;
unsigned long lastOLED  = 0;

// ── Protótipos ────────────────────────────────────────────────
void  connectWiFi();
float readTemperature();
void  updateOutputs();
void  updateOLED();
void  reportAndReceive();

// =============================================================
// SETUP
// =============================================================
void setup() {
    Serial.begin(115200);

    pinMode(PIN_LED_GREEN, OUTPUT);
    pinMode(PIN_LED_ALARM, OUTPUT);
    pinMode(PIN_LED_SPR,   OUTPUT);
    pinMode(PIN_BTN, INPUT_PULLUP);

    digitalWrite(PIN_LED_GREEN, HIGH);
    digitalWrite(PIN_LED_ALARM, LOW);
    digitalWrite(PIN_LED_SPR,   LOW);

    Wire.begin(OLED_SDA, OLED_SCL);
    if (!display.begin(SSD1306_SWITCHCAPVCC, OLED_ADDR)) {
        Serial.println("[OLED] Não encontrado!");
    } else {
        display.clearDisplay();
        display.setTextColor(SSD1306_WHITE);
        display.setTextSize(1);
        display.setCursor(0, 16); display.println("  Monit. Incendio");
        display.setCursor(0, 32); display.println("   Iniciando...");
        display.display();
    }

    connectWiFi();
}

// =============================================================
// LOOP PRINCIPAL
// =============================================================
void loop() {
    unsigned long now = millis();

    // ── 1. Lê temperatura ─────────────────────────────────────
    temperature = readTemperature();

    // ── 2. Botão de emergência (borda de descida) ─────────────
    int btnState = digitalRead(PIN_BTN);
    if (btnState == LOW && lastBtnState == HIGH) {
        Serial.println("[BTN] Emergência pressionada!");
        buttonAlarm = true;   // alarme persistente — só reset do supervisório desliga
        alarmActive = true;
    }
    lastBtnState = btnState;

    // ── 3. Lógica de ativação/desativação do alarme ───────────
    //
    // ATIVA: temperatura >= limiar E janela de supressão expirou
    // DESATIVA automaticamente: temperatura < limiar E nenhum alarme persistente
    //
    // Alarme persistente (buttonAlarm) NÃO desliga com a temperatura —
    // exige reset explícito do supervisório.
    //
    if (temperature < TEMP_THRESHOLD) {
        tempAlarmBlocked = false;
    }

    // Ativação automática por temperatura
    if (temperature >= TEMP_THRESHOLD && !tempAlarmBlocked) {
        if (!alarmActive) {
            Serial.printf("[TEMP] Limiar atingido: %.1f °C → Alarme!\n", temperature);
        }
        alarmActive = true;
    }

    // Desativação automática
    else if (temperature < TEMP_THRESHOLD && !buttonAlarm) {
        if (alarmActive) {
            Serial.printf("[TEMP] Temperatura normalizada: %.1f °C → Sistema Normal\n", temperature);
        }
        alarmActive = false;
    }
    // Se temperatura < limiar mas buttonAlarm=true → alarmActive permanece true
    // Se temperatura >= limiar mas now < suppressUntil → alarmActive permanece false

    // ── 4. Piscar do LED de alarme ────────────────────────────
    if (alarmActive && now - lastBlink >= BLINK_INTERVAL) {
        lastBlink  = now;
        blinkState = !blinkState;
        digitalWrite(PIN_LED_ALARM, blinkState ? HIGH : LOW);
    }

    // ── 5. Saídas físicas ─────────────────────────────────────
    updateOutputs();

    // ── 6. OLED ───────────────────────────────────────────────
    if (now - lastOLED >= OLED_INTERVAL) {
        lastOLED = now;
        updateOLED();
    }

    // ── 7. Polling com servidor ───────────────────────────────
    if (now - lastPoll >= POLL_INTERVAL) {
        lastPoll = now;
        if (WiFi.status() == WL_CONNECTED) {
            reportAndReceive();
        } else {
            Serial.println("[WiFi] Desconectado — reconectando...");
            connectWiFi();
        }
    }
}

// =============================================================
// FUNÇÕES AUXILIARES
// =============================================================

float readTemperature() {
    int raw = analogRead(PIN_POT);
    return (raw / 4095.0f) * 100.0f;
}

void updateOutputs() {
    if (alarmActive) {
        digitalWrite(PIN_LED_GREEN, LOW);
        digitalWrite(PIN_LED_SPR,   HIGH);
    } else {
        blinkState = false;
        digitalWrite(PIN_LED_ALARM, LOW);
        digitalWrite(PIN_LED_SPR,   LOW);
        digitalWrite(PIN_LED_GREEN, HIGH);
    }
}

void updateOLED() {
    display.clearDisplay();
    display.setTextSize(1);

    display.setCursor(0, 0);
    display.print("Temp: ");
    display.print(temperature, 1);
    display.println(" C");

    display.drawLine(0, 12, SCREEN_WIDTH - 1, 12, SSD1306_WHITE);

    display.setCursor(0, 17);
    display.println("Estado:");
    display.setCursor(0, 29);
    display.println(alarmActive ? "RISCO INCENDIO!" : "Normal");

    display.drawLine(0, 42, SCREEN_WIDTH - 1, 42, SSD1306_WHITE);

    display.setCursor(0, 47);
    display.print("Sprinkler: ");
    display.println(alarmActive ? "ATIVO" : "Inativo");

    display.display();
}

void reportAndReceive() {
    HTTPClient http;
    String url = String(SERVER_URL) + "/api/device.php";
    http.begin(url);
    http.addHeader("Content-Type", "application/json");

    StaticJsonDocument<256> payload;
    payload["device_id"]   = DEVICE_ID;
    payload["button"]      = (lastBtnState == LOW) ? 1 : 0;
    payload["led"]         = alarmActive ? 1 : 0;
    payload["temperature"] = (int) round(temperature);
    payload["alarm"]       = alarmActive ? 1 : 0;
    payload["sprinkler"]   = alarmActive ? 1 : 0;

    String body;
    serializeJson(payload, body);
    Serial.print("[HTTP] POST → "); Serial.println(body);

    int httpCode = http.POST(body);

    if (httpCode == HTTP_CODE_OK) {
        String response = http.getString();
        Serial.print("[HTTP] Resposta → "); Serial.println(response);

        StaticJsonDocument<512> doc;
        if (!deserializeJson(doc, response) && doc.containsKey("commands")) {
            for (JsonObject cmd : doc["commands"].as<JsonArray>()) {
                const char* command = cmd["command"];
                int         value   = cmd["value"];
                Serial.printf("[CMD] %s = %d\n", command, value);

                if (strcmp(command, "reset") == 0 && value == 1) {
                    alarmActive = false;
                    buttonAlarm = false;
                    blinkState  = false;

                    // Bloqueia novo alarme automático somente enquanto a temperatura ainda estiver alta
                    if (temperature >= TEMP_THRESHOLD) {
                        tempAlarmBlocked = true;
                    }

                    Serial.println("[CMD] Reset executado");
                }

                if (strcmp(command, "alarm") == 0 && value == 1) {
                    // Forçar alarme do supervisório → alarme persistente
                    alarmActive = true;
                    buttonAlarm = true;
                    Serial.println("[CMD] Alarme forçado pelo supervisório");
                }
            }
        }
    } else {
        Serial.printf("[HTTP] Erro: %d\n", httpCode);
    }

    http.end();
}

void connectWiFi() {
    Serial.print("[WiFi] Conectando");
    WiFi.begin(WIFI_SSID, WIFI_PASSWORD);

    int attempts = 0;
    while (WiFi.status() != WL_CONNECTED && attempts < 20) {
        delay(500);
        Serial.print(".");
        attempts++;
    }

    if (WiFi.status() == WL_CONNECTED) {
        Serial.print("\n[WiFi] Conectado! IP: ");
        Serial.println(WiFi.localIP());
        for (int i = 0; i < 3; i++) {
            digitalWrite(PIN_LED_GREEN, LOW);  delay(120);
            digitalWrite(PIN_LED_GREEN, HIGH); delay(120);
        }
    } else {
        Serial.println("\n[WiFi] Falha — operando offline");
    }
}

// ============================================================
// RESUMO DA LÓGICA DE ALARME
// ─────────────────────────────────────────────────────────────
//  ATIVA automaticamente:
//    temperatura >= 60 °C E janela de supressão expirada
//
//  DESATIVA automaticamente:
//    temperatura < 60 °C E !buttonAlarm
//
//  PERSISTE (não desliga com temperatura):
//    buttonAlarm = true (ativado pelo botão físico ou pelo
//    comando "alarm" do supervisório)
//
//  RESET do supervisório:
//    Limpa buttonAlarm + suprime re-ativação por 60 s
//    (útil quando temperatura ainda está alta)
// ============================================================
