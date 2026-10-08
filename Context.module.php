<?php namespace ProcessWire;

/**
 * Context - ProcessWire AI Context Exporter
 * 
 * Full-featured module for exporting ProcessWire site structure
 * in a format optimized for working with AI assistants.
 * 
 * Creates complete documentation: structure, templates, content samples,
 * API schemas, code snippets, URL mapping and ready-to-use AI prompts.
 * 
 * Supports JSON and TOON (Token-Oriented Object Notation) formats.
 * TOON format reduces token consumption by 30-60% for AI prompts.
 */

class Context extends Process implements Module, ConfigurableModule {

    const VERSION = '2.2.0';

    public static function getModuleInfo() {
        return [
            'title' => 'Context', 
            'version' => 220,
            'summary' => '[DISCONTINUED] Context functionality is now integrated into Jigsaw',
            'author' => 'Maxim Semenov',
            'href'     => 'https://smnv.org',
            'icon' => 'code',
            'permissions' => [
                'context-admin' => 'Administer Context exports and AI gateway'
            ],
            'page' => [
                'name' => 'context',
                'parent' => 'setup',
                'title' => 'Context'
            ],
            'requires' => 'ProcessWire>=3.0',
            'autoload' => true,
            'singular' => true
        ];
    }

    // Default module settings
    protected static $configDefaults = [
        'samples_count' => 3,
        'max_depth' => 10,
        'json_child_limit' => 20,
        'export_samples' => 1,
        'export_api_docs' => 1,
        'export_routes' => 1,
        'export_snippets' => 1,
        'export_prompts' => 1,
        'export_field_definitions' => 1,
        'export_performance' => 1,
        'export_integrations' => 0,
        'export_formats' => 'toon,json,csv',
        'export_toon_format' => 1,
        'compact_mode' => 0,
        'auto_update' => 0,
        'site_type' => 'generic',
        'custom_ai_instructions' => '',
        'sample_field_denylist' => 'password, pass, email, token, secret, api_key, apikey, auth, session, cookie, csrf, salt, hash, phone, mobile, address, ssn, credit, card, iban, private',
        'export_path' => 'site/assets/cache/context/',
        'css_framework' => 'auto',
        'generate_skill_md' => 1,
        // AI Gateway
        'ai_enabled'         => 0,
        'ai_provider'        => 'squad',
        'ai_api_key'         => '',
        'ai_model'           => 'anthropic/claude-sonnet-4-6',
        'ai_max_tokens'      => 1024,
        'ai_temperature'     => 0.7,
        'ai_timeout'         => 30,
        'ai_system_prompt'   => '',
        'ai_site_url'        => '',
        'ai_site_name'       => '',
        'ai_custom_endpoint' => ''
    ];

    /** Track whether static dependencies are loaded */
    protected static $dependenciesLoaded = false;

    protected static function loadDependencies() : void {
        if(self::$dependenciesLoaded) return;

        require_once __DIR__ . '/src/ContextAI.php';
        require_once __DIR__ . '/src/ContextToon.php';
        require_once __DIR__ . '/src/ContextSampleSerializer.php';
        require_once __DIR__ . '/src/ContextDashboard.php';
        require_once __DIR__ . '/src/ContextExporter.php';
        require_once __DIR__ . '/src/ContextConfigFields.php';
        require_once __DIR__ . '/src/ContextCli.php';
        require_once __DIR__ . '/src/ContextAutoUpdater.php';
        require_once __DIR__ . '/src/ContextFilesystem.php';
        require_once __DIR__ . '/src/ContextStructureExporter.php';
        require_once __DIR__ . '/src/ContextTemplateExporter.php';
        require_once __DIR__ . '/src/ContextSampleExporter.php';
        require_once __DIR__ . '/src/ContextApiExporter.php';
        require_once __DIR__ . '/src/ContextMetadataExporter.php';
        require_once __DIR__ . '/src/ContextPromptExporter.php';
        require_once __DIR__ . '/src/ContextPromptTemplates.php';
        require_once __DIR__ . '/src/ContextIntegrationExporter.php';
        require_once __DIR__ . '/src/ContextSystemExporter.php';
        require_once __DIR__ . '/src/ContextDocsExporter.php';
        require_once __DIR__ . '/src/ContextArchiveDownloader.php';
        require_once __DIR__ . '/src/ContextAdminActions.php';
        require_once __DIR__ . '/src/ContextFrontendDetector.php';
        require_once __DIR__ . '/src/ContextAiTestAction.php';
        require_once __DIR__ . '/src/ContextExportFormats.php';
        require_once __DIR__ . '/src/ContextSiteInspector.php';
        require_once __DIR__ . '/src/ContextWebHelper.php';

        self::$dependenciesLoaded = true;
    }

    /**
     * Constructor - apply default values
     */
    public function __construct() {
        self::loadDependencies();
        foreach(self::$configDefaults as $key => $value) {
            $this->$key = $value;
        }
    }

    /** @var ContextAI|null */
    protected $_ai = null;

    /** @var ContextToon|null */
    protected $_toon = null;

    /** @var ContextSampleSerializer|null */
    protected $_sampleSerializer = null;

    /** @var ContextFilesystem|null */
    protected $_filesystem = null;

    /** @var ContextStructureExporter|null */
    protected $_structureExporter = null;

    /** @var ContextTemplateExporter|null */
    protected $_templateExporter = null;

    /** @var ContextSampleExporter|null */
    protected $_sampleExporter = null;

    /** @var ContextApiExporter|null */
    protected $_apiExporter = null;

    /** @var ContextMetadataExporter|null */
    protected $_metadataExporter = null;

    /** @var ContextPromptExporter|null */
    protected $_promptExporter = null;

    /** @var ContextPromptTemplates|null */
    protected $_promptTemplates = null;

    /** @var ContextIntegrationExporter|null */
    protected $_integrationExporter = null;

    /** @var ContextSystemExporter|null */
    protected $_systemExporter = null;

    /** @var ContextDocsExporter|null */
    protected $_docsExporter = null;

    /** @var ContextArchiveDownloader|null */
    protected $_archiveDownloader = null;

    /** @var ContextAdminActions|null */
    protected $_adminActions = null;

    /** @var ContextFrontendDetector|null */
    protected $_frontendDetector = null;

    /** @var ContextAiTestAction|null */
    protected $_aiTestAction = null;

    /** @var ContextExportFormats|null */
    protected $_exportFormatsHelper = null;

    /** @var ContextSiteInspector|null */
    protected $_siteInspector = null;

    /** @var ContextWebHelper|null */
    protected $_webHelper = null;

    /** @var bool Whether JSON/CSV files are expected in the final export artifact. */
    protected $_exportJsonFormat = true;

    /** @var bool Whether CSV files are expected in the final export artifact. */
    protected $_exportCsvFormat = true;

    /** @var bool */
    protected $_cliHandled = false;

    /**
     * Process modules default to non-autoload; Context needs boot-time hooks and CLI dispatch.
     */
    public function isAutoload() {
        return true;
    }

    /**
     * Get the AI gateway instance.
     *
     * Usage from any module:
     *   $ai = wire('context')->ai();
     *   $text = $ai->complete('Summarize the homepage');
     *   $result = $ai->gateway(['caller' => 'MyModule', 'messages' => [...]]);
     *
     * @return ContextAI
     */
    public function ai(): ContextAI {
        if($this->_ai === null) {
            $cfg = [];
            foreach([
                'ai_enabled', 'ai_provider', 'ai_api_key', 'ai_model',
                'ai_max_tokens', 'ai_temperature', 'ai_timeout',
                'ai_system_prompt', 'ai_site_url', 'ai_site_name',
                'ai_custom_endpoint',
            ] as $key) {
                $cfg[$key] = $this->$key;
            }
            $this->_ai = new ContextAI($cfg);
        }
        return $this->_ai;
    }

    /**
     * Module initialization
     */
    public function init() {
        parent::init();
        $this->applyExportFormats();
        
        // Register API variable
        $this->wire('context', $this);
        $this->dispatchCliCommand();
        
        // Auto-update if enabled
        if($this->auto_update) {
            $this->addHookAfter('Template::saved', $this, 'autoUpdate');
            $this->addHookAfter('Field::saved', $this, 'autoUpdate');
        }
    }

    /**
     * ProcessWire ready - handle CLI commands
     */
    public function ready() {
        $this->dispatchCliCommand();
    }

    protected function dispatchCliCommand() {
        if($this->_cliHandled || PHP_SAPI !== 'cli') return;

        $argv = $GLOBALS['argv'] ?? [];
        if(!empty($argv[1]) && strpos($argv[1], '--context-') === 0) {
            $this->_cliHandled = true;
            $action = str_replace('--context-', '', $argv[1]);
            $this->handleCLI($action, $argv);
        }
    }

    /**
     * Handle CLI commands
     */
    protected function handleCLI($action, $argv) {
        (new ContextCli($this))->handle($action, $argv);
    }

    protected function aiTestAction() {
        if($this->_aiTestAction === null) {
            $this->_aiTestAction = new ContextAiTestAction($this);
        }
        return $this->_aiTestAction;
    }

    /**
     * AJAX endpoint: test AI gateway connection
     * Called at /setup/context/ai-test/
     */
    public function executeAiTest() {
        $this->aiTestAction()->execute();
    }

    /**
     * Export all files (shared CLI/admin pipeline).
     */
    protected function exportAll($aiPath, ?callable $progress = null) {
        (new ContextExporter($this))->export($aiPath, $progress);
    }

    /**
     * Auto-update on changes
     */
    public function autoUpdate($event) {
        (new ContextAutoUpdater($this))->handle($event);
    }

    protected function filesystem() {
        if($this->_filesystem === null) {
            $this->_filesystem = new ContextFilesystem($this);
        }
        return $this->_filesystem;
    }

    protected function structureExporter() {
        if($this->_structureExporter === null) {
            $this->_structureExporter = new ContextStructureExporter($this);
        }
        return $this->_structureExporter;
    }

    protected function templateExporter() {
        if($this->_templateExporter === null) {
            $this->_templateExporter = new ContextTemplateExporter($this);
        }
        return $this->_templateExporter;
    }

    protected function sampleExporter() {
        if($this->_sampleExporter === null) {
            $this->_sampleExporter = new ContextSampleExporter($this);
        }
        return $this->_sampleExporter;
    }

    protected function apiExporter() {
        if($this->_apiExporter === null) {
            $this->_apiExporter = new ContextApiExporter($this);
        }
        return $this->_apiExporter;
    }

    protected function metadataExporter() {
        if($this->_metadataExporter === null) {
            $this->_metadataExporter = new ContextMetadataExporter($this);
        }
        return $this->_metadataExporter;
    }

    protected function promptExporter() {
        if($this->_promptExporter === null) {
            $this->_promptExporter = new ContextPromptExporter($this);
        }
        return $this->_promptExporter;
    }

    protected function promptTemplates() {
        if($this->_promptTemplates === null) {
            $this->_promptTemplates = new ContextPromptTemplates($this);
        }
        return $this->_promptTemplates;
    }

    protected function integrationExporter() {
        if($this->_integrationExporter === null) {
            $this->_integrationExporter = new ContextIntegrationExporter($this);
        }
        return $this->_integrationExporter;
    }

    protected function systemExporter() {
        if($this->_systemExporter === null) {
            $this->_systemExporter = new ContextSystemExporter($this);
        }
        return $this->_systemExporter;
    }

    protected function docsExporter() {
        if($this->_docsExporter === null) {
            $this->_docsExporter = new ContextDocsExporter($this);
        }
        return $this->_docsExporter;
    }

    protected function archiveDownloader() {
        if($this->_archiveDownloader === null) {
            $this->_archiveDownloader = new ContextArchiveDownloader($this);
        }
        return $this->_archiveDownloader;
    }

    protected function adminActions() {
        if($this->_adminActions === null) {
            $this->_adminActions = new ContextAdminActions($this);
        }
        return $this->_adminActions;
    }

    protected function frontendDetector() {
        if($this->_frontendDetector === null) {
            $this->_frontendDetector = new ContextFrontendDetector($this);
        }
        return $this->_frontendDetector;
    }

    protected function exportFormatsHelper() {
        if($this->_exportFormatsHelper === null) {
            $this->_exportFormatsHelper = new ContextExportFormats($this);
        }
        return $this->_exportFormatsHelper;
    }

    protected function siteInspector() {
        if($this->_siteInspector === null) {
            $this->_siteInspector = new ContextSiteInspector($this);
        }
        return $this->_siteInspector;
    }

    protected function webHelper() {
        if($this->_webHelper === null) {
            $this->_webHelper = new ContextWebHelper($this);
        }
        return $this->_webHelper;
    }

    protected function getContextPath() {
        return $this->filesystem()->getContextPath();
    }

    protected function ensureFolder($path) {
        return $this->filesystem()->ensureFolder($path);
    }

    protected function validateContextPath($path) {
        $this->filesystem()->validateContextPath($path);
    }

    protected function normalizeFilesystemPath($path) {
        return $this->filesystem()->normalizePath($path);
    }

    protected function writeFile($path, $contents) {
        return $this->filesystem()->writeFile($path, $contents);
    }

    protected function readFile($path, $maxBytes = null) {
        return $this->filesystem()->readFile($path, $maxBytes);
    }

    protected function readJsonFile($path) {
        return $this->filesystem()->readJsonFile($path);
    }

    protected function writeJsonFile($path, $data, $flags = null) {
        return $this->filesystem()->writeJsonFile($path, $data, $flags);
    }

    protected function writeToonFile($path, $data) {
        return $this->filesystem()->writeToonFile($path, $data);
    }

    protected function sendJsonResponse(array $data) {
        $this->webHelper()->sendJsonResponse($data);
    }

    protected function hasContextAccess() {
        return $this->webHelper()->hasContextAccess();
    }

    protected function requireContextAccess() {
        $this->webHelper()->requireContextAccess();
    }

    protected function hasValidCsrfToken() {
        return $this->webHelper()->hasValidCsrfToken();
    }

    protected function requirePostCsrf() {
        $this->webHelper()->requirePostCsrf();
    }

    protected function getCsrfInputMarkup() {
        return $this->webHelper()->getCsrfInputMarkup();
    }

    public static function normalizeStaticExportFormats($formats = null, $legacyToon = 1) {
        if(!class_exists(__NAMESPACE__ . '\\ContextExportFormats')) {
            require_once __DIR__ . '/src/ContextExportFormats.php';
        }
        return ContextExportFormats::normalize($formats, $legacyToon);
    }

    protected function normalizeExportFormats($formats = null) {
        return $this->exportFormatsHelper()->normalizeModuleFormats($formats);
    }

    protected function applyExportFormats($formats = null) {
        return $this->exportFormatsHelper()->apply($formats);
    }

    protected function exportFormatLabel(?array $formats = null) {
        return $this->exportFormatsHelper()->label($formats);
    }

    protected function setExportFormatFlag($property, $value) {
        if($property === '_exportJsonFormat') {
            $this->_exportJsonFormat = (bool)$value;
        } elseif($property === '_exportCsvFormat') {
            $this->_exportCsvFormat = (bool)$value;
        }
    }

    public function isJsonExportEnabled() {
        return $this->_exportJsonFormat;
    }

    public function isCsvExportEnabled() {
        return $this->_exportCsvFormat;
    }

    public static function getConfigDefaults() {
        return self::$configDefaults;
    }

    protected function html($value) {
        return $this->webHelper()->html($value);
    }

    protected function attr($value) {
        return $this->webHelper()->attr($value);
    }

    protected function isSensitiveSampleField(Field $field) {
        return $this->sampleSerializer()->isSensitiveField($field);
    }

    protected function pruneExportFormats($path, array $extensions) {
        return $this->filesystem()->pruneExportFormats($path, $extensions);
    }

    protected function removeExportPath($basePath, $relativePath) {
        return $this->filesystem()->removeExportPath($basePath, $relativePath);
    }

    protected function removeEmptyExportDirectory($basePath, $relativePath) {
        return $this->filesystem()->removeEmptyExportDirectory($basePath, $relativePath);
    }

    protected function isPathInsideExportRoot($path, $root) {
        return $this->filesystem()->isPathInsideExportRoot($path, $root);
    }

    protected function contextFileName($baseName) {
        $extension = $this->export_toon_format ? 'toon' : 'json';
        return $baseName . '.' . $extension;
    }

    /**
     * Build page tree (JSON)
     */
    protected function buildPageTree(Page $page, $depth = 0, $maxDepth = 10) {
        return $this->structureExporter()->buildPageTree($page, $depth, $maxDepth);
    }

    /**
     * Build ASCII tree with smart collapsing for large homogeneous lists
     */
    protected function buildAsciiTree(Page $page, $depth = 0, $prefix = '', $isLast = true, $maxDepth = 10) {
        return $this->structureExporter()->buildAsciiTree($page, $depth, $prefix, $isLast, $maxDepth);
    }

    // -------------------------------------------------------------------------
    // ProFields helpers — use official module APIs, never raw ->data[] access
    // -------------------------------------------------------------------------

    /**
     * Get FieldtypeTable columns using the official API.
     *
     * Returns an array of columns, each with keys: name, label, type, options, selector.
     * Falls back gracefully when FieldtypeTable is not installed.
     *
     * @param Field $field
     * @return array
     */
    protected function getTableColumns(Field $field) {
        return $this->templateExporter()->getTableColumns($field);
    }

    /**
     * Get FieldtypeCombo subfields using the official API.
     *
     * Returns an array of subfield definitions with keys:
     * name, label, type, required, columnWidth, description, notes, options.
     * Falls back gracefully when FieldtypeCombo is not installed.
     *
     * @param Field $field
     * @return array
     */
    protected function getComboSubfields(Field $field) {
        return $this->templateExporter()->getComboSubfields($field);
    }

    /**
     * Get FieldtypeRepeaterMatrix types with their fields using the official API.
     *
     * Returns an array of matrix type definitions:
     * [ name, label, head, sort, fields[] ]
     * Each field entry contains full field metadata (same shape as exportTemplates fields).
     * Falls back gracefully when FieldtypeRepeaterMatrix is not installed.
     *
     * @param Field $field
     * @return array
     */
    protected function getMatrixTypesData(Field $field) {
        return $this->templateExporter()->getMatrixTypesData($field);
    }

    /**
     * Build a standardised field data array for a single Field object.
     *
     * Used by getMatrixTypesData() and can be used anywhere a consistent
     * field representation is needed. Handles Page, Options, Image/File,
     * Text/Textarea, Table, Combo, Repeater, and nested RepeaterMatrix fields.
     *
     * @param Field $field
     * @return array
     */
    protected function buildFieldData(Field $field) {
        return $this->templateExporter()->buildFieldData($field);
    }

    // -------------------------------------------------------------------------
    // End ProFields helpers
    // -------------------------------------------------------------------------

    /**
     * Export all templates with fields
     */
    protected function exportTemplates() {
        return $this->templateExporter()->exportTemplates();
    }

    /**
     * Export RepeaterMatrix types as a dedicated file (matrix-templates.json/.toon).
     *
     * Uses the official getMatrixTypesInfo() API — no template-name pattern matching.
     * Each matrix field becomes a top-level entry with its types and their full field
     * definitions (via buildFieldData).
     */
    protected function exportMatrixTemplates() {
        return $this->templateExporter()->exportMatrixTemplates();
    }
    /**
     * Export complete site tree (templates with nested fields structure)
     * Technical overview without data - just the field architecture
     */
    protected function exportTree() {
        return $this->structureExporter()->exportTree();
    }

    /**
     * Export templates to CSV format
     */
    protected function exportTemplatesToCSV() {
        return $this->templateExporter()->exportTemplatesToCSV();
    }

    protected function isEmptySampleValue($value) {
        return $this->sampleSerializer()->isEmptyValue($value);
    }

    protected function serializeSampleFieldValue(Field $field, $value) {
        return $this->sampleSerializer()->serializeFieldValue($field, $value);
    }

    protected function sampleSerializer() {
        if($this->_sampleSerializer === null) {
            $this->_sampleSerializer = new ContextSampleSerializer($this);
        }
        return $this->_sampleSerializer;
    }

    /**
     * Export content samples
     */
    protected function exportSamples() {
        return $this->sampleExporter()->exportSamples();
    }

    /**
     * Export samples for Matrix templates
     */
    protected function exportMatrixSamples() {
        return $this->sampleExporter()->exportMatrixSamples();
    }

    /**
     * Generate JSON Schema for API
     */
    protected function exportApiDocs() {
        return $this->apiExporter()->exportApiDocs();
    }

    /**
     * Determine JSON Schema type from ProcessWire field
     */
    protected function getJsonSchemaType($field) {
        return $this->apiExporter()->getJsonSchemaType($field);
    }

    protected function buildJsonSchemaProperty(Field $field) {
        return $this->apiExporter()->buildJsonSchemaProperty($field);
    }

    protected function getJsonSchemaTypeFromName($typeName) {
        return $this->apiExporter()->getJsonSchemaTypeFromName($typeName);
    }

    /**
     * Export detailed custom field definitions
     */
    protected function exportFieldDefinitions() {
        return $this->metadataExporter()->exportFieldDefinitions();
    }

    /**
     * Export URL routing
     */
    protected function exportRoutes() {
        return $this->metadataExporter()->exportRoutes();
    }

    /**
     * Export performance metrics
     */
    protected function exportPerformance() {
        return $this->metadataExporter()->exportPerformance();
    }

    /**
     * Create code library
     */
    protected function createSnippets() {
        $this->promptExporter()->createSnippets();
    }

    /**
     * Generate selectors snippet based on site type setting
     */
    protected function createPrompts() {
        return $this->promptExporter()->createPrompts();
    }

    protected function generateProjectContext() {
        return $this->promptTemplates()->generateProjectContext();
    }
    
    /**
     * Create IDE integration files
     */
    protected function createIntegrationFiles() {
        $this->integrationExporter()->createIntegrationFiles();
    }
    
    /**
     * Update .cursorrules file (add paths if not exists)
     */
    protected function updateCursorRules($rootDir) {
        $this->integrationExporter()->updateCursorRules($rootDir);
    }
    
    /**
     * Update .claudecode.json file (add context paths if not exists)
     */
    protected function updateClaudeCode($rootDir) {
        $this->integrationExporter()->updateClaudeCode($rootDir);
    }

    protected function generateCreateTemplatePrompt() {
        return $this->promptTemplates()->generateCreateTemplatePrompt();
    }

    protected function generateCreateApiPrompt() {
        return $this->promptTemplates()->generateCreateApiPrompt();
    }

    protected function generateDebugPrompt() {
        return $this->promptTemplates()->generateDebugPrompt();
    }

    /**
     * Export configuration
     */
    protected function exportConfig() {
        return $this->systemExporter()->exportConfig();
    }

    /**
     * Export modules
     */
    protected function exportModules() {
        return $this->systemExporter()->exportModules();
    }

    /**
     * Export custom page classes from /site/classes/
     */
    protected function exportCustomClasses() {
        return $this->systemExporter()->exportCustomClasses();
    }

    /**
     * Generate project summary template for session continuity
     */
    protected function generateProjectSummaryTemplate() {
        return $this->promptTemplates()->generateProjectSummaryTemplate();
    }

    /**
     * Create README
     */
    protected function createReadme() {
        return $this->docsExporter()->createReadme();
    }

    /**
     * Create SKILL.md for AI agents (Cline, Junie, etc.)
     */
    protected function createSkillMd() {
        return $this->docsExporter()->createSkillMd();
    }


    /**
     * Download context folder as ZIP archive
     */
    public function executeDownload() {
        $this->archiveDownloader()->execute();
    }

    /**
     * Export context from the admin action route.
     */
    public function executeExport() {
        $this->adminActions()->executeExport();
    }

    /**
     * Detect frontend stack
     */
    protected function detectFrontendStack() {
        return $this->frontendDetector()->detectFrontendStack();
    }
    
    /**
     * Detect JavaScript frameworks only (helper for manual CSS selection)
     */
    protected function detectJavaScriptFrameworks() {
        return $this->frontendDetector()->detectJavaScriptFrameworks();
    }

    /**
     * Get route map (URL segments)
     */
    protected function getRouteMap() {
        return $this->metadataExporter()->getRouteMap();
    }

    /**
     * Get access map (roles & permissions)
     */
    protected function getAccessMap() {
        return $this->siteInspector()->getAccessMap();
    }

    /**
     * Get site statistics
     */
    protected function getSiteStats() {
        return $this->siteInspector()->getSiteStats();
    }

    /**
     * Main module page
     */
    public function execute() {
        $this->requireContextAccess();

        $contextPath = $this->getContextPath();
        $exists = is_dir($contextPath);
        $inventory = $this->getExportInventory($contextPath);
        $folderSize = $exists ? $this->getFolderSize($contextPath) : null;
        $readmePath = $contextPath . 'README.md';
        $lastModified = ($exists && file_exists($readmePath)) ? filemtime($readmePath) : null;

        return (new ContextDashboard($this))->render([
            'contextPath' => $contextPath,
            'exists' => $exists,
            'inventory' => $inventory,
            'stats' => $this->getSiteStats(),
            'settingsUrl' => $this->config->urls->admin . "module/edit?name=Context",
            'csrfInput' => $this->getCsrfInputMarkup(),
            'formatsLabel' => $this->exportFormatLabel($this->normalizeExportFormats()),
            'folderSize' => $folderSize,
            'lastModified' => $lastModified
        ]);
    }

    /**
     * Get folder size
     */
    protected function getFolderSize($path) {
        return $this->filesystem()->getFolderSize($path);
    }

    protected function getExportInventory($path) {
        return $this->filesystem()->getExportInventory($path);
    }

    protected function convertToToon($data) {
        if($this->_toon === null) {
            $this->_toon = new ContextToon();
        }
        return $this->_toon->convert($data);
    }

    /**
     * Module settings page
     */
    public static function getModuleConfigInputfields(array $data) {
        self::loadDependencies();
        return ContextConfigFields::build($data);
    }
}
