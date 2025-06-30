<?php
/**
 * Painel administrativo do MKP Multisite WooCommerce
 * 
 * @package MKP_Multisite_Woo
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class MKP_Admin_Panel {
    
    private $subscription_manager;
    private $subdomain_manager;
    private $activity_logger;
    
    public function __construct() {
        add_action('network_admin_menu', array($this, 'add_network_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_ajax_mkp_bulk_action', array($this, 'handle_bulk_actions'));
        add_action('wp_ajax_mkp_site_action', array($this, 'handle_site_actions'));
        
        $this->init_components();
    }
    
    /**
     * Inicializar componentes
     */
    private function init_components() {
        if (class_exists('MKP_Activity_Logger')) {
            $this->activity_logger = new MKP_Activity_Logger();
        }
        
        if (class_exists('MKP_Subscription_Manager')) {
            $this->subscription_manager = new MKP_Subscription_Manager($this->activity_logger);
        }
        
        if (class_exists('MKP_Subdomain_Manager')) {
            $this->subdomain_manager = new MKP_Subdomain_Manager($this->activity_logger);
        }
    }
    
    /**
     * Adicionar menu no network admin
     */
    public function add_network_admin_menu() {
        add_menu_page(
            'MKP Multisite WooCommerce',
            'MKP Multisite',
            'manage_network',
            'mkp-multisite-woo',
            array($this, 'admin_page'),
            'dashicons-networking',
            30
        );
        
        add_submenu_page(
            'mkp-multisite-woo',
            'Dashboard',
            'Dashboard',
            'manage_network',
            'mkp-multisite-woo',
            array($this, 'admin_page')
        );
        
        add_submenu_page(
            'mkp-multisite-woo',
            'Sites Gerenciados',
            'Sites',
            'manage_network',
            'mkp-multisite-sites',
            array($this, 'sites_page')
        );
        
        add_submenu_page(
            'mkp-multisite-woo',
            'Assinaturas',
            'Assinaturas',
            'manage_network',
            'mkp-multisite-subscriptions',
            array($this, 'subscriptions_page')
        );
        
        add_submenu_page(
            'mkp-multisite-woo',
            'Logs de Atividade',
            'Logs',
            'manage_network',
            'mkp-multisite-logs',
            array($this, 'logs_page')
        );
        
        add_submenu_page(
            'mkp-multisite-woo',
            'Configurações',
            'Configurações',
            'manage_network',
            'mkp-multisite-settings',
            array($this, 'settings_page')
        );
    }
    
    /**
     * Carregar scripts e estilos do admin
     */
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'mkp-multisite') !== false) {
            wp_enqueue_script('mkp-admin-js', MKP_MULTISITE_WOO_PLUGIN_URL . 'admin/js/admin.js', array('jquery'), MKP_MULTISITE_WOO_VERSION, true);
            wp_enqueue_style('mkp-admin-css', MKP_MULTISITE_WOO_PLUGIN_URL . 'admin/css/admin.css', array(), MKP_MULTISITE_WOO_VERSION);
            
            wp_localize_script('mkp-admin-js', 'mkp_admin', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('mkp_admin_nonce'),
                'messages' => array(
                    'confirm_bulk_action' => 'Tem certeza que deseja executar esta ação em massa?',
                    'confirm_site_delete' => 'Tem certeza que deseja deletar este site? Esta ação não pode ser desfeita.',
                )
            ));
        }
    }
    
    /**
     * Página principal do admin
     */
    public function admin_page() {
        if (!current_user_can('manage_network')) {
            wp_die('Você não tem permissão para acessar esta página.');
        }
        
        $stats = $this->subscription_manager ? $this->subscription_manager->get_subscription_stats() : array();
        $recent_activities = $this->activity_logger ? $this->activity_logger->get_logs(array('limit' => 10)) : array();
        
        ?>
        <div class="wrap mkp-admin-page">
            <h1>MKP Multisite WooCommerce - Dashboard</h1>
            
            <div class="mkp-dashboard-grid">
                <!-- Estatísticas Gerais -->
                <div class="mkp-dashboard-card">
                    <h2>📊 Estatísticas Gerais</h2>
                    <div class="mkp-stats-grid">
                        <div class="mkp-stat-item">
                            <div class="mkp-stat-number"><?php echo $stats['total'] ?? 0; ?></div>
                            <div class="mkp-stat-label">Total Assinaturas</div>
                        </div>
                        <div class="mkp-stat-item">
                            <div class="mkp-stat-number"><?php echo $stats['active'] ?? 0; ?></div>
                            <div class="mkp-stat-label">Sites Ativos</div>
                        </div>
                        <div class="mkp-stat-item">
                            <div class="mkp-stat-number"><?php echo $stats['suspended'] ?? 0; ?></div>
                            <div class="mkp-stat-label">Sites Suspensos</div>
                        </div>
                        <div class="mkp-stat-item">
                            <div class="mkp-stat-number"><?php echo $stats['with_sites'] ?? 0; ?></div>
                            <div class="mkp-stat-label">Com Sites</div>
                        </div>
                    </div>
                </div>
                
                <!-- Ações Rápidas -->
                <div class="mkp-dashboard-card">
                    <h2>⚡ Ações Rápidas</h2>
                    <div class="mkp-quick-actions">
                        <a href="<?php echo network_admin_url('admin.php?page=mkp-multisite-sites'); ?>" class="mkp-quick-btn">
                            🌐 Gerenciar Sites
                        </a>
                        <a href="<?php echo network_admin_url('admin.php?page=mkp-multisite-subscriptions'); ?>" class="mkp-quick-btn">
                            💳 Ver Assinaturas
                        </a>
                        <a href="<?php echo network_admin_url('admin.php?page=mkp-multisite-logs'); ?>" class="mkp-quick-btn">
                            📋 Ver Logs
                        </a>
                        <button onclick="mkpSyncAllSubscriptions()" class="mkp-quick-btn mkp-btn-action">
                            🔄 Sincronizar Tudo
                        </button>
                    </div>
                </div>
                
                <!-- Atividades Recentes -->
                <div class="mkp-dashboard-card mkp-full-width">
                    <h2>📈 Atividades Recentes</h2>
                    <div class="mkp-activities-list">
                        <?php if (!empty($recent_activities)): ?>
                            <?php foreach ($recent_activities as $activity): ?>
                                <div class="mkp-activity-item">
                                    <div class="mkp-activity-icon mkp-severity-<?php echo esc_attr($activity->severity); ?>">
                                        <?php echo $this->get_activity_icon($activity->action); ?>
                                    </div>
                                    <div class="mkp-activity-content">
                                        <div class="mkp-activity-action"><?php echo esc_html($activity->action); ?></div>
                                        <div class="mkp-activity-details"><?php echo esc_html($activity->details); ?></div>
                                        <div class="mkp-activity-meta">
                                            Site: <?php echo $activity->site_id; ?> | 
                                            <?php echo human_time_diff(strtotime($activity->created_at), current_time('timestamp')); ?> atrás
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p>Nenhuma atividade recente.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Página de sites gerenciados
     */
    public function sites_page() {
        if (!current_user_can('manage_network')) {
            wp_die('Você não tem permissão para acessar esta página.');
        }
        
        $sites = $this->subdomain_manager ? $this->subdomain_manager->list_managed_subdomains() : array();
        
        ?>
        <div class="wrap mkp-admin-page">
            <h1>Sites Gerenciados</h1>
            
            <div class="mkp-page-header">
                <div class="mkp-bulk-actions">
                    <select id="mkp-bulk-action">
                        <option value="">Ações em massa</option>
                        <option value="activate">Ativar sites</option>
                        <option value="suspend">Suspender sites</option>
                        <option value="sync">Sincronizar com assinaturas</option>
                    </select>
                    <button onclick="mkpExecuteBulkAction()" class="button">Aplicar</button>
                </div>
                
                <div class="mkp-search-box">
                    <input type="text" id="mkp-site-search" placeholder="Buscar sites..." />
                </div>
            </div>
            
            <div class="mkp-sites-grid">
                <?php if (!empty($sites)): ?>
                    <?php foreach ($sites as $site): ?>
                        <div class="mkp-site-card" data-site-id="<?php echo $site['site_id']; ?>">
                            <div class="mkp-site-header">
                                <input type="checkbox" class="mkp-site-checkbox" value="<?php echo $site['site_id']; ?>" />
                                <h3><?php echo esc_html($site['subdomain']); ?></h3>
                                <span class="mkp-status-badge mkp-status-<?php echo esc_attr($site['status']); ?>">
                                    <?php echo ucfirst($site['status']); ?>
                                </span>
                            </div>
                            
                            <div class="mkp-site-info">
                                <div class="mkp-site-url">
                                    <a href="https://<?php echo esc_attr($site['full_domain']); ?>" target="_blank">
                                        <?php echo esc_html($site['full_domain']); ?>
                                    </a>
                                </div>
                                
                                <?php if (isset($site['usage_stats'])): ?>
                                    <div class="mkp-usage-info">
                                        <div class="mkp-usage-item">
                                            <span>Páginas:</span> <?php echo $site['usage_stats']['pages_count'] ?? 0; ?>
                                        </div>
                                        <div class="mkp-usage-item">
                                            <span>Armazenamento:</span> <?php echo size_format($site['usage_stats']['storage_used'] ?? 0); ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="mkp-site-actions">
                                <button onclick="mkpSiteAction('view', <?php echo $site['site_id']; ?>)" class="mkp-btn mkp-btn-sm">
                                    👁️ Ver
                                </button>
                                <button onclick="mkpSiteAction('edit', <?php echo $site['site_id']; ?>)" class="mkp-btn mkp-btn-sm">
                                    ✏️ Editar
                                </button>
                                <?php if ($site['status'] === 'active'): ?>
                                    <button onclick="mkpSiteAction('suspend', <?php echo $site['site_id']; ?>)" class="mkp-btn mkp-btn-sm mkp-btn-warning">
                                        ⏸️ Suspender
                                    </button>
                                <?php else: ?>
                                    <button onclick="mkpSiteAction('activate', <?php echo $site['site_id']; ?>)" class="mkp-btn mkp-btn-sm mkp-btn-success">
                                        ▶️ Ativar
                                    </button>
                                <?php endif; ?>
                                <button onclick="mkpSiteAction('delete', <?php echo $site['site_id']; ?>)" class="mkp-btn mkp-btn-sm mkp-btn-danger">
                                    🗑️ Deletar
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="mkp-empty-state">
                        <h3>Nenhum site gerenciado encontrado</h3>
                        <p>Sites criados através de assinaturas aparecerão aqui.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Página de assinaturas
     */
    public function subscriptions_page() {
        if (!current_user_can('manage_network')) {
            wp_die('Você não tem permissão para acessar esta página.');
        }
        
        $subscriptions_with_sites = $this->subscription_manager ? $this->subscription_manager->get_subscriptions_with_sites() : array();
        
        ?>
        <div class="wrap mkp-admin-page">
            <h1>Assinaturas WooCommerce</h1>
            
            <div class="mkp-subscriptions-table-wrapper">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Cliente</th>
                            <th>Status</th>
                            <th>Site</th>
                            <th>Próximo Pagamento</th>
                            <th>Total</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($subscriptions_with_sites)): ?>
                            <?php foreach ($subscriptions_with_sites as $item): ?>
                                <?php
                                $subscription = $item['subscription'];
                                $user = get_user_by('id', $item['user_id']);
                                ?>
                                <tr>
                                    <td><?php echo $subscription->get_id(); ?></td>
                                    <td>
                                        <?php echo $user ? esc_html($user->display_name) : 'Usuário desconhecido'; ?>
                                        <br>
                                        <small><?php echo $user ? esc_html($user->user_email) : ''; ?></small>
                                    </td>
                                    <td>
                                        <span class="mkp-status-badge mkp-status-<?php echo esc_attr($item['status']); ?>">
                                            <?php echo ucfirst($item['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($item['site_details']): ?>
                                            <a href="<?php echo esc_url($item['site_details']->siteurl); ?>" target="_blank">
                                                <?php echo esc_html($item['site_details']->blogname); ?>
                                            </a>
                                            <br>
                                            <small><?php echo esc_html($item['site_details']->domain); ?></small>
                                        <?php else: ?>
                                            <em>Sem site associado</em>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo $item['next_payment'] ? date('d/m/Y', strtotime($item['next_payment'])) : '-'; ?>
                                    </td>
                                    <td><?php echo $subscription->get_total(); ?></td>
                                    <td>
                                        <button onclick="mkpSyncSubscription(<?php echo $subscription->get_id(); ?>)" class="button button-small">
                                            🔄 Sincronizar
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7">Nenhuma assinatura com site encontrada.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }
    
    /**
     * Página de logs
     */
    public function logs_page() {
        if (!current_user_can('manage_network')) {
            wp_die('Você não tem permissão para acessar esta página.');
        }
        
        $logs = $this->activity_logger ? $this->activity_logger->get_logs(array('limit' => 100)) : array();
        
        ?>
        <div class="wrap mkp-admin-page">
            <h1>Logs de Atividade</h1>
            
            <div class="mkp-logs-filters">
                <select id="mkp-log-severity">
                    <option value="">Todas as severidades</option>
                    <option value="info">Info</option>
                    <option value="warning">Aviso</option>
                    <option value="error">Erro</option>
                    <option value="critical">Crítico</option>
                </select>
                
                <input type="date" id="mkp-log-date-from" />
                <input type="date" id="mkp-log-date-to" />
                
                <button onclick="mkpFilterLogs()" class="button">Filtrar</button>
                <button onclick="mkpExportLogs()" class="button">📥 Exportar</button>
                <button onclick="mkpClearOldLogs()" class="button button-link-delete">🗑️ Limpar Antigos</button>
            </div>
            
            <div class="mkp-logs-table-wrapper">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Data/Hora</th>
                            <th>Severidade</th>
                            <th>Ação</th>
                            <th>Site</th>
                            <th>Detalhes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($logs)): ?>
                            <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td>
                                        <?php echo date('d/m/Y H:i:s', strtotime($log->created_at)); ?>
                                    </td>
                                    <td>
                                        <span class="mkp-severity-badge mkp-severity-<?php echo esc_attr($log->severity); ?>">
                                            <?php echo ucfirst($log->severity); ?>
                                        </span>
                                    </td>
                                    <td><?php echo esc_html($log->action); ?></td>
                                    <td>
                                        <?php if ($log->site_id > 0): ?>
                                            <a href="<?php echo network_admin_url('site-info.php?id=' . $log->site_id); ?>">
                                                Site #<?php echo $log->site_id; ?>
                                            </a>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="mkp-log-details" title="<?php echo esc_attr($log->details); ?>">
                                            <?php echo esc_html(wp_trim_words($log->details, 10)); ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5">Nenhum log encontrado.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }
    
    /**
     * Página de configurações
     */
    public function settings_page() {
        if (!current_user_can('manage_network')) {
            wp_die('Você não tem permissão para acessar esta página.');
        }
        
        // Salvar configurações se formulário foi enviado
        if (isset($_POST['mkp_save_settings']) && wp_verify_nonce($_POST['mkp_settings_nonce'], 'mkp_save_settings')) {
            $this->save_settings();
            echo '<div class="notice notice-success"><p>Configurações salvas com sucesso!</p></div>';
        }
        
        $settings = get_site_option('mkp_multisite_settings', array());
        
        ?>
        <div class="wrap mkp-admin-page">
            <h1>Configurações</h1>
            
            <form method="post" action="">
                <?php wp_nonce_field('mkp_save_settings', 'mkp_settings_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">Limite padrão de páginas</th>
                        <td>
                            <input type="number" name="default_page_limit" value="<?php echo esc_attr($settings['default_page_limit'] ?? 10); ?>" min="0" />
                            <p class="description">Limite padrão de páginas para novos sites (0 = ilimitado)</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Limite padrão de armazenamento (MB)</th>
                        <td>
                            <input type="number" name="default_storage_limit" value="<?php echo esc_attr($settings['default_storage_limit'] ?? 1024); ?>" min="0" />
                            <p class="description">Limite padrão de armazenamento para novos sites (0 = ilimitado)</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Retenção de logs (dias)</th>
                        <td>
                            <input type="number" name="logs_retention_days" value="<?php echo esc_attr($settings['logs_retention_days'] ?? 30); ?>" min="1" />
                            <p class="description">Quantos dias manter os logs antes de deletar automaticamente</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Email de suporte</th>
                        <td>
                            <input type="email" name="support_email" value="<?php echo esc_attr($settings['support_email'] ?? 'suporte@' . DOMAIN_CURRENT_SITE); ?>" class="regular-text" />
                            <p class="description">Email exibido nas páginas de suspensão e templates de email</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Dias antes da remoção</th>
                        <td>
                            <input type="number" name="removal_grace_period" value="<?php echo esc_attr($settings['removal_grace_period'] ?? 30); ?>" min="1" />
                            <p class="description">Quantos dias aguardar antes de remover sites cancelados</p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button('Salvar Configurações', 'primary', 'mkp_save_settings'); ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Salvar configurações
     */
    private function save_settings() {
        $settings = array(
            'default_page_limit' => absint($_POST['default_page_limit']),
            'default_storage_limit' => absint($_POST['default_storage_limit']),
            'logs_retention_days' => absint($_POST['logs_retention_days']),
            'support_email' => sanitize_email($_POST['support_email']),
            'removal_grace_period' => absint($_POST['removal_grace_period'])
        );
        
        update_site_option('mkp_multisite_settings', $settings);
    }
    
    /**
     * Obter ícone para ação
     */
    private function get_activity_icon($action) {
        $icons = array(
            'site_created' => '🆕',
            'site_suspended' => '⏸️',
            'site_activated' => '▶️',
            'site_deleted' => '🗑️',
            'subscription_synced' => '🔄',
            'payment_reminder_sent' => '💰',
            'welcome_email_sent' => '📧',
            'error_occurred' => '❌',
            'warning' => '⚠️'
        );
        
        return $icons[$action] ?? '📝';
    }
    
    /**
     * Gerenciar ações em massa via AJAX
     */
    public function handle_bulk_actions() {
        if (!wp_verify_nonce($_POST['nonce'], 'mkp_admin_nonce')) {
            wp_die('Nonce inválido');
        }
        
        if (!current_user_can('manage_network')) {
            wp_die('Permissão negada');
        }
        
        $action = sanitize_text_field($_POST['action']);
        $site_ids = array_map('absint', $_POST['site_ids']);
        
        $results = array();
        
        foreach ($site_ids as $site_id) {
            switch ($action) {
                case 'activate':
                    update_site_meta($site_id, '_mkp_status', 'active');
                    $results[] = "Site {$site_id} ativado";
                    break;
                    
                case 'suspend':
                    update_site_meta($site_id, '_mkp_status', 'suspended');
                    $results[] = "Site {$site_id} suspenso";
                    break;
                    
                case 'sync':
                    // Sincronizar com assinatura
                    $results[] = "Site {$site_id} sincronizado";
                    break;
            }
        }
        
        wp_send_json_success(array('results' => $results));
    }
    
    /**
     * Gerenciar ações de site via AJAX
     */
    public function handle_site_actions() {
        if (!wp_verify_nonce($_POST['nonce'], 'mkp_admin_nonce')) {
            wp_die('Nonce inválido');
        }
        
        if (!current_user_can('manage_network')) {
            wp_die('Permissão negada');
        }
        
        $action = sanitize_text_field($_POST['action']);
        $site_id = absint($_POST['site_id']);
        
        switch ($action) {
            case 'suspend':
                update_site_meta($site_id, '_mkp_status', 'suspended');
                wp_send_json_success(array('message' => 'Site suspenso com sucesso'));
                break;
                
            case 'activate':
                update_site_meta($site_id, '_mkp_status', 'active');
                wp_send_json_success(array('message' => 'Site ativado com sucesso'));
                break;
                
            case 'delete':
                wp_delete_site($site_id);
                wp_send_json_success(array('message' => 'Site deletado com sucesso'));
                break;
                
            default:
                wp_send_json_error(array('message' => 'Ação inválida'));
        }
    }
}