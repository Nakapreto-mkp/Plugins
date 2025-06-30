<?php
/**
 * API REST personalizada para MKP Multisite WooCommerce
 * 
 * @package MKP_Multisite_Woo
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class MKP_REST_API {
    
    private $namespace = 'mkp/v1';
    private $activity_logger;
    
    public function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'));
        $this->activity_logger = new MKP_Activity_Logger();
    }
    
    /**
     * Registrar rotas da API
     */
    public function register_routes() {
        // Rota para listar sites do usuário
        register_rest_route($this->namespace, '/sites', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_user_sites'),
            'permission_callback' => array($this, 'check_user_permissions')
        ));
        
        // Rota para criar novo site
        register_rest_route($this->namespace, '/sites', array(
            'methods' => 'POST',
            'callback' => array($this, 'create_site'),
            'permission_callback' => array($this, 'check_user_permissions'),
            'args' => array(
                'subscription_id' => array(
                    'required' => true,
                    'type' => 'integer'
                ),
                'site_name' => array(
                    'required' => true,
                    'type' => 'string'
                ),
                'plan' => array(
                    'required' => false,
                    'type' => 'string'
                )
            )
        ));
        
        // Rota para obter estatísticas de um site
        register_rest_route($this->namespace, '/sites/(?P<site_id>\d+)/stats', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_site_stats'),
            'permission_callback' => array($this, 'check_site_permissions'),
            'args' => array(
                'site_id' => array(
                    'required' => true,
                    'type' => 'integer'
                )
            )
        ));
        
        // Rota para atualizar status do site
        register_rest_route($this->namespace, '/sites/(?P<site_id>\d+)/status', array(
            'methods' => 'PUT',
            'callback' => array($this, 'update_site_status'),
            'permission_callback' => array($this, 'check_site_permissions'),
            'args' => array(
                'site_id' => array(
                    'required' => true,
                    'type' => 'integer'
                ),
                'status' => array(
                    'required' => true,
                    'type' => 'string',
                    'enum' => array('active', 'suspended', 'pending_removal')
                )
            )
        ));
        
        // Rota para sincronizar assinatura
        register_rest_route($this->namespace, '/sync-subscription', array(
            'methods' => 'POST',
            'callback' => array($this, 'sync_subscription'),
            'permission_callback' => array($this, 'check_admin_permissions'),
            'args' => array(
                'subscription_id' => array(
                    'required' => true,
                    'type' => 'integer'
                )
            )
        ));
        
        // Webhook para WooCommerce
        register_rest_route($this->namespace, '/webhook/subscription-updated', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_subscription_webhook'),
            'permission_callback' => array($this, 'check_webhook_permissions')
        ));
        
        // Endpoints para mobile
        register_rest_route($this->namespace, '/mobile/dashboard', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_mobile_dashboard'),
            'permission_callback' => array($this, 'check_user_permissions')
        ));
        
        register_rest_route($this->namespace, '/mobile/register-device', array(
            'methods' => 'POST',
            'callback' => array($this, 'register_mobile_device'),
            'permission_callback' => array($this, 'check_user_permissions'),
            'args' => array(
                'device_token' => array(
                    'required' => true,
                    'type' => 'string'
                ),
                'platform' => array(
                    'required' => true,
                    'type' => 'string',
                    'enum' => array('android', 'ios')
                )
            )
        ));
    }
    
    /**
     * Obter sites do usuário
     */
    public function get_user_sites($request) {
        $user_id = get_current_user_id();
        
        try {
            // Buscar sites onde o usuário é proprietário
            $sites = get_sites(array(
                'meta_query' => array(
                    array(
                        'key' => '_mkp_subscription_id',
                        'compare' => 'EXISTS'
                    )
                )
            ));
            
            $user_sites = array();
            
            foreach ($sites as $site) {
                $subscription_id = get_site_meta($site->blog_id, '_mkp_subscription_id', true);
                
                if ($subscription_id && function_exists('wcs_get_subscription')) {
                    $subscription = wcs_get_subscription($subscription_id);
                    
                    if ($subscription && $subscription->get_user_id() == $user_id) {
                        $site_details = get_blog_details($site->blog_id);
                        $usage_stats = get_site_meta($site->blog_id, '_mkp_usage_stats', true);
                        $plan_details = get_site_meta($site->blog_id, '_mkp_plan_details', true);
                        
                        $user_sites[] = array(
                            'site_id' => $site->blog_id,
                            'site_name' => $site_details->blogname,
                            'site_url' => 'https://' . $site_details->domain . $site_details->path,
                            'admin_url' => 'https://' . $site_details->domain . $site_details->path . 'wp-admin/',
                            'status' => get_site_meta($site->blog_id, '_mkp_status', true) ?: 'active',
                            'subscription_id' => $subscription_id,
                            'subscription_status' => $subscription->get_status(),
                            'usage_stats' => $usage_stats,
                            'plan_details' => $plan_details,
                            'created_date' => $site_details->registered
                        );
                    }
                }
            }
            
            return rest_ensure_response($user_sites);
            
        } catch (Exception $e) {
            return new WP_Error('api_error', 'Erro ao obter sites: ' . $e->getMessage(), array('status' => 500));
        }
    }
    
    /**
     * Criar novo site
     */
    public function create_site($request) {
        $subscription_id = $request->get_param('subscription_id');
        $site_name = sanitize_text_field($request->get_param('site_name'));
        $plan = sanitize_text_field($request->get_param('plan'));
        
        try {
            // Verificar se a assinatura existe e pertence ao usuário
            if (!function_exists('wcs_get_subscription')) {
                return new WP_Error('wcs_not_available', 'WooCommerce Subscriptions não está disponível', array('status' => 400));
            }
            
            $subscription = wcs_get_subscription($subscription_id);
            
            if (!$subscription || $subscription->get_user_id() != get_current_user_id()) {
                return new WP_Error('invalid_subscription', 'Assinatura inválida ou não pertence ao usuário', array('status' => 403));
            }
            
            // Verificar se já existe um site para esta assinatura
            $existing_sites = get_sites(array(
                'meta_key' => '_mkp_subscription_id',
                'meta_value' => $subscription_id
            ));
            
            if (!empty($existing_sites)) {
                return new WP_Error('site_exists', 'Já existe um site para esta assinatura', array('status' => 400));
            }
            
            // Criar site usando a classe principal
            if (!class_exists('MKP_Multisite_Woo_Integrator')) {
                return new WP_Error('integrator_not_available', 'Integrador não está disponível', array('status' => 500));
            }
            
            // Simular ativação de assinatura para criar site
            $integrator = new MKP_Multisite_Woo_Integrator();
            $integrator->subscription_activated($subscription);
            
            // Buscar o site criado
            $new_sites = get_sites(array(
                'meta_key' => '_mkp_subscription_id',
                'meta_value' => $subscription_id
            ));
            
            if (empty($new_sites)) {
                return new WP_Error('site_creation_failed', 'Falha ao criar site', array('status' => 500));
            }
            
            $site = $new_sites[0];
            $site_details = get_blog_details($site->blog_id);
            
            return rest_ensure_response(array(
                'site_id' => $site->blog_id,
                'site_name' => $site_details->blogname,
                'site_url' => 'https://' . $site_details->domain . $site_details->path,
                'admin_url' => 'https://' . $site_details->domain . $site_details->path . 'wp-admin/',
                'status' => 'active',
                'message' => 'Site criado com sucesso'
            ));
            
        } catch (Exception $e) {
            return new WP_Error('api_error', 'Erro ao criar site: ' . $e->getMessage(), array('status' => 500));
        }
    }
    
    /**
     * Obter estatísticas do site
     */
    public function get_site_stats($request) {
        $site_id = $request->get_param('site_id');
        
        try {
            $usage_stats = get_site_meta($site_id, '_mkp_usage_stats', true);
            $plan_details = get_site_meta($site_id, '_mkp_plan_details', true);
            $site_status = get_site_meta($site_id, '_mkp_status', true);
            
            // Calcular estatísticas em tempo real se necessário
            if (!$usage_stats || !isset($usage_stats['last_updated']) || 
                strtotime($usage_stats['last_updated']) < strtotime('-1 hour')) {
                
                switch_to_blog($site_id);
                
                $usage_stats = array(
                    'pages_count' => wp_count_posts('page')->publish + wp_count_posts('post')->publish,
                    'users_count' => count_users()['total_users'],
                    'storage_used' => $this->calculate_site_storage($site_id),
                    'last_updated' => current_time('mysql')
                );
                
                update_site_meta($site_id, '_mkp_usage_stats', $usage_stats);
                
                restore_current_blog();
            }
            
            return rest_ensure_response(array(
                'site_id' => $site_id,
                'status' => $site_status ?: 'active',
                'usage' => $usage_stats,
                'limits' => $plan_details,
                'usage_percentage' => $this->calculate_usage_percentage($usage_stats, $plan_details)
            ));
            
        } catch (Exception $e) {
            return new WP_Error('api_error', 'Erro ao obter estatísticas: ' . $e->getMessage(), array('status' => 500));
        }
    }
    
    /**
     * Atualizar status do site
     */
    public function update_site_status($request) {
        $site_id = $request->get_param('site_id');
        $status = $request->get_param('status');
        
        try {
            update_site_meta($site_id, '_mkp_status', $status);
            
            // Log da mudança de status
            $this->activity_logger->log(
                $site_id,
                get_site_meta($site_id, '_mkp_subscription_id', true),
                get_current_user_id(),
                'status_changed_via_api',
                "Status alterado para: {$status}",
                'info'
            );
            
            return rest_ensure_response(array(
                'site_id' => $site_id,
                'status' => $status,
                'message' => 'Status atualizado com sucesso'
            ));
            
        } catch (Exception $e) {
            return new WP_Error('api_error', 'Erro ao atualizar status: ' . $e->getMessage(), array('status' => 500));
        }
    }
    
    /**
     * Sincronizar assinatura
     */
    public function sync_subscription($request) {
        $subscription_id = $request->get_param('subscription_id');
        
        try {
            if (!function_exists('wcs_get_subscription')) {
                return new WP_Error('wcs_not_available', 'WooCommerce Subscriptions não está disponível', array('status' => 400));
            }
            
            $subscription = wcs_get_subscription($subscription_id);
            
            if (!$subscription) {
                return new WP_Error('invalid_subscription', 'Assinatura não encontrada', array('status' => 404));
            }
            
            // Executar sincronização
            $integrator = new MKP_Multisite_Woo_Integrator();
            
            switch ($subscription->get_status()) {
                case 'active':
                    $integrator->subscription_activated($subscription);
                    break;
                case 'on-hold':
                    $integrator->subscription_suspended($subscription);
                    break;
                case 'cancelled':
                    $integrator->subscription_cancelled($subscription);
                    break;
            }
            
            return rest_ensure_response(array(
                'subscription_id' => $subscription_id,
                'status' => $subscription->get_status(),
                'message' => 'Sincronização realizada com sucesso'
            ));
            
        } catch (Exception $e) {
            return new WP_Error('api_error', 'Erro na sincronização: ' . $e->getMessage(), array('status' => 500));
        }
    }
    
    /**
     * Gerenciar webhook de assinatura
     */
    public function handle_subscription_webhook($request) {
        $data = $request->get_json_params();
        
        try {
            if (!isset($data['subscription_id']) || !isset($data['status'])) {
                return new WP_Error('invalid_webhook_data', 'Dados do webhook inválidos', array('status' => 400));
            }
            
            $subscription_id = absint($data['subscription_id']);
            $status = sanitize_text_field($data['status']);
            
            // Log do webhook recebido
            $this->activity_logger->log(
                0,
                $subscription_id,
                0,
                'webhook_received',
                "Webhook recebido para assinatura {$subscription_id} com status {$status}",
                'info'
            );
            
            // Processar webhook
            $sync_result = $this->sync_subscription(new WP_REST_Request('POST', '', array('subscription_id' => $subscription_id)));
            
            if (is_wp_error($sync_result)) {
                return $sync_result;
            }
            
            return rest_ensure_response(array(
                'subscription_id' => $subscription_id,
                'processed' => true,
                'message' => 'Webhook processado com sucesso'
            ));
            
        } catch (Exception $e) {
            return new WP_Error('webhook_error', 'Erro ao processar webhook: ' . $e->getMessage(), array('status' => 500));
        }
    }
    
    /**
     * Dashboard mobile
     */
    public function get_mobile_dashboard($request) {
        $user_id = get_current_user_id();
        
        try {
            $sites_response = $this->get_user_sites($request);
            $sites = $sites_response->get_data();
            
            $dashboard_data = array(
                'user_info' => array(
                    'id' => $user_id,
                    'name' => wp_get_current_user()->display_name,
                    'email' => wp_get_current_user()->user_email
                ),
                'site_stats' => array(
                    'total_sites' => count($sites),
                    'active_sites' => count(array_filter($sites, function($site) { return $site['status'] === 'active'; })),
                    'suspended_sites' => count(array_filter($sites, function($site) { return $site['status'] === 'suspended'; }))
                ),
                'sites' => array_slice($sites, 0, 5), // Últimos 5 sites
                'quick_actions' => array(
                    array('action' => 'create_site', 'label' => 'Criar Novo Site', 'icon' => 'add'),
                    array('action' => 'view_sites', 'label' => 'Ver Todos os Sites', 'icon' => 'list'),
                    array('action' => 'account', 'label' => 'Minha Conta', 'icon' => 'person')
                ),
                'notifications' => $this->get_user_notifications($user_id)
            );
            
            return rest_ensure_response($dashboard_data);
            
        } catch (Exception $e) {
            return new WP_Error('api_error', 'Erro ao obter dashboard: ' . $e->getMessage(), array('status' => 500));
        }
    }
    
    /**
     * Registrar dispositivo móvel
     */
    public function register_mobile_device($request) {
        $device_token = sanitize_text_field($request->get_param('device_token'));
        $platform = sanitize_text_field($request->get_param('platform'));
        $user_id = get_current_user_id();
        
        try {
            // Salvar token do dispositivo
            $devices = get_user_meta($user_id, '_mkp_mobile_devices', true) ?: array();
            
            $devices[$device_token] = array(
                'platform' => $platform,
                'registered_at' => current_time('mysql'),
                'active' => true
            );
            
            update_user_meta($user_id, '_mkp_mobile_devices', $devices);
            
            return rest_ensure_response(array(
                'device_token' => $device_token,
                'platform' => $platform,
                'registered' => true,
                'message' => 'Dispositivo registrado com sucesso'
            ));
            
        } catch (Exception $e) {
            return new WP_Error('api_error', 'Erro ao registrar dispositivo: ' . $e->getMessage(), array('status' => 500));
        }
    }
    
    /**
     * Verificar permissões de usuário
     */
    public function check_user_permissions($request) {
        return is_user_logged_in();
    }
    
    /**
     * Verificar permissões de site
     */
    public function check_site_permissions($request) {
        if (!is_user_logged_in()) {
            return false;
        }
        
        $site_id = $request->get_param('site_id');
        $user_id = get_current_user_id();
        
        // Super admin sempre pode acessar
        if (is_super_admin()) {
            return true;
        }
        
        // Verificar se o usuário é proprietário do site
        $subscription_id = get_site_meta($site_id, '_mkp_subscription_id', true);
        
        if ($subscription_id && function_exists('wcs_get_subscription')) {
            $subscription = wcs_get_subscription($subscription_id);
            return $subscription && $subscription->get_user_id() == $user_id;
        }
        
        return false;
    }
    
    /**
     * Verificar permissões de admin
     */
    public function check_admin_permissions($request) {
        return current_user_can('manage_network');
    }
    
    /**
     * Verificar permissões de webhook
     */
    public function check_webhook_permissions($request) {
        // Verificar se o webhook vem do WooCommerce
        $signature = $request->get_header('X-WC-Webhook-Signature');
        
        if (!$signature) {
            return false;
        }
        
        // Verificar assinatura do webhook (implementar validação específica se necessário)
        return true;
    }
    
    /**
     * Calcular armazenamento do site
     */
    private function calculate_site_storage($site_id) {
        $upload_dir = wp_upload_dir();
        $storage_used = 0;
        
        if (is_dir($upload_dir['basedir'])) {
            $storage_used = $this->get_directory_size($upload_dir['basedir']);
        }
        
        return $storage_used;
    }
    
    /**
     * Obter tamanho de diretório
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
     * Calcular porcentagem de uso
     */
    private function calculate_usage_percentage($usage, $limits) {
        $percentages = array();
        
        if (isset($limits['page_limit']) && $limits['page_limit'] > 0) {
            $percentages['pages'] = min(($usage['pages_count'] / $limits['page_limit']) * 100, 100);
        }
        
        if (isset($limits['storage_limit']) && $limits['storage_limit'] > 0) {
            $storage_limit_bytes = $limits['storage_limit'] * 1024 * 1024;
            $percentages['storage'] = min(($usage['storage_used'] / $storage_limit_bytes) * 100, 100);
        }
        
        if (isset($limits['user_limit']) && $limits['user_limit'] > 0) {
            $percentages['users'] = min(($usage['users_count'] / $limits['user_limit']) * 100, 100);
        }
        
        return $percentages;
    }
    
    /**
     * Obter notificações do usuário
     */
    private function get_user_notifications($user_id) {
        // Implementar lógica para obter notificações específicas do usuário
        return array();
    }
}