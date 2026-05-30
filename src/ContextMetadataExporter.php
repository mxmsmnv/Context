<?php namespace ProcessWire;

/**
 * Exports metadata files: field definitions, routes, and performance metrics.
 */
class ContextMetadataExporter {

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

    public function exportFieldDefinitions() {
        $metadataPath = $this->invoke('ensureFolder', $this->module->getContextPath() . 'metadata/');
        $definitions = [
            'custom_fields' => [],
            'field_types' => []
        ];

        foreach($this->module->fields as $field) {
            $className = $field->type->className();

            if(!isset($definitions['field_types'][$className])) {
                $label = $className;
                if(method_exists($field->type, 'getModuleInfo')) {
                    $moduleInfo = $field->type->getModuleInfo();
                    $label = $moduleInfo['title'] ?? $className;
                }

                $definitions['field_types'][$className] = [
                    'class' => $className,
                    'label' => $label,
                    'usage_count' => 0,
                    'examples' => []
                ];
            }

            $definitions['field_types'][$className]['usage_count']++;
            $definitions['field_types'][$className]['examples'][] = $field->name;

            if($className === 'FieldtypeTable') {
                $colsList = [];
                foreach($this->invoke('getTableColumns', $field) as $col) {
                    $colsList[] = [
                        'name' => $col['name'],
                        'label' => $col['label'],
                        'type' => $col['type'],
                        'purpose' => $col['label'],
                    ];
                }
                $definitions['custom_fields'][$field->name] = [
                    'name' => $field->name,
                    'label' => $field->label,
                    'type' => 'Table',
                    'columns' => $colsList,
                    'usage_example' => $field->notes ?: "Table field with structured data",
                ];
            }

            if($className === 'FieldtypeRepeater') {
                $repeaterTemplate = $this->module->templates->get("repeater_" . $field->name);
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

            if($className === 'FieldtypeRepeaterMatrix') {
                $matrixTypesList = [];
                foreach($this->invoke('getMatrixTypesData', $field) as $typeInfo) {
                    $mfList = [];
                    foreach($typeInfo['fields'] as $mf) {
                        $mfList[] = [
                            'name' => $mf['name'],
                            'type' => $mf['type'],
                            'label' => $mf['label'] ?? $mf['name'],
                            'purpose' => (!empty($mf['description']) ? $mf['description'] : ($mf['label'] ?? $mf['name'])),
                        ];
                    }
                    $matrixTypesList[] = [
                        'name' => $typeInfo['name'],
                        'label' => $typeInfo['label'],
                        'head' => $typeInfo['head'],
                        'fields' => $mfList,
                    ];
                }
                $definitions['custom_fields'][$field->name] = [
                    'name' => $field->name,
                    'label' => $field->label,
                    'type' => 'RepeaterMatrix',
                    'matrix_types' => $matrixTypesList,
                    'usage_example' => $field->notes ?: "Matrix of different repeater types",
                ];
            }

            if($className === 'FieldtypeCombo') {
                $subsList = [];
                foreach($this->invoke('getComboSubfields', $field) as $sub) {
                    $subsList[] = [
                        'name' => $sub['name'],
                        'type' => $sub['type'],
                        'label' => $sub['label'],
                        'purpose' => (!empty($sub['description']) ? $sub['description'] : $sub['label']),
                    ];
                }
                $definitions['custom_fields'][$field->name] = [
                    'name' => $field->name,
                    'label' => $field->label,
                    'type' => 'Combo',
                    'subfields' => $subsList,
                    'usage_example' => $field->notes ?: "Combined set of subfields",
                ];
            }
        }

        $this->invoke('writeJsonFile', $metadataPath . 'field-definitions.json', $definitions);

        return $definitions;
    }

    public function exportRoutes() {
        $metadataPath = $this->invoke('ensureFolder', $this->module->getContextPath() . 'metadata/');
        $routes = [];

        foreach($this->module->templates as $template) {
            if($template->flags & Template::flagSystem) continue;

            $samplePage = $this->module->pages->get("template={$template->name}");
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

        $this->invoke('writeJsonFile', $metadataPath . 'routes.json', $routes);

        return $routes;
    }

    public function getRouteMap() {
        $routeMap = [];
        $templatesPath = $this->module->config->paths->templates;

        foreach($this->module->templates as $tmpl) {
            if($tmpl->flags & Template::flagSystem) continue;

            $file = $templatesPath . $tmpl->name . '.php';
            if(!file_exists($file)) continue;

            $content = $this->invoke('readFile', $file);
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

    public function exportPerformance() {
        $metadataPath = $this->invoke('ensureFolder', $this->module->getContextPath() . 'metadata/');

        $templateCounts = [];
        $totalPages = 0;
        $totalNonSystemTemplates = 0;

        foreach($this->module->templates as $template) {
            if($template->flags & Template::flagSystem) continue;
            $totalNonSystemTemplates++;

            $count = $this->module->pages->count("template={$template->name}");
            if($count > 0) {
                $templateCounts[] = [
                    'template' => $template->name,
                    'label' => $template->label ?: $template->name,
                    'count' => $count
                ];
                $totalPages += $count;
            }
        }

        usort($templateCounts, function($a, $b) {
            return $b['count'] - $a['count'];
        });

        $dbSize = 'N/A';
        try {
            $dbName = $this->module->config->dbName;
            $result = $this->module->database->query("SELECT 
                ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb 
                FROM information_schema.TABLES 
                WHERE table_schema = '{$dbName}'")->fetch();
            $dbSize = $result['size_mb'] . ' MB';
        } catch(\Exception $e) {
            // Keep N/A when the database account cannot read information_schema.
        }

        $performance = [
            'total_pages' => $totalPages,
            'total_templates' => $totalNonSystemTemplates,
            'largest_templates' => array_slice($templateCounts, 0, 10),
            'database_size' => $dbSize,
            'processwire_version' => $this->module->config->version,
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

        $this->invoke('writeJsonFile', $metadataPath . 'performance.json', $performance);

        return $performance;
    }

    protected function invoke($method, ...$args) {
        return ($this->call)($method, ...$args);
    }
}
