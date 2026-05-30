<?php namespace ProcessWire;

/**
 * Detects frontend CSS/JS stack from module settings, package.json, and templates.
 */
class ContextFrontendDetector {

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

    public function detectFrontendStack() {
        if($this->module->css_framework && $this->module->css_framework !== 'auto') {
            $frameworkMap = [
                'tailwind' => 'Tailwind CSS',
                'bootstrap' => 'Bootstrap',
                'uikit' => 'UIkit',
                'vanilla' => 'Vanilla CSS',
                'none' => 'None'
            ];

            $manualFramework = $frameworkMap[$this->module->css_framework] ?? 'Vanilla CSS';
            $jsStack = $this->detectJavaScriptFrameworks();

            return $manualFramework . ($jsStack ? ', ' . $jsStack : '');
        }

        $stack = [];
        $rootDir = $this->module->config->paths->root;
        $templatesPath = $this->module->config->paths->templates;

        if(file_exists($rootDir . 'package.json')) {
            $pkg = $this->invoke('readJsonFile', $rootDir . 'package.json');
            $deps = array_merge($pkg['dependencies'] ?? [], $pkg['devDependencies'] ?? []);

            $map = [
                'tailwindcss' => 'Tailwind CSS',
                'bootstrap' => 'Bootstrap',
                'alpinejs' => 'Alpine.js',
                'vue' => 'Vue.js',
                'react' => 'React',
                'htmx.org' => 'HTMX',
                'uikit' => 'UIkit',
                'jquery' => 'jQuery'
            ];

            foreach($map as $key => $name) {
                if(isset($deps[$key])) $stack[] = $name;
            }
        }

        $contentSample = $this->collectTemplateSample($templatesPath, ['php', 'inc', 'js', 'css'], 100);
        $signatures = [
            'Tailwind CSS' => ['@tailwind', 'text-', 'bg-', 'dark:', 'sm:flex'],
            'Bootstrap' => ['container-fluid', 'col-md-', 'btn-primary', 'data-bs-'],
            'Alpine.js' => ['x-data', 'x-init', 'x-on:', '@click'],
            'HTMX' => ['hx-get', 'hx-post', 'hx-target', 'hx-swap'],
            'UIkit' => ['uk-container', 'uk-grid', 'uk-navbar'],
            'jQuery' => ['$(document)', '$.ajax', 'jQuery(']
        ];

        foreach($signatures as $name => $tokens) {
            if(in_array($name, $stack)) continue;
            foreach($tokens as $token) {
                if(strpos($contentSample, $token) !== false) {
                    $stack[] = $name;
                    break;
                }
            }
        }

        return !empty($stack) ? implode(', ', array_unique($stack)) : 'Vanilla HTML/PHP';
    }

    public function detectJavaScriptFrameworks() {
        $stack = [];
        $rootDir = $this->module->config->paths->root;
        $templatesPath = $this->module->config->paths->templates;

        if(file_exists($rootDir . 'package.json')) {
            $pkg = $this->invoke('readJsonFile', $rootDir . 'package.json');
            $deps = array_merge($pkg['dependencies'] ?? [], $pkg['devDependencies'] ?? []);

            $jsMap = [
                'alpinejs' => 'Alpine.js',
                'vue' => 'Vue.js',
                'react' => 'React',
                'htmx.org' => 'HTMX',
                'jquery' => 'jQuery'
            ];

            foreach($jsMap as $key => $name) {
                if(isset($deps[$key])) $stack[] = $name;
            }
        }

        $contentSample = $this->collectTemplateSample($templatesPath, ['php', 'inc', 'js'], 50);
        $jsSignatures = [
            'Alpine.js' => ['x-data', 'x-init', 'x-on:', '@click'],
            'HTMX' => ['hx-get', 'hx-post', 'hx-target', 'hx-swap'],
            'jQuery' => ['$(document)', '$.ajax', 'jQuery(']
        ];

        foreach($jsSignatures as $name => $tokens) {
            if(in_array($name, $stack)) continue;
            foreach($tokens as $token) {
                if(strpos($contentSample, $token) !== false) {
                    $stack[] = $name;
                    break;
                }
            }
        }

        return !empty($stack) ? implode(', ', array_unique($stack)) : '';
    }

    protected function collectTemplateSample($templatesPath, array $extensions, $limit) {
        $contentSample = '';

        if(!is_dir($templatesPath)) {
            return $contentSample;
        }

        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($templatesPath));
        $count = 0;

        foreach($files as $file) {
            if($file->isLink() || $file->isDir()) continue;
            if(!in_array($file->getExtension(), $extensions)) continue;

            $contentSample .= $this->invoke('readFile', $file->getRealPath(), 1024);
            $count++;

            if($count > $limit) break;
        }

        return $contentSample;
    }

    protected function invoke($method, ...$args) {
        return ($this->call)($method, ...$args);
    }
}
