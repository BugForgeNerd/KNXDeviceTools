# KNX Device Tools für IP-Symcon

[![IP-Symcon is awesome!](https://img.shields.io/badge/IP--Symcon-9.0-blue.svg)](https://www.symcon.de)
![License](https://img.shields.io/badge/license-Apache2.0-blue.svg)

Die **KNX Device Tools** Gruppen bieten verschiedene Module für IP-Symcon, die darauf ausgelegt sind, KNX-Telegramme zu überwachen, zu filtern und automatisch Variablen für definierte Geräte und Gruppenadressen zu erstellen. Diese Module unterstützen eine Vielzahl von KNX-Datentypen (DPTs) und erleichtern die Integration von KNX-Geräten in IP-Symcon.

Aktuell stehen folgende drei Module zur Verfügung:
- KNXDeviceTickMonitor
- KNXDeviceTrigger
- KNXDeviceWatcher

**KNXDeviceTickMonitor:**
Dieses Modul ermöglicht die Überwachung mehrerer Gruppen- und Geräteadresskombinationen im KNX-Datenfluss. Für jede Kombination wird eine Variable unterhalb des Moduls erstellt. Die Variable wird für die im Modul konfigurierbare Tick-Länge auf "True" gesetzt, wenn eine gefilterte Gruppen- oder Geräteadresskombination im KNX-Datenfluss erkannt wird. Anschließend fällt die Variable auf "False" zurück. Dieser "Tick" kann dann mit den Standard-Symcon-Tools (wie Ereignissen) weiterverarbeitet werden.

**KNXDeviceTrigger:**
Auch mit diesem Modul können mehrere Gruppen- und Geräteadresskombinationen im KNX-Datenfluss überwacht werden. Es richtet sich besonders an Benutzer, die eigene Module entwickeln möchten. KNXDeviceTrigger kann Ereignisse basierend auf Gruppen- oder Geräteadresskombinationen aus den KNX-Telegrammen direkt an andere Module weiterleiten oder Funktionen anderer Module auslösen. Dieses Modul ermöglicht es, den Umweg über die Erstellung von Variablen zu umgehen.

**KNXDeviceWatcher:**
Das Modul KNXDeviceWatcher funktioniert grundsätzlich ähnlich wie der KNXDeviceTickMonitor, jedoch mit dem Unterschied, dass die Variablen unterhalb des Moduls direkt mit den Daten aus dem KNX-Datenfluss befüllt werden. Die Daten werden automatisch übernommen. Es wird jedoch empfohlen, die Daten aus den integrierten KNX-Modulen von Symcon zu verwenden, da die unterstützten Datentypen in diesem Modul begrenzt sind. Für die DPT der Gruppe 1.001 gelten auch die gleichen Werte für 1.002 und ähnliche Gruppen, wobei sich die Einheiten unterscheiden. Das gleiche gilt für die DPT 9.001.

---

**Wieso, weshalb, warum?**

KNX überträgt Informationen grundsätzlich über Gruppenadressen. Ein wesentlicher Nachteil dabei ist, dass aus einem Telegramm nicht direkt ersichtlich ist, welches Gerät der Absender ist. In einigen Situationen – insbesondere in der Hausautomation – kann es jedoch sehr hilfreich sein zu wissen, welcher Schalter den Impuls ausgelöst hat.

Befinden sich mehrere Schalter auf derselben Gruppenadresse, lässt sich in einem klassischen KNX-Setup nicht erkennen, welcher Schalter tatsächlich betätigt wurde. Um dieses Problem zu lösen, müsste für jeden Schalter eine eigene Gruppenadresse definiert werden. Das kann jedoch schnell zu komplexen Strukturen führen.

Auch in Symcon-Modulen müssten entsprechend mehrere Gruppenadressen angelegt werden, um dieses Verhalten korrekt abzubilden. Deutlich einfacher ist es hingegen, das KNX-Telegramm direkt auf den Absender auszuwerten. In Kombination aus Gruppenadresse und Geräteadresse kann damit exakt nachvollzogen werden, welcher Schalter wann welchen Befehl gesendet hat.

Der Auslöser für diese Überlegung war bei mir ein Modul zur Steuerung meiner Raffstores. Die Steuerung läuft vollständig automatisiert. Ich wollte jedoch erreichen, dass die Automatik für einen definierbaren Zeitraum unterbrochen wird, sobald ein Bewohner den physischen Schalter betätigt. Es war mir schlicht zu umständlich, die Automatik ständig über die Weboberfläche eines Terminals deaktivieren zu müssen.

---

## Funktionen

- Überwachung von KNX-Telegrammen in Echtzeit.
- Filterung nach **Gruppenadresse (GA)** und/oder **Geräteadresse (PA)**.
- Automatische Erstellung von Variablen für erkannte KNX-Geräte.
- Unterstützung der wichtigsten KNX-DPTs:
  - `1.001` – Schalten (Boolean)
  - `5.001` – Prozentwert (0–100 %)
  - `5.004` – Wertebereich (0–255)
  - `7.001` – Unsigned Integer (0–65535)
  - `9.001` – Temperatur (2-Byte Float)
  - `12.001` – 4-Byte Unsigned Integer (0–4294967295)
- Debug-Funktion zur Anzeige empfangener Telegramme und Variablenupdates.
- Einfaches Konfigurationsformular in IP-Symcon.

---

## Installation
Bitte beachten, dass die Module erst ab Symcon Version 8.2 lauffähig sind!
1. Modul in den IP-Symcon Modul-Store einbinden oder lokal installieren.
2. Instanz des **KNX Device Watcher** anlegen.
3. Verbindung zum KNX-Gateway herstellen (über Parent-Instanz, GUID: `{1C902193-B044-43B8-9433-419F09C641B8}`).
4. Modul aktivieren (`Active = true`).
5. Filterliste (`DeviceList`) konfigurieren:
   - Gruppenadresse (GA) im Format `x/x/x`, z.B. `1/1/16`.
   - Geräteadresse (PA) im Format `x.x.x`, z.B. `1.1.101`.
   - DPT für korrekte Werte-Dekodierung.
6. Variablen automatisch erstellen lassen oder über "Variablen aktualisieren" Button manuell auslösen.

---

## Konfiguration

Die **DeviceList** ist eine Liste von Geräten, die überwacht werden sollen. Jede Zeile enthält:

| GA | PA | DPT |
|----|----|----------------|
| Gruppenadresse, z.B. 1/1/16 | Geräteadresse, z.B. 1.1.101 | DPT aus Auswahl (z.B. 1.001, 5.001) |

**Hinweis:**  
- Wird keine GA oder PA angegeben, wird der Eintrag ignoriert.
- DPT; unbekannte DPTs werden als Hex-Wert angezeigt.

---

## Verwendung

- Empfängt KNX-Telegramme automatisch vom Gateway.
- Filtert Telegramme nach GA/PA.
- Erstellt/aktualisiert Variablen entsprechend der DeviceList.
- Unterstützt unterschiedliche Datentypen automatisch.

---

### Debugging

- Alle empfangenen KNX-Telegramme können im Debug-Log des jeweiligen Moduls angezeigt werden.
- Variablenupdates werden ebenfalls geloggt.

---

### Danksagung an das Symcon-Team

Ich möchte mich herzlich beim gesamten Symcon-Team bedanken, das das KNX-Gateway um wichtige Eigenschaften erweitert hat, um die Nutzung der neuen Module zu ermöglichen. Diese Funktionalität steht nun allen Anwendern zur Verfügung, die die Version 8.2 oder höher von Symcon verwenden. Eure Arbeit hat einen erheblichen Beitrag dazu geleistet, den Funktionsumfang für unsere KNX-Anwendungen weiter zu verbessern!

Vielen Dank für eure kontinuierliche Unterstützung und hervorragende Arbeit!





