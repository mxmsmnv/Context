<?php namespace ProcessWire;

/**
 * Shared export pipeline for CLI, admin, and auto-export flows.
 */
class ContextExporter {

    /** @var Context */
    protected $module;

    /** @var callable */
    protected $call;

    public function __construct(Context $module) {
        $this->module = $module;
        $this->call = \Closure::bind(function($method, ...$args) {
            return $this->$method(...$args);
        }, $module, get_class($module));
    }

    public function export($aiPath, ?callable $progress = null) {
        $m = $this->module;
        $progress = $progress ?: function($message) {};

        $progress("📄 Exporting structure...");
        $structure = $this->invoke('buildPageTree', $m->pages->get('/'), 0, $m->max_depth);
        $this->invoke('writeJsonFile', $aiPath . 'structure.json', $structure);

        if($m->export_toon_format) {
            $this->invoke('writeToonFile', $aiPath . 'structure.toon', $structure);
        }

        $asciiTree = $this->invoke('buildAsciiTree', $m->pages->get('/'), 0, '', true, $m->max_depth);
        $this->invoke('writeFile', $aiPath . 'structure.txt', $asciiTree);

        $progress("📝 Exporting templates...");
        $this->invoke('exportTemplates');

        $progress("🌳 Exporting complete tree...");
        $tree = $this->invoke('exportTree');
        $this->invoke('writeJsonFile', $aiPath . 'tree.json', $tree);

        if($m->export_toon_format) {
            $this->invoke('writeToonFile', $aiPath . 'tree.toon', $tree);
        }

        $this->invoke('exportMatrixTemplates');
        $this->invoke('exportTemplatesToCSV');

        $progress("⚙️  Exporting configuration...");
        $config = $this->invoke('exportConfig');
        $this->invoke('writeJsonFile', $aiPath . 'config.json', $config);

        if($m->export_toon_format) {
            $this->invoke('writeToonFile', $aiPath . 'config.toon', $config);
        }

        $progress("🔌 Exporting modules...");
        $modules = $this->invoke('exportModules');
        $this->invoke('writeJsonFile', $aiPath . 'modules.json', $modules);

        if($m->export_toon_format) {
            $this->invoke('writeToonFile', $aiPath . 'modules.toon', ['modules' => $modules]);
        }

        $classes = $this->invoke('exportCustomClasses');
        if(!empty($classes)) {
            $this->invoke('writeJsonFile', $aiPath . 'classes.json', $classes);
            if($m->export_toon_format) {
                $this->invoke('writeToonFile', $aiPath . 'classes.toon', ['classes' => $classes]);
            }
        } else {
            $this->invoke('removeExportPath', $aiPath, 'classes.json');
            $this->invoke('removeExportPath', $aiPath, 'classes.toon');
        }

        if($m->export_samples) {
            $progress("📦 Exporting samples...");
            $this->invoke('exportSamples');
            $this->invoke('exportMatrixSamples');
        } else {
            $this->invoke('removeExportPath', $aiPath, 'samples');
        }

        if($m->export_api_docs) {
            $progress("🔗 Exporting API docs...");
            $this->invoke('exportApiDocs');
        } else {
            $this->invoke('removeExportPath', $aiPath, 'api');
        }

        if($m->export_routes) {
            $progress("🗺️  Exporting routes...");
            $this->invoke('exportRoutes');
        } else {
            $this->invoke('removeExportPath', $aiPath, 'metadata/routes.json');
        }

        if($m->export_snippets) {
            $progress("💻 Exporting snippets...");
            $this->invoke('createSnippets');
        } else {
            $this->invoke('removeExportPath', $aiPath, 'snippets');
        }

        if($m->export_prompts) {
            $progress("📋 Exporting prompts...");
            $this->invoke('createPrompts');
        } else {
            $this->invoke('removeExportPath', $aiPath, 'prompts/project-context.md');
            $this->invoke('removeExportPath', $aiPath, 'prompts/create-template.md');
            $this->invoke('removeExportPath', $aiPath, 'prompts/create-api.md');
            $this->invoke('removeExportPath', $aiPath, 'prompts/debug-issue.md');
            $this->invoke('removeEmptyExportDirectory', $aiPath, 'prompts');
        }

        if($m->export_field_definitions) {
            $progress("📊 Exporting field definitions...");
            $this->invoke('exportFieldDefinitions');
        } else {
            $this->invoke('removeExportPath', $aiPath, 'metadata/field-definitions.json');
        }

        if($m->export_performance) {
            $progress("⚡ Exporting performance metrics...");
            $this->invoke('exportPerformance');
        } else {
            $this->invoke('removeExportPath', $aiPath, 'metadata/performance.json');
        }

        $this->invoke('removeEmptyExportDirectory', $aiPath, 'metadata');

        if($m->export_integrations) {
            $progress("🔗 Exporting integrations...");
            $this->invoke('createIntegrationFiles');
        }

        $progress("📖 Creating README...");
        $this->invoke('writeFile', $aiPath . 'README.md', $this->invoke('createReadme'));

        if($m->generate_skill_md) {
            $progress("🤖 Generating SKILL.md...");
            $this->invoke('writeFile', $aiPath . 'SKILL.md', $this->invoke('createSkillMd'));
        } else {
            $this->invoke('removeExportPath', $aiPath, 'SKILL.md');
        }

        $this->cleanupFormats($aiPath);
    }

    protected function cleanupFormats($aiPath) {
        $m = $this->module;

        if(!$m->export_toon_format) {
            $this->invoke('pruneExportFormats', $aiPath, ['toon']);
        }

        if(!$m->isJsonExportEnabled()) {
            $this->invoke('pruneExportFormats', $aiPath, ['json']);
            $this->invoke('removeExportPath', $aiPath, 'api');
            $this->invoke('removeExportPath', $aiPath, 'metadata');
        }

        if(!$m->isCsvExportEnabled()) {
            $this->invoke('pruneExportFormats', $aiPath, ['csv']);
        }
    }

    protected function invoke($method, ...$args) {
        return ($this->call)($method, ...$args);
    }
}
