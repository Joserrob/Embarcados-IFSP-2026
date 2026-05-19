// ============================================================
// ESP32 CYD — Sistema de Monitoramento de Incêndio
// Interface Touch com Slider de Temperatura
//
// Adaptação Estética: FireWatch Web Dashboard (Light Mode)
// ============================================================

#include <WiFi.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>
#include <SPI.h>
#include <TFT_eSPI.h>
#include <XPT2046_Touchscreen.h>

// ============================================================
// CONFIGURAÇÃO DE REDE
// ============================================================

const char* WIFI_SSID     = "Jose";
const char* WIFI_PASSWORD = "brasil2021";
const char* SERVER_URL    = "http://18.229.134.103/ze";
const char* DEVICE_ID     = "esp32-02";

// ============================================================
// CONFIGURAÇÃO DO DISPLAY
// ============================================================

#define SCREEN_WIDTH   320
#define SCREEN_HEIGHT  240

TFT_eSPI tft = TFT_eSPI();

// ============================================================
// PALETA DE CORES — BASEADA NO SITE FIREWATCH
//
// Como a sua tela inverte as cores nativamente, esta macro
// calcula a cor RGB565 e já inverte os bits matematicamente,
// permitindo usar as cores exatas do site sem dor de cabeça.
// ============================================================

#define RGB565(r, g, b) ((((r) & 0xF8) << 8) | (((g) & 0xFC) << 3) | ((b) >> 3))
#define INV_RGB565(r, g, b) RGB565(255-(r), 255-(g), 255-(b))

#define UI_BG        INV_RGB565(244, 241, 234) // Fundo Bege Claro (f4f1ea)
#define UI_CARD      INV_RGB565(255, 255, 255) // Fundo dos Cards (Branco)
#define UI_HEADER    INV_RGB565(40, 40, 40)    // Cinza Escuro do Topo
#define UI_TEXT_DARK INV_RGB565(51, 51, 51)    // Texto Principal
#define UI_TEXT_MID  INV_RGB565(136, 136, 136) // Texto Secundário
#define UI_TEXT_LITE INV_RGB565(255, 255, 255) // Texto Branco
#define UI_RED       INV_RGB565(231, 76, 60)   // Vermelho (Alarme/Emergência)
#define UI_ORANGE    INV_RGB565(230, 126, 34)  // Laranja (Fogo/Temp Normal)
#define UI_GREEN     INV_RGB565(46, 204, 113)  // Verde (Status OK)
#define UI_BLUE      INV_RGB565(52, 152, 219)  // Azul (Sprinklers)
#define UI_BORDER    INV_RGB565(220, 220, 220) // Bordas dos Cards

// ============================================================
// CONFIGURAÇÃO DO TOUCH — ESP32 CYD
// ============================================================

#define XPT2046_IRQ   36
#define XPT2046_MOSI  32
#define XPT2046_MISO  39
#define XPT2046_CLK   25
#define XPT2046_CS    33

SPIClass touchSPI = SPIClass(VSPI);
XPT2046_Touchscreen touch(XPT2046_CS, XPT2046_IRQ);

// ============================================================
// LED RGB EMBARCADO DO ESP32 CYD
// ============================================================

#define LED_RED    4
#define LED_GREEN 16
#define LED_BLUE  17

// ============================================================
// BOTÃO FÍSICO OPCIONAL
// ============================================================

#define PIN_BOOT_BUTTON 0

// ============================================================
// PARÂMETROS DO SISTEMA
// ============================================================

#define TEMP_THRESHOLD 60.0f

const unsigned long POLL_INTERVAL  = 2000;
const unsigned long UI_INTERVAL    = 150;

// ============================================================
// COORDENADAS DA INTERFACE
// ============================================================

// Slider de temperatura (mantido nas coordenadas para o touch)
const int SLIDER_X = 30;
const int SLIDER_Y = 84;   
const int SLIDER_W = 260;
const int SLIDER_H = 18;

// Botão de emergência
const int BTN_EMG_X = 20;
const int BTN_EMG_Y = 178;
const int BTN_EMG_W = 280;
const int BTN_EMG_H = 45;

// ============================================================
// VARIÁVEIS DE ESTADO
// ============================================================

float temperature = 25.0f;

bool alarmActive = false;
bool sprinklerActive = false;
bool blinkState = false;

bool tempAlarmBlocked = false;

bool lastBootButtonState = HIGH;

unsigned long lastPoll  = 0;
unsigned long lastUI    = 0;

float lastDrawnTemperature = -100.0f;
bool lastDrawnAlarm = false;
bool lastDrawnSprinkler = false;
bool lastDrawnBlocked = false;

// ============================================================
// PROTÓTIPOS
// ============================================================

void connectWiFi();

void activateAlarm(const char* source);
void resetAlarmBySite();

void updateSystemLogic();
void updatePhysicalLEDs();

void drawInterface();
void drawHeader();
void drawTemperatureBox();
void drawSlider();
void drawIndicators();
void drawSystemState();
void drawButtons();
void drawWiFiStatus();

void handleTouch();
bool getTouchPoint(int &x, int &y);
bool pointInside(int x, int y, int bx, int by, int bw, int bh);

void reportAndReceive();

// ============================================================
// SETUP
// ============================================================

void setup() {
    Serial.begin(115200);

    pinMode(LED_RED, OUTPUT);
    pinMode(LED_GREEN, OUTPUT);
    pinMode(LED_BLUE, OUTPUT);

    pinMode(PIN_BOOT_BUTTON, INPUT_PULLUP);

    digitalWrite(LED_RED, HIGH);
    digitalWrite(LED_GREEN, LOW);
    digitalWrite(LED_BLUE, HIGH);

    tft.init();
    tft.setRotation(1);
    tft.fillScreen(UI_BG);

    touchSPI.begin(XPT2046_CLK, XPT2046_MISO, XPT2046_MOSI, XPT2046_CS);
    touch.begin(touchSPI);
    touch.setRotation(1);

    drawInterface();

    connectWiFi();
}

// ============================================================
// LOOP PRINCIPAL
// ============================================================

void loop() {
    unsigned long now = millis();

    handleTouch();

    bool bootButtonState = digitalRead(PIN_BOOT_BUTTON);

    if (bootButtonState == LOW && lastBootButtonState == HIGH) {
        activateAlarm("BOTAO FISICO BOOT");
    }

    lastBootButtonState = bootButtonState;

    updateSystemLogic();
    updatePhysicalLEDs();

    if (now - lastUI >= UI_INTERVAL) {
        lastUI = now;

        bool needRedraw = false;

        if (abs(temperature - lastDrawnTemperature) >= 0.2f) {
            needRedraw = true;
        }

        if (alarmActive != lastDrawnAlarm) {
            needRedraw = true;
        }

        if (sprinklerActive != lastDrawnSprinkler) {
            needRedraw = true;
        }

        if (tempAlarmBlocked != lastDrawnBlocked) {
            needRedraw = true;
        }

        if (needRedraw) {
            drawInterface();

            lastDrawnTemperature = temperature;
            lastDrawnAlarm = alarmActive;
            lastDrawnSprinkler = sprinklerActive;
            lastDrawnBlocked = tempAlarmBlocked;
        }
    }

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

// ============================================================
// LÓGICA PRINCIPAL DO SISTEMA
// ============================================================

void updateSystemLogic() {
    if (temperature < TEMP_THRESHOLD) {
        tempAlarmBlocked = false;
    }

    if (temperature >= TEMP_THRESHOLD && !alarmActive && !tempAlarmBlocked) {
        activateAlarm("TEMPERATURA ALTA");
    }

    sprinklerActive = alarmActive;
}

// ============================================================
// ATIVAÇÃO DO ALARME
// ============================================================

void activateAlarm(const char* source) {
    alarmActive = true;
    sprinklerActive = true;
    blinkState = true;
    tempAlarmBlocked = false;

    Serial.print("[ALARME] Ativado por: ");
    Serial.println(source);
}

// ============================================================
// RESET EXCLUSIVO PELO SITE
// ============================================================

void resetAlarmBySite() {
    alarmActive = false;
    sprinklerActive = false;
    blinkState = false;

    if (temperature >= TEMP_THRESHOLD) {
        tempAlarmBlocked = true;
    } else {
        tempAlarmBlocked = false;
    }

    Serial.println("[SITE] Sistema retornou ao estado NORMAL");
}

// ============================================================
// LEDS FÍSICOS
// ============================================================

void updatePhysicalLEDs() {
    if (alarmActive) {
        digitalWrite(LED_GREEN, HIGH);
        digitalWrite(LED_RED, LOW);
        digitalWrite(LED_BLUE, LOW);
    } else {
        digitalWrite(LED_GREEN, LOW);
        digitalWrite(LED_RED, HIGH);
        digitalWrite(LED_BLUE, HIGH);
    }
}

// ============================================================
// INTERFACE GRÁFICA
// ============================================================

void drawInterface() {
    tft.fillScreen(UI_BG);

    drawHeader();
    drawTemperatureBox();
    drawSlider();
    drawIndicators();
    // drawSystemState(); // Mesclado no drawIndicators para melhor estética
    drawButtons();
    drawWiFiStatus();
}

// ============================================================
// CABEÇALHO
// ============================================================

void drawHeader() {
    // Se em alarme, o header inteiro fica vermelho chamando atenção
    uint16_t headerColor = alarmActive ? UI_RED : UI_HEADER;
    tft.fillRect(0, 0, SCREEN_WIDTH, 32, headerColor);

    tft.setTextColor(alarmActive ? UI_TEXT_LITE : UI_ORANGE);
    tft.setTextSize(2);
    tft.setCursor(10, 8);
    tft.print("O"); // Ícone abstrato de chama

    tft.setTextColor(UI_TEXT_LITE);
    tft.setCursor(28, 8);
    tft.print("FireWatch");
}

// ============================================================
// TEMPERATURA (AGORA EM FORMATO DE CARD)
// ============================================================

void drawTemperatureBox() {
    // Fundo do Card
    tft.fillRoundRect(10, 40, 300, 75, 8, UI_CARD);
    tft.drawRoundRect(10, 40, 300, 75, 8, UI_BORDER);

    tft.setTextSize(2);
    tft.setTextColor(UI_TEXT_DARK);
    tft.setCursor(25, 52);
    tft.print("Temperatura");

    tft.setTextSize(3);
    tft.setTextColor(alarmActive ? UI_RED : UI_ORANGE);

    // Alinhamento dinâmico rudimentar à direita
    if (temperature >= 100.0f) {
        tft.setCursor(185, 48);
    } else if (temperature >= 10.0f) {
        tft.setCursor(205, 48);
    } else {
        tft.setCursor(225, 48);
    }

    tft.print(temperature, 1);
    tft.setTextSize(2);
    tft.print(" C");
}

// ============================================================
// SLIDER DE TEMPERATURA
// ============================================================

void drawSlider() {
    uint16_t stateColor = alarmActive ? UI_RED : UI_ORANGE;

    // Fundo do trilho
    tft.fillRoundRect(SLIDER_X, SLIDER_Y, SLIDER_W, SLIDER_H, 8, UI_BORDER);

    float ratio = temperature / 100.0f;
    ratio = constrain(ratio, 0.0f, 1.0f);
    int filledWidth = ratio * SLIDER_W;

    // Preenchimento ativo
    if (filledWidth > 6) {
        tft.fillRoundRect(SLIDER_X, SLIDER_Y, filledWidth, SLIDER_H, 8, stateColor);
    }

    // Linha de limite crítico (Threshold)
    int thresholdX = SLIDER_X + (TEMP_THRESHOLD / 100.0f) * SLIDER_W;
    tft.drawLine(thresholdX, SLIDER_Y - 4, thresholdX, SLIDER_Y + SLIDER_H + 4, UI_RED);

    // Botão do Slider (Knob)
    int knobX = SLIDER_X + filledWidth;
    knobX = constrain(knobX, SLIDER_X + 6, SLIDER_X + SLIDER_W - 6);

    tft.fillCircle(knobX, SLIDER_Y + SLIDER_H / 2, 9, UI_CARD);
    tft.drawCircle(knobX, SLIDER_Y + SLIDER_H / 2, 9, UI_BORDER);
    tft.fillCircle(knobX, SLIDER_Y + SLIDER_H / 2, 4, stateColor);
}

// ============================================================
// INDICADORES DE ESTADO (AGORA EM FORMATO DE CARD)
// ============================================================

void drawIndicators() {
    tft.fillRoundRect(10, 122, 300, 45, 8, UI_CARD);
    tft.drawRoundRect(10, 122, 300, 45, 8, UI_BORDER);

    int yCircle = 144;
    int yText = 140;

    tft.setTextSize(1);
    tft.setTextColor(UI_TEXT_DARK);

    // 1. Status Geral / Alarme
    uint16_t colorAlarme = alarmActive ? UI_RED : UI_BORDER;
    tft.fillCircle(25, yCircle, 6, colorAlarme);
    tft.setCursor(38, yText);
    tft.print(alarmActive ? "EM ALARME" : "Normal");

    // 2. Status Sprinklers
    uint16_t colorSprink = sprinklerActive ? UI_BLUE : UI_BORDER;
    tft.fillCircle(135, yCircle, 6, colorSprink);
    tft.setCursor(148, yText);
    tft.print(sprinklerActive ? "Sprinklers ON" : "Sprinklers OFF");

    // 3. Status Bloqueio
    if (tempAlarmBlocked) {
        tft.setTextColor(UI_ORANGE);
        tft.setCursor(245, yText);
        tft.print("Bloqueado");
    }
}

// ============================================================
// ESTADO ATUAL DO SISTEMA (Substituído pela lógica acima)
// ============================================================

void drawSystemState() {
    // Deixado vazio de propósito. As informações foram
    // mescladas no drawIndicators() para melhor estética.
}

// ============================================================
// BOTÃO DE EMERGÊNCIA (ESTILO WEB UI)
// ============================================================

void drawButtons() {
    uint16_t btnColor    = alarmActive ? UI_RED : UI_CARD;
    uint16_t txtColor    = alarmActive ? UI_TEXT_LITE : UI_RED;
    uint16_t borderColor = UI_RED;

    tft.fillRoundRect(BTN_EMG_X, BTN_EMG_Y, BTN_EMG_W, BTN_EMG_H, 8, btnColor);
    tft.drawRoundRect(BTN_EMG_X, BTN_EMG_Y, BTN_EMG_W, BTN_EMG_H, 8, borderColor);

    tft.setTextSize(2);
    tft.setTextColor(txtColor);

    if (alarmActive) {
        tft.setCursor(BTN_EMG_X + 40, BTN_EMG_Y + 15);
        tft.print("EMERGENCIA ATIVA");
    } else {
        tft.setCursor(BTN_EMG_X + 50, BTN_EMG_Y + 15);
        tft.print("FORCAR ALARME");
    }
}

// ============================================================
// STATUS DO WIFI
// ============================================================

void drawWiFiStatus() {
    tft.setTextSize(1);

    if (WiFi.status() == WL_CONNECTED) {
        tft.setTextColor(UI_GREEN);
        tft.setCursor(255, 12);
        tft.print("WiFi OK");
    } else {
        tft.setTextColor(UI_TEXT_MID);
        tft.setCursor(250, 12);
        tft.print("Offline");
    }
}

// ============================================================
// TOUCHSCREEN
// ============================================================

void handleTouch() {
    int x, y;

    if (!getTouchPoint(x, y)) {
        return;
    }

    if (
        x >= SLIDER_X &&
        x <= SLIDER_X + SLIDER_W &&
        y >= SLIDER_Y - 18 &&
        y <= SLIDER_Y + SLIDER_H + 18
    ) {
        float ratio = (float)(x - SLIDER_X) / (float)SLIDER_W;

        if (ratio < 0.0f) {
            ratio = 0.0f;
        }

        if (ratio > 1.0f) {
            ratio = 1.0f;
        }

        temperature = ratio * 100.0f;

        Serial.print("[SLIDER] Temperatura ajustada: ");
        Serial.println(temperature, 1);

        delay(80);
        return;
    }

    if (pointInside(x, y, BTN_EMG_X, BTN_EMG_Y, BTN_EMG_W, BTN_EMG_H)) {
        activateAlarm("BOTAO TOUCH EMERGENCIA");
        delay(250);
        return;
    }
}

// ============================================================
// LEITURA DO TOUCH
// ============================================================

bool getTouchPoint(int &x, int &y) {
    if (!touch.touched()) {
        return false;
    }

    TS_Point p = touch.getPoint();

    x = map(p.x, 200, 3700, 0, SCREEN_WIDTH);
    y = map(p.y, 240, 3800, 0, SCREEN_HEIGHT);

    x = constrain(x, 0, SCREEN_WIDTH - 1);
    y = constrain(y, 0, SCREEN_HEIGHT - 1);

    return true;
}

// ============================================================
// VERIFICA SE O TOQUE ESTÁ DENTRO DE UMA ÁREA
// ============================================================

bool pointInside(int x, int y, int bx, int by, int bw, int bh) {
    return (
        x >= bx &&
        x <= bx + bw &&
        y >= by &&
        y <= by + bh
    );
}

// ============================================================
// COMUNICAÇÃO HTTP COM O SITE
// ============================================================

void reportAndReceive() {
    HTTPClient http;

    String url = String(SERVER_URL) + "/api/device.php";

    http.begin(url);
    http.addHeader("Content-Type", "application/json");

    StaticJsonDocument<256> payload;

    payload["device_id"]   = DEVICE_ID;
    payload["temperature"] = (int)round(temperature);
    payload["alarm"]       = alarmActive ? 1 : 0;
    payload["sprinkler"]   = sprinklerActive ? 1 : 0;
    payload["button"]      = 0;
    payload["led"]         = alarmActive ? 1 : 0;
    payload["normal"]      = alarmActive ? 0 : 1;

    String body;
    serializeJson(payload, body);

    Serial.print("[HTTP] POST -> ");
    Serial.println(body);

    int httpCode = http.POST(body);

    if (httpCode == HTTP_CODE_OK) {
        String response = http.getString();

        Serial.print("[HTTP] Resposta -> ");
        Serial.println(response);

        StaticJsonDocument<512> doc;
        DeserializationError error = deserializeJson(doc, response);

        if (!error && doc.containsKey("commands")) {
            for (JsonObject cmd : doc["commands"].as<JsonArray>()) {
                const char* command = cmd["command"];
                int value = cmd["value"];

                Serial.printf("[CMD SITE] %s = %d\n", command, value);

                if (strcmp(command, "reset") == 0 && value == 1) {
                    resetAlarmBySite();
                }

                if (strcmp(command, "normal") == 0 && value == 1) {
                    resetAlarmBySite();
                }

                if (strcmp(command, "alarm") == 0 && value == 0) {
                    resetAlarmBySite();
                }

                if (strcmp(command, "alarm") == 0 && value == 1) {
                    activateAlarm("SITE");
                }
            }
        } else {
            Serial.println("[JSON] Resposta sem comandos ou JSON invalido");
        }
    } else {
        Serial.printf("[HTTP] Erro: %d\n", httpCode);
    }

    http.end();
}

// ============================================================
// CONEXÃO WIFI
// ============================================================

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
    } else {
        Serial.println("\n[WiFi] Falha — operando offline");
    }
}