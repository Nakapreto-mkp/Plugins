<?php
/**
 * Logger de atividades para MKP Multisite WooCommerce
 * 
 * @package MKP_Multisite_Woo
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class MKP_Activity_Logger {
    
    private $table_name;
    
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->base_prefix . 'mkp_activity_logs';
        $this->init_hooks();
    }
    
    /**
     * Inicializar hooks
     */
    private function init_hooks() {
        add_action('wp_ajax_mkp_export_logs', array($this, 'export_logs'));
        add_action('wp_ajax_mkp_clear_logs', array($this, 'clear_old_logs'));
        add_action('mkp_daily_maintenance', array($this, 'cleanup_old_logs'));
    }
    
    /**
     * Registrar atividade no log
     */
    public function log($site_id, $subscription_id, $user_id, $action, $details = '', $severity = 'info') {
        global $wpdb;
        
        try {
            // Validar parâmetros
            $site_id = absint($site_id);
            $subscription_id = absint($subscription_id);
            $user_id = absint($user_id);
            $action = sanitize_text_field($action);
            $details = sanitize_textarea_field($details);
            $severity = in_array($severity, array('info', 'warning', 'error', 'critical')) ? $severity : 'info';
            
            // Preparar dados do contexto
            $context = array(
                'ip_address' => $this->get_client_ip(),
                'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 255) : '',
                'request_uri' => isset($_SERVER['REQUEST_URI']) ? substr($_SERVER['REQUEST_URI'], 0, 255) : '',
                'php_memory_usage' => memory_get_usage(true),
                'execution_time' => microtime(true) - (defined('WP_START_TIMESTAMP') ? WP_START_TIMESTAMP : $_SERVER['REQUEST_TIME_FLOAT'])
            );
            
            $result = $wpdb->insert(
                $this->table_name,
                array(
                    'site_id' => $site_id,
                    'subscription_id' => $subscription_id,
                    'user_id' => $user_id,
                    'action' => $action,
                    'details' => $details,
                    'severity' => $severity,
                    'context' => wp_json_encode($context),
                    'created_at' => current_time('mysql')
                ),
                array('%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s')
            );
            
            if ($result === false) {
                error_log('MKP Activity Logger: Falha ao inserir log - ' . $wpdb->last_error);
            }
            
            return $result !== false;
            
        } catch (Exception $e) {
            error_log('MKP Activity Logger: Exceção ao registrar log - ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Obter IP do cliente
     */
    private function get_client_ip() {
        $ip_keys = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR');
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                $ip = $_SERVER[$key];
                
                if (strpos($ip, ',') !== false) {
                    $ip = explode(',', $ip)[0];
                }
                
                $ip = trim($ip);
                
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown';
    }
    
    /**
     * Limpar logs antigos
     */
    public function cleanup_old_logs($days = null) {
        global $wpdb;
        
        if ($days === null) {
            $days = apply_filters('mkp_logs_retention_days', 30);
        }
        
        $result = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->table_name} WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        ));
        
        if ($result !== false) {
            $this->log(0, 0, 0, 'logs_cleanup', "Limpeza automática realizada: {$result} logs removidos", 'info');
        }
        
        return $result;
    }
}