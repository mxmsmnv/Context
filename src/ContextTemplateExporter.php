<?php namespace ProcessWire;

/**
 * Exports ProcessWire templates, fields, CSV, and ProFields metadata.
 */
class ContextTemplateExporter {

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

    public function getTableColumns(Field $field) {
        $columns = [];
        try {
            $cols = $field->type->getColumnsByName($field);
            foreach($cols as $name => $col) {
                if(empty($name)) continue;
                $colData = [
                    'name' => $col['name'],
                    'label' => $col['label'] ?: $col['name'],
                    'type' => $col['type'] ?: 'text',
                ];
                if(!empty($col['options']) && is_array($col['options'])) {
                    $colData['options'] = array_values(array_filter($col['options'], 'strlen'));
                }
                if(!empty($col['selector'])) {
                    $colData['selector'] = $col['selector'];
                }
                $columns[] = $colData;
            }
        } catch(\Exception $e) {
            $this->module->log("getTableColumns({$field->name}) failed: " . $e->getMessage());
        }
        return $columns;
    }

    public function getComboSubfields(Field $field) {
        $subfields = [];
        try {
            $settings = $field->getComboSettings();
            foreach($settings->getSubfields() as $sub) {
                $subfieldData = [
                    'name' => $sub->name,
                    'label' => $sub->label ?: $sub->name,
                    'type' => $sub->type,
                    'required' => $sub->required ? 1 : 0,
                    'columnWidth' => $sub->columnWidth ?: 100,
                ];
                if($sub->description) $subfieldData['description'] = $sub->description;
                if($sub->notes) $subfieldData['notes'] = $sub->notes;
                if(!empty($sub->options)) {
                    $opts = $sub->options;
                    if(is_string($opts)) {
                        $opts = array_values(array_filter(array_map('trim', explode("\n", $opts)), 'strlen'));
                    }
                    if(!empty($opts)) $subfieldData['options'] = $opts;
                }
                $subfields[] = $subfieldData;
            }
        } catch(\Exception $e) {
            $this->module->log("getComboSubfields({$field->name}) failed: " . $e->getMessage());
        }
        return $subfields;
    }

    public function getMatrixTypesData(Field $field) {
        $types = [];
        try {
            $typesInfo = $field->getMatrixTypesInfo();
            foreach($typesInfo as $typeName => $typeInfo) {
                $typeData = [
                    'name' => $typeName,
                    'label' => $typeInfo['label'] ?: $typeName,
                    'head' => $typeInfo['head'] ?: '',
                    'sort' => (int)($typeInfo['sort'] ?? 0),
                    'fields' => [],
                ];
                foreach($typeInfo['fields'] as $matrixField) {
                    if(!($matrixField instanceof Field)) continue;
                    if($matrixField->name === 'repeater_matrix_type') continue;
                    $typeData['fields'][] = $this->buildFieldData($matrixField);
                }
                $types[] = $typeData;
            }
            usort($types, function($a, $b) { return $a['sort'] - $b['sort']; });
        } catch(\Exception $e) {
            $this->module->log("getMatrixTypesData({$field->name}) failed: " . $e->getMessage());
        }
        return $types;
    }

    public function buildFieldData(Field $field) {
        $data = [
            'name' => $field->name,
            'type' => $field->type->className(),
            'label' => $field->label,
            'required' => $field->required ? 1 : 0,
            'columnWidth' => $field->columnWidth ?: 100,
        ];
        if($field->description) $data['description'] = $field->description;
        if($field->notes) $data['notes'] = $field->notes;

        if($field->type instanceof FieldtypePage) {
            $data['derefAsPage'] = $field->derefAsPage;
            if($field->parent_id) {
                $parent = $this->module->pages->get($field->parent_id);
                if($parent && $parent->id) $data['parent'] = $parent->path;
            }
            if($field->template_id) {
                $tmpl = $this->module->templates->get($field->template_id);
                if($tmpl) $data['template'] = $tmpl->name;
            }
            if(!empty($field->template_ids) && is_array($field->template_ids)) {
                $data['templates'] = [];
                foreach($field->template_ids as $tid) {
                    $t = $this->module->templates->get((int)$tid);
                    if($t) $data['templates'][] = $t->name;
                }
            }
            if($field->findPagesSelector) $data['selector'] = $field->findPagesSelector;
            $data['inputfield'] = $field->inputfield;
        }

        if($field->type instanceof FieldtypeOptions) {
            $data['options'] = [];
            try {
                foreach($field->type->getOptions($field) as $opt) {
                    $data['options'][] = [
                        'id' => $opt->id,
                        'value' => $opt->value,
                        'title' => $opt->title,
                    ];
                }
            } catch(\Exception $e) {}
        }

        if($field->type instanceof FieldtypeImage || $field->type instanceof FieldtypeFile) {
            $data['maxFiles'] = $field->maxFiles;
            $data['extensions'] = $field->extensions;
            if($field->type instanceof FieldtypeImage) {
                if($field->maxWidth) $data['maxWidth'] = $field->maxWidth;
                if($field->maxHeight) $data['maxHeight'] = $field->maxHeight;
            }
        }

        $textTypes = ['FieldtypeText', 'FieldtypeTextarea'];
        if(in_array($field->type->className(), $textTypes)) {
            if($field->maxlength) $data['maxlength'] = $field->maxlength;
            if($field->type->className() === 'FieldtypeTextarea') {
                $data['rows'] = $field->rows;
                $data['contentType'] = $field->contentType;
            }
        }

        if($field->type->className() === 'FieldtypeTable') {
            $data['columns'] = $this->getTableColumns($field);
        }

        if($field->type->className() === 'FieldtypeCombo') {
            $data['subfields'] = $this->getComboSubfields($field);
        }

        if($field->type->className() === 'FieldtypeRepeater') {
            $data['repeaterFields'] = [];
            $repTemplate = $this->module->templates->get("repeater_" . $field->name);
            if($repTemplate) {
                foreach($repTemplate->fields as $repField) {
                    $data['repeaterFields'][] = [
                        'name' => $repField->name,
                        'type' => $repField->type->className(),
                        'label' => $repField->label,
                    ];
                }
            }
        }

        if($field->type->className() === 'FieldtypeRepeaterMatrix') {
            $data['matrix_types'] = $this->getMatrixTypesData($field);
        }

        return $data;
    }

    public function exportTemplates() {
        $templates = [];

        foreach($this->module->templates as $template) {
            if($template->flags & Template::flagSystem) continue;

            $fields = [];
            foreach($template->fields as $field) {
                $fieldData = [
                    'name' => $field->name,
                    'type' => $field->type->className(),
                    'label' => $field->label,
                    'required' => $field->required ? 1 : null,
                    'collapsed' => $field->collapsed !== Inputfield::collapsedNo ? $field->collapsed : null,
                ];

                if($field->type instanceof FieldtypeImage) {
                    $fieldData['maxFiles'] = $field->maxFiles;
                    $fieldData['extensions'] = $field->extensions;
                    if($field->maxWidth) $fieldData['maxWidth'] = $field->maxWidth;
                    if($field->maxHeight) $fieldData['maxHeight'] = $field->maxHeight;
                }

                if($field->type instanceof FieldtypePage) {
                    $fieldData['inputfield'] = $field->inputfield;
                    if($field->parent_id) $fieldData['parent_id'] = $field->parent_id;
                    if($field->template_id) $fieldData['template_id'] = $field->template_id;
                    if($field->findPagesSelector) $fieldData['selector'] = $field->findPagesSelector;
                    $fieldData['derefAsPage'] = $field->derefAsPage;
                }

                if($field->type instanceof FieldtypeOptions) {
                    $options = [];
                    foreach($field->type->getOptions($field) as $option) {
                        $options[] = $option->title;
                    }
                    $fieldData['options'] = $options;
                }

                if($field->type->className() === 'FieldtypeRepeater') {
                    $fieldData['repeaterFields'] = [];
                    $repeaterTemplate = $this->module->templates->get("repeater_" . $field->name);
                    if($repeaterTemplate) {
                        foreach($repeaterTemplate->fields as $repField) {
                            $fieldData['repeaterFields'][] = [
                                'name' => $repField->name,
                                'type' => $repField->type->className(),
                                'label' => $repField->label,
                            ];
                        }
                    }
                }

                if($field->type->className() === 'FieldtypeTable') {
                    $fieldData['columns'] = $this->getTableColumns($field);
                }

                if($field->type->className() === 'FieldtypeCombo') {
                    $fieldData['subfields'] = $this->getComboSubfields($field);
                }

                if($field->type->className() === 'FieldtypeRepeaterMatrix') {
                    $fieldData['matrix_types'] = $this->getMatrixTypesData($field);
                }

                if($field->notes) $fieldData['notes'] = $field->notes;
                if($field->description) $fieldData['description'] = $field->description;

                $fields[] = $fieldData;
            }

            $templates[] = [
                'name' => $template->name,
                'id' => $template->id,
                'label' => $template->label,
                'fields' => $fields,
                'fieldCount' => count($fields),
                'pageCount' => $this->module->pages->count("template={$template->name}"),
                'allowPageNum' => $template->allowPageNum,
                'urlSegments' => $template->urlSegments
            ];
        }

        try {
            $aiPath = $this->invoke('getContextPath');
            $this->invoke('writeJsonFile', $aiPath . 'templates.json', $templates);

            if($this->module->export_toon_format) {
                $this->invoke('writeToonFile', $aiPath . 'templates.toon', ['templates' => $templates]);
            }
        } catch(\Exception $e) {
            throw new WireException("Failed to export templates: " . $e->getMessage());
        }

        return $templates;
    }

    public function exportMatrixTemplates() {
        $output = [];

        foreach($this->module->fields as $field) {
            if($field->type->className() !== 'FieldtypeRepeaterMatrix') continue;

            $types = $this->getMatrixTypesData($field);
            if(empty($types)) continue;

            $output[] = [
                'field' => $field->name,
                'field_label' => $field->label ?: $field->name,
                'types' => $types,
            ];
        }

        if(empty($output)) {
            $this->module->log("No RepeaterMatrix fields found — skipping matrix-templates export.");
            return [];
        }

        $aiPath = $this->invoke('getContextPath');
        $data = ['matrix_templates' => $output];

        $this->invoke('writeJsonFile', $aiPath . 'matrix-templates.json', $data);
        $this->module->log("Created matrix-templates.json (" . count($output) . " fields)");

        if($this->module->export_toon_format) {
            $this->invoke('writeToonFile', $aiPath . 'matrix-templates.toon', $data);
            $this->module->log("Created matrix-templates.toon");
        }

        return $output;
    }

    public function exportTemplatesToCSV() {
        $csv = "Template Name,Template Label,Template ID,Field Name,Field Label,Field Type,Required,Collapsed,Notes,Description\n";

        foreach($this->module->templates as $template) {
            if($template->flags & Template::flagSystem) continue;

            foreach($template->fields as $field) {
                $row = [
                    $template->name,
                    $template->label ?: $template->name,
                    $template->id,
                    $field->name,
                    $field->label ?: $field->name,
                    $field->type->className(),
                    $field->required ? 'Yes' : 'No',
                    $field->collapsed !== Inputfield::collapsedNo ? 'Yes' : 'No',
                    str_replace(['"', "\n", "\r"], ['""', ' ', ''], $field->notes ?: ''),
                    str_replace(['"', "\n", "\r"], ['""', ' ', ''], $field->description ?: '')
                ];

                $csv .= '"' . implode('","', $row) . '"' . "\n";
            }
        }

        try {
            $aiPath = $this->invoke('getContextPath');
            $this->invoke('writeFile', $aiPath . 'templates.csv', $csv);
        } catch(\Exception $e) {
            throw new WireException("Failed to export templates CSV: " . $e->getMessage());
        }

        return $csv;
    }

    protected function invoke($method, ...$args) {
        return ($this->call)($method, ...$args);
    }
}
