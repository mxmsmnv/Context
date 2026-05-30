<?php namespace ProcessWire;

/**
 * Exports API endpoint metadata and JSON schemas.
 */
class ContextApiExporter {

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

    public function exportApiDocs() {
        $apiPath = $this->invoke('ensureFolder', $this->module->getContextPath() . 'api/');
        $schemasPath = $this->invoke('ensureFolder', $apiPath . 'schemas/');

        $endpoints = [];
        $allSchemas = [];

        foreach($this->module->templates as $template) {
            if($template->flags & Template::flagSystem) continue;

            $schema = [
                '$schema' => 'http://json-schema.org/draft-07/schema#',
                'title' => $template->label ?: $template->name,
                'type' => 'object',
                'properties' => []
            ];

            $required = [];

            foreach($template->fields as $field) {
                $property = $this->buildJsonSchemaProperty($field);

                if($field->notes) {
                    $property['notes'] = $field->notes;
                }

                if($field->type->className() === 'FieldtypeRepeater') {
                    $property['type'] = 'array';
                    $property['description'] .= ' (Repeater field)';
                    $repeaterTemplate = $this->module->templates->get("repeater_" . $field->name);
                    if($repeaterTemplate) {
                        $property['items'] = ['type' => 'object', 'properties' => []];
                        foreach($repeaterTemplate->fields as $repField) {
                            $property['items']['properties'][$repField->name] = $this->buildJsonSchemaProperty($repField);
                        }
                    }
                } elseif($field->type->className() === 'FieldtypeTable') {
                    $property['type'] = 'array';
                    $property['description'] .= ' (Table field)';
                    $cols = $this->invoke('getTableColumns', $field);
                    if(!empty($cols)) {
                        $property['items'] = ['type' => 'object', 'properties' => []];
                        foreach($cols as $col) {
                            $property['items']['properties'][$col['name']] = [
                                'type' => $this->getJsonSchemaTypeFromName($col['type'] ?? 'text'),
                                'description' => $col['label']
                            ];
                        }
                    }
                } elseif($field->type->className() === 'FieldtypeRepeaterMatrix') {
                    $property['type'] = 'array';
                    $property['description'] .= ' (RepeaterMatrix field)';
                    $property['items'] = ['oneOf' => []];
                    foreach($this->invoke('getMatrixTypesData', $field) as $typeInfo) {
                        $matrixSchema = [
                            'type' => 'object',
                            'properties' => [
                                'type' => [
                                    'type' => 'string',
                                    'const' => $typeInfo['name'],
                                    'description' => $typeInfo['label']
                                ]
                            ],
                            'required' => ['type']
                        ];
                        foreach($typeInfo['fields'] as $mf) {
                            $matrixSchema['properties'][$mf['name']] = [
                                'type' => $this->getJsonSchemaTypeFromName($mf['type'] ?? 'text'),
                                'description' => $mf['label'] ?? $mf['name']
                            ];
                        }
                        $property['items']['oneOf'][] = $matrixSchema;
                    }
                } elseif($field->type->className() === 'FieldtypeCombo') {
                    $property['type'] = 'object';
                    $property['description'] .= ' (Combo field)';
                    $subs = $this->invoke('getComboSubfields', $field);
                    if(!empty($subs)) {
                        $property['properties'] = [];
                        foreach($subs as $sub) {
                            $property['properties'][$sub['name']] = [
                                'type' => $this->getJsonSchemaTypeFromName($sub['type'] ?? 'text'),
                                'description' => $sub['label']
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
                ],
                'File' => [
                    'type' => 'object',
                    'properties' => [
                        'url' => ['type' => 'string'],
                        'basename' => ['type' => 'string'],
                        'filesize' => ['type' => 'integer'],
                        'description' => ['type' => 'string']
                    ]
                ]
            ];

            $allSchemas[$template->name] = $schema;
            $this->invoke('writeJsonFile', $schemasPath . "{$template->name}-schema.json", $schema, JSON_PRETTY_PRINT);

            $samplePage = $this->module->pages->get("template={$template->name}");
            if($samplePage->id) {
                $endpoints[] = [
                    'path' => "/api/{$template->name}/{id}",
                    'method' => 'GET',
                    'description' => "Get single {$template->label}",
                    'response_schema' => "{$template->name}-schema.json"
                ];

                if($samplePage->numChildren > 0 || $this->module->pages->count("template={$template->name}") > 1) {
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

        $this->invoke('writeJsonFile', $apiPath . 'endpoints.json', $endpoints, JSON_PRETTY_PRINT);
        $this->invoke('writeJsonFile', $apiPath . 'all-schemas.json', $allSchemas);

        return ['endpoints' => $endpoints, 'schemas' => $allSchemas];
    }

    public function getJsonSchemaType($field) {
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
            'FieldtypeOptions' => 'string',
            'FieldtypeRepeater' => 'array',
            'FieldtypeRepeaterMatrix' => 'array',
            'FieldtypeTable' => 'array',
            'FieldtypeCombo' => 'object',
        ];

        return $typeMap[$className] ?? 'string';
    }

    public function buildJsonSchemaProperty(Field $field) {
        $property = [
            'type' => $this->getJsonSchemaType($field),
            'description' => $field->label ?: $field->name,
        ];

        if($field->type instanceof FieldtypePage) {
            $property['$ref'] = '#/definitions/PageReference';
        } elseif($field->type instanceof FieldtypeImage) {
            $property['type'] = 'array';
            $property['items'] = ['$ref' => '#/definitions/Image'];
        } elseif($field->type instanceof FieldtypeFile) {
            $property['type'] = 'array';
            $property['items'] = ['$ref' => '#/definitions/File'];
        } elseif($field->type instanceof FieldtypeOptions) {
            $property['type'] = 'string';
        }

        return $property;
    }

    public function getJsonSchemaTypeFromName($typeName) {
        $typeName = strtolower((string)$typeName);

        if(strpos($typeName, 'int') !== false) return 'integer';
        if(strpos($typeName, 'float') !== false || strpos($typeName, 'decimal') !== false) return 'number';
        if(strpos($typeName, 'bool') !== false || strpos($typeName, 'checkbox') !== false) return 'boolean';
        if(strpos($typeName, 'date') !== false || strpos($typeName, 'time') !== false) return 'string';
        if(strpos($typeName, 'page') !== false) return 'object';
        if(strpos($typeName, 'image') !== false || strpos($typeName, 'file') !== false || strpos($typeName, 'repeater') !== false) return 'array';
        if(strpos($typeName, 'combo') !== false) return 'object';

        return 'string';
    }

    protected function invoke($method, ...$args) {
        return ($this->call)($method, ...$args);
    }
}
