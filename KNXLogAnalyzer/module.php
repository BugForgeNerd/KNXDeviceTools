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

class KNXLogAnalyzer extends IPSModuleStrict
{

	/**
	 * Create
	 *
	 * Wird beim Erstellen der Modulinstanz aufgerufen.
	 * - Registriert Modul-Eigenschaften für Logpfad, Debugmodus und Spaltenanzeige
	 * - Registriert Attribute für Theme, UI-Einstellungen, zuletzt gewählte Datei und Relationstabellen
	 * - Initialisiert die Tile-Visualisierung
	 *
	 * Parameter: keine
	 * Rückgabewert: void
	 */
    public function Create(): void
    {
        parent::Create();

        $this->RegisterPropertyString('LogPath', IPS_GetKernelDir() . 'logs/knx/');
        $this->RegisterPropertyBoolean('DebugActive', false);

        $this->RegisterPropertyBoolean('ShowGAText', true);
        $this->RegisterPropertyBoolean('ShowPAText', true);
        $this->RegisterPropertyBoolean('ShowPayload', true);
        $this->RegisterPropertyBoolean('ShowAPCI', true);
        $this->RegisterPropertyBoolean('ShowLen', true);

        $this->RegisterAttributeString('Theme', 'dark');
        $this->RegisterAttributeString('UI', 'compact');
        $this->RegisterAttributeString('LastFile', '');
        $this->RegisterAttributeString('GARelations', '{}');
        $this->RegisterAttributeString('PARelations', '{}');

        $this->SetVisualizationType(1);
    }

	/**
	 * ApplyChanges
	 *
	 * Wird nach dem Laden oder Ändern der Instanzkonfiguration aufgerufen.
	 * - Prüft den konfigurierten Logpfad
	 * - Setzt den Instanzstatus abhängig von der Verfügbarkeit des Logverzeichnisses
	 * - Schreibt optional Debuginformationen
	 *
	 * Parameter: keine
	 * Rückgabewert: void
	 */
    public function ApplyChanges(): void
    {
        parent::ApplyChanges();

        $path = $this->NormalizePath($this->ReadPropertyString('LogPath'));
        if ($this->ReadPropertyBoolean('DebugActive')) {
            $this->SendDebug('ApplyChanges', 'LogPath=' . $path, 0);
        }

        if ($path === '' || !is_dir($path)) {
            $this->SetStatus(201);
            if ($this->ReadPropertyBoolean('DebugActive')) {
                $this->SendDebug('ApplyChanges', $this->Translate('Log folder not found'), 0);
            }
            return;
        }

        $this->SetStatus(102);
    }

	/**
	 * GetConfigurationForm
	 *
	 * Erstellt das Konfigurationsformular der Modulinstanz.
	 * - Definiert Eingabefelder für Logpfad und Anzeigeoptionen
	 * - Prüft die installierte IP-Symcon-Version
	 * - Stellt Bedien- und Prüfaktionen bereit
	 *
	 * Parameter: keine
	 * Rückgabewert: JSON-kodierte Formularbeschreibung
	 */
    public function GetConfigurationForm(): string
    {
        $kernel = IPS_GetKernelVersion();
        $warnings = [];

        if (version_compare($kernel, '8.2', '<')) {
            $warnings[] = [
                'type'    => 'Label',
                'caption' => $this->Translate('Note: This module is intended for IP-Symcon 8.2 or newer. Current version:') . ' ' . $kernel,
                'color'   => '8B2500'
            ];
        }

        return json_encode([
            'elements' => array_merge($warnings, [
                [
                    'type'    => 'ValidationTextBox',
                    'name'    => 'LogPath',
                    'caption' => $this->Translate('Folder of KNX log files')
                ],
                [
                    'type'    => 'CheckBox',
                    'name'    => 'DebugActive',
                    'caption' => $this->Translate('Enable debug output')
                ],
                [
                    'type'    => 'ExpansionPanel',
                    'caption' => $this->Translate('Table columns'),
                    'expanded' => true,
                    'items'   => [
                        [
                            'type'    => 'CheckBox',
                            'name'    => 'ShowGAText',
                            'caption' => $this->Translate('Show GA long text')
                        ],
                        [
                            'type'    => 'CheckBox',
                            'name'    => 'ShowPAText',
                            'caption' => $this->Translate('Show PA long text')
                        ],
                        [
                            'type'    => 'CheckBox',
                            'name'    => 'ShowPayload',
                            'caption' => $this->Translate('Show payload')
                        ],
                        [
                            'type'    => 'CheckBox',
                            'name'    => 'ShowAPCI',
                            'caption' => $this->Translate('Show write/read')
                        ],
                        [
                            'type'    => 'CheckBox',
                            'name'    => 'ShowLen',
                            'caption' => $this->Translate('Show LEN')
                        ]
                    ]
                ]
            ]),
            'actions' => [
                [
                    'type'    => 'Button',
                    'caption' => $this->Translate('Check log files'),
                    'onClick' => 'echo KNXLOGAN_TestConfiguration($id);'
                ],
                [
                    'type'    => 'Label',
                    'caption' => $this->Translate('Files matching the pattern *_KNXTrafficLog.jsonl are read. Long texts are stored in the instance.')
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
            ]
        ]);
    }

	/**
	 * GetVisualizationTile
	 *
	 * Erstellt die HTML-Tile-Ansicht des Moduls.
	 * - Lädt die Visualisierungsvorlage aus module.html
	 * - Erzeugt die Initialdaten für die Anzeige
	 * - Übergibt die Daten Base64-kodiert an das Frontend
	 *
	 * Parameter: keine
	 * Rückgabewert: HTML-Code der Visualisierung
	 */
    public function GetVisualizationTile(): string
    {
        $file = __DIR__ . '/module.html';
        if (!is_file($file)) {
            return '<div style="padding:12px;color:red">' . $this->Translate('module.html is missing') . '</div>';
        }

        $html = file_get_contents($file);
        $state = $this->BuildView([]);
        if ($this->ReadPropertyBoolean('DebugActive')) {
            $this->SendDebug('Tile', $this->Translate('Initial data created'), 0);
        }

        return str_replace('%%INITIAL_STATE_B64%%', base64_encode($this->Json($state)), $html);
    }

	/**
	 * RequestAction
	 *
	 * Verarbeitet Aktionen aus der Tile-Visualisierung.
	 * - Lädt Ansichten neu
	 * - Speichert Benutzereinstellungen
	 * - Speichert Gruppen- und physikalische Adressrelationen
	 * - Aktualisiert die Darstellung
	 *
	 * Parameter:
	 * - Ident: Aktionskennung
	 * - Value: Übergebene Aktionsdaten
	 *
	 * Rückgabewert: void
	 */
    public function RequestAction(string $Ident, mixed $Value): void
    {
        if ($this->ReadPropertyBoolean('DebugActive')) {
            $this->SendDebug('RequestAction', $Ident . ' => ' . $this->DebugValue($Value), 0);
        }

        $data = $this->NormalizeRequestValue($Value);

        switch ($Ident) {
            case 'Load':
                $this->SendView($this->BuildView($data));
                return;

            case 'SaveSettings':
                $this->SaveSettings($data);
                $this->SendView($this->BuildView($data));
                return;

            case 'SaveRelations':
                $result = $this->SaveRelations($data);
                $view = is_array($data['view'] ?? null) ? $data['view'] : $data;
                $this->SendView($this->BuildView($view), $result['ok'] ? ($this->Translate('Saved:') . ' ' . (string)$result['count']) : (string)$result['error']);
                return;
        }

        $this->SendView([
            'ok'    => false,
            'error' => $this->Translate('Invalid action:') . ' ' . $Ident
        ]);
    }

	/**
	 * SendView
	 *
	 * Überträgt einen Datenzustand an die Tile-Visualisierung.
	 * - Ergänzt optionale Statusmeldungen
	 * - Aktualisiert die Visualisierung
	 * - Protokolliert Fehler bei aktivem Debugmodus
	 *
	 * Parameter:
	 * - state: Zu übertragender Ansichtsstatus
	 * - modalInfo: Optionale Meldung für die Oberfläche
	 *
	 * Rückgabewert: void
	 */
    private function SendView(array $state, string $modalInfo = ''): void
    {
        if ($modalInfo !== '') {
            $state['modalInfo'] = $modalInfo;
        }

        try {
            $this->UpdateVisualizationValue($this->Json($state));
        } catch (Throwable $e) {
            if ($this->ReadPropertyBoolean('DebugActive')) {
                $this->SendDebug('TileUpdate', $e->getMessage(), 0);
            }
        }
    }

	/**
	 * NormalizeRequestValue
	 *
	 * Wandelt übergebene Aktionsdaten in ein Array um.
	 * - Akzeptiert Arrays direkt
	 * - Dekodiert JSON-Strings
	 * - Liefert bei ungültigen Daten ein leeres Array zurück
	 *
	 * Parameter:
	 * - value: Zu normalisierende Eingabedaten
	 *
	 * Rückgabewert: Normalisierte Daten als Array
	 */
    private function NormalizeRequestValue(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }

	/**
	 * DebugValue
	 *
	 * Wandelt beliebige Werte für Debugausgaben in Text um.
	 * - Unterstützt Strings, Skalare, Nullwerte und Arrays
	 * - Nutzt JSON für komplexe Datentypen
	 *
	 * Parameter:
	 * - value: Zu protokollierender Wert
	 *
	 * Rückgabewert: Formatierter Text
	 */
    private function DebugValue(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }
        if (is_scalar($value) || $value === null) {
            return (string)$value;
        }
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

	/**
	 * TestConfiguration
	 *
	 * Prüft die Konfiguration des Moduls.
	 * - Kontrolliert den konfigurierten Logordner
	 * - Ermittelt die Anzahl verfügbarer KNX-Logdateien
	 * - Liefert eine Statusmeldung zurück
	 *
	 * Parameter: keine
	 * Rückgabewert: Ergebnistext der Prüfung
	 */
    public function TestConfiguration(): string
    {
        $path = $this->NormalizePath($this->ReadPropertyString('LogPath'));

        if ($path === '') {
            return $this->Translate('No log folder configured.');
        }

        if (!is_dir($path)) {
            return $this->Translate('Log folder not found:') . ' ' . $path;
        }

        return 'OK: ' . count($this->GetLogFiles()) . ' ' . $this->Translate('KNX log files found.');
    }

	/**
	 * BuildView
	 *
	 * Erstellt den vollständigen Datenzustand für die Visualisierung.
	 * - Lädt Logdateien und KNX-Daten
	 * - Verarbeitet Filter, Seiten und Einstellungen
	 * - Ermittelt Statistiken und Metadaten
	 * - Bereitet die Darstellung für das Frontend auf
	 *
	 * Parameter:
	 * - request: Benutzereinstellungen und Filterdaten
	 *
	 * Rückgabewert: Vollständiger Ansichtsstatus als Array
	 */
    private function BuildView(array $request): array
    {
        $start = microtime(true);

        $files = $this->GetLogFiles();
        $file = basename((string)($request['file'] ?? $this->ReadAttributeString('LastFile')));
        if ($file === '' || !isset($files[$file])) {
            $file = array_key_first($files) ?: '';
        }
        if ($file !== '') {
            $this->WriteAttributeString('LastFile', $file);
        }

        $theme = (string)($request['theme'] ?? $this->ReadAttributeString('Theme'));
        $ui = (string)($request['ui'] ?? $this->ReadAttributeString('UI'));
        if (!in_array($theme, ['dark', 'light'], true)) {
            $theme = 'dark';
        }
        if (!in_array($ui, ['compact', 'standard'], true)) {
            $ui = 'compact';
        }
        $this->WriteAttributeString('Theme', $theme);
        $this->WriteAttributeString('UI', $ui);

        $pageSize = (int)($request['pageSize'] ?? 50);
        if (!in_array($pageSize, [20, 50, 100, 200, 500, 1000, 2000, 3000], true)) {
            $pageSize = 50;
        }

        $page = max(1, (int)($request['page'] ?? 1));
        $filters = is_array($request['filters'] ?? null) ? $request['filters'] : [];
        $filters = [
            'ga'   => (string)($filters['ga'] ?? ''),
            'pa'   => (string)($filters['pa'] ?? ''),
            'apci' => (string)($filters['apci'] ?? '')
        ];

        $gaText = $this->GetRelations('ga');
        $paText = $this->GetRelations('pa');
        $rows = [];
        $error = '';
        $fileInfo = null;

        if ($file !== '' && isset($files[$file])) {
            $fileInfo = $files[$file];
            $rows = $this->ReadRows($fileInfo['path'], $gaText, $paText, $error);
        }

        $filterOptions = $this->BuildFilterOptions($rows, $filters);
        $filtered = $this->ApplyFilters($rows, $filters);
        $total = count($filtered);
        $pages = max(1, (int)ceil($total / max(1, $pageSize)));
        $page = min($page, $pages);
        $offset = ($page - 1) * $pageSize;
        $pageRows = array_slice($filtered, $offset, $pageSize);

        if ($this->ReadPropertyBoolean('DebugActive')) {
            $this->SendDebug('BuildView', 'File=' . $file . ', Total=' . count($rows) . ', Filtered=' . $total . ', Page=' . $page, 0);
        }

        return [
            'ok' => $error === '',
            'error' => $error,
            'settings' => [
                'theme' => $theme,
                'ui' => $ui,
                'pageSize' => $pageSize,
                'page' => $page,
                'filters' => $filters
            ],
            'columns' => $this->GetColumns(),
            'files' => array_values($files),
            'selectedFile' => $file,
            'fileInfo' => $fileInfo,
            'rows' => $pageRows,
            'filterOptions' => $filterOptions,
            'relations' => [
                'ga' => $gaText,
                'pa' => $paText
            ],
            'texts' => $this->GetFrontendTexts(),
            'meta' => [
                'total' => count($rows),
                'filtered' => $total,
                'page' => $page,
                'pages' => $pages,
                'from' => $total > 0 ? $offset + 1 : 0,
                'to' => min($offset + $pageSize, $total),
                'loadMs' => (int)round((microtime(true) - $start) * 1000),
                'stand' => date('Y-m-d H:i:s')
            ]
        ];
    }

	/**
	 * GetColumns
	 *
	 * Ermittelt die sichtbaren Tabellenspalten.
	 * - Berücksichtigt die Modulkonfiguration
	 * - Liefert die Sichtbarkeit aller Spalten zurück
	 *
	 * Parameter: keine
	 * Rückgabewert: Spaltenkonfiguration als Array
	 */
    private function GetColumns(): array
    {
        return [
            'time'    => true,
            'ga'      => true,
            'gaText'  => $this->ReadPropertyBoolean('ShowGAText'),
            'pa'      => true,
            'paText'  => $this->ReadPropertyBoolean('ShowPAText'),
            'payload' => $this->ReadPropertyBoolean('ShowPayload'),
            'apci'    => $this->ReadPropertyBoolean('ShowAPCI'),
            'len'     => $this->ReadPropertyBoolean('ShowLen')
        ];
    }

	/**
	 * GetLogFiles
	 *
	 * Liest alle verfügbaren KNX-Logdateien ein.
	 * - Sucht Dateien nach dem definierten Namensmuster
	 * - Ermittelt Größe und Zeitinformationen
	 * - Bereitet die Dateiliste für die Oberfläche auf
	 *
	 * Parameter: keine
	 * Rückgabewert: Liste gefundener Logdateien
	 */
    private function GetLogFiles(): array
    {
        $path = $this->NormalizePath($this->ReadPropertyString('LogPath'));
        if ($path === '' || !is_dir($path)) {
            return [];
        }

        $pattern = rtrim($path, '/\\') . DIRECTORY_SEPARATOR . '*_KNXTrafficLog.jsonl';
        $list = glob($pattern) ?: [];
        rsort($list, SORT_STRING);

        $files = [];
        foreach ($list as $fullPath) {
            if (!is_file($fullPath)) {
                continue;
            }
            $name = basename($fullPath);
            $timeText = $this->TimeFromFileName($name);
            $size = filesize($fullPath);
            $sizeText = $this->FormatBytes($size === false ? 0 : $size);
            $files[$name] = [
                'name' => $name,
                'path' => $fullPath,
                'size' => $size === false ? 0 : $size,
                'sizeText' => $sizeText,
                'timeText' => $timeText,
                'label' => $timeText . ' · ' . $sizeText
            ];
        }

        return $files;
    }

	/**
	 * ReadRows
	 *
	 * Liest eine KNX-Logdatei zeilenweise ein.
	 * - Dekodiert JSONL-Einträge
	 * - Ergänzt Gruppen- und physikalische Adressbezeichnungen
	 * - Filtert Systemeinträge aus
	 * - Bereitet Datensätze für die Anzeige auf
	 *
	 * Parameter:
	 * - file: Zu lesende Logdatei
	 * - gaText: Gruppenadress-Texte
	 * - paText: Physikalische Adress-Texte
	 * - error: Rückgabe möglicher Fehlertexte
	 *
	 * Rückgabewert: Datensatzliste
	 */
    private function ReadRows(string $file, array $gaText, array $paText, string &$error): array
    {
        $rows = [];
        $handle = @fopen($file, 'rb');
        if (!$handle) {
            $error = $this->Translate('File could not be opened:') . ' ' . $file;
            if ($this->ReadPropertyBoolean('DebugActive')) {
                $this->SendDebug('ReadRows', $error, 0);
            }
            return [];
        }

        $lineNo = 0;
        while (($line = fgets($handle)) !== false) {
            $lineNo++;
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $data = json_decode($line, true);
            if (!is_array($data)) {
                if ($this->ReadPropertyBoolean('DebugActive')) {
                    $this->SendDebug('ReadRows', $this->Translate('Invalid JSON line') . ' ' . $lineNo, 0);
                }
                continue;
            }

            if (($data['type'] ?? '') === 'system') {
                continue;
            }

            $ga = (string)($data['ga'] ?? '');
            $pa = (string)($data['pa'] ?? '');

            $rows[] = [
                'time' => $this->FormatTime($data),
                'ga' => $ga,
                'gaText' => $gaText[$ga] ?? '',
                'pa' => $pa,
                'paText' => $paText[$pa] ?? '',
                'payload' => (string)($data['payload'] ?? ''),
                'apci' => (string)($data['apci'] ?? ''),
                'len' => (string)($data['len'] ?? '')
            ];
        }
        fclose($handle);

        return array_reverse($rows);
    }

	/**
	 * BuildFilterOptions
	 *
	 * Ermittelt die verfügbaren Filterwerte für die Oberfläche.
	 * - Erstellt Optionen für GA, PA und APCI
	 * - Berücksichtigt aktive Filter
	 *
	 * Parameter:
	 * - rows: Datensätze
	 * - active: Aktive Filter
	 *
	 * Rückgabewert: Filteroptionen als Array
	 */
    private function BuildFilterOptions(array $rows, array $active): array
    {
        return [
            'ga' => $this->BuildOneFilter($rows, 'ga', $active),
            'pa' => $this->BuildOneFilter($rows, 'pa', $active),
            'apci' => $this->BuildOneFilter($rows, 'apci', $active)
        ];
    }

	/**
	 * BuildOneFilter
	 *
	 * Erzeugt die Auswahlwerte eines einzelnen Filters.
	 * - Ermittelt vorkommende Werte
	 * - Zählt deren Häufigkeit
	 * - Bereitet die Dropdownliste auf
	 *
	 * Parameter:
	 * - rows: Datensätze
	 * - field: Zu analysierendes Feld
	 * - active: Aktive Filter
	 *
	 * Rückgabewert: Filteroptionen
	 */
    private function BuildOneFilter(array $rows, string $field, array $active): array
    {
        $other = $active;
        $other[$field] = '';
        $base = $this->ApplyFilters($rows, $other);

        $counts = [];
        foreach ($base as $row) {
            $value = (string)($row[$field] ?? '');
            if ($value === '') {
                continue;
            }
            $counts[$value] = ($counts[$value] ?? 0) + 1;
        }

        ksort($counts, SORT_NATURAL);

        $options = [[
            'value' => '',
            'label' => $this->Translate('All') . ' [' . count($base) . ']'
        ]];

        foreach ($counts as $value => $count) {
            $options[] = [
                'value' => $value,
                'label' => $value . ' [' . $count . ']'
            ];
        }

        return $options;
    }

	/**
	 * ApplyFilters
	 *
	 * Filtert Datensätze anhand der gewählten Kriterien.
	 * - Unterstützt Gruppenadresse, physikalische Adresse und APCI
	 * - Entfernt nicht passende Datensätze
	 *
	 * Parameter:
	 * - rows: Zu filternde Datensätze
	 * - filters: Aktive Filter
	 *
	 * Rückgabewert: Gefilterte Datensatzliste
	 */
    private function ApplyFilters(array $rows, array $filters): array
    {
        return array_values(array_filter($rows, static function (array $row) use ($filters): bool {
            foreach (['ga', 'pa', 'apci'] as $field) {
                $wanted = (string)($filters[$field] ?? '');
                if ($wanted !== '' && (string)($row[$field] ?? '') !== $wanted) {
                    return false;
                }
            }
            return true;
        }));
    }

	/**
	 * SaveSettings
	 *
	 * Speichert Benutzereinstellungen der Visualisierung.
	 * - Speichert Theme, UI-Modus und zuletzt gewählte Datei
	 * - Validiert zulässige Werte
	 *
	 * Parameter:
	 * - data: Zu speichernde Einstellungen
	 *
	 * Rückgabewert: void
	 */
    private function SaveSettings(array $data): void
    {
        if (isset($data['theme']) && in_array($data['theme'], ['dark', 'light'], true)) {
            $this->WriteAttributeString('Theme', $data['theme']);
        }
        if (isset($data['ui']) && in_array($data['ui'], ['compact', 'standard'], true)) {
            $this->WriteAttributeString('UI', $data['ui']);
        }
        if (isset($data['file'])) {
            $this->WriteAttributeString('LastFile', basename((string)$data['file']));
        }
    }

	/**
	 * SaveRelations
	 *
	 * Speichert Gruppen- oder physikalische Adressbeziehungen.
	 * - Validiert den Relationstyp
	 * - Bereinigt die Eingabedaten
	 * - Speichert die Zuordnungen in Attributen
	 *
	 * Parameter:
	 * - data: Zu speichernde Relationstabellen
	 *
	 * Rückgabewert: Ergebnisstatus als Array
	 */
    private function SaveRelations(array $data): array
    {
        $type = strtolower((string)($data['type'] ?? ''));
        if (!in_array($type, ['ga', 'pa'], true)) {
            return ['ok' => false, 'error' => $this->Translate('Invalid relation type')];
        }

        $items = $this->CleanRelations($data['items'] ?? []);
        $this->WriteAttributeString($type === 'ga' ? 'GARelations' : 'PARelations', $this->Json($items));
        if ($this->ReadPropertyBoolean('DebugActive')) {
            $this->SendDebug('SaveRelations', strtoupper($type) . ': ' . count($items), 0);
        }

        return ['ok' => true, 'count' => count($items)];
    }

	/**
	 * GetRelations
	 *
	 * Liest gespeicherte Adressrelationen aus den Attributen.
	 * - Unterstützt Gruppen- und physikalische Adressen
	 * - Dekodiert gespeicherte JSON-Daten
	 *
	 * Parameter:
	 * - type: Relationstyp (ga oder pa)
	 *
	 * Rückgabewert: Relationstabelle als Array
	 */
    private function GetRelations(string $type): array
    {
        $json = $type === 'ga' ? $this->ReadAttributeString('GARelations') : $this->ReadAttributeString('PARelations');
        $data = json_decode($json, true);
        return is_array($data) ? $data : [];
    }

	/**
	 * CleanRelations
	 *
	 * Bereinigt und sortiert Relationstabellen.
	 * - Entfernt leere Schlüssel und Werte
	 * - Sortiert die Einträge natürlich
	 *
	 * Parameter:
	 * - items: Zu bereinigende Relationstabelle
	 *
	 * Rückgabewert: Bereinigte Relationstabelle
	 */
    private function CleanRelations(mixed $items): array
    {
        $out = [];
        if (is_array($items)) {
            foreach ($items as $key => $value) {
                $key = trim((string)$key);
                $value = trim((string)$value);
                if ($key !== '' && $value !== '') {
                    $out[$key] = $value;
                }
            }
        }
        ksort($out, SORT_NATURAL);
        return $out;
    }

	/**
	 * NormalizePath
	 *
	 * Normalisiert einen Dateipfad.
	 * - Entfernt führende und nachfolgende Leerzeichen
	 * - Ergänzt einen abschließenden Verzeichnistrenner
	 *
	 * Parameter:
	 * - path: Zu normalisierender Pfad
	 *
	 * Rückgabewert: Normalisierter Pfad
	 */
    private function NormalizePath(string $path): string
    {
        $path = trim($path);
        if ($path === '') {
            return '';
        }
        return rtrim($path, '/\\') . DIRECTORY_SEPARATOR;
    }

	/**
	 * TimeFromFileName
	 *
	 * Extrahiert Datum und Uhrzeit aus einem Logdateinamen.
	 * - Wandelt das Dateinamensformat in ein lesbares Zeitformat um
	 *
	 * Parameter:
	 * - name: Dateiname
	 *
	 * Rückgabewert: Formatierter Zeitstempel
	 */
    private function TimeFromFileName(string $name): string
    {
        if (preg_match('/^(\d{8})-(\d{6})_KNXTrafficLog\.jsonl$/', $name, $m)) {
            return substr($m[1], 0, 4) . '-' . substr($m[1], 4, 2) . '-' . substr($m[1], 6, 2) . ' ' . substr($m[2], 0, 2) . ':' . substr($m[2], 2, 2) . ':' . substr($m[2], 4, 2);
        }
        return $name;
    }

	/**
	 * FormatTime
	 *
	 * Formatiert Zeitinformationen eines Logeintrags.
	 * - Unterstützt Unix-Zeitstempel und KNX-Zeitformate
	 * - Erzeugt eine lesbare Datums- und Uhrzeitdarstellung
	 *
	 * Parameter:
	 * - data: Datensatz mit Zeitinformationen
	 *
	 * Rückgabewert: Formatierter Zeitstempel
	 */
    private function FormatTime(array $data): string
    {
        if (isset($data['unix']) && is_numeric($data['unix'])) {
            return date('d.m.Y H:i:s', (int)$data['unix']);
        }
        $ts = (string)($data['ts'] ?? '');
        if (preg_match('/^(\d{8})-(\d{6})$/', $ts, $m)) {
            return substr($m[1], 6, 2) . '.' . substr($m[1], 4, 2) . '.' . substr($m[1], 0, 4) . ' ' . substr($m[2], 0, 2) . ':' . substr($m[2], 2, 2) . ':' . substr($m[2], 4, 2);
        }
        return $ts;
    }

	/**
	 * FormatBytes
	 *
	 * Formatiert eine Byte-Anzahl in eine lesbare Darstellung.
	 * - Unterstützt Byte, Kilobyte und Megabyte
	 *
	 * Parameter:
	 * - bytes: Größe in Byte
	 *
	 * Rückgabewert: Formatierte Größenangabe
	 */
    private function FormatBytes(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2, ',', '.') . ' MB';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 2, ',', '.') . ' KB';
        }
        return $bytes . ' B';
    }

	/**
	 * GetFrontendTexts
	 *
	 * Erstellt die übersetzbaren Textbausteine für die Tile-Visualisierung.
	 * - Übergibt alle sichtbaren Frontend-Texte zentral an JavaScript
	 * - Nutzt die Symcon-Lokalisierung über locale.json
	 * - Vermeidet fest verdrahtete Oberflächentexte im Frontend
	 *
	 * Parameter: keine
	 * Rückgabewert: Übersetzte Frontend-Texte als Array
	 */
    private function GetFrontendTexts(): array
    {
        $keys = [
            'Log file',
            'Rows per page',
            'GA',
            'PA',
            'APCI',
            'Theme:',
            'UI:',
            'Dark',
            'Light',
            'Compact',
            'Standard',
            'Apply filter',
            'Refresh',
            'Newer',
            'Older',
            'GA long texts',
            'PA long texts',
            'Long texts',
            'Close',
            'Paste CSV: Address;Long text or Address,Long text. Saving replaces the assignments of this type.',
            'CSV import',
            'Current assignments',
            'Apply CSV',
            'Save',
            'Date/Time',
            'GA long text',
            'PA long text',
            'Payload',
            'Write/Read',
            'LEN',
            'No entries found.',
            'File:',
            'Size:',
            'Page:',
            'Range:',
            'of',
            'Total:',
            'Load time:',
            'Updated:',
            'CSV applied.'
        ];

        $texts = [];
        foreach ($keys as $key) {
            $texts[$key] = $this->Translate($key);
        }

        return $texts;
    }

	/**
	 * Json
	 *
	 * Kodiert Daten als JSON-String.
	 * - Verwendet UTF-8 ohne Escaping von Unicode-Zeichen
	 * - Erhält Schrägstriche unverändert
	 *
	 * Parameter:
	 * - data: Zu kodierende Daten
	 *
	 * Rückgabewert: JSON-String
	 */
    private function Json(mixed $data): string
    {
        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
?>
