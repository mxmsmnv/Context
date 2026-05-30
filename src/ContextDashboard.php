<?php namespace ProcessWire;

/**
 * Admin dashboard renderer for the Context module.
 */
class ContextDashboard {

    /** @var Context */
    protected $module;

    public function __construct(Context $module) {
        $this->module = $module;
    }

    public function render(array $data) {
        $m = $this->module;

        $contextPath = $data['contextPath'];
        $exists = $data['exists'];
        $inventory = $data['inventory'];
        $stats = $data['stats'];
        $settingsUrl = $data['settingsUrl'];
        $csrfInput = $data['csrfInput'];
        $formatsLabel = $data['formatsLabel'];
        $folderSize = $data['folderSize'];
        $lastModified = $data['lastModified'];
        $fileCount = $exists ? $inventory['total_files'] : null;

        $timeAgo = '-';
        $timeAbsolute = '';
        if($lastModified) {
            $diff = time() - $lastModified;
            if($diff < 60) $timeAgo = $diff . 's ago';
            elseif($diff < 3600) $timeAgo = floor($diff / 60) . 'm ago';
            elseif($diff < 86400) $timeAgo = floor($diff / 3600) . 'h ago';
            elseif($diff < 604800) $timeAgo = floor($diff / 86400) . 'd ago';
            else $timeAgo = date('M j', $lastModified);
            $timeAbsolute = date('M j, H:i', $lastModified);
        }

        list($formatLabel, $formatClass) = $this->getFormatStatus($inventory);
        $healthItems = $this->getHealthItems($inventory, (bool)$m->generate_skill_md);

        $exportButtonLabel = $exists ? 'Re-Export Context' : 'Export Context';
        $exportSizeLabel = $exists ? $this->formatBytes($folderSize) : '-';
        $lastExportLabel = $timeAbsolute ?: 'Never';

        $out = $this->renderStyles();
        $out .= "<div class='ContextDash'>";
        $out .= $this->renderHero($contextPath, $exists, $settingsUrl, $csrfInput, $exportButtonLabel, $formatLabel, $formatClass, $timeAgo, $folderSize);
        $out .= $this->renderMetrics($stats, $exists, $fileCount, $inventory);
        $out .= $this->renderStatusAndCli($inventory, $formatLabel, $formatClass, $lastExportLabel, $exportSizeLabel, $healthItems);
        $out .= $this->renderConfiguration($formatsLabel);
        $out .= $this->renderExportPreview();
        $out .= $this->renderTips();
        $out .= "</div>";

        return $out;
    }

    protected function renderHero($contextPath, $exists, $settingsUrl, $csrfInput, $exportButtonLabel, $formatLabel, $formatClass, $timeAgo, $folderSize) {
        $m = $this->module;
        $out = '';
        $out .= "<div class='ContextDash-hero uk-margin'>";
        $out .= "<div class='uk-grid-small uk-flex-middle' uk-grid>";
        $out .= "<div class='uk-width-expand@m'>";
        $out .= "<div class='ContextDash-kicker'>Context Module " . $this->html(Context::VERSION) . "</div>";
        $out .= "<h2>AI-ready ProcessWire context</h2>";
        $out .= "<p>Export structure, templates, samples, prompts, and compact TOON files for coding agents.</p>";
        $out .= "<div class='ContextDash-pills'>";
        $out .= "<span class='" . $this->attr($formatClass) . "'><i class='fa fa-database'></i> " . $this->html($formatLabel) . "</span>";
        $out .= "<span class='ContextDash-pill'><i class='fa fa-clock-o'></i> " . $this->html($timeAgo) . "</span>";
        $out .= "<span class='ContextDash-pill'><i class='fa fa-archive'></i> " . ($exists ? $this->html($this->formatBytes($folderSize)) : 'No export') . "</span>";
        if($m->export_toon_format) {
            $out .= "<span class='ContextDash-pill'><i class='fa fa-magic'></i> TOON enabled</span>";
        }
        $out .= "</div></div>";
        $out .= "<div class='uk-width-auto@m'>";
        $out .= "<div class='ContextDash-actions'>";
        $out .= "<form method='post' action='./export/'>{$csrfInput}";
        $out .= "<button type='submit' class='uk-button uk-button-primary'><i class='fa fa-download'></i> " . $this->html($exportButtonLabel) . "</button>";
        $out .= "</form>";
        if($exists) {
            $out .= "<form method='post' action='./download/'>{$csrfInput}";
            $out .= "<button type='submit' class='uk-button uk-button-default'><i class='fa fa-file-archive-o'></i> Archive</button>";
            $out .= "</form>";
        }
        $out .= "<a href='" . $this->attr($settingsUrl) . "' class='uk-button uk-button-default'><i class='fa fa-cog'></i> Settings</a>";
        $out .= "</div>";
        if($exists) $out .= "<code class='ContextDash-path'>" . $this->html($contextPath) . "</code>";
        $out .= "</div></div></div>";
        return $out;
    }

    protected function renderMetrics(array $stats, $exists, $fileCount, array $inventory) {
        $metrics = [
            ['fa-object-group', 'Templates', $stats['templates'], 'Total'],
            ['fa-list-alt', 'Fields', $stats['fields'], 'Custom'],
            ['fa-sitemap', 'Pages', $stats['pages'], 'Published'],
            ['fa-files-o', 'Files', $exists ? $fileCount : '-', 'Exported'],
            ['fa-magic', 'TOON', $inventory['toon'], 'Files'],
            ['fa-code', 'JSON', $inventory['json'], 'Files']
        ];

        $out = "<div class='ContextDash-metrics'>";
        foreach($metrics as list($icon, $label, $value, $subLabel)) {
            $out .= "<div class='ContextDash-metric uk-card uk-card-default'>";
            $out .= "<div class='ContextDash-metricIcon'><i class='fa " . $this->attr($icon) . "'></i></div>";
            $out .= "<div><div class='ContextDash-metricValue'>" . $this->html($value) . "</div>";
            $out .= "<div class='ContextDash-metricLabel'>" . $this->html($label) . " / " . $this->html($subLabel) . "</div></div>";
            $out .= "</div>";
        }
        $out .= "</div>";
        return $out;
    }

    protected function renderStatusAndCli(array $inventory, $formatLabel, $formatClass, $lastExportLabel, $exportSizeLabel, array $healthItems) {
        $cliCommands = [
            'php index.php --context-export --toon-only',
            'php index.php --context-stats',
            'php index.php --context-query templates',
            'php index.php --context-query pages "template=listing, limit=10"'
        ];

        $out = "<div class='ContextDash-grid'>";
        $out .= "<div><div class='ContextDash-panel uk-card uk-card-default'>";
        $out .= "<div class='ContextDash-panelHeader'><h3><i class='fa fa-check-circle'></i> Current Export</h3><span class='" . $this->attr($formatClass) . "'>" . $this->html($formatLabel) . "</span></div>";
        $out .= "<div class='ContextDash-panelBody'><table class='uk-table uk-table-divider uk-table-small uk-margin-remove'><tbody>";
        $rows = [
            ['Last export', $lastExportLabel],
            ['Export size', $exportSizeLabel],
            ['TOON files', (int)$inventory['toon']],
            ['JSON files', (int)$inventory['json']],
            ['CSV files', (int)$inventory['csv']]
        ];
        foreach($rows as list($label, $value)) {
            $out .= "<tr><td><strong>" . $this->html($label) . "</strong></td><td>" . $this->html($value) . "</td></tr>";
        }
        $out .= "</tbody></table>";
        if(!empty($healthItems)) {
            $out .= "<div class='uk-margin-small-top'>";
            foreach($healthItems as list($class, $label)) {
                $out .= "<span class='" . $this->attr($class) . " uk-margin-small-right uk-margin-small-bottom'>" . $this->html($label) . "</span>";
            }
            $out .= "</div>";
        }
        $out .= "</div></div></div>";

        $out .= "<div><div class='ContextDash-panel uk-card uk-card-default'>";
        $out .= "<div class='ContextDash-panelHeader'><h3><i class='fa fa-terminal'></i> CLI Commands</h3></div>";
        $out .= "<div class='ContextDash-panelBody'>";
        $out .= "<p class='ContextDash-panelIntro'>Run these from the ProcessWire web root, the folder that contains <code>index.php</code>. The first command refreshes the AI context, the next commands inspect the generated structure without opening the admin.</p>";
        $out .= "<div class='ContextDash-steps'>";
        $out .= "<div class='ContextDash-step'><strong>1. Open project root</strong><span><code>cd /path/to/processwire</code></span></div>";
        $out .= "<div class='ContextDash-step'><strong>2. Export context</strong><span>Use TOON-only for the smallest AI payload.</span></div>";
        $out .= "<div class='ContextDash-step'><strong>3. Read output</strong><span>Start with <code>SKILL.md</code>, then <code>templates.toon</code> and <code>structure.toon</code>.</span></div>";
        $out .= "</div><pre class='ContextDash-command'><code>" . $this->html(implode("\n", $cliCommands)) . "</code></pre>";
        $out .= "</div></div></div>";
        $out .= "</div>";
        return $out;
    }

    protected function renderConfiguration($formatsLabel) {
        $m = $this->module;
        $rootDir = $m->config->paths->root;
        $integrationsCreated = file_exists($rootDir . '.cursorrules') && file_exists($rootDir . '.claudecode.json');

        $features = [
            ['Export Formats', 'formats', $formatsLabel, true],
            ['Auto-Update on Changes', 'boolean', null, (bool)$m->auto_update],
            ['Content Samples', 'boolean', $m->samples_count . ' per template', (bool)$m->export_samples],
            ['API Documentation', 'boolean', null, (bool)$m->export_api_docs],
            ['Field Definitions', 'boolean', null, (bool)$m->export_field_definitions],
            ['URL Routes', 'boolean', null, (bool)$m->export_routes],
            ['Performance Metrics', 'boolean', null, (bool)$m->export_performance],
            ['Code Snippets', 'boolean', null, (bool)$m->export_snippets],
            ['AI Prompts', 'boolean', null, (bool)$m->export_prompts],
            ['Integration Files', 'custom', '.cursorrules, .claudecode.json', $integrationsCreated],
            ['Compact Mode', 'boolean', 'Reduce file sizes', (bool)$m->compact_mode],
            ['Maximum Tree Depth', 'number', $m->max_depth . ' levels', true],
            ['JSON Children Limit', 'number', $m->json_child_limit . ' items', true]
        ];

        $enabled = 0;
        $cards = [];
        foreach($features as list($label, $type, $value, $state)) {
            if($type === 'boolean') {
                if($state) $enabled++;
                $statusLabel = $state ? 'Enabled' : 'Disabled';
                $statusClass = $state ? 'ContextDash-pill ContextDash-pill--good' : 'ContextDash-pill ContextDash-pill--bad';
                $displayValue = $value ?: 'Configured in module settings';
            } elseif($type === 'custom') {
                if($state) $enabled++;
                $statusLabel = $state ? 'Created' : 'Not created';
                $statusClass = $state ? 'ContextDash-pill ContextDash-pill--good' : 'ContextDash-pill ContextDash-pill--warn';
                $displayValue = $value;
            } elseif($type === 'formats') {
                $enabled++;
                $statusLabel = 'Selected';
                $statusClass = 'ContextDash-pill ContextDash-pill--good';
                $displayValue = $value;
            } else {
                $enabled++;
                $statusLabel = 'Value';
                $statusClass = 'ContextDash-pill';
                $displayValue = $value;
            }
            $cards[] = [$label, $statusLabel, $statusClass, $displayValue];
        }

        $out = "<div class='ContextDash-section'><div class='ContextDash-panel uk-card uk-card-default'>";
        $out .= "<div class='ContextDash-panelHeader'><div><h3><i class='fa fa-cog'></i> Module Configuration</h3><p class='ContextDash-panelIntro'>The active settings that control the next export and AI integration output.</p></div><span class='ContextDash-pill ContextDash-pill--good'>" . (int)$enabled . " active</span></div>";
        $out .= "<div class='ContextDash-panelBody'><div class='ContextDash-cardGrid'>";
        foreach($cards as list($label, $statusLabel, $statusClass, $displayValue)) {
            $out .= "<div class='ContextDash-configCard'><div class='ContextDash-cardTop'><strong>" . $this->html($label) . "</strong><span class='" . $this->attr($statusClass) . "'>" . $this->html($statusLabel) . "</span></div><div class='ContextDash-cardMeta'>" . $this->html($displayValue) . "</div></div>";
        }
        $out .= "</div></div></div></div>";
        return $out;
    }

    protected function renderExportPreview() {
        $m = $this->module;
        $hasMatrixFields = false;
        foreach($m->fields as $field) {
            if($field->type->className() === 'FieldtypeRepeaterMatrix') {
                $hasMatrixFields = true;
                break;
            }
        }

        $coreFiles = [];
        if($m->isJsonExportEnabled()) $coreFiles[] = ['structure.json', 'File', 'Complete page tree (JSON)'];
        if($m->export_toon_format) $coreFiles[] = ['structure.toon', 'File', 'Complete page tree (TOON)'];
        $coreFiles[] = ['structure.txt', 'File', 'ASCII visualization'];
        if($m->isJsonExportEnabled()) $coreFiles[] = ['templates.json', 'File', 'Templates & fields (JSON)'];
        if($m->isCsvExportEnabled()) $coreFiles[] = ['templates.csv', 'File', 'Templates in CSV format'];
        if($m->export_toon_format) $coreFiles[] = ['templates.toon', 'File', 'Templates & fields (TOON)'];
        if($hasMatrixFields && $m->isJsonExportEnabled()) $coreFiles[] = ['matrix-templates.json', 'File', 'Repeater Matrix types (ProFields)'];
        if($hasMatrixFields && $m->export_toon_format) $coreFiles[] = ['matrix-templates.toon', 'File', 'Repeater Matrix types (TOON)'];
        if($m->isJsonExportEnabled()) $coreFiles[] = ['config.json', 'File', 'Site configuration (JSON)'];
        if($m->export_toon_format) $coreFiles[] = ['config.toon', 'File', 'Site configuration (TOON)'];
        if($m->isJsonExportEnabled()) {
            $coreFiles[] = ['modules.json', 'File', 'Installed modules (JSON)'];
            $coreFiles[] = ['classes.json', 'File', 'Custom page classes (JSON)'];
        }
        if($m->export_toon_format) {
            $coreFiles[] = ['modules.toon', 'File', 'Installed modules (TOON)'];
            $coreFiles[] = ['classes.toon', 'File', 'Custom page classes (TOON)'];
        }
        $coreFiles[] = ['README.md', 'File', 'Documentation'];

        $optionalFiles = [
            ['samples/', 'Folder', 'Content examples', 'export_samples'],
            ['api/', 'Folder', 'API documentation', 'export_api_docs'],
            ['metadata/field-definitions.json', 'File', 'Field definitions', 'export_field_definitions'],
            ['metadata/routes.json', 'File', 'URL routes map', 'export_routes'],
            ['metadata/performance.json', 'File', 'Performance metrics', 'export_performance'],
            ['snippets/', 'Folder', 'Code library', 'export_snippets'],
            ['prompts/', 'Folder', 'AI prompts', 'export_prompts']
        ];

        $artifactRows = [];
        foreach($coreFiles as $row) $artifactRows[] = [$row[0], $row[1], $row[2], 'Core', 'ContextDash-pill ContextDash-pill--good'];
        foreach($optionalFiles as list($name, $type, $desc, $setting)) {
            if(!$m->isJsonExportEnabled() && strpos($name, 'metadata/') === 0) continue;
            if(!$m->isJsonExportEnabled() && $name === 'api/') continue;
            if($m->$setting) $artifactRows[] = [$name, $type, $desc, 'Optional', 'ContextDash-pill ContextDash-pill--warn'];
        }

        $out = "<div class='ContextDash-section'><div class='ContextDash-panel uk-card uk-card-default'>";
        $out .= "<div class='ContextDash-panelHeader'><div><h3><i class='fa fa-list'></i> What Will Be Exported?</h3><p class='ContextDash-panelIntro'>A preview of the files and folders generated by the next export with the current module settings.</p></div><span class='ContextDash-pill'>" . count($artifactRows) . " artifacts</span></div>";
        $out .= "<div class='ContextDash-panelBody'><div class='ContextDash-artifacts'>";
        foreach($artifactRows as list($name, $type, $desc, $badge, $badgeClass)) {
            $icon = $type === 'Folder' ? 'fa-folder' : 'fa-file-text-o';
            $out .= "<div class='ContextDash-artifact'><div class='ContextDash-artifactIcon'><i class='fa " . $this->attr($icon) . "'></i></div><div><div class='ContextDash-artifactName'>" . $this->html($name) . "</div><div class='ContextDash-artifactDesc'>" . $this->html($desc) . "</div></div><span class='" . $this->attr($badgeClass) . "'>" . $this->html($badge) . "</span></div>";
        }
        $out .= "</div></div></div></div>";
        return $out;
    }

    protected function renderTips() {
        $m = $this->module;
        $tips = [
            ['fa-refresh', 'Refresh after schema changes', 'Re-export whenever templates, fields, or routes change so agents see the current site shape.'],
            ['fa-book', 'Start with README and SKILL', 'Use <code>README.md</code> for human overview and <code>SKILL.md</code> for agent-facing instructions.'],
            ['fa-lightbulb-o', 'Use project summary first', 'Open <code>prompts/project-summary.md</code> at the start of a coding session to restore context.']
        ];
        if($m->export_toon_format) {
            $tips[] = ['fa-magic', 'Prefer TOON for AI payloads', 'Share <code>templates.toon</code>, <code>structure.toon</code>, and <code>samples/*.toon</code> before JSON.'];
        } else {
            $tips[] = ['fa-code', 'Use JSON for integrations', 'Share <code>templates.json</code> and <code>structure.json</code> when another tool needs standard JSON.'];
        }
        $tips[] = ['fa-code', 'Keep snippets close', 'Use <code>snippets/</code> as the local pattern library when generating ProcessWire code.'];
        $tips[] = ['fa-files-o', 'Use samples for content logic', 'Attach <code>samples/</code> when work depends on real page data or field values.'];

        $out = "<div class='ContextDash-section'><div class='ContextDash-panel uk-card uk-card-default'>";
        $out .= "<div class='ContextDash-panelHeader'><div><h3><i class='fa fa-info-circle'></i> Quick Tips</h3><p class='ContextDash-panelIntro'>Short workflow reminders for using the exported context with AI agents.</p></div></div>";
        $out .= "<div class='ContextDash-panelBody'><div class='ContextDash-cardGrid'>";
        foreach($tips as list($icon, $title, $text)) {
            $out .= "<div class='ContextDash-tipCard'><div class='ContextDash-cardTop'><strong><i class='fa " . $this->attr($icon) . "'></i> " . $this->html($title) . "</strong></div><div class='ContextDash-cardMeta'>{$text}</div></div>";
        }
        $out .= "</div></div></div></div>";
        return $out;
    }

    protected function getFormatStatus(array $inventory) {
        if(!$inventory['exists']) return ['Not exported', 'ContextDash-pill'];
        if($inventory['toon'] > 0 && $inventory['json'] === 0 && $inventory['csv'] === 0) return ['TOON only', 'ContextDash-pill ContextDash-pill--good'];
        if($inventory['toon'] > 0 && ($inventory['json'] > 0 || $inventory['csv'] > 0)) return ['JSON + TOON', 'ContextDash-pill ContextDash-pill--good'];
        if($inventory['json'] > 0 || $inventory['csv'] > 0) return ['JSON', 'ContextDash-pill ContextDash-pill--warn'];
        return ['No core files', 'ContextDash-pill ContextDash-pill--bad'];
    }

    protected function getHealthItems(array $inventory, $generateSkillMd) {
        if(!$inventory['exists']) return [['ContextDash-pill', 'Export has not been created yet']];
        $items = [];
        $items[] = $inventory['readme'] ? ['ContextDash-pill ContextDash-pill--good', 'README ready'] : ['ContextDash-pill ContextDash-pill--warn', 'README missing'];
        if($inventory['skill']) $items[] = ['ContextDash-pill ContextDash-pill--good', 'SKILL ready'];
        elseif($generateSkillMd) $items[] = ['ContextDash-pill ContextDash-pill--warn', 'SKILL missing'];
        if($inventory['project_context']) $items[] = ['ContextDash-pill ContextDash-pill--good', 'Project summary ready'];
        if($inventory['toon'] > 0 && ($inventory['json'] > 0 || $inventory['csv'] > 0)) $items[] = ['ContextDash-pill ContextDash-pill--warn', 'Mixed output'];
        if($inventory['api_dir'] && $inventory['toon'] > 0 && $inventory['json'] === 0) $items[] = ['ContextDash-pill ContextDash-pill--warn', 'JSON API dir present'];
        if($inventory['metadata_dir'] && $inventory['toon'] > 0 && $inventory['json'] === 0) $items[] = ['ContextDash-pill ContextDash-pill--warn', 'JSON metadata dir present'];
        return $items;
    }

    protected function renderStyles() {
        return "<style>"
            . ".ContextDash{--ctx-main:var(--pw-main-color,#0432ff);--ctx-surface:var(--pw-blocks-background,#fff);--ctx-surface-muted:var(--pw-inputs-background,#f8f8f8);--ctx-border:var(--pw-border-color,rgba(0,0,0,.16));--ctx-text:var(--pw-text-color,#111);--ctx-muted:var(--pw-muted-color,rgba(0,0,0,.55));--ctx-shadow:0 8px 22px rgba(0,0,0,.045);--ctx-command-bg:#111827;--ctx-command-text:#f8fafc}"
            . "body.dark-theme .ContextDash{--ctx-surface:#000;--ctx-surface-muted:#161616;--ctx-border:#444;--ctx-text:#fff;--ctx-muted:rgba(255,255,255,.6);--ctx-shadow:none;--ctx-command-bg:#111827;--ctx-command-text:#f8fafc}"
            . "@media(prefers-color-scheme:dark){body.auto-theme .ContextDash{--ctx-surface:#000;--ctx-surface-muted:#161616;--ctx-border:#444;--ctx-text:#fff;--ctx-muted:rgba(255,255,255,.6);--ctx-shadow:none;--ctx-command-bg:#111827;--ctx-command-text:#f8fafc}}"
            . ".ContextDash *{box-sizing:border-box}.ContextDash-hero{background:var(--ctx-main);color:#fff;border-radius:8px;padding:24px;box-shadow:0 14px 36px rgba(0,0,0,.12)}.ContextDash-hero h2{color:#fff;font-size:28px;line-height:1.15;margin:0 0 6px}.ContextDash-hero p{color:rgba(255,255,255,.82);margin:0}.ContextDash-kicker{font-size:12px;text-transform:uppercase;color:rgba(255,255,255,.68);font-weight:700;margin-bottom:8px}.ContextDash-actions{display:flex;flex-wrap:wrap;gap:8px;justify-content:flex-end;align-items:center}.ContextDash-actions form{margin:0}.ContextDash-pills{display:flex;flex-wrap:wrap;gap:8px;margin-top:16px}.ContextDash-pill{display:inline-flex;align-items:center;gap:6px;border-radius:999px;padding:5px 10px;background:#eef2f7;color:#374151;font-size:12px;font-weight:700;line-height:1.2;white-space:nowrap}.ContextDash-hero .ContextDash-pill{background:rgba(255,255,255,.14);color:#fff}.ContextDash-pill--good{background:#e8f6ee;color:#167243}.ContextDash-pill--warn{background:#fff4d8;color:#8a5a00}.ContextDash-pill--bad{background:#fde8e8;color:#a61b1b}"
            . ".ContextDash-metrics{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:12px;margin:16px 0}.ContextDash-metric,.ContextDash-panel{background:var(--ctx-surface);color:var(--ctx-text);border:1px solid var(--ctx-border);border-radius:8px;box-shadow:var(--ctx-shadow)}.ContextDash-metric{padding:16px;min-height:116px;display:flex;flex-direction:column;justify-content:space-between}.ContextDash-metricIcon{width:34px;height:34px;border-radius:8px;background:var(--ctx-surface-muted);display:flex;align-items:center;justify-content:center;color:var(--ctx-main)}.ContextDash-metricValue{font-size:28px;font-weight:800;line-height:1;color:var(--ctx-text);margin-top:14px;white-space:nowrap}.ContextDash-metricLabel{font-size:12px;text-transform:uppercase;color:var(--ctx-muted);font-weight:700;margin-top:4px}"
            . ".ContextDash-grid{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:16px;margin:16px 0;align-items:stretch}.ContextDash-grid>div{display:flex;min-width:0}.ContextDash-grid .ContextDash-panel{width:100%;height:100%;display:flex;flex-direction:column}.ContextDash-grid .ContextDash-panelBody{flex:1}.ContextDash-panelHeader{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:14px 16px;border-bottom:1px solid var(--ctx-border)}.ContextDash-panelHeader h3{font-size:16px;margin:0;color:var(--ctx-text)}.ContextDash-panelBody{padding:16px}.ContextDash-panelIntro{color:var(--ctx-muted);margin:0 0 14px;font-size:13px;line-height:1.45}.ContextDash .uk-table td,.ContextDash .uk-table th{color:var(--ctx-text);border-color:var(--ctx-border)}.ContextDash .uk-table th{color:var(--ctx-muted)}"
            . ".ContextDash-command{background:var(--ctx-command-bg);color:var(--ctx-command-text);border:1px solid var(--ctx-border);border-radius:8px;padding:14px;white-space:pre-wrap;margin:0;overflow:auto}.ContextDash-command code{color:inherit;background:transparent;padding:0}.ContextDash-path{display:block;margin-top:10px;white-space:normal;word-break:break-all}.ContextDash-steps{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;margin-bottom:14px}.ContextDash-step{background:var(--ctx-surface-muted);border:1px solid var(--ctx-border);border-radius:8px;padding:12px}.ContextDash-step strong{display:block;color:var(--ctx-text);margin-bottom:4px}.ContextDash-step span{display:block;color:var(--ctx-muted);font-size:12px;line-height:1.35}"
            . ".ContextDash-section{margin-top:16px}.ContextDash-cardGrid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}.ContextDash-configCard,.ContextDash-tipCard{background:var(--ctx-surface);border:1px solid var(--ctx-border);border-radius:8px;padding:14px;min-height:116px}.ContextDash-cardTop{display:flex;align-items:flex-start;justify-content:space-between;gap:10px;margin-bottom:12px}.ContextDash-cardTop strong{color:var(--ctx-text)}.ContextDash-cardMeta{color:var(--ctx-muted);font-size:12px;line-height:1.4}.ContextDash-artifacts{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.ContextDash-artifact{display:grid;grid-template-columns:34px minmax(0,1fr) auto;gap:12px;align-items:center;background:var(--ctx-surface);border:1px solid var(--ctx-border);border-radius:8px;padding:12px}.ContextDash-artifactIcon{width:34px;height:34px;border-radius:8px;background:var(--ctx-surface-muted);color:var(--ctx-main);display:flex;align-items:center;justify-content:center}.ContextDash-artifactName{font-weight:700;color:var(--ctx-text);word-break:break-word}.ContextDash-artifactDesc{font-size:12px;color:var(--ctx-muted);line-height:1.35;margin-top:2px}"
            . "@media(max-width:1100px){.ContextDash-metrics{grid-template-columns:repeat(3,minmax(0,1fr))}.ContextDash-actions{justify-content:flex-start}}@media(max-width:960px){.ContextDash-cardGrid,.ContextDash-artifacts,.ContextDash-steps{grid-template-columns:1fr}}@media(max-width:760px){.ContextDash-metrics,.ContextDash-grid{grid-template-columns:1fr}.ContextDash-hero{padding:18px}.ContextDash-hero h2{font-size:24px}}"
            . "</style>";
    }

    protected function formatBytes($bytes, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));
        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    protected function html($value) {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }

    protected function attr($value) {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}
