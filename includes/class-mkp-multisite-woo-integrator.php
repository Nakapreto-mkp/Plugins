<?php
/**
 * Classe principal do plugin integrador
 */

if (!defined('ABSPATH')) {
    exit;
}

class MKP_Multisite_Woo_Integrator {
    
    private $subscription_manager;
    private $subdomain_manager;
    private $limiter_integration;
    private $redirect_handler;
    private $email_notifications;
    private $activity_logger;
    private $backup_manager;
    
    public function __construct() {
        add_action('init', array($this, 'init'));
    }
    
    /**
     * Inicializar o plugin
     */
    public function init() {
        // Inicializar componentes
        $this->activity_logger = new MKP_Activity_Logger();
        $this->backup_manager = new MKP_Backup_Manager();
        $this->subscription_manager = new MKP_Subscription_Manager($this->activity_logger);
        $this->subdomain_manager = new MKP_Subdomain_Manager($this->activity_logger, $this->backup_manager);
        $this->limiter_integration = new MKP_Limiter_Integration($this->activity_logger);
        $this->redirect_handler = new MKP_Redirect_Handler();
        $this->email_notifications = new MKP_Email_Notifications();
        
        // Inicializar API REST
        new MKP_REST_API($this->subdomain_manager, $this->subscription_manager);
        
        // Hooks principais
        $this->setup_hooks();
        
        // Log de inicialização
        $this->activity_logger->log(0, 0, 0, 'plugin_init', 'Plugin MKP Multisite WooCommerce Integrator inicializado');
    }
    
    /**
     * Configurar hooks do WordPress e WooCommerce
     */
    private function setup_hooks() {
        // Hooks de assinatura do WooCommerce
        add_action('woocommerce_subscription_status_active', array($this, 'handle_subscription_activation'), 10, 1);
        add_action('woocommerce_subscription_status_on-hold', array($this, 'handle_subscription_suspension'), 10, 1);
        add_action('woocommerce_subscription_status_cancelled', array($this, 'handle_subscription_cancellation'), 10, 1);
        add_action('woocommerce_subscription_status_expired', array($this, 'handle_subscription_expiration'), 10, 1);
        
        // Hook para primeira compra de assinatura
        add_action('woocommerce_checkout_subscription_created', array($this, 'handle_new_subscription'), 10, 2);
        
        // Hooks de redirecionamento
        add_action('template_redirect', array($this->redirect_handler, 'check_site_access'));
        
        // Hooks de integração com Limiter MKP Pro
        add_action('limiter_mkp_page_created', array($this->limiter_integration, 'handle_page_creation'), 10, 2);
        add_action('limiter_mkp_page_deleted', array($this->limiter_integration, 'handle_page_deletion'), 10, 2);
        
        // Hook para verificação periódica de status
        add_action('mkp_daily_subscription_check', array($this, 'daily_subscription_check'));
        if (!wp_next_scheduled('mkp_daily_subscription_check')) {
            wp_schedule_event(time(), 'daily', 'mkp_daily_subscription_check');
        }
    }
    
    /**
     * Manipular nova assinatura criada
     */
    public function handle_new_subscription($subscription, $order) {
        $user_id = $subscription->get_user_id();
        $subscription_id = $subscription->get_id();
        
        // Verificar se o usuário já possui um subdomínio
        $existing_site = $this->subdomain_manager->get_user_site($user_id);
        
        if (!$existing_site) {
            // Criar novo subdomínio
            $site_id = $this->subdomain_manager->create_subdomain($user_id, $subscription);
            
            if ($site_id) {
                // Associar site à assinatura
                $subscription->update_meta_data('_mkp_site_id', $site_id);
                $subscription->save();
                
                // Configurar limites baseados no produto
                $this->limiter_integration->setup_site_limits($site_id, $subscription);
                
                // Enviar email de boas-vindas
                $this->email_notifications->send_welcome_email($user_id, $site_id);
                
                // Log da ação
                $this->activity_logger->log($site_id, $subscription_id, $user_id, 'site_created', 'Subdomínio criado para nova assinatura');
            }
        }
    }
    
    /**
     * Manipular ativação de assinatura
     */
    public function handle_subscription_activation($subscription) {
        $site_id = $subscription->get_meta('_mkp_site_id');
        $user_id = $subscription->get_user_id();
        $subscription_id = $subscription->get_id();
        
        if ($site_id) {
            // Ativar subdomínio
            $this->subdomain_manager->activate_site($site_id);
            
            // Atualizar limites
            $this->limiter_integration->update_site_limits($site_id, $subscription);
            
            // Enviar email de reativação
            $this->email_notifications->send_reactivation_email($user_id, $site_id);
            
            // Log da ação
            $this->activity_logger->log($site_id, $subscription_id, $user_id, 'site_activated', 'Subdomínio ativado - assinatura ativa');
        }
    }
    
    /**
     * Manipular suspensão de assinatura
     */
    public function handle_subscription_suspension($subscription) {
        $site_id = $subscription->get_meta('_mkp_site_id');
        $user_id = $subscription->get_user_id();
        $subscription_id = $subscription->get_id();
        
        if ($site_id) {
            // Fazer backup antes de suspender
            $this->backup_manager->create_site_backup($site_id);
            
            // Suspender subdomínio
            $this->subdomain_manager->suspend_site($site_id);
            
            // Enviar email de suspensão
            $this->email_notifications->send_suspension_email($user_id, $site_id);
            
            // Log da ação
            $this->activity_logger->log($site_id, $subscription_id, $user_id, 'site_suspended', 'Subdomínio suspenso - pagamento em atraso');
        }
    }
    
    /**
     * Manipular cancelamento de assinatura
     */
    public function handle_subscription_cancellation($subscription) {
        $site_id = $subscription->get_meta('_mkp_site_id');
        $user_id = $subscription->get_user_id();
        $subscription_id = $subscription->get_id();
        
        if ($site_id) {
            // Fazer backup antes de arquivar
            $this->backup_manager->create_site_backup($site_id);
            
            // Arquivar subdomínio (não deletar)
            $this->subdomain_manager->archive_site($site_id);
            
            // Enviar email de cancelamento
            $this->email_notifications->send_cancellation_email($user_id, $site_id);
            
            // Log da ação
            $this->activity_logger->log($site_id, $subscription_id, $user_id, 'site_archived', 'Subdomínio arquivado - assinatura cancelada');
        }
    }
    
    /**
     * Manipular expiração de assinatura
     */
    public function handle_subscription_expiration($subscription) {
        // Mesmo comportamento do cancelamento
        $this->handle_subscription_cancellation($subscription);
    }
    
    /**
     * Verificação diária de status das assinaturas
     */
    public function daily_subscription_check() {
        $subscriptions = wcs_get_subscriptions();
        
        foreach ($subscriptions as $subscription) {
            $site_id = $subscription->get_meta('_mkp_site_id');
            
            if ($site_id) {
                $status = $subscription->get_status();
                $current_site_status = $this->subdomain_manager->get_site_status($site_id);
                
                // Sincronizar status se necessário
                if ($this->needs_status_sync($status, $current_site_status)) {
                    switch ($status) {
                        case 'active':
                            $this->handle_subscription_activation($subscription);
                            break;
                        case 'on-hold':
                            $this->handle_subscription_suspension($subscription);
                            break;
                        case 'cancelled':
                        case 'expired':
                            $this->handle_subscription_cancellation($subscription);
                            break;
                    }
                }
            }
        }
        
        // Log da verificação diária
        $this->activity_logger->log(0, 0, 0, 'daily_check', 'Verificação diária de sincronização realizada');
    }
    
    /**
     * Verificar se precisa sincronizar status
     */
    private function needs_status_sync($subscription_status, $site_status) {
        $status_map = array(
            'active' => 'active',
            'on-hold' => 'suspended',
            'cancelled' => 'archived',
            'expired' => 'archived'
        );
        
        $expected_site_status = isset($status_map[$subscription_status]) ? $status_map[$subscription_status] : 'suspended';
        
        return $site_status !== $expected_site_status;
    }
    
    /**
     * Obter instância do manager de subdomínios
     */
    public function get_subdomain_manager() {
        return $this->subdomain_manager;
    }
    
    /**
     * Obter instância do manager de assinaturas
     */
    public function get_subscription_manager() {
        return $this->subscription_manager;
    }
    
    /**
     * Obter instância do logger de atividades
     */
    public function get_activity_logger() {
        return $this->activity_logger;
    }
}