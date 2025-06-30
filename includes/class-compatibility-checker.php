<?php
/**
 * Verificador de compatibilidade para WooCommerce Subscriptions
 * 
 * @package MKP_Multisite_Woo
 * @since 1.0.1
 */

if (!defined('ABSPATH')) {
    exit;
}

class MKP_Compatibility_Checker {
    
    private static $instance = null;
    private $compatibility_issues = array();
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('admin_init', array($this, 'check_compatibility'));
        add_action('admin_notices', array($this, 'display_compatibility_notices'));
    }
    
    /**
     * Verificar compatibilidade com todas as dependências
     */
    public function check_compatibility() {
        $this->compatibility_issues = array();
        
        // Verificar WordPress Multisite
        if (!is_multisite()) {
            $this->compatibility_issues[] = array(
                'type' => 'error',
                'message' => 'WordPress Multisite deve estar habilitado para usar este plugin.'
            );
        }
        
        // Verificar WooCommerce
        if (!class_exists('WooCommerce')) {
            $this->compatibility_issues[] = array(
                'type' => 'error',
                'message' => 'WooCommerce deve estar instalado e ativado.'
            );
        }
        
        // Verificar WooCommerce Subscriptions
        $this->check_woocommerce_subscriptions();
        
        // Verificar versão do PHP
        if (version_compare(PHP_VERSION, '8.0', '<')) {
            $this->compatibility_issues[] = array(
                'type' => 'warning',
                'message' => 'PHP 8.0 ou superior é recomendado. Versão atual: ' . PHP_VERSION
            );
        }
        
        // Verificar versão do WordPress
        if (version_compare(get_bloginfo('version'), '6.0', '<')) {
            $this->compatibility_issues[] = array(
                'type' => 'warning',
                'message' => 'WordPress 6.0 ou superior é recomendado. Versão atual: ' . get_bloginfo('version')
            );
        }
    }
    
    /**
     * Verificar compatibilidade específica com WooCommerce Subscriptions
     */
    private function check_woocommerce_subscriptions() {
        if (!class_exists('WC_Subscriptions')) {
            $this->compatibility_issues[] = array(
                'type' => 'error',
                'message' => 'WooCommerce Subscriptions deve estar instalado e ativado.'
            );
            return;
        }
        
        $wcs_version = WC_Subscriptions::$version;
        
        // Verificar se as funções necessárias existem
        $required_functions = array(
            'wcs_get_subscriptions',
            'wcs_get_subscription',
            'wcs_get_users_subscriptions',
            'wcs_create_subscription'
        );
        
        foreach ($required_functions as $function) {
            if (!function_exists($function)) {
                $this->compatibility_issues[] = array(
                    'type' => 'error',
                    'message' => sprintf('Função %s não encontrada. Verifique se o WooCommerce Subscriptions está corretamente instalado.', $function)
                );
            }
        }
        
        // Verificar compatibilidade com versão específica
        if (version_compare($wcs_version, '7.6.0', '>=')) {
            // Versão 7.6.0+ detectada - verificar se as correções estão aplicadas
            $this->compatibility_issues[] = array(
                'type' => 'info',
                'message' => sprintf('WooCommerce Subscriptions %s detectado. Plugin atualizado para compatibilidade.', $wcs_version)
            );
        } elseif (version_compare($wcs_version, '7.0.0', '<')) {
            $this->compatibility_issues[] = array(
                'type' => 'warning',
                'message' => sprintf('WooCommerce Subscriptions %s é muito antigo. Versão 7.0.0 ou superior é recomendada.', $wcs_version)
            );
        }
        
        // Testar chamada da função problemática
        $this->test_wcs_get_subscriptions_function();
    }
    
    /**
     * Testar especificamente a função wcs_get_subscriptions
     */
    private function test_wcs_get_subscriptions_function() {
        if (!function_exists('wcs_get_subscriptions')) {
            return;
        }
        
        try {
            // Tentar chamada sem argumentos (versões antigas)
            $reflection = new ReflectionFunction('wcs_get_subscriptions');
            $required_params = 0;
            
            foreach ($reflection->getParameters() as $param) {
                if (!$param->isOptional()) {
                    $required_params++;
                }
            }
            
            if ($required_params > 0) {
                // Função requer argumentos - versão 7.6.0+
                try {
                    wcs_get_subscriptions(array());
                    $this->compatibility_issues[] = array(
                        'type' => 'success', 
                        'message' => 'Função wcs_get_subscriptions testada com sucesso com argumentos.'
                    );
                } catch (Exception $e) {
                    $this->compatibility_issues[] = array(
                        'type' => 'error',
                        'message' => 'Erro ao testar wcs_get_subscriptions: ' . $e->getMessage()
                    );
                }
            } else {
                // Função não requer argumentos - versão anterior
                $this->compatibility_issues[] = array(
                    'type' => 'info',
                    'message' => 'Função wcs_get_subscriptions compatível com versões anteriores.'
                );
            }
            
        } catch (Exception $e) {
            $this->compatibility_issues[] = array(
                'type' => 'error',
                'message' => 'Erro ao analisar função wcs_get_subscriptions: ' . $e->getMessage()
            );
        }
    }
    
    /**
     * Exibir avisos de compatibilidade no admin
     */
    public function display_compatibility_notices() {
        if (empty($this->compatibility_issues)) {
            return;
        }
        
        foreach ($this->compatibility_issues as $issue) {
            $class = 'notice';
            
            switch ($issue['type']) {
                case 'error':
                    $class .= ' notice-error';
                    break;
                case 'warning':
                    $class .= ' notice-warning';
                    break;
                case 'success':
                    $class .= ' notice-success';
                    break;
                case 'info':
                default:
                    $class .= ' notice-info';
                    break;
            }
            
            echo '<div class="' . esc_attr($class) . '"><p>';
            echo '<strong>MKP Multisite WooCommerce Integrator:</strong> ';
            echo esc_html($issue['message']);
            echo '</p></div>';
        }
    }
    
    /**
     * Obter todas as questões de compatibilidade
     */
    public function get_compatibility_issues() {
        return $this->compatibility_issues;
    }
    
    /**
     * Verificar se há erros críticos de compatibilidade
     */
    public function has_critical_errors() {
        foreach ($this->compatibility_issues as $issue) {
            if ($issue['type'] === 'error') {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Gerar relatório de compatibilidade
     */
    public function generate_compatibility_report() {
        $report = array(
            'timestamp' => current_time('mysql'),
            'wp_version' => get_bloginfo('version'),
            'php_version' => PHP_VERSION,
            'is_multisite' => is_multisite(),
            'wc_version' => class_exists('WooCommerce') ? WC()->version : 'N/A',
            'wcs_version' => class_exists('WC_Subscriptions') ? WC_Subscriptions::$version : 'N/A',
            'plugin_version' => MKP_MULTISITE_WOO_VERSION,
            'issues' => $this->compatibility_issues,
            'critical_errors' => $this->has_critical_errors()
        );
        
        return $report;
    }
}

// Inicializar verificador de compatibilidade
MKP_Compatibility_Checker::get_instance();
