<?php
/**
 * Painel administrativo do plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

class MKP_Admin_Panel {
    
    public function __construct() {
        add_action('network_admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_ajax_mkp_get_dashboard_data', array($this, 'ajax_get_dashboard_data'));
        add_action('wp_ajax_mkp_manage_site', array($this, 'ajax_manage_site'));
    }
    
    /**
     * Adicionar menu administrativo
     */
    public function add_admin_menu() {
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
            'Sites e Assinaturas',
            'Sites e Assinaturas',
            'manage_network',
            'mkp-multisite-sites',
            array($this, 'sites_page')
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
        if (strpos($hook, 'mkp-multisite') === false) {
            return;
        }
        
        wp_enqueue_script(
            'mkp-admin-js',
            MKP_MULTISITE_WOO_PLUGIN_URL . 'admin/js/admin-scripts.js',
            array('jquery'),
            MKP_MULTISITE_WOO_VERSION,
            true
        );
        
        wp_enqueue_style(
            'mkp-admin-css',
            MKP_MULTISITE_WOO_PLUGIN_URL . 'admin/css/admin-styles.css',
            array(),
            MKP_MULTISITE_WOO_VERSION
        );
        
        // Localizar script
        wp_localize_script('mkp-admin-js', 'mkp_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mkp_admin_nonce')
        ));
    }
    
    /**
     * Página principal do admin
     */
    public function admin_page() {
        include MKP_MULTISITE_WOO_PLUGIN_DIR . 'admin/admin-page.php';
    }
    
    /**
     * Página de sites e assinaturas
     */
    public function sites_page() {
        $subscription_manager = new MKP_Subscription_Manager(new MKP_Activity_Logger());
        $subdomain_manager = new MKP_Subdomain_Manager(new MKP_Activity_Logger(), new MKP_Backup_Manager());
        
        $subscriptions_with_sites = $subscription_manager->get_subscriptions_with_sites();
        
        ?>
        <div class="wrap">
            <h1>Sites e Assinaturas</h1>
            
            <div class="tablenav top">
                <div class="alignleft actions">
                    <select id="bulk-action-selector-top">
                        <option value="-1">Ações em lote</option>
                        <option value="activate">Ativar</option>
                        <option value="suspend">Suspender</option>
                        <option value="archive">Arquivar</option>
                    </select>
                    <input type="submit" class="button action" value="Aplicar">
                </div>
            </div>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <td class="manage-column column-cb check-column">
                            <input type="checkbox" id="cb-select-all-1">
                        </td>
                        <th>Site</th>
                        <th>Usuário</th>
                        <th>Assinatura</th>
                        <th>Status</th>
                        <th>Próximo Pagamento</th>
                        <th>Valor</th>
                        <th>Páginas</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($subscriptions_with_sites as $item): ?>
                        <?php
                        $user = get_user_by('id', $item['user_id']);
                        $config = $subdomain_manager->get_subdomain_config($item['site_id']);
                        ?>
                        <tr>
                            <th class="check-column">
                                <input type="checkbox" name="site[]" value="<?php echo esc_attr($item['site_id']); ?>">
                            </th>
                            <td>
                                <strong>
                                    <a href="<?php echo esc_url($item['site_details']->siteurl); ?>" target="_blank">
                                        <?php echo esc_html($item['site_details']->blogname); ?>
                                    </a>
                                </strong>
                                <br>
                                <small><?php echo esc_html($item['site_details']->siteurl); ?></small>
                            </td>
                            <td>
                                <?php echo esc_html($user ? $user->display_name : 'Usuário não encontrado'); ?>
                                <br>
                                <small><?php echo esc_html($user ? $user->user_email : ''); ?></small>
                            </td>
                            <td>
                                #<?php echo esc_html($item['subscription']->get_id()); ?>
                                <br>
                                <small>Criada em <?php echo esc_html($item['subscription']->get_date('date_created')); ?></small>
                            </td>
                            <td>
                                <span class="status-badge status-<?php echo esc_attr($item['status']); ?>">
                                    <?php 
                                    $status_labels = array(
                                        'active' => 'Ativo',
                                        'on-hold' => 'Suspenso',
                                        'cancelled' => 'Cancelado',
                                        'expired' => 'Expirado'
                                    );
                                    echo esc_html($status_labels[$item['status']] ?? $item['status']);
                                    ?>
                                </span>
                            </td>
                            <td>
                                <?php 
                                if ($item['next_payment']) {
                                    echo esc_html(date('d/m/Y', strtotime($item['next_payment'])));
                                } else {
                                    echo '-';
                                }
                                ?>
                            </td>
                            <td>R$ <?php echo esc_html($item['total']); ?></td>
                            <td>
                                <?php if ($config): ?>
                                    <?php echo esc_html($config->current_pages); ?>/<?php echo esc_html($config->page_limit); ?>
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="width: <?php echo min(100, ($config->current_pages / $config->page_limit) * 100); ?>%"></div>
                                    </div>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td>
                                <button class="button button-small mkp-manage-site" 
                                        data-site-id="<?php echo esc_attr($item['site_id']); ?>"
                                        data-action="view">
                                    Ver
                                </button>
                                
                                <?php if ($item['status'] === 'active'): ?>
                                    <button class="button button-small mkp-manage-site" 
                                            data-site-id="<?php echo esc_attr($item['site_id']); ?>"
                                            data-action="suspend">
                                        Suspender
                                    </button>
                                <?php else: ?>
                                    <button class="button button-small mkp-manage-site" 
                                            data-site-id="<?php echo esc_attr($item['site_id']); ?>"
                                            data-action="activate">
                                        Ativar
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    /**
     * Página de logs
     */
    public function logs_page() {
        $activity_logger = new MKP_Activity_Logger();
        $logs = $activity_logger->get_recent_logs(100);
        
        ?>
        <div class="wrap">
            <h1>Logs de Atividade</h1>
            
            <div class="tablenav top">
                <div class="alignleft actions">
                    <select id="log-filter-action">
                        <option value="">Todas as ações</option>
                        <option value="site_created">Site criado</option>
                        <option value="site_activated">Site ativado</option>
                        <option value="site_suspended">Site suspenso</option>
                        <option value="site_archived">Site arquivado</option>
                        <option value="page_limit_exceeded">Limite excedido</option>
                    </select>
                    <input type="submit" class="button" value="Filtrar">
                </div>
                
                <div class="alignright">
                    <button class="button" id="export-logs">Exportar Logs</button>
                    <button class="button" id="clear-old-logs">Limpar Logs Antigos</button>
                </div>
            </div>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Data/Hora</th>
                        <th>Site</th>
                        <th>Usuário</th>
                        <th>Ação</th>
                        <th>Detalhes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?php echo esc_html(date('d/m/Y H:i:s', strtotime($log->created_at))); ?></td>
                            <td>
                                <?php if ($log->site_id > 0): ?>
                                    <?php $site = get_blog_details($log->site_id); ?>
                                    <?php if ($site): ?>
                                        <a href="<?php echo esc_url($site->siteurl); ?>" target="_blank">
                                            <?php echo esc_html($site->blogname); ?>
                                        </a>
                                    <?php else: ?>
                                        Site #<?php echo esc_html($log->site_id); ?> (deletado)
                                    <?php endif; ?>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($log->user_id > 0): ?>
                                    <?php $user = get_user_by('id', $log->user_id); ?>
                                    <?php echo $user ? esc_html($user->display_name) : 'Usuário #' . $log->user_id; ?>
                                <?php else: ?>
                                    Sistema
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="action-badge action-<?php echo esc_attr($log->action); ?>">
                                    <?php echo esc_html($this->format_action_name($log->action)); ?>
                                </span>
                            </td>
                            <td><?php echo esc_html($log->details); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    /**
     * Página de configurações
     */
    public function settings_page() {
        if (isset($_POST['submit'])) {
            $this->save_settings();
        }
        
        $settings = $this->get_settings();
        
        ?>
        <div class="wrap">
            <h1>Configurações MKP Multisite</h1>
            
            <form method="post" action="">
                <?php wp_nonce_field('mkp_settings'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">Tema Padrão para Novos Sites</th>
                        <td>
                            <select name="mkp_default_theme">
                                <?php 
                                $themes = wp_get_themes();
                                foreach ($themes as $theme_slug => $theme) {
                                    echo '<option value="' . esc_attr($theme_slug) . '"' . selected($settings['default_theme'], $theme_slug, false) . '>' . esc_html($theme->get('Name')) . '</option>';
                                }
                                ?>
                            </select>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Email de Remetente</th>
                        <td>
                            <input type="email" name="mkp_email_from" value="<?php echo esc_attr($settings['email_from']); ?>" class="regular-text">
                            <p class="description">Email usado como remetente das notificações</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Nome do Remetente</th>
                        <td>
                            <input type="text" name="mkp_email_from_name" value="<?php echo esc_attr($settings['email_from_name']); ?>" class="regular-text">
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Limite Padrão de Páginas</th>
                        <td>
                            <input type="number" name="mkp_default_page_limit" value="<?php echo esc_attr($settings['default_page_limit']); ?>" min="1" max="1000">
                            <p class="description">Limite padrão para sites sem configuração específica</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Dias para Backup Automático</th>
                        <td>
                            <input type="number" name="mkp_backup_retention_days" value="<?php echo esc_attr($settings['backup_retention_days']); ?>" min="1" max="365">
                            <p class="description">Quantos dias manter os backups automáticos</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Notificações por Email</th>
                        <td>
                            <fieldset>
                                <label><input type="checkbox" name="mkp_email_welcome" value="1" <?php checked($settings['email_welcome']); ?>> Email de boas-vindas</label><br>
                                <label><input type="checkbox" name="mkp_email_suspension" value="1" <?php checked($settings['email_suspension']); ?>> Email de suspensão</label><br>
                                <label><input type="checkbox" name="mkp_email_reactivation" value="1" <?php checked($settings['email_reactivation']); ?>> Email de reativação</label><br>
                                <label><input type="checkbox" name="mkp_email_cancellation" value="1" <?php checked($settings['email_cancellation']); ?>> Email de cancelamento</label>
                            </fieldset>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
            
            <h2>Status do Sistema</h2>
            <table class="widefat">
                <tr>
                    <td><strong>WordPress Multisite:</strong></td>
                    <td><?php echo is_multisite() ? '✅ Habilitado' : '❌ Não habilitado'; ?></td>
                </tr>
                <tr>
                    <td><strong>WooCommerce:</strong></td>
                    <td><?php echo class_exists('WooCommerce') ? '✅ Instalado' : '❌ Não instalado'; ?></td>
                </tr>
                <tr>
                    <td><strong>WooCommerce Subscriptions:</strong></td>
                    <td><?php echo class_exists('WC_Subscriptions') ? '✅ Instalado' : '❌ Não instalado'; ?></td>
                </tr>
                <tr>
                    <td><strong>Limiter MKP Pro:</strong></td>
                    <td><?php echo class_exists('Limiter_MKP_Pro') ? '✅ Instalado' : '❌ Não instalado'; ?></td>
                </tr>
                <tr>
                    <td><strong>Configuração de Subdomínio:</strong></td>
                    <td><?php echo defined('SUBDOMAIN_INSTALL') && SUBDOMAIN_INSTALL ? '✅ Configurado' : '❌ Não configurado'; ?></td>
                </tr>
            </table>
        </div>
        <?php
    }
    
    /**
     * Salvar configurações
     */
    private function save_settings() {
        if (!wp_verify_nonce($_POST['_wpnonce'], 'mkp_settings')) {
            return;
        }
        
        update_network_option(null, 'mkp_default_theme', sanitize_text_field($_POST['mkp_default_theme']));
        update_network_option(null, 'mkp_email_from', sanitize_email($_POST['mkp_email_from']));
        update_network_option(null, 'mkp_email_from_name', sanitize_text_field($_POST['mkp_email_from_name']));
        update_network_option(null, 'mkp_default_page_limit', intval($_POST['mkp_default_page_limit']));
        update_network_option(null, 'mkp_backup_retention_days', intval($_POST['mkp_backup_retention_days']));
        
        update_network_option(null, 'mkp_email_welcome', isset($_POST['mkp_email_welcome']) ? 1 : 0);
        update_network_option(null, 'mkp_email_suspension', isset($_POST['mkp_email_suspension']) ? 1 : 0);
        update_network_option(null, 'mkp_email_reactivation', isset($_POST['mkp_email_reactivation']) ? 1 : 0);
        update_network_option(null, 'mkp_email_cancellation', isset($_POST['mkp_email_cancellation']) ? 1 : 0);
        
        add_action('admin_notices', function() {
            echo '<div class="notice notice-success"><p>Configurações salvas com sucesso!</p></div>';
        });
    }
    
    /**
     * Obter configurações
     */
    private function get_settings() {
        return array(
            'default_theme' => get_network_option(null, 'mkp_default_theme', get_option('stylesheet')),
            'email_from' => get_network_option(null, 'mkp_email_from', get_option('admin_email')),
            'email_from_name' => get_network_option(null, 'mkp_email_from_name', 'MKP Multisite'),
            'default_page_limit' => get_network_option(null, 'mkp_default_page_limit', 10),
            'backup_retention_days' => get_network_option(null, 'mkp_backup_retention_days', 30),
            'email_welcome' => get_network_option(null, 'mkp_email_welcome', 1),
            'email_suspension' => get_network_option(null, 'mkp_email_suspension', 1),
            'email_reactivation' => get_network_option(null, 'mkp_email_reactivation', 1),
            'email_cancellation' => get_network_option(null, 'mkp_email_cancellation', 1)
        );
    }
    
    /**
     * Formatar nome da ação
     */
    private function format_action_name($action) {
        $actions = array(
            'site_created' => 'Site Criado',
            'site_activated' => 'Site Ativado',
            'site_suspended' => 'Site Suspenso',
            'site_archived' => 'Site Arquivado',
            'page_limit_exceeded' => 'Limite Excedido',
            'subscription_created' => 'Assinatura Criada',
            'plugin_init' => 'Plugin Inicializado'
        );
        
        return isset($actions[$action]) ? $actions[$action] : ucwords(str_replace('_', ' ', $action));
    }
    
    /**
     * AJAX: Obter dados do dashboard
     */
    public function ajax_get_dashboard_data() {
        if (!wp_verify_nonce($_POST['nonce'], 'mkp_admin_nonce')) {
            wp_die('Nonce inválido');
        }
        
        $subscription_manager = new MKP_Subscription_Manager(new MKP_Activity_Logger());
        $stats = $subscription_manager->get_subscription_stats();
        
        wp_send_json_success($stats);
    }
    
    /**
     * AJAX: Gerenciar site
     */
    public function ajax_manage_site() {
        if (!wp_verify_nonce($_POST['nonce'], 'mkp_admin_nonce')) {
            wp_die('Nonce inválido');
        }
        
        $site_id = intval($_POST['site_id']);
        $action = sanitize_text_field($_POST['action']);
        
        $subdomain_manager = new MKP_Subdomain_Manager(new MKP_Activity_Logger(), new MKP_Backup_Manager());
        
        switch ($action) {
            case 'activate':
                $subdomain_manager->activate_site($site_id);
                wp_send_json_success('Site ativado com sucesso');
                break;
                
            case 'suspend':
                $subdomain_manager->suspend_site($site_id);
                wp_send_json_success('Site suspenso com sucesso');
                break;
                
            case 'archive':
                $subdomain_manager->archive_site($site_id);
                wp_send_json_success('Site arquivado com sucesso');
                break;
                
            default:
                wp_send_json_error('Ação inválida');
        }
    }
}

new MKP_Admin_Panel();