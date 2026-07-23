<?php namespace ProcessWire;

/**
 * Context Module - AI Gateway
 *
 * Centralized AI provider gateway for the Context module and third-party modules.
 * Uses Squad when selected, with direct OpenRouter/OpenAI-compatible access
 * retained for backwards compatibility.
 *
 * Third-party modules can use this gateway via the $context API variable:
 *
 *   $ai = wire('context')->ai();
 *   $result = $ai->complete('Summarize this page: ' . $page->title);
 *
 * Or with full options:
 *   $result = $ai->complete([
 *       'messages' => [['role' => 'user', 'content' => 'Hello']],
 *       'model'    => 'anthropic/claude-sonnet-4-6',
 *       'caller'   => 'MyModule',
 *   ]);
 *
 * @package Context
 */
class ContextAI {

    /** @var array Module config data */
    protected $config = [];

    /** @var array Request log (current request only) */
    protected $log = [];

    // ── Provider constants ────────────────────────────────────────────────────

    const PROVIDER_SQUAD      = 'squad';
    const PROVIDER_OPENROUTER = 'openrouter';
    const PROVIDER_OPENAI     = 'openai';
    const PROVIDER_CUSTOM     = 'custom';

    // ── OpenRouter base URL ───────────────────────────────────────────────────

    const OPENROUTER_BASE_URL = 'https://openrouter.ai/api/v1';

    // ── Default model ─────────────────────────────────────────────────────────

    const DEFAULT_MODEL = 'anthropic/claude-sonnet-4-6';

    /**
     * Constructor
     *
     * @param array $config Merged module config (ai_* keys)
     */
    public function __construct(array $config = []) {
        $this->config = $config;
    }

    // =========================================================================
    // Public API
    // =========================================================================

    /**
     * Check whether AI is configured and enabled.
     *
     * @return bool
     */
    public function isEnabled(): bool {
        if (empty($this->config['ai_enabled'])) {
            return false;
        }

        $provider = $this->config['ai_provider'] ?? self::PROVIDER_OPENROUTER;
        if ($provider === self::PROVIDER_SQUAD) {
            return $this->squadIsReady();
        }
        if (empty($this->config['ai_api_key'])) {
            return false;
        }
        if ($provider === self::PROVIDER_CUSTOM && empty($this->config['ai_custom_endpoint'])) {
            return false;
        }

        return true;
    }

    /**
     * Simple one-shot text completion.
     *
     * @param string|array $prompt  Plain string prompt OR full options array (see complete())
     * @param array        $options Additional options (merged over defaults)
     * @return string|false  Response text, or false on failure
     */
    public function complete($prompt, array $options = []) {
        if (is_string($prompt)) {
            $options['messages'] = [['role' => 'user', 'content' => $prompt]];
        } elseif (is_array($prompt)) {
            $options = array_merge($prompt, $options);
        }

        $result = $this->chat($options);

        if (isset($result['error'])) {
            return false;
        }

        return $result['content'] ?? false;
    }

    /**
     * Full chat completion — returns structured response array.
     *
     * @param array $options {
     *   @type array  $messages   Required. Array of ['role' => ..., 'content' => ...] messages.
     *   @type string $model      Model slug. Defaults to module setting or DEFAULT_MODEL.
     *   @type string $system     System prompt (prepended automatically).
     *   @type int    $max_tokens Max tokens. Default 1024.
     *   @type float  $temperature Temperature 0-2. Default 0.7.
     *   @type string $caller     Identifies the calling module (for logging).
     * }
     * @return array {
     *   @type string $content      Response text (first choice)
     *   @type array  $usage        Token usage stats
     *   @type string $model        Model used
     *   @type string $error        Error message (only on failure)
     *   @type int    $status_code  HTTP status code
     * }
     */
    public function chat(array $options = []): array {
        if (!$this->isEnabled()) {
            return ['error' => 'AI gateway is not configured or disabled.', 'status_code' => 0];
        }

        $provider    = $this->config['ai_provider'] ?? self::PROVIDER_OPENROUTER;
        $model       = trim((string)($options['model'] ?? ($provider === self::PROVIDER_SQUAD ? '' : ($this->config['ai_model'] ?? self::DEFAULT_MODEL))));
        $maxTokens   = $this->clampInt($options['max_tokens'] ?? $this->config['ai_max_tokens'] ?? 1024, 1, 200000);
        $temperature = $this->clampFloat($options['temperature'] ?? $this->config['ai_temperature'] ?? 0.7, 0.0, 2.0);
        $timeout     = $this->clampInt($options['timeout'] ?? $this->config['ai_timeout'] ?? 120, 5, 300);
        $caller      = trim((string)($options['caller'] ?? 'Context'));

        if ($model === '' && $provider !== self::PROVIDER_SQUAD) $model = self::DEFAULT_MODEL;
        if ($caller === '') $caller = 'Context';

        $messages = $this->normalizeMessages($options['messages'] ?? []);

        // Prepend system message if provided
        if (isset($options['system']) && trim((string)$options['system']) !== '') {
            array_unshift($messages, ['role' => 'system', 'content' => (string)$options['system']]);
        }

        // Global system prompt from module settings
        if (isset($this->config['ai_system_prompt']) && trim((string)$this->config['ai_system_prompt']) !== '') {
            array_unshift($messages, ['role' => 'system', 'content' => (string)$this->config['ai_system_prompt']]);
        }

        if (!$messages) {
            return ['error' => 'AI messages are required.', 'status_code' => 0];
        }

        if ($provider === self::PROVIDER_SQUAD) {
            return $this->chatViaSquad(
                $messages,
                $options,
                $model,
                $maxTokens,
                $temperature,
                $timeout,
                $caller
            );
        }

        $payload = [
            'model'       => $model,
            'messages'    => $messages,
            'max_tokens'  => $maxTokens,
            'temperature' => $temperature,
        ];

        $endpoint = $this->getEndpoint();
        $headers  = $this->getHeaders($caller);

        $startTime = microtime(true);
        $response  = $this->httpPost($endpoint, $payload, $headers, $timeout);
        $duration  = round((microtime(true) - $startTime) * 1000); // ms

        $result = $this->parseResponse($response);
        $result['duration_ms'] = $duration;
        $result['model'] = $model;
        $result['caller'] = $caller;

        $this->logRequest($caller, $model, $messages, $result);

        return $result;
    }

    /**
     * Summarize a ProcessWire page using AI.
     *
     * @param Page   $page    The page to summarize
     * @param string $context Extra context for the prompt
     * @return string|false
     */
    public function summarizePage(Page $page, string $context = '') {
        $fields = [];
        foreach ($page->template->fields as $field) {
            $value = $page->get($field->name);
            if (is_string($value) && strlen($value) > 0) {
                $fields[$field->name] = substr($value, 0, 500);
            }
        }

        $data = json_encode($fields, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $prompt = "Summarize this ProcessWire page in 2-3 sentences.\n\nTemplate: {$page->template->name}\nFields:\n{$data}";

        if ($context) {
            $prompt .= "\n\nAdditional context: {$context}";
        }

        return $this->complete($prompt, ['caller' => 'Context::summarizePage']);
    }

    /**
     * Ask the AI anything about the current site using exported context.
     *
     * @param string $question
     * @param string $contextData  Raw context string (e.g. contents of structure.toon)
     * @return string|false
     */
    public function askAboutSite(string $question, string $contextData = '') {
        $system = "You are a ProcessWire CMS expert. Answer questions about the site structure and configuration.";
        $content = $contextData
            ? "Site context:\n{$contextData}\n\nQuestion: {$question}"
            : $question;

        return $this->complete([
            'messages' => [['role' => 'user', 'content' => $content]],
            'system'   => $system,
            'caller'   => 'Context::askAboutSite',
        ]);
    }

    /**
     * Get available models from the configured provider.
     * Returns simplified list suitable for a select field.
     *
     * @return array ['model_id' => 'Display Name', ...]
     */
    public function getAvailableModels(): array {
        if (!$this->isEnabled()) return [];

        $provider = $this->config['ai_provider'] ?? self::PROVIDER_OPENROUTER;

        if ($provider === self::PROVIDER_SQUAD) {
            $squad = $this->squad();
            if (!$squad) return [];
            $providerKey = (string)$squad->getDefaultProviderKey();
            return (array)$squad->getProviderModels($providerKey);
        }

        if ($provider === self::PROVIDER_OPENROUTER) {
            return $this->fetchOpenRouterModels();
        }

        // For other providers return an empty array (caller can show text input)
        return [];
    }

    /**
     * Get the request log for the current PHP process.
     *
     * @return array
     */
    public function getLog(): array {
        return $this->log;
    }

    // =========================================================================
    // Gateway API — for third-party modules
    // =========================================================================

    /**
     * Gateway entry point for third-party modules.
     *
     * Usage from any module:
     *   $result = wire('context')->ai()->gateway([
     *       'caller'   => 'MyModule',
     *       'messages' => [['role' => 'user', 'content' => 'Do X']],
     *       'model'    => 'openai/gpt-4o-mini',   // optional override
     *   ]);
     *
     * @param array $options  Same as chat() options
     * @return array          Same as chat() return value
     */
    public function gateway(array $options): array {
        // Enforce caller identification
        if (empty($options['caller'])) {
            $options['caller'] = 'UnknownModule';
        }
        return $this->chat($options);
    }

    // =========================================================================
    // Internal helpers
    // =========================================================================

    /**
     * Resolve the installed Squad module without making a provider request.
     */
    protected function squad(): ?object {
        try {
            $modules = wire('modules');
            if (!$modules || !$modules->isInstalled('Squad')) return null;
            $squad = $modules->get('Squad');
            return is_object($squad) ? $squad : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * The configured Squad default provider must have an enabled key.
     */
    protected function squadIsReady(): bool {
        $squad = $this->squad();
        if (!$squad
            || !method_exists($squad, 'getDefaultProviderKey')
            || !method_exists($squad, 'getProvidersStatus')) {
            return false;
        }

        $provider = (string)$squad->getDefaultProviderKey();
        $status = (array)$squad->getProvidersStatus();
        return !empty($status[$provider]['active']);
    }

    /**
     * Send a normalized Context request through Squad.
     */
    protected function chatViaSquad(
        array $messages,
        array $options,
        string $model,
        int $maxTokens,
        float $temperature,
        int $timeout,
        string $caller
    ): array {
        $squad = $this->squad();
        if (!$squad || !method_exists($squad, 'ask')) {
            return ['error' => 'Squad is not installed or unavailable.', 'status_code' => 0];
        }
        if ($model === '') {
            $model = $this->squadDefaultModel($squad);
        }

        $systemParts = [];
        $history = [];
        foreach ($messages as $message) {
            $role = (string)($message['role'] ?? 'user');
            $content = $this->messageText($message['content'] ?? '');
            if ($content === '') continue;

            if ($role === 'system' || $role === 'developer') {
                $systemParts[] = $content;
                continue;
            }
            $history[] = [
                'role' => $role === 'assistant' ? 'assistant' : 'user',
                'content' => $content,
            ];
        }

        if (!$history) {
            return ['error' => 'AI messages require a user message.', 'status_code' => 0];
        }

        $current = array_pop($history);
        if ($current['role'] !== 'user') {
            $history[] = $current;
            $current = ['role' => 'user', 'content' => 'Continue.'];
        }

        $request = [
            'systemPrompt' => trim(implode("\n\n", $systemParts)),
            'history' => $history,
            'maxTokens' => $maxTokens,
            'temperature' => $temperature,
            'timeout' => $timeout,
            'cache' => $options['cache'] ?? false,
        ];
        if ($model !== '') $request['model'] = $model;
        if (!empty($options['squad_provider'])) {
            $request['provider'] = trim((string)$options['squad_provider']);
        }

        $startTime = microtime(true);
        $response = (array)$squad->ask($current['content'], $request);
        $duration = (int)round((microtime(true) - $startTime) * 1000);

        if (empty($response['success'])) {
            $result = [
                'error' => (string)($response['message'] ?? 'Squad request failed.'),
                'status_code' => 0,
                'duration_ms' => $duration,
                'model' => (string)($response['model'] ?? $model),
                'caller' => $caller,
            ];
        } else {
            $result = [
                'content' => (string)($response['content'] ?? ''),
                'usage' => (array)($response['usage'] ?? []),
                'status_code' => 200,
                'duration_ms' => $duration,
                'model' => (string)($response['model'] ?? $model),
                'caller' => $caller,
                'cached' => !empty($response['cached']),
            ];
            if ($result['content'] === '') {
                $result['error'] = 'Squad returned an empty response.';
                $result['status_code'] = 0;
            }
        }

        $this->logRequest($caller, $result['model'], $messages, $result);
        return $result;
    }

    /**
     * Read the selected model from Squad's active default key.
     */
    protected function squadDefaultModel(object $squad): string {
        if (!method_exists($squad, 'getDefaultProviderKey')
            || !method_exists($squad, 'getProvider')) {
            return '';
        }

        $provider = $squad->getProvider((string)$squad->getDefaultProviderKey());
        return $provider && method_exists($provider, 'getModel')
            ? trim((string)$provider->getModel())
            : '';
    }

    /**
     * Convert OpenAI-compatible scalar or text-part content to Squad text.
     */
    protected function messageText($content): string {
        if (is_scalar($content)) return trim((string)$content);
        if (!is_array($content)) return '';

        $parts = [];
        foreach ($content as $part) {
            if (is_scalar($part)) {
                $parts[] = (string)$part;
            } elseif (is_array($part)) {
                $text = $part['text'] ?? $part['content'] ?? '';
                if (is_scalar($text)) $parts[] = (string)$text;
            }
        }
        return trim(implode("\n", array_filter($parts, 'strlen')));
    }

    /**
     * Determine API endpoint based on provider setting.
     */
    protected function getEndpoint(): string {
        $provider = $this->config['ai_provider'] ?? self::PROVIDER_OPENROUTER;

        switch ($provider) {
            case self::PROVIDER_OPENAI:
                return 'https://api.openai.com/v1/chat/completions';

            case self::PROVIDER_CUSTOM:
                $base = rtrim(trim((string)($this->config['ai_custom_endpoint'] ?? '')), '/');
                return $base . '/chat/completions';

            case self::PROVIDER_OPENROUTER:
            default:
                return self::OPENROUTER_BASE_URL . '/chat/completions';
        }
    }

    /**
     * Build HTTP headers.
     */
    protected function getHeaders(string $caller = 'Context'): array {
        $key      = $this->config['ai_api_key'] ?? '';
        $provider = $this->config['ai_provider'] ?? self::PROVIDER_OPENROUTER;

        $headers = [
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer ' . $key,
        ];

        // OpenRouter-specific headers
        if ($provider === self::PROVIDER_OPENROUTER) {
            $siteUrl  = $this->config['ai_site_url']  ?? (wire('config')->httpHost ?? 'processwire');
            $siteName = $this->config['ai_site_name'] ?? (wire('config')->systemName ?? 'ProcessWire Site');

            $siteUrl = preg_replace('#^https?://#i', '', (string)$siteUrl);
            $headers['HTTP-Referer'] = 'https://' . ltrim($siteUrl, '/');
            $headers['X-Title']      = $siteName . ' / ' . $caller;
        }

        return $headers;
    }

    /**
     * Execute HTTP POST with cURL.
     *
     * @return array ['body' => string, 'status' => int]
     */
    protected function httpPost(string $url, array $payload, array $headers, int $timeout): array {
        $headersFlat = [];
        foreach ($headers as $k => $v) {
            $headersFlat[] = "{$k}: {$v}";
        }

        $body = json_encode($payload);
        if ($body === false) {
            return ['body' => '', 'status' => 0, 'curl_error' => 'JSON encode failed: ' . json_last_error_msg()];
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => $headersFlat,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $body   = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error  = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return ['body' => '', 'status' => 0, 'curl_error' => $error];
        }

        return ['body' => $body, 'status' => $status];
    }

    /**
     * Keep third-party gateway calls within OpenAI-compatible chat message shape.
     */
    protected function normalizeMessages($messages): array {
        if (!is_array($messages)) return [];

        $normalized = [];
        $allowedRoles = ['system', 'user', 'assistant', 'tool', 'developer'];

        foreach ($messages as $message) {
            if (!is_array($message)) continue;

            $role = isset($message['role']) ? trim((string)$message['role']) : 'user';
            if (!in_array($role, $allowedRoles, true)) $role = 'user';
            if (!array_key_exists('content', $message)) continue;

            $content = $message['content'];
            if (is_scalar($content)) {
                $content = (string)$content;
                if ($content === '') continue;
            } elseif (!is_array($content)) {
                continue;
            }

            $normalized[] = [
                'role'    => $role,
                'content' => $content,
            ];
        }

        return $normalized;
    }

    /**
     * Clamp integer request options to predictable bounds.
     */
    protected function clampInt($value, int $min, int $max): int {
        $value = (int)$value;
        if ($value < $min) return $min;
        if ($value > $max) return $max;
        return $value;
    }

    /**
     * Clamp floating point request options to predictable bounds.
     */
    protected function clampFloat($value, float $min, float $max): float {
        $value = (float)$value;
        if ($value < $min) return $min;
        if ($value > $max) return $max;
        return $value;
    }

    /**
     * Parse raw HTTP response into a structured array.
     */
    protected function parseResponse(array $response): array {
        $status = $response['status'] ?? 0;

        if (!empty($response['curl_error'])) {
            return ['error' => 'cURL error: ' . $response['curl_error'], 'status_code' => 0];
        }

        $data = json_decode($response['body'] ?? '', true);
        if (!is_array($data)) {
            return ['error' => 'Invalid JSON response from AI provider.', 'status_code' => $status];
        }

        if ($status !== 200) {
            $msg = $data['error']['message'] ?? ('HTTP ' . $status);
            return ['error' => $msg, 'status_code' => $status];
        }

        $content = $data['choices'][0]['message']['content'] ?? '';
        $usage   = $data['usage'] ?? [];

        return [
            'content'     => $content,
            'usage'       => $usage,
            'status_code' => $status,
        ];
    }

    /**
     * Fetch model list from OpenRouter /models endpoint.
     */
    protected function fetchOpenRouterModels(): array {
        $key = $this->config['ai_api_key'] ?? '';
        if (!$key) return [];

        $ch = curl_init(self::OPENROUTER_BASE_URL . '/models');
        if (!$ch) return [];

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $key],
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $body = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error || $status !== 200 || !is_string($body)) return [];

        $data = json_decode($body, true);
        if (!is_array($data) || empty($data['data']) || !is_array($data['data'])) return [];

        $models = [];
        foreach ($data['data'] as $m) {
            if (!is_array($m)) continue;
            $id   = $m['id'] ?? '';
            $name = $m['name'] ?? $id;
            if ($id) {
                $models[(string)$id] = (string)$name;
            }
        }

        // Sort by name
        asort($models);

        return $models;
    }

    /**
     * Log a request (in-memory, current process only).
     */
    protected function logRequest(string $caller, string $model, array $messages, array $result): void {
        $this->log[] = [
            'time'        => date('Y-m-d H:i:s'),
            'caller'      => $caller,
            'model'       => $model,
            'input_msgs'  => count($messages),
            'tokens_in'   => $result['usage']['prompt_tokens']     ?? null,
            'tokens_out'  => $result['usage']['completion_tokens'] ?? null,
            'duration_ms' => $result['duration_ms'] ?? null,
            'success'     => empty($result['error']),
            'error'       => $result['error'] ?? null,
        ];
    }
}
