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


class KNXDeviceTrigger extends IPSModule
{

    /**
     * Erzeugt das Modul, registriert die Eigenschaften und stellt die Verbindung zum übergeordneten KNX-Modul her.
     */
    public function Create()
    {
        parent::Create();

        // KNX Parent verbinden
        $this->ConnectParent("{1C902193-B044-43B8-9433-419F09C641B8}");

        // Eigenschaften
        $this->RegisterPropertyBoolean('Active', false);
        $this->RegisterPropertyString('DeviceList', '[]');
    }

    /**
     * Wird aufgerufen, wenn Änderungen an den Modul-Eigenschaften vorgenommen werden.
     * Validiert die Geräteliste, wenn das Modul aktiv ist.
     */
    public function ApplyChanges()
    {
        parent::ApplyChanges();

        if ($this->ReadPropertyBoolean('Active')) {
            $this->ValidateDeviceList();
        }
    }

    /**
     * Validiert die Einträge in der `DeviceList`-Eigenschaft.
     * Gibt Warnungen aus, wenn ungültige Funktionen angegeben sind, blockiert jedoch nicht den Speichervorgang.
     * Zeigt ein Popup mit den Warnungen im WebFront an.
     */
    private function ValidateDeviceList()
    {
        $list = json_decode($this->ReadPropertyString('DeviceList'), true);

        if (!is_array($list)) {
            $this->SendDebug('DeviceList', 'Ungültige DeviceList!', 0);
            return;
        }

        $warnText = "";

        foreach ($list as $index => $row) {
            $function = trim($row['Function']);

            if ($function !== '' && !function_exists($function)) {
                $warnText .= "Zeile " . ($index+1) . ": Funktion '{$function}' existiert nicht!\n";

                // Debug & Log
                $this->SendDebug(
                    "Warnung",
                    "Zeile " . ($index+1) . ": Funktion '{$function}' existiert nicht! Eintrag wird gespeichert.",
                    0
                );

                IPS_LogMessage(
                    "KNXDeviceTrigger",
                    "Zeile " . ($index+1) . ": Funktion '{$function}' existiert nicht! Eintrag wird gespeichert."
                );
            }
        }

        if ($warnText !== "") {
            // JS-Popup im WebFront / Designer anzeigen
            echo "<script type='text/javascript'>alert(`{$warnText}`);</script>";
        }
    }

    /**
     * Verarbeitet empfangene KNX-Telegramme und prüft, ob die empfangenen Adressen mit der `DeviceList` übereinstimmen.
     * Wenn ja, wird die entsprechende Funktion aufgerufen.
     */
	public function ReceiveData($JSONString)
	{
		if (!$this->ReadPropertyBoolean('Active')) return;

		$data = json_decode($JSONString, true);
		if (!is_array($data)) return;

		// --- Schutz gegen nicht existierende Keys ---
		$GA1 = $data['GroupAddress1'] ?? '';
		$GA2 = $data['GroupAddress2'] ?? '';
		$GA3 = $data['GroupAddress3'] ?? '';
		$PA1 = $data['PhysicalAddress1'] ?? '';
		$PA2 = $data['PhysicalAddress2'] ?? '';
		$PA3 = $data['PhysicalAddress3'] ?? '';

		// KNX-Adressen
		$ga = "$GA1/$GA2/$GA3";
		$pa = "$PA1.$PA2.$PA3";

		$this->SendDebug('Receive', "Telegramm: GA=$ga PA=$pa", 0);

		// Liste der Einträge laden
		$list = json_decode($this->ReadPropertyString('DeviceList'), true);
		if (!is_array($list)) return;

		foreach ($list as $entry)
		{
			$gaMatch = ($entry['GA'] === '' || $entry['GA'] === $ga);
			$paMatch = ($entry['PA'] === '' || $entry['PA'] === $pa);

			// Wenn GA/PA NICHT matchen → nächsten Eintrag
			if (!$gaMatch || !$paMatch)
				continue;

			$instanceID = intval($entry['ModuleID']);
			$function = trim($entry['Function']);

			if ($instanceID <= 0 || $function === '')
				continue;

			$this->SendDebug('Trigger', "Auswahl passt → Funktion: {$function}({$instanceID})", 0);

			// Instanz prüfen
			if (!IPS_InstanceExists($instanceID)) {
				$this->SendDebug('Error', "Instanz existiert nicht: $instanceID", 0);
				continue;
			}

			// Funktion prüfen
			if (!function_exists($function)) {
				$this->SendDebug('Error', "Funktion '$function' existiert nicht!", 0);
				continue;
			}

			// --- FUNKTION AUSFÜHREN ---
			try {
				$function($instanceID);
			}
			catch (Exception $e) {
				$this->SendDebug('Exception', $e->getMessage(), 0);
			}
		}
	}


    /**
     * Gibt das Konfigurationsformular für das Modul zurück.
     * Ermöglicht es dem Benutzer, das Modul zu konfigurieren, einschließlich der Geräteliste und Funktionen.
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
				'color'   => '8B2500'
			];
		}

		// --- ursprüngliche Elemente ---
		$baseElements = [
			// Überschrift oben
			[
				'type'    => 'Label',
				'caption' => $this->Translate("Modulname")
			],
			[
				'type'    => 'CheckBox',
				'name'    => 'Active',
				'caption' => $this->Translate("Modul aktiv")
			],
			[
				'type'    => 'List',
				'name'    => 'DeviceList',
				'caption' => $this->Translate("Telegramm-Aktionen"),
				'rowCount'=> 6,
				'add'     => [
					'GA'        => '',
					'PA'        => '',
					'ModuleID'  => 0,
					'Function'  => ''
				],
				'delete'  => true,
				'columns' => [
					[
						'caption' => $this->Translate("Gruppenadresse"),
						'name'    => 'GA',
						'width'   => '130px',
						'add'     => '',
						'edit'    => ['type' => 'ValidationTextBox']
					],
					[
						'caption' => $this->Translate("Geräteadresse"),
						'name'    => 'PA',
						'width'   => '130px',
						'add'     => '',
						'edit'    => ['type' => 'ValidationTextBox']
					],
					[
						'caption' => $this->Translate("Modul auswählen"),
						'name'    => 'ModuleID',
						'width'   => '180px',
						'add'     => 0,
						'edit'    => ['type' => 'SelectInstance']
					],
					[
						'caption' => $this->Translate("Funktionsname (ohne Klammern)"),
						'name'    => 'Function',
						'width'   => '150px',
						'add'     => '',
						'edit'    => ['type' => 'ValidationTextBox']
					]
				]
			]
		];

		// --- Actions-Bereich unten ---
		$actions = [
			[
				'type'    => 'Label',
				'caption' => $this->Translate("Funktionshinweis")
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
						"image"   => "data:image/png;base64,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"
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

		// Warnung + normale Elemente zusammenführen, Actions extra setzen
		return json_encode([
			'elements' => array_merge($warnLabel, $baseElements),
			'actions'  => $actions
		]);
	}

}

?>
