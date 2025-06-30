<?php
/**
 * Sistema de logs de atividades
 */

if (!defined('ABSPATH')) {
    exit;
}

class MKP_Activity_Logger {
    
    private $table_name;
    
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->base_prefix . 'mkp_activity_logs';
    }
    
    /**
     * Registrar log de atividade
     */
    public function log($site_id, $subscription_id, $user_id, $action, $details = '') {
        global $wpdb;
        
        $result = $wpdb->insert(
            $this->table_name,
            array(
                'site_id' => intval($site_id),
                'subscription_id' => intval($subscription_id),
                'user_id' => intval($user_id),
                'action' => sanitize_text_field($action),
                'details' => sanitize_textarea_field($details),
                'created_at' => current_time('mysql')
            ),
            array('%d', '%d', '%d', '%s', '%s', '%s')
        );
        
        if ($result === false) {
            error_log('MKP Activity Logger: Falha ao inserir log - ' . $wpdb->last_error);
        }
        
        // Limpar logs antigos se necessário
        $this->cleanup_old_logs();
        
        return $result !== false;
    }
    
    /**
     * Obter logs recentes
     */
    public function get_recent_logs($limit = 50) {
        global $wpdb;
        
        $logs = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table_name} 
             ORDER BY created_at DESC 
             LIMIT %d",
            $limit
        ));
        
        return $logs ?: array();
    }
    
    /**
     * Obter logs com filtros
     */
    public function get_logs($limit = 50, $action = null, $site_id = null, $user_id = null, $date_from = null, $date_to = null) {
        global $wpdb;
        
        $where_conditions = array();
        $where_values = array();
        
        if ($action) {
            $where_conditions[] = 'action = %s';
            $where_values[] = $action;
        }
        
        if ($site_id) {
            $where_conditions[] = 'site_id = %d';
            $where_values[] = $site_id;
        }
        
        if ($user_id) {
            $where_conditions[] = 'user_id = %d';
            $where_values[] = $user_id;
        }
        
        if ($date_from) {
            $where_conditions[] = 'created_at >= %s';
            $where_values[] = $date_from;
        }
        
        if ($date_to) {
            $where_conditions[] = 'created_at <= %s';
            $where_values[] = $date_to;
        }
        
        $where_clause = '';
        if (!empty($where_conditions)) {
            $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
        }
        
        $query = "SELECT * FROM {$this->table_name} 
                  $where_clause 
                  ORDER BY created_at DESC 
                  LIMIT %d";
        
        $where_values[] = $limit;
        
        $logs = $wpdb->get_results($wpdb->prepare($query, $where_values));
        
        return $logs ?: array();
    }
    
    /**
     * Obter logs por site
     */
    public function get_site_logs($site_id, $limit = 25) {
        return $this->get_logs($limit, null, $site_id);
    }
    
    /**
     * Obter logs por usuário
     */
    public function get_user_logs($user_id, $limit = 25) {
        return $this->get_logs($limit, null, null, $user_id);
    }
    
    /**
     * Obter logs por ação
     */
    public function get_action_logs($action, $limit = 25) {
        return $this->get_logs($limit, $action);
    }
    
    /**
     * Contar logs por período
     */
    public function count_logs_by_period($period = 'today') {
        global $wpdb;
        
        $date_condition = '';
        
        switch ($period) {
            case 'today':
                $date_condition = "DATE(created_at) = CURDATE()";
                break;
            case 'yesterday':
                $date_condition = "DATE(created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
                break;
            case 'week':
                $date_condition = "created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
                break;
            case 'month':
                $date_condition = "created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
                break;
            default:
                $date_condition = "1=1";
        }
        
        $count = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE $date_condition"
        );
        
        return intval($count);
    }
    
    /**
     * Obter estatísticas de ações
     */
    public function get_action_stats($days = 30) {
        global $wpdb;
        
        $stats = $wpdb->get_results($wpdb->prepare(
            "SELECT action, COUNT(*) as count 
             FROM {$this->table_name} 
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
             GROUP BY action 
             ORDER BY count DESC",
            $days
        ));
        
        $formatted_stats = array();
        foreach ($stats as $stat) {
            $formatted_stats[$stat->action] = intval($stat->count);
        }
        
        return $formatted_stats;
    }
    
    /**
     * Obter atividade por hora (últimas 24h)
     */
    public function get_hourly_activity() {
        global $wpdb;
        
        $activity = $wpdb->get_results(
            "SELECT HOUR(created_at) as hour, COUNT(*) as count 
             FROM {$this->table_name} 
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
             GROUP BY HOUR(created_at) 
             ORDER BY hour"
        );
        
        // Criar array com todas as horas (0-23)
        $hourly_data = array_fill(0, 24, 0);
        
        foreach ($activity as $hour_data) {
            $hourly_data[intval($hour_data->hour)] = intval($hour_data->count);
        }
        
        return $hourly_data;
    }
    
    /**
     * Exportar logs para CSV
     */
    public function export_logs_csv($filters = array()) {
        $logs = $this->get_logs(
            $filters['limit'] ?? 1000,
            $filters['action'] ?? null,
            $filters['site_id'] ?? null,
            $filters['user_id'] ?? null,
            $filters['date_from'] ?? null,
            $filters['date_to'] ?? null
        );
        
        $filename = 'mkp-activity-logs-' . date('Y-m-d-H-i-s') . '.csv';
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        
        // Cabeçalhos
        fputcsv($output, array(
            'ID',
            'Data/Hora',
            'Site ID',
            'Site Nome',
            'Assinatura ID',
            'Usuário ID',
            'Usuário Nome',
            'Ação',
            'Detalhes'
        ));
        
        // Dados
        foreach ($logs as $log) {
            $site_name = '';
            if ($log->site_id > 0) {
                $site = get_blog_details($log->site_id);
                $site_name = $site ? $site->blogname : 'Site não encontrado';
            }
            
            $user_name = '';
            if ($log->user_id > 0) {
                $user = get_user_by('id', $log->user_id);
                $user_name = $user ? $user->display_name : 'Usuário não encontrado';
            }
            
            fputcsv($output, array(
                $log->id,
                $log->created_at,
                $log->site_id,
                $site_name,
                $log->subscription_id,
                $log->user_id,
                $user_name,
                $log->action,
                $log->details
            ));
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * Limpar logs antigos
     */
    public function cleanup_old_logs($days = null) {
        global $wpdb;
        
        if ($days === null) {
            $days = get_network_option(null, 'mkp_log_retention_days', 90);
        }
        
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->table_name} 
             WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        ));
        
        if ($deleted > 0) {
            $this->log(0, 0, 0, 'logs_cleaned', "Removidos $deleted logs antigos (mais de $days dias)");
        }
        
        return $deleted;
    }
    
    /**
     * Obter tamanho da tabela de logs
     */
    public function get_table_size() {
        global $wpdb;
        
        $size = $wpdb->get_var($wpdb->prepare(
            "SELECT ROUND(((data_length + index_length) / 1024 / 1024), 2) AS 'size_mb'
             FROM information_schema.tables 
             WHERE table_schema = %s AND table_name = %s",
            $wpdb->dbname,
            $this->table_name
        ));
        
        return floatval($size);
    }
    
    /**
     * Verificar saúde da tabela de logs
     */
    public function check_table_health() {
        global $wpdb;
        
        $total_logs = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name}");
        $table_size = $this->get_table_size();
        $oldest_log = $wpdb->get_var("SELECT MIN(created_at) FROM {$this->table_name}");
        $newest_log = $wpdb->get_var("SELECT MAX(created_at) FROM {$this->table_name}");
        
        return array(
            'total_logs' => intval($total_logs),
            'table_size_mb' => $table_size,
            'oldest_log' => $oldest_log,
            'newest_log' => $newest_log,
            'avg_logs_per_day' => $this->calculate_avg_logs_per_day()
        );
    }
    
    /**
     * Calcular média de logs por dia
     */
    private function calculate_avg_logs_per_day() {
        global $wpdb;
        
        $result = $wpdb->get_row(
            "SELECT 
                COUNT(*) as total_logs,
                DATEDIFF(MAX(created_at), MIN(created_at)) as days_span
             FROM {$this->table_name}"
        );
        
        if ($result && $result->days_span > 0) {
            return round($result->total_logs / $result->days_span, 2);
        }
        
        return 0;
    }
    
    /**
     * Logs específicos de segurança
     */
    public function log_security_event($event_type, $details, $user_id = 0, $ip_address = null) {
        if ($ip_address === null) {
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        }
        
        $security_details = array(
            'event_type' => $event_type,
            'ip_address' => $ip_address,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'details' => $details
        );
        
        return $this->log(0, 0, $user_id, 'security_event', json_encode($security_details));
    }
    
    /**
     * Logs de performance
     */
    public function log_performance($operation, $execution_time, $memory_usage = null) {
        if ($memory_usage === null) {
            $memory_usage = memory_get_usage(true);
        }
        
        $performance_details = array(
            'operation' => $operation,
            'execution_time' => $execution_time,
            'memory_usage' => $memory_usage,
            'peak_memory' => memory_get_peak_usage(true)
        );
        
        return $this->log(0, 0, 0, 'performance_log', json_encode($performance_details));
    }
    
    /**
     * Obter logs de segurança
     */
    public function get_security_logs($limit = 50) {
        return $this->get_action_logs('security_event', $limit);
    }
    
    /**
     * Obter logs de performance
     */
    public function get_performance_logs($limit = 50) {
        return $this->get_action_logs('performance_log', $limit);
    }
}