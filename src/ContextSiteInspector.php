<?php namespace ProcessWire;

/**
 * Reads high-level site metadata used by dashboards and generated prompts.
 */
class ContextSiteInspector {

    /** @var Context */
    protected $module;

    public function __construct(Context $module) {
        $this->module = $module;
    }

    public function getAccessMap() {
        $accessMap = [];

        foreach($this->module->roles as $role) {
            if($role->name === 'guest' && !$role->permissions->count()) continue;

            $accessMap[$role->name] = [
                'permissions' => $role->permissions->explode('name'),
                'description' => $role->get('title|name')
            ];
        }

        return $accessMap;
    }

    public function getSiteStats() {
        $stats = [
            'templates' => 0,
            'fields' => 0,
            'pages' => 0,
            'users' => 0
        ];

        foreach($this->module->templates as $template) {
            if(!($template->flags & Template::flagSystem)) $stats['templates']++;
        }

        foreach($this->module->fields as $field) {
            if(!($field->flags & Field::flagSystem)) $stats['fields']++;
        }

        $stats['pages'] = $this->module->pages->count("id>0");
        $stats['users'] = $this->module->users->count();

        return $stats;
    }
}
