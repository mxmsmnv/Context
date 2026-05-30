<?php namespace ProcessWire;

/**
 * Keeps configured Context exports refreshed after template/field changes.
 */
class ContextAutoUpdater {

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

    public function handle($event = null) {
        try {
            $formats = $this->invoke('applyExportFormats');
            $contextPath = $this->invoke('ensureFolder', $this->module->getContextPath());

            $this->invoke('exportAll', $contextPath);

            $this->module->log('Context auto-updated (' . $this->invoke('exportFormatLabel', $formats) . ')');
        } catch(\Exception $e) {
            $this->module->log('Context auto-update failed: ' . $e->getMessage());
        }
    }

    protected function invoke($method, ...$args) {
        return ($this->call)($method, ...$args);
    }
}
