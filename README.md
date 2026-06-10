# KNX Device Tools für IP-Symcon

[![IP-Symcon](https://img.shields.io/badge/IP--Symcon-9.0-blue.svg)](https://www.symcon.de)
![License](https://img.shields.io/badge/license-Apache--2.0-blue.svg)

## Inhalt

- [Beschreibung](#beschreibung)
- [Voraussetzungen](#voraussetzungen)
- [Module](#module)
  - [KNX Device Tick Monitor](#knx-device-tick-monitor)
  - [KNX Device Watcher](#knx-device-watcher)
  - [KNX Device Trigger](#knx-device-trigger)
  - [KNX Traffic Logger](#knx-traffic-logger)
- [Installation](#installation)
- [Konfiguration](#konfiguration)
- [DPT-Unterstützung](#dpt-unterstützung)
- [Dateibasierte Protokollierung](#dateibasierte-protokollierung)
- [Debugging](#debugging)
- [Hinweise und Grenzen](#hinweise-und-grenzen)
- [Versionshinweise](#versionshinweise)
- [Lizenz](#lizenz)
- [Danksagung](#danksagung)

---

## Beschreibung

**KNX Device Tools** ist eine Modulsammlung für IP-Symcon zur Auswertung von KNX-Telegrammen. Die Module können Telegramme nach Gruppenadresse (GA) und/oder physikalischer Geräteadresse (PA) filtern, daraus Variablen erzeugen, Werte übernehmen, Trigger an eigene Module weiterreichen oder den KNX-Telegrammverkehr in Dateien protokollieren.

Der praktische Nutzen liegt insbesondere darin, bei KNX-Telegrammen nicht nur die Gruppenadresse, sondern auch den tatsächlichen Absender über die physikalische Geräteadresse auswerten zu können. Dadurch kann zum Beispiel erkannt werden, welcher Taster einen Befehl gesendet hat, obwohl mehrere Taster dieselbe Gruppenadresse verwenden.

Typische Anwendungsfälle:

- Erkennen, welcher KNX-Taster eine Aktion ausgelöst hat.
- Kurzzeit-Tick für Automationen erzeugen.
- KNX-Werte aus Telegrammen in Symcon-Variablen schreiben.
- Eigene Module auf KNX-Telegramme reagieren lassen.
- KNX-Verkehr für Analyse, Fehlersuche oder externe Auswertung protokollieren.

---

## Voraussetzungen

- IP-Symcon ab Version **9.0**.
- Eine funktionsfähige KNX-Gateway-/KNX-Parent-Instanz in IP-Symcon.
- KNX-Telegramme müssen vom Parent an die Modulinstanz weitergereicht werden.
- Für die Auswertung der physikalischen Geräteadresse ist eine Symcon-Version erforderlich, die diese Telegramminformationen bereitstellt.

Hinweis zur technischen Mindestversion:

Die Module verwenden `IPSModuleStrict`. Intern wird zusätzlich berücksichtigt, dass ältere Symcon-Versionen vor 8.2 bestimmte Verbindungsmechanismen anders behandeln. Für die Veröffentlichung und reguläre Nutzung dieses Pakets ist jedoch IP-Symcon **9.0 oder neuer** vorgesehen.

---

## Module

### KNX Device Tick Monitor

Der **KNX Device Tick Monitor** überwacht definierte Kombinationen aus Gruppenadresse und/oder Geräteadresse. Wird ein passendes Telegramm erkannt, setzt das Modul die zugehörige Boolean-Variable kurzzeitig auf `true`. Nach Ablauf der konfigurierten Tick-Länge wird die Variable wieder auf `false` gesetzt.

#### Einsatzbereich

Dieses Modul eignet sich für einfache Ereignis- und Automationslogik, bei der ein Telegramm nur als kurzer Impuls benötigt wird.

Beispiele:

- Tasterdruck erkennen.
- Automatik für eine bestimmte Zeit unterbrechen.
- Ereignisse in Symcon auslösen.
- Telegramme bestimmter Geräte sichtbar machen.

#### Einstellungen

| Einstellung | Beschreibung |
|---|---|
| Modul aktiv | Aktiviert oder deaktiviert die Auswertung. |
| Tick-Länge | Dauer in Millisekunden, für die die Variable auf `true` gesetzt bleibt. |
| Filterliste | Liste aus Name, Gruppenadresse und Geräteadresse. |

#### Filterlogik

- Wird eine GA angegeben, muss die Telegramm-GA übereinstimmen.
- Wird eine PA angegeben, muss die Telegramm-PA übereinstimmen.
- Sind GA und PA angegeben, müssen beide übereinstimmen.
- Einträge ohne GA und ohne PA werden ignoriert.

#### Erzeugte Variablen

Für jeden gültigen Listeneintrag wird unterhalb der Instanz eine Boolean-Variable erzeugt. Der Ident wird aus GA und PA gebildet.

Beispiel:

```text
GA: 1/1/16
PA: 1.1.101
Ident: KNX_1_1_16_1_1_101
```

---

### KNX Device Watcher

Der **KNX Device Watcher** überwacht KNX-Telegramme und schreibt den empfangenen bzw. dekodierten Wert direkt in eine automatisch erzeugte Symcon-Variable.

#### Einsatzbereich

Dieses Modul eignet sich, wenn Werte aus dem KNX-Telegrammfluss zusätzlich sichtbar oder weiterverarbeitbar gemacht werden sollen.

Beispiele:

- Statuswerte aus Telegrammen mitschreiben.
- KNX-Werte aus bestimmten Absendern getrennt erfassen.
- Telegramminhalte für Diagnosezwecke sichtbar machen.

#### Einstellungen

| Einstellung | Beschreibung |
|---|---|
| Modul aktiv | Aktiviert oder deaktiviert die Auswertung. |
| Debug-Ausgaben | Aktiviert zusätzliche Debug-Ausgaben im Modul-Debug. |
| Watchlist | Liste aus Gruppenadresse, Geräteadresse und DPT. |

#### Erzeugte Variablen

Das Modul erzeugt abhängig vom gewählten DPT automatisch Boolean-, Integer-, Float- oder String-Variablen.

---

### KNX Device Trigger

Der **KNX Device Trigger** wertet Telegramme nach GA und/oder PA aus und kann beim Treffer eine konfigurierte Funktion einer ausgewählten Instanz aufrufen.

#### Einsatzbereich

Das Modul richtet sich vor allem an Anwender, die eigene IP-Symcon-Module entwickeln und KNX-Telegramme direkt an eigene Modulmethoden weitergeben möchten, ohne zuvor Hilfsvariablen anzulegen.

Beispiele:

- Eigenes Raffstore-/Beschattungsmodul auf manuelle KNX-Taster reagieren lassen.
- Eigene Logikmodule direkt über Telegramme anstoßen.
- Telegrammabhängige Funktionen in eigenen Modulinstanzen aufrufen.

#### Einstellungen

| Einstellung | Beschreibung |
|---|---|
| Modul aktiv | Aktiviert oder deaktiviert die Auswertung. |
| Gruppenadresse | Optionaler Filter auf die Gruppenadresse. |
| Geräteadresse | Optionaler Filter auf die physikalische Geräteadresse. |
| Modul auswählen | Zielinstanz, deren Funktion aufgerufen werden soll. |
| Funktionsname | Funktionsname ohne Klammern. |

#### Sicherheitshinweis

Der Trigger ist für den gezielten Aufruf eigener, dafür vorgesehener Modulmethoden gedacht. Die Zielinstanz und der Funktionsname müssen bewusst konfiguriert werden. Es wird empfohlen, nur eigene Module und klar definierte Funktionen zu verwenden.

---

### KNX Traffic Logger

Der **KNX Traffic Logger** protokolliert empfangene KNX-Telegramme in JSONL-Dateien. Jede Zeile enthält ein Telegramm bzw. einen Systemeintrag.

#### Einsatzbereich

Das Modul eignet sich für:

- Fehlersuche im KNX-Bus.
- Nachträgliche Analyse von Telegrammen.
- Langzeitbeobachtung des KNX-Verkehrs.
- Weiterverarbeitung mit externen Tools wie Python, Grafana, ELK/Opensearch oder anderen Logsystemen.

#### Einstellungen

| Einstellung | Beschreibung |
|---|---|
| Modul aktiv | Aktiviert oder deaktiviert die Protokollierung. |
| Maximale Logdateien | Anzahl der aufzubewahrenden Logdateien. |
| Maximale Zeilen pro Datei | Sicherheitsgrenze pro Datei. Bei Erreichen wird die aktuelle Datei gestoppt. |
| Rotationsstunde | Uhrzeit, zu der täglich eine neue Datei begonnen wird. |
| Logpfad | Zielordner für die JSONL-Dateien. |

#### Standardpfad

Standardmäßig wird innerhalb des Symcon-Kernelverzeichnisses unter `logs/knx/` geschrieben.

---

## Installation

### Installation über den Modul-Store

1. Modul-Store in IP-Symcon öffnen.
2. Nach **KNX Device Tools** suchen.
3. Modul installieren.
4. Gewünschte Instanz anlegen.
5. KNX-Parent/Gateway auswählen bzw. verbinden.
6. Modul konfigurieren und aktivieren.

### Manuelle Installation

1. Repository als Modul in IP-Symcon einbinden.
2. IP-Symcon neu laden bzw. Modulverwaltung aktualisieren.
3. Eine der enthaltenen Modulinstanzen anlegen.
4. KNX-Parent verbinden.
5. Einstellungen vornehmen.

---

## Konfiguration

### Adressformate

| Typ | Format | Beispiel |
|---|---|---|
| Gruppenadresse | `x/x/x` | `1/1/16` |
| Geräteadresse | `x.x.x` | `1.1.101` |

### Filterverhalten

Die Module vergleichen die aus dem Telegramm gelesenen Adressen mit den konfigurierten Filtern.

| GA konfiguriert | PA konfiguriert | Verhalten |
|---|---|---|
| ja | nein | Treffer bei passender Gruppenadresse. |
| nein | ja | Treffer bei passender Geräteadresse. |
| ja | ja | Treffer nur, wenn beide Adressen passen. |
| nein | nein | Eintrag wird ignoriert. |

---

## DPT-Unterstützung

Der KNX Device Watcher unterstützt aktuell ausgewählte DPTs.

| DPT | Typ | Beschreibung |
|---|---|---|
| `1.001` | Boolean | Schalten / Ein-Aus |
| `5.001` | Integer/Float | Prozentwert 0–100 % |
| `5.004` | Integer | Wertebereich 0–255 |
| `7.001` | Integer | 2-Byte unsigned |
| `9.001` | Float | 2-Byte-Float, z. B. Temperatur |
| `12.001` | Integer | 4-Byte unsigned |

Hinweis: DPT-Untergruppen mit gleicher Codierung können technisch ähnlich ausgewertet werden, auch wenn sich Einheit oder Bedeutung unterscheiden. Für produktive KNX-Status- und Steuerfunktionen sollten die nativen Symcon-KNX-Instanzen bevorzugt werden, sofern diese den jeweiligen Anwendungsfall bereits vollständig abdecken.

---

## Dateibasierte Protokollierung

Der KNX Traffic Logger schreibt bewusst persistente Logdateien. Das ist für dieses Modul systembedingt notwendig, da die Telegramme auch nachträglich und außerhalb von Symcon analysierbar bleiben sollen.

### JSONL-Format

Jede Zeile ist ein JSON-Objekt.

Beispiel:

```json
{"ts":"20260610-153000","unix":1781105400,"ga":"1/1/16","pa":"1.1.101","payload":"80","apci":"write","len":1}
```

### Felder

| Feld | Bedeutung |
|---|---|
| `ts` | Zeitstempel im Format `YYYYMMDD-HHMMSS`. |
| `unix` | Unix-Zeitstempel. |
| `ga` | Gruppenadresse. |
| `pa` | Physikalische Geräteadresse. |
| `payload` | Telegrammdaten als Hex-Wert. |
| `apci` | Erkannter Telegrammtyp, z. B. `read`, `write`, `response` oder `unknown`. |
| `len` | Nutzdatenlänge in Byte. |
| `raw` | Optionale Rohdaten, falls vom Gateway geliefert. |

---

## Debugging

Alle Module verwenden Debug-Ausgaben über den Modul-Debug von IP-Symcon. Die Debug-Ausgaben können bei der Einrichtung und Fehlersuche helfen.

Typische Prüfpunkte:

- Kommt überhaupt ein Telegramm in der Instanz an?
- Stimmen GA und PA mit der Filterliste überein?
- Wird der DPT korrekt dekodiert?
- Wird die Zielvariable angelegt?
- Wird beim Trigger die Zielinstanz und Funktion korrekt konfiguriert?
- Hat der Traffic Logger Schreibrechte auf den konfigurierten Logpfad?

---

## Hinweise und Grenzen

- Die Module sind als Zusatzwerkzeuge zur Analyse und Automatisierung gedacht und ersetzen nicht in jedem Fall die nativen KNX-Instanzen von IP-Symcon.
- Der KNX Device Watcher unterstützt nur ausgewählte DPTs.
- Der KNX Device Trigger sollte nur mit bewusst ausgewählten eigenen Modulmethoden verwendet werden.
- Der KNX Traffic Logger erzeugt Dateien im Dateisystem. Der Logpfad sollte so gewählt werden, dass Symcon Schreibrechte besitzt und keine ungewollten Systempfade verwendet werden.
- Bei sehr hohem KNX-Telegrammaufkommen sollte die Loggröße begrenzt und die Aufbewahrung passend eingestellt werden.

---

## Versionshinweise

### 1.0.0

- Erweiterung der Modulsammlung um KNX Traffic Logger.
- Telegrammfilterung nach GA und PA.
- Tick-Monitor für kurzzeitige Boolean-Impulse.
- Watcher für ausgewählte DPT-Werte.
- Trigger für eigene Modulmethoden.
- JSONL-Protokollierung mit Rotation und Aufbewahrungsbegrenzung.

---

## Lizenz

Dieses Projekt steht unter der Apache-2.0-Lizenz. Details siehe `LICENSE`.

---

## Danksagung

Vielen Dank an das Symcon-Team für die Erweiterungen am KNX-Gateway und die Bereitstellung der Entwicklungswerkzeuge. Die Möglichkeit, Gruppenadresse und Geräteadresse aus dem Telegrammfluss auszuwerten, eröffnet zusätzliche Diagnose- und Automationsmöglichkeiten für KNX-Anwendungen in IP-Symcon.
