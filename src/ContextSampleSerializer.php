<?php namespace ProcessWire;

/**
 * Context Module - content sample serializer
 *
 * Normalizes ProcessWire field values for exported AI context samples.
 */
class ContextSampleSerializer {

    /** @var Context */
    protected $module;

    public function __construct(Context $module) {
        $this->module = $module;
    }

    public function isEmptyValue($value) {
        return empty($value) && $value !== '0' && $value !== 0;
    }

    public function serializeFieldValue(Field $field, $value) {
        if($field->type instanceof FieldtypePage) {
            return $this->serializePageReference($value);
        }

        if($field->type instanceof FieldtypeImage) {
            return $this->serializeFiles($value, true);
        }

        if($field->type instanceof FieldtypeFile) {
            return $this->serializeFiles($value, false);
        }

        if($field->type instanceof FieldtypeDatetime) {
            return $value ? date('Y-m-d H:i:s', $value) : null;
        }

        if($field->type instanceof FieldtypeOptions) {
            return $this->serializeOptions($value);
        }

        $className = $field->type->className();

        if($className === 'FieldtypeTable' || $className === 'FieldtypeCombo') {
            return $this->normalizeStructuredValue($value);
        }

        if($className === 'FieldtypeRepeater' || $className === 'FieldtypeRepeaterMatrix') {
            return $this->serializeRepeaterItems($field, $value);
        }

        return is_array($value) ? $value : (string) $value;
    }

    public function isSensitiveField(Field $field) {
        $className = $field->type->className();
        if(stripos($className, 'password') !== false) return true;

        $defaults = Context::getConfigDefaults();
        $denylist = $this->module->sample_field_denylist ?: $defaults['sample_field_denylist'];
        $tokens = array_filter(array_map('trim', explode(',', strtolower($denylist))));
        $haystack = strtolower($field->name . ' ' . $field->label . ' ' . $field->description . ' ' . $field->notes);

        foreach($tokens as $token) {
            if($token !== '' && strpos($haystack, $token) !== false) return true;
        }

        return false;
    }

    protected function serializePageReference($value) {
        if($value instanceof Page && $value->id) {
            return [
                'id' => $value->id,
                'title' => $value->title,
                'url' => $value->url
            ];
        }

        if($value instanceof PageArray) {
            $pages = [];
            foreach($value as $page) {
                $pages[] = [
                    'id' => $page->id,
                    'title' => $page->title,
                    'url' => $page->url
                ];
            }
            return $pages;
        }

        return null;
    }

    protected function serializeFiles($value, $includeDimensions = false) {
        $files = [];
        $items = is_iterable($value) ? $value : [$value];

        foreach($items as $file) {
            if(!$file) continue;
            $data = [
                'url' => $file->url,
                'basename' => $file->basename,
                'filesize' => $file->filesize,
                'description' => $file->description
            ];

            if($includeDimensions) {
                $data['width'] = $file->width;
                $data['height'] = $file->height;
            }

            $files[] = $data;
        }

        return $files;
    }

    protected function serializeOptions($value) {
        if($value instanceof SelectableOption) {
            return [
                'id' => $value->id,
                'value' => $value->value,
                'title' => $value->title
            ];
        }

        if($value instanceof SelectableOptionArray) {
            $options = [];
            foreach($value as $option) {
                $options[] = [
                    'id' => $option->id,
                    'value' => $option->value,
                    'title' => $option->title
                ];
            }
            return $options;
        }

        return is_array($value) ? $value : (string) $value;
    }

    protected function serializeRepeaterItems(Field $field, $value) {
        $items = [];
        $isMatrix = $field->type->className() === 'FieldtypeRepeaterMatrix';

        if(!is_iterable($value)) {
            return $items;
        }

        foreach($value as $repeaterItem) {
            $itemData = ['id' => $repeaterItem->id];

            if($isMatrix) {
                $matrixTypeName = $field->type->getMatrixTypeName((int)$repeaterItem->get('repeater_matrix_type'), $field);
                $itemData['type'] = $matrixTypeName ?: '';
                if($matrixTypeName) {
                    try {
                        $itemData['type_label'] = $field->type->getMatrixTypeLabel($matrixTypeName, $field);
                    } catch(\Exception $e) {
                        $itemData['type_label'] = $matrixTypeName;
                    }
                }
            }

            foreach($repeaterItem->fields as $repField) {
                if($this->isSensitiveField($repField)) continue;

                $repValue = $repeaterItem->get($repField->name);
                if($this->isEmptyValue($repValue)) continue;

                $itemData[$repField->name] = $this->serializeFieldValue($repField, $repValue);
            }

            $items[] = $itemData;
        }

        return $items;
    }

    protected function normalizeStructuredValue($value) {
        if(is_array($value)) return $value;

        $json = json_encode($value);
        if($json === false) return (string)$value;

        $decoded = json_decode($json, true);
        if(json_last_error() !== JSON_ERROR_NONE) return (string)$value;

        return $decoded;
    }
}
