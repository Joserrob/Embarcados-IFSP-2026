// ============================================================
// esp32_incendio.ino — Sistema de Monitoramento de Incêndio
//
// Bibliotecas necessárias:
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
const char* DEVICE_ID     = "esp32-01";

// ── Pinos ─────────────────────────────────────────────────────
#define PIN_POT        34
#define PIN_BTN        15
#define PIN_LED_GREEN  17
#define PIN_LED_ALARM   5
#define PIN_LED_SPR    12

// ── OLED ──────────────────────────────────────────────────────
#define OLED_SDA       22
#define OLED_SCL       23
#define SCREEN_WIDTH  128
#define SCREEN_HEIGHT  64
#define OLED_ADDR    0x3C

Adafruit_SSD1306 display(SCREEN_WIDTH, SCREEN_HEIGHT, &Wire, -1);

// ── Limiar de temperatura ─────────────────────────────────────
#define TEMP_THRESHOLD 60.0f

// ── Intervalos em milissegundos ────────────────────────────────
const unsigned long POLL_INTERVAL  = 2000;
const unsigned long BLINK_INTERVAL = 200;
const unsigned long OLED_INTERVAL  = 400;

// ── Estado do sistema ─────────────────────────────────────────
bool  alarmActive = false;   // Alarme travado
bool  blinkState  = false;   // Estado do pisca do LED Alarm
float temperature = 0.0f;

int lastBtnState = HIGH;

unsigned long lastPoll  = 0;
unsigned long lastBlink = 0;
unsigned long lastOLED  = 0;

// ── Protótipos ────────────────────────────────────────────────
void  connectWiFi();
float readTemperature();
void  activateAlarm(const char* source);
void  resetAlarmBySupervisor();
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
        display.setCursor(0, 16);
        display.println("  Monit. Incendio");
        display.setCursor(0, 32);
        display.println("   Iniciando...");
        display.display();
    }

    connectWiFi();
}

// =============================================================
// LOOP PRINCIPAL
// =============================================================
void loop() {
    unsigned long now = millis();

    // ── 1. Leitura da temperatura ─────────────────────────────
    temperature = readTemperature();

    // ── 2. Botão físico de emergência ─────────────────────────
    int btnState = digitalRead(PIN_BTN);

    if (btnState == LOW && lastBtnState == HIGH) {
        activateAlarm("BOTAO FISICO");
    }

    lastBtnState = btnState;

    // ── 3. Ativação automática por temperatura ────────────────
    /*
       Correção principal:

       Antes:
       - O sistema ativava o alarme acima de 60 °C.
       - Porém, desligava automaticamente abaixo de 60 °C.
       - Próximo ao limite, a leitura analógica oscila.
       - Isso fazia LED Green e LED Alarm alternarem rapidamente.

       Agora:
       - Temperatura >= 60 °C ativa o alarme.
       - O alarme permanece ativo.
       - Só o site/supervisório pode voltar o estado para normal.
    */
    if (temperature >= TEMP_THRESHOLD && !alarmActive) {
        activateAlarm("TEMPERATURA ALTA");
    }

    // ── 4. Pisca do LED de alarme ─────────────────────────────
    if (alarmActive && now - lastBlink >= BLINK_INTERVAL) {
        lastBlink = now;
        blinkState = !blinkState;
    }

    // ── 5. Atualiza saídas físicas ────────────────────────────
    updateOutputs();

    // ── 6. Atualiza OLED ──────────────────────────────────────
    if (now - lastOLED >= OLED_INTERVAL) {
        lastOLED = now;
        updateOLED();
    }

    // ── 7. Comunicação com o servidor ─────────────────────────
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

    /*
       Conversão simulada:
       ADC ESP32: 0 a 4095
       Temperatura simulada: 0 a 100 °C
    */
    float temp = (raw / 4095.0f) * 100.0f;

    return temp;
}

void activateAlarm(const char* source) {
    alarmActive = true;
    blinkState  = true;
    lastBlink   = millis();

    digitalWrite(PIN_LED_GREEN, LOW);
    digitalWrite(PIN_LED_ALARM, HIGH);
    digitalWrite(PIN_LED_SPR, HIGH);

    Serial.print("[ALARME] Ativado por: ");
    Serial.println(source);
}

void resetAlarmBySupervisor() {
    /*
       Esta função representa o retorno ao estado normal
       somente por comando vindo do site/supervisório.
    */

    alarmActive = false;
    blinkState  = false;

    digitalWrite(PIN_LED_ALARM, LOW);
    digitalWrite(PIN_LED_SPR, LOW);
    digitalWrite(PIN_LED_GREEN, HIGH);

    Serial.println("[SUPERVISORIO] Sistema retornou ao estado NORMAL");
}

void updateOutputs() {
    if (alarmActive) {
        // Em alarme:
        // LED Green apagado
        // LED Alarm piscando
        // Sprinkler ativo
        digitalWrite(PIN_LED_GREEN, LOW);
        digitalWrite(PIN_LED_ALARM, blinkState ? HIGH : LOW);
        digitalWrite(PIN_LED_SPR, HIGH);
    } else {
        // Estado normal:
        // LED Green ligado
        // LED Alarm apagado
        // Sprinkler desligado
        digitalWrite(PIN_LED_GREEN, HIGH);
        digitalWrite(PIN_LED_ALARM, LOW);
        digitalWrite(PIN_LED_SPR, LOW);
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
    if (alarmActive) {
        display.println("RISCO INCENDIO!");
    } else {
        display.println("Normal");
    }

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

    Serial.print("[HTTP] POST → ");
    Serial.println(body);

    int httpCode = http.POST(body);

    if (httpCode == HTTP_CODE_OK) {
        String response = http.getString();

        Serial.print("[HTTP] Resposta → ");
        Serial.println(response);

        StaticJsonDocument<512> doc;
        DeserializationError error = deserializeJson(doc, response);

        if (!error && doc.containsKey("commands")) {
            for (JsonObject cmd : doc["commands"].as<JsonArray>()) {
                const char* command = cmd["command"];
                int value = cmd["value"];

                Serial.printf("[CMD] %s = %d\n", command, value);

                // Comando 1: reset vindo do site
                if (strcmp(command, "reset") == 0 && value == 1) {
                    resetAlarmBySupervisor();
                }

                // Comando 2: normal vindo do site
                if (strcmp(command, "normal") == 0 && value == 1) {
                    resetAlarmBySupervisor();
                }

                // Comando 3: alarm = 0 vindo do site
                if (strcmp(command, "alarm") == 0 && value == 0) {
                    resetAlarmBySupervisor();
                }

                // Comando 4: alarm = 1 vindo do site
                if (strcmp(command, "alarm") == 0 && value == 1) {
                    activateAlarm("SUPERVISORIO");
                }
            }
        } else {
            Serial.println("[JSON] Erro ao interpretar resposta ou sem comandos");
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

        // Pisca inicial apenas se o sistema ainda não estiver em alarme
        if (!alarmActive) {
            for (int i = 0; i < 3; i++) {
                digitalWrite(PIN_LED_GREEN, LOW);
                delay(120);
                digitalWrite(PIN_LED_GREEN, HIGH);
                delay(120);
            }
        }
    } else {
        Serial.println("\n[WiFi] Falha — operando offline");
    }
}

// ============================================================
// RESUMO DA LÓGICA CORRIGIDA
// ─────────────────────────────────────────────────────────────
//
//  Temperatura >= 60 °C:
//    - Ativa o alarme.
//    - Apaga o LED Green.
//    - Pisca o LED Alarm.
//    - Liga o sprinkler.
//    - O alarme fica travado.
//
//  Temperatura < 60 °C:
//    - Não desliga o alarme automaticamente.
//
//  Retorno ao estado normal:
//    - Apenas pelo site/supervisório.
//    - Comandos aceitos:
//        reset  = 1
//        normal = 1
//        alarm  = 0
//
// ============================================================