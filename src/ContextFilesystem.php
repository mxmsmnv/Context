<?php namespace ProcessWire;

/**
 * Filesystem boundary for Context exports.
 */
class ContextFilesystem {

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

    public function getContextPath() {
        $path = $this->module->export_path ?: 'site/assets/cache/context/';

        if(strpos($path, '/') === 0) {
            return rtrim($path, '/') . '/';
        }

        $path = trim($path, '/');
        return $this->module->config->paths->root . $path . '/';
    }

    public function ensureFolder($path) {
        $this->validateContextPath($path);

        if(!is_dir($path)) {
            if(!wireMkdir($path, true)) {
                throw new WireException("Cannot create folder: $path");
            }
        }

        $this->validateContextPath($path);

        $htaccess = $path . '.htaccess';
        if(!file_exists($htaccess)) {
            $content = "# Deny access to Context exports\n";
            $content .= "# Remove this file if you need public access\n";
            $content .= "Deny from all\n";
            $this->writeFile($htaccess, $content);
        }

        return $path;
    }

    public function validateContextPath($path) {
        $path = $this->normalizePath($path);
        $modulePath = $this->normalizePath(dirname(__DIR__));
        $realPath = realpath($path);
        $pathsToCheck = [$path];

        if(is_link($path)) {
            throw new WireException("Refusing to use symlinked Context export path: {$path}");
        }

        if($realPath !== false) {
            $pathsToCheck[] = $this->normalizePath($realPath);
        }

        $blockedPaths = [
            $this->module->config->paths->root ?? null,
            $this->module->config->paths->site ?? null,
            $this->module->config->paths->assets ?? null,
            $this->module->config->paths->cache ?? null,
            $this->module->config->paths->templates ?? null,
            '/',
        ];

        foreach($blockedPaths as $blockedPath) {
            if(!$blockedPath) continue;
            $blockedPath = $this->normalizePath($blockedPath);
            foreach($pathsToCheck as $pathToCheck) {
                if($pathToCheck === $blockedPath) {
                    throw new WireException("Refusing to use unsafe Context export path: {$path}");
                }
            }
        }

        foreach($pathsToCheck as $pathToCheck) {
            if(strpos($pathToCheck . '/', $modulePath . '/') === 0) {
                throw new WireException("Refusing to write Context exports inside the module directory: {$path}");
            }
        }
    }

    public function normalizePath($path) {
        $path = str_replace('\\', '/', (string)$path);
        $path = preg_replace('#/+#', '/', $path);
        return rtrim($path, '/') ?: '/';
    }

    public function writeFile($path, $contents) {
        $bytes = file_put_contents($path, $contents, LOCK_EX);
        if($bytes === false) {
            throw new WireException("Failed to write file: {$path}");
        }
        return $bytes;
    }

    public function readFile($path, $maxBytes = null) {
        if(!is_string($path) || $path === '' || is_link($path) || !is_file($path) || !is_readable($path)) {
            return '';
        }

        $contents = $maxBytes === null
            ? file_get_contents($path)
            : file_get_contents($path, false, null, 0, (int)$maxBytes);

        return $contents === false ? '' : $contents;
    }

    public function readJsonFile($path) {
        $contents = $this->readFile($path);
        if($contents === '') return [];

        $data = json_decode($contents, true);
        return is_array($data) ? $data : [];
    }

    public function writeJsonFile($path, $data, $flags = null) {
        $flags = $flags ?? (JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $json = json_encode($data, $flags);
        if($json === false) {
            throw new WireException("Failed to encode JSON for {$path}: " . json_last_error_msg());
        }
        return $this->writeFile($path, $json);
    }

    public function writeToonFile($path, $data) {
        return $this->writeFile($path, $this->invoke('convertToToon', $data));
    }

    public function pruneExportFormats($path, array $extensions) {
        if(!is_dir($path)) return 0;
        $this->validateContextPath($path);

        $extensions = array_map('strtolower', $extensions);
        $removed = 0;
        $root = rtrim($this->normalizePath($path), '/');
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach($iterator as $file) {
            if($file->isLink()) continue;
            if(!$file->isFile()) continue;
            $filePath = $file->getRealPath();
            if($filePath === false || strpos($this->normalizePath($filePath), $root . '/') !== 0) continue;
            $extension = strtolower(pathinfo($file->getFilename(), PATHINFO_EXTENSION));
            if(!in_array($extension, $extensions, true)) continue;
            if(@unlink($filePath)) $removed++;
        }

        return $removed;
    }

    public function removeExportPath($basePath, $relativePath) {
        $base = rtrim($this->normalizePath($basePath), '/');
        $target = $base . '/' . ltrim(str_replace('\\', '/', (string)$relativePath), '/');

        if(!$this->isPathInsideExportRoot($target, $base)) return false;
        if(is_link($target)) return @unlink($target);
        if(!file_exists($target)) return false;

        if(is_dir($target)) {
            try {
                $iterator = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($target, \FilesystemIterator::SKIP_DOTS),
                    \RecursiveIteratorIterator::CHILD_FIRST
                );

                foreach($iterator as $file) {
                    $path = $file->getPathname();
                    if(!$this->isPathInsideExportRoot($path, $base)) continue;
                    if($file->isLink() || $file->isFile()) {
                        @unlink($path);
                    } elseif($file->isDir()) {
                        @rmdir($path);
                    }
                }
            } catch(\Exception $e) {
                return false;
            }

            return @rmdir($target);
        }

        return is_file($target) ? @unlink($target) : false;
    }

    public function removeEmptyExportDirectory($basePath, $relativePath) {
        $base = rtrim($this->normalizePath($basePath), '/');
        $target = $base . '/' . ltrim(str_replace('\\', '/', (string)$relativePath), '/');

        if(!$this->isPathInsideExportRoot($target, $base)) return false;
        if(!is_dir($target) || is_link($target)) return false;

        $items = scandir($target);
        if($items === false) return false;

        return count(array_diff($items, ['.', '..'])) === 0 ? @rmdir($target) : false;
    }

    public function isPathInsideExportRoot($path, $root) {
        $path = $this->normalizePath($path);
        $root = rtrim($this->normalizePath($root), '/');

        return $path === $root || strpos($path . '/', $root . '/') === 0;
    }

    public function getFolderSize($path) {
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

    public function getExportInventory($path) {
        $inventory = [
            'exists' => is_dir($path),
            'total_files' => 0,
            'toon' => 0,
            'json' => 0,
            'csv' => 0,
            'readme' => false,
            'skill' => false,
            'project_context' => false,
            'api_dir' => false,
            'metadata_dir' => false
        ];

        if(!$inventory['exists']) return $inventory;

        $base = rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $inventory['api_dir'] = is_dir($base . 'api') && !is_link($base . 'api');
        $inventory['metadata_dir'] = is_dir($base . 'metadata') && !is_link($base . 'metadata');

        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)
            );

            foreach($iterator as $file) {
                if($file->isLink() || !$file->isFile()) continue;

                $inventory['total_files']++;
                $ext = strtolower($file->getExtension());
                if(isset($inventory[$ext])) $inventory[$ext]++;

                $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($base)));
                if($relative === 'README.md') $inventory['readme'] = true;
                if($relative === 'SKILL.md') $inventory['skill'] = true;
                if($relative === 'prompts/project-summary.md') $inventory['project_context'] = true;
            }
        } catch(\Exception $e) {
            // Keep partial inventory for dashboard rendering.
        }

        return $inventory;
    }

    protected function invoke($method, ...$args) {
        return ($this->call)($method, ...$args);
    }
}
