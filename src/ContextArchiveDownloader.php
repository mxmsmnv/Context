<?php namespace ProcessWire;

/**
 * Streams the exported context folder as a ZIP archive.
 */
class ContextArchiveDownloader {

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

    public function execute() {
        $this->invoke('requireContextAccess');

        try {
            $this->invoke('requirePostCsrf');
        } catch(\Exception $e) {
            $this->module->error($e->getMessage());
            $this->module->session->redirect($this->module->page->url);
            return;
        }

        $contextPath = $this->invoke('getContextPath');

        try {
            $this->invoke('validateContextPath', $contextPath);
        } catch(\Exception $e) {
            $this->module->error($e->getMessage());
            $this->module->session->redirect($this->module->page->url);
            return;
        }

        if(!is_dir($contextPath)) {
            $this->module->error("Nothing to download - export context first.");
            $this->module->session->redirect($this->module->page->url);
            return;
        }

        $siteName = $this->module->sanitizer->pageName($this->module->config->httpHost ?: 'context');
        $filename = 'context-' . $siteName . '-' . date('Ymd-His') . '.zip';
        $tmpFile = $this->createZip($contextPath);

        if($tmpFile === '') {
            $this->module->session->redirect($this->module->page->url);
            return;
        }

        $this->streamZip($tmpFile, $filename);
    }

    protected function createZip($contextPath) {
        $zip = new \ZipArchive();
        $tmpFile = tempnam(sys_get_temp_dir(), 'ctx_');

        if($tmpFile === false) {
            $this->module->error("Failed to create temporary ZIP file.");
            return '';
        }

        if($zip->open($tmpFile, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            @unlink($tmpFile);
            $this->module->error("Failed to create ZIP archive.");
            return '';
        }

        $contextRoot = rtrim($contextPath, '/');
        $baseName = basename($contextRoot);
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($contextPath, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach($iterator as $file) {
            if($file->isLink()) continue;

            $filePath = $file->getRealPath();
            if($filePath === false || strpos($filePath, $contextRoot . '/') !== 0) continue;

            $relativePath = $baseName . '/' . ltrim(substr($filePath, strlen($contextRoot)), '/');

            if($file->isDir()) {
                $zip->addEmptyDir($relativePath);
            } else {
                $zip->addFile($filePath, $relativePath);
            }
        }

        $zip->close();
        return $tmpFile;
    }

    protected function streamZip($tmpFile, $filename) {
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($tmpFile));
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        while(ob_get_level() > 0) {
            ob_end_clean();
        }

        readfile($tmpFile);
        unlink($tmpFile);
        exit;
    }

    protected function invoke($method, ...$args) {
        return ($this->call)($method, ...$args);
    }
}
