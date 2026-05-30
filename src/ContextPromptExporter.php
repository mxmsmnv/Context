<?php namespace ProcessWire;

/**
 * Writes generated code snippets and prompt files.
 */
class ContextPromptExporter {

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

    public function createSnippets() {
        $snippetsPath = $this->invoke('ensureFolder', $this->module->getContextPath() . 'snippets/');
        require_once __DIR__ . '/ContextSnippets.php';

        $siteType = $this->module->site_type ?: 'generic';

        $templates = [];
        foreach($this->module->templates as $template) {
            if($template->flags & Template::flagSystem) continue;
            if(in_array($template->name, ['home', 'basic-page', 'admin', 'sitemap'])) continue;
            $templates[] = $template->name;
        }

        $this->invoke('writeFile', $snippetsPath . 'selectors.php', ContextSnippets::getSelectorsSnippet($siteType, $templates));
        $this->invoke('writeFile', $snippetsPath . 'helpers.php', ContextSnippets::getHelpersSnippet());
        $this->invoke('writeFile', $snippetsPath . 'api-examples.php', ContextSnippets::getApiExamplesSnippet($siteType, $templates));
    }

    public function createPrompts() {
        $promptsPath = $this->invoke('ensureFolder', $this->module->getContextPath() . 'prompts/');

        $this->invoke('writeFile', $promptsPath . 'project-context.md', $this->invoke('generateProjectContext'));
        $this->invoke('writeFile', $promptsPath . 'create-template.md', $this->invoke('generateCreateTemplatePrompt'));
        $this->invoke('writeFile', $promptsPath . 'create-api.md', $this->invoke('generateCreateApiPrompt'));
        $this->invoke('writeFile', $promptsPath . 'debug-issue.md', $this->invoke('generateDebugPrompt'));

        if(!file_exists($promptsPath . 'project-summary.md')) {
            $this->invoke('writeFile', $promptsPath . 'project-summary.md', $this->invoke('generateProjectSummaryTemplate'));
        }

        return true;
    }

    protected function invoke($method, ...$args) {
        return ($this->call)($method, ...$args);
    }
}
