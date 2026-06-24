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
 * - 
 * - 
*/

class KNXTrafficLogger extends IPSModuleStrict
{
    private $fileHandle = null;

    public function Create(): void
    {
        parent::Create();
		
		if ((float) IPS_GetKernelVersion() < 8.2) {
            $this->ConnectParent("{1C902193-B044-43B8-9433-419F09C641B8}");
        }
		
        $this->RegisterPropertyBoolean('Active', false);
        $this->RegisterPropertyInteger('MaxLogFiles', 7);
        $this->RegisterPropertyInteger('MaxLinesPerFile', 100000);
        $this->RegisterPropertyInteger('RotationHour', 1);
        $this->RegisterPropertyString('LogPath', IPS_GetKernelDir() . "logs/knx/");

        $this->RegisterVariableString("Status", "Status");

        $this->RegisterTimer("DailyRotation", 0, 'KNXTL_CheckRotation($_IPS["TARGET"]);');
    }
	
    public function ApplyChanges(): void
    {
        parent::ApplyChanges();

        $this->SetStatus(102);

        if (!$this->ReadPropertyBoolean('Active')) {
            $this->SetValue("Status", "Logging deaktiviert");
            $this->CloseFile();
            $this->SetTimerInterval("DailyRotation", 0);
            return;
        }

        $this->EnsureLogPath();
        $this->ScheduleNextRotation();
        $this->OpenCurrentLogFile();

        $this->SetValue("Status", "Logging aktiv");
    }

	public function ReceiveData(string $JSONString): string
	{
		if (!$this->ReadPropertyBoolean('Active')) {
			return '';
		}

		$this->SendDebug("ReceiveData", $JSONString, 0);

		$data = json_decode($JSONString, true);
		if (!is_array($data)) {
			$this->SendDebug("ReceiveData", "Ungültiges JSON", 0);
			return '';
		}

		$ga = ($data['GroupAddress1'] ?? '') . "/" .
			  ($data['GroupAddress2'] ?? '') . "/" .
			  ($data['GroupAddress3'] ?? '');

		$pa = ($data['PhysicalAddress1'] ?? '') . "." .
			  ($data['PhysicalAddress2'] ?? '') . "." .
			  ($data['PhysicalAddress3'] ?? '');

		$payload = $this->ToHex($data['Data'] ?? '');
		$raw     = $this->ToHex($data['RawData'] ?? '');	// wird aktuell nicht geliefert
		$apci = $this->DecodeAPCI($payload);

		$timestampUnix = time();
		$timestampHuman = date("Ymd-His", $timestampUnix);

		$entry = [
			"ts"   => $timestampHuman,
			"unix" => $timestampUnix,
			"ga"   => $ga,
			"pa"   => $pa,
			"payload" => $payload,
			"apci" => $apci,
			"len"  => strlen($payload) > 0 ? strlen($payload) / 2 : 0
		];
		
		// raw nur hinzufügen wenn vorhanden
		if ($raw !== '') {
			$entry["raw"] = $raw;
		}

		$this->WriteLogLine($entry);

		return '';
	}

	private function DecodeAPCI(string $hex): string
	{
		if ($hex === '' || strlen($hex) < 2) {
			return 'unknown';
		}

		$byte = hexdec(substr($hex, 0, 2));

		// obere 2 Bits auswerten
		$apci = $byte & 0xC0;

		if ($apci === 0x00) return 'read';
		if ($apci === 0x40) return 'response';
		if ($apci === 0x80) return 'write';

		return 'unknown';
	}

	private function ToHex($data): string
	{
		if ($data === null || $data === '') {
			return '';
		}

		// Fall 1: Array
		if (is_array($data)) {
			$bin = '';
			foreach ($data as $byte) {
				$bin .= chr((int)$byte);
			}
			return strtoupper(bin2hex($bin));
		}

		// Fall 2: String
		if (is_string($data)) {

			// prüfen ob bereits HEX
			if (ctype_xdigit($data) && strlen($data) % 2 === 0) {
				return strtoupper($data);
			}

			// sonst: binär → HEX
			return strtoupper(bin2hex($data));
		}

		// Fall 3: Zahl
		if (is_int($data)) {
			return strtoupper(dechex($data));
		}

		return '';
	}

    private function WriteLogLine(array $entry): void
    {
        if ($this->IsMaxLinesReached()) {
            return;
        }

        $handle = $this->GetFileHandle();
        if (!$handle) {
            return;
        }

        fwrite($handle, json_encode($entry, JSON_UNESCAPED_SLASHES) . PHP_EOL);

        $this->IncreaseLineCounter();
    }

    private function IsMaxLinesReached(): bool
    {
        $max = $this->ReadPropertyInteger("MaxLinesPerFile");
        $current = $this->GetLineCounter();

        if ($current >= $max) {

            $this->SendDebug("Logger", "MaxLines erreicht – Logging wird gestoppt", 0);

            $this->WriteSystemEntry("max_lines_reached", $current);

            $this->SetValue("Status", "Gestoppt – MaxLines erreicht");
            $this->CloseFile();

            return true;
        }

        return false;
    }

    private function WriteSystemEntry(string $event, int $lines): void
    {
        $handle = $this->GetFileHandle();
        if (!$handle) return;

        $entry = [
            "type"  => "system",
            "event" => $event,
            "lines" => $lines,
            "unix"  => time()
        ];

        fwrite($handle, json_encode($entry) . PHP_EOL);
    }

    public function CheckRotation(): void
    {
        if (!$this->ReadPropertyBoolean('Active')) return;

        $this->SendDebug("Rotation", "Rotation wird ausgeführt", 0);

        $this->CloseFile();
        $this->ResetLineCounter();
        $this->OpenNewLogFile();
        $this->CleanupOldFiles();

        $this->ScheduleNextRotation();
    }

    private function ScheduleNextRotation(): void
    {
        $hour = $this->ReadPropertyInteger("RotationHour");
        $next = strtotime("tomorrow $hour:00");

        $interval = ($next - time()) * 1000;
        $this->SetTimerInterval("DailyRotation", $interval);
    }

    private function EnsureLogPath(): void
    {
        $path = $this->ReadPropertyString("LogPath");

        if (!is_dir($path)) {
            mkdir($path, 0777, true);
        }
    }

    private function OpenCurrentLogFile(): void
    {
        $path = $this->ReadPropertyString("LogPath");
        $filename = $this->GetBuffer("CurrentFile");

        if ($filename == "") {
            $this->OpenNewLogFile();
            return;
        }

        $fullPath = $path . $filename;

        if (!file_exists($fullPath)) {
            $this->OpenNewLogFile();
            return;
        }

        $this->fileHandle = fopen($fullPath, 'a');
    }

    private function OpenNewLogFile(): void
    {
        $path = $this->ReadPropertyString("LogPath");
        $filename = date("Ymd-His") . "_KNXTrafficLog.jsonl";
        $fullPath = $path . $filename;

        $this->fileHandle = fopen($fullPath, 'a');

        $this->SetBuffer("CurrentFile", $filename);
        $this->ResetLineCounter();
    }

    private function CloseFile(): void
    {
        if ($this->fileHandle) {
            fclose($this->fileHandle);
            $this->fileHandle = null;
        }
    }

    private function GetFileHandle()
    {
        if (!$this->fileHandle) {
            $this->OpenCurrentLogFile();
        }
        return $this->fileHandle;
    }

    private function GetLineCounter(): int
    {
        return (int)$this->GetBuffer("LineCounter");
    }

    private function IncreaseLineCounter(): void
    {
        $this->SetBuffer("LineCounter", (string)($this->GetLineCounter() + 1));
    }

    private function ResetLineCounter(): void
    {
        $this->SetBuffer("LineCounter", "0");
    }

    private function CleanupOldFiles(): void
    {
        $path = $this->ReadPropertyString("LogPath");
        $files = glob($path . "*_KNXTrafficLog.jsonl");

        usort($files, fn($a, $b) => filemtime($a) - filemtime($b));

        $maxFiles = $this->ReadPropertyInteger("MaxLogFiles");

        while (count($files) > $maxFiles) {
            unlink(array_shift($files));
        }
    }

	public function GetConfigurationForm(): string
	{
		// --- Kernel-Version prüfen ---
		$kernel = IPS_GetKernelVersion();
		$warnLabel = [];

		if (version_compare($kernel, '8.2', '<')) {
			$warnLabel[] = [
				'type'    => 'Label',
				'caption' => $this->Translate('WARN_SYMPATH_VERSION') . $kernel,
				'color'   => '8B2500'
			];
		}

		// --- Form Elemente ---
		$baseElements = [
			[
				"type" => "CheckBox",
				"name" => "Active",
				"caption" => $this->Translate("ActiveLabel")
			],
			[
				"type" => "NumberSpinner",
				"name" => "MaxLogFiles",
				"caption" => $this->Translate("MaxLogFilesLabel")
			],
			[
				"type" => "NumberSpinner",
				"name" => "MaxLinesPerFile",
				"caption" => $this->Translate("MaxLinesPerFileLabel")
			],
			[
				"type" => "NumberSpinner",
				"name" => "RotationHour",
				"caption" => $this->Translate("RotationHourLabel")
			],
			[
				"type" => "ValidationTextBox",
				"name" => "LogPath",
				"caption" => $this->Translate("LogPathLabel")
			]
		];

		// --- Actions ---
		$actions = [
			[
				"type"    => "Label",
				"width"   => "50%",
				"caption" => $this->Translate('LICENSE_NOTICE')
			],
			[
				"type"    => "Label",
				"width"   => "50%",
				"bold"    => true,
				"caption" => $this->Translate('DONATION_HEADER')
			],
			[
				"type"    => "Label",
				"width"   => "50%",
				"caption" => $this->Translate('DONATION_TEXT')
			],
			[
				"type"  => "RowLayout",
				"items" => [
					[
						"type"    => "Image",
						"onClick" => "echo '" . $this->Translate('PAYPAL_LINK') . "';",
						"image"   => "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAADsAAAA6CAYAAAAOeSEWAAAAAXNSR0IArs4c6QAAAARnQU1BAACxjwv8YQUAAAAJcEhZcwAADsMAAA7DAcdvqGQAAAoiSURBVGhD7ZprkBTVFcd/5/bsC1heAvIKLAgsuwqYGI3ECPjAfAhRtOKjKjGlljwUTfIhSlZNrVtBVqIJsomFIBUrqZRVPkvFqphofMVoJEYSXrsrbxU1PFR2kd3Zmb4nH3qmp+fua4adjR/0V9U1955zunv+fe89t/t2wxcIcQ19p9ZQNeBiPEa7nh5RPiPhH8Kwh5Pje3ilLumG9JXCi62qvw3Puys4tDqn0EiZlC8dE/m12gI8D/4GdtT8GcTd8YQovNjqu1/EeOcFlajY7spp0nqci2P1eWLmB2y55WDEcUIY19BnRKZ3bsE0rqB0vbt4QGQ+SfvCSZWryl1XvhRW7Iz6YZAeq27rud25K4Fua0tgMjLjiGGl48ybwopN+JWISJc6IDI20+W0uGg5unPkghmuHzJr9dCIM28KK9bEqoLWiP757nC7sHuF0kJTdpHSo/H2c5ygvCisWHS6U8+uQqQVo60cjXO7eyTeyPiIM28KK1YkJTb1512tmlQ6WpVEC8HWCh2tmd+Olsh2TEkeV2yHhmNc6dPcW1ixms7EqdaR6DhUaDsM8cNC+xHCLR75zdoOC20Hhc8OCMfeVdqPKMNL26Kny5fCiZ1bWwpM7jrRpGx+e8SWB2qFjlbh0gs2sH7vNa47Vwon9mDZKRiJBZVI66axSQXtLWt1z+ByZXB5KcgGb/2u8113LhROrBBk4lSlU/KxiXRkhrCb58DUU9Il42vsrmxnbhROrGrkzsnNTN2I1S7iumPGaZmycBYP7j456s6FwoklnYnDenbrdiU2V8aPV8aPjVoEjU2LGnKhcGKFqkyDRrtzqnyiYsXARV0MUT9Z6pp6ozBiL7/cA6nsNNVEsR2Zcj5j9bw5ypguHo3FO+KaeqMwYrfNHI/IwIwh0qIAahW1GUN3Y9W9COfMVmaflW0L8Im17XSNvVEYsaZoujvTZBFt1Z5IX4SBA5XLLlHmnetGBCjNXF/V6pp7ozBisVVZVbflbC53eQIjRyoXnq8sWwxVlW5ABtE/uaZc6Kk9cqeq/gE8b0l2Bo6QOKpMHg2lJdlZuqgIBg6E4cNg7BgoH+Tu2RVKMjmLG6dscx29URix1b98GSNzXXOAwpxZltMrC3Mu5RmWVCx0zblQiG4soKlu3EWrKjBpXGGEwtESqz9xjbnSd7HTVw5HGBWo6kJTzIPBkUR94rRj7ZXxGybtcx250nexalLza3SOjTB0oHaaUvJFZR+auJClk//iuvKh72JN9M6Jzq07bHB2PR9UdqLcSssnM1gy9XXXnS99vORA9ap7MOanQaWLrnxWlXJ25Ca+Mx2oPg7SimoHokcwZg/t/iZumrwTKcwCOZ3/2QlQvepZjPmOaw6787e/oVROdJ0R9A4WT+rzMmku9L0bQxeLbJHGGN5LN07o311Tf9E3sWfUDkCoCCppgenOklpS7UmsKpQmd7jm/qJvYluLpyDidcrAacGDyhTPc3wRRI5w3dTDrrm/6JtYz0zKNjiie2pVAJXmQiag3ujhsufAxPkfEfc9fN2M1U3BZjPbzKnK2BE9LGzL8zx73zOutb/oezbuifX7HgAWu+YQ5VaWVNzrmvuLvnXj3nEytYNPk2vqT/pPrKqA9PBQio+Yza6xP+nfbrx2zyKMN8M1A6D2ZZZOetI1f8mXfEmPZI/ZyhVfh9g3s2zB6tkHeO0vsaPu42xfAVm3Zy5iZmIjS64YH5H38I6/lPdq4rq90xGZj7WCxp7ghgkHssVW370RYxZ0uXCmtIL/fbbXbMx2FIh1+99C9GuuOcVhlIUsqcj9mXbd3l8gcjsACb7FsorXo1OPQPpNnIK1cXzNLPiKlKPyALW1hZ+ual+KOZ8otEPWW/YRwG8j9RwwwfEUKDZNZM2zZ9SWgZkICqpxBhcNY9TxgST81IM5IDKWJ0uHhPVC8ZWJ4xAGBBX5iKGbyhl6bBCqkbsrqaJW87jQ4cU7yKIJH5M1ZivrZ1DkbQHAahM7lgcrhtNWnklxbBMIqI1TOrScycMsW3YvxOMCVMqA7SW27fG4lKWWOP3NJUYOxK1ZAIDYN2i87c3wXDNqx5Mo+17g023cfKVHzKQWvuVFFk+8EIB1+y9G9CkAlA9ZUjGONe+UUFp8BcI5qMbA/rvE2OfifvECDCD8jSGtW/m0vAW0GHiVxRXzAFJvygGP6Zmxqo2AMLfW46AsCmMs/xzEB0OObf/0aYq8VCIL9onLgFsxMhIAX+rjfuJJYrHVCGDlH8BsSL0E2172R2JmLgrY5FUYGROeQ2kChIf2lpDQa0M7vFa6Yc/Edms2AsE6jwjgEbfeYYyOAMCaH3FkSAueXxwcT5vTB4h2i6owKYmZR/XdTRwacAjjLQpaVRW1vz7WNuBhjARCrTZh7VqsvhEKBVDbxFWJt0F3BQ/xnMnMe0YBsPWMmzFmLghY/2Eab3sUidxWil7Bun1NdMhB4JLgeNiY2vvbffNUKBTZDKwFtiAEQgFsohmxkfFvwsWBjNjwsx5AGIbxpmFkKCJgbQLr34GRQ4jMD/6ovjxlwvuns2P5jZz61rlY+1a4v+83UVdnUR5JHdsjkVxQXH1XNZ4E603Wf48YNwEafKIQMgJhKkLwLkRoQ1maVEYjzApC9HEOTDiTxRXLKNKzQfaHexfFtmclO5HwYSMj1ka+ibD2aZL+Gnz7K6x/M76dRmPNSuCCIETBT96/67nfxAF47DEf2E3gsmh5cALfPhK+5BJZ2CGxhxApC2L8a9la80nwwBARq/owyhpU7kXtDfhMYWnFBjyTeSOtch91YgG4dlI7qu+m7Ed573cfZV089RrTxWDMXv6ox/a9wWt7VR9frqZ5eedJXMl8GWq8MCuXzVoxri0hFwUx+iG7ftwCQHPNNqpXbUfkVMR8N0yH6jfQePtfAVjffBJSGgwB5SiLK67uZvUiMwtYDb9hLF67r7oDzgZAdKfeWafy4HWVwazCMT5Y/346NmjZ/zRPQCRI/cqHNC8/lg7IQnRbMKwFjNxLVX091fUr2hJFb2JkWCoqvJJBF9B0Vw4s1m5jZHtNJqQ0822E8E43QkElmCkAPLOB9XvrWL9/VYfRVxGKghga5U4VlOqgrjupqwt6QCjWi75M1qbgT3ZBWfujWLsDFESG4sV+hvFuB4LMB+lsmiGhmWUX1TiqP+SVuszXX1ldrqeH+diDCAeCso4G+TnoLYjxwxCrTYzcNwrRVC/IjFdCsSr/xWoDNtmA2tXRgCz+VXcctXPwtR7VF7B2I8nkMkhchPUbsH4DXuL3kT2EGMuCooL6dTTWZD+wi+4GGoAGPP6Q5YuyZNxhtGM2yhqQF1GeArkGXy8L9tcGPPsExrfh8bDroofIvjcuMN5p9Zf66j2BIPj+a5z29rxUMvtc6D+x01eMwRRtwZgRqG0pMfar8a01e9yw/yd53Gvmi+nAk+sRXQh2zuct9AvH/wAcerqGMemSoQAAAABJRU5ErkJggg=="
					],
					[
						"type"    => "Label",
						"width"   => "70%",
						"caption" => $this->Translate('DONATION_INFO')
					]
				]
			],
			[
				"type"    => "Label",
				"caption" => $this->Translate('PAYPAL_LINK')
			]
		];

		return json_encode([
			'elements' => array_merge($warnLabel, $baseElements),
			'actions'  => $actions
		]);
	}
}
?>
