<?php
/**
 * AutoPosts API - OpenAI Integration
 * 
 * Clase para comunicación con la API de OpenAI
 * Solo contiene la función genérica generateContent()
 * Los prompts inteligentes se construyen en index.php
 * 
 * @package AutoPostsAPI
 * @version 3.2.0
 */

defined('API_ACCESS') or die('Direct access not permitted');

require_once API_BASE_DIR . '/models/License.php';

/**
 * Clase OpenAI - Wrapper para la API de OpenAI
 */
class OpenAIService {
    
    private $apiKey;
    private $model;
    private $maxTokens;
    private $temperature;
    
    /**
     * Constructor
     */
    public function __construct($apiKey = null) {
        // Obtener API key de settings en BD o de constante
        if ($apiKey) {
            $this->apiKey = $apiKey;
        } else {
            // Intentar obtener de BD
            try {
                $db = Database::getInstance();
                $stmt = $db->prepare("SELECT setting_value FROM " . DB_PREFIX . "settings WHERE setting_key = 'openai_api_key' LIMIT 1");
                $stmt->execute();
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $this->apiKey = $result['setting_value'] ?? OPENAI_API_KEY ?? '';
            } catch (Exception $e) {
                $this->apiKey = OPENAI_API_KEY ?? '';
            }
        }
        
        $this->model = OPENAI_MODEL ?? 'gpt-4o-mini';
        $this->maxTokens = OPENAI_MAX_TOKENS ?? 4000;
        $this->temperature = OPENAI_TEMPERATURE ?? 0.7;
    }
    
    /**
     * Verificar si la API Key está configurada
     */
    public function hasApiKey() {
        return !empty($this->apiKey);
    }
    
    /**
     * Generar contenido con OpenAI
     * 
     * Función genérica que recibe un prompt ya construido y lo envía a OpenAI
     * 
     * @param array $params Parámetros de la petición
     *   - prompt: string (requerido) - Prompt para la IA
     *   - system_prompt: string (opcional) - Prompt del sistema
     *   - max_tokens: int (opcional) - Máximo de tokens
     *   - temperature: float (opcional) - Creatividad (0-1)
     *   - response_format: string (opcional) - 'text' o 'json'
     *   - context: string (opcional) - Contexto adicional
     * 
     * @return array Respuesta con 'success', 'content', 'usage', etc.
     */
    public function generateContent($params) {
        // Verificar API Key
        if (!$this->hasApiKey()) {
            return [
                'success' => false,
                'error' => 'API Key de OpenAI no configurada',
                'code' => 'OPENAI_API_KEY_MISSING'
            ];
        }
        
        // Validar parámetros requeridos
        if (empty($params['prompt'])) {
            return [
                'success' => false,
                'error' => 'Prompt es requerido',
                'code' => 'PROMPT_REQUIRED'
            ];
        }
        
        try {
            // Preparar parámetros
            $prompt = $params['prompt'];
            $systemPrompt = $params['system_prompt'] ?? 'Eres un asistente experto en generación de contenido para blogs y redes sociales. Genera contenido de alta calidad, original y optimizado para SEO.';
            $maxTokens = $params['max_tokens'] ?? $this->maxTokens;
            $temperature = $params['temperature'] ?? $this->temperature;
            $responseFormat = $params['response_format'] ?? 'text';
            $model = $params['model'] ?? $this->model;  // ⭐ Permitir override del modelo
            
            // Construir mensajes
            $messages = [
                [
                    'role' => 'system',
                    'content' => $systemPrompt
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ];
            
            // Si hay contexto adicional
            if (!empty($params['context'])) {
                $messages[] = [
                    'role' => 'user',
                    'content' => 'Contexto adicional: ' . $params['context']
                ];
            }
            
            // Preparar payload
            $payload = [
                'model' => $model,  // ⭐ Usar modelo del parámetro o default
                'messages' => $messages,
                'max_tokens' => $maxTokens,
                'temperature' => $temperature
            ];
            
            // Si se solicita JSON
            if ($responseFormat === 'json') {
                $payload['response_format'] = ['type' => 'json_object'];
            }
            
            // Realizar petición a OpenAI
            $response = $this->makeRequest('https://api.openai.com/v1/chat/completions', $payload);
            
            if (!$response['success']) {
                return $response;
            }
            
            // Procesar respuesta
            $data = $response['data'];
            
            if (!isset($data['choices'][0]['message']['content'])) {
                return [
                    'success' => false,
                    'error' => 'Respuesta inválida de OpenAI',
                    'code' => 'INVALID_OPENAI_RESPONSE'
                ];
            }
            
            $content = $data['choices'][0]['message']['content'];
            
            // Limpiar bloques de código markdown si existen
            $content = preg_replace('/```html\s*/i', '', $content);
            $content = preg_replace('/```\s*$/i', '', $content);
            $content = preg_replace('/```/i', '', $content);
            
            // Eliminar frases introductorias comunes de la IA
            $content = preg_replace('/^(Aquí está|He generado|A continuación|Este es)[^\n]*\n*/i', '', $content);
            
            // Si se solicita JSON, decodificar
            if ($responseFormat === 'json') {
                $content = json_decode($content, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    return [
                        'success' => false,
                        'error' => 'Error al decodificar JSON de OpenAI',
                        'code' => 'JSON_DECODE_ERROR'
                    ];
                }
            }
            
            // Trim final
            $content = trim($content);
            
            return [
                'success' => true,
                'content' => $content,
                'usage' => $data['usage'] ?? null,
                'model' => $data['model'] ?? $this->model,
                'finish_reason' => $data['choices'][0]['finish_reason'] ?? null
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'code' => 'OPENAI_EXCEPTION'
            ];
        }
    }
    
    /**
     * Verificar estado de la API Key
     * 
     * @return array Resultado de la verificación
     */
    public function testApiKey() {
        if (!$this->hasApiKey()) {
            return [
                'success' => false,
                'error' => 'API Key no configurada'
            ];
        }
        
        $result = $this->generateContent([
            'prompt' => 'Di "OK"',
            'max_tokens' => 10
        ]);
        
        if ($result['success']) {
            return [
                'success' => true,
                'message' => 'API Key válida y funcionando',
                'model' => $result['model']
            ];
        }
        
        return $result;
    }
    
    // ========================================================================
    // FUNCIONES PRIVADAS
    // ========================================================================
    
    /**
     * Realizar petición HTTP a OpenAI
     */
    private function makeRequest($url, $data) {
        $ch = curl_init($url);
        
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey
            ],
            CURLOPT_TIMEOUT => 60,
            CURLOPT_SSL_VERIFYPEER => true
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        curl_close($ch);
        
        if ($error) {
            return [
                'success' => false,
                'error' => 'Error de conexión: ' . $error,
                'code' => 'CURL_ERROR'
            ];
        }
        
        $responseData = json_decode($response, true);
        
        if ($httpCode !== 200) {
            $errorMessage = $responseData['error']['message'] ?? 'Error desconocido';
            $errorCode = $responseData['error']['code'] ?? 'OPENAI_ERROR';
            
            return [
                'success' => false,
                'error' => $errorMessage,
                'code' => $errorCode,
                'http_code' => $httpCode
            ];
        }
        
        return [
            'success' => true,
            'data' => $responseData
        ];
    }
    
    /**
     * Calcular tokens aproximados según palabras
     */
    public function calculateTokens($words) {
        // Aproximadamente 1.3 tokens por palabra en español
        $tokens = (int)($words * 1.3);
        
        // Agregar margen para formato HTML
        $tokens = (int)($tokens * 1.2);
        
        // Limitar a máximo configurado
        return min($tokens, $this->maxTokens);
    }
}
