<?php
/**
 * Plugin Name: MKP Multisite WooCommerce Integrator
 * Plugin URI: https://github.com/Nakapreto-mkp/Plugins
 * Description: Plugin integrador para WordPress Multisite que automatiza criação e gerenciamento de subdomínios baseado em assinaturas do WooCommerce
 * Version: 1.0.1
 * Author: MKP Team
 * License: GPL v2 or later
 * Text Domain: mkp-multisite-woo
 * Domain Path: /languages
 * Requires at least: 6.0
 * Tested up to: 6.4
 * Requires PHP: 8.0
 * Network: true
 * 
 * Changelog:
 * 1.0.1 - Correção de compatibilidade com WooCommerce Subscriptions 7.6.0+
 * 1.0.0 - Versão inicial
 */

// Prevenir acesso direto
if (!defined('ABSPATH')) {
    exit;
}

// Definir constantes do plugin
define('MKP_MULTISITE_WOO_VERSION', '1.0.1');
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
    } else {
        // Verificar versão mínima recomendada
        if (version_compare(WC_Subscriptions::$version, '7.0.0', '<')) {
            $dependencies[] = 'WooCommerce Subscriptions versão 7.0.0 ou superior é recomendado (versão atual: ' . WC_Subscriptions::$version . ')';
        }
    }
    
    // Verificar Limiter MKP Pro (assumindo que existe uma classe específica)
    if (!class_exists('Limiter_MKP_Pro')) {
        // Este é um aviso, não um erro crítico
        error_log('MKP Multisite Woo: Plugin Limiter MKP Pro não encontrado - algumas funcionalidades podem não estar disponíveis');
    }
    
    if (!empty($dependencies)) {
        add_action('admin_notices', function() use ($dependencies) {
            echo '<div class="notice notice-error"><p>';
            echo '<strong>MKP Multisite WooCommerce Integrator:</strong> ';
            echo 'As seguintes dependências são necessárias:<br>';
            foreach ($dependencies as $dependency) {
                echo '• ' . esc_html($dependency) . '<br>';
            }
            echo '</p></div>';
        });
        return false;
    }
    
    return true;
}

/**
 * Verificar compatibilidade específica com WooCommerce Subscriptions 7.6.0+
 */
function mkp_multisite_woo_check_wcs_compatibility() {
    if (!function_exists('wcs_get_subscriptions')) {
        return false;
    }
    
    try {
        // Testar se a função aceita argumentos (versão 7.6.0+)
        $reflection = new ReflectionFunction('wcs_get_subscriptions');
        $required_params = 0;
        
        foreach ($reflection->getParameters() as $param) {
            if (!$param->isOptional()) {
                $required_params++;
            }
        }
        
        // Se requer pelo menos 1 parâmetro, é versão 7.6.0+
        if ($required_params > 0) {
            // Testar chamada com array vazio
            wcs_get_subscriptions(array());
            error_log('MKP Multisite Woo: Compatibilidade com WooCommerce Subscriptions 7.6.0+ confirmada');
        }
        
        return true;
        
    } catch (ArgumentCountError $e) {
        error_log('MKP Multisite Woo: Erro de compatibilidade detectado - ' . $e->getMessage());
        return false;
    } catch (Exception $e) {
        error_log('MKP Multisite Woo: Erro ao verificar compatibilidade - ' . $e->getMessage());
        return false;
    }
}

/**
 * Ativar plugin
 */
function mkp_multisite_woo_activate() {
    if (!mkp_multisite_woo_check_dependencies()) {
        wp_die(
            '<h1>Dependências Necessárias</h1>' .
            '<p>Por favor, instale e ative todas as dependências necessárias antes de ativar este plugin:</p>' .
            '<ul>' .
            '<li>WordPress Multisite</li>' .
            '<li>WooCommerce</li>' .
            '<li>WooCommerce Subscriptions (versão 7.0.0 ou superior)</li>' .
            '</ul>' .
            '<p><a href="' . admin_url('plugins.php') . '">← Voltar aos Plugins</a></p>',
            'Dependências Necessárias',
            array('back_link' => true)
        );
    }
    
    // Verificar compatibilidade específica com WCS
    if (!mkp_multisite_woo_check_wcs_compatibility()) {
        error_log('MKP Multisite Woo: Aviso de compatibilidade durante ativação');
    }
    
    // Criar tabelas necessárias
    mkp_multisite_woo_create_tables();
    
    // Flush rewrite rules
    flush_rewrite_rules();
    
    // Log de ativação
    error_log('MKP Multisite Woo: Plugin ativado com sucesso - Versão ' . MKP_MULTISITE_WOO_VERSION);
}

register_activation_hook(__FILE__, 'mkp_multisite_woo_activate');

/**
 * Desativar plugin
 */
function mkp_multisite_woo_deactivate() {
    flush_rewrite_rules();
    error_log('MKP Multisite Woo: Plugin desativado');
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
    
    error_log('MKP Multisite Woo: Tabelas do banco de dados criadas/atualizadas');
}

/**
 * Carregar classes do plugin
 */
function mkp_multisite_woo_load_classes() {
    // Verificar dependências
    if (!mkp_multisite_woo_check_dependencies()) {
        return;
    }
    
    // Carregar verificador de compatibilidade primeiro
    require_once MKP_MULTISITE_WOO_PLUGIN_DIR . 'includes/class-compatibility-checker.php';
    
    // Carregar classes principais
    require_once MKP_MULTISITE_WOO_PLUGIN_DIR . 'logs/class-activity-logger.php';
    require_once MKP_MULTISITE_WOO_PLUGIN_DIR . 'includes/class-subscription-manager.php';
    require_once MKP_MULTISITE_WOO_PLUGIN_DIR . 'includes/class-subdomain-manager.php';
    require_once MKP_MULTISITE_WOO_PLUGIN_DIR . 'includes/class-limiter-integration.php';
    require_once MKP_MULTISITE_WOO_PLUGIN_DIR . 'includes/class-redirect-handler.php';
    require_once MKP_MULTISITE_WOO_PLUGIN_DIR . 'includes/class-email-notifications.php';
    require_once MKP_MULTISITE_WOO_PLUGIN_DIR . 'backup/class-backup-manager.php';
    require_once MKP_MULTISITE_WOO_PLUGIN_DIR . 'api/class-rest-api.php';
    require_once MKP_MULTISITE_WOO_PLUGIN_DIR . 'includes/class-mkp-multisite-woo-integrator.php';
    
    // Carregar classes admin apenas no admin
    if (is_admin()) {
        require_once MKP_MULTISITE_WOO_PLUGIN_DIR . 'admin/class-admin-panel.php';
        
        // Carregar template de avisos se há problemas de compatibilidade
        $compatibility_checker = MKP_Compatibility_Checker::get_instance();
        if (!empty($compatibility_checker->get_compatibility_issues())) {
            add_action('admin_notices', function() {
                include MKP_MULTISITE_WOO_PLUGIN_DIR . 'admin/notices/compatibility-notice.php';
            });
        }
    }
    
    // Inicializar componentes principais
    if (class_exists('MKP_Multisite_Woo_Integrator')) {
        new MKP_Multisite_Woo_Integrator();
    } else {
        error_log('MKP Multisite Woo: Classe principal não encontrada');
    }
    
    // Inicializar API REST
    if (class_exists('MKP_REST_API')) {
        new MKP_REST_API();
    }
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
    $compatibility_link = '<a href="' . network_admin_url('admin.php?page=mkp-multisite-woo&tab=compatibility') . '">' . __('Compatibilidade', 'mkp-multisite-woo') . '</a>';
    
    array_unshift($links, $settings_link, $compatibility_link);
    return $links;
}

add_filter('plugin_action_links_' . MKP_MULTISITE_WOO_PLUGIN_BASENAME, 'mkp_multisite_woo_plugin_action_links');

/**
 * Hook para verificar compatibilidade após atualizações de plugins
 */
function mkp_multisite_woo_check_after_plugin_update($upgrader_object, $options) {
    if ($options['type'] === 'plugin') {
        // Verificar se WooCommerce Subscriptions foi atualizado
        $updated_plugins = isset($options['plugins']) ? $options['plugins'] : array();
        
        foreach ($updated_plugins as $plugin) {
            if (strpos($plugin, 'woocommerce-subscriptions') !== false) {
                // WooCommerce Subscriptions foi atualizado, verificar compatibilidade
                mkp_multisite_woo_check_wcs_compatibility();
                break;
            }
        }
    }
}

add_action('upgrader_process_complete', 'mkp_multisite_woo_check_after_plugin_update', 10, 2);

/**
 * Adicionar informações de debug ao Site Health
 */
function mkp_multisite_woo_add_debug_info($debug_info) {
    if (class_exists('MKP_Compatibility_Checker')) {
        $compatibility_checker = MKP_Compatibility_Checker::get_instance();
        $report = $compatibility_checker->generate_compatibility_report();
        
        $debug_info['mkp-multisite-woo'] = array(
            'label' => 'MKP Multisite WooCommerce Integrator',
            'fields' => array(
                'version' => array(
                    'label' => 'Versão do Plugin',
                    'value' => MKP_MULTISITE_WOO_VERSION,
                ),
                'wcs_version' => array(
                    'label' => 'WooCommerce Subscriptions',
                    'value' => $report['wcs_version'],
                ),
                'critical_errors' => array(
                    'label' => 'Erros Críticos',
                    'value' => $report['critical_errors'] ? 'Sim' : 'Não',
                ),
                'issues_count' => array(
                    'label' => 'Problemas Detectados',
                    'value' => count($report['issues']),
                ),
            ),
        );
    }
    
    return $debug_info;
}

add_filter('debug_information', 'mkp_multisite_woo_add_debug_info');
