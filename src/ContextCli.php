<?php namespace ProcessWire;

/**
 * CLI command runner for the Context module.
 */
class ContextCli {

    /** @var Context */
    protected $module;

    public function __construct(Context $module) {
        $this->module = $module;
    }

    public function handle($action, $argv) {
        switch($action) {
            case 'export':
                $this->export($argv);
                break;
            case 'stats':
                $this->stats();
                break;
            case 'query':
                $this->query($argv);
                break;
            case 'eval':
                $this->evalCode($argv);
                break;
            case 'stdin':
                $this->stdin();
                break;
            case 'help':
                $this->help();
                break;
            default:
                echo "Unknown command: --context-{$action}\n";
                $this->help();
                exit(1);
        }

        exit(0);
    }

    protected function export($argv) {
        echo "🚀 Starting Context export...\n\n";

        $toonOnly = in_array('--toon-only', $argv);
        $jsonOnly = in_array('--json-only', $argv);

        if($toonOnly) {
            $this->callModule('applyExportFormats', [['toon']]);
            echo "📦 Mode: TOON format only\n";
        } else if($jsonOnly) {
            $this->callModule('applyExportFormats', [['json']]);
            echo "📦 Mode: JSON format only\n";
        } else {
            $formats = $this->callModule('applyExportFormats');
            echo "📦 Formats: " . $this->callModule('exportFormatLabel', [$formats]) . "\n";
        }

        try {
            $startTime = microtime(true);
            $aiPath = $this->callModule('ensureFolder', [$this->module->getContextPath()]);

            echo "📁 Export path: {$aiPath}\n\n";

            $this->callModule('exportAll', [$aiPath, function($message) {
                echo $message . "\n";
            }]);

            if($toonOnly) {
                $removed = $this->callModule('pruneExportFormats', [$aiPath, ['json', 'csv']]);
                echo "🧹 Removed {$removed} JSON/CSV files for TOON-only export\n";
            } elseif($jsonOnly) {
                $removed = $this->callModule('pruneExportFormats', [$aiPath, ['toon', 'csv']]);
                echo "🧹 Removed {$removed} TOON/CSV files for JSON-only export\n";
            }

            $duration = round(microtime(true) - $startTime, 2);

            echo "\n✅ Context exported successfully!\n";
            echo "⏱️  Completed in {$duration} seconds\n";
            echo "📂 Files available at: {$aiPath}\n";

        } catch(\Exception $e) {
            echo "\n❌ Export failed: " . $e->getMessage() . "\n";
            exit(1);
        }
    }

    protected function stats() {
        echo "📊 Context Module Statistics\n";
        echo str_repeat('=', 60) . "\n\n";

        $templates = 0;
        foreach($this->module->templates as $t) {
            if(!($t->flags & Template::flagSystem)) $templates++;
        }

        $fields = 0;
        foreach($this->module->fields as $f) {
            if(!($f->flags & Field::flagSystem)) $fields++;
        }

        $pages = $this->module->pages->count("id>0");

        $contextPath = $this->module->getContextPath();
        $exportSize = is_dir($contextPath) ? $this->getDirectorySize($contextPath) : 0;

        echo "Templates:      {$templates}\n";
        echo "Fields:         {$fields}\n";
        echo "Pages:          {$pages}\n";
        echo "\n";
        echo "Export Path:    {$contextPath}\n";
        echo "Export Size:    " . $this->formatBytes($exportSize) . "\n";
        echo "\n";

        echo "Configuration:\n";
        echo "  Formats:      " . $this->callModule('exportFormatLabel', [$this->callModule('normalizeExportFormats')]) . "\n";
        echo "  Samples:      " . ($this->module->export_samples ? 'Enabled' : 'Disabled') . "\n";
        echo "  SKILL.md:     " . ($this->module->generate_skill_md ? 'Enabled' : 'Disabled') . "\n";
        echo "\n";
    }

    protected function query($argv) {
        if(empty($argv[2])) {
            echo "❌ Error: Query parameter required\n";
            echo "Usage: php index.php --context-query \"templates\"\n";
            exit(1);
        }

        switch($argv[2]) {
            case 'templates':
                $this->queryTemplates();
                break;
            case 'fields':
                $this->queryFields();
                break;
            case 'pages':
                $this->queryPages($argv);
                break;
            default:
                echo "❌ Unknown query: {$argv[2]}\n";
                echo "Available queries: templates, fields, pages\n";
                exit(1);
        }
    }

    protected function queryTemplates() {
        echo "📝 Templates:\n\n";

        foreach($this->module->templates as $t) {
            if($t->flags & Template::flagSystem) continue;

            $fieldCount = count($t->fields);
            $pageCount = $this->module->pages->count("template={$t->name}");

            echo "  • {$t->name}\n";
            echo "    Label: {$t->label}\n";
            echo "    Fields: {$fieldCount}\n";
            echo "    Pages: {$pageCount}\n";
            echo "\n";
        }
    }

    protected function queryFields() {
        echo "📋 Fields:\n\n";

        foreach($this->module->fields as $f) {
            if($f->flags & Field::flagSystem) continue;

            echo "  • {$f->name}\n";
            echo "    Type: {$f->type}\n";
            echo "    Label: {$f->label}\n";
            echo "\n";
        }
    }

    protected function queryPages($argv) {
        $selector = isset($argv[3]) ? $argv[3] : 'limit=10';

        echo "📄 Pages ({$selector}):\n\n";

        $pages = $this->module->pages->find($selector);

        foreach($pages as $p) {
            echo "  • {$p->title}\n";
            echo "    ID: {$p->id}\n";
            echo "    Template: {$p->template->name}\n";
            echo "    URL: {$p->url}\n";
            echo "\n";
        }

        echo "Total: " . count($pages) . " pages\n";
    }

    protected function evalCode($argv) {
        if(empty($argv[2])) {
            echo "❌ Error: Code parameter required\n";
            echo "Usage: php index.php --context-eval 'echo \$pages->count();'\n";
            exit(1);
        }

        $this->runCode($argv[2]);
    }

    protected function stdin() {
        $code = file_get_contents('php://stdin');

        if(empty(trim($code))) {
            echo "❌ Error: No code provided via stdin\n";
            echo "Usage: echo 'CODE' | php index.php --context-stdin\n";
            exit(1);
        }

        $this->runCode($code);
    }

    protected function runCode($code) {
        $pages = $this->module->pages;
        $templates = $this->module->templates;
        $fields = $this->module->fields;
        $modules = $this->module->modules;
        $config = $this->module->config;
        $users = $this->module->users;
        $session = $this->module->session;
        $input = $this->module->input;
        $sanitizer = $this->module->sanitizer;
        $database = $this->module->database;
        $cache = $this->module->cache;
        $log = $this->module->log;
        $files = $this->module->files;
        $context = $this->module;

        $code = '?>' . '<?php namespace ProcessWire; ' . $code;

        try {
            eval($code);
        } catch(\Throwable $e) {
            echo "❌ Error: " . $e->getMessage() . "\n";
            echo "   Line: " . $e->getLine() . "\n";
            exit(1);
        }
    }

    protected function help() {
        echo "\n";
        echo "ProcessWire Context Module - CLI Commands\n";
        echo str_repeat('=', 60) . "\n\n";
        echo "Usage:\n";
        echo "  php index.php --context-export [options]\n";
        echo "  php index.php --context-stats\n";
        echo "  php index.php --context-query <type> [selector]\n";
        echo "  php index.php --context-eval 'CODE'\n";
        echo "  echo 'CODE' | php index.php --context-stdin\n";
        echo "  php index.php --context-help\n";
        echo "\n";
        echo "Export Commands:\n";
        echo "  --context-export              Export selected module formats\n";
        echo "  --context-export --toon-only  Export TOON and remove JSON/CSV artifacts\n";
        echo "  --context-export --json-only  Export JSON and remove TOON/CSV artifacts\n";
        echo "\n";
        echo "Query Commands:\n";
        echo "  --context-query templates     List all templates\n";
        echo "  --context-query fields        List all fields\n";
        echo "  --context-query pages [sel]   List pages (with optional selector)\n";
        echo "\n";
        echo "API Access Commands:\n";
        echo "  --context-eval 'CODE'         Execute PHP code with PW API access\n";
        echo "  echo 'CODE' | php index.php --context-stdin\n";
        echo "\n";
        echo "Stats Commands:\n";
        echo "  --context-stats               Show module statistics\n";
        echo "\n";
        echo "Examples:\n";
        echo "  # Export\n";
        echo "  php index.php --context-export\n";
        echo "  php index.php --context-export --toon-only\n";
        echo "\n";
        echo "  # Query\n";
        echo "  php index.php --context-query templates\n";
        echo "  php index.php --context-query pages \"template=product, limit=5\"\n";
        echo "\n";
        echo "  # API Access\n";
        echo "  php index.php --context-eval 'echo \$pages->count() . \" pages\\n\";'\n";
        echo "  php index.php --context-eval '\$p = \$pages->get(1); echo \$p->title;'\n";
        echo "\n";
        echo "  # Multi-line code\n";
        echo "  echo 'foreach(\$templates as \$t) {\n";
        echo "    if(\$t->flags & Template::flagSystem) continue;\n";
        echo "    echo \$t->name . \"\\n\";\n";
        echo "  }' | php index.php --context-stdin\n";
        echo "\n";
    }

    protected function callModule($method, array $args = []) {
        return \Closure::bind(function() use ($method, $args) {
            return $this->$method(...$args);
        }, $this->module, get_class($this->module))();
    }

    protected function getDirectorySize($path) {
        $size = 0;
        if(!is_dir($path)) return 0;

        try {
            foreach(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)) as $file) {
                if($file->isLink()) continue;
                if($file->isFile()) $size += $file->getSize();
            }
        } catch(\Exception $e) {
            return 0;
        }

        return $size;
    }

    protected function formatBytes($bytes, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}
