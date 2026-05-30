<?php namespace ProcessWire;

/**
 * Builds site structure exports: page tree, ASCII tree, and template field tree.
 */
class ContextStructureExporter {

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

    public function buildPageTree(Page $page, $depth = 0, $maxDepth = 10) {
        if($depth > $maxDepth) return null;

        if($page->template && ($page->template->flags & Template::flagSystem)) {
            return null;
        }

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
            $childLimit = $this->module->json_child_limit ?: 20;

            if($numChildren > $childLimit && $depth >= 1) {
                $regularItems = [];
                $nestedItems = [];

                foreach($children as $child) {
                    if($child->template && ($child->template->flags & Template::flagSystem)) continue;

                    $childChildren = $child->children("include=all")->count();
                    if($childChildren > 0) {
                        $nestedItems[] = $child;
                    } else {
                        $regularItems[] = $child;
                    }
                }

                $templates = [];
                foreach(array_slice($regularItems, 0, 5) as $item) {
                    $templates[$item->template->name] = true;
                }
                $allSameTemplate = count($templates) === 1;

                $data['children'] = [];

                $shownCount = 0;
                foreach($regularItems as $item) {
                    if($shownCount >= $childLimit) break;
                    $childData = $this->buildPageTree($item, $depth + 1, $maxDepth);
                    if($childData) {
                        $data['children'][] = $childData;
                        $shownCount++;
                    }
                }

                foreach($nestedItems as $item) {
                    $childData = $this->buildPageTree($item, $depth + 1, $maxDepth);
                    if($childData) $data['children'][] = $childData;
                }

                $hiddenCount = count($regularItems) - $shownCount;
                if($hiddenCount > 0) {
                    $childTemplate = $allSameTemplate ? array_key_first($templates) : 'items';
                    $data['children_note'] = "Showing first {$childLimit} regular items + " . count($nestedItems) . " nested items. {$hiddenCount} more {$childTemplate} hidden (total: {$numChildren})";
                } elseif(count($nestedItems) > 0) {
                    $data['children_note'] = "Showing all " . count($regularItems) . " regular items + " . count($nestedItems) . " nested items";
                }
            } else {
                $data['children'] = [];
                foreach($children as $child) {
                    $childData = $this->buildPageTree($child, $depth + 1, $maxDepth);
                    if($childData) $data['children'][] = $childData;
                }
            }
        }

        return $data;
    }

    public function buildAsciiTree(Page $page, $depth = 0, $prefix = '', $isLast = true, $maxDepth = 10) {
        if($depth > $maxDepth) return '';

        if($page->template && ($page->template->flags & Template::flagSystem)) {
            return '';
        }

        $output = '';

        if($depth > 0) {
            $connector = $isLast ? '└─ ' : '├─ ';
            $output .= $prefix . $connector;
        }

        $children = $page->children("include=all");
        $childCount = $children->count();

        $itemCount = $childCount > 0 ? " (items: {$childCount})" : '';
        $output .= "{$page->title} [template: {$page->template->name}]{$itemCount}\n";

        if($childCount > 0) {
            $threshold = $this->module->compact_mode ? 30 : 50;
            $alwaysExpandTemplates = ['category'];

            $newPrefix = $prefix;
            if($depth > 0) {
                $newPrefix .= $isLast ? '    ' : '│   ';
            }

            $templates = [];
            foreach($children as $child) {
                if($child->template && ($child->template->flags & Template::flagSystem)) continue;
                $templates[$child->template->name] = true;
            }

            $allSameTemplate = count($templates) === 1;
            $childTemplate = $allSameTemplate ? array_key_first($templates) : '';
            $shouldAlwaysExpand = in_array($childTemplate, $alwaysExpandTemplates);

            if($depth >= 1 && $allSameTemplate && !$shouldAlwaysExpand && $childCount > $threshold) {
                $itemsWithChildren = [];
                $regularItems = [];

                foreach($children as $index => $child) {
                    if($child->template && ($child->template->flags & Template::flagSystem)) continue;

                    if($child->children("include=all")->count() > 0) {
                        $itemsWithChildren[] = ['index' => $index, 'page' => $child];
                    } else {
                        $regularItems[] = ['index' => $index, 'page' => $child];
                    }
                }

                $shownCount = 0;
                foreach($regularItems as $item) {
                    if($shownCount >= $threshold) break;
                    $output .= $this->buildAsciiTree($item['page'], $depth + 1, $newPrefix, false, $maxDepth);
                    $shownCount++;
                }

                foreach($itemsWithChildren as $item) {
                    $output .= $this->buildAsciiTree($item['page'], $depth + 1, $newPrefix, false, $maxDepth);
                }

                $hiddenCount = count($regularItems) - $shownCount;
                if($hiddenCount > 0) {
                    $output .= $newPrefix . '└─ ';
                    $output .= "and {$hiddenCount} more {$childTemplate} elements...\n";
                }
            } else {
                foreach($children as $index => $child) {
                    $isLastChild = ($index === $childCount - 1);
                    $output .= $this->buildAsciiTree($child, $depth + 1, $newPrefix, $isLastChild, $maxDepth);
                }
            }
        }

        return $output;
    }

    public function exportTree() {
        $tree = [];

        $this->module->log("Building site tree (templates + fields)...");

        foreach($this->module->templates as $template) {
            if($template->flags & Template::flagSystem) continue;

            $templateData = [
                'name' => $template->name,
                'label' => $template->label ?: $template->name,
                'fields' => []
            ];

            foreach($template->fields as $field) {
                $fieldData = [
                    'name' => $field->name,
                    'type' => $field->type->className(),
                    'label' => $field->label
                ];

                if($field->type instanceof FieldtypePage) {
                    if($field->template_id) {
                        $refTemplate = $this->module->templates->get($field->template_id);
                        if($refTemplate) $fieldData['template'] = $refTemplate->name;
                    }
                } elseif($field->type->className() === 'FieldtypeRepeater') {
                    $repeaterTemplate = $this->module->templates->get("name=repeater_{$field->name}");
                    if($repeaterTemplate) {
                        $fieldData['subfields'] = [];
                        foreach($repeaterTemplate->fields as $repField) {
                            $subFieldData = [
                                'name' => $repField->name,
                                'type' => $repField->type->className(),
                                'label' => $repField->label,
                            ];
                            if($repField->type instanceof FieldtypePage && $repField->template_id) {
                                $refTemplate = $this->module->templates->get($repField->template_id);
                                if($refTemplate) $subFieldData['template'] = $refTemplate->name;
                            }
                            $fieldData['subfields'][] = $subFieldData;
                        }
                    }
                } elseif($field->type->className() === 'FieldtypeRepeaterMatrix') {
                    $fieldData['matrix_types'] = $this->invoke('getMatrixTypesData', $field);
                } elseif($field->type->className() === 'FieldtypeTable') {
                    $fieldData['columns'] = $this->invoke('getTableColumns', $field);
                } elseif($field->type->className() === 'FieldtypeCombo') {
                    $fieldData['subfields'] = $this->invoke('getComboSubfields', $field);
                }

                $templateData['fields'][] = $fieldData;
            }

            $tree[] = $templateData;
        }

        $this->module->log("Site tree built with " . count($tree) . " templates");

        return $tree;
    }

    protected function invoke($method, ...$args) {
        return ($this->call)($method, ...$args);
    }
}
