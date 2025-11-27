<?php
/**
 * Clase base para todos los endpoints de generación
 * 
 * Maneja validación de licencia, tracking de uso y respuestas comunes
 * 
 * @package AutoPostsAPI
 * @version 5.0
 */

defined('API_ACCESS') or die('Direct access not permitted');

require_once API_BASE_DIR . '/core/Response.php';
require_once API_BASE_DIR . '/core/PromptManager.php';
require_once API_BASE_DIR . '/models/License.php';
require_once API_BASE_DIR . '/models/UsageTracking.php';
require_once API_BASE_DIR . '/services/LicenseValidator.php';
require_once API_BASE_DIR . '/services/TokenManager.php';
require_once API_BASE_DIR . '/services/OpenAIService.php';

abstract class BaseEndpoint {
    
    protected $licenseKey;
    protected $license;
    protected $params;
    protected $promptManager;
    protected $openai;
    
    /**
     * Constructor - Obtiene parámetros y prepara servicios
     */
    public function __construct() {
        $this->params = Response::getJsonInput();
        $this->licenseKey = $this->params['license_key'] ?? $_GET['license_key'] ?? null;
        $this->promptManager = new PromptManager();
        $this->openai = new OpenAIService();
    }
    
    /**
     * Valida la licencia antes de ejecutar
     * 
     * @throws Exception Si la licencia no es válida
     */
    protected function validateLicense() {
        if (!$this->licenseKey) {
            Response::error('License key requerida', 401);
        }
        
        $validator = new LicenseValidator();
        $validation = $validator->validate($this->licenseKey);
        
        if (!$validation['valid']) {
            Response::error($validation['reason'] ?? 'Invalid license', 401);
        }
        
        $this->license = $validation['license'];
    }
    
    /**
     * Registra el uso de tokens
     * 
     * @param string $operationType Tipo de operación
     * @param array $result Resultado de OpenAI
     */
    protected function trackUsage($operationType, $result) {
        if (!$this->license) {
            return;
        }
        
        $usage = $result['usage'] ?? [];
        $tokensUsed = $usage['total_tokens'] ?? 0;
        
        if ($tokensUsed > 0) {
            // Obtener campaign_id y batch_id si existen
            $campaignId = $this->params['campaign_id'] ?? null;
            $campaignName = $this->params['campaign_name'] ?? null;
            $batchId = $this->params['batch_id'] ?? null;
            
            UsageTracking::record(
                $this->license['id'],
                $operationType,
                $tokensUsed,
                $usage['prompt_tokens'] ?? 0,
                $usage['completion_tokens'] ?? 0,
                $campaignId,
                $campaignName,
                $batchId
            );
            
            // Actualizar contador de licencia
            License::incrementTokens($this->license['id'], $tokensUsed);
        }
    }
    
    /**
     * Carga un prompt desde archivo .md
     * 
     * @param string $promptName Nombre del archivo (sin extensión)
     * @return string Contenido del prompt
     */
    protected function loadPrompt($promptName) {
        $promptFile = API_BASE_DIR . '/prompts/' . $promptName . '.md';
        
        if (!file_exists($promptFile)) {
            Logger::api('error', "Prompt file not found: {$promptName}.md");
            return null;
        }
        
        return file_get_contents($promptFile);
    }
    
    /**
     * Reemplaza variables en un prompt
     * 
     * @param string $prompt Template del prompt
     * @param array $variables Variables a reemplazar
     * @return string Prompt con variables reemplazadas
     */
    protected function replaceVariables($prompt, $variables) {
        foreach ($variables as $key => $value) {
            $prompt = str_replace("{{$key}}", $value, $prompt);
        }
        return $prompt;
    }
    
    /**
     * Obtiene contenido web de una URL
     * 
     * @param string $url URL a obtener
     * @return string|null Contenido o null si falla
     */
    protected function fetchWebContent($url) {
        try {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 3,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; AutoPostsBot/1.0)'
            ]);
            
            $content = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode !== 200 || !$content) {
                return null;
            }
            
            // Limpiar HTML
            $content = strip_tags($content);
            $content = preg_replace('/\s+/', ' ', $content);
            
            // Limitar a 3000 caracteres
            return substr(trim($content), 0, 3000);
            
        } catch (Exception $e) {
            Logger::api('error', 'Error fetching web content', [
                'url' => $url,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
    
    /**
     * Método abstracto que cada endpoint debe implementar
     */
    abstract public function handle();
}
