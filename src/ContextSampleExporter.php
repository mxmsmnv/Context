<?php namespace ProcessWire;

/**
 * Exports page and RepeaterMatrix content samples.
 */
class ContextSampleExporter {

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

    public function exportSamples() {
        $samplesPath = $this->invoke('ensureFolder', $this->module->getContextPath() . 'samples/');
        $allSamples = [];

        foreach($this->module->templates as $template) {
            if($template->flags & Template::flagSystem) continue;

            $pages = $this->module->pages->find("template={$template->name}, limit={$this->module->samples_count}, sort=random");
            if(!$pages->count()) continue;

            $templateSamples = [];
            foreach($pages as $page) {
                $pageData = [
                    'id' => $page->id,
                    'name' => $page->name,
                    'title' => $page->title,
                    'url' => $page->url,
                    'created' => date('Y-m-d H:i:s', $page->created),
                    'modified' => date('Y-m-d H:i:s', $page->modified),
                    'fields' => []
                ];

                foreach($template->fields as $field) {
                    if($this->invoke('isSensitiveSampleField', $field)) continue;

                    $value = $page->get($field->name);
                    if($this->invoke('isEmptySampleValue', $value)) continue;

                    $pageData['fields'][$field->name] = $this->invoke('serializeSampleFieldValue', $field, $value);
                }

                $templateSamples[] = $pageData;
            }

            $allSamples[$template->name] = [
                'template' => $template->name,
                'label' => $template->label ?: $template->name,
                'samples' => $templateSamples
            ];

            $this->invoke('writeJsonFile', $samplesPath . "{$template->name}-samples.json", $templateSamples);

            if($this->module->export_toon_format) {
                $this->invoke('writeToonFile', $samplesPath . "{$template->name}-samples.toon", ['samples' => $templateSamples]);
            }
        }

        $this->invoke('writeJsonFile', $samplesPath . '_all-samples.json', $allSamples);

        if($this->module->export_toon_format) {
            $this->invoke('writeToonFile', $samplesPath . '_all-samples.toon', $allSamples);
        }

        return $allSamples;
    }

    public function exportMatrixSamples() {
        if(!$this->module->export_samples) return [];

        $samplesPath = $this->invoke('ensureFolder', $this->module->getContextPath() . 'samples/');
        $allMatrixSamples = [];

        $matrixFields = [];
        foreach($this->module->fields as $field) {
            if($field->type->className() === 'FieldtypeRepeaterMatrix') {
                $matrixFields[] = $field;
            }
        }

        if(empty($matrixFields)) return [];

        $this->module->log("Exporting Matrix samples for " . count($matrixFields) . " fields");

        foreach($matrixFields as $matrixField) {
            $templatesWithMatrix = [];
            foreach($this->module->templates as $template) {
                if($template->hasField($matrixField)) {
                    $templatesWithMatrix[] = $template;
                }
            }

            if(empty($templatesWithMatrix)) continue;

            foreach($templatesWithMatrix as $template) {
                $pages = $this->module->pages->find("template={$template->name}, {$matrixField->name}.count>0, limit={$this->module->samples_count}, sort=random");
                if(!$pages->count()) continue;

                foreach($pages as $page) {
                    $matrixItems = $page->get($matrixField->name);
                    if(!$matrixItems || !$matrixItems->count()) continue;

                    foreach($matrixItems as $matrixItem) {
                        $matrixTypeInt = (int)$matrixItem->get('repeater_matrix_type');
                        $matrixTypeName = $matrixField->type->getMatrixTypeName($matrixTypeInt, $matrixField);
                        if(!$matrixTypeName) continue;

                        try {
                            $matrixTypeLabel = $matrixField->type->getMatrixTypeLabel($matrixTypeName, $matrixField);
                        } catch(\Exception $e) {
                            $matrixTypeLabel = $matrixTypeName;
                        }

                        if(!isset($allMatrixSamples[$matrixTypeName])) {
                            $allMatrixSamples[$matrixTypeName] = [
                                'type_name' => $matrixTypeName,
                                'type_label' => $matrixTypeLabel,
                                'parent_field' => $matrixField->name,
                                'parent_field_label' => $matrixField->label,
                                'samples' => []
                            ];
                        }

                        if(count($allMatrixSamples[$matrixTypeName]['samples']) >= $this->module->samples_count) {
                            continue;
                        }

                        $itemData = [
                            'id' => $matrixItem->id,
                            'type' => $matrixTypeName,
                            'type_label' => $matrixTypeLabel,
                            'fields' => []
                        ];

                        foreach($matrixItem->template->fields as $itemField) {
                            if($this->invoke('isSensitiveSampleField', $itemField)) continue;

                            $value = $matrixItem->get($itemField->name);
                            if($this->invoke('isEmptySampleValue', $value)) continue;

                            $itemData['fields'][$itemField->name] = $this->invoke('serializeSampleFieldValue', $itemField, $value);
                        }

                        $allMatrixSamples[$matrixTypeName]['samples'][] = $itemData;
                    }
                }
            }
        }

        foreach($allMatrixSamples as $matrixTypeName => $matrixSampleData) {
            $this->invoke('writeJsonFile', $samplesPath . "{$matrixTypeName}-samples.json", $matrixSampleData['samples']);

            if($this->module->export_toon_format) {
                $this->invoke('writeToonFile', $samplesPath . "{$matrixTypeName}-samples.toon", ['samples' => $matrixSampleData['samples']]);
            }

            $this->module->log("Created samples for Matrix template: {$matrixTypeName} (" . count($matrixSampleData['samples']) . " samples)");
        }

        return $allMatrixSamples;
    }

    protected function invoke($method, ...$args) {
        return ($this->call)($method, ...$args);
    }
}
