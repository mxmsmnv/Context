<?php namespace ProcessWire;

/**
 * Normalizes and applies configured export formats.
 */
class ContextExportFormats {

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

    public static function normalize($formats = null, $legacyToon = 1) {
        if($formats === null || $formats === '' || $formats === []) {
            $formats = $legacyToon ? ['toon', 'json', 'csv'] : ['json', 'csv'];
        } elseif(is_string($formats)) {
            $formats = preg_split('/[\s,|]+/', strtolower($formats), -1, PREG_SPLIT_NO_EMPTY);
        } elseif($formats instanceof \Traversable) {
            $formats = iterator_to_array($formats);
            $formats = array_map('strtolower', $formats);
        } elseif(is_array($formats)) {
            $formats = array_map('strtolower', $formats);
        } else {
            $formats = [];
        }

        $allowed = ['toon', 'json', 'csv'];
        $formats = array_values(array_unique(array_intersect($formats, $allowed)));

        if(empty($formats)) {
            $formats = ['toon'];
        }

        return $formats;
    }

    public function normalizeModuleFormats($formats = null) {
        if($formats === null) {
            $formats = $this->module->export_formats ?? null;
        }

        return self::normalize($formats, $this->module->export_toon_format);
    }

    public function apply($formats = null) {
        $formats = $this->normalizeModuleFormats($formats);
        $this->module->export_formats = implode(',', $formats);
        $this->module->export_toon_format = in_array('toon', $formats, true) ? 1 : 0;
        $this->setFlag('_exportJsonFormat', in_array('json', $formats, true));
        $this->setFlag('_exportCsvFormat', in_array('csv', $formats, true));
        return $formats;
    }

    public function label(?array $formats = null) {
        $formats = $formats ?: $this->normalizeModuleFormats();
        return strtoupper(implode(' + ', $formats));
    }

    protected function setFlag($property, $value) {
        return ($this->call)('setExportFormatFlag', $property, $value);
    }
}
