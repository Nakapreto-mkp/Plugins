<?php
/**
 * Classe principal do integrador MKP Multisite WooCommerce
 * 
 * @package MKP_Multisite_Woo
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class MKP_Multisite_Woo_Integrator {
    
    private $subscription_manager;
    private $subdomain_manager;
    private $activity_logger;
    private $compatibility_checker;
    
    public function __construct() {
        $this->init_hooks();
        $this->load_dependencies();
        $this->init_components();
    }
    
    /**
     * Inicializar hooks principais
     */
    private function init_hooks() {
        // Hooks do WooCommerce Subscriptions
        add_action('woocommerce_subscription_status_active', array($this, 'subscription_activated'), 10, 1);
        add_action('woocommerce_subscription_status_on-hold', array($this, 'subscription_suspended'), 10, 1);
        add_action('woocommerce_subscription_status_cancelled', array($this, 'subscription_cancelled'), 10, 1);
        add_action('woocommerce_subscription_renewal_payment_complete', array($this, 'renewal_complete'), 10, 1);
        add_action('woocommerce_subscription_renewal_payment_failed', array($this, 'payment_failed'), 10, 1);
        
        // Hooks do WordPress Multisite
        add_action('wp_initialize_site', array($this, 'initialize_new_site'), 10, 2);
        add_action('wp_delete_site', array($this, 'before_site_deletion'), 10, 1);
        
        // Hooks personalizados
        add_action('mkp_daily_maintenance', array($this, 'daily_maintenance'));
        add_action('mkp_sync_all_subscriptions', array($this, 'sync_all_subscriptions'));
        
        // Cron jobs
        if (!wp_next_scheduled('mkp_daily_maintenance')) {
            wp_schedule_event(time(), 'daily', 'mkp_daily_maintenance');
        }
    }
    
    /**
     * Carregar dependências necessárias
     */
    private function load_dependencies() {
        if (!class_exists('MKP_Activity_Logger')) {
            require_once MKP_MULTISITE_WOO_PLUGIN_DIR . 'logs/class-activity-logger.php';
        }
        
        if (!class_exists('MKP_Subdomain_Manager')) {
            require_once MKP_MULTISITE_WOO_PLUGIN_DIR . 'includes/class-subdomain-manager.php';
        }
    }
    
    /**
     * Inicializar componentes principais
     */
    private function init_components() {
        $this->activity_logger = new MKP_Activity_Logger();
        $this->subscription_manager = new MKP_Subscription_Manager($this->activity_logger);
        $this->subdomain_manager = new MKP_Subdomain_Manager($this->activity_logger);
        $this->compatibility_checker = MKP_Compatibility_Checker::get_instance();
    }
    
    /**
     * Assinatura ativada - criar site se necessário
     */
    public function subscription_activated($subscription) {
        try {
            $subscription_id = $subscription->get_id();
            $user_id = $subscription->get_user_id();
            
            // Verificar se já existe site para esta assinatura
            $existing_site = $this->get_site_by_subscription($subscription_id);
            
            if (!$existing_site) {
                // Criar novo site
                $site_id = $this->create_site_for_subscription($subscription);
                
                if ($site_id) {
                    $this->activity_logger->log(
                        $site_id, 
                        $subscription_id, 
                        $user_id, 
                        'site_created', 
                        'Site criado automaticamente para assinatura ativa'
                    );
                    
                    // Hook personalizado
                    do_action('mkp_after_site_creation', $site_id, $subscription_id, $user_id);
                }
            } else {
                // Reativar site existente
                $this->reactivate_site($existing_site);
                
                $this->activity_logger->log(
                    $existing_site, 
                    $subscription_id, 
                    $user_id, 
                    'site_reactivated', 
                    'Site reativado após assinatura voltar ao status ativo'
                );
            }
            
        } catch (Exception $e) {
            error_log('MKP Multisite Woo: Erro ao processar assinatura ativa - ' . $e->getMessage());
        }
    }
    
    /**
     * Assinatura suspensa - suspender site
     */
    public function subscription_suspended($subscription) {
        try {
            $subscription_id = $subscription->get_id();
            $user_id = $subscription->get_user_id();
            $site_id = $this->get_site_by_subscription($subscription_id);
            
            if ($site_id) {
                $this->suspend_site($site_id);
                
                $this->activity_logger->log(
                    $site_id, 
                    $subscription_id, 
                    $user_id, 
                    'site_suspended', 
                    'Site suspenso devido ao status da assinatura'
                );
                
                // Hook personalizado
                do_action('mkp_site_suspended', $site_id, 'subscription_suspended');
            }
            
        } catch (Exception $e) {
            error_log('MKP Multisite Woo: Erro ao suspender site - ' . $e->getMessage());
        }
    }
    
    /**
     * Assinatura cancelada - desativar site
     */
    public function subscription_cancelled($subscription) {
        try {
            $subscription_id = $subscription->get_id();
            $user_id = $subscription->get_user_id();
            $site_id = $this->get_site_by_subscription($subscription_id);
            
            if ($site_id) {
                // Marcar site para remoção em 30 dias
                $this->schedule_site_removal($site_id, 30);
                
                $this->activity_logger->log(
                    $site_id, 
                    $subscription_id, 
                    $user_id, 
                    'site_scheduled_removal', 
                    'Site agendado para remoção em 30 dias devido ao cancelamento da assinatura'
                );
            }
            
        } catch (Exception $e) {
            error_log('MKP Multisite Woo: Erro ao processar cancelamento - ' . $e->getMessage());
        }
    }
    
    /**
     * Criar site para assinatura
     */
    private function create_site_for_subscription($subscription) {
        $subscription_id = $subscription->get_id();
        $user_id = $subscription->get_user_id();
        $user = get_user_by('id', $user_id);
        
        if (!$user) {
            error_log('MKP Multisite Woo: Usuário não encontrado para assinatura ' . $subscription_id);
            return false;
        }
        
        // Obter detalhes do plano
        $plan_details = $this->subscription_manager->get_subscription_plan_details($subscription);
        
        // Gerar subdomínio único
        $subdomain = $this->subdomain_manager->generate_unique_subdomain($user->user_login, $subscription_id);
        
        // Criar site
        $site_data = array(
            'domain' => $subdomain . '.' . DOMAIN_CURRENT_SITE,
            'path' => '/',
            'title' => 'Site de ' . $user->display_name,
            'user_id' => $user_id,
            'meta' => array(
                '_mkp_subscription_id' => $subscription_id,
                '_mkp_plan_details' => $plan_details,
                '_mkp_created_date' => current_time('mysql'),
                '_mkp_status' => 'active'
            )
        );
        
        // Hook para personalizar dados de criação
        $site_data = apply_filters('mkp_site_creation_data', $site_data, $subscription);
        
        $site_id = wp_insert_site($site_data);
        
        if (is_wp_error($site_id)) {
            error_log('MKP Multisite Woo: Erro ao criar site - ' . $site_id->get_error_message());
            return false;
        }
        
        // Configurar site após criação
        $this->configure_new_site($site_id, $plan_details);
        
        // Enviar email de boas-vindas
        $this->send_welcome_email($site_id, $user_id, $subscription_id);
        
        return $site_id;
    }
    
    /**
     * Configurar site recém-criado
     */
    private function configure_new_site($site_id, $plan_details) {
        switch_to_blog($site_id);
        
        try {
            // Configurar tema padrão
            $default_theme = isset($plan_details['default_theme']) ? $plan_details['default_theme'] : 'twentytwentyfour';
            switch_theme($default_theme);
            
            // Criar páginas básicas
            $home_page = wp_insert_post(array(
                'post_title' => 'Bem-vindo ao seu novo site',
                'post_content' => 'Seu site foi criado com sucesso! Comece criando conteúdo incrível.',
                'post_status' => 'publish',
                'post_type' => 'page'
            ));
            
            // Definir página inicial
            update_option('page_on_front', $home_page);
            update_option('show_on_front', 'page');
            
            // Configurar permalinks
            update_option('permalink_structure', '/%postname%/');
            
            // Aplicar limites do plano
            update_option('_mkp_page_limit', $plan_details['page_limit']);
            update_option('_mkp_storage_limit', $plan_details['storage_limit']);
            update_option('_mkp_features', $plan_details['features']);
            
            // Flush rewrite rules
            flush_rewrite_rules();
            
        } catch (Exception $e) {
            error_log('MKP Multisite Woo: Erro ao configurar novo site - ' . $e->getMessage());
        } finally {
            restore_current_blog();
        }
    }
    
    /**
     * Obter site por ID da assinatura
     */
    private function get_site_by_subscription($subscription_id) {
        $sites = get_sites(array(
            'meta_key' => '_mkp_subscription_id',
            'meta_value' => $subscription_id,
            'number' => 1
        ));
        
        return !empty($sites) ? $sites[0]->blog_id : false;
    }
    
    /**
     * Suspender site
     */
    private function suspend_site($site_id) {
        update_site_meta($site_id, '_mkp_status', 'suspended');
        update_site_meta($site_id, '_mkp_suspended_date', current_time('mysql'));
        
        // Implementar lógica de suspensão (redirecionamento, etc.)
        do_action('mkp_site_suspended', $site_id, 'subscription_suspended');
    }
    
    /**
     * Reativar site
     */
    private function reactivate_site($site_id) {
        update_site_meta($site_id, '_mkp_status', 'active');
        delete_site_meta($site_id, '_mkp_suspended_date');
        
        do_action('mkp_site_activated', $site_id);
    }
    
    /**
     * Agendar remoção de site
     */
    private function schedule_site_removal($site_id, $days = 30) {
        $removal_date = date('Y-m-d H:i:s', strtotime("+{$days} days"));
        update_site_meta($site_id, '_mkp_scheduled_removal', $removal_date);
        update_site_meta($site_id, '_mkp_status', 'pending_removal');
    }
    
    /**
     * Enviar email de boas-vindas
     */
    private function send_welcome_email($site_id, $user_id, $subscription_id) {
        $user = get_user_by('id', $user_id);
        $site_details = get_blog_details($site_id);
        
        if (!$user || !$site_details) {
            return false;
        }
        
        $site_url = 'https://' . $site_details->domain . $site_details->path;
        $admin_url = $site_url . 'wp-admin/';
        
        $subject = 'Seu novo site está pronto!';
        $message = "
            <h2>Olá {$user->display_name},</h2>
            
            <p>Seu site foi criado com sucesso!</p>
            
            <h3>Detalhes do seu site:</h3>
            <ul>
                <li><strong>URL:</strong> <a href='{$site_url}'>{$site_url}</a></li>
                <li><strong>Painel Admin:</strong> <a href='{$admin_url}'>{$admin_url}</a></li>
                <li><strong>Usuário:</strong> {$user->user_login}</li>
            </ul>
            
            <p>Acesse seu site agora e comece a criar conteúdo incrível!</p>
            
            <p>Em caso de dúvidas, entre em contato conosco.</p>
        ";
        
        $headers = array('Content-Type: text/html; charset=UTF-8');
        
        wp_mail($user->user_email, $subject, $message, $headers);
        
        // Log do email enviado
        $this->activity_logger->log(
            $site_id, 
            $subscription_id, 
            $user_id, 
            'welcome_email_sent', 
            'Email de boas-vindas enviado para ' . $user->user_email
        );
    }
    
    /**
     * Manutenção diária
     */
    public function daily_maintenance() {
        $this->cleanup_old_logs();
        $this->process_scheduled_removals();
        $this->update_usage_statistics();
        $this->send_daily_reports();
    }
    
    /**
     * Limpar logs antigos
     */
    private function cleanup_old_logs() {
        global $wpdb;
        
        $table_name = $wpdb->base_prefix . 'mkp_activity_logs';
        $days_to_keep = apply_filters('mkp_logs_retention_days', 30);
        
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$table_name} WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days_to_keep
        ));
    }
    
    /**
     * Processar remoções agendadas
     */
    private function process_scheduled_removals() {
        $sites = get_sites(array(
            'meta_key' => '_mkp_status',
            'meta_value' => 'pending_removal',
            'number' => 100
        ));
        
        foreach ($sites as $site) {
            $removal_date = get_site_meta($site->blog_id, '_mkp_scheduled_removal', true);
            
            if ($removal_date && strtotime($removal_date) <= time()) {
                // Fazer backup antes de remover
                do_action('mkp_before_site_removal', $site->blog_id);
                
                // Remover site
                wp_delete_site($site->blog_id);
                
                $this->activity_logger->log(
                    $site->blog_id, 
                    0, 
                    0, 
                    'site_removed', 
                    'Site removido automaticamente após período de carência'
                );
            }
        }
    }
    
    /**
     * Atualizar estatísticas de uso
     */
    private function update_usage_statistics() {
        $sites = get_sites(array(
            'meta_key' => '_mkp_subscription_id',
            'number' => 0
        ));
        
        foreach ($sites as $site) {
            $this->update_site_usage_stats($site->blog_id);
        }
    }
    
    /**
     * Atualizar estatísticas de um site específico
     */
    private function update_site_usage_stats($site_id) {
        switch_to_blog($site_id);
        
        try {
            $stats = array(
                'pages_count' => wp_count_posts('page')->publish + wp_count_posts('post')->publish,
                'storage_used' => $this->calculate_site_storage($site_id),
                'users_count' => count_users()['total_users'],
                'last_updated' => current_time('mysql')
            );
            
            update_site_meta($site_id, '_mkp_usage_stats', $stats);
            
        } catch (Exception $e) {
            error_log('MKP Multisite Woo: Erro ao atualizar estatísticas do site ' . $site_id . ' - ' . $e->getMessage());
        } finally {
            restore_current_blog();
        }
    }
    
    /**
     * Calcular armazenamento usado pelo site
     */
    private function calculate_site_storage($site_id) {
        $upload_dir = wp_upload_dir();
        $storage_used = 0;
        
        if (is_dir($upload_dir['basedir'])) {
            $storage_used = $this->get_directory_size($upload_dir['basedir']);
        }
        
        return round($storage_used / 1024 / 1024, 2); // MB
    }
    
    /**
     * Obter tamanho de diretório recursivamente
     */
    private function get_directory_size($directory) {
        $size = 0;
        
        if (is_dir($directory)) {
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory)) as $file) {
                if ($file->isFile()) {
                    $size += $file->getSize();
                }
            }
        }
        
        return $size;
    }
    
    /**
     * Enviar relatórios diários
     */
    private function send_daily_reports() {
        $stats = $this->subscription_manager->get_subscription_stats();
        
        // Enviar para administradores da rede
        $network_admins = get_super_admins();
        
        foreach ($network_admins as $admin_login) {
            $admin = get_user_by('login', $admin_login);
            
            if ($admin) {
                $this->send_daily_report_email($admin, $stats);
            }
        }
    }
    
    /**
     * Enviar email de relatório diário
     */
    private function send_daily_report_email($admin, $stats) {
        $subject = 'Relatório Diário - MKP Multisite WooCommerce';
        $message = "
            <h2>Relatório Diário</h2>
            <p>Data: " . date('d/m/Y') . "</p>
            
            <h3>Estatísticas Gerais:</h3>
            <ul>
                <li>Total de assinaturas: {$stats['total']}</li>
                <li>Sites ativos: {$stats['active']}</li>
                <li>Sites suspensos: {$stats['suspended']}</li>
                <li>Assinaturas canceladas: {$stats['cancelled']}</li>
            </ul>
            
            <p>Acesse o painel administrativo para mais detalhes.</p>
        ";
        
        $headers = array('Content-Type: text/html; charset=UTF-8');
        
        wp_mail($admin->user_email, $subject, $message, $headers);
    }
    
    /**
     * Sincronizar todas as assinaturas
     */
    public function sync_all_subscriptions() {
        $subscriptions = $this->subscription_manager->get_subscriptions_safe();
        
        foreach ($subscriptions as $subscription) {
            $this->sync_subscription($subscription);
        }
    }
    
    /**
     * Sincronizar assinatura específica
     */
    private function sync_subscription($subscription) {
        $subscription_id = $subscription->get_id();
        $status = $subscription->get_status();
        $site_id = $this->get_site_by_subscription($subscription_id);
        
        // Sincronizar status
        switch ($status) {
            case 'active':
                if ($site_id) {
                    $this->reactivate_site($site_id);
                } else {
                    $this->create_site_for_subscription($subscription);
                }
                break;
                
            case 'on-hold':
                if ($site_id) {
                    $this->suspend_site($site_id);
                }
                break;
                
            case 'cancelled':
                if ($site_id) {
                    $this->schedule_site_removal($site_id);
                }
                break;
        }
        
        // Hook para sincronização personalizada
        do_action('mkp_subscription_synced', $subscription_id, array(
            'status' => $status,
            'site_id' => $site_id
        ));
    }
    
    /**
     * Inicializar novo site (hook do WordPress)
     */
    public function initialize_new_site($new_site, $args) {
        // Verificar se é um site criado pelo nosso plugin
        if (isset($args['meta']['_mkp_subscription_id'])) {
            $subscription_id = $args['meta']['_mkp_subscription_id'];
            
            $this->activity_logger->log(
                $new_site->blog_id, 
                $subscription_id, 
                $args['user_id'], 
                'site_initialized', 
                'Site inicializado pelo WordPress Multisite'
            );
        }
    }
    
    /**
     * Antes da deleção do site (hook do WordPress)
     */
    public function before_site_deletion($old_site) {
        $subscription_id = get_site_meta($old_site->blog_id, '_mkp_subscription_id', true);
        
        if ($subscription_id) {
            // Fazer backup final
            do_action('mkp_before_site_deletion', $old_site->blog_id, $subscription_id);
            
            $this->activity_logger->log(
                $old_site->blog_id, 
                $subscription_id, 
                0, 
                'site_deletion_started', 
                'Início do processo de deleção do site'
            );
        }
    }
    
    /**
     * Renovação de pagamento completa
     */
    public function renewal_complete($subscription) {
        $subscription_id = $subscription->get_id();
        $user_id = $subscription->get_user_id();
        $site_id = $this->get_site_by_subscription($subscription_id);
        
        if ($site_id) {
            // Garantir que o site está ativo
            $this->reactivate_site($site_id);
            
            $this->activity_logger->log(
                $site_id, 
                $subscription_id, 
                $user_id, 
                'renewal_complete', 
                'Renovação de pagamento processada com sucesso'
            );
        }
    }
    
    /**
     * Falha no pagamento
     */
    public function payment_failed($subscription) {
        $subscription_id = $subscription->get_id();
        $user_id = $subscription->get_user_id();
        $site_id = $this->get_site_by_subscription($subscription_id);
        
        if ($site_id) {
            // Enviar aviso de pagamento pendente
            $this->send_payment_due_notification($site_id, $subscription);
            
            $this->activity_logger->log(
                $site_id, 
                $subscription_id, 
                $user_id, 
                'payment_failed', 
                'Falha no pagamento da renovação - notificação enviada'
            );
        }
    }
    
    /**
     * Enviar notificação de pagamento pendente
     */
    private function send_payment_due_notification($site_id, $subscription) {
        $user = get_user_by('id', $subscription->get_user_id());
        $site_details = get_blog_details($site_id);
        
        if (!$user || !$site_details) {
            return false;
        }
        
        $payment_url = $subscription->get_checkout_payment_url();
        $site_name = $site_details->blogname;
        
        $subject = 'Pagamento pendente - ' . $site_name;
        $message = "
            <h2>Olá {$user->display_name},</h2>
            
            <p>Sua assinatura do site <strong>{$site_name}</strong> tem um pagamento pendente.</p>
            
            <h3>Detalhes:</h3>
            <ul>
                <li><strong>Valor:</strong> " . $subscription->get_total() . "</li>
                <li><strong>Status:</strong> Pagamento pendente</li>
            </ul>
            
            <p><a href='{$payment_url}' style='background-color: #007cba; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Efetuar Pagamento</a></p>
            
            <p><strong>Importante:</strong> Após 7 dias sem pagamento, seu site será suspenso temporariamente.</p>
            
            <p>Em caso de dúvidas, entre em contato conosco.</p>
        ";
        
        $headers = array('Content-Type: text/html; charset=UTF-8');
        
        return wp_mail($user->user_email, $subject, $message, $headers);
    }
}