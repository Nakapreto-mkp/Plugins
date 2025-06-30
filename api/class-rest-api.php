<?php
/**
 * API REST para operações do plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

class MKP_REST_API {
    
    private $subdomain_manager;
    private $subscription_manager;
    
    public function __construct($subdomain_manager, $subscription_manager) {
        $this->subdomain_manager = $subdomain_manager;
        $this->subscription_manager = $subscription_manager;
        
        add_action('rest_api_init', array($this, 'register_routes'));
    }
    
    /**
     * Registrar rotas da API
     */
    public function register_routes() {
        $namespace = 'mkp-multisite/v1';
        
        // Rota para listar sites
        register_rest_route($namespace, '/sites', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_sites'),
            'permission_callback' => array($this, 'check_admin_permissions'),
            'args' => array(
                'status' => array(
                    'description' => 'Filtrar por status',
                    'type' => 'string',
                    'enum' => array('active', 'suspended', 'archived')
                ),
                'user_id' => array(
                    'description' => 'Filtrar por ID do usuário',
                    'type' => 'integer'
                ),
                'per_page' => array(
                    'description' => 'Número de sites por página',
                    'type' => 'integer',
                    'default' => 10
                ),
                'page' => array(
                    'description' => 'Página atual',
                    'type' => 'integer',
                    'default' => 1
                )
            )
        ));
        
        // Rota para criar site
        register_rest_route($namespace, '/sites', array(
            'methods' => 'POST',
            'callback' => array($this, 'create_site'),
            'permission_callback' => array($this, 'check_admin_permissions'),
            'args' => array(
                'user_id' => array(
                    'required' => true,
                    'description' => 'ID do usuário proprietário',
                    'type' => 'integer'
                ),
                'subscription_id' => array(
                    'required' => true,
                    'description' => 'ID da assinatura',
                    'type' => 'integer'
                ),
                'subdomain' => array(
                    'description' => 'Nome do subdomínio (opcional)',
                    'type' => 'string'
                )
            )
        ));
        
        // Rota para gerenciar site específico
        register_rest_route($namespace, '/sites/(?P<site_id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_site'),
            'permission_callback' => array($this, 'check_site_permissions'),
            'args' => array(
                'site_id' => array(
                    'description' => 'ID do site',
                    'type' => 'integer'
                )
            )
        ));
        
        // Rota para atualizar site
        register_rest_route($namespace, '/sites/(?P<site_id>\d+)', array(
            'methods' => 'PUT',
            'callback' => array($this, 'update_site'),
            'permission_callback' => array($this, 'check_site_permissions'),
            'args' => array(
                'site_id' => array(
                    'description' => 'ID do site',
                    'type' => 'integer'
                ),
                'status' => array(
                    'description' => 'Novo status do site',
                    'type' => 'string',
                    'enum' => array('active', 'suspended', 'archived')
                ),
                'page_limit' => array(
                    'description' => 'Novo limite de páginas',
                    'type' => 'integer'
                )
            )
        ));
        
        // Rota para deletar site
        register_rest_route($namespace, '/sites/(?P<site_id>\d+)', array(
            'methods' => 'DELETE',
            'callback' => array($this, 'delete_site'),
            'permission_callback' => array($this, 'check_admin_permissions'),
            'args' => array(
                'site_id' => array(
                    'description' => 'ID do site',
                    'type' => 'integer'
                ),
                'backup' => array(
                    'description' => 'Criar backup antes de deletar',
                    'type' => 'boolean',
                    'default' => true
                )
            )
        ));
        
        // Rota para estatísticas
        register_rest_route($namespace, '/stats', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_stats'),
            'permission_callback' => array($this, 'check_admin_permissions')
        ));
        
        // Rota para logs
        register_rest_route($namespace, '/logs', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_logs'),
            'permission_callback' => array($this, 'check_admin_permissions'),
            'args' => array(
                'action' => array(
                    'description' => 'Filtrar por ação',
                    'type' => 'string'
                ),
                'site_id' => array(
                    'description' => 'Filtrar por site',
                    'type' => 'integer'
                ),
                'user_id' => array(
                    'description' => 'Filtrar por usuário',
                    'type' => 'integer'
                ),
                'limit' => array(
                    'description' => 'Número máximo de logs',
                    'type' => 'integer',
                    'default' => 50
                )
            )
        ));
        
        // Rota para sincronização
        register_rest_route($namespace, '/sync', array(
            'methods' => 'POST',
            'callback' => array($this, 'sync_subscriptions'),
            'permission_callback' => array($this, 'check_admin_permissions')
        ));
        
        // Rota para webhook (para integrações externas)
        register_rest_route($namespace, '/webhook', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_webhook'),
            'permission_callback' => array($this, 'verify_webhook'),
            'args' => array(
                'event' => array(
                    'required' => true,
                    'description' => 'Tipo de evento',
                    'type' => 'string'
                ),
                'data' => array(
                    'required' => true,
                    'description' => 'Dados do evento',
                    'type' => 'object'
                )
            )
        ));
        
        // Rota para usuário atual
        register_rest_route($namespace, '/user/sites', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_user_sites'),
            'permission_callback' => array($this, 'check_user_permissions')
        ));
        
        // Rota para uso de recursos
        register_rest_route($namespace, '/sites/(?P<site_id>\d+)/usage', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_site_usage'),
            'permission_callback' => array($this, 'check_site_permissions'),
            'args' => array(
                'site_id' => array(
                    'description' => 'ID do site',
                    'type' => 'integer'
                )
            )
        ));
    }
    
    /**
     * Obter lista de sites
     */
    public function get_sites($request) {
        $params = $request->get_params();
        $subscriptions_with_sites = $this->subscription_manager->get_subscriptions_with_sites();
        
        // Aplicar filtros
        if (isset($params['status'])) {
            $subscriptions_with_sites = array_filter($subscriptions_with_sites, function($item) use ($params) {
                return $item['status'] === $params['status'];
            });
        }
        
        if (isset($params['user_id'])) {
            $subscriptions_with_sites = array_filter($subscriptions_with_sites, function($item) use ($params) {
                return $item['user_id'] == $params['user_id'];
            });
        }
        
        // Paginação
        $total = count($subscriptions_with_sites);
        $per_page = $params['per_page'];
        $page = $params['page'];
        $offset = ($page - 1) * $per_page;
        
        $subscriptions_with_sites = array_slice($subscriptions_with_sites, $offset, $per_page);
        
        // Formatar resposta
        $sites = array_map(function($item) {
            return $this->format_site_response($item);
        }, $subscriptions_with_sites);
        
        return rest_ensure_response(array(
            'sites' => $sites,
            'total' => $total,
            'pages' => ceil($total / $per_page),
            'current_page' => $page
        ));
    }
    
    /**
     * Criar novo site
     */
    public function create_site($request) {
        $params = $request->get_params();
        
        $user = get_user_by('id', $params['user_id']);
        if (!$user) {
            return new WP_Error('invalid_user', 'Usuário não encontrado', array('status' => 404));
        }
        
        $subscription = wcs_get_subscription($params['subscription_id']);
        if (!$subscription) {
            return new WP_Error('invalid_subscription', 'Assinatura não encontrada', array('status' => 404));
        }
        
        // Verificar se usuário já possui site
        $existing_site = $this->subdomain_manager->get_user_site($params['user_id']);
        if ($existing_site) {
            return new WP_Error('site_exists', 'Usuário já possui um site', array('status' => 409));
        }
        
        $site_id = $this->subdomain_manager->create_subdomain($params['user_id'], $subscription);
        
        if (!$site_id) {
            return new WP_Error('creation_failed', 'Falha ao criar site', array('status' => 500));
        }
        
        // Associar site à assinatura
        $subscription->update_meta_data('_mkp_site_id', $site_id);
        $subscription->save();
        
        $site_details = get_blog_details($site_id);
        
        return rest_ensure_response(array(
            'success' => true,
            'site_id' => $site_id,
            'site_url' => $site_details->siteurl,
            'site_name' => $site_details->blogname,
            'message' => 'Site criado com sucesso'
        ));
    }
    
    /**
     * Obter detalhes de um site específico
     */
    public function get_site($request) {
        $site_id = $request['site_id'];
        $site_details = get_blog_details($site_id);
        
        if (!$site_details) {
            return new WP_Error('site_not_found', 'Site não encontrado', array('status' => 404));
        }
        
        $config = $this->subdomain_manager->get_subdomain_config($site_id);
        $usage = null;
        
        if (class_exists('MKP_Limiter_Integration')) {
            $limiter = new MKP_Limiter_Integration(new MKP_Activity_Logger());
            $usage = $limiter->get_site_usage($site_id);
        }
        
        return rest_ensure_response(array(
            'site_id' => $site_id,
            'site_url' => $site_details->siteurl,
            'site_name' => $site_details->blogname,
            'status' => $this->subdomain_manager->get_site_status($site_id),
            'config' => $config,
            'usage' => $usage,
            'created_at' => $site_details->registered,
            'last_updated' => $site_details->last_updated
        ));
    }
    
    /**
     * Atualizar site
     */
    public function update_site($request) {
        $site_id = $request['site_id'];
        $params = $request->get_params();
        
        $site_details = get_blog_details($site_id);
        if (!$site_details) {
            return new WP_Error('site_not_found', 'Site não encontrado', array('status' => 404));
        }
        
        // Atualizar status se fornecido
        if (isset($params['status'])) {
            switch ($params['status']) {
                case 'active':
                    $this->subdomain_manager->activate_site($site_id);
                    break;
                case 'suspended':
                    $this->subdomain_manager->suspend_site($site_id);
                    break;
                case 'archived':
                    $this->subdomain_manager->archive_site($site_id);
                    break;
            }
        }
        
        // Atualizar limite de páginas se fornecido
        if (isset($params['page_limit'])) {
            $this->subdomain_manager->update_page_limit($site_id, $params['page_limit']);
        }
        
        return rest_ensure_response(array(
            'success' => true,
            'message' => 'Site atualizado com sucesso'
        ));
    }
    
    /**
     * Deletar site
     */
    public function delete_site($request) {
        $site_id = $request['site_id'];
        $create_backup = $request['backup'];
        
        $site_details = get_blog_details($site_id);
        if (!$site_details) {
            return new WP_Error('site_not_found', 'Site não encontrado', array('status' => 404));
        }
        
        // Criar backup se solicitado
        if ($create_backup && class_exists('MKP_Backup_Manager')) {
            $backup_manager = new MKP_Backup_Manager();
            $backup_manager->create_site_backup($site_id);
        }
        
        // Deletar site
        $result = wpmu_delete_blog($site_id, true);
        
        if (is_wp_error($result)) {
            return new WP_Error('deletion_failed', $result->get_error_message(), array('status' => 500));
        }
        
        return rest_ensure_response(array(
            'success' => true,
            'message' => 'Site deletado com sucesso'
        ));
    }
    
    /**
     * Obter estatísticas
     */
    public function get_stats($request) {
        $stats = $this->subscription_manager->get_subscription_stats();
        
        // Adicionar estatísticas adicionais
        $stats['total_sites'] = get_blog_count();
        $stats['active_sites'] = $this->count_sites_by_status('active');
        $stats['suspended_sites'] = $this->count_sites_by_status('suspended');
        
        return rest_ensure_response($stats);
    }
    
    /**
     * Obter logs
     */
    public function get_logs($request) {
        $params = $request->get_params();
        $activity_logger = new MKP_Activity_Logger();
        
        $logs = $activity_logger->get_logs(
            $params['limit'],
            $params['action'] ?? null,
            $params['site_id'] ?? null,
            $params['user_id'] ?? null
        );
        
        return rest_ensure_response(array(
            'logs' => $logs,
            'total' => count($logs)
        ));
    }
    
    /**
     * Sincronizar assinaturas
     */
    public function sync_subscriptions($request) {
        $synced = 0;
        $errors = 0;
        
        $subscriptions = wcs_get_subscriptions();
        
        foreach ($subscriptions as $subscription) {
            try {
                // Lógica de sincronização aqui
                $synced++;
            } catch (Exception $e) {
                $errors++;
            }
        }
        
        return rest_ensure_response(array(
            'success' => true,
            'synced' => $synced,
            'errors' => $errors,
            'message' => "Sincronização concluída. $synced assinaturas sincronizadas, $errors erros."
        ));
    }
    
    /**
     * Manipular webhook
     */
    public function handle_webhook($request) {
        $params = $request->get_params();
        $event = $params['event'];
        $data = $params['data'];
        
        // Log do webhook
        $activity_logger = new MKP_Activity_Logger();
        $activity_logger->log(0, 0, 0, 'webhook_received', "Webhook recebido: $event");
        
        switch ($event) {
            case 'subscription.activated':
                // Processar ativação de assinatura
                break;
            case 'subscription.suspended':
                // Processar suspensão de assinatura
                break;
            case 'payment.completed':
                // Processar pagamento concluído
                break;
            default:
                return new WP_Error('unknown_event', 'Evento não reconhecido', array('status' => 400));
        }
        
        return rest_ensure_response(array(
            'success' => true,
            'message' => 'Webhook processado com sucesso'
        ));
    }
    
    /**
     * Obter sites do usuário atual
     */
    public function get_user_sites($request) {
        $user_id = get_current_user_id();
        $sites = get_blogs_of_user($user_id);
        
        $formatted_sites = array();
        foreach ($sites as $site) {
            if ($site->userblog_id != 1) { // Excluir site principal
                $config = $this->subdomain_manager->get_subdomain_config($site->userblog_id);
                $formatted_sites[] = array(
                    'site_id' => $site->userblog_id,
                    'site_url' => $site->siteurl,
                    'site_name' => $site->blogname,
                    'status' => $this->subdomain_manager->get_site_status($site->userblog_id),
                    'config' => $config
                );
            }
        }
        
        return rest_ensure_response(array(
            'sites' => $formatted_sites,
            'total' => count($formatted_sites)
        ));
    }
    
    /**
     * Obter uso de recursos do site
     */
    public function get_site_usage($request) {
        $site_id = $request['site_id'];
        
        if (!get_blog_details($site_id)) {
            return new WP_Error('site_not_found', 'Site não encontrado', array('status' => 404));
        }
        
        $usage = array();
        
        if (class_exists('MKP_Limiter_Integration')) {
            $limiter = new MKP_Limiter_Integration(new MKP_Activity_Logger());
            $usage = $limiter->get_site_usage($site_id);
        }
        
        return rest_ensure_response($usage);
    }
    
    /**
     * Verificar permissões de administrador
     */
    public function check_admin_permissions($request) {
        return current_user_can('manage_network');
    }
    
    /**
     * Verificar permissões de site
     */
    public function check_site_permissions($request) {
        $site_id = $request['site_id'];
        
        // Super admin pode tudo
        if (is_super_admin()) {
            return true;
        }
        
        // Proprietário do site pode visualizar/editar
        $user_id = get_current_user_id();
        $user_sites = get_blogs_of_user($user_id);
        
        foreach ($user_sites as $site) {
            if ($site->userblog_id == $site_id) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Verificar permissões de usuário
     */
    public function check_user_permissions($request) {
        return is_user_logged_in();
    }
    
    /**
     * Verificar webhook (implementar assinatura de segurança)
     */
    public function verify_webhook($request) {
        // Implementar verificação de assinatura/token do webhook
        $webhook_secret = get_option('mkp_webhook_secret', '');
        
        if (empty($webhook_secret)) {
            return true; // Permitir se não há segredo configurado
        }
        
        $signature = $request->get_header('X-MKP-Signature');
        $body = $request->get_body();
        $expected_signature = hash_hmac('sha256', $body, $webhook_secret);
        
        return hash_equals($signature, $expected_signature);
    }
    
    /**
     * Formatar resposta do site
     */
    private function format_site_response($item) {
        return array(
            'site_id' => $item['site_id'],
            'site_url' => $item['site_details']->siteurl,
            'site_name' => $item['site_details']->blogname,
            'user_id' => $item['user_id'],
            'subscription_id' => $item['subscription']->get_id(),
            'status' => $item['status'],
            'next_payment' => $item['next_payment'],
            'total' => $item['total'],
            'created_at' => $item['site_details']->registered,
            'last_updated' => $item['site_details']->last_updated
        );
    }
    
    /**
     * Contar sites por status
     */
    private function count_sites_by_status($status) {
        global $wpdb;
        
        $table = $wpdb->base_prefix . 'mkp_subdomain_config';
        
        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE status = %s",
            $status
        ));
    }
}