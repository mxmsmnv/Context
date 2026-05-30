<?php namespace ProcessWire;

/**
 * Module configuration form builder for Context.
 */
class ContextConfigFields {

    public static function build(array $data) {
        $modules = wire('modules');
        $inputfields = new InputfieldWrapper();

        $data = array_merge(Context::getConfigDefaults(), $data);

        // ── Row 1: Site Type + CSS Framework (50/50) ──────────────────────────
        $f = $modules->get('InputfieldSelect');
        $f->name = 'site_type';
        $f->label = 'Site Type';
        $f->addOption('generic', 'Generic / Mixed Content');
        $f->addOption('blog', 'Blog / News / Magazine');
        $f->addOption('ecommerce', 'E-commerce / Online Store');
        $f->addOption('business', 'Business / Portfolio / Agency');
        $f->addOption('catalog', 'Catalog / Directory / Listings');
        $f->value = $data['site_type'];
        $f->columnWidth = 50;
        $inputfields->add($f);

        $f = $modules->get('InputfieldSelect');
        $f->name = 'css_framework';
        $f->label = 'CSS Framework';
        $f->addOption('auto', 'Auto-detect (recommended)');
        $f->addOption('tailwind', 'Tailwind CSS');
        $f->addOption('bootstrap', 'Bootstrap');
        $f->addOption('uikit', 'UIkit');
        $f->addOption('vanilla', 'Vanilla CSS / Custom');
        $f->addOption('none', 'None');
        $f->value = $data['css_framework'];
        $f->columnWidth = 50;
        $inputfields->add($f);

        // ── Export Path (full width) ───────────────────────────────────────────
        $f = $modules->get('InputfieldText');
        $f->name = 'export_path';
        $f->label = 'Export Path';
        $f->notes = 'Default: `site/assets/cache/context/` (PW-protected). Also accepts absolute paths and paths like `.junie/skills/docs`. Unsafe paths such as the site root, assets root, cache root, templates folder, or module folder are blocked.';
        $f->value = $data['export_path'];
        $f->columnWidth = 100;
        $inputfields->add($f);

        // ── Export Options fieldset ────────────────────────────────────────────
        $fieldset = $modules->get('InputfieldFieldset');
        $fieldset->label = 'Export Options';
        $fieldset->collapsed = Inputfield::collapsedNo;
        $fieldset->icon = 'sliders';

        // Export formats — full width
        $f = $modules->get('InputfieldCheckboxes');
        $f->name = 'export_formats';
        $f->label = 'Export Formats';
        $f->description = 'Choose which machine-readable formats should remain in the final export folder.';
        $f->notes = 'TOON is optimized for AI agents. JSON is best for integrations and API schemas. CSV currently exports template/field inventory.';
        $f->icon = 'file-code-o';
        $f->addOption('toon', 'TOON');
        $f->addOption('json', 'JSON');
        $f->addOption('csv', 'CSV');
        $f->value = Context::normalizeStaticExportFormats($data['export_formats'] ?? null, $data['export_toon_format'] ?? 1);
        $f->columnWidth = 100;
        $fieldset->add($f);

        // SKILL.md — 50%
        $f = $modules->get('InputfieldCheckbox');
        $f->name = 'generate_skill_md';
        $f->label = 'Generate SKILL.md';
        $f->notes = 'For Cline, Junie, and other AI agents';
        $f->icon = 'robot';
        $f->checked = $data['generate_skill_md'] ? 'checked' : '';
        $f->columnWidth = 50;
        $fieldset->add($f);

        // API Docs — 50%
        $f = $modules->get('InputfieldCheckbox');
        $f->name = 'export_api_docs';
        $f->label = 'API Documentation';
        $f->notes = '→ `api/` JSON schemas';
        $f->checked = $data['export_api_docs'] ? 'checked' : '';
        $f->columnWidth = 50;
        $fieldset->add($f);

        // Field Definitions — 50%
        $f = $modules->get('InputfieldCheckbox');
        $f->name = 'export_field_definitions';
        $f->label = 'Field Definitions';
        $f->notes = '→ `metadata/field-definitions.json`';
        $f->checked = $data['export_field_definitions'] ? 'checked' : '';
        $f->columnWidth = 50;
        $fieldset->add($f);

        // URL Routes — 50%
        $f = $modules->get('InputfieldCheckbox');
        $f->name = 'export_routes';
        $f->label = 'URL Routes';
        $f->notes = '→ `metadata/routes.json`';
        $f->checked = $data['export_routes'] ? 'checked' : '';
        $f->columnWidth = 50;
        $fieldset->add($f);

        // Performance Metrics — 50%
        $f = $modules->get('InputfieldCheckbox');
        $f->name = 'export_performance';
        $f->label = 'Performance Metrics';
        $f->notes = '→ `metadata/performance.json`';
        $f->checked = $data['export_performance'] ? 'checked' : '';
        $f->columnWidth = 50;
        $fieldset->add($f);

        // Code Snippets — 50%
        $f = $modules->get('InputfieldCheckbox');
        $f->name = 'export_snippets';
        $f->label = 'Code Snippets';
        $f->notes = '→ `snippets/*.php`';
        $f->checked = $data['export_snippets'] ? 'checked' : '';
        $f->columnWidth = 50;
        $fieldset->add($f);

        // AI Prompts — 50%
        $f = $modules->get('InputfieldCheckbox');
        $f->name = 'export_prompts';
        $f->label = 'AI Prompts';
        $f->notes = '→ `prompts/project-context.md`';
        $f->checked = $data['export_prompts'] ? 'checked' : '';
        $f->columnWidth = 50;
        $fieldset->add($f);

        // Content Samples checkbox — 50%
        $f = $modules->get('InputfieldCheckbox');
        $f->name = 'export_samples';
        $f->label = 'Content Samples';
        $f->notes = '→ `samples/` per template';
        $f->checked = $data['export_samples'] ? 'checked' : '';
        $f->columnWidth = 50;
        $fieldset->add($f);

        // Samples count — 50%
        $f = $modules->get('InputfieldInteger');
        $f->name = 'samples_count';
        $f->label = 'Samples Per Template';
        $f->value = $data['samples_count'];
        $f->min = 1;
        $f->max = 20;
        $f->showIf = 'export_samples=1';
        $f->columnWidth = 50;
        $fieldset->add($f);

        $inputfields->add($fieldset);

        // ── Advanced Settings (collapsed) ─────────────────────────────────────
        $fieldset = $modules->get('InputfieldFieldset');
        $fieldset->label = 'Advanced Settings';
        $fieldset->collapsed = Inputfield::collapsedYes;
        $fieldset->icon = 'cog';

        $f = $modules->get('InputfieldInteger');
        $f->name = 'max_depth';
        $f->label = 'Max Tree Depth';
        $f->value = $data['max_depth'];
        $f->min = 3;
        $f->max = 20;
        $f->columnWidth = 33;
        $fieldset->add($f);

        $f = $modules->get('InputfieldInteger');
        $f->name = 'json_child_limit';
        $f->label = 'JSON Children Limit';
        $f->notes = 'Max children per page in structure.json';
        $f->value = $data['json_child_limit'];
        $f->min = 5;
        $f->max = 100;
        $f->columnWidth = 33;
        $fieldset->add($f);

        // spacer
        $f = $modules->get('InputfieldMarkup');
        $f->value = '';
        $f->columnWidth = 34;
        $fieldset->add($f);

        $f = $modules->get('InputfieldCheckbox');
        $f->name = 'compact_mode';
        $f->label = 'Compact Mode';
        $f->notes = 'Collapse large lists in structure.txt';
        $f->checked = $data['compact_mode'] ? 'checked' : '';
        $f->columnWidth = 33;
        $fieldset->add($f);

        $f = $modules->get('InputfieldCheckbox');
        $f->name = 'auto_update';
        $f->label = 'Auto-Update on Changes';
        $f->notes = 'Re-export on template/field save (may impact performance)';
        $f->checked = $data['auto_update'] ? 'checked' : '';
        $f->columnWidth = 33;
        $fieldset->add($f);

        $f = $modules->get('InputfieldCheckbox');
        $f->name = 'export_integrations';
        $f->label = 'IDE Integration Files';
        $f->notes = 'Creates `.cursorrules` and `.claudecode.json` in root';
        $f->checked = $data['export_integrations'] ? 'checked' : '';
        $f->columnWidth = 34;
        $fieldset->add($f);

        $f = $modules->get('InputfieldTextarea');
        $f->name = 'custom_ai_instructions';
        $f->label = 'Custom AI Instructions';
        $f->notes = 'Appended to `prompts/project-context.md`';
        $f->value = $data['custom_ai_instructions'];
        $f->rows = 3;
        $f->columnWidth = 100;
        $fieldset->add($f);

        $f = $modules->get('InputfieldTextarea');
        $f->name = 'sample_field_denylist';
        $f->label = 'Sample Field Denylist';
        $f->notes = 'Comma-separated field name/label fragments excluded from content samples.';
        $f->value = $data['sample_field_denylist'];
        $f->rows = 3;
        $f->columnWidth = 100;
        $fieldset->add($f);

        $inputfields->add($fieldset);


        // ── AI Gateway ──────────────────────────────────────────────────────
        $fieldset = $modules->get('InputfieldFieldset');
        $fieldset->label = 'AI Gateway';
        $fieldset->description = "Centralized AI access for this module and third-party modules via <code>wire('context')->ai()</code>.";
        $fieldset->collapsed = Inputfield::collapsedNo;
        $fieldset->icon = 'magic';

        $f = $modules->get('InputfieldCheckbox');
        $f->name = 'ai_enabled';
        $f->label = 'Enable AI Gateway';
        $f->notes = 'Required to use any AI features';
        $f->checked = $data['ai_enabled'] ? 'checked' : '';
        $f->columnWidth = 33;
        $fieldset->add($f);

        $f = $modules->get('InputfieldSelect');
        $f->name = 'ai_provider';
        $f->label = 'Provider';
        $f->addOption('openrouter', 'OpenRouter');
        $f->addOption('openai', 'OpenAI');
        $f->addOption('custom', 'Custom (OpenAI-compatible)');
        $f->value = $data['ai_provider'];
        $f->showIf = 'ai_enabled=1';
        $f->columnWidth = 33;
        $fieldset->add($f);

        $f = $modules->get('InputfieldText');
        $f->name = 'ai_custom_endpoint';
        $f->label = 'Custom API Base URL';
        $f->notes = 'e.g. https://my-llm.example.com/v1';
        $f->placeholder = 'https://';
        $f->value = $data['ai_custom_endpoint'];
        $f->showIf = 'ai_provider=custom, ai_enabled=1';
        $f->columnWidth = 34;
        $fieldset->add($f);

        $f = $modules->get('InputfieldText');
        $f->name = 'ai_api_key';
        $f->label = 'API Key';
        $f->notes = 'For OpenRouter: get your key at openrouter.ai/keys';
        $f->placeholder = 'sk-or-...';
        $f->value = $data['ai_api_key'];
        $f->attr('type', 'password');
        $f->showIf = 'ai_enabled=1';
        $f->columnWidth = 100;
        $fieldset->add($f);


        // Test connection button
        $testUrl = rtrim(wire('config')->urls->admin, '/') . '/setup/context/ai-test/';
        $csrfName = wire('session')->CSRF->getTokenName();
        $csrfValue = wire('session')->CSRF->getTokenValue();
        $f = $modules->get('InputfieldMarkup');
        $f->name = 'ai_test_connection';
        $f->label = 'Connection Status';
        $f->showIf = 'ai_enabled=1';
        $f->columnWidth = 100;
        $html = '<div id="ai-gateway-status" style="display:flex;align-items:center;gap:12px;padding:8px 0;">';
        $html .= '<button type="button" id="ai-test-btn" class="ui-button ui-widget ui-state-default ui-corner-all" style="cursor:pointer;">Test Connection</button>';
        $html .= '<span id="ai-test-result" style="font-size:13px;"></span>';
        $html .= '</div>';
        $html .= '<script>';
        $html .= 'document.getElementById("ai-test-btn").addEventListener("click", function() {';
        $html .= '  var btn = this;';
        $html .= '  var result = document.getElementById("ai-test-result");';
        $html .= '  var body = new FormData();';
        $html .= '  body.append(' . json_encode($csrfName) . ', ' . json_encode($csrfValue) . ');';
        $html .= '  btn.disabled = true;';
        $html .= '  result.style.color = "";';
        $html .= '  result.textContent = "Testing...";';
        $html .= '  fetch(' . json_encode($testUrl) . ', {method:"POST",headers:{"X-Requested-With":"XMLHttpRequest"},body:body})';
        $html .= '  .then(function(r){return r.json();})';
        $html .= '  .then(function(d){';
        $html .= '    result.style.fontWeight = "600";';
        $html .= '    if(d.success){result.style.color="#3d9970";result.textContent="Connected - "+d.model+" - "+d.ms+"ms";}';
        $html .= '    else{result.style.color="#e74c3c";result.textContent="Error: "+d.error;}';
        $html .= '    btn.disabled=false;';
        $html .= '  }).catch(function(){result.style.color="#e74c3c";result.style.fontWeight="600";result.textContent="Request failed";btn.disabled=false;});';
        $html .= '});';
        $html .= '</script>';
        $f->value = $html;
        $fieldset->add($f);

        $f = $modules->get('InputfieldText');
        $f->name = 'ai_model';
        $f->label = 'Default Model';
        $f->notes = 'OpenRouter format: provider/model  e.g. anthropic/claude-sonnet-4-6 or openai/gpt-4o-mini';
        $f->placeholder = 'anthropic/claude-sonnet-4-6';
        $f->value = $data['ai_model'];
        $f->showIf = 'ai_enabled=1';
        $f->columnWidth = 50;
        $fieldset->add($f);

        $f = $modules->get('InputfieldInteger');
        $f->name = 'ai_timeout';
        $f->label = 'Timeout (sec)';
        $f->value = (int)$data['ai_timeout'];
        $f->min = 5;
        $f->max = 120;
        $f->showIf = 'ai_enabled=1';
        $f->columnWidth = 25;
        $fieldset->add($f);

        $f = $modules->get('InputfieldInteger');
        $f->name = 'ai_max_tokens';
        $f->label = 'Max Tokens';
        $f->value = (int)$data['ai_max_tokens'];
        $f->min = 64;
        $f->max = 16384;
        $f->showIf = 'ai_enabled=1';
        $f->columnWidth = 25;
        $fieldset->add($f);

        $f = $modules->get('InputfieldText');
        $f->name = 'ai_temperature';
        $f->label = 'Temperature';
        $f->notes = '0 = deterministic  ·  1 = creative';
        $f->placeholder = '0.7';
        $f->value = $data['ai_temperature'];
        $f->showIf = 'ai_enabled=1';
        $f->columnWidth = 50;
        $fieldset->add($f);

        $f = $modules->get('InputfieldTextarea');
        $f->name = 'ai_system_prompt';
        $f->label = 'Global System Prompt';
        $f->notes = 'Prepended to every AI request from any module using this gateway.';
        $f->placeholder = 'You are a helpful ProcessWire development assistant.';
        $f->value = $data['ai_system_prompt'];
        $f->rows = 3;
        $f->showIf = 'ai_enabled=1';
        $f->columnWidth = 100;
        $fieldset->add($f);

        $f = $modules->get('InputfieldText');
        $f->name = 'ai_site_url';
        $f->label = 'Site URL (OpenRouter attribution)';
        $f->notes = 'Sent as HTTP-Referer header.';
        $f->value = $data['ai_site_url'];
        $f->showIf = 'ai_provider=openrouter, ai_enabled=1';
        $f->columnWidth = 50;
        $fieldset->add($f);

        $f = $modules->get('InputfieldText');
        $f->name = 'ai_site_name';
        $f->label = 'Site / App Name (OpenRouter attribution)';
        $f->notes = 'Sent as X-Title header.';
        $f->value = $data['ai_site_name'];
        $f->showIf = 'ai_provider=openrouter, ai_enabled=1';
        $f->columnWidth = 50;
        $fieldset->add($f);

        $inputfields->add($fieldset);

        return $inputfields;
    
    }
}
