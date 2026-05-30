<?php namespace ProcessWire;

/**
 * Updates optional IDE integration files with Context export paths.
 */
class ContextIntegrationExporter {

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

    public function createIntegrationFiles() {
        if(!$this->module->export_integrations) return;

        $rootDir = $this->module->config->paths->root;
        $this->updateCursorRules($rootDir);
        $this->updateClaudeCode($rootDir);
    }

    public function updateCursorRules($rootDir) {
        $cursorRulesPath = $rootDir . '.cursorrules';
        if(is_link($cursorRulesPath)) {
            throw new WireException("Refusing to update symlinked integration file: {$cursorRulesPath}");
        }

        $contextPath = $this->module->getContextPath();
        $promptsPath = $contextPath . 'prompts/project-context.md';

        $newLines = [
            "# ProcessWire Context",
            "Root: {$rootDir}",
            "Context: {$contextPath}",
        ];

        if($this->module->export_prompts) {
            $newLines[] = "Follow rules in {$promptsPath}";
        }

        if(file_exists($cursorRulesPath)) {
            $content = $this->invoke('readFile', $cursorRulesPath);
            $lines = explode("\n", $content);

            $needsUpdate = false;
            $linesToAdd = [];

            foreach($newLines as $newLine) {
                $found = false;
                foreach($lines as $line) {
                    if(stripos($line, $contextPath) !== false ||
                       stripos($line, $promptsPath) !== false ||
                       stripos($line, 'ProcessWire Context') !== false) {
                        $found = true;
                        break;
                    }
                }

                if(!$found) {
                    $linesToAdd[] = $newLine;
                    $needsUpdate = true;
                }
            }

            if($needsUpdate) {
                $content .= "\n\n" . implode("\n", $linesToAdd);
                $this->invoke('writeFile', $cursorRulesPath, $content);
            }
        } else {
            $this->invoke('writeFile', $cursorRulesPath, implode("\n", $newLines));
        }
    }

    public function updateClaudeCode($rootDir) {
        $claudeCodePath = $rootDir . '.claudecode.json';
        if(is_link($claudeCodePath)) {
            throw new WireException("Refusing to update symlinked integration file: {$claudeCodePath}");
        }

        $contextPath = $this->module->getContextPath();
        $templateContextFile = $this->module->export_toon_format ? 'templates.toon' : 'templates.json';

        $contextPaths = [
            $contextPath . $templateContextFile,
        ];

        if($this->module->export_prompts) {
            $contextPaths[] = $contextPath . "prompts/project-context.md";
        }

        if(file_exists($claudeCodePath)) {
            $config = $this->invoke('readJsonFile', $claudeCodePath);

            if(!$config) {
                $config = [
                    "name" => "PW-" . $this->module->config->httpHost,
                    "context" => []
                ];
            }

            if(!isset($config['context'])) {
                $config['context'] = [];
            }

            $needsUpdate = false;
            foreach($contextPaths as $path) {
                if(!in_array($path, $config['context'])) {
                    $config['context'][] = $path;
                    $needsUpdate = true;
                }
            }

            if($needsUpdate) {
                $this->invoke('writeJsonFile', $claudeCodePath, $config);
            }
        } else {
            $config = [
                "name" => "PW-" . $this->module->config->httpHost,
                "context" => $contextPaths
            ];
            $this->invoke('writeJsonFile', $claudeCodePath, $config);
        }
    }

    protected function invoke($method, ...$args) {
        return ($this->call)($method, ...$args);
    }
}
