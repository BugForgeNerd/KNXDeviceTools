<?php

/**
 * NEUSTART des Moduls: MC_ReloadModule(59139, "KNXDeviceTools");
 * sudo /etc/init.d/symcon start
 * sudo /etc/init.d/symcon stop
 * sudo /etc/init.d/symcon restart
 *
 * ToDo:
 * - 
 * - 
*/


class KNXDeviceWatcher extends IPSModule
{

    /**
     * Erstellen des Moduls
     */
    public function Create()
    {
        parent::Create();

        // Verbindung zum KNX-Gateway herstellen
        $this->ConnectParent("{1C902193-B044-43B8-9433-419F09C641B8}");

        // Konfigurationsparameter
        $this->RegisterPropertyBoolean('Active', false);
        $this->RegisterPropertyString('DeviceList', '[]');
    }

    /**
     * Anwenden der Änderungen
     * 
     * Wird aufgerufen, wenn Änderungen an den Konfigurationseinstellungen vorgenommen wurden.
     */
    public function ApplyChanges()
    {
        parent::ApplyChanges();

        if (!$this->ReadPropertyBoolean('Active')) {
            return;
        }

        $this->UpdateVariableList();
    }

    /**
     * Empfang von KNX-Daten
     * 
     * Wird aufgerufen, wenn ein KNX-Telegramm vom Gateway empfangen wird.
     * Hier wird das Telegramm verarbeitet und die entsprechenden Variablen aktualisiert.
     * 
     * @param string $JSONString Das empfangene KNX-Telegramm im JSON-Format
     */
	public function ReceiveData($JSONString)
	{
		$data = json_decode($JSONString, true);
		if (!is_array($data)) {
			return;
		}
		//IPS_LogMessage('KNXTest', print_r($data, true));

		// --- Schutz gegen nicht existierende Keys ---
		$GA1 = $data['GroupAddress1'] ?? '';
		$GA2 = $data['GroupAddress2'] ?? '';
		$GA3 = $data['GroupAddress3'] ?? '';
		$PA1 = $data['PhysicalAddress1'] ?? '';
		$PA2 = $data['PhysicalAddress2'] ?? '';
		$PA3 = $data['PhysicalAddress3'] ?? '';

		// KNX-Adresse aus Einzelbytes zusammensetzen
		$ga = "$GA1/$GA2/$GA3";
		$pa = "$PA1.$PA2.$PA3";

		// Rohdatenwert (binär) lesen und hexadezimal anzeigen
		$rawValueUtf8 = $data['Data'] ?? '';
		$rawValue = $this->KNX_Utf8ToBinary($rawValueUtf8);
		$valueHex = bin2hex($rawValue);

		$this->SendDebug('KNX Telegramm', "GA=$ga | PA=$pa | Raw=$valueHex", 0);

		$list = json_decode($this->ReadPropertyString('DeviceList'), true);
		if (!is_array($list)) {
			return;
		}

		foreach ($list as $entry) {
			$gaFilter = $entry['GA'] ?? '';
			$paFilter = $entry['PA'] ?? '';

			// Filter prüfen
			if (($gaFilter === '' || $gaFilter === $ga) &&
				($paFilter === '' || $paFilter === $pa)) {

				// Ident aus Watchlist
				$ident = 'KNX_' . str_replace('/', '_', $entry['GA']) . '_' . str_replace('.', '_', $entry['PA']);
				//IPS_LogMessage ("Watcher", "ident: " . $ident);

				if ($this->GetIDForIdent($ident) > 0) {
					//IPS_LogMessage("Watcher", "GetIDForIdent >0");
					$value = $this->DecodeKNXValue($rawValue, $entry['DPT'] ?? '');
					//IPS_LogMessage("Watcher", "RawValue Hex: " . bin2hex($rawValue) . " | DPT: " . ($entry['DPT'] ?? ''));

					// Den decodierten Wert in die Variable schreiben
					if ($value !== null) {
						$this->SetValue($ident, $value);
					}

					$this->SendDebug('Update', "GA=$ga PA=$pa → DPT={$entry['DPT']} | Value=$value", 0);
				}
			}
		}
	}


    /**
     * Variablenliste erstellen / aktualisieren
     * 
     * Diese Funktion erstellt oder aktualisiert die Variablen basierend auf der 'DeviceList'.
     * Sie stellt sicher, dass alle in der Liste konfigurierten Geräte korrekt angelegt sind.
     */
    public function UpdateVariableList()
    {
        $list = json_decode($this->ReadPropertyString('DeviceList'), true);
        if (!is_array($list)) {
            $list = [];
        }

        // Vorhandene Variablen erfassen
        $existing = [];
        foreach (IPS_GetChildrenIDs($this->InstanceID) as $childID) {
            $obj = IPS_GetObject($childID);
            if ($obj['ObjectType'] == 2) { // Variable
                $existing[$obj['ObjectIdent']] = $childID;
            }
        }

        // Neue Variablen anlegen
        foreach ($list as $entry) {
            $ga = $entry['GA'] ?? '';
            $pa = $entry['PA'] ?? '';
            //$name = $entry['Name'] ?? "GA $ga / PA $pa";

            //if ($ga === '' && $pa === '') {
                //continue;
            //}
			
			// Variablenname automatisch aus GA/PA
			if ($ga !== '' || $pa !== '') {
				$name = "$ga / $pa";
			} else {
				continue; // Keine GA/PA angegeben, überspringen
			}
			
			// Ident unverändert
			$ident = 'KNX_' . str_replace('/', '_', $ga) . '_' . str_replace('.', '_', $pa);

			if (!isset($existing[$ident])) {
				// Typ bestimmen
				$varType = $this->GetVariableTypeByDPT($entry['DPT'] ?? '');
				
				// Variable registrieren
				switch ($varType) {
					case 0: // Boolean
						$this->RegisterVariableBoolean($ident, $name);
						break;
					case 1: // Integer
						$this->RegisterVariableInteger($ident, $name);
						break;
					case 2: // Float
						$this->RegisterVariableFloat($ident, $name);
						break;
					case 3: // String
					default:
						$this->RegisterVariableString($ident, $name);
						break;
				}
				
				$this->SendDebug('Create', "Neue Variable: $ident ($name, Typ=$varType)", 0);
			} else {
				unset($existing[$ident]);
			}

        }

        // Nicht mehr benötigte Variablen löschen
        foreach ($existing as $ident => $id) {
            $this->UnregisterVariable($ident);
            $this->SendDebug('Delete', "Variable entfernt: $ident", 0);
        }
    }

    /**
     * Konfigurationsformular für das Modul
     * 
     * Diese Methode gibt das JSON für das Konfigurationsformular zurück,
     * das die Benutzeroberfläche des Moduls beschreibt.
     */
	public function GetConfigurationForm()
	{
		// --- Kernel-Version prüfen ---
		$kernel = IPS_GetKernelVersion();
		$warnLabel = [];
		if (version_compare($kernel, '8.2', '<')) {
			$warnLabel[] = [
				'type'    => 'Label',
				'caption' => $this->Translate('WARN_SYMPATH_VERSION') . '8.2 oder höher! Aktuelle Version: ' . $kernel,
				'color'   => '8B2500' // Dunkelrot
			];
		}

		// --- ursprüngliche Elemente ---
		$baseElements = [
			[
				"type"    => "Label",
				"caption" => $this->Translate("ModuleLabel")
			],
			[
				'type'    => 'CheckBox',
				'name'    => 'Active',
				'caption' => $this->Translate('Active')
			],
			[
				'type'    => 'List',
				'name'    => 'DeviceList',
				'caption' => $this->Translate('DeviceList'),
				'rowCount'=> 8,
				'add'     => true,
				'delete'  => true,
				'columns' => [
					[
						'caption' => $this->Translate('GroupAddress'),
						'name'    => 'GA',
						'width'   => '160px',
						'add'     => '',
						"edit"    => [
							"type" => "ValidationTextBox",
							"validate" => "^\\d{1,2}/\\d{1,2}/\\d{1,3}$",
							"errorMessage" => $this->Translate('InvalidGA')
						]
					],
					[
						'caption' => $this->Translate('DeviceAddress'),
						'name'    => 'PA',
						'width'   => '160px',
						'add'     => '',
						"edit"    => [
							"type" => "ValidationTextBox",
							"validate" => "^\\d{1,2}\\.\\d{1,2}\\.\\d{1,3}$",
							"errorMessage" => $this->Translate('InvalidPA')
						]
					],
					[
						'caption' => $this->Translate('DPT'),
						'name'    => 'DPT',
						'width'   => 'auto',
						'add'     => '',
						'edit'    => [
							'type'    => 'Select',
							'options' => [
								['label' => $this->Translate('DPTOptions.1.001'), 'value' => '1.001'],
								['label' => $this->Translate('DPTOptions.5.001'), 'value' => '5.001'],
								['label' => $this->Translate('DPTOptions.5.004'), 'value' => '5.004'],
								['label' => $this->Translate('DPTOptions.7.001'), 'value' => '7.001'],
								['label' => $this->Translate('DPTOptions.9.001'), 'value' => '9.001'],
								['label' => $this->Translate('DPTOptions.12.001'), 'value' => '12.001']
							]
						]
					]                       
				]
			]
		];

		// --- Actions-Bereich unten ---
		$actions = [
			// Update-Button
			[
				'type'    => 'Button',
				'caption' => $this->Translate('UpdateVariables'),
				'onClick' => "KNXDW_UpdateVariableListWithInfo('$this->InstanceID');"
			],
			[
				"type"    => "Label",
				"width"   => "50%",
				"caption" => ""
			],
			[
				"type"    => "Label",
				"width"   => "50%",
				"caption" => $this->Translate("LICENSE_NOTICE")
			],
			[
				"type"    => "Label",
				"width"   => "50%",
				"bold"    => true,
				"caption" => $this->Translate("DONATION_HEADER")
			],
			[
				"type"    => "Label",
				"width"   => "50%",
				"caption" => $this->Translate("DONATION_TEXT")
			],
			// PayPal-Button als Image
			[
				"type"  => "RowLayout",
				"items" => [
					[
						"type"    => "Image",
						"onClick" => "echo '" . $this->Translate("PAYPAL_LINK") . "';",
						"image"   => "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAADsAAAA6CAYAAAAOeSEWAAAAAXNSR0IArs4c6QAAAARnQU1BAACxjwv8YQUAAAAJcEhZcwAADsMAAA7DAcdvqGQAAAoiSURBVGhD7ZprkBTVFcd/5/bsC1heAvIKLAgsuwqYGI3ECPjAfAhRtOKjKjGlljwUTfIhSlZNrVtBVqIJsomFIBUrqZRVPkvFqphofMVoJEYSXrsrbxU1PFR2kd3Zmb4nH3qmp+fua4adjR/0V9U1955zunv+fe89t/t2wxcIcQ19p9ZQNeBiPEa7nh5RPiPhH8Kwh5Pje3ilLumG9JXCi62qvw3Puys4tDqn0EiZlC8dE/m12gI8D/4GdtT8GcTd8YQovNjqu1/EeOcFlajY7spp0nqci2P1eWLmB2y55WDEcUIY19BnRKZ3bsE0rqB0vbt4QGQ+SfvCSZWryl1XvhRW7Iz6YZAeq27rud25K4Fua0tgMjLjiGGl48ybwopN+JWISJc6IDI20+W0uGg5unPkghmuHzJr9dCIM28KK9bEqoLWiP757nC7sHuF0kJTdpHSo/H2c5ygvCisWHS6U8+uQqQVo60cjXO7eyTeyPiIM28KK1YkJTb1512tmlQ6WpVEC8HWCh2tmd+Olsh2TEkeV2yHhmNc6dPcW1ixms7EqdaR6DhUaDsM8cNC+xHCLR75zdoOC20Hhc8OCMfeVdqPKMNL26Kny5fCiZ1bWwpM7jrRpGx+e8SWB2qFjlbh0gs2sH7vNa47Vwon9mDZKRiJBZVI66axSQXtLWt1z+ByZXB5KcgGb/2u8113LhROrBBk4lSlU/KxiXRkhrCb58DUU9Il42vsrmxnbhROrGrkzsnNTN2I1S7iumPGaZmycBYP7j456s6FwoklnYnDenbrdiU2V8aPV8aPjVoEjU2LGnKhcGKFqkyDRrtzqnyiYsXARV0MUT9Z6pp6ozBiL7/cA6nsNNVEsR2Zcj5j9bw5ypguHo3FO+KaeqMwYrfNHI/IwIwh0qIAahW1GUN3Y9W9COfMVmaflW0L8Im17XSNvVEYsaZoujvTZBFt1Z5IX4SBA5XLLlHmnetGBCjNXF/V6pp7ozBisVVZVbflbC53eQIjRyoXnq8sWwxVlW5ABtE/uaZc6Kk9cqeq/gE8b0l2Bo6QOKpMHg2lJdlZuqgIBg6E4cNg7BgoH+Tu2RVKMjmLG6dscx29URix1b98GSNzXXOAwpxZltMrC3Mu5RmWVCx0zblQiG4soKlu3EWrKjBpXGGEwtESqz9xjbnSd7HTVw5HGBWo6kJTzIPBkUR94rRj7ZXxGybtcx250nexalLza3SOjTB0oHaaUvJFZR+auJClk//iuvKh72JN9M6Jzq07bHB2PR9UdqLcSssnM1gy9XXXnS99vORA9ap7MOanQaWLrnxWlXJ25Ca+Mx2oPg7SimoHokcwZg/t/iZumrwTKcwCOZ3/2QlQvepZjPmOaw6787e/oVROdJ0R9A4WT+rzMmku9L0bQxeLbJHGGN5LN07o311Tf9E3sWfUDkCoCCppgenOklpS7UmsKpQmd7jm/qJvYluLpyDidcrAacGDyhTPc3wRRI5w3dTDrrm/6JtYz0zKNjiie2pVAJXmQiag3ujhsufAxPkfEfc9fN2M1U3BZjPbzKnK2BE9LGzL8zx73zOutb/oezbuifX7HgAWu+YQ5VaWVNzrmvuLvnXj3nEytYNPk2vqT/pPrKqA9PBQio+Yza6xP+nfbrx2zyKMN8M1A6D2ZZZOetI1f8mXfEmPZI/ZyhVfh9g3s2zB6tkHeO0vsaPu42xfAVm3Zy5iZmIjS64YH5H38I6/lPdq4rq90xGZj7WCxp7ghgkHssVW370RYxZ0uXCmtIL/fbbXbMx2FIh1+99C9GuuOcVhlIUsqcj9mXbd3l8gcjsACb7FsorXo1OPQPpNnIK1cXzNLPiKlKPyALW1hZ+ual+KOZ8otEPWW/YRwG8j9RwwwfEUKDZNZM2zZ9SWgZkICqpxBhcNY9TxgST81IM5IDKWJ0uHhPVC8ZWJ4xAGBBX5iKGbyhl6bBCqkbsrqaJW87jQ4cU7yKIJH5M1ZivrZ1DkbQHAahM7lgcrhtNWnklxbBMIqI1TOrScycMsW3YvxOMCVMqA7SW27fG4lKWWOP3NJUYOxK1ZAIDYN2i87c3wXDNqx5Mo+17g023cfKVHzKQWvuVFFk+8EIB1+y9G9CkAlA9ZUjGONe+UUFp8BcI5qMbA/rvE2OfifvECDCD8jSGtW/m0vAW0GHiVxRXzAFJvygGP6Zmxqo2AMLfW46AsCmMs/xzEB0OObf/0aYq8VCIL9onLgFsxMhIAX+rjfuJJYrHVCGDlH8BsSL0E2172R2JmLgrY5FUYGROeQ2kChIf2lpDQa0M7vFa6Yc/Edms2AsE6jwjgEbfeYYyOAMCaH3FkSAueXxwcT5vTB4h2i6owKYmZR/XdTRwacAjjLQpaVRW1vz7WNuBhjARCrTZh7VqsvhEKBVDbxFWJt0F3BQ/xnMnMe0YBsPWMmzFmLghY/2Eab3sUidxWil7Bun1NdMhB4JLgeNiY2vvbffNUKBTZDKwFtiAEQgFsohmxkfFvwsWBjNjwsx5AGIbxpmFkKCJgbQLr34GRQ4jMD/6ovjxlwvuns2P5jZz61rlY+1a4v+83UVdnUR5JHdsjkVxQXH1XNZ4E603Wf48YNwEafKIQMgJhKkLwLkRoQ1maVEYjzApC9HEOTDiTxRXLKNKzQfaHexfFtmclO5HwYSMj1ka+ibD2aZL+Gnz7K6x/M76dRmPNSuCCIETBT96/67nfxAF47DEf2E3gsmh5cALfPhK+5BJZ2CGxhxApC2L8a9la80nwwBARq/owyhpU7kXtDfhMYWnFBjyTeSOtch91YgG4dlI7qu+m7Ed573cfZV089RrTxWDMXv6ox/a9wWt7VR9frqZ5eedJXMl8GWq8MCuXzVoxri0hFwUx+iG7ftwCQHPNNqpXbUfkVMR8N0yH6jfQePtfAVjffBJSGgwB5SiLK67uZvUiMwtYDb9hLF67r7oDzgZAdKfeWafy4HWVwazCMT5Y/346NmjZ/zRPQCRI/cqHNC8/lg7IQnRbMKwFjNxLVX091fUr2hJFb2JkWCoqvJJBF9B0Vw4s1m5jZHtNJqQ0822E8E43QkElmCkAPLOB9XvrWL9/VYfRVxGKghga5U4VlOqgrjupqwt6QCjWi75M1qbgT3ZBWfujWLsDFESG4sV+hvFuB4LMB+lsmiGhmWUX1TiqP+SVuszXX1ldrqeH+diDCAeCso4G+TnoLYjxwxCrTYzcNwrRVC/IjFdCsSr/xWoDNtmA2tXRgCz+VXcctXPwtR7VF7B2I8nkMkhchPUbsH4DXuL3kT2EGMuCooL6dTTWZD+wi+4GGoAGPP6Q5YuyZNxhtGM2yhqQF1GeArkGXy8L9tcGPPsExrfh8bDroofIvjcuMN5p9Zf66j2BIPj+a5z29rxUMvtc6D+x01eMwRRtwZgRqG0pMfar8a01e9yw/yd53Gvmi+nAk+sRXQh2zuct9AvH/wAcerqGMemSoQAAAABJRU5ErkJggg=="
					],
					[
						"type"    => "Label",
						"caption" => " "
					],
					[
						"type"    => "Label",
						"width"   => "70%",
						"caption" => $this->Translate("DONATION_INFO")
					],
					[
						"type"    => "Label",
						"caption" => " "
					]
				]
			],
			[
				"type"    => "Label",
				"width"   => "50%",
				"caption" => $this->Translate("PAYPAL_LINK")
			]
		];

		// Warnung + Elemente zusammenführen, Actions extra setzen
		return json_encode([
			'elements' => array_merge($warnLabel, $baseElements),
			'actions'  => $actions
		]);
	}


	public function UpdateVariableListWithInfo(): void
	{
		$this->UpdateVariableList();
		echo "Mögliche Änderungen an Variablen wurden übernommen.";
	}


    /**
     * Wandelt eine Roh-Nachricht in ein lesbares Format um.
     *
     * Diese Funktion interpretiert die Rohdaten (z. B. von einem KNX-Gateway empfangen)
     * und gibt den entsprechenden Wert in menschenlesbarer Form zurück. Dabei wird
     * der Typ des DPTs berücksichtigt.
     *
     * @param string $rawValue Das Rohdaten-Array vom KNX-Telegramm.
     * @param string $dpt Der DPT-Typ (z. B. "1.001", "5.001").
     * @return mixed Der dekodierte Wert.
     */
    protected function DecodeKNXValue($rawValue, $dpt)
    {
        if ($rawValue === '' || !is_string($rawValue)) {
            return null;
        }

        switch ($dpt) {
            case '1.001': // Schalten (bool)
				// Manche Gateways senden mehr als 1 Byte – Wert steckt im letzten Byte
				$byte = ord(substr($rawValue, -1));
				return ($byte & 0x01) ? true : false;

			case '5.001': // Prozentwert 0–100 %
				return $this->DecodeDPT5_001($rawValue);

			case '5.004': // Wertebereich 0–255
				return $this->DecodeDPT5_004($rawValue);

			case '9.001': // Temperatur 2-Byte Float
				return $this->DecodeDPT9_001($rawValue);

			case '7.001': // Unsigned Integer (0–65535)
				return $this->DecodeDPT7_001($rawValue);

			case '12.001': // 4-Byte Unsigned (0–4294967295)
				return $this->DecodeDPT12_001($rawValue);

            default:
                // Unbekannter Typ -> Hex anzeigen
                return bin2hex($rawValue);
        }
    }
	
	/**
	 * Hilfsfunktion: Dekodiert DPT 9.001 (2-Byte Float KNX Standard)
	 * @param string $raw 2 Byte vom KNX Telegramm
	 * @return float Temperatur in °C
	 */
	protected function DecodeDPT9_001($raw)
	{
		// Gateway sendet manchmal 3 Bytes, z.B.: 0x80 0x07 0xD0
		// Wir benötigen nur die letzten 2 Byte
		if (strlen($raw) < 2) {
			return null;
		}

		// Immer letzte 2 Bytes nehmen
		$data = substr($raw, -2);

		$hi = ord($data[0]);
		$lo = ord($data[1]);

		// 16-Bit Wert zusammensetzen
		$rawValue = ($hi << 8) | $lo;

		// Nur die unteren 16 Bits verwenden
		$rawValue &= 0xFFFF;

		// Vorzeichen
		$isNegative = ($rawValue & 0x8000) !== 0;

		// Exponent = Bits 14–11
		$exponent = ($rawValue >> 11) & 0x0F;

		// Mantisse = Bits 10–0
		$mantissa = $rawValue & 0x07FF;

		// Zweierkomplement, falls negativ
		if ($isNegative) {
			$mantissa = - (0x0800 - $mantissa);
		}

		// Finaler Wert
		$value = 0.01 * $mantissa * pow(2, $exponent);

		return round($value, 2);
	}
	
    /**
     * Hilfsfunktion: Dekodiert DPT 5.001 (1-Byte Prozentwert 0–100 %)
     *
     * KNX sendet für DPT 5.xxx typischerweise 1 Datenbyte.
     * Viele Gateways senden jedoch zusätzliche Bytes davor (z. B. "80"),
     * daher wird ausschließlich das letzte empfangene Byte ausgewertet.
     *
     * @param string $raw Rohdaten vom KNX Telegramm (mindestens 1 Byte)
     * @return float Prozentwert (0.00–100.00 %)
     */	
	protected function DecodeDPT5_001($raw)
	{
		if (strlen($raw) < 1) {
			return null;
		}

		// Nur das letzte Byte enthält den Wert (0–255)
		$lastByte = ord(substr($raw, -1));

		// Auf gültigen Bereich prüfen
		if ($lastByte < 0 || $lastByte > 255) {
			return null;
		}

		// Prozent = (Byte / 255) * 100
		return round(($lastByte / 255) * 100, 2);
	}

	/**
	 * Hilfsfunktion: Dekodiert DPT 5.004 (1-Byte Wert 0–255)
	 *
	 * KNX sendet für DPT 5.xxx typischerweise 1 Datenbyte.
	 * Einige Gateways senden zusätzliche Bytes davor, daher
	 * wird ausschließlich das letzte Byte ausgewertet.
	 *
	 * @param string $raw Rohdaten aus dem KNX-Telegramm
	 * @return int|null Wert zwischen 0 und 255
	 */
	protected function DecodeDPT5_004($raw)
	{
		if (strlen($raw) < 1) {
			return null;
		}

		// Letztes Byte enthält den Wert 0–255
		$lastByte = ord(substr($raw, -1));

		if ($lastByte < 0 || $lastByte > 255) {
			return null;
		}

		return $lastByte;
	}

	/**
	 * Hilfsfunktion: Dekodiert DPT 7.001 (2-Byte Unsigned Integer, 0–65535)
	 *
	 * KNX sendet bei DPT 7 immer 2 Datenbytes in Big-Endian-Reihenfolge.
	 * Einige Gateways senden zusätzliche Bytes davor, daher werden immer nur
	 * die letzten beiden Bytes ausgewertet.
	 *
	 * Beispiel:
	 *   Hex: 00 3C → 60
	 *   Hex: 1A C0 → 6848
	 *
	 * @param string $raw KNX-Rohdaten (mindestens 2 Byte)
	 * @return int|null Wert zwischen 0 und 65535
	 */
	protected function DecodeDPT7_001($raw)
	{
		if (strlen($raw) < 2) {
			return null;
		}

		// Nur die letzten zwei Bytes nutzen
		$data = substr($raw, -2);

		$high = ord($data[0]);
		$low  = ord($data[1]);

		// DPT7 ist immer Unsigned Integer (0–65535)
		$value = ($high << 8) | $low;

		return $value;
	}

	/**
	 * Hilfsfunktion: Dekodiert DPT 12.001 (4-Byte Unsigned Integer, 0–4294967295)
	 *
	 * KNX sendet bei DPT 12 vier Datenbytes in Big-Endian-Reihenfolge.
	 * Einige Gateways senden zusätzliche Bytes davor. Daher werden immer nur
	 * die letzten vier Bytes des Rohdaten-Payloads ausgewertet.
	 *
	 * Beispiel:
	 *   Hex: 00 00 1A C0 → 6848
	 *   Hex: 00 0F 42 40 → 1.000.000
	 *
	 * @param string $raw KNX-Rohdaten (mindestens 4 Byte)
	 * @return int|float Unsigned 32-Bit Wert (0–4294967295)
	 */
	protected function DecodeDPT12_001($raw)
	{
		if (strlen($raw) < 4) {
			return null;
		}

		// Letzte 4 Bytes extrahieren (Big Endian)
		$data = substr($raw, -4);

		$b1 = ord($data[0]);
		$b2 = ord($data[1]);
		$b3 = ord($data[2]);
		$b4 = ord($data[3]);

		// Big Endian → Unsigned 32bit Integer
		// 256^3 = 16777216
		// 256^2 = 65536
		// 256^1 = 256
		// 256^0 = 1
		$value = ($b1 * 16777216) +   // 2^24
				 ($b2 * 65536) +      // 2^16
				 ($b3 * 256) +        // 2^8
				 $b4;                 // 2^0

		return $value; // auf 64-Bit-Systemen int, sonst float
	}

	/**
	 * Wandelt UTF-8 encodierte KNX-Daten (Symcon JSON) zurück in rohe Bytes.
	 *
	 * Symcon kodiert Bytes > 0x7F als UTF-8 Sequenzen (z. B. 0x80 → C2 80),
	 * weshalb bin2hex() falsche Werte zeigt. Diese Funktion stellt die Original-
	 * Bytes wieder her.
	 *
	 * @param string $data UTF-8 String aus ReceiveData()
	 * @return string Reine Binärdaten (1:1 der KNX-Payload)
	 */
	protected function KNX_Utf8ToBinary(string $data): string
	{
		//return utf8_decode($data);
		return mb_convert_encoding($data, 'ISO-8859-1', 'UTF-8');
	}

/**
 * Bestimmt den Variablentyp basierend auf dem DPT (Datapoint Type).
 *
 * @param string $dpt Der DPT-Typ (z.B. "1.001", "5.001", "9.001").
 * 
 * @return int Der Variablentyp: 
 *             0 = Boolean, 
 *             1 = Integer, 
 *             2 = Float, 
 *             3 = String.
 */
	protected function GetVariableTypeByDPT(string $dpt): int
	{
		switch ($dpt) {
			case '1.001':  // Schalten
				return 0; // Boolean
			case '5.001':
			case '5.004':
			case '9.001':
				return 2; // Float
			case '7.001':
			case '12.001':
				return 1; // Integer
			default:
				return 3; // String
		}
	}

}
?>
