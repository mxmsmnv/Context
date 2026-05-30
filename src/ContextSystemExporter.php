<?php namespace ProcessWire;

/**
 * Exports site/system configuration, module inventory, and custom classes.
 */
class ContextSystemExporter {

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

    public function exportConfig() {
        return [
            'site_name' => $this->module->config->httpHost,
            'admin_url' => $this->module->config->urls->admin,
            'pw_version' => $this->module->config->version,
            'php_version' => phpversion(),
            'timezone' => $this->module->config->timezone,
            'debug_mode' => $this->module->config->debug,
            'charset' => $this->module->config->dbCharset,
            'exported_at' => date('Y-m-d H:i:s'),
            'export_version' => Context::VERSION
        ];
    }

    public function exportModules() {
        $modules = [];

        foreach($this->module->modules as $moduleName) {
            try {
                $info = $this->module->modules->getModuleInfo($moduleName, ['verbose' => true]);

                if(empty($info['summary']) || empty($info['author'])) {
                    $moduleFile = $this->module->modules->getModuleFile($moduleName);
                    if($moduleFile && file_exists($moduleFile)) {
                        $fileContent = $this->invoke('readFile', $moduleFile);

                        if(empty($info['summary']) && preg_match('/[\'"]summary[\'"]\s*=>\s*[\'"]([^\'"]+)[\'"]/i', $fileContent, $summaryMatch)) {
                            $info['summary'] = $summaryMatch[1];
                        }

                        if(empty($info['author']) && preg_match('/[\'"]author[\'"]\s*=>\s*[\'"]([^\'"]+)[\'"]/i', $fileContent, $authorMatch)) {
                            $info['author'] = $authorMatch[1];
                        }
                    }
                }

                if(isset($info['title'])) {
                    $modules[] = [
                        'name' => is_object($moduleName) ? $moduleName->className() : (string)$moduleName,
                        'title' => $info['title'] ?? '',
                        'version' => $info['version'] ?? '',
                        'summary' => $info['summary'] ?? '',
                        'author' => $info['author'] ?? ''
                    ];
                }
            } catch(\Exception $e) {
                continue;
            }
        }

        usort($modules, function($a, $b) {
            return strcmp($a['name'], $b['name']);
        });

        return $modules;
    }

    public function exportCustomClasses() {
        $classes = [];
        $classesPath = $this->module->config->paths->site . 'classes/';

        if(!is_dir($classesPath)) {
            return $classes;
        }

        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($classesPath, \RecursiveDirectoryIterator::SKIP_DOTS)
            );

            foreach($iterator as $file) {
                if(!$file->isFile() || $file->getExtension() !== 'php') continue;

                $filePath = $file->getRealPath();
                if($file->isLink() || $filePath === false) continue;

                $relativePath = str_replace($classesPath, '', $filePath);
                $content = $this->invoke('readFile', $filePath);
                $contentNoComments = preg_replace('/\/\*.*?\*\/|\/\/.*/s', '', $content);

                $namespace = '';
                if(preg_match('/namespace\s+([^;]+);/', $content, $nsMatch)) {
                    $namespace = trim($nsMatch[1]);
                }

                if(preg_match('/\bclass\s+(\w+)(?:\s+extends\s+([\w\\\\]+))?/i', $contentNoComments, $classMatch)) {
                    $className = $classMatch[1];
                    $extends = isset($classMatch[2]) ? trim($classMatch[2], '\\') : null;

                    preg_match_all('/(?:public|protected|private)\s+(?:static\s+)?function\s+(\w+)\s*\(/i', $content, $methodMatches);
                    $methods = array_unique($methodMatches[1]);
                    $methods = array_filter($methods, function($method) {
                        return !in_array($method, ['__construct', '__destruct', '__get', '__set', '__call', '__toString', '__isset', '__unset']);
                    });

                    $classInfo = [
                        'name' => $className,
                        'file' => $relativePath,
                        'namespace' => $namespace,
                        'extends' => $extends,
                        'methods' => array_values($methods),
                        'is_page_class' => ($extends && (stripos($extends, 'Page') !== false)),
                        'full_name' => $namespace ? $namespace . '\\' . $className : $className
                    ];

                    if(preg_match('/\/\*\*\s*\n\s*\*\s*(.+?)(?:\n\s*\*\s*\n|\*\/)/s', $content, $docMatch)) {
                        $description = trim($docMatch[1]);
                        $description = preg_replace('/^\s*\*\s*/m', '', $description);
                        $classInfo['description'] = $description;
                    }

                    $classes[] = $classInfo;
                }
            }

            usort($classes, function($a, $b) {
                return strcmp($a['name'], $b['name']);
            });
        } catch(\Exception $e) {
            return [];
        }

        return $classes;
    }

    protected function invoke($method, ...$args) {
        return ($this->call)($method, ...$args);
    }
}
