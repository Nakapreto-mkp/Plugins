<?php
/**
 * Gerenciador de assinaturas WooCommerce
 * Atualizado para compatibilidade com WooCommerce Subscriptions 7.6.0+
 */

if (!defined('ABSPATH')) {
    exit;
}

class MKP_Subscription_Manager {
    
    private $activity_logger;
    
    public function __construct($activity_logger) {
        $this->activity_logger = $activity_logger;
    }
    
    /**
     * Verificar compatibilidade com versão do WooCommerce Subscriptions
     */
    private function is_wcs_compatible() {
        if (!function_exists('wcs_get_subscriptions')) {
            error_log('MKP Multisite Woo: Função wcs_get_subscriptions não encontrada');
            return false;
        }
        
        // Verificar se a versão suporta argumentos opcionais
        $reflection = new ReflectionFunction('wcs_get_subscriptions');
        $required_params = 0;
        
        foreach ($reflection->getParameters() as $param) {
            if (!$param->isOptional()) {
                $required_params++;
            }
        }
        
        return $required_params <= 1; // A função deve aceitar 0 ou 1 argumento obrigatório
    }
    
    /**
     * Wrapper seguro para wcs_get_subscriptions com compatibilidade de versão
     */
    private function get_subscriptions_safe($args = array()) {
        try {
            if (!function_exists('wcs_get_subscriptions')) {
                error_log('MKP Multisite Woo: WooCommerce Subscriptions não está ativo');
                return array();
            }
            
            // Para WooCommerce Subscriptions 7.6.0+, sempre passar um argumento
            if (version_compare(WC_Subscriptions::$version, '7.6.0', '>=')) {
                return wcs_get_subscriptions($args);
            } else {
                // Versões anteriores podem funcionar sem argumentos
                return empty($args) ? wcs_get_subscriptions() : wcs_get_subscriptions($args);
            }
            
        } catch (ArgumentCountError $e) {
            // Fallback para versões mais novas que requerem argumentos
            error_log('MKP Multisite Woo: ArgumentCountError capturado, usando array vazio como argumento');
            return wcs_get_subscriptions(array());
            
        } catch (Exception $e) {
            error_log('MKP Multisite Woo: Erro ao obter assinaturas - ' . $e->getMessage());
            return array();
        }
    }
    
    /**
     * Obter assinatura por ID do usuário
     */
    public function get_user_subscription($user_id) {
        try {
            if (!function_exists('wcs_get_users_subscriptions')) {
                error_log('MKP Multisite Woo: Função wcs_get_users_subscriptions não encontrada');
                return false;
            }
            
            $subscriptions = wcs_get_users_subscriptions($user_id);
            
            if (!empty($subscriptions)) {
                // Retornar a primeira assinatura ativa ou a mais recente
                foreach ($subscriptions as $subscription) {
                    if ($subscription->get_status() === 'active') {
                        return $subscription;
                    }
                }
                // Se não há ativa, retornar a mais recente
                return reset($subscriptions);
            }
            
        } catch (Exception $e) {
            error_log('MKP Multisite Woo: Erro ao obter assinatura do usuário ' . $user_id . ' - ' . $e->getMessage());
        }
        
        return false;
    }

    /**
     * Obter todas as assinaturas com sites associados
     */
    public function get_subscriptions_with_sites() {
        $results = array();
        
        try {
            // Usar wrapper seguro para obter assinaturas
            $subscriptions = $this->get_subscriptions_safe();
            
            foreach ($subscriptions as $subscription) {
                $site_id = $subscription->get_meta('_mkp_site_id');
                
                if ($site_id) {
                    $site_details = get_blog_details($site_id);
                    
                    $results[] = array(
                        'subscription' => $subscription,
                        'site_id' => $site_id,
                        'site_details' => $site_details,
                        'user_id' => $subscription->get_user_id(),
                        'status' => $subscription->get_status(),
                        'next_payment' => $subscription->get_date('next_payment'),
                        'total' => $subscription->get_total()
                    );
                }
            }
            
        } catch (Exception $e) {
            error_log('MKP Multisite Woo: Erro ao obter assinaturas com sites - ' . $e->getMessage());
        }
        
        return $results;
    }

    /**
     * Obter detalhes do plano de assinatura
     */
    public function get_subscription_plan_details($subscription) {
        $plan_details = array(
            'page_limit' => 10, // Padrão
            'storage_limit' => 1024, // MB
            'features' => array()
        );
        
        try {
            foreach ($subscription->get_items() as $item) {
                $product = $item->get_product();
                
                if ($product) {
                    // Obter limites do produto
                    $page_limit = $product->get_meta('_mkp_page_limit');
                    if ($page_limit) {
                        $plan_details['page_limit'] = intval($page_limit);
                    }
                    
                    $storage_limit = $product->get_meta('_mkp_storage_limit');
                    if ($storage_limit) {
                        $plan_details['storage_limit'] = intval($storage_limit);
                    }
                    
                    // Obter recursos adicionais
                    $features = $product->get_meta('_mkp_features');
                    if ($features) {
                        $plan_details['features'] = maybe_unserialize($features);
                    }
                }
            }
            
        } catch (Exception $e) {
            error_log('MKP Multisite Woo: Erro ao obter detalhes do plano - ' . $e->getMessage());
        }
        
        return $plan_details;
    }

    /**
     * Verificar se assinatura está ativa
     */
    public function is_subscription_active($subscription_id) {
        try {
            if (!function_exists('wcs_get_subscription')) {
                error_log('MKP Multisite Woo: Função wcs_get_subscription não encontrada');
                return false;
            }
            
            $subscription = wcs_get_subscription($subscription_id);
            
            if (!$subscription) {
                return false;
            }
            
            return $subscription->get_status() === 'active';
            
        } catch (Exception $e) {
            error_log('MKP Multisite Woo: Erro ao verificar status da assinatura ' . $subscription_id . ' - ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Obter URL de pagamento para assinatura suspensa
     */
    public function get_payment_url($subscription_id) {
        try {
            if (!function_exists('wcs_get_subscription')) {
                error_log('MKP Multisite Woo: Função wcs_get_subscription não encontrada');
                return false;
            }
            
            $subscription = wcs_get_subscription($subscription_id);
            
            if (!$subscription) {
                return false;
            }
            
            // Se há pagamento pendente, usar URL específica
            if ($subscription->needs_payment()) {
                return $subscription->get_checkout_payment_url();
            }
            
            // Caso contrário, redirecionar para minha conta
            return wc_get_page_permalink('myaccount');
            
        } catch (Exception $e) {
            error_log('MKP Multisite Woo: Erro ao obter URL de pagamento para assinatura ' . $subscription_id . ' - ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Obter estatísticas de assinaturas
     * CORRIGIDO: Compatibilidade com WooCommerce Subscriptions 7.6.0+
     */
    public function get_subscription_stats() {
        $stats = array(
            'total' => 0,
            'active' => 0,
            'suspended' => 0,
            'cancelled' => 0,
            'expired' => 0,
            'with_sites' => 0
        );
        
        try {
            // CORREÇÃO PRINCIPAL: Usar wrapper seguro que passa argumentos corretos
            $subscriptions = $this->get_subscriptions_safe(array());
            
            foreach ($subscriptions as $subscription) {
                $stats['total']++;
                
                $status = $subscription->get_status();
                if (isset($stats[$status])) {
                    $stats[$status]++;
                }
                
                if ($subscription->get_meta('_mkp_site_id')) {
                    $stats['with_sites']++;
                }
            }
            
        } catch (Exception $e) {
            error_log('MKP Multisite Woo: Erro ao obter estatísticas de assinaturas - ' . $e->getMessage());
            
            // Em caso de erro, retornar estatísticas zeradas com flag de erro
            $stats['error'] = true;
            $stats['error_message'] = $e->getMessage();
        }
        
        return $stats;
    }

    /**
     * Criar assinatura programaticamente (para testes ou migrações)
     */
    public function create_subscription($user_id, $product_id, $start_date = null) {
        try {
            $user = get_user_by('id', $user_id);
            $product = wc_get_product($product_id);
            
            if (!$user || !$product) {
                error_log('MKP Multisite Woo: Usuário ou produto inválido para criação de assinatura');
                return false;
            }
            
            if (!class_exists('WC_Subscriptions_Product') || !WC_Subscriptions_Product::is_subscription($product)) {
                error_log('MKP Multisite Woo: Produto não é uma assinatura válida');
                return false;
            }
            
            // Criar order temporária
            $order = wc_create_order();
            $order->set_customer_id($user_id);
            
            // Adicionar produto à order
            $order->add_product($product);
            $order->calculate_totals();
            
            // Criar assinatura a partir da order
            if (!function_exists('wcs_create_subscription')) {
                error_log('MKP Multisite Woo: Função wcs_create_subscription não encontrada');
                return false;
            }
            
            $subscription = wcs_create_subscription(array(
                'order_id' => $order->get_id(),
                'status' => 'active',
                'billing_period' => WC_Subscriptions_Product::get_period($product),
                'billing_interval' => WC_Subscriptions_Product::get_interval($product)
            ));
            
            if ($subscription) {
                $subscription->add_product($product);
                $subscription->set_customer_id($user_id);
                
                if ($start_date) {
                    $subscription->set_date('start', $start_date);
                }
                
                $subscription->calculate_totals();
                $subscription->save();
                
                // Log da criação
                if ($this->activity_logger) {
                    $this->activity_logger->log(0, $subscription->get_id(), $user_id, 'subscription_created', 'Assinatura criada programaticamente');
                }
                
                return $subscription;
            }
            
        } catch (Exception $e) {
            error_log('MKP Multisite Woo: Erro ao criar assinatura - ' . $e->getMessage());
        }
        
        return false;
    }

    /**
     * Notificar sobre pagamento em atraso
     */
    public function notify_payment_due($subscription_id) {
        try {
            if (!function_exists('wcs_get_subscription')) {
                error_log('MKP Multisite Woo: Função wcs_get_subscription não encontrada');
                return false;
            }
            
            $subscription = wcs_get_subscription($subscription_id);
            
            if (!$subscription) {
                return false;
            }
            
            $user_id = $subscription->get_user_id();
            $site_id = $subscription->get_meta('_mkp_site_id');
            
            // Log da notificação
            if ($this->activity_logger) {
                $this->activity_logger->log($site_id, $subscription_id, $user_id, 'payment_due_notification', 'Notificação de pagamento em atraso enviada');
            }
            
            return true;
            
        } catch (Exception $e) {
            error_log('MKP Multisite Woo: Erro ao notificar pagamento em atraso - ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Verificar compatibilidade e retornar informações de diagnóstico
     */
    public function get_compatibility_info() {
        $info = array(
            'wcs_version' => class_exists('WC_Subscriptions') ? WC_Subscriptions::$version : 'N/A',
            'wcs_functions_exist' => array(
                'wcs_get_subscriptions' => function_exists('wcs_get_subscriptions'),
                'wcs_get_subscription' => function_exists('wcs_get_subscription'),
                'wcs_get_users_subscriptions' => function_exists('wcs_get_users_subscriptions'),
                'wcs_create_subscription' => function_exists('wcs_create_subscription')
            ),
            'is_compatible' => $this->is_wcs_compatible(),
            'php_version' => PHP_VERSION,
            'wp_version' => get_bloginfo('version')
        );
        
        return $info;
    }
}
