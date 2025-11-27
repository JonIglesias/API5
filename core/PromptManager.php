<?php
/**
 * PromptManager - Sistema de Gestión de Prompts V4.2
 * 
 * Gestiona templates de prompts con variables, versionado y preview
 * 
 * @package AutoPostsAPI
 * @version 4.2.0
 */

defined('API_ACCESS') or die('Direct access not permitted');

require_once API_BASE_DIR . '/core/Database.php';

class PromptManager {
    
    private $db;
    private $cache = [];
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    // ========================================================================
    // MÉTODOS PRINCIPALES
    // ========================================================================
    
    /**
     * Obtener un prompt por slug
     * 
     * @param string $slug Identificador del prompt
     * @return array|null Datos del prompt o null si no existe
     */
    public function getPrompt($slug) {
        // Cache
        if (isset($this->cache[$slug])) {
            return $this->cache[$slug];
        }
        
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM " . DB_PREFIX . "prompts 
                WHERE slug = :slug AND is_active = 1
                LIMIT 1
            ");
            $stmt->execute(['slug' => $slug]);
            $prompt = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($prompt) {
                // Decodificar JSON
                $prompt['variables'] = json_decode($prompt['variables'], true) ?? [];
                
                // Cache
                $this->cache[$slug] = $prompt;
            }
            
            return $prompt ?: null;
            
        } catch (Exception $e) {
            error_log("PromptManager Error getting prompt: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Renderizar un prompt con variables
     * 
     * @param string $slug Slug del prompt
     * @param array $variables Variables para reemplazar
     * @return string|null Prompt renderizado o null si error
     */
    public function render($slug, $variables = []) {
        $prompt = $this->getPrompt($slug);
        
        if (!$prompt) {
            error_log("PromptManager: Prompt '{$slug}' no encontrado");
            return null;
        }
        
        return $this->replaceVariables($prompt['template'], $variables);
    }
    
    /**
     * Obtener prompt completo con contexto del plugin
     * 
     * @param string $slug Slug del prompt
     * @param array $variables Variables para reemplazar
     * @return array ['plugin_part', 'api_part', 'full', 'metadata']
     */
    public function getFullPrompt($slug, $variables = []) {
        $prompt = $this->getPrompt($slug);
        
        if (!$prompt) {
            return [
                'success' => false,
                'error' => "Prompt '{$slug}' no encontrado"
            ];
        }
        
        $plugin_part = $this->renderPluginContext($slug, $variables);
        $api_part = $this->replaceVariables($prompt['template'], $variables);
        
        return [
            'success' => true,
            'plugin_part' => $plugin_part,
            'api_part' => $api_part,
            'full' => $plugin_part . ($plugin_part ? "\n\n" : "") . $api_part,
            'metadata' => [
                'name' => $prompt['name'],
                'version' => $prompt['version'],
                'estimated_tokens_input' => $prompt['estimated_tokens_input'],
                'estimated_tokens_output' => $prompt['estimated_tokens_output']
            ]
        ];
    }
    
    /**
     * Guardar o actualizar un prompt
     * 
     * @param array $data Datos del prompt
     * @return array Resultado de la operación
     */
    public function savePrompt($data) {
        try {
            // Validar datos requeridos
            if (empty($data['slug']) || empty($data['name']) || empty($data['template'])) {
                return [
                    'success' => false,
                    'error' => 'slug, name y template son requeridos'
                ];
            }
            
            // Validar variables en el template
            $validation = $this->validateTemplate($data['template'], $data['variables'] ?? []);
            if (!$validation['valid']) {
                return [
                    'success' => false,
                    'error' => 'Template inválido: ' . $validation['error']
                ];
            }
            
            // Verificar si existe
            $existing = $this->getPrompt($data['slug']);
            
            if ($existing) {
                // ACTUALIZAR - Guardar versión anterior en historial
                $this->saveToHistory($existing);
                
                $stmt = $this->db->prepare("
                    UPDATE " . DB_PREFIX . "prompts SET
                        name = :name,
                        description = :description,
                        category = :category,
                        template = :template,
                        plugin_context = :plugin_context,
                        variables = :variables,
                        estimated_tokens_input = :estimated_tokens_input,
                        estimated_tokens_output = :estimated_tokens_output,
                        version = version + 1,
                        updated_by = :updated_by
                    WHERE slug = :slug
                ");
                
                $stmt->execute([
                    'slug' => $data['slug'],
                    'name' => $data['name'],
                    'description' => $data['description'] ?? null,
                    'category' => $data['category'] ?? 'generation',
                    'template' => $data['template'],
                    'plugin_context' => $data['plugin_context'] ?? null,
                    'variables' => json_encode($data['variables'] ?? []),
                    'estimated_tokens_input' => $data['estimated_tokens_input'] ?? 0,
                    'estimated_tokens_output' => $data['estimated_tokens_output'] ?? 0,
                    'updated_by' => $data['updated_by'] ?? 'admin'
                ]);
                
                // Limpiar cache
                unset($this->cache[$data['slug']]);
                
                return [
                    'success' => true,
                    'message' => 'Prompt actualizado correctamente',
                    'version' => $existing['version'] + 1
                ];
                
            } else {
                // CREAR NUEVO
                $stmt = $this->db->prepare("
                    INSERT INTO " . DB_PREFIX . "prompts 
                    (slug, name, description, category, template, plugin_context, variables, 
                     estimated_tokens_input, estimated_tokens_output, updated_by)
                    VALUES 
                    (:slug, :name, :description, :category, :template, :plugin_context, :variables,
                     :estimated_tokens_input, :estimated_tokens_output, :updated_by)
                ");
                
                $stmt->execute([
                    'slug' => $data['slug'],
                    'name' => $data['name'],
                    'description' => $data['description'] ?? null,
                    'category' => $data['category'] ?? 'generation',
                    'template' => $data['template'],
                    'plugin_context' => $data['plugin_context'] ?? null,
                    'variables' => json_encode($data['variables'] ?? []),
                    'estimated_tokens_input' => $data['estimated_tokens_input'] ?? 0,
                    'estimated_tokens_output' => $data['estimated_tokens_output'] ?? 0,
                    'updated_by' => $data['updated_by'] ?? 'admin'
                ]);
                
                return [
                    'success' => true,
                    'message' => 'Prompt creado correctamente',
                    'id' => $this->db->lastInsertId()
                ];
            }
            
        } catch (Exception $e) {
            error_log("PromptManager Error saving prompt: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Error al guardar: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Listar todos los prompts
     * 
     * @param array $filters Filtros opcionales ['category', 'search']
     * @return array Lista de prompts
     */
    public function listPrompts($filters = []) {
        try {
            $sql = "SELECT * FROM " . DB_PREFIX . "prompts WHERE is_active = 1";
            $params = [];
            
            if (!empty($filters['category'])) {
                $sql .= " AND category = :category";
                $params['category'] = $filters['category'];
            }
            
            if (!empty($filters['search'])) {
                $sql .= " AND (name LIKE :search OR slug LIKE :search OR description LIKE :search)";
                $params['search'] = '%' . $filters['search'] . '%';
            }
            
            $sql .= " ORDER BY category, name";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $prompts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Decodificar JSON
            foreach ($prompts as &$prompt) {
                $prompt['variables'] = json_decode($prompt['variables'], true) ?? [];
            }
            
            return $prompts;
            
        } catch (Exception $e) {
            error_log("PromptManager Error listing prompts: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Probar un prompt con datos reales (llamada a OpenAI)
     * 
     * @param string $slug Slug del prompt
     * @param array $test_data Datos de prueba
     * @return array Resultado del test
     */
    public function testPrompt($slug, $test_data) {
        $full_prompt = $this->getFullPrompt($slug, $test_data);
        
        if (!$full_prompt['success']) {
            return $full_prompt;
        }
        
        // Llamar a OpenAI con el prompt completo
        require_once API_BASE_DIR . '/services/OpenAIService.php';
        $openai = new OpenAIService();
        
        $result = $openai->generateContent([
            'prompt' => $full_prompt['full'],
            'max_tokens' => $full_prompt['metadata']['estimated_tokens_output'] ?? 500
        ]);
        
        if ($result['success']) {
            return [
                'success' => true,
                'prompt_used' => $full_prompt['full'],
                'result' => $result['content'],
                'tokens' => $result['usage'] ?? null,
                'cost' => $this->calculateCost($result['usage'] ?? null)
            ];
        }
        
        return [
            'success' => false,
            'error' => $result['error'] ?? 'Error desconocido'
        ];
    }
    
    // ========================================================================
    // MÉTODOS PRIVADOS
    // ========================================================================
    
    /**
     * Reemplazar variables en el template
     * 
     * @param string $template Template con {{variables}}
     * @param array $variables Valores a reemplazar
     * @return string Template con variables reemplazadas
     */
    private function replaceVariables($template, $variables) {
        foreach ($variables as $key => $value) {
            // Convertir arrays a strings
            if (is_array($value)) {
                $value = implode(', ', $value);
            }
            
            // Reemplazar {{variable}}
            $template = str_replace('{{' . $key . '}}', $value, $template);
        }
        
        // Limpiar variables no reemplazadas (dejarlas como están o vacías según preferencia)
        // Por ahora las dejamos como están para debug
        
        return $template;
    }
    
    /**
     * Renderizar contexto del plugin (solo documentación)
     * 
     * @param string $slug Slug del prompt
     * @param array $variables Variables para reemplazar
     * @return string Contexto documentado del plugin
     */
    private function renderPluginContext($slug, $variables) {
        $prompt = $this->getPrompt($slug);
        
        if (!$prompt || empty($prompt['plugin_context'])) {
            return '';
        }
        
        return $this->replaceVariables($prompt['plugin_context'], $variables);
    }
    
    /**
     * Validar template
     * 
     * @param string $template Template a validar
     * @param array $declared_variables Variables declaradas en metadata
     * @return array ['valid' => bool, 'error' => string|null]
     */
    private function validateTemplate($template, $declared_variables) {
        // Extraer variables usadas en el template
        preg_match_all('/\{\{([a-zA-Z0-9_]+)\}\}/', $template, $matches);
        $used_variables = array_unique($matches[1]);
        
        // Extraer variables declaradas
        $declared_keys = array_column($declared_variables, 'key');
        
        // Verificar que todas las variables usadas estén declaradas
        $undeclared = array_diff($used_variables, $declared_keys);
        
        if (!empty($undeclared)) {
            return [
                'valid' => false,
                'error' => 'Variables no declaradas: ' . implode(', ', $undeclared)
            ];
        }
        
        return ['valid' => true];
    }
    
    /**
     * Guardar versión anterior en historial
     * 
     * @param array $prompt Datos del prompt a guardar en historial
     */
    private function saveToHistory($prompt) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO " . DB_PREFIX . "prompts_history 
                (prompt_id, version, template, variables, created_by)
                VALUES (:prompt_id, :version, :template, :variables, :created_by)
            ");
            
            $stmt->execute([
                'prompt_id' => $prompt['id'],
                'version' => $prompt['version'],
                'template' => $prompt['template'],
                'variables' => json_encode($prompt['variables']),
                'created_by' => $prompt['updated_by'] ?? 'system'
            ]);
        } catch (Exception $e) {
            error_log("PromptManager Error saving to history: " . $e->getMessage());
        }
    }
    
    /**
     * Calcular costo estimado
     * 
     * @param array $usage Datos de usage de OpenAI
     * @return float Costo en USD
     */
    private function calculateCost($usage) {
        if (!$usage) return 0;
        
        // Precios aproximados gpt-4o-mini (ajustar según modelo)
        $input_cost_per_1k = 0.00015;  // $0.15 por 1M tokens
        $output_cost_per_1k = 0.0006;  // $0.60 por 1M tokens
        
        $input_tokens = $usage['prompt_tokens'] ?? 0;
        $output_tokens = $usage['completion_tokens'] ?? 0;
        
        $cost = ($input_tokens / 1000 * $input_cost_per_1k) + 
                ($output_tokens / 1000 * $output_cost_per_1k);
        
        return round($cost, 6);
    }
}
