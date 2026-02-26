<?php namespace ProcessWire;

/**
 * Context - ProcessWire AI Context Exporter
 * 
 * Full-featured module for exporting ProcessWire site structure
 * in a format optimized for working with AI assistants.
 * 
 * Creates complete documentation: structure, templates, content samples,
 * API schemas, code snippets, URL mapping and ready-to-use AI prompts.
 * 
 * Supports JSON and TOON (Token-Oriented Object Notation) formats.
 * TOON format reduces token consumption by 30-60% for AI prompts.
 */

class Context extends Process implements Module, ConfigurableModule {

    public static function getModuleInfo() {
        return [
            'title' => 'Context', 
            'version' => '1.1.4', 
            'summary' => 'Export ProcessWire site context for AI development (JSON + TOON formats)',
            'author' => 'Maxim Alex',
            'icon' => 'code',
            'permission' => 'page-edit',
            'page' => [
                'name' => 'context',
                'parent' => 'setup',
                'title' => 'Context'
            ],
            'requires' => 'ProcessWire>=3.0',
            'autoload' => false,
            'singular' => true
        ];
    }

    // Default module settings
    protected static $configDefaults = [
        'samples_count' => 3,
        'max_depth' => 10,
        'json_child_limit' => 20,
        'export_samples' => 1,
        'export_api_docs' => 1,
        'export_routes' => 1,
        'export_snippets' => 1,
        'export_prompts' => 1,
        'export_field_definitions' => 1,
        'export_performance' => 1,
        'export_integrations' => 0,
        'export_toon_format' => 1,
        'compact_mode' => 0,
        'auto_update' => 0,
        'site_type' => 'generic',
        'custom_ai_instructions' => ''
    ];

    /**
     * Module initialization
     */
    public function init() {
        parent::init();
        
        // Auto-update if enabled
        if($this->auto_update) {
            $this->addHookAfter('Template::saved', $this, 'autoUpdate');
            $this->addHookAfter('Field::saved', $this, 'autoUpdate');
        }
    }

    /**
     * Auto-update on changes
     */
    public function autoUpdate($event) {
        try {
            $contextPath = $this->ensureFolder($this->getContextPath());
            
            // Update core files
            $this->exportTemplates();
            
            // Update structure
            $structure = $this->buildPageTree($this->pages->get('/'), 0, $this->max_depth);
            file_put_contents($contextPath . 'structure.json', json_encode($structure, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            
            $asciiTree = $this->buildAsciiTree($this->pages->get('/'), 0, '', true, $this->max_depth);
            file_put_contents($contextPath . 'structure.txt', $asciiTree);
            
            // Update config
            $config = $this->exportConfig();
            file_put_contents($contextPath . 'config.json', json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            
            // TOON format (if enabled)
            if($this->export_toon_format) {
                file_put_contents($contextPath . 'structure.toon', $this->convertToToon($structure));
                file_put_contents($contextPath . 'config.toon', $this->convertToToon($config));
            }
            
            $this->log('Context auto-updated: templates, structure, config' . ($this->export_toon_format ? ' (JSON + TOON)' : ''));
        } catch(\Exception $e) {
            $this->log('Context auto-update failed: ' . $e->getMessage());
        }
    }

    protected function getContextPath() {
        return $this->config->paths->assets . 'context/';
    }

    protected function ensureFolder($path) {
        if(!is_dir($path)) {
            if(!wireMkdir($path, true)) {
                throw new WireException("Cannot create folder: $path");
            }
        }
        return $path;
    }

    /**
     * Build page tree (JSON)
     */
    protected function buildPageTree(Page $page, $depth = 0, $maxDepth = 10) {
        if($depth > $maxDepth) return null;
        
        // Skip system templates
        if($page->template && ($page->template->flags & Template::flagSystem)) {
            return null;
        }

        // Get all children including hidden/unpublished
        $children = $page->children("include=all");
        $numChildren = $children->count();

        $data = [
            'id' => $page->id,
            'name' => $page->name,
            'title' => $page->title,
            'template' => $page->template->name,
            'template_id' => $page->template->id,
            'template_label' => $page->template->label ?: $page->template->name,
            'url' => $page->url,
            'parent_id' => $page->parent->id,
            'created' => date('Y-m-d H:i:s', $page->created),
            'modified' => date('Y-m-d H:i:s', $page->modified),
            'status' => $page->status,
            'numChildren' => $numChildren
        ];

        if($numChildren > 0) {
            // Limit children export to keep JSON file manageable
            $childLimit = $this->json_child_limit ?: 20;
            
            // Smart collapse: show first N + all items with children
            if($numChildren > $childLimit && $depth >= 1) {
                // Separate children into two groups
                $regularItems = [];
                $nestedItems = [];
                
                foreach($children as $child) {
                    if($child->template && ($child->template->flags & Template::flagSystem)) {
                        continue;
                    }
                    
                    $childChildren = $child->children("include=all")->count();
                    if($childChildren > 0) {
                        $nestedItems[] = $child;
                    } else {
                        $regularItems[] = $child;
                    }
                }
                
                // Check if all regular items have same template
                $templates = [];
                foreach(array_slice($regularItems, 0, 5) as $item) {
                    $templates[$item->template->name] = true;
                }
                $allSameTemplate = count($templates) === 1;
                
                $data['children'] = [];
                
                // Show first N regular items
                $shownCount = 0;
                foreach($regularItems as $item) {
                    if($shownCount >= $childLimit) break;
                    $childData = $this->buildPageTree($item, $depth + 1, $maxDepth);
                    if($childData) {
                        $data['children'][] = $childData;
                        $shownCount++;
                    }
                }
                
                // Always show ALL items with children (nested structure)
                foreach($nestedItems as $item) {
                    $childData = $this->buildPageTree($item, $depth + 1, $maxDepth);
                    if($childData) {
                        $data['children'][] = $childData;
                    }
                }
                
                // Add note about hidden items
                $hiddenCount = count($regularItems) - $shownCount;
                if($hiddenCount > 0) {
                    $childTemplate = $allSameTemplate ? array_key_first($templates) : 'items';
                    $data['children_note'] = "Showing first {$childLimit} regular items + " . count($nestedItems) . " nested items. {$hiddenCount} more {$childTemplate} hidden (total: {$numChildren})";
                } elseif(count($nestedItems) > 0) {
                    $data['children_note'] = "Showing all " . count($regularItems) . " regular items + " . count($nestedItems) . " nested items";
                }
            } else {
                // Normal export for small lists
                $data['children'] = [];
                foreach($children as $child) {
                    $childData = $this->buildPageTree($child, $depth + 1, $maxDepth);
                    if($childData) $data['children'][] = $childData;
                }
            }
        }

        return $data;
    }

    /**
     * Build ASCII tree with smart collapsing for large homogeneous lists
     */
    protected function buildAsciiTree(Page $page, $depth = 0, $prefix = '', $isLast = true, $maxDepth = 10) {
        if($depth > $maxDepth) return '';
        
        // Skip system templates (like admin pages), but process the page
        if($page->template && ($page->template->flags & Template::flagSystem)) {
            return '';
        }

        $output = '';
        
        if($depth > 0) {
            $connector = $isLast ? '└─ ' : '├─ ';
            $output .= $prefix . $connector;
        }

        // Get children count including hidden/unpublished
        $children = $page->children("include=all");
        $childCount = $children->count();
        
        $itemCount = $childCount > 0 ? " (items: {$childCount})" : '';
        $output .= "{$page->title} [template: {$page->template->name}]{$itemCount}\n";

        if($childCount > 0) {
            
            // Smart collapsing configuration
            $threshold = $this->compact_mode ? 30 : 50; // Show up to 50 items (30 in compact mode)
            $alwaysExpandTemplates = ['category']; // Templates that should never collapse
            
            $newPrefix = $prefix;
            if($depth > 0) {
                $newPrefix .= $isLast ? '    ' : '│   ';
            }
            
            // Check if all children have the same template
            $templates = [];
            $anyChildHasChildren = false;
            foreach($children as $child) {
                // Skip system templates
                if($child->template && ($child->template->flags & Template::flagSystem)) {
                    continue;
                }
                
                $templates[$child->template->name] = true;
                // Check if this child has children (including hidden)
                if($child->children("include=all")->count() > 0) {
                    $anyChildHasChildren = true;
                }
            }
            
            $allSameTemplate = count($templates) === 1;
            $childTemplate = $allSameTemplate ? array_key_first($templates) : '';
            $shouldAlwaysExpand = in_array($childTemplate, $alwaysExpandTemplates);
            
            // Collapse if: depth >= 1 AND count > threshold AND all same template AND not always-expand
            // Smart handling: show first N + all items with children
            if($depth >= 1 && $allSameTemplate && !$shouldAlwaysExpand && 
               $childCount > $threshold) {
                
                // First pass: show items up to threshold OR items with children
                $count = 0;
                $itemsWithChildren = [];
                $regularItems = [];
                
                foreach($children as $index => $child) {
                    if($child->template && ($child->template->flags & Template::flagSystem)) {
                        continue;
                    }
                    
                    if($child->children("include=all")->count() > 0) {
                        $itemsWithChildren[] = ['index' => $index, 'page' => $child];
                    } else {
                        $regularItems[] = ['index' => $index, 'page' => $child];
                    }
                }
                
                // Show first N regular items
                $shownCount = 0;
                foreach($regularItems as $item) {
                    if($shownCount >= $threshold) break;
                    $output .= $this->buildAsciiTree($item['page'], $depth + 1, $newPrefix, false, $maxDepth);
                    $shownCount++;
                }
                
                // Always show ALL items with children (nested structure)
                foreach($itemsWithChildren as $item) {
                    $isLastChild = (count($itemsWithChildren) === 0 && count($regularItems) <= $threshold);
                    $output .= $this->buildAsciiTree($item['page'], $depth + 1, $newPrefix, false, $maxDepth);
                }
                
                // Add "and more X elements" if we hid some regular items
                $hiddenCount = count($regularItems) - $shownCount;
                if($hiddenCount > 0) {
                    $connector = '└─ ';
                    $output .= $newPrefix . $connector;
                    $output .= "and {$hiddenCount} more {$childTemplate} elements...\n";
                }
            } else {
                // Show all children normally (small lists or mixed templates)
                foreach($children as $index => $child) {
                    $isLastChild = ($index === $childCount - 1);
                    $output .= $this->buildAsciiTree($child, $depth + 1, $newPrefix, $isLastChild, $maxDepth);
                }
            }
        }

        return $output;
    }

    /**
     * Export all templates with fields
     */
    protected function exportTemplates() {
        $templates = [];

        foreach($this->templates as $template) {
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

                // Image fields
                if($field->type instanceof FieldtypeImage) {
                    $fieldData['maxFiles'] = $field->maxFiles;
                    $fieldData['extensions'] = $field->extensions;
                    if($field->maxWidth) $fieldData['maxWidth'] = $field->maxWidth;
                    if($field->maxHeight) $fieldData['maxHeight'] = $field->maxHeight;
                }

                // Page fields
                if($field->type instanceof FieldtypePage) {
                    $fieldData['inputfield'] = $field->inputfield;
                    if($field->parent_id) $fieldData['parent_id'] = $field->parent_id;
                    if($field->template_id) $fieldData['template_id'] = $field->template_id;
                    if($field->findPagesSelector) $fieldData['selector'] = $field->findPagesSelector;
                    $fieldData['derefAsPage'] = $field->derefAsPage;
                }

                // Options fields
                if($field->type instanceof FieldtypeOptions) {
                    $options = [];
                    foreach($field->type->getOptions($field) as $option) {
                        $options[] = $option->title;
                    }
                    $fieldData['options'] = $options;
                }

                // Table fields
                if($field->type->className() === 'FieldtypeTable') {
                    $columns = [];
                    if($field->columns) {
                        foreach($field->columns as $col) {
                            $colData = [
                                'name' => $col['name'],
                                'label' => $col['label'],
                                'type' => $col['type']
                            ];
                            if(isset($col['options'])) {
                                $colData['options'] = explode("\n", $col['options']);
                            }
                            if(isset($col['selector'])) {
                                $colData['selector'] = $col['selector'];
                            }
                            $columns[] = $colData;
                        }
                    }
                    $fieldData['columns'] = $columns;
                }

                // Repeater fields
                if($field->type->className() === 'FieldtypeRepeater') {
                    $fieldData['repeaterFields'] = [];
                    $repeaterTemplate = $this->templates->get("repeater_" . $field->name);
                    if($repeaterTemplate) {
                        foreach($repeaterTemplate->fields as $repField) {
                            $fieldData['repeaterFields'][] = [
                                'name' => $repField->name,
                                'type' => $repField->type->className(),
                                'label' => $repField->label
                            ];
                        }
                    }
                }

                // RepeaterMatrix fields
                if($field->type->className() === 'FieldtypeRepeaterMatrix') {
                    $fieldData['matrixTypes'] = [];
                    $matrixTypes = $field->type->getMatrixTypes($field);
                    foreach($matrixTypes as $matrixType) {
                        if(!isset($matrixType->name)) continue;
                        
                        $matrixTemplate = $this->wire('templates')->get($matrixType->name);
                        if($matrixTemplate && $matrixTemplate instanceof Template) {
                            $matrixFields = [];
                            foreach($matrixTemplate->getFields() as $matrixField) {
                                if($matrixField instanceof Field) {
                                    // Полная информация о поле как для обычных полей
                                    $matrixFieldData = [
                                        'name' => $matrixField->name,
                                        'type' => $matrixField->type->className(),
                                        'label' => $matrixField->label,
                                        'description' => $matrixField->description,
                                        'notes' => $matrixField->notes,
                                        'required' => $matrixField->required ? 1 : 0,
                                        'columnWidth' => $matrixField->columnWidth
                                    ];
                                    
                                    // Опции для Page reference полей
                                    if($matrixField->type->className() === 'FieldtypePage') {
                                        $matrixFieldData['derefAsPage'] = $matrixField->derefAsPage;
                                        if($matrixField->parent_id) {
                                            $parent = $this->pages->get($matrixField->parent_id);
                                            $matrixFieldData['parent'] = $parent ? $parent->path : '';
                                        }
                                        if($matrixField->template_id) {
                                            $template = $this->templates->get($matrixField->template_id);
                                            $matrixFieldData['template'] = $template ? $template->name : '';
                                        }
                                        if($matrixField->template_ids && is_array($matrixField->template_ids)) {
                                            $matrixFieldData['templates'] = [];
                                            foreach($matrixField->template_ids as $tid) {
                                                $t = $this->templates->get($tid);
                                                if($t) $matrixFieldData['templates'][] = $t->name;
                                            }
                                        }
                                    }
                                    
                                    // Опции для Options полей
                                    if($matrixField->type->className() === 'FieldtypeOptions') {
                                        $matrixFieldData['options'] = [];
                                        if($matrixField->type) {
                                            $manager = $this->wire(new \SelectableOptionManager());
                                            foreach($manager->setField($matrixField) as $option) {
                                                $matrixFieldData['options'][] = [
                                                    'id' => $option->id,
                                                    'value' => $option->value,
                                                    'title' => $option->title
                                                ];
                                            }
                                        }
                                    }
                                    
                                    // Опции для Image/File полей
                                    if(in_array($matrixField->type->className(), ['FieldtypeImage', 'FieldtypeFile'])) {
                                        $matrixFieldData['maxFiles'] = $matrixField->maxFiles;
                                        $matrixFieldData['extensions'] = $matrixField->extensions;
                                        if($matrixField->type->className() === 'FieldtypeImage') {
                                            $matrixFieldData['maxWidth'] = $matrixField->maxWidth;
                                            $matrixFieldData['maxHeight'] = $matrixField->maxHeight;
                                        }
                                    }
                                    
                                    // Опции для текстовых полей
                                    if(in_array($matrixField->type->className(), ['FieldtypeText', 'FieldtypeTextarea'])) {
                                        $matrixFieldData['maxlength'] = $matrixField->maxlength;
                                        if($matrixField->type->className() === 'FieldtypeTextarea') {
                                            $matrixFieldData['rows'] = $matrixField->rows;
                                            $matrixFieldData['contentType'] = $matrixField->contentType;
                                        }
                                    }
                                    
                                    $matrixFields[] = $matrixFieldData;
                                }
                            }
                            $fieldData['matrixTypes'][] = [
                                'name' => $matrixType->name,
                                'label' => $matrixType->label,
                                'head' => isset($matrixType->head) ? $matrixType->head : '',
                                'sort' => isset($matrixType->sort) ? $matrixType->sort : 0,
                                'template' => $matrixTemplate->name,
                                'fields' => $matrixFields
                            ];
                        }
                    }
                }

                // Combo fields
                if($field->type->className() === 'FieldtypeCombo') {
                    $fieldData['comboFields'] = [];
                    if($field->subfields) {
                        foreach($field->subfields as $subfield) {
                            $fieldData['comboFields'][] = [
                                'name' => $subfield->name,
                                'type' => $subfield->type,
                                'label' => $subfield->label
                            ];
                        }
                    }
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
                'pageCount' => $this->pages->count("template={$template->name}"),
                'allowPageNum' => $template->allowPageNum,
                'urlSegments' => $template->urlSegments
            ];
        }

        try {
            $aiPath = $this->getContextPath();
            file_put_contents($aiPath . 'templates.json', json_encode($templates, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            
            // TOON format (if enabled)
            if($this->export_toon_format) {
                file_put_contents($aiPath . 'templates.toon', $this->convertToToon(['templates' => $templates]));
            }
        } catch(\Exception $e) {
            throw new WireException("Failed to export templates: " . $e->getMessage());
        }

        return $templates;
    }

    /**
     * Export Repeater Matrix templates separately
     */
    protected function exportMatrixTemplates() {
        $matrixTemplates = [];
        
        $this->log("Starting Matrix templates export...");
        
        // Find all Matrix fields first
        $matrixFields = [];
        foreach($this->fields as $field) {
            if($field->type->className() === 'FieldtypeRepeaterMatrix') {
                $matrixFields[$field->name] = $field;
            }
        }
        
        if(empty($matrixFields)) {
            $this->log("No Matrix fields found on this site");
            return [];
        }
        
        $this->log("Found " . count($matrixFields) . " Matrix fields: " . implode(', ', array_keys($matrixFields)));
        
        // Find all templates that could be Matrix types
        // Matrix templates can have these patterns:
        // 1. repeater_FIELDNAME (older style)
        // 2. repeater_matrix_FIELDNAME_INDEX (newer style)
        // 3. repeatermatrix_FIELDNAME_INDEX (alternative)
        $allTemplates = $this->templates->getAll();
        $potentialMatrixTemplates = [];
        
        foreach($allTemplates as $template) {
            $name = $template->name;
            
            // Check if this template belongs to any Matrix field
            foreach($matrixFields as $fieldName => $matrixField) {
                $matched = false;
                
                // Pattern 1: repeater_FIELDNAME (exact match for parent repeater)
                if($name === "repeater_{$fieldName}") {
                    $matched = true;
                }
                // Pattern 2: repeater_matrix_FIELDNAME_*
                elseif(strpos($name, "repeater_matrix_{$fieldName}") === 0) {
                    $matched = true;
                }
                // Pattern 3: repeatermatrix_FIELDNAME_*
                elseif(strpos($name, "repeatermatrix_{$fieldName}") === 0) {
                    $matched = true;
                }
                // Pattern 4: Check if this template is listed in matrix types
                else {
                    try {
                        $matrixTypes = $matrixField->type->getMatrixTypes($matrixField);
                        foreach($matrixTypes as $mt) {
                            if($mt->name === $name) {
                                $matched = true;
                                break;
                            }
                        }
                    } catch(\Exception $e) {}
                }
                
                if($matched) {
                    $potentialMatrixTemplates[] = [
                        'template' => $template,
                        'field' => $matrixField
                    ];
                    break; // Found parent field, no need to check others
                }
            }
        }
        
        if(empty($potentialMatrixTemplates)) {
            $this->log("No Matrix templates found (no templates matching Matrix field patterns)");
            return [];
        }
        
        $this->log("Found " . count($potentialMatrixTemplates) . " potential Matrix templates");
        
        // Process each Matrix template
        foreach($potentialMatrixTemplates as $item) {
            $matrixTemplate = $item['template'];
            $parentField = $item['field'];
            
            $this->log("Processing Matrix template: {$matrixTemplate->name} (field: {$parentField->name})");
            
            // Get matrix type label
            $typeLabel = $matrixTemplate->label ?: $matrixTemplate->name;
            
            // Try to get proper label from matrix types
            try {
                $matrixTypes = $parentField->type->getMatrixTypes($parentField);
                foreach($matrixTypes as $mt) {
                    if($mt->name === $matrixTemplate->name) {
                        $typeLabel = $mt->label;
                        break;
                    }
                }
            } catch(\Exception $e) {
                $this->log("  Could not get matrix types for field {$parentField->name}: " . $e->getMessage());
            }
            
            $matrixData = [
                'name' => $matrixTemplate->name,
                'label' => $typeLabel,
                'parent_field' => $parentField->name,
                'parent_field_label' => $parentField->label,
                'fields' => []
            ];
            
            // Export all fields from this matrix type
            foreach($matrixTemplate->fields as $matrixField) {
                if(!($matrixField instanceof Field)) continue;
                
                // Skip system fields
                if($matrixField->name === 'repeater_matrix_type') continue;
                
                $fieldData = [
                    'name' => $matrixField->name,
                    'type' => $matrixField->type->className(),
                    'label' => $matrixField->label,
                    'description' => $matrixField->description,
                    'notes' => $matrixField->notes,
                    'required' => $matrixField->required ? 1 : 0,
                    'columnWidth' => $matrixField->columnWidth,
                    'collapsed' => $matrixField->collapsed !== Inputfield::collapsedNo ? $matrixField->collapsed : null,
                ];
                
                // Page reference fields
                if($matrixField->type instanceof FieldtypePage) {
                    $fieldData['inputfield'] = $matrixField->inputfield;
                    $fieldData['derefAsPage'] = $matrixField->derefAsPage;
                    
                    if($matrixField->parent_id) {
                        $parent = $this->pages->get($matrixField->parent_id);
                        $fieldData['parent'] = $parent ? $parent->path : '';
                        $fieldData['parent_id'] = $matrixField->parent_id;
                    }
                    
                    if($matrixField->template_id) {
                        $template = $this->templates->get($matrixField->template_id);
                        $fieldData['template'] = $template ? $template->name : '';
                        $fieldData['template_id'] = $matrixField->template_id;
                    }
                    
                    if($matrixField->template_ids && is_array($matrixField->template_ids)) {
                        $fieldData['template_ids'] = $matrixField->template_ids;
                        $fieldData['templates'] = [];
                        foreach($matrixField->template_ids as $tid) {
                            $t = $this->templates->get($tid);
                            if($t) $fieldData['templates'][] = $t->name;
                        }
                    }
                    
                    if($matrixField->findPagesSelector) {
                        $fieldData['selector'] = $matrixField->findPagesSelector;
                    }
                }
                
                // Options fields
                if($matrixField->type instanceof FieldtypeOptions) {
                    $fieldData['options'] = [];
                    try {
                        foreach($matrixField->type->getOptions($matrixField) as $option) {
                            $fieldData['options'][] = [
                                'id' => $option->id,
                                'value' => $option->value,
                                'title' => $option->title
                            ];
                        }
                    } catch(\Exception $e) {}
                }
                
                // Image/File fields
                if($matrixField->type instanceof FieldtypeImage || $matrixField->type instanceof FieldtypeFile) {
                    $fieldData['maxFiles'] = $matrixField->maxFiles;
                    $fieldData['extensions'] = $matrixField->extensions;
                    
                    if($matrixField->type instanceof FieldtypeImage) {
                        if($matrixField->maxWidth) $fieldData['maxWidth'] = $matrixField->maxWidth;
                        if($matrixField->maxHeight) $fieldData['maxHeight'] = $matrixField->maxHeight;
                    }
                }
                
                // Text fields
                if(in_array($matrixField->type->className(), ['FieldtypeText', 'FieldtypeTextarea'])) {
                    if($matrixField->maxlength) $fieldData['maxlength'] = $matrixField->maxlength;
                    
                    if($matrixField->type->className() === 'FieldtypeTextarea') {
                        $fieldData['rows'] = $matrixField->rows;
                        $fieldData['contentType'] = $matrixField->contentType;
                    }
                }
                
                // Table fields
                if($matrixField->type->className() === 'FieldtypeTable') {
                    $fieldData['columns'] = [];
                    
                    // Table columns stored in field settings
                    if(isset($matrixField->data) && is_array($matrixField->data)) {
                        $maxCols = isset($matrixField->data['maxCols']) ? (int)$matrixField->data['maxCols'] : 0;
                        
                        for($i = 1; $i <= $maxCols; $i++) {
                            $colName = isset($matrixField->data["col{$i}name"]) ? $matrixField->data["col{$i}name"] : null;
                            $colLabel = isset($matrixField->data["col{$i}label"]) ? $matrixField->data["col{$i}label"] : null;
                            $colType = isset($matrixField->data["col{$i}type"]) ? $matrixField->data["col{$i}type"] : null;
                            
                            if($colName) {
                                $column = [
                                    'name' => $colName,
                                    'label' => $colLabel ?: $colName,
                                    'type' => $colType ?: 'text'
                                ];
                                
                                // Add column options if they exist
                                if(isset($matrixField->data["col{$i}options"]) && !empty($matrixField->data["col{$i}options"])) {
                                    $options = $matrixField->data["col{$i}options"];
                                    if(is_string($options)) {
                                        // Parse options string
                                        $column['options'] = [];
                                        $lines = explode("\n", $options);
                                        foreach($lines as $line) {
                                            $line = trim($line);
                                            if(empty($line)) continue;
                                            
                                            if(strpos($line, '=') !== false) {
                                                list($key, $value) = explode('=', $line, 2);
                                                $column['options'][trim($key)] = trim($value);
                                            } else {
                                                $column['options'][] = $line;
                                            }
                                        }
                                    } elseif(is_array($options)) {
                                        $column['options'] = $options;
                                    }
                                }
                                
                                // Add selector for page fields
                                if(isset($matrixField->data["col{$i}selector"]) && !empty($matrixField->data["col{$i}selector"])) {
                                    $column['selector'] = $matrixField->data["col{$i}selector"];
                                }
                                
                                $fieldData['columns'][] = $column;
                            }
                        }
                    }
                }
                
                // Combo fields (ProFields)
                if($matrixField->type->className() === 'FieldtypeCombo') {
                    $fieldData['subfields'] = [];
                    
                    if(isset($matrixField->data) && is_array($matrixField->data)) {
                        $qty = isset($matrixField->data['qty']) ? (int)$matrixField->data['qty'] : 0;
                        
                        // Check if there's an order specified
                        $order = [];
                        if(isset($matrixField->data['order']) && !empty($matrixField->data['order'])) {
                            $order = explode(',', $matrixField->data['order']);
                        }
                        
                        if(!empty($order)) {
                            // Use order array - it contains indices like ['1', '2', '3']
                            foreach($order as $index) {
                                $index = trim($index);
                                if(empty($index)) continue;
                                
                                $nameKey = "i{$index}_name";
                                $labelKey = "i{$index}_label";
                                $typeKey = "i{$index}_type";
                                
                                if(isset($matrixField->data[$nameKey]) && isset($matrixField->data[$typeKey])) {
                                    $subfield = [
                                        'name' => $matrixField->data[$nameKey],
                                        'label' => isset($matrixField->data[$labelKey]) ? $matrixField->data[$labelKey] : $matrixField->data[$nameKey],
                                        'type' => $matrixField->data[$typeKey]
                                    ];
                                    
                                    // Add notes and description
                                    $notesKey = "i{$index}_notes";
                                    $descriptionKey = "i{$index}_description";
                                    
                                    if(isset($matrixField->data[$notesKey]) && !empty($matrixField->data[$notesKey])) {
                                        $subfield['notes'] = $matrixField->data[$notesKey];
                                    }
                                    if(isset($matrixField->data[$descriptionKey]) && !empty($matrixField->data[$descriptionKey])) {
                                        $subfield['description'] = $matrixField->data[$descriptionKey];
                                    }
                                    
                                    // Add options if available
                                    $optionsKey = "i{$index}_options";
                                    if(isset($matrixField->data[$optionsKey]) && !empty($matrixField->data[$optionsKey])) {
                                        $options = $matrixField->data[$optionsKey];
                                        if(is_string($options)) {
                                            $subfield['options'] = [];
                                            $lines = explode("\n", $options);
                                            foreach($lines as $line) {
                                                $line = trim($line);
                                                if(empty($line)) continue;
                                                
                                                if(strpos($line, '=') !== false) {
                                                    list($key, $value) = explode('=', $line, 2);
                                                    $subfield['options'][trim($key)] = trim($value);
                                                } else {
                                                    $subfield['options'][] = $line;
                                                }
                                            }
                                        } elseif(is_array($options)) {
                                            $subfield['options'] = $options;
                                        }
                                    }
                                    
                                    $fieldData['subfields'][] = $subfield;
                                }
                            }
                        } else {
                            // No order specified, iterate by index
                            for($i = 1; $i <= $qty; $i++) {
                                $nameKey = "i{$i}_name";
                                $labelKey = "i{$i}_label";
                                $typeKey = "i{$i}_type";
                                
                                if(isset($matrixField->data[$nameKey]) && isset($matrixField->data[$typeKey])) {
                                    $subfield = [
                                        'name' => $matrixField->data[$nameKey],
                                        'label' => isset($matrixField->data[$labelKey]) ? $matrixField->data[$labelKey] : $matrixField->data[$nameKey],
                                        'type' => $matrixField->data[$typeKey]
                                    ];
                                    
                                    // Add notes and description
                                    $notesKey = "i{$i}_notes";
                                    $descriptionKey = "i{$i}_description";
                                    
                                    if(isset($matrixField->data[$notesKey]) && !empty($matrixField->data[$notesKey])) {
                                        $subfield['notes'] = $matrixField->data[$notesKey];
                                    }
                                    if(isset($matrixField->data[$descriptionKey]) && !empty($matrixField->data[$descriptionKey])) {
                                        $subfield['description'] = $matrixField->data[$descriptionKey];
                                    }
                                    
                                    // Add options if available
                                    $optionsKey = "i{$i}_options";
                                    if(isset($matrixField->data[$optionsKey]) && !empty($matrixField->data[$optionsKey])) {
                                        $options = $matrixField->data[$optionsKey];
                                        if(is_string($options)) {
                                            $subfield['options'] = [];
                                            $lines = explode("\n", $options);
                                            foreach($lines as $line) {
                                                $line = trim($line);
                                                if(empty($line)) continue;
                                                
                                                if(strpos($line, '=') !== false) {
                                                    list($key, $value) = explode('=', $line, 2);
                                                    $subfield['options'][trim($key)] = trim($value);
                                                } else {
                                                    $subfield['options'][] = $line;
                                                }
                                            }
                                        } elseif(is_array($options)) {
                                            $subfield['options'] = $options;
                                        }
                                    }
                                    
                                    $fieldData['subfields'][] = $subfield;
                                }
                            }
                        }
                    }
                }
                
                $matrixData['fields'][] = $fieldData;
            }
            
            $matrixTemplates[] = $matrixData;
            $this->log("  Added matrix type: {$matrixTemplate->name} with " . count($matrixData['fields']) . " fields");
        }
        
        // Export if there are any matrix templates
        if(!empty($matrixTemplates)) {
            $aiPath = $this->getContextPath();
            
            $this->log("Exporting " . count($matrixTemplates) . " Matrix templates");
            
            // JSON format
            file_put_contents(
                $aiPath . 'matrix-templates.json', 
                json_encode(['matrix_templates' => $matrixTemplates], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            );
            
            $this->log("Created matrix-templates.json");
            
            // TOON format (if enabled)
            if($this->export_toon_format) {
                file_put_contents(
                    $aiPath . 'matrix-templates.toon', 
                    $this->convertToToon(['matrix_templates' => $matrixTemplates])
                );
                $this->log("Created matrix-templates.toon");
            }
        } else {
            $this->log("No Matrix templates found to export");
        }
        
        return $matrixTemplates;
    }

    /**
     * Export complete site tree (templates with nested fields structure)
     * Technical overview without data - just the field architecture
     */
    protected function exportTree() {
        $tree = [];
        
        $this->log("Building site tree (templates + fields)...");
        
        // Get all templates with their fields
        foreach($this->templates as $template) {
            if($template->flags & Template::flagSystem) continue;
            
            $templateData = [
                'name' => $template->name,
                'label' => $template->label ?: $template->name,
                'fields' => []
            ];
            
            // Add all fields
            foreach($template->fields as $field) {
                $fieldData = [
                    'name' => $field->name,
                    'type' => $field->type->className(),
                    'label' => $field->label
                ];
                
                // Add subfield info for special types
                if($field->type instanceof FieldtypePage) {
                    if($field->template_id) {
                        $refTemplate = $this->templates->get($field->template_id);
                        if($refTemplate) {
                            $fieldData['template'] = $refTemplate->name;
                        }
                    }
                } elseif($field->type->className() === 'FieldtypeRepeater') {
                    // Get repeater template
                    $repeaterTemplate = $this->templates->get("name=repeater_{$field->name}");
                    if($repeaterTemplate) {
                        $fieldData['subfields'] = [];
                        foreach($repeaterTemplate->fields as $repField) {
                            $subFieldData = [
                                'name' => $repField->name,
                                'type' => $repField->type->className(),
                                'label' => $repField->label
                            ];
                            
                            // Check if repeater subfield is also a Page reference
                            if($repField->type instanceof FieldtypePage && $repField->template_id) {
                                $refTemplate = $this->templates->get($repField->template_id);
                                if($refTemplate) {
                                    $subFieldData['template'] = $refTemplate->name;
                                }
                            }
                            
                            $fieldData['subfields'][] = $subFieldData;
                        }
                    }
                } elseif($field->type->className() === 'FieldtypeRepeaterMatrix') {
                    $fieldData['matrix_types'] = [];
                    
                    // Find all matrix templates for this field
                    $allTemplates = $this->templates->getAll();
                    foreach($allTemplates as $t) {
                        if(strpos($t->name, "repeater_{$field->name}") === 0 || 
                           strpos($t->name, "repeater_matrix_{$field->name}") === 0 ||
                           strpos($t->name, "repeatermatrix_{$field->name}") === 0) {
                            
                            $matrixType = [
                                'name' => $t->name,
                                'label' => $t->label ?: $t->name,
                                'subfields' => []
                            ];
                            
                            foreach($t->fields as $matrixField) {
                                if($matrixField->name === 'repeater_matrix_type') continue;
                                
                                $subFieldData = [
                                    'name' => $matrixField->name,
                                    'type' => $matrixField->type->className(),
                                    'label' => $matrixField->label
                                ];
                                
                                // Page reference in matrix
                                if($matrixField->type instanceof FieldtypePage && $matrixField->template_id) {
                                    $refTemplate = $this->templates->get($matrixField->template_id);
                                    if($refTemplate) {
                                        $subFieldData['template'] = $refTemplate->name;
                                    }
                                }
                                // Table in matrix
                                elseif($matrixField->type->className() === 'FieldtypeTable') {
                                    $subFieldData['columns'] = [];
                                    if(isset($matrixField->data) && is_array($matrixField->data)) {
                                        $maxCols = isset($matrixField->data['maxCols']) ? (int)$matrixField->data['maxCols'] : 0;
                                        for($i = 1; $i <= $maxCols; $i++) {
                                            $colName = isset($matrixField->data["col{$i}name"]) ? $matrixField->data["col{$i}name"] : null;
                                            if($colName) {
                                                $subFieldData['columns'][] = [
                                                    'name' => $colName,
                                                    'type' => isset($matrixField->data["col{$i}type"]) ? $matrixField->data["col{$i}type"] : 'text'
                                                ];
                                            }
                                        }
                                    }
                                }
                                // Combo in matrix
                                elseif($matrixField->type->className() === 'FieldtypeCombo') {
                                    $subFieldData['subfields'] = [];
                                    if(isset($matrixField->data) && is_array($matrixField->data)) {
                                        $qty = isset($matrixField->data['qty']) ? (int)$matrixField->data['qty'] : 0;
                                        for($i = 1; $i <= $qty; $i++) {
                                            $nameKey = "i{$i}_name";
                                            $typeKey = "i{$i}_type";
                                            if(isset($matrixField->data[$nameKey]) && isset($matrixField->data[$typeKey])) {
                                                $subFieldData['subfields'][] = [
                                                    'name' => $matrixField->data[$nameKey],
                                                    'type' => $matrixField->data[$typeKey]
                                                ];
                                            }
                                        }
                                    }
                                }
                                // Nested Matrix (Matrix inside Matrix)
                                elseif($matrixField->type->className() === 'FieldtypeRepeaterMatrix') {
                                    $subFieldData['matrix_types'] = [];
                                    
                                    // Find all nested matrix templates for this field
                                    foreach($allTemplates as $nestedT) {
                                        if(strpos($nestedT->name, "repeater_{$matrixField->name}") === 0 || 
                                           strpos($nestedT->name, "repeater_matrix_{$matrixField->name}") === 0 ||
                                           strpos($nestedT->name, "repeatermatrix_{$matrixField->name}") === 0) {
                                            
                                            $nestedMatrixType = [
                                                'name' => $nestedT->name,
                                                'label' => $nestedT->label ?: $nestedT->name,
                                                'subfields' => []
                                            ];
                                            
                                            foreach($nestedT->fields as $nestedField) {
                                                if($nestedField->name === 'repeater_matrix_type') continue;
                                                
                                                $nestedSubFieldData = [
                                                    'name' => $nestedField->name,
                                                    'type' => $nestedField->type->className(),
                                                    'label' => $nestedField->label
                                                ];
                                                
                                                // Page reference in nested matrix
                                                if($nestedField->type instanceof FieldtypePage && $nestedField->template_id) {
                                                    $refTemplate = $this->templates->get($nestedField->template_id);
                                                    if($refTemplate) {
                                                        $nestedSubFieldData['template'] = $refTemplate->name;
                                                    }
                                                }
                                                // Table in nested matrix
                                                elseif($nestedField->type->className() === 'FieldtypeTable') {
                                                    $nestedSubFieldData['columns'] = [];
                                                    if(isset($nestedField->data) && is_array($nestedField->data)) {
                                                        $maxCols = isset($nestedField->data['maxCols']) ? (int)$nestedField->data['maxCols'] : 0;
                                                        for($i = 1; $i <= $maxCols; $i++) {
                                                            $colName = isset($nestedField->data["col{$i}name"]) ? $nestedField->data["col{$i}name"] : null;
                                                            if($colName) {
                                                                $nestedSubFieldData['columns'][] = [
                                                                    'name' => $colName,
                                                                    'type' => isset($nestedField->data["col{$i}type"]) ? $nestedField->data["col{$i}type"] : 'text'
                                                                ];
                                                            }
                                                        }
                                                    }
                                                }
                                                // Combo in nested matrix
                                                elseif($nestedField->type->className() === 'FieldtypeCombo') {
                                                    $nestedSubFieldData['subfields'] = [];
                                                    if(isset($nestedField->data) && is_array($nestedField->data)) {
                                                        $qty = isset($nestedField->data['qty']) ? (int)$nestedField->data['qty'] : 0;
                                                        for($i = 1; $i <= $qty; $i++) {
                                                            $nameKey = "i{$i}_name";
                                                            $typeKey = "i{$i}_type";
                                                            if(isset($nestedField->data[$nameKey]) && isset($nestedField->data[$typeKey])) {
                                                                $nestedSubFieldData['subfields'][] = [
                                                                    'name' => $nestedField->data[$nameKey],
                                                                    'type' => $nestedField->data[$typeKey]
                                                                ];
                                                            }
                                                        }
                                                    }
                                                }
                                                
                                                $nestedMatrixType['subfields'][] = $nestedSubFieldData;
                                            }
                                            
                                            $subFieldData['matrix_types'][] = $nestedMatrixType;
                                        }
                                    }
                                }
                                
                                $matrixType['subfields'][] = $subFieldData;
                            }
                            
                            $fieldData['matrix_types'][] = $matrixType;
                        }
                    }
                } elseif($field->type->className() === 'FieldtypeTable') {
                    $fieldData['columns'] = [];
                    if(isset($field->data) && is_array($field->data)) {
                        $maxCols = isset($field->data['maxCols']) ? (int)$field->data['maxCols'] : 0;
                        for($i = 1; $i <= $maxCols; $i++) {
                            $colName = isset($field->data["col{$i}name"]) ? $field->data["col{$i}name"] : null;
                            if($colName) {
                                $fieldData['columns'][] = [
                                    'name' => $colName,
                                    'type' => isset($field->data["col{$i}type"]) ? $field->data["col{$i}type"] : 'text'
                                ];
                            }
                        }
                    }
                } elseif($field->type->className() === 'FieldtypeCombo') {
                    $fieldData['subfields'] = [];
                    if(isset($field->data) && is_array($field->data)) {
                        $qty = isset($field->data['qty']) ? (int)$field->data['qty'] : 0;
                        for($i = 1; $i <= $qty; $i++) {
                            $nameKey = "i{$i}_name";
                            $typeKey = "i{$i}_type";
                            if(isset($field->data[$nameKey]) && isset($field->data[$typeKey])) {
                                $fieldData['subfields'][] = [
                                    'name' => $field->data[$nameKey],
                                    'type' => $field->data[$typeKey]
                                ];
                            }
                        }
                    }
                }
                
                $templateData['fields'][] = $fieldData;
            }
            
            $tree[] = $templateData;
        }
        
        $this->log("Site tree built with " . count($tree) . " templates");
        
        return $tree;
    }
    
    /**
     * Build tree structure recursively (template names only, no fields)
     * DEPRECATED - no longer used
     */
    protected function buildTreeStructure($page, $depth = 0) {
        return null;
    }

    /**
     * Export templates to CSV format
     */
    protected function exportTemplatesToCSV() {
        $csv = "Template Name,Template Label,Template ID,Field Name,Field Label,Field Type,Required,Collapsed,Notes,Description\n";
        
        foreach($this->templates as $template) {
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
            $aiPath = $this->getContextPath();
            file_put_contents($aiPath . 'templates.csv', $csv);
        } catch(\Exception $e) {
            throw new WireException("Failed to export templates CSV: " . $e->getMessage());
        }
        
        return $csv;
    }

    /**
     * Export content samples
     */
    protected function exportSamples() {
        $samplesPath = $this->ensureFolder($this->getContextPath() . 'samples/');
        $allSamples = [];

        foreach($this->templates as $template) {
            if($template->flags & Template::flagSystem) continue;
            
            $pages = $this->pages->find("template={$template->name}, limit={$this->samples_count}, sort=random");
            
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
                    $value = $page->get($field->name);
                    
                    if(empty($value) && $value !== '0' && $value !== 0) continue;

                    // Page fields
                    if($field->type instanceof FieldtypePage) {
                        if($value instanceof Page && $value->id) {
                            $pageData['fields'][$field->name] = [
                                'id' => $value->id,
                                'title' => $value->title,
                                'url' => $value->url
                            ];
                        } elseif($value instanceof PageArray) {
                            $pageData['fields'][$field->name] = [];
                            foreach($value as $p) {
                                $pageData['fields'][$field->name][] = [
                                    'id' => $p->id,
                                    'title' => $p->title,
                                    'url' => $p->url
                                ];
                            }
                        }
                    } 
                    // Image fields
                    elseif($field->type instanceof FieldtypeImage) {
                        $images = [];
                        foreach($value as $img) {
                            $images[] = [
                                'url' => $img->url,
                                'description' => $img->description,
                                'width' => $img->width,
                                'height' => $img->height,
                                'filesize' => $img->filesize
                            ];
                        }
                        $pageData['fields'][$field->name] = $images;
                    } 
                    // File fields
                    elseif($field->type instanceof FieldtypeFile) {
                        $files = [];
                        foreach($value as $file) {
                            $files[] = [
                                'url' => $file->url,
                                'basename' => $file->basename,
                                'filesize' => $file->filesize,
                                'description' => $file->description
                            ];
                        }
                        $pageData['fields'][$field->name] = $files;
                    }
                    // Datetime fields
                    elseif($field->type instanceof FieldtypeDatetime) {
                        $pageData['fields'][$field->name] = $value ? date('Y-m-d H:i:s', $value) : null;
                    } 
                    // Table fields
                    elseif($field->type->className() === 'FieldtypeTable') {
                        $pageData['fields'][$field->name] = json_decode(json_encode($value), true);
                    }
                    // Repeater fields
                    elseif($field->type->className() === 'FieldtypeRepeater' || 
                            $field->type->className() === 'FieldtypeRepeaterMatrix') {
                        $repeaterData = [];
                        foreach($value as $repeaterItem) {
                            $itemData = [
                                'id' => $repeaterItem->id
                            ];
                            
                            // For RepeaterMatrix add type and label
                            if($field->type->className() === 'FieldtypeRepeaterMatrix') {
                                $itemData['type'] = $repeaterItem->type;
                                // Get matrix type label
                                try {
                                    $matrixTypes = $field->type->getMatrixTypes($field);
                                    foreach($matrixTypes as $mt) {
                                        if($mt->name === $repeaterItem->type) {
                                            $itemData['type_label'] = $mt->label;
                                            break;
                                        }
                                    }
                                } catch(\Exception $e) {}
                            }
                            
                            // Export repeater item fields with proper types
                            foreach($repeaterItem->fields as $repField) {
                                $repValue = $repeaterItem->get($repField->name);
                                if(empty($repValue) && $repValue !== '0' && $repValue !== 0) continue;
                                
                                // Page reference in repeater
                                if($repField->type instanceof FieldtypePage) {
                                    if($repValue instanceof Page && $repValue->id) {
                                        $itemData[$repField->name] = [
                                            'id' => $repValue->id,
                                            'title' => $repValue->title,
                                            'url' => $repValue->url
                                        ];
                                    } elseif($repValue instanceof PageArray) {
                                        $itemData[$repField->name] = [];
                                        foreach($repValue as $p) {
                                            $itemData[$repField->name][] = [
                                                'id' => $p->id,
                                                'title' => $p->title,
                                                'url' => $p->url
                                            ];
                                        }
                                    }
                                }
                                // Images in repeater
                                elseif($repField->type instanceof FieldtypeImage) {
                                    $images = [];
                                    foreach($repValue as $img) {
                                        $images[] = [
                                            'url' => $img->url,
                                            'description' => $img->description,
                                            'width' => $img->width,
                                            'height' => $img->height
                                        ];
                                    }
                                    $itemData[$repField->name] = $images;
                                }
                                // Files in repeater
                                elseif($repField->type instanceof FieldtypeFile) {
                                    $files = [];
                                    foreach($repValue as $file) {
                                        $files[] = [
                                            'url' => $file->url,
                                            'basename' => $file->basename,
                                            'filesize' => $file->filesize
                                        ];
                                    }
                                    $itemData[$repField->name] = $files;
                                }
                                // Options in repeater
                                elseif($repField->type instanceof FieldtypeOptions) {
                                    if($repValue instanceof SelectableOption) {
                                        $itemData[$repField->name] = [
                                            'id' => $repValue->id,
                                            'value' => $repValue->value,
                                            'title' => $repValue->title
                                        ];
                                    } elseif($repValue instanceof SelectableOptionArray) {
                                        $itemData[$repField->name] = [];
                                        foreach($repValue as $opt) {
                                            $itemData[$repField->name][] = [
                                                'id' => $opt->id,
                                                'value' => $opt->value,
                                                'title' => $opt->title
                                            ];
                                        }
                                    }
                                }
                                // Datetime in repeater
                                elseif($repField->type instanceof FieldtypeDatetime) {
                                    $itemData[$repField->name] = $repValue ? date('Y-m-d H:i:s', $repValue) : null;
                                }
                                // Everything else as string
                                else {
                                    $itemData[$repField->name] = (string) $repValue;
                                }
                            }
                            $repeaterData[] = $itemData;
                        }
                        $pageData['fields'][$field->name] = $repeaterData;
                    }
                    // Combo fields
                    elseif($field->type->className() === 'FieldtypeCombo') {
                        // Combo stores data as object
                        $pageData['fields'][$field->name] = json_decode(json_encode($value), true);
                    }
                    // Other fields
                    else {
                        if(is_array($value)) {
                            $pageData['fields'][$field->name] = $value;
                        } else {
                            $pageData['fields'][$field->name] = (string) $value;
                        }
                    }
                }

                $templateSamples[] = $pageData;
            }

            $allSamples[$template->name] = [
                'template' => $template->name,
                'label' => $template->label ?: $template->name,
                'samples' => $templateSamples
            ];
            
            $filename = $samplesPath . "{$template->name}-samples.json";
            file_put_contents($filename, json_encode($templateSamples, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            
            // TOON format (if enabled)
            if($this->export_toon_format) {
                file_put_contents($samplesPath . "{$template->name}-samples.toon", $this->convertToToon(['samples' => $templateSamples]));
            }
        }

        // Combined file of all samples
        file_put_contents($samplesPath . '_all-samples.json', json_encode($allSamples, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        // TOON format (if enabled)
        if($this->export_toon_format) {
            file_put_contents($samplesPath . '_all-samples.toon', $this->convertToToon($allSamples));
        }

        return $allSamples;
    }

    /**
     * Export samples for Matrix templates
     */
    protected function exportMatrixSamples() {
        if(!$this->export_samples) return [];
        
        $samplesPath = $this->ensureFolder($this->getContextPath() . 'samples/');
        $allMatrixSamples = [];
        
        // Find all Matrix fields
        $matrixFields = [];
        foreach($this->fields as $field) {
            if($field->type->className() === 'FieldtypeRepeaterMatrix') {
                $matrixFields[] = $field;
            }
        }
        
        if(empty($matrixFields)) return [];
        
        $this->log("Exporting Matrix samples for " . count($matrixFields) . " fields");
        
        // For each Matrix field, find pages that use it
        foreach($matrixFields as $matrixField) {
            // Find templates that have this Matrix field
            $templatesWithMatrix = [];
            foreach($this->templates as $template) {
                if($template->hasField($matrixField)) {
                    $templatesWithMatrix[] = $template;
                }
            }
            
            if(empty($templatesWithMatrix)) continue;
            
            // Find pages with this template that have Matrix data
            foreach($templatesWithMatrix as $template) {
                $pages = $this->pages->find("template={$template->name}, {$matrixField->name}.count>0, limit={$this->samples_count}, sort=random");
                
                if(!$pages->count()) continue;
                
                foreach($pages as $page) {
                    $matrixItems = $page->get($matrixField->name);
                    if(!$matrixItems || !$matrixItems->count()) continue;
                    
                    foreach($matrixItems as $matrixItem) {
                        // Get the matrix type template name
                        $matrixTypeName = $matrixItem->template->name;
                        
                        if(!isset($allMatrixSamples[$matrixTypeName])) {
                            $allMatrixSamples[$matrixTypeName] = [
                                'template' => $matrixTypeName,
                                'label' => $matrixItem->template->label ?: $matrixTypeName,
                                'parent_field' => $matrixField->name,
                                'parent_field_label' => $matrixField->label,
                                'samples' => []
                            ];
                        }
                        
                        // Limit samples per matrix type
                        if(count($allMatrixSamples[$matrixTypeName]['samples']) >= $this->samples_count) {
                            continue;
                        }
                        
                        $itemData = [
                            'id' => $matrixItem->id,
                            'type' => $matrixItem->type,
                            'fields' => []
                        ];
                        
                        // Export all fields from this matrix item
                        foreach($matrixItem->template->fields as $itemField) {
                            $value = $matrixItem->get($itemField->name);
                            
                            if(empty($value) && $value !== '0' && $value !== 0) continue;
                            
                            // Handle different field types (same as in exportSamples)
                            if($itemField->type instanceof FieldtypePage) {
                                if($value instanceof Page && $value->id) {
                                    $itemData['fields'][$itemField->name] = [
                                        'id' => $value->id,
                                        'title' => $value->title,
                                        'url' => $value->url
                                    ];
                                } elseif($value instanceof PageArray) {
                                    $itemData['fields'][$itemField->name] = [];
                                    foreach($value as $p) {
                                        $itemData['fields'][$itemField->name][] = [
                                            'id' => $p->id,
                                            'title' => $p->title,
                                            'url' => $p->url
                                        ];
                                    }
                                }
                            }
                            elseif($itemField->type instanceof FieldtypeImage) {
                                $images = [];
                                foreach($value as $img) {
                                    $images[] = [
                                        'url' => $img->url,
                                        'description' => $img->description,
                                        'width' => $img->width,
                                        'height' => $img->height
                                    ];
                                }
                                $itemData['fields'][$itemField->name] = $images;
                            }
                            elseif($itemField->type instanceof FieldtypeFile) {
                                $files = [];
                                foreach($value as $file) {
                                    $files[] = [
                                        'url' => $file->url,
                                        'basename' => $file->basename,
                                        'filesize' => $file->filesize
                                    ];
                                }
                                $itemData['fields'][$itemField->name] = $files;
                            }
                            elseif($itemField->type instanceof FieldtypeDatetime) {
                                $itemData['fields'][$itemField->name] = $value ? date('Y-m-d H:i:s', $value) : null;
                            }
                            elseif($itemField->type->className() === 'FieldtypeTable') {
                                $itemData['fields'][$itemField->name] = json_decode(json_encode($value), true);
                            }
                            elseif($itemField->type->className() === 'FieldtypeCombo') {
                                $itemData['fields'][$itemField->name] = json_decode(json_encode($value), true);
                            }
                            elseif($itemField->type instanceof FieldtypeOptions) {
                                if($value instanceof SelectableOption) {
                                    $itemData['fields'][$itemField->name] = [
                                        'id' => $value->id,
                                        'value' => $value->value,
                                        'title' => $value->title
                                    ];
                                } elseif($value instanceof SelectableOptionArray) {
                                    $itemData['fields'][$itemField->name] = [];
                                    foreach($value as $opt) {
                                        $itemData['fields'][$itemField->name][] = [
                                            'id' => $opt->id,
                                            'value' => $opt->value,
                                            'title' => $opt->title
                                        ];
                                    }
                                }
                            }
                            else {
                                if(is_array($value)) {
                                    $itemData['fields'][$itemField->name] = $value;
                                } else {
                                    $itemData['fields'][$itemField->name] = (string) $value;
                                }
                            }
                        }
                        
                        $allMatrixSamples[$matrixTypeName]['samples'][] = $itemData;
                    }
                }
            }
        }
        
        // Write individual Matrix template samples
        foreach($allMatrixSamples as $matrixTypeName => $matrixSampleData) {
            $filename = $samplesPath . "{$matrixTypeName}-samples.json";
            file_put_contents($filename, json_encode($matrixSampleData['samples'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            
            // TOON format (if enabled)
            if($this->export_toon_format) {
                file_put_contents($samplesPath . "{$matrixTypeName}-samples.toon", $this->convertToToon(['samples' => $matrixSampleData['samples']]));
            }
            
            $this->log("Created samples for Matrix template: {$matrixTypeName} (" . count($matrixSampleData['samples']) . " samples)");
        }
        
        return $allMatrixSamples;
    }

    /**
     * Generate JSON Schema for API
     */
    protected function exportApiDocs() {
        $apiPath = $this->ensureFolder($this->getContextPath() . 'api/');
        $schemasPath = $this->ensureFolder($apiPath . 'schemas/');
        $examplesPath = $this->ensureFolder($apiPath . 'examples/');
        
        $endpoints = [];
        $allSchemas = [];

        foreach($this->templates as $template) {
            if($template->flags & Template::flagSystem) continue;

            // JSON Schema for template
            $schema = [
                '$schema' => 'http://json-schema.org/draft-07/schema#',
                'title' => $template->label ?: $template->name,
                'type' => 'object',
                'properties' => []
            ];

            $required = [];

            foreach($template->fields as $field) {
                $property = [
                    'type' => $this->getJsonSchemaType($field),
                    'description' => $field->label
                ];

                if($field->notes) {
                    $property['notes'] = $field->notes;
                }

                // Additional properties for different types
                if($field->type instanceof FieldtypePage) {
                    $property['$ref'] = '#/definitions/PageReference';
                } elseif($field->type instanceof FieldtypeImage) {
                    $property['type'] = 'array';
                    $property['items'] = ['$ref' => '#/definitions/Image'];
                } 
                // Table fields
                elseif($field->type->className() === 'FieldtypeTable') {
                    $property['type'] = 'array';
                    $property['description'] .= ' (Table field)';
                    if($field->columns) {
                        $property['items'] = [
                            'type' => 'object',
                            'properties' => []
                        ];
                        foreach($field->columns as $col) {
                            $property['items']['properties'][$col['name']] = [
                                'type' => 'string',
                                'description' => $col['label']
                            ];
                        }
                    }
                }
                // Repeater fields
                elseif($field->type->className() === 'FieldtypeRepeater') {
                    $property['type'] = 'array';
                    $property['description'] .= ' (Repeater field)';
                    $repeaterTemplate = $this->templates->get("repeater_" . $field->name);
                    if($repeaterTemplate) {
                        $property['items'] = [
                            'type' => 'object',
                            'properties' => []
                        ];
                        foreach($repeaterTemplate->fields as $repField) {
                            $property['items']['properties'][$repField->name] = [
                                'type' => 'string',
                                'description' => $repField->label
                            ];
                        }
                    }
                }
                // RepeaterMatrix fields
                elseif($field->type->className() === 'FieldtypeRepeaterMatrix') {
                    $property['type'] = 'array';
                    $property['description'] .= ' (RepeaterMatrix field)';
                    $matrixTypes = $field->type->getMatrixTypes($field);
                    $property['items'] = [
                        'oneOf' => []
                    ];
                    foreach($matrixTypes as $matrixType) {
                        if(!isset($matrixType->name)) continue;
                        
                        $matrixTemplate = $this->wire('templates')->get($matrixType->name);
                        if($matrixTemplate && $matrixTemplate instanceof Template) {
                            $matrixSchema = [
                                'type' => 'object',
                                'properties' => [
                                    'type' => [
                                        'type' => 'string',
                                        'const' => $matrixType->name,
                                        'description' => $matrixType->label
                                    ]
                                ],
                                'required' => ['type']
                            ];
                            foreach($matrixTemplate->getFields() as $matrixField) {
                                if($matrixField instanceof Field) {
                                    $matrixSchema['properties'][$matrixField->name] = [
                                        'type' => 'string',
                                        'description' => $matrixField->label
                                    ];
                                }
                            }
                            $property['items']['oneOf'][] = $matrixSchema;
                        }
                    }
                }
                // Combo fields
                elseif($field->type->className() === 'FieldtypeCombo') {
                    $property['type'] = 'object';
                    $property['description'] .= ' (Combo field)';
                    if($field->subfields) {
                        $property['properties'] = [];
                        foreach($field->subfields as $subfield) {
                            $property['properties'][$subfield->name] = [
                                'type' => 'string',
                                'description' => $subfield->label
                            ];
                        }
                    }
                }

                $schema['properties'][$field->name] = $property;

                if($field->required) {
                    $required[] = $field->name;
                }
            }

            if(!empty($required)) {
                $schema['required'] = $required;
            }

            // Definitions
            $schema['definitions'] = [
                'PageReference' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'integer'],
                        'title' => ['type' => 'string'],
                        'url' => ['type' => 'string']
                    ]
                ],
                'Image' => [
                    'type' => 'object',
                    'properties' => [
                        'url' => ['type' => 'string'],
                        'width' => ['type' => 'integer'],
                        'height' => ['type' => 'integer'],
                        'description' => ['type' => 'string']
                    ]
                ]
            ];

            $allSchemas[$template->name] = $schema;
            file_put_contents($schemasPath . "{$template->name}-schema.json", json_encode($schema, JSON_PRETTY_PRINT));

            // API Endpoint
            $samplePage = $this->pages->get("template={$template->name}");
            if($samplePage->id) {
                $endpoints[] = [
                    'path' => "/api/{$template->name}/{id}",
                    'method' => 'GET',
                    'description' => "Get single {$template->label}",
                    'response_schema' => "{$template->name}-schema.json"
                ];

                if($samplePage->numChildren > 0 || $this->pages->count("template={$template->name}") > 1) {
                    $endpoints[] = [
                        'path' => "/api/{$template->name}/",
                        'method' => 'GET',
                        'description' => "List all {$template->label}",
                        'query_params' => ['limit', 'page', 'sort'],
                        'response_schema' => "{$template->name}-schema.json (array)"
                    ];
                }
            }
        }

        file_put_contents($apiPath . 'endpoints.json', json_encode($endpoints, JSON_PRETTY_PRINT));
        file_put_contents($apiPath . 'all-schemas.json', json_encode($allSchemas, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return ['endpoints' => $endpoints, 'schemas' => $allSchemas];
    }

    /**
     * Determine JSON Schema type from ProcessWire field
     */
    protected function getJsonSchemaType($field) {
        $className = $field->type->className();
        
        $typeMap = [
            'FieldtypeText' => 'string',
            'FieldtypeTextarea' => 'string',
            'FieldtypePageTitle' => 'string',
            'FieldtypeInteger' => 'integer',
            'FieldtypeFloat' => 'number',
            'FieldtypeCheckbox' => 'boolean',
            'FieldtypeDatetime' => 'string',
            'FieldtypeURL' => 'string',
            'FieldtypeEmail' => 'string',
            'FieldtypePage' => 'object',
            'FieldtypeImage' => 'array',
            'FieldtypeFile' => 'array',
        ];

        return $typeMap[$className] ?? 'string';
    }

    /**
     * Export detailed custom field definitions
     */
    protected function exportFieldDefinitions() {
        $metadataPath = $this->ensureFolder($this->getContextPath() . 'metadata/');
        $definitions = [
            'custom_fields' => [],
            'field_types' => []
        ];

        foreach($this->fields as $field) {
            $className = $field->type->className();

            // Collect field type information
            if(!isset($definitions['field_types'][$className])) {
                $definitions['field_types'][$className] = [
                    'class' => $className,
                    'label' => $field->type->getModuleInfo()['title'] ?? $className,
                    'usage_count' => 0,
                    'examples' => []
                ];
            }

            $definitions['field_types'][$className]['usage_count']++;
            $definitions['field_types'][$className]['examples'][] = $field->name;

            // Detailed definitions for complex fields
            if($className === 'FieldtypeTable' && $field->columns) {
                $definitions['custom_fields'][$field->name] = [
                    'name' => $field->name,
                    'label' => $field->label,
                    'type' => 'Table',
                    'columns' => [],
                    'usage_example' => $field->notes ?: "Table field with structured data"
                ];

                foreach($field->columns as $col) {
                    $definitions['custom_fields'][$field->name]['columns'][] = [
                        'name' => $col['name'],
                        'label' => $col['label'],
                        'type' => $col['type'],
                        'purpose' => $col['label']
                    ];
                }
            }

            if($className === 'FieldtypeRepeater') {
                $repeaterTemplate = $this->templates->get("repeater_" . $field->name);
                if($repeaterTemplate) {
                    $definitions['custom_fields'][$field->name] = [
                        'name' => $field->name,
                        'label' => $field->label,
                        'type' => 'Repeater',
                        'fields' => [],
                        'usage_example' => $field->notes ?: "Repeatable set of fields"
                    ];

                    foreach($repeaterTemplate->fields as $repField) {
                        $definitions['custom_fields'][$field->name]['fields'][] = [
                            'name' => $repField->name,
                            'type' => $repField->type->className(),
                            'label' => $repField->label,
                            'purpose' => $repField->description ?: $repField->label
                        ];
                    }
                }
            }

            // RepeaterMatrix fields
            if($className === 'FieldtypeRepeaterMatrix') {
                $definitions['custom_fields'][$field->name] = [
                    'name' => $field->name,
                    'label' => $field->label,
                    'type' => 'RepeaterMatrix',
                    'matrix_types' => [],
                    'usage_example' => $field->notes ?: "Matrix of different repeater types"
                ];

                $matrixTypes = $field->type->getMatrixTypes($field);
                foreach($matrixTypes as $matrixType) {
                    if(!isset($matrixType->name)) continue;
                    
                    $matrixTemplate = $this->wire('templates')->get($matrixType->name);
                    if($matrixTemplate && $matrixTemplate instanceof Template) {
                        $matrixFields = [];
                        foreach($matrixTemplate->getFields() as $matrixField) {
                            if($matrixField instanceof Field) {
                                $matrixFields[] = [
                                    'name' => $matrixField->name,
                                    'type' => $matrixField->type->className(),
                                    'label' => $matrixField->label,
                                    'purpose' => $matrixField->description ?: $matrixField->label
                                ];
                            }
                        }
                        $definitions['custom_fields'][$field->name]['matrix_types'][] = [
                            'name' => $matrixType->name,
                            'label' => $matrixType->label,
                            'fields' => $matrixFields
                        ];
                    }
                }
            }

            // Combo fields
            if($className === 'FieldtypeCombo') {
                $definitions['custom_fields'][$field->name] = [
                    'name' => $field->name,
                    'label' => $field->label,
                    'type' => 'Combo',
                    'subfields' => [],
                    'usage_example' => $field->notes ?: "Combined set of subfields"
                ];

                if($field->subfields) {
                    foreach($field->subfields as $subfield) {
                        $definitions['custom_fields'][$field->name]['subfields'][] = [
                            'name' => $subfield->name,
                            'type' => $subfield->type,
                            'label' => $subfield->label,
                            'purpose' => $subfield->label
                        ];
                    }
                }
            }
        }

        file_put_contents($metadataPath . 'field-definitions.json', json_encode($definitions, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return $definitions;
    }

    /**
     * Export URL routing
     */
    protected function exportRoutes() {
        $metadataPath = $this->ensureFolder($this->getContextPath() . 'metadata/');
        $routes = [];

        foreach($this->templates as $template) {
            if($template->flags & Template::flagSystem) continue;

            $samplePage = $this->pages->get("template={$template->name}");
            if(!$samplePage->id) continue;

            $route = [
                'template' => $template->name,
                'label' => $template->label ?: $template->name,
                'url_pattern' => $samplePage->url,
                'has_children' => $samplePage->numChildren > 0,
                'allow_page_num' => $template->allowPageNum ? true : false,
                'url_segments' => $template->urlSegments ? true : false
            ];

            if($samplePage->numChildren > 0) {
                $firstChild = $samplePage->child();
                if($firstChild->id) {
                    $route['type'] = 'listing';
                    $route['children_template'] = $firstChild->template->name;
                    $route['children_url_pattern'] = $firstChild->url;
                }
            } else {
                $route['type'] = $samplePage->parent->id === 1 ? 'root' : 'detail';
            }

            $routes[] = $route;
        }

        file_put_contents($metadataPath . 'routes.json', json_encode($routes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return $routes;
    }

    /**
     * Export performance metrics
     */
    protected function exportPerformance() {
        $metadataPath = $this->ensureFolder($this->getContextPath() . 'metadata/');

        // Count pages by template
        $templateCounts = [];
        $totalPages = 0;
        $totalNonSystemTemplates = 0;
        
        foreach($this->templates as $template) {
            if($template->flags & Template::flagSystem) continue;
            $totalNonSystemTemplates++;
            
            $count = $this->pages->count("template={$template->name}");
            if($count > 0) {
                $templateCounts[] = [
                    'template' => $template->name,
                    'label' => $template->label ?: $template->name,
                    'count' => $count
                ];
                $totalPages += $count;
            }
        }

        // Sort by count
        usort($templateCounts, function($a, $b) {
            return $b['count'] - $a['count'];
        });

        // Database size
        $dbSize = 'N/A';
        try {
            $result = $this->database->query("SELECT 
                ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb 
                FROM information_schema.TABLES 
                WHERE table_schema = '{$this->config->dbName}'")->fetch();
            $dbSize = $result['size_mb'] . ' MB';
        } catch(\Exception $e) {
            // Ignore
        }

        $performance = [
            'total_pages' => $totalPages,
            'total_templates' => $totalNonSystemTemplates,
            'largest_templates' => array_slice($templateCounts, 0, 10),
            'database_size' => $dbSize,
            'processwire_version' => $this->config->version,
            'php_version' => phpversion(),
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'limits' => [
                'recommended_page_limit' => 1000,
                'max_file_upload' => ini_get('upload_max_filesize'),
                'post_max_size' => ini_get('post_max_size')
            ],
            'generated_at' => date('Y-m-d H:i:s')
        ];

        file_put_contents($metadataPath . 'performance.json', json_encode($performance, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return $performance;
    }

    /**
     * Create code library
     */
    protected function createSnippets() {
        $snippetsPath = $this->ensureFolder($this->getContextPath() . 'snippets/');
        
        // Load snippets library
        require_once(__DIR__ . '/ContextSnippets.php');
        
        // Get site type from settings
        $siteType = $this->site_type ?: 'generic';
        
        // Get real templates for examples
        $templates = [];
        foreach($this->templates as $t) {
            if($t->flags & Template::flagSystem) continue;
            if(in_array($t->name, ['home', 'basic-page', 'admin', 'sitemap'])) continue;
            $templates[] = $t->name;
        }
        
        // Generate snippets using external class
        $selectors = ContextSnippets::getSelectorsSnippet($siteType, $templates);
        file_put_contents($snippetsPath . 'selectors.php', $selectors);
        
        $helpers = ContextSnippets::getHelpersSnippet();
        file_put_contents($snippetsPath . 'helpers.php', $helpers);
        
        $apiExamples = ContextSnippets::getApiExamplesSnippet($siteType, $templates);
        file_put_contents($snippetsPath . 'api-examples.php', $apiExamples);
    }
    
    /**
     * Generate selectors snippet based on site type setting
     */
    protected function createPrompts() {
        $promptsPath = $this->ensureFolder($this->getContextPath() . 'prompts/');

        // Main context
        $projectContext = $this->generateProjectContext();
        file_put_contents($promptsPath . 'project-context.md', $projectContext);

        // Prompt for template creation
        $createTemplate = $this->generateCreateTemplatePrompt();
        file_put_contents($promptsPath . 'create-template.md', $createTemplate);

        // Prompt for API creation
        $createApi = $this->generateCreateApiPrompt();
        file_put_contents($promptsPath . 'create-api.md', $createApi);

        // Debugging prompt
        $debugPrompt = $this->generateDebugPrompt();
        file_put_contents($promptsPath . 'debug-issue.md', $debugPrompt);

        return true;
    }

    protected function generateProjectContext() {
        $homePage = $this->pages->get('/');
        
        // Detect frontend stack
        $stack = $this->detectFrontendStack();
        
        // Get route map
        $routes = $this->getRouteMap();
        
        // Get access map
        $access = $this->getAccessMap();
        
        // Count key metrics
        $stats = [];
        foreach($this->templates as $template) {
            if($template->flags & Template::flagSystem) continue;
            $count = $this->pages->count("template={$template->name}");
            if($count > 100) {
                $stats[] = "- **{$template->label}**: {$count} " . strtolower($template->label ?: $template->name);
            }
        }

        $phpVersion = phpversion();
        
        $toonInfo = '';
        if($this->export_toon_format) {
            $toonInfo = <<<TOON

## 📊 Export Formats

This site's context is available in two formats:

**TOON Format (.toon files) - RECOMMENDED FOR AI**
- Token-Oriented Object Notation
- 30-60% fewer tokens than JSON
- Optimized for AI assistants (Claude, ChatGPT, etc.)
- Same data, significantly smaller size
- Example: `templates.toon`, `structure.toon`, `samples/*.toon`

**JSON Format (.json files) - For Development**
- Standard JSON for APIs, tools, IDEs
- Example: `templates.json`, `structure.json`

**💡 Use .toon files for AI interactions to save tokens and reduce API costs!**

TOON;
        }

        $md = <<<MD
# SYSTEM PROMPT: ProcessWire Expert Mode

You are an expert developer for this specific ProcessWire instance.
{$toonInfo}

## Project Overview
**Site**: {$homePage->title}
**URL**: {$this->config->httpHost}
**ProcessWire Version**: {$this->config->version}
**PHP Version**: {$phpVersion}

## 🎨 Frontend & Design
**Tech Stack**: {$stack}

MD;

        // Add routes if any
        if(!empty($routes)) {
            $md .= "\n## 🛣 Route Map (URL Segments)\n";
            foreach($routes as $name => $info) {
                $md .= "- **Template '{$name}':** Allows {$info['max_segments']} segments ({$info['segments_allowed']})\n";
            }
        }

        // Add access control
        if(!empty($access)) {
            $md .= "\n## 🔐 Access Control (Roles & Permissions)\n";
            foreach($access as $role => $info) {
                $perms = implode(', ', $info['permissions']);
                $md .= "- **Role '{$role}':** [{$perms}]\n";
            }
        }

        $md .= "\n## Key Statistics\n";
        $md .= implode("\n", $stats);

        $md .= <<<MD


## Technical Stack
- **CMS**: ProcessWire {$this->config->version}
- **PHP**: {$phpVersion}
- **Database**: MySQL
- **Admin**: {$this->config->urls->admin}

## Content Organization
This site uses ProcessWire's flexible template system. See `/site/assets/context/structure.txt` for the complete page tree.

## Important Patterns

### Getting Pages
```php
// Single page
\$product = \$pages->get("template=product, name=product-slug");

// Multiple pages
\$products = \$pages->find("template=product, limit=20");

// With relationships
\$products = \$pages->find("template=product, brand=\$brandId");
```

### Working with Fields
```php
// Text fields
echo \$page->title;
echo \$page->summary;

// Page references
\$brand = \$page->brand; // Returns Page object
echo \$brand->title;

// Images
foreach(\$page->images as \$img) {
    echo "<img src='{\$img->url}' alt='{\$img->description}'>";
}
```

MD;

        $md .= <<<MD

## Common Tasks

1. **Listing Pages**: Use `\$pages->find()` with appropriate selectors
2. **Creating Pages**: Instantiate `new Page()`, set template and parent
3. **Search**: Use `title|summary%=\$query` for text search
4. **Pagination**: Use `limit` and `start` in selectors

## File References

**Core Files (TOON format recommended for AI):**
- **Structure**: 
  - `/site/assets/context/structure.toon` - Complete page tree (TOON - 43% fewer tokens)
  - `/site/assets/context/structure.json` - Complete page tree (JSON)
  - `/site/assets/context/structure.txt` - ASCII visualization
- **Templates**: 
  - `/site/assets/context/templates.toon` - All templates with fields (TOON - 50% fewer tokens)
  - `/site/assets/context/templates.json` - All templates with fields (JSON)
- **Samples**: 
  - `/site/assets/context/samples/*.toon` - Real content examples (TOON - 46% fewer tokens)
  - `/site/assets/context/samples/*.json` - Real content examples (JSON)
- **Snippets**: `/site/assets/context/snippets/` - Code examples
- **API Docs**: `/site/assets/context/api/` - API schemas and endpoints

**💡 Pro Tip**: Always prefer .toon files over .json when available - they contain the same data but use significantly fewer tokens!

## Notes
- Always sanitize user input using `\$sanitizer`
- Use ProcessWire's built-in URL functions
- Implement caching for heavy queries
- Keep selectors efficient

For detailed information, explore the files in `/site/assets/context/` directory.

MD;

        // Add custom AI instructions if set
        if($this->custom_ai_instructions) {
            $md .= "\n## 📝 Custom Project Instructions\n\n";
            $md .= $this->custom_ai_instructions . "\n";
        }

        
        return $md;
    }
    
    /**
     * Create IDE integration files
     */
    protected function createIntegrationFiles() {
        if(!$this->export_integrations) return;
        
        $rootDir = $this->config->paths->root;
        
        // Process .cursorrules
        $this->updateCursorRules($rootDir);
        
        // Process .claudecode.json
        $this->updateClaudeCode($rootDir);
    }
    
    /**
     * Update .cursorrules file (add paths if not exists)
     */
    protected function updateCursorRules($rootDir) {
        $cursorRulesPath = $rootDir . '.cursorrules';
        
        $contextPath = 'site/assets/context/';
        $promptsPath = 'site/assets/context/prompts/project-context.md';
        
        $newLines = [
            "# ProcessWire Context",
            "Root: {$rootDir}",
            "Context: {$contextPath}",
            "Follow rules in {$promptsPath}"
        ];
        
        if(file_exists($cursorRulesPath)) {
            // File exists - check if our paths are already there
            $content = file_get_contents($cursorRulesPath);
            $lines = explode("\n", $content);
            
            $needsUpdate = false;
            $linesToAdd = [];
            
            foreach($newLines as $newLine) {
                $found = false;
                foreach($lines as $line) {
                    // Check if this path/rule already exists
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
                // Add missing lines
                $content .= "\n\n" . implode("\n", $linesToAdd);
                file_put_contents($cursorRulesPath, $content);
            }
            // else - all paths already exist, skip
            
        } else {
            // File doesn't exist - create new
            file_put_contents($cursorRulesPath, implode("\n", $newLines));
        }
    }
    
    /**
     * Update .claudecode.json file (add context paths if not exists)
     */
    protected function updateClaudeCode($rootDir) {
        $claudeCodePath = $rootDir . '.claudecode.json';
        
        $contextPaths = [
            "site/assets/context/templates.json",
            "site/assets/context/prompts/project-context.md"
        ];
        
        if(file_exists($claudeCodePath)) {
            // File exists - read and update
            $content = file_get_contents($claudeCodePath);
            $config = json_decode($content, true);
            
            if(!$config) {
                // Invalid JSON - create new structure
                $config = [
                    "name" => "PW-" . $this->config->httpHost,
                    "context" => []
                ];
            }
            
            // Ensure context array exists
            if(!isset($config['context'])) {
                $config['context'] = [];
            }
            
            // Add missing paths
            $needsUpdate = false;
            foreach($contextPaths as $path) {
                if(!in_array($path, $config['context'])) {
                    $config['context'][] = $path;
                    $needsUpdate = true;
                }
            }
            
            if($needsUpdate) {
                file_put_contents($claudeCodePath, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            }
            // else - all paths already exist, skip
            
        } else {
            // File doesn't exist - create new
            $config = [
                "name" => "PW-" . $this->config->httpHost,
                "context" => $contextPaths
            ];
            file_put_contents($claudeCodePath, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
    }

    protected function generateCreateTemplatePrompt() {
        return <<<'MD'
# Create ProcessWire Template - AI Prompt

I need help creating a new ProcessWire template.

## Template Details

**Template Name**: [e.g., "winery", "event", "award"]

**Purpose**: [Describe what this template is for]

**Label**: [Human-readable label]

## Fields Required

### Basic Fields
- title (FieldtypePageTitle) - required
- [Add other fields...]

### Custom Fields
List any special fields needed:
1. [field_name] - [field_type] - [description]
2. ...

## Relationships
- Parent template: [which template contains these pages?]
- Child templates: [can this have children? which templates?]
- Page references: [connects to which other templates?]

## URL Structure
- Example URL: `/section/page-name/`
- Parent path: [e.g., /events/]

## Example Data
Provide 1-2 examples of pages that would use this template.

---

## Files to Reference
When generating the template, review:
- `/site/assets/context/templates.json` - existing field patterns
- `/site/assets/context/structure.txt` - site structure
- `/site/assets/context/snippets/selectors.php` - query examples

## Expected Output
Please generate:
1. Template file code (`templates/template-name.php`)
2. Field creation code or instructions
3. Example page creation code
4. Common selectors for querying these pages
MD;
    }

    protected function generateCreateApiPrompt() {
        return <<<'MD'
# Create ProcessWire API Endpoint - AI Prompt

I need to create a REST API endpoint for ProcessWire.

## API Endpoint Details

**Endpoint**: `/api/[resource]/[action]`

**Method**: GET / POST / PUT / DELETE

**Purpose**: [What this endpoint does]

## Request

### URL Parameters
- [param1]: [description]
- [param2]: [description]

### Query Parameters
- limit: [number of results]
- page: [page number]
- [custom params...]

### POST Body (if applicable)
```json
{
  "field1": "value",
  "field2": "value"
}
```

## Response

### Success Response (200)
```json
{
  "success": true,
  "data": {}
}
```

### Error Response (4xx/5xx)
```json
{
  "error": "Error message"
}
```

## Authentication
- Required: Yes / No
- Method: [Session, API Key, etc.]

## Example Use Cases
1. [Use case 1]
2. [Use case 2]

---

## Files to Reference
- `/site/assets/context/api/endpoints.json` - existing API endpoints
- `/site/assets/context/api/schemas/` - data schemas
- `/site/assets/context/snippets/api-examples.php` - code patterns

## Expected Output
Please generate:
1. Complete PHP endpoint code
2. Example request/response
3. Error handling
4. Authentication checks (if needed)
MD;
    }

    protected function generateDebugPrompt() {
        return <<<'MD'
# Debug ProcessWire Issue - AI Prompt

I'm experiencing an issue with my ProcessWire site.

## Problem Description
[Describe the issue in detail]

## What I'm Trying to Do
[What are you trying to accomplish?]

## Current Code
```php
// Paste your code here
```

## Error Messages
```
[Paste any error messages here]
```

## Expected Behavior
[What should happen?]

## Actual Behavior
[What is actually happening?]

## Environment
- ProcessWire Version: [check `/site/assets/context/config.json`]
- PHP Version: [check `/site/assets/context/performance.json`]
- Template: [which template is affected?]

## What I've Tried
1. [Thing 1]
2. [Thing 2]

---

## Files to Reference
- `/site/assets/context/templates.json` - template/field structure
- `/site/assets/context/snippets/` - code examples
- `/site/assets/context/structure.txt` - page tree

## Expected Help
Please provide:
1. Explanation of the issue
2. Fixed code
3. Alternative approaches
4. Best practices to avoid this in future
MD;
    }

    /**
     * Export configuration
     */
    protected function exportConfig() {
        return [
            'site_name' => $this->config->httpHost,
            'admin_url' => $this->config->urls->admin,
            'pw_version' => $this->config->version,
            'php_version' => phpversion(),
            'timezone' => $this->config->timezone,
            'debug_mode' => $this->config->debug,
            'charset' => $this->config->dbCharset,
            'exported_at' => date('Y-m-d H:i:s'),
            'export_version' => self::getModuleInfo()['version']
        ];
    }

    /**
     * Export modules
     */
    protected function exportModules() {
        $modules = [];
        
        foreach($this->modules as $moduleName) {
            try {
                // Get module info from ProcessWire
                $info = $this->modules->getModuleInfo($moduleName, ['verbose' => true]);
                
                // If summary or author is empty, try to read from file
                if(empty($info['summary']) || empty($info['author'])) {
                    $moduleFile = $this->modules->getModuleFile($moduleName);
                    if($moduleFile && file_exists($moduleFile)) {
                        $fileContent = file_get_contents($moduleFile);
                        
                        // Extract summary
                        if(empty($info['summary']) && preg_match('/[\'"]summary[\'"]\s*=>\s*[\'"]([^\'"]+)[\'"]/i', $fileContent, $summaryMatch)) {
                            $info['summary'] = $summaryMatch[1];
                        }
                        
                        // Extract author
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
                // Skip modules that can't be read
                continue;
            }
        }
        
        // Sort by name
        usort($modules, function($a, $b) {
            return strcmp($a['name'], $b['name']);
        });
        
        return $modules;
    }

    /**
     * Export custom page classes from /site/classes/
     */
    protected function exportCustomClasses() {
        $classes = [];
        $classesPath = $this->config->paths->site . 'classes/';
        
        if(!is_dir($classesPath)) {
            return $classes;
        }
        
        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($classesPath, \RecursiveDirectoryIterator::SKIP_DOTS)
            );
            
            foreach($iterator as $file) {
                if($file->isFile() && $file->getExtension() === 'php') {
                    $filePath = $file->getRealPath();
                    $relativePath = str_replace($classesPath, '', $filePath);
                    $content = file_get_contents($filePath);
                    
                    // Remove comments to avoid false matches
                    $contentNoComments = preg_replace('/\/\*.*?\*\/|\/\/.*/s', '', $content);
                    
                    // Extract namespace
                    $namespace = '';
                    if(preg_match('/namespace\s+([^;]+);/', $content, $nsMatch)) {
                        $namespace = trim($nsMatch[1]);
                    }
                    
                    // Extract class name and what it extends (from cleaned content)
                    if(preg_match('/\bclass\s+(\w+)(?:\s+extends\s+([\w\\\\]+))?/i', $contentNoComments, $classMatch)) {
                        $className = $classMatch[1];
                        $extends = isset($classMatch[2]) ? trim($classMatch[2], '\\') : null;
                        
                        // Get methods
                        preg_match_all('/(?:public|protected|private)\s+(?:static\s+)?function\s+(\w+)\s*\(/i', $content, $methodMatches);
                        $methods = array_unique($methodMatches[1]);
                        
                        // Remove magic methods and constructors from list
                        $methods = array_filter($methods, function($m) {
                            return !in_array($m, ['__construct', '__destruct', '__get', '__set', '__call', '__toString', '__isset', '__unset']);
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
                        
                        // Extract docblock description if exists
                        if(preg_match('/\/\*\*\s*\n\s*\*\s*(.+?)(?:\n\s*\*\s*\n|\*\/)/s', $content, $docMatch)) {
                            $description = trim($docMatch[1]);
                            // Clean up asterisks
                            $description = preg_replace('/^\s*\*\s*/m', '', $description);
                            $classInfo['description'] = $description;
                        }
                        
                        $classes[] = $classInfo;
                    }
                }
            }
            
            // Sort by name
            usort($classes, function($a, $b) {
                return strcmp($a['name'], $b['name']);
            });
            
        } catch(\Exception $e) {
            // If can't read directory, return empty array
        }
        
        return $classes;
    }

    /**
     * Create README
     */
    protected function createReadme() {
        $toonEnabled = $this->export_toon_format ? 'enabled' : 'disabled';
        $toonSection = '';
        
        if($this->export_toon_format) {
            $toonSection = <<<'TOON'

## 🎯 TOON Format (AI-Optimized)

This export includes files in **TOON (Token-Oriented Object Notation)** format alongside standard JSON files.

**What is TOON?**
- Compact, human-readable format designed for AI assistants
- Uses 30-60% fewer tokens than JSON
- Same data, smaller size = lower API costs

**File Formats:**
- `.json` files - Standard JSON for development, APIs, tools
- `.toon` files - AI-optimized format for Claude, ChatGPT, etc.

**When to use which:**
- ✅ **Use .toon for AI**: Upload to Claude, ChatGPT to save tokens and costs
- ✅ **Use .json for dev**: Use with IDEs, APIs, standard tools

**Example savings:**
```
structure.json  (15,000 tokens)  →  structure.toon  (8,500 tokens)  = 43% savings
templates.json  (8,000 tokens)   →  templates.toon  (4,000 tokens)  = 50% savings
samples/*.json  (12,000 tokens)  →  samples/*.toon  (6,500 tokens)  = 46% savings
```

**Viewing TOON files:**
- VS Code/Cursor: Install "TOON Language Support" extension
- PhpStorm: Use YAML syntax highlighting
- AI Assistants: Upload directly - they understand TOON natively

TOON;
        }
        
        // Directory structure - разный в зависимости от TOON
        $directoryStructure = '';
        if($this->export_toon_format) {
            $directoryStructure = <<<'STRUCTURE'
```
/site/assets/context/
├── README.md                      # This file
├── structure.json                 # Complete page tree (JSON)
├── structure.toon                 # Complete page tree (TOON - AI optimized)
├── structure.txt                  # Page tree visualization (ASCII)
├── templates.json                 # All templates with field definitions (JSON)
├── templates.toon                 # All templates with field definitions (TOON)
├── templates.csv                  # Templates export in CSV format
├── matrix-templates.json          # Repeater Matrix field types (JSON) - if ProFields installed
├── matrix-templates.toon          # Repeater Matrix field types (TOON) - if ProFields installed
├── config.json                    # Site configuration (JSON)
├── config.toon                    # Site configuration (TOON)
├── modules.json                   # Installed modules with versions (JSON)
├── modules.toon                   # Installed modules with versions (TOON)
├── classes.json                   # Custom page classes (JSON)
├── classes.toon                   # Custom page classes (TOON)
│
├── samples/                       # Real content examples (optional)
│   ├── [template]-samples.json    # Sample pages per template (JSON)
│   ├── [template]-samples.toon    # Sample pages per template (TOON)
│   └── _all-samples.json          # All samples combined (JSON)
│   └── _all-samples.toon          # All samples combined (TOON)
STRUCTURE;
        } else {
            $directoryStructure = <<<'STRUCTURE'
```
/site/assets/context/
├── README.md                      # This file
├── structure.json                 # Complete page tree (JSON)
├── structure.txt                  # Page tree visualization (ASCII)
├── templates.json                 # All templates with field definitions
├── templates.csv                  # Templates export in CSV format
├── matrix-templates.json          # Repeater Matrix field types (if ProFields installed)
├── config.json                    # Site configuration
├── modules.json                   # Installed modules with versions
├── classes.json                   # Custom page classes (/site/classes/)
│
├── samples/                       # Real content examples (optional)
│   ├── [template]-samples.json    # Sample pages per template
│   └── _all-samples.json          # All samples combined
STRUCTURE;
        }
        
        return <<<README
# ProcessWire AI Context Documentation

This directory contains a comprehensive export of your ProcessWire site structure, optimized for use with AI development assistants (ChatGPT, Claude, Copilot, etc.).

**Generated by Context Module v1.1.0**
**TOON Format: {$toonEnabled}**
{$toonSection}

## 📁 Directory Structure

{$directoryStructure}
├── classes.json                   # Custom page classes (JSON)
├── classes.toon                   # Custom page classes (TOON)
│
├── samples/                       # Real content examples (optional)
│   └── _all-samples.toon          # All samples combined (TOON)
│
├── api/                           # API documentation (optional)
│   ├── endpoints.json             # Available API endpoints
│   ├── all-schemas.json           # All JSON schemas
│   └── schemas/
│       └── [template]-schema.json # JSON Schema per template
│
├── snippets/                      # Code library (optional)
│   ├── selectors.php              # Selector patterns for your site type
│   ├── helpers.php                # Utility functions
│   └── api-examples.php           # API implementation examples
│
├── prompts/                       # Ready-to-use AI prompts (optional)
│   └── project-context.md         # Overall project context & instructions
│
└── metadata/                      # Technical metadata (optional)
    ├── routes.json                # URL routing structure
    ├── field-definitions.json     # Detailed field information
    └── performance.json           # Performance metrics

```

**Note:** Folders marked (optional) are created only if enabled in module settings.

## 🎯 Site Type Configuration

The snippets in this export are customized for your site type. You can change this in:  
**Setup → Modules → Context → Configure → Site Type**

**Available site types:**
- **Generic / Mixed Content** - General purpose with various content types
- **Blog / News / Magazine** - Articles, posts, authors, categories
- **E-commerce / Online Store** - Products, cart, orders, inventory
- **Business / Portfolio / Agency** - Services, team, projects, case studies
- **Catalog / Directory / Listings** - Brands, categories, hierarchical data

Changing the site type will regenerate `snippets/selectors.php` with relevant examples.

## 🚀 How to Use with AI

### Quick Start

README;
        
        // Условный Quick Start в зависимости от TOON
        $readme .= $this->export_toon_format ? <<<'TOON_QUICK'

1. **Upload core files** to your AI assistant:
   - **For AI (TOON - Recommended)**: `prompts/project-context.md`, `templates.toon`, `structure.toon`
   - **For Development (JSON)**: `templates.json`, `structure.json`
   - **Always useful**: `structure.txt`, `README.md`
   - **For coding**: `snippets/selectors.php`, `snippets/helpers.php`
   - **For API work**: `api/schemas/`, `snippets/api-examples.php`

2. **Describe your task** clearly to the AI

3. **Reference specific files** when asking questions

**💡 Pro Tip**: Use `.toon` files instead of `.json` when uploading to AI assistants - you'll save 30-60% on tokens and API costs!

### Common Workflows

#### Understanding Site Structure
```
Upload: structure.toon, templates.toon, README.md  (TOON format saves ~45% tokens!)
Ask: "Explain the site structure and main content types"
```

#### Creating a New Template
```
Upload: templates.toon, prompts/project-context.md  (50% fewer tokens than JSON!)
Ask: "Create a template for [purpose] following existing patterns"
```

#### Building Features with Selectors
```
Upload: snippets/selectors.php, templates.toon
Ask: "Show me how to get the 10 most recent [items] with images"
```
TOON_QUICK
 : <<<'JSON_QUICK'

1. **Upload core files** to your AI assistant:
   - **Always**: `prompts/project-context.md`, `templates.json`
   - **Recommended**: `structure.txt`, `README.md`
   - **For coding**: `snippets/selectors.php`, `snippets/helpers.php`
   - **For API work**: `api/schemas/`, `snippets/api-examples.php`

2. **Describe your task** clearly to the AI

3. **Reference specific files** when asking questions

### Common Workflows

#### Understanding Site Structure
```
Upload: structure.txt, templates.json, README.md
Ask: "Explain the site structure and main content types"
```

#### Creating a New Template
```
Upload: templates.json, prompts/project-context.md
Ask: "Create a template for [purpose] following existing patterns"
```

#### Building Features with Selectors
```
Upload: snippets/selectors.php, templates.json
Ask: "Show me how to get the 10 most recent [items] with images"
```
JSON_QUICK;

        $readme .= <<<'README'

#### Building an API Endpoint
```
Upload: api/schemas/, snippets/api-examples.php
Ask: "Create a REST API endpoint for [template] with CRUD operations"
```

#### Debugging an Issue
```
Upload: templates.json, samples/[template]-samples.json
Ask: "Why is [field] not working on [template]? Here's sample data."
```

#### Working with Custom Page Classes
```
Upload: classes.json, templates.json
Ask: "Create a custom Page class for [template] with methods to [purpose]"
```

## 📊 File Descriptions

### Core Files (Always Generated)

**structure.json**
- Complete hierarchical page tree in JSON format
- Includes page IDs, titles, templates, URLs, parent-child relationships
- **Use for**: Understanding site architecture, building navigation, finding pages programmatically

**structure.txt**
- Human-readable ASCII tree visualization
- Shows site structure at a glance with indentation
- **Use for**: Quick overview, documentation, sharing with non-technical team members

**templates.json**
- All templates with complete field definitions
- Field types, labels, requirements, options, default values
- Includes Repeater Matrix, Table field structures
- **Use for**: Template development, understanding field configurations, building forms

**templates.csv**
- Same data as templates.json but in CSV format
- Easy to import into Excel, Google Sheets
- **Use for**: Analysis, planning, sharing with stakeholders

**matrix-templates.json** (if ProFields Repeater Matrix installed)
- Detailed structure of all Repeater Matrix field types
- Each matrix type with complete field definitions
- Includes parent field information, labels, sort order
- All field options, settings, and relationships
- **Use for**: Understanding complex Matrix structures, AI-assisted Matrix development, documentation

**config.json**
- ProcessWire version, PHP version, database info
- Site configuration, timezone, installed language
- Frontend stack detection (Alpine.js, Tailwind, UIkit, etc.)
- **Use for**: Environment setup, compatibility checks, deployment planning

**modules.json**
- All installed modules with versions, summaries, authors
- Sorted alphabetically for easy reference
- **Use for**: Module compatibility checks, understanding available functionality

**classes.json**
- Custom Page classes from `/site/classes/` directory
- Class names, namespaces, extends, methods, descriptions
- Shows which templates use custom classes
- **Use for**: Understanding OOP structure, custom page behaviors, available methods

### Optional Directories

**snippets/** (if Code Snippets enabled)
- **selectors.php**: Customized selector examples for your site type
  - Basic queries, search, sorting, filtering
  - Type-specific patterns (blog posts, products, services, etc.)
  - Advanced selectors, pagination, counting
  - Real template names from your site
- **helpers.php**: Universal helper functions
  - Page helpers (getPageTitle, getBreadcrumbs)
  - Text helpers (getExcerpt, timeAgo)
  - URL helpers (isCurrentPage, getCurrentUrl)
  - Image helpers (getResponsiveImage)
  - Form helpers (sanitizeInput, getInput)
- **api-examples.php**: REST API implementation examples
  - List items, get single item, search
  - Customized for your site type
- **Note**: Snippets are generated based on your Site Type setting
- **To customize**: Edit `/site/modules/Context/ContextSnippets.php`

**samples/** (if Content Samples enabled)
- Real content examples exported from live pages
- Shows actual data formats and field usage patterns
- Helps AI understand how data is structured in practice
- **Use for**: Data migration, understanding content patterns, training AI on your data

**api/** (if API Documentation enabled)
- JSON schemas for each template
- Endpoint documentation and examples
- **Use for**: Building REST APIs, headless CMS integration, external applications

**prompts/** (if AI Prompts enabled)
- **project-context.md**: Complete system-level instructions for AI
  - Site overview, technical stack, templates, fields
  - Best practices, code standards, common patterns
  - Custom AI instructions (if configured)
- **Use for**: Consistent AI interactions, onboarding, complex workflows

**metadata/** (if enabled)
- **routes.json**: URL segment configurations
- **field-definitions.json**: Deep field type information (Repeater, Matrix, Table)
- **performance.json**: Site metrics, page counts, database size
- **Use for**: Advanced development, optimization, troubleshooting

## 🎯 Best Practices

### When Working with AI

1. **Always start with project-context.md** - it contains system instructions
2. **Upload templates.json** for any field-related questions
3. **Use structure.txt** for quick site overview
4. **Include snippets/selectors.php** when writing queries
5. **Reference samples/** when asking about data patterns

### File Upload Strategy

- **Small tasks** (< 3 files): Upload directly to chat
- **Medium tasks** (3-10 files): Upload core files + specific sections
- **Large tasks** (10+ files): Use Claude Projects or upload entire `/context/` folder

### Updating Context

The context exports automatically when you change templates or fields if **Auto-Update on Changes** is enabled in module settings.

Otherwise, click **Re-Export Context for AI** in the module when you:
- Add or modify templates
- Add or modify fields
- Make structural changes to the site
- Change the Site Type setting

## 🔧 Module Settings

Configure what gets exported in **Setup → Modules → Context → Configure**

### Site Type Selection
Choose your site type to customize code snippets:
- Generic / Mixed Content
- Blog / News / Magazine
- E-commerce / Online Store
- Business / Portfolio / Agency
- Catalog / Directory / Listings

### Content Features
- **Export Content Samples**: Include real page examples
- **Samples Per Template**: Number of examples (1-10)
- **Generate API Documentation**: Create JSON schemas
- **Export URL Routes**: URL segment configurations
- **Export Performance Metrics**: Site statistics
- **Create Code Snippets**: PHP code examples (customized per site type)
- **Create AI Prompts**: Ready-to-use prompt templates

### Advanced Settings
- **Maximum Tree Depth**: How deep to export page tree (3-20)
- **JSON Children Limit**: Max children per page in JSON (5-100)
- **Compact Mode**: Collapse large lists in structure.txt
- **Auto-Update on Changes**: Auto-regenerate on template/field save
- **Create IDE Integration Files**: Generate `.cursorrules`, `.claudecode.json`
- **Custom AI Instructions**: Project-specific instructions for AI

## 💡 Tips & Tricks

### Customizing Code Snippets

The code snippets in `snippets/selectors.php` are generated from templates in:
`/site/modules/Context/ContextSnippets.php`

To add your own patterns:
1. Edit `ContextSnippets.php`
2. Add examples to the appropriate method (getBlogSelectors, getEcommerceSelectors, etc.)
3. Re-export context

### Working with Multiple Projects

If you use Claude Projects:
1. Create a project for each ProcessWire site
2. Upload the entire `/site/assets/context/` folder to Project Knowledge
3. AI will have permanent access to your site structure

### IDE Integration

If **Create IDE Integration Files** is enabled:
- `.cursorrules` - Rules for Cursor AI editor
- `.claudecode.json` - Configuration for Claude Code CLI

These files help AI editors understand your ProcessWire project structure.

## 📖 Additional Resources

- **ProcessWire Documentation**: https://processwire.com/docs/
- **API Reference**: https://processwire.com/api/ref/
- **Selectors Guide**: https://processwire.com/docs/selectors/
- **Module Repository**: https://modules.processwire.com/
- **ProcessWire Forums**: https://processwire.com/talk/

## 🔄 Version History

**v1.0.0** - Current version
- Site type selection (5 types)
- Customized code snippets per site type
- External snippets library (ContextSnippets.php)
- Custom page classes export
- Frontend stack detection
- IDE integration files
- Auto-update on changes
- Comprehensive documentation

---

**Export location**: `/site/assets/context/`  
**Module**: Context v1.0.0  
**Website**: https://processwire.com  

Use AI assistants effectively with complete site context! 🚀

README;
    }


    /**
     * Main export function
     */
    public function executeExport() {
        $startTime = microtime(true);
        
        try {
            $aiPath = $this->ensureFolder($this->getContextPath());
            
            $this->message("🚀 Starting Context export...");
            
            // 1. Base files
            $this->message("📄 Exporting base structure...");
            
            $structure = $this->buildPageTree($this->pages->get('/'), 0, $this->max_depth);
            file_put_contents($aiPath . 'structure.json', json_encode($structure, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            
            // TOON format (if enabled)
            if($this->export_toon_format) {
                file_put_contents($aiPath . 'structure.toon', $this->convertToToon($structure));
            }
            
            $asciiTree = $this->buildAsciiTree($this->pages->get('/'), 0, '', true, $this->max_depth);
            file_put_contents($aiPath . 'structure.txt', $asciiTree);
            
            $templates = $this->exportTemplates();
            
            // Export complete site tree (structure + templates + fields)
            $this->message("🌳 Exporting complete site tree...");
            $tree = $this->exportTree();
            file_put_contents($aiPath . 'tree.json', json_encode($tree, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            
            // TOON format (if enabled)
            if($this->export_toon_format) {
                file_put_contents($aiPath . 'tree.toon', $this->convertToToon($tree));
            }
            
            // Export Matrix templates separately
            $this->exportMatrixTemplates();
            
            // Export CSV version of templates
            $this->exportTemplatesToCSV();
            
            $config = $this->exportConfig();
            file_put_contents($aiPath . 'config.json', json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            
            // TOON format (if enabled)
            if($this->export_toon_format) {
                file_put_contents($aiPath . 'config.toon', $this->convertToToon($config));
            }
            
            $modules = $this->exportModules();
            file_put_contents($aiPath . 'modules.json', json_encode($modules, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            
            // TOON format (if enabled)
            if($this->export_toon_format) {
                file_put_contents($aiPath . 'modules.toon', $this->convertToToon(['modules' => $modules]));
            }
            
            // Export custom page classes
            $classes = $this->exportCustomClasses();
            if(!empty($classes)) {
                file_put_contents($aiPath . 'classes.json', json_encode($classes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                
                // TOON format (if enabled)
                if($this->export_toon_format) {
                    file_put_contents($aiPath . 'classes.toon', $this->convertToToon(['classes' => $classes]));
                }
            }
            
            // 2. Optional components
            if($this->export_samples) {
                $this->message("📦 Exporting content samples...");
                $this->exportSamples();
                $this->exportMatrixSamples();
            }
            
            if($this->export_api_docs) {
                $this->message("🔌 Generating API documentation...");
                $this->exportApiDocs();
            }
            
            if($this->export_field_definitions) {
                $this->message("📋 Exporting field definitions...");
                $this->exportFieldDefinitions();
            }
            
            if($this->export_routes) {
                $this->message("🗺️ Exporting URL routes...");
                $this->exportRoutes();
            }
            
            if($this->export_performance) {
                $this->message("📊 Collecting performance metrics...");
                $this->exportPerformance();
            }
            
            if($this->export_snippets) {
                $this->message("💻 Creating code snippets...");
                $this->createSnippets();
            }
            
            if($this->export_prompts) {
                $this->message("🤖 Creating AI prompts...");
                $this->createPrompts();
            }
            
            // Create IDE integration files (if enabled)
            $this->createIntegrationFiles();
            
            // 3. README
            file_put_contents($aiPath . 'README.md', $this->createReadme());
            
            $duration = round(microtime(true) - $startTime, 2);
            
            $this->message("✅ Context successfully exported to: <strong>{$aiPath}</strong>");
            $this->message("⏱️ Export completed in {$duration} seconds");
            
            // Log to file
            $this->log("Context exported successfully in {$duration}s");
            
            // Redirect to main module page (without /export/)
            $this->session->redirect($this->page->url);
            
        } catch(\Exception $e) {
            $this->error("❌ Export failed: " . $e->getMessage());
            $this->log("Context export failed: " . $e->getMessage());
        }
    }

    /**
     * Detect frontend stack
     */
    protected function detectFrontendStack() {
        $stack = [];
        $rootDir = $this->config->paths->root;
        $templatesPath = $this->config->paths->templates;

        // Check package.json
        if(file_exists($rootDir . 'package.json')) {
            $pkg = json_decode(file_get_contents($rootDir . 'package.json'), true);
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

        // Scan template files
        $contentSample = "";
        if(is_dir($templatesPath)) {
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($templatesPath));
            $count = 0;
            foreach($files as $file) {
                if($file->isDir()) continue;
                if(in_array($file->getExtension(), ['php', 'inc', 'js', 'css'])) {
                    $contentSample .= file_get_contents($file->getRealPath(), false, null, 0, 1024);
                    $count++;
                }
                if($count > 100) break;
            }
        }

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

    /**
     * Get route map (URL segments)
     */
    protected function getRouteMap() {
        $routeMap = [];
        $templatesPath = $this->config->paths->templates;
        
        foreach($this->templates as $tmpl) {
            if($tmpl->flags & Template::flagSystem) continue;
            
            $file = $templatesPath . $tmpl->name . '.php';
            if(!file_exists($file)) continue;
            
            $content = file_get_contents($file);
            $foundSegments = [];
            
            if(preg_match_all('/urlSegment([1-9])/', $content, $matches)) {
                $foundSegments = array_unique($matches[1]);
            }

            if(!empty($foundSegments) || $tmpl->urlSegments) {
                $routeMap[$tmpl->name] = [
                    'segments_allowed' => $tmpl->urlSegments ? 'Yes' : 'Detected in code',
                    'max_segments' => !empty($foundSegments) ? max($foundSegments) : 'unknown'
                ];
            }
        }
        return $routeMap;
    }

    /**
     * Get access map (roles & permissions)
     */
    protected function getAccessMap() {
        $accessMap = [];
        foreach($this->roles as $role) {
            if($role->name === 'guest' && !$role->permissions->count()) continue;
            
            $accessMap[$role->name] = [
                'permissions' => $role->permissions->explode('name'),
                'description' => $role->get('title|name')
            ];
        }
        return $accessMap;
    }

    /**
     * Get site statistics
     */
    protected function getSiteStats() {
        $stats = [
            'templates' => 0,
            'fields' => 0,
            'pages' => 0,
            'users' => 0
        ];
        
        foreach($this->templates as $t) {
            if(!($t->flags & Template::flagSystem)) $stats['templates']++;
        }
        
        foreach($this->fields as $f) {
            if(!($f->flags & Field::flagSystem)) $stats['fields']++;
        }
        
        $stats['pages'] = $this->pages->count("id>0");
        $stats['users'] = $this->users->count();
        
        return $stats;
    }

    /**
     * Human readable time difference
     */
    protected function human_time_diff($timestamp) {
        $diff = time() - $timestamp;
        
        if($diff < 60) return $diff . 's ago';
        if($diff < 3600) return floor($diff / 60) . 'm ago';
        if($diff < 86400) return floor($diff / 3600) . 'h ago';
        if($diff < 604800) return floor($diff / 86400) . 'd ago';
        
        return date('M j', $timestamp);
    }

    /**
     * Main module page
     */
    public function execute() {
        $contextPath = $this->getContextPath();
        $exists = is_dir($contextPath);
        $stats = $this->getSiteStats();
        
        $out = '';
        
        // Custom CSS
        $out .= "<style>
        .context-metric-card {
            background: #fff;
            border-radius: 8px;
            padding: 24px;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border: 1px solid #e5e5e5;
        }
        .context-metric-label {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #999;
            font-weight: 600;
            margin-bottom: 8px;
        }
        .context-metric-value {
            font-size: 36px;
            font-weight: 700;
            color: #333;
            line-height: 1;
            margin: 12px 0;
        }
        .context-metric-value.success {
            color: #10b981;
        }
        .context-metric-value.warning {
            color: #f59e0b;
        }
        .context-metric-sublabel {
            font-size: 12px;
            color: #666;
            margin-top: 4px;
        }
        .context-status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .context-status-badge.success {
            background: #d1fae5;
            color: #065f46;
        }
        .context-status-badge.muted {
            background: #f3f4f6;
            color: #6b7280;
        }
        .context-config-table {
            width: 100%;
            border-collapse: collapse;
        }
        .context-config-table th {
            text-align: left;
            padding: 12px 16px;
            background: #f9fafb;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #6b7280;
            font-weight: 600;
            border-bottom: 2px solid #e5e7eb;
        }
        .context-config-table td {
            padding: 14px 16px;
            border-bottom: 1px solid #f3f4f6;
            font-size: 14px;
        }
        .context-section-title {
            font-size: 20px;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .context-tips-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 12px;
        }
        .context-tip-item {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 12px;
            background: #f9fafb;
            border-radius: 6px;
            font-size: 13px;
            line-height: 1.6;
        }
        .context-tip-item code {
            background: #e5e7eb;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 12px;
        }
        .bi {
            display: inline-block;
            width: 1em;
            height: 1em;
            vertical-align: -0.125em;
        }
        </style>";
        
        // Big metrics cards (без заголовка)
        $out .= "<div style='display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 16px; margin-bottom: 32px;'>";
        
        // Templates
        $out .= "<div class='context-metric-card'>";
        $out .= "<div class='context-metric-label'>Templates</div>";
        $out .= "<div class='context-metric-value'>{$stats['templates']}</div>";
        $out .= "<div class='context-metric-sublabel'>Total</div>";
        $out .= "</div>";
        
        // Fields
        $out .= "<div class='context-metric-card'>";
        $out .= "<div class='context-metric-label'>Fields</div>";
        $out .= "<div class='context-metric-value'>{$stats['fields']}</div>";
        $out .= "<div class='context-metric-sublabel'>Custom</div>";
        $out .= "</div>";
        
        // Pages
        $out .= "<div class='context-metric-card'>";
        $out .= "<div class='context-metric-label'>Pages</div>";
        $out .= "<div class='context-metric-value'>{$stats['pages']}</div>";
        $out .= "<div class='context-metric-sublabel'>Published</div>";
        $out .= "</div>";
        
        if($exists) {
            $fileCount = 0;
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($contextPath, \RecursiveDirectoryIterator::SKIP_DOTS)
            );
            foreach($iterator as $file) {
                if($file->isFile()) $fileCount++;
            }
            
            $folderSize = $this->getFolderSize($contextPath);
            $readmePath = $contextPath . 'README.md';
            $lastModified = file_exists($readmePath) ? filemtime($readmePath) : null;
            
            // Exported Files
            $out .= "<div class='context-metric-card'>";
            $out .= "<div class='context-metric-label'>Exported Files</div>";
            $out .= "<div class='context-metric-value success'>{$fileCount}</div>";
            $out .= "<div class='context-metric-sublabel'>Ready</div>";
            $out .= "</div>";
            
            // Export Size
            $out .= "<div class='context-metric-card'>";
            $out .= "<div class='context-metric-label'>Export Size</div>";
            $out .= "<div class='context-metric-value success' style='white-space: nowrap;'>" . $this->formatBytes($folderSize) . "</div>";
            $out .= "<div class='context-metric-sublabel'>Total</div>";
            $out .= "</div>";
            
            // Last Export
            $out .= "<div class='context-metric-card'>";
            $out .= "<div class='context-metric-label'>Last Export</div>";
            if($lastModified) {
                $diff = time() - $lastModified;
                if($diff < 60) {
                    $timeAgo = $diff . 's ago';
                } elseif($diff < 3600) {
                    $timeAgo = floor($diff / 60) . 'm ago';
                } elseif($diff < 86400) {
                    $timeAgo = floor($diff / 3600) . 'h ago';
                } elseif($diff < 604800) {
                    $timeAgo = floor($diff / 86400) . 'd ago';
                } else {
                    $timeAgo = date('M j', $lastModified);
                }
                
                $out .= "<div class='context-metric-value success'>{$timeAgo}</div>";
                $out .= "<div class='context-metric-sublabel'>" . date('M j, H:i', $lastModified) . "</div>";
            } else {
                $out .= "<div class='context-metric-value'>-</div>";
                $out .= "<div class='context-metric-sublabel'>Never</div>";
            }
            $out .= "</div>";
            
        } else {
            // Status: Not Exported
            $out .= "<div class='context-metric-card'>";
            $out .= "<div class='context-metric-label'>Status</div>";
            $out .= "<div class='context-metric-value warning'>Not Exported</div>";
            $out .= "<div class='context-metric-sublabel'>Click below</div>";
            $out .= "</div>";
        }
        
        $out .= "</div>"; // end metrics grid
        
        // TOON Format Banner (if enabled)
        if($this->export_toon_format) {
            $out .= "<div class='notes' style='border-radius: 8px; padding: 20px; margin-bottom: 24px;'>";
            $out .= "<div style='display: flex; align-items: center; gap: 16px;'>";
            $out .= "<div style='font-size: 42px; color: #10b981;'><i class='fa fa-magic'></i></div>";
            $out .= "<div style='flex: 1;'>";
            $out .= "<h3 style='margin: 0 0 8px 0; font-size: 17px; font-weight: 600; color: #1f2937;'>TOON Format Enabled</h3>";
            $out .= "<p style='margin: 0; font-size: 14px; line-height: 1.6; color: #4b5563;'>";
            $out .= "Your exports include <strong>AI-optimized TOON files</strong> that use <strong>30-60% fewer tokens</strong> than JSON. ";
            $out .= "Upload <code style='background: #e5e7eb; padding: 2px 6px; border-radius: 3px;'>.toon</code> files to AI assistants to save API costs!";
            $out .= "</p>";
            $out .= "</div>";
            $out .= "</div>";
            $out .= "</div>";
        }
        
        // Export Button
        $out .= "<div style='text-align: center; margin: 32px 0;'>";
        
        $btn = $this->modules->get('InputfieldButton');
        $btn->href = './export/';
        $btn->icon = 'download';
        
        if($exists) {
            $btn->value = 'Re-Export Context for AI';
            $btn->class = 'ui-button ui-state-default ui-priority-secondary';
        } else {
            $btn->value = 'Export Context for AI';
            $btn->class = 'ui-button ui-state-default';
        }
        
        $out .= $btn->render();
        
        if($exists) {
            $out .= "<div style='margin-top: 12px; font-size: 13px; color: #6b7280;'>";
            $out .= "<i class='fa fa-folder'></i> <code style='background: #f3f4f6; padding: 4px 8px; border-radius: 4px;'>{$contextPath}</code>";
            $out .= "</div>";
        }
        $out .= "</div>";
        
        // Configuration table
        $out .= "<div style='background: white; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); overflow: hidden; margin-bottom: 24px; border: 1px solid #e5e5e5;'>";
        $out .= "<div style='padding: 20px 24px; border-bottom: 1px solid #f3f4f6;'>";
        $out .= "<h3 class='context-section-title'><i class='fa fa-cog'></i> Module Configuration</h3>";
        $out .= "</div>";
        $out .= "<div style='padding: 0;'>";
        
        $out .= "<table class='context-config-table'>";
        $out .= "<thead><tr><th>FEATURE</th><th>STATUS</th><th>VALUE</th></tr></thead>";
        $out .= "<tbody>";
        
        // Check if integration files exist
        $rootDir = $this->config->paths->root;
        $cursorrulesExists = file_exists($rootDir . '.cursorrules');
        $claudecodeExists = file_exists($rootDir . '.claudecode.json');
        $integrationsCreated = $cursorrulesExists && $claudecodeExists;
        
        // All features with proper checks
        $allFeatures = [
            ['export_toon_format', 'TOON Format Export', 'boolean', 'AI-optimized (30-60% fewer tokens)', null],
            ['auto_update', 'Auto-Update on Changes', 'boolean', null, null],
            ['export_samples', 'Content Samples', 'boolean', $this->samples_count . ' per template', null],
            ['export_api_docs', 'API Documentation', 'boolean', null, null],
            ['export_field_definitions', 'Field Definitions', 'boolean', null, null],
            ['export_routes', 'URL Routes', 'boolean', null, null],
            ['export_performance', 'Performance Metrics', 'boolean', null, null],
            ['export_snippets', 'Code Snippets', 'boolean', null, null],
            ['export_prompts', 'AI Prompts', 'boolean', null, null],
            ['export_integrations', 'Integration Files', 'custom', '.cursorrules, .claudecode.json', $integrationsCreated],
            ['compact_mode', 'Compact Mode', 'boolean', 'Reduce file sizes', null],
            ['max_depth', 'Maximum Tree Depth', 'number', $this->max_depth . ' levels', null],
            ['json_child_limit', 'JSON Children Limit', 'number', $this->json_child_limit . ' items', null]
        ];
        
        foreach($allFeatures as $feature) {
            list($setting, $label, $type, $value, $customCheck) = $feature;
            
            if($type === 'boolean') {
                $isEnabled = $this->$setting ? true : false;
                if($isEnabled) {
                    $status = "<span class='context-status-badge success'><i class='fa fa-check'></i> Enabled</span>";
                } else {
                    $status = "<span class='context-status-badge muted'><i class='fa fa-times'></i> Disabled</span>";
                }
                $displayValue = $value ?? '-';
            } elseif($type === 'custom') {
                // Custom check for integration files
                if($customCheck) {
                    $status = "<span class='context-status-badge success'><i class='fa fa-check'></i> Created</span>";
                } else {
                    $status = "<span class='context-status-badge muted'><i class='fa fa-times'></i> Not Created</span>";
                }
                $displayValue = $value;
            } else {
                // number type
                $status = '-';
                $displayValue = $value;
            }
            
            $out .= "<tr><td><strong>{$label}</strong></td><td>{$status}</td><td>{$displayValue}</td></tr>";
        }
        
        $out .= "</tbody></table>";
        
        $out .= "<div style='padding: 16px 24px; background: #f9fafb; border-top: 1px solid #e5e7eb;'>";
        $out .= "<a href='../module/edit?name=Context&collapse_info=1' style='color: #10b981; text-decoration: none; font-size: 13px; font-weight: 600; display: inline-flex; align-items: center; gap: 6px;'>";
        $out .= "<i class='fa fa-cog'></i> Edit configuration";
        $out .= "</a>";
        $out .= "</div>";
        
        $out .= "</div></div>";
        
        // What will be exported
        $out .= "<div style='background: white; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); margin-bottom: 24px; border: 1px solid #e5e5e5;'>";
        $out .= "<div style='padding: 20px 24px; border-bottom: 1px solid #f3f4f6;'>";
        $out .= "<h3 class='context-section-title'><i class='fa fa-list'></i> What Will Be Exported?</h3>";
        $out .= "</div>";
        $out .= "<div style='padding: 0;'>";
        
        // Single table for all exports
        $out .= "<table class='context-config-table'>";
        $out .= "<thead><tr><th style='width: 40%;'>FILE / FOLDER</th><th>TYPE</th><th>DESCRIPTION</th></tr></thead>";
        $out .= "<tbody>";
        
        // Core Structure (always exported)
        $coreFiles = [
            ['structure.json', 'File', 'Complete page tree (JSON)'],
            ['structure.txt', 'File', 'ASCII visualization'],
            ['templates.json', 'File', 'Templates & fields (JSON)'],
            ['templates.csv', 'File', 'Templates in CSV format'],
            ['config.json', 'File', 'Site configuration (JSON)'],
            ['modules.json', 'File', 'Installed modules (JSON)'],
            ['classes.json', 'File', 'Custom page classes (JSON)'],
            ['README.md', 'File', 'Documentation']
        ];
        
        // Check if ProFields Repeater Matrix is installed
        $hasMatrixFields = false;
        foreach($this->fields as $field) {
            if($field->type->className() === 'FieldtypeRepeaterMatrix') {
                $hasMatrixFields = true;
                break;
            }
        }
        
        // Add matrix-templates if ProFields Matrix is used
        if($hasMatrixFields) {
            array_splice($coreFiles, 4, 0, [['matrix-templates.json', 'File', 'Repeater Matrix types (ProFields)']]);
            if($this->export_toon_format) {
                array_splice($coreFiles, 5, 0, [['matrix-templates.toon', 'File', 'Repeater Matrix types (TOON)']]);
            }
        }
        
        // Add TOON files if enabled
        if($this->export_toon_format) {
            $toonFiles = [
                ['structure.toon', 'File', 'Complete page tree (TOON - 43% smaller)'],
                ['templates.toon', 'File', 'Templates & fields (TOON - 50% smaller)'],
                ['config.toon', 'File', 'Site configuration (TOON)'],
                ['modules.toon', 'File', 'Installed modules (TOON)'],
                ['classes.toon', 'File', 'Custom page classes (TOON)']
            ];
            // Insert after first JSON file
            array_splice($coreFiles, 1, 0, [$toonFiles[0]]); // structure.toon
            array_splice($coreFiles, 4, 0, [$toonFiles[1]]); // templates.toon
            array_splice($coreFiles, 7, 0, [$toonFiles[2]]); // config.toon
            array_splice($coreFiles, 10, 0, [$toonFiles[3]]); // modules.toon
            array_splice($coreFiles, 13, 0, [$toonFiles[4]]); // classes.toon
        }
        
        foreach($coreFiles as list($name, $type, $desc)) {
            $icon = $type === 'Folder' ? 'fa-folder' : 'fa-file-text-o';
            $out .= "<tr>";
            $out .= "<td><i class='fa {$icon}'></i> <strong>{$name}</strong></td>";
            $out .= "<td><span style='color: #10b981; font-weight: 600;'>Core</span></td>";
            $out .= "<td>{$desc}</td>";
            $out .= "</tr>";
        }
        
        // Optional/Enhanced Features (conditional)
        $optionalFiles = [
            ['samples/', 'Folder', 'Content examples', 'export_samples'],
            ['api/', 'Folder', 'API documentation', 'export_api_docs'],
            ['metadata/field-definitions.json', 'File', 'Field definitions', 'export_field_definitions'],
            ['metadata/routes.json', 'File', 'URL routes map', 'export_routes'],
            ['metadata/performance.json', 'File', 'Performance metrics', 'export_performance'],
            ['snippets/', 'Folder', 'Code library', 'export_snippets'],
            ['prompts/', 'Folder', 'AI prompts', 'export_prompts']
        ];
        
        foreach($optionalFiles as list($name, $type, $desc, $setting)) {
            if($this->$setting) {
                $icon = $type === 'Folder' ? 'fa-folder' : 'fa-file-text-o';
                $out .= "<tr>";
                $out .= "<td><i class='fa {$icon}'></i> <strong>{$name}</strong></td>";
                $out .= "<td><span style='color: #f59e0b; font-weight: 600;'>Optional</span></td>";
                $out .= "<td>{$desc}</td>";
                $out .= "</tr>";
            }
        }
        
        $out .= "</tbody></table>";
        
        $out .= "</div></div>";
        
        // Quick Tips
        $out .= "<div style='background: white; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); border: 1px solid #e5e5e5;'>";
        $out .= "<div style='padding: 20px 24px; border-bottom: 1px solid #f3f4f6;'>";
        $out .= "<h3 class='context-section-title'><i class='fa fa-info-circle'></i> Quick Tips</h3>";
        $out .= "</div>";
        $out .= "<div style='padding: 20px 24px;'>";
        
        $out .= "<div class='context-tips-grid'>";
        
        $tips = [];
        
        // TOON-specific tips if enabled
        if($this->export_toon_format) {
            $tips[] = ['fa-magic', '<strong>NEW!</strong> Upload <code>.toon</code> files to AI instead of <code>.json</code> - saves 30-60% tokens!'];
            $tips[] = ['fa-money', 'Use TOON format to reduce AI API costs significantly'];
        }
        
        // Standard tips
        $tips[] = ['fa-lightbulb-o', 'Upload <code>prompts/project-context.md</code> first when starting with AI'];
        
        if($this->export_toon_format) {
            $tips[] = ['fa-list', 'Include <code>templates.toon</code> for field questions (50% fewer tokens)'];
            $tips[] = ['fa-files-o', 'Share <code>samples/*.toon</code> to show AI real data (46% smaller)'];
        } else {
            $tips[] = ['fa-list', 'Include <code>templates.json</code> for field-related questions'];
            $tips[] = ['fa-files-o', 'Share <code>samples/</code> to show AI real data formats'];
        }
        
        $tips[] = ['fa-code', 'Use <code>snippets/</code> for code examples and patterns'];
        $tips[] = ['fa-refresh', 'Re-export after making structural changes'];
        $tips[] = ['fa-book', 'Check <code>README.md</code> for complete documentation'];
        
        foreach($tips as list($icon, $text)) {
            $out .= "<div class='context-tip-item'>";
            $out .= "<div style='color: #10b981;'><i class='fa {$icon} fa-lg'></i></div>";
            $out .= "<div>{$text}</div>";
            $out .= "</div>";
        }
        
        $out .= "</div>";
        
        $out .= "</div></div>";
        
        // Footer
        $out .= "<div style='text-align: center; margin-top: 32px; padding: 24px; color: #9ca3af; font-size: 13px;'>";
        $out .= "Context Module v" . self::getModuleInfo()['version'];
        $out .= "</div>";
        
        return $out;
    }

    /**
     * Get folder size
     */
    protected function getFolderSize($path) {
        $size = 0;
        
        if(!is_dir($path)) return 0;
        
        try {
            foreach(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)) as $file) {
                if($file->isFile()) {
                    $size += $file->getSize();
                }
            }
        } catch(\Exception $e) {
            // If unable to read directory, return 0
            return 0;
        }
        
        return $size;
    }

    /**
     * Format bytes
     */
    protected function formatBytes($bytes, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    /**
     * Helper for array imploding
     */
    protected function implodeArray($arr) {
        return implode("\n", $arr);
    }

    /**
     * ========================================================================
     * TOON FORMAT CONVERTER
     * Token-Oriented Object Notation for AI assistants
     * Reduces token consumption by 30-60% compared to JSON
     * ========================================================================
     */

    /**
     * Convert PHP array to TOON format
     */
    protected function convertToToon($data) {
        return $this->toToonRecursive($data, 0);
    }

    /**
     * Recursive TOON generation
     */
    protected function toToonRecursive($data, $level = 0) {
        $indent = str_repeat('  ', $level);
        $output = '';
        
        // Handle indexed arrays (list of items)
        if(isset($data[0]) && is_array($data)) {
            // Check if this is a uniform array of objects (table format)
            if($this->isTableFormat($data)) {
                // Return table format without key (will be added by parent)
                return $this->formatTableData($data, $level);
            } else {
                // List of non-uniform objects - output each one
                foreach($data as $index => $item) {
                    if(is_array($item)) {
                        $output .= $indent . "- # item $index\n";
                        foreach($item as $key => $value) {
                            if(is_array($value) && !empty($value)) {
                                if($this->isTableFormat($value)) {
                                    $output .= $this->formatAsTable($key, $value, $level + 1);
                                } else {
                                    $output .= $indent . "  " . $this->escapeKey($key) . ":\n";
                                    $output .= $this->toToonRecursive($value, $level + 2);
                                }
                            } else {
                                $val = $this->formatSimpleValue($value);
                                $output .= $indent . "  " . $this->escapeKey($key) . ": " . $val . "\n";
                            }
                        }
                    } else {
                        $output .= $indent . "- " . $this->formatSimpleValue($item) . "\n";
                    }
                }
                return $output;
            }
        }
        
        // Handle associative arrays (objects)
        foreach($data as $key => $value) {
            
            if(is_array($value) && !empty($value)) {
                
                // Check if this is a uniform array of objects (table format)
                if($this->isTableFormat($value)) {
                    // Tabular format - biggest token savings!
                    $output .= $this->formatAsTable($key, $value, $level);
                } 
                else {
                    // Nested object/array
                    $output .= $indent . $this->escapeKey($key) . ":\n";
                    $output .= $this->toToonRecursive($value, $level + 1);
                }
                
            } else {
                // Simple key-value pair
                $val = $this->formatSimpleValue($value);
                $output .= $indent . $this->escapeKey($key) . ": " . $val . "\n";
            }
        }
        
        return $output;
    }

    /**
     * Check if array can be formatted as TOON table
     */
    protected function isTableFormat($array) {
        // Must be indexed array
        if(!isset($array[0])) return false;
        
        // First element must be array
        if(!is_array($array[0])) return false;
        
        // Get keys from first element
        $firstKeys = array_keys($array[0]);
        if(empty($firstKeys)) return false;
        
        // Check all elements have same keys
        foreach($array as $item) {
            if(!is_array($item)) return false;
            if(array_keys($item) != $firstKeys) return false;
        }
        
        return true;
    }

    /**
     * Format array data as TOON table (without key)
     */
    protected function formatTableData($array, $level = 0) {
        $indent = str_repeat('  ', $level);
        $count = count($array);
        $fields = array_keys($array[0]);
        
        // Header: [count]{field1,field2,...}:
        $output = $indent . "[{$count}]{" . implode(',', array_map([$this, 'escapeKey'], $fields)) . "}:\n";
        
        // Rows: value1,value2,...
        foreach($array as $row) {
            $values = [];
            foreach($fields as $field) {
                $val = $row[$field];
                // Handle nested arrays in table cells
                if(is_array($val)) {
                    $values[] = json_encode($val, JSON_UNESCAPED_UNICODE);
                } else {
                    $values[] = $this->formatSimpleValue($val);
                }
            }
            $output .= $indent . implode(',', $values) . "\n";
        }
        
        return $output;
    }

    /**
     * Format array as TOON table (with key)
     */
    protected function formatAsTable($key, $array, $level = 0) {
        $indent = str_repeat('  ', $level);
        $count = count($array);
        $fields = array_keys($array[0]);
        
        // Header: key[count]{field1,field2,...}:
        $output = $indent . $this->escapeKey($key) . "[{$count}]{" . implode(',', array_map([$this, 'escapeKey'], $fields)) . "}:\n";
        
        // Rows: value1,value2,...
        foreach($array as $row) {
            $values = [];
            foreach($fields as $field) {
                $val = $row[$field];
                // Handle nested arrays in table cells
                if(is_array($val)) {
                    $values[] = json_encode($val, JSON_UNESCAPED_UNICODE);
                } else {
                    $values[] = $this->formatSimpleValue($val);
                }
            }
            $output .= $indent . implode(',', $values) . "\n";
        }
        
        return $output;
    }

    /**
     * Escape TOON key if needed
     */
    protected function escapeKey($key) {
        // Keys with special chars need quotes
        if(preg_match('/^[A-Za-z_][\w.]*$/', $key)) {
            return $key;
        }
        return '"' . addslashes($key) . '"';
    }

    /**
     * Format value for TOON
     */
    /**
     * Format simple (non-array) value for TOON
     */
    protected function formatSimpleValue($value) {
        // Null
        if(is_null($value)) return 'null';
        
        // Boolean
        if(is_bool($value)) return $value ? 'true' : 'false';
        
        // Number
        if(is_numeric($value)) return $value;
        
        // String
        $value = (string)$value;
        
        // Check if needs quoting
        $needsQuotes = (
            strpos($value, ',') !== false ||
            strpos($value, ':') !== false ||
            strpos($value, "\n") !== false ||
            strpos($value, "\r") !== false ||
            strpos($value, "\t") !== false ||
            trim($value) !== $value ||
            $value === '' ||
            in_array(strtolower($value), ['true', 'false', 'null'])
        );
        
        if($needsQuotes) {
            // Escape special characters
            $value = str_replace(['\\', '"', "\n", "\r", "\t"], ['\\\\', '\\"', '\\n', '\\r', '\\t'], $value);
            return '"' . $value . '"';
        }
        
        return $value;
    }

    /**
     * ========================================================================
     * END OF TOON FORMAT METHODS
     * ========================================================================
     */

    /**
     * Module settings page
     */
    public static function getModuleConfigInputfields(array $data) {
        $modules = wire('modules');
        $inputfields = new InputfieldWrapper();

        $data = array_merge(self::$configDefaults, $data);

        // Site Type Selection
        $fieldset = $modules->get('InputfieldFieldset');
        $fieldset->label = 'Site Type';
        $fieldset->collapsed = Inputfield::collapsedNo;

        $f = $modules->get('InputfieldSelect');
        $f->name = 'site_type';
        $f->label = 'What type of site are you building?';
        $f->description = 'This determines which code examples and snippets will be generated';
        
        // Add options with descriptions
        $f->addOption('generic', 'Generic / Mixed Content — General purpose site with various content types');
        $f->addOption('blog', 'Blog / News / Magazine — Sites with articles, posts, authors, categories');
        $f->addOption('ecommerce', 'E-commerce / Online Store — Products, shopping cart, orders, inventory');
        $f->addOption('business', 'Business / Portfolio / Agency — Services, team, projects, case studies');
        $f->addOption('catalog', 'Catalog / Directory / Listings — Brands, categories, hierarchical data');
        
        $f->value = $data['site_type'];
        $fieldset->add($f);

        $inputfields->add($fieldset);

        // Export Formats
        $fieldset = $modules->get('InputfieldFieldset');
        $fieldset->label = 'Export Formats';
        $fieldset->description = 'Choose which file formats to generate';
        $fieldset->collapsed = Inputfield::collapsedNo;

        $f = $modules->get('InputfieldCheckbox');
        $f->name = 'export_toon_format';
        $f->label = 'Export TOON Format (AI-Optimized)';
        $f->description = 'Generate .toon files alongside .json for AI assistants';
        $f->notes = '**TOON format uses 30-60% fewer tokens** than JSON when uploaded to Claude, ChatGPT, or other LLMs. Highly recommended for AI development!';
        $f->icon = 'magic';
        $f->checked = $data['export_toon_format'] ? 'checked' : '';
        $fieldset->add($f);

        $inputfields->add($fieldset);

        // Samples
        $fieldset = $modules->get('InputfieldFieldset');
        $fieldset->label = 'Content Sampling';
        $fieldset->collapsed = Inputfield::collapsedNo;

        $f = $modules->get('InputfieldCheckbox');
        $f->name = 'export_samples';
        $f->label = 'Export Content Samples';
        $f->description = 'Export real page examples for each template';
        $f->notes = 'Creates: **samples/** folder with JSON files for each template';
        $f->checked = $data['export_samples'] ? 'checked' : '';
        $fieldset->add($f);

        $f = $modules->get('InputfieldInteger');
        $f->name = 'samples_count';
        $f->label = 'Samples Per Template';
        $f->description = 'Number of sample pages to export per template';
        $f->value = $data['samples_count'];
        $f->min = 1;
        $f->max = 10;
        $f->showIf = 'export_samples=1';
        $fieldset->add($f);

        $inputfields->add($fieldset);

        // API Documentation
        $fieldset = $modules->get('InputfieldFieldset');
        $fieldset->label = 'API Documentation';
        $fieldset->collapsed = Inputfield::collapsedNo;

        $f = $modules->get('InputfieldCheckbox');
        $f->name = 'export_api_docs';
        $f->label = 'Generate API Documentation';
        $f->description = 'Create JSON schemas and endpoint documentation';
        $f->notes = 'Creates: **api/** folder with JSON schemas';
        $f->checked = $data['export_api_docs'] ? 'checked' : '';
        $fieldset->add($f);

        $inputfields->add($fieldset);

        // Additional Features
        $fieldset = $modules->get('InputfieldFieldset');
        $fieldset->label = 'Additional Features';
        $fieldset->collapsed = Inputfield::collapsedNo;

        $f = $modules->get('InputfieldCheckbox');
        $f->name = 'export_field_definitions';
        $f->label = 'Export Detailed Field Definitions';
        $f->description = 'Include in-depth information about custom fields (Table, Repeater, etc.)';
        $f->notes = 'Creates: **metadata/field-definitions.json**';
        $f->checked = $data['export_field_definitions'] ? 'checked' : '';
        $fieldset->add($f);

        $f = $modules->get('InputfieldCheckbox');
        $f->name = 'export_routes';
        $f->label = 'Export URL Routes';
        $f->description = 'Create URL routing structure map';
        $f->notes = 'Creates: **metadata/routes.json**';
        $f->checked = $data['export_routes'] ? 'checked' : '';
        $fieldset->add($f);

        $f = $modules->get('InputfieldCheckbox');
        $f->name = 'export_performance';
        $f->label = 'Export Performance Metrics';
        $f->description = 'Include page counts, database size, and system limits';
        $f->notes = 'Creates: **metadata/performance.json**';
        $f->checked = $data['export_performance'] ? 'checked' : '';
        $fieldset->add($f);

        $f = $modules->get('InputfieldCheckbox');
        $f->name = 'export_snippets';
        $f->label = 'Create Code Snippets';
        $f->description = 'Generate PHP code examples and patterns';
        $f->notes = 'Creates: **snippets/** folder with .php files';
        $f->checked = $data['export_snippets'] ? 'checked' : '';
        $fieldset->add($f);

        $f = $modules->get('InputfieldCheckbox');
        $f->name = 'export_prompts';
        $f->label = 'Create AI Prompts';
        $f->description = 'Generate ready-to-use AI prompts for common tasks';
        $f->notes = 'Creates: **prompts/** folder with project-context.md';
        $f->checked = $data['export_prompts'] ? 'checked' : '';
        $fieldset->add($f);

        $inputfields->add($fieldset);

        // Advanced Settings
        $fieldset = $modules->get('InputfieldFieldset');
        $fieldset->label = 'Advanced Settings';
        $fieldset->collapsed = Inputfield::collapsedYes;

        $f = $modules->get('InputfieldInteger');
        $f->name = 'max_depth';
        $f->label = 'Maximum Tree Depth';
        $f->description = 'Maximum depth for page tree export';
        $f->value = $data['max_depth'];
        $f->min = 3;
        $f->max = 20;
        $fieldset->add($f);

        $f = $modules->get('InputfieldInteger');
        $f->name = 'json_child_limit';
        $f->label = 'JSON Children Limit';
        $f->description = 'Maximum children per page in structure.json to reduce file size';
        $f->notes = 'Lists with more items will be collapsed or show first N items. Does not affect structure.txt';
        $f->value = $data['json_child_limit'];
        $f->min = 5;
        $f->max = 100;
        $fieldset->add($f);

        $f = $modules->get('InputfieldCheckbox');
        $f->name = 'compact_mode';
        $f->label = 'Compact Mode';
        $f->description = 'Collapse large homogeneous lists in structure.txt for better readability';
        $f->notes = 'When disabled: shows up to 50 items. When enabled: shows up to 30 items. Lists exceeding the limit will show as "include N elements [template: name]"';
        $f->checked = $data['compact_mode'] ? 'checked' : '';
        $fieldset->add($f);

        $f = $modules->get('InputfieldCheckbox');
        $f->name = 'auto_update';
        $f->label = 'Auto-Update on Changes';
        $f->description = 'Automatically update templates.json when templates/fields are modified';
        $f->notes = 'Warning: May impact performance on large sites';
        $f->checked = $data['auto_update'] ? 'checked' : '';
        $fieldset->add($f);

        $f = $modules->get('InputfieldCheckbox');
        $f->name = 'export_integrations';
        $f->label = 'Create IDE Integration Files';
        $f->description = 'Generate configuration files for Cursor, Claude Code, and other AI IDEs';
        $f->notes = 'Creates: **.cursorrules** and **.claudecode.json** in project root';
        $f->checked = $data['export_integrations'] ? 'checked' : '';
        $fieldset->add($f);

        $f = $modules->get('InputfieldTextarea');
        $f->name = 'custom_ai_instructions';
        $f->label = 'Custom AI Instructions';
        $f->description = 'Additional instructions to append to project-context.md';
        $f->notes = 'Appends to: **prompts/project-context.md** | Example: "Always use BEM methodology"';
        $f->value = $data['custom_ai_instructions'];
        $f->rows = 3;
        $fieldset->add($f);

        $inputfields->add($fieldset);

        return $inputfields;
    }}