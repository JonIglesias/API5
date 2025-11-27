<?php
/**
 * Auth Class - Sistema de autenticación
 * 
 * @version 4.0
 */

defined('API_ACCESS') or die('Direct access not permitted');

class Auth {
    
    /**
     * Verificar si el usuario está autenticado
     */
    public static function check() {
        return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
    }
    
    /**
     * Requerir autenticación (redirigir si no está autenticado)
     */
    public static function require() {
        if (!self::check()) {
            header('Location: ' . API_BASE_URL . 'admin/login.php');
            exit;
        }
    }
    
    /**
     * Intentar login
     */
    public static function attempt($username, $password) {
        try {
            $db = Database::getInstance();
            
            $stmt = $db->prepare("
                SELECT id, username, password, email 
                FROM " . DB_PREFIX . "admin_users 
                WHERE username = ? AND is_active = 1
            ");
            
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                return false;
            }
            
            // Verificar contraseña
            if (!password_verify($password, $user['password'])) {
                return false;
            }
            
            // Login exitoso
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_user_id'] = $user['id'];
            $_SESSION['admin_username'] = $user['username'];
            $_SESSION['admin_email'] = $user['email'];
            
            // Actualizar último login
            $db->query(
                "UPDATE " . DB_PREFIX . "admin_users SET last_login = NOW() WHERE id = ?",
                [$user['id']]
            );
            
            return true;
            
        } catch (Exception $e) {
            error_log("Auth error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Cerrar sesión
     */
    public static function logout() {
        $_SESSION = [];
        session_destroy();
    }
    
    /**
     * Obtener datos del usuario actual
     */
    public static function user() {
        if (!self::check()) {
            return null;
        }
        
        return [
            'id' => $_SESSION['admin_user_id'] ?? null,
            'username' => $_SESSION['admin_username'] ?? null,
            'email' => $_SESSION['admin_email'] ?? null
        ];
    }
    
    /**
     * Obtener ID del usuario actual
     */
    public static function id() {
        return $_SESSION['admin_user_id'] ?? null;
    }
    
    /**
     * Obtener username del usuario actual
     */
    public static function username() {
        return $_SESSION['admin_username'] ?? null;
    }
}
