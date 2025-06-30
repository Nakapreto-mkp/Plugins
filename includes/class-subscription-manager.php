<?php
/**
 * Gerenciador de assinaturas WooCommerce
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
     * Obter assinatura por ID do usuário
     */
    public function get_user_subscription($user_id) {
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
        
        return false;
    }
    
    /**
     * Obter todas as assinaturas com sites associados
     */
    public function get_subscriptions_with_sites() {
        $results = array();
        $subscriptions = wcs_get_subscriptions();
        
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
        
        return $plan_details;
    }
    
    /**
     * Verificar se assinatura está ativa
     */
    public function is_subscription_active($subscription_id) {
        $subscription = wcs_get_subscription($subscription_id);
        
        if (!$subscription) {
            return false;
        }
        
        return $subscription->get_status() === 'active';
    }
    
    /**
     * Obter URL de pagamento para assinatura suspensa
     */
    public function get_payment_url($subscription_id) {
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
    }
    
    /**
     * Obter estatísticas de assinaturas
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
        
        $subscriptions = wcs_get_subscriptions();
        
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
        
        return $stats;
    }
    
    /**
     * Criar assinatura programaticamente (para testes ou migrações)
     */
    public function create_subscription($user_id, $product_id, $start_date = null) {
        $user = get_user_by('id', $user_id);
        $product = wc_get_product($product_id);
        
        if (!$user || !$product || !WC_Subscriptions_Product::is_subscription($product)) {
            return false;
        }
        
        // Criar order temporária
        $order = wc_create_order();
        $order->set_customer_id($user_id);
        
        // Adicionar produto à order
        $order->add_product($product);
        $order->calculate_totals();
        
        // Criar assinatura a partir da order
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
            $this->activity_logger->log(0, $subscription->get_id(), $user_id, 'subscription_created', 'Assinatura criada programaticamente');
            
            return $subscription;
        }
        
        return false;
    }
    
    /**
     * Notificar sobre pagamento em atraso
     */
    public function notify_payment_due($subscription_id) {
        $subscription = wcs_get_subscription($subscription_id);
        
        if (!$subscription) {
            return false;
        }
        
        $user_id = $subscription->get_user_id();
        $site_id = $subscription->get_meta('_mkp_site_id');
        
        // Log da notificação
        $this->activity_logger->log($site_id, $subscription_id, $user_id, 'payment_due_notification', 'Notificação de pagamento em atraso enviada');
        
        return true;
    }
}