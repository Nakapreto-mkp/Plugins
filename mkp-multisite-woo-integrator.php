<?php
/**
 * Plugin Name: MKP Multisite WooCommerce Integrator
 * Plugin URI: https://github.com/Nakapreto-mkp/Plugins
 * Description: Plugin integrador para WordPress Multisite que automatiza criação e gerenciamento de subdomínios baseado em assinaturas do WooCommerce
 * Version: 1.0.0
 * Author: MKP Team
 * License: GPL v2 or later
 * Text Domain: mkp-multisite-woo
 * Domain Path: /languages
 * Requires at least: 6.0
 * Tested up to: 6.4
 * Requires PHP: 8.0
 * Network: true
 */

// Prevenir acesso direto
if (!defined('ABSPATH')) {
    exit;
}

// Definir constantes do plugin
define('MKP_MULTISITE_WOO_VERSION', '1.0.0');
define('MKP_MULTISITE_WOO_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MKP_MULTISITE_WOO_PLUGIN_URL', plugin_dir_url(__FILE__));
define('MKP_MULTISITE_WOO_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Verificar dependências necessárias
 */
function mkp_multisite_woo_check_dependencies() {
    $dependencies = array();
    
    // Verificar se é Multisite
    if (!is_multisite()) {
        $dependencies[] = 'WordPress Multisite deve estar habilitado';
    }
    
    // Verificar WooCommerce
    if (!class_exists('WooCommerce')) {
        $dependencies[] = 'WooCommerce deve estar instalado e ativado';
    }
    
    // Verificar WooCommerce Subscriptions
    if (!class_exists('WC_Subscriptions')) {
        $dependencies[] = 'WooCommerce Subscriptions deve estar instalado e ativado';
    }
    
    // Verificar Limiter MKP Pro (assumindo que existe uma classe específica)
    if (!class_exists('Limiter_MKP_Pro')) {
        $dependencies[] = 'Plugin Limiter MKP Pro deve estar instalado e ativado';
    }
    
    if (!empty($dependencies)) {
        add_action('admin_notices', function() use ($dependencies) {
            echo '<div class="notice notice-error"><p>';
            echo '<strong>MKP Multisite WooCommerce Integrator:</strong> ';
            echo 'As seguintes dependências são necessárias:<br>';
            foreach ($dependencies as $dependency) {
                echo '• ' . $dependency . '<br>';
            }
            echo '</p></div>';
        });
        return false;
    }
    
    return true;
}

/**
 * Ativar plugin
 */
function mkp_multisite_woo_activate() {
    if (!mkp_multisite_woo_check_dependencies()) {
        wp_die('Por favor, instale todas as dependências necessárias antes de ativar este plugin.');
    }
    
    // Criar tabelas necessárias
    mkp_multisite_woo_create_tables();
    
    // Flush rewrite rules
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'mkp_multisite_woo_activate');

/**
 * Desativar plugin
 */
function mkp_multisite_woo_deactivate() {
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'mkp_multisite_woo_deactivate');

/**
 * Criar tabelas do banco de dados
 */
function mkp_multisite_woo_create_tables() {
    global $wpdb;
    
    $charset_collate = $wpdb->get_charset_collate();
    
    // Tabela para logs de atividades
    $table_logs = $wpdb->base_prefix . 'mkp_activity_logs';
    $sql_logs = "CREATE TABLE $table_logs (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        site_id bigint(20) NOT NULL,
        subscription_id bigint(20) NOT NULL,
        user_id bigint(20) NOT NULL,
        action varchar(50) NOT NULL,
        details text,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY site_id (site_id),
        KEY subscription_id (subscription_id),
        KEY user_id (user_id)
    ) $charset_collate;";
    
    // Tabela para configurações de subdomínios
    $table_subdomains = $wpdb->base_prefix . 'mkp_subdomain_config';
    $sql_subdomains = "CREATE TABLE $table_subdomains (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        site_id bigint(20) NOT NULL,
        subscription_id bigint(20) NOT NULL,
        subdomain varchar(255) NOT NULL,
        status varchar(20) DEFAULT 'active',
        page_limit int(11) DEFAULT 0,
        current_pages int(11) DEFAULT 0,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY site_id (site_id),
        UNIQUE KEY subdomain (subdomain),
        KEY subscription_id (subscription_id)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql_logs);
    dbDelta($sql_subdomains);
}

/**
 * Carregar classes do plugin
 */
function mkp_multisite_woo_load_classes() {
    // Verificar dependências
    if (!mkp_multisite_woo_check_dependencies()) {
        return;
    }
    
    // Carregar classes principais
    require_once MKP_MULTISITE_WOO_PLUGIN_DIR . 'includes/class-mkp-multisite-woo-integrator.php';
    require_once MKP_MULTISITE_WOO_PLUGIN_DIR . 'includes/class-subscription-manager.php';
    require_once MKP_MULTISITE_WOO_PLUGIN_DIR . 'includes/class-subdomain-manager.php';
    require_once MKP_MULTISITE_WOO_PLUGIN_DIR . 'includes/class-limiter-integration.php';
    require_once MKP_MULTISITE_WOO_PLUGIN_DIR . 'includes/class-redirect-handler.php';
    require_once MKP_MULTISITE_WOO_PLUGIN_DIR . 'includes/class-email-notifications.php';
    require_once MKP_MULTISITE_WOO_PLUGIN_DIR . 'logs/class-activity-logger.php';
    require_once MKP_MULTISITE_WOO_PLUGIN_DIR . 'backup/class-backup-manager.php';
    require_once MKP_MULTISITE_WOO_PLUGIN_DIR . 'api/class-rest-api.php';
    
    // Carregar classes admin apenas no admin
    if (is_admin()) {
        require_once MKP_MULTISITE_WOO_PLUGIN_DIR . 'admin/class-admin-panel.php';
    }
    
    // Inicializar plugin principal
    new MKP_Multisite_Woo_Integrator();
}
add_action('plugins_loaded', 'mkp_multisite_woo_load_classes');

/**
 * Carregar textdomain
 */
function mkp_multisite_woo_load_textdomain() {
    load_plugin_textdomain('mkp-multisite-woo', false, dirname(plugin_basename(__FILE__)) . '/languages');
}
add_action('plugins_loaded', 'mkp_multisite_woo_load_textdomain');

/**
 * Adicionar link de configurações na lista de plugins
 */
function mkp_multisite_woo_plugin_action_links($links) {
    $settings_link = '<a href="' . network_admin_url('admin.php?page=mkp-multisite-woo') . '">' . __('Configurações', 'mkp-multisite-woo') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
}
add_filter('plugin_action_links_' . MKP_MULTISITE_WOO_PLUGIN_BASENAME, 'mkp_multisite_woo_plugin_action_links');
