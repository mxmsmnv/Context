<?php namespace ProcessWire;

/**
 * Handles POST actions from the Context admin screen.
 */
class ContextAdminActions {

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

    public function executeExport() {
        $this->invoke('requireContextAccess');

        try {
            $this->invoke('requirePostCsrf');
        } catch(\Exception $e) {
            $this->module->error($e->getMessage());
            $this->module->session->redirect($this->module->page->url);
            return;
        }

        $startTime = microtime(true);

        try {
            $formats = $this->invoke('applyExportFormats');
            $aiPath = $this->invoke('ensureFolder', $this->invoke('getContextPath'));

            $this->module->message("🚀 Starting Context export (" . $this->invoke('exportFormatLabel', $formats) . ")...");
            $this->invoke('exportAll', $aiPath, function($message) {
                $this->module->message($message);
            });

            $duration = round(microtime(true) - $startTime, 2);

            $this->module->message("✅ Context successfully exported to: <strong>{$aiPath}</strong>");
            $this->module->message("⏱️ Export completed in {$duration} seconds");
            $this->module->log("Context exported successfully in {$duration}s");
            $this->module->session->redirect($this->module->page->url);
        } catch(\Exception $e) {
            $this->module->error("❌ Export failed: " . $e->getMessage());
            $this->module->log("Context export failed: " . $e->getMessage());
        }
    }

    protected function invoke($method, ...$args) {
        return ($this->call)($method, ...$args);
    }
}
