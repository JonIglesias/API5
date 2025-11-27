<?php
/**
 * Logger Class
 * 
 * @version 4.0
 */

defined('API_ACCESS') or die('Direct access not permitted');

class Logger {
    
    /**
     * Log de API general
     */
    public static function api($level, $message, $context = []) {
        if (!ENABLE_API_LOG) return;
        self::write('api', $level, $message, $context);
    }
    
    /**
     * Log de sincronización
     */
    public static function sync($level, $message, $context = []) {
        if (!ENABLE_SYNC_LOG) return;
        self::write('sync', $level, $message, $context);
    }
    
    /**
     * Log de webhooks
     */
    public static function webhook($level, $message, $context = []) {
        if (!ENABLE_WEBHOOK_LOG) return;
        self::write('webhook', $level, $message, $context);
    }
    
    /**
     * Escribir log
     */
    private static function write($type, $level, $message, $context = []) {
        // Verificar nivel de log
        $levels = ['debug' => 0, 'info' => 1, 'warning' => 2, 'error' => 3];
        $currentLevel = $levels[LOG_LEVEL] ?? 1;
        $messageLevel = $levels[$level] ?? 1;
        
        if ($messageLevel < $currentLevel) {
            return;
        }
        
        // Preparar entrada de log
        $entry = [
            'timestamp' => date(DATE_FORMAT),
            'level' => strtoupper($level),
            'message' => $message,
            'context' => $context
        ];
        
        // Archivo de log
        $logFile = API_BASE_DIR . "/logs/{$type}.log";
        
        // Escribir
        $logLine = json_encode($entry) . PHP_EOL;
        file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);
        
        // Rotar logs si es necesario
        self::rotateIfNeeded($logFile);
    }
    
    /**
     * Rotar logs antiguos
     */
    private static function rotateIfNeeded($logFile) {
        if (!file_exists($logFile)) {
            return;
        }
        
        $fileTime = filemtime($logFile);
        $daysSinceModified = (time() - $fileTime) / 86400;
        
        if ($daysSinceModified > LOG_MAX_FILES) {
            $archiveFile = $logFile . '.' . date('Y-m-d', $fileTime) . '.archive';
            rename($logFile, $archiveFile);
        }
    }
    
    /**
     * Limpiar logs antiguos
     */
    public static function cleanOldLogs() {
        $logsDir = API_BASE_DIR . '/logs';
        $files = glob($logsDir . '/*.archive');
        
        foreach ($files as $file) {
            $fileTime = filemtime($file);
            $daysSinceModified = (time() - $fileTime) / 86400;
            
            if ($daysSinceModified > LOG_MAX_FILES) {
                unlink($file);
            }
        }
    }
}
