<?php namespace ProcessWire;

/**
 * Shared helpers for admin web actions and markup safety.
 */
class ContextWebHelper {

    /** @var Context */
    protected $module;

    public function __construct(Context $module) {
        $this->module = $module;
    }

    public function sendJsonResponse(array $data) {
        header('Content-Type: application/json; charset=utf-8');

        $json = json_encode($data, JSON_UNESCAPED_UNICODE);
        if($json === false) {
            http_response_code(500);
            $json = '{"success":false,"error":"Failed to encode JSON response"}';
        }

        echo $json;
        exit;
    }

    public function hasContextAccess() {
        return $this->module->user->isSuperuser() || $this->module->user->hasPermission('context-admin');
    }

    public function requireContextAccess() {
        if(!$this->hasContextAccess()) {
            throw new WireException('You do not have permission to administer Context exports.');
        }
    }

    public function hasValidCsrfToken() {
        if(method_exists($this->module->session->CSRF, 'hasValidToken')) {
            return $this->module->session->CSRF->hasValidToken();
        }

        $name = $this->module->session->CSRF->getTokenName();
        $value = $this->module->session->CSRF->getTokenValue();
        return isset($_POST[$name]) && hash_equals((string)$value, (string)$_POST[$name]);
    }

    public function requirePostCsrf() {
        if(($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            throw new WireException('This action requires a POST request.');
        }
        if(!$this->hasValidCsrfToken()) {
            throw new WireException('Invalid CSRF token.');
        }
    }

    public function getCsrfInputMarkup() {
        $name = $this->module->session->CSRF->getTokenName();
        $value = $this->module->session->CSRF->getTokenValue();
        return '<input type="hidden" name="' . $this->attr($name) . '" value="' . $this->attr($value) . '">';
    }

    public function html($value) {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }

    public function attr($value) {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}
