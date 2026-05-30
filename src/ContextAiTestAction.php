<?php namespace ProcessWire;

/**
 * Handles the AJAX AI gateway connection test.
 */
class ContextAiTestAction {

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

    public function execute() {
        if(!$this->invoke('hasContextAccess')) {
            $this->send(['success' => false, 'error' => 'Access denied']);
        }

        if(!$this->module->config->ajax || ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            $this->send(['success' => false, 'error' => 'AJAX only']);
        }

        if(!$this->invoke('hasValidCsrfToken')) {
            $this->send(['success' => false, 'error' => 'Invalid CSRF token']);
        }

        $ai = $this->module->ai();

        if(!$ai->isEnabled()) {
            $this->send(['success' => false, 'error' => 'AI Gateway is not enabled or API key is missing']);
        }

        $start = microtime(true);
        $result = $ai->chat([
            'messages'   => [['role' => 'user', 'content' => 'Reply with only the word: OK']],
            'max_tokens' => 10,
            'caller'     => 'Context::testConnection',
        ]);
        $ms = round((microtime(true) - $start) * 1000);

        if(isset($result['error'])) {
            $this->send(['success' => false, 'error' => $result['error']]);
        }

        $this->send([
            'success' => true,
            'model'   => $result['model'] ?? 'unknown',
            'ms'      => $ms,
            'content' => trim($result['content'] ?? ''),
        ]);
    }

    protected function send(array $data) {
        $this->invoke('sendJsonResponse', $data);
    }

    protected function invoke($method, ...$args) {
        return ($this->call)($method, ...$args);
    }
}
