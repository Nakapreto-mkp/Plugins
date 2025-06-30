<?php
/**
 * Integração com plugin Limiter MKP Pro
 */

if (!defined('ABSPATH')) {
    exit;
}

class MKP_Limiter_Integration {
    
    private $activity_logger;
    
    public function __construct($activity_logger) {
        $this->activity_logger = $activity_logger;
        
        // Hooks para monitorar criação/exclusão de páginas
        add_action('wp_insert_post', array($this, 'track_page_creation'), 10, 3);
        add_action('before_delete_post', array($this, 'track_page_deletion'), 10, 1);
    }
    
    /**
     * Configurar limites do site baseado na assinatura
     */
    public function setup_site_limits($site_id, $subscription) {
        // Obter detalhes do plano
        $plan_details = $this->get_plan_details($subscription);
        
        switch_to_blog($site_id);
        
        // Configurar Limiter MKP Pro se estiver ativo
        if (class_exists('Limiter_MKP_Pro')) {
            $this->configure_limiter_pro($plan_details);
        }
        
        // Atualizar configuração na nossa tabela
        global $wpdb;
        $table = $wpdb->base_prefix . 'mkp_subdomain_config';
        
        $wpdb->update(
            $table,
            array(
                'page_limit' => $plan_details['page_limit'],
                'updated_at' => current_time('mysql')
            ),
            array('site_id' => $site_id),
            array('%d', '%s'),
            array('%d')
        );
        
        restore_current_blog();
        
        $this->activity_logger->log($site_id, $subscription->get_id(), $subscription->get_user_id(), 'limits_configured', 
            'Limites configurados - Páginas: ' . $plan_details['page_limit']);
    }
    
    /**
     * Atualizar limites do site
     */
    public function update_site_limits($site_id, $subscription) {
        $plan_details = $this->get_plan_details($subscription);
        
        switch_to_blog($site_id);
        
        if (class_exists('Limiter_MKP_Pro')) {
            $this->configure_limiter_pro($plan_details);
        }
        
        // Atualizar nossa configuração
        global $wpdb;
        $table = $wpdb->base_prefix . 'mkp_subdomain_config';
        
        $wpdb->update(
            $table,
            array(
                'page_limit' => $plan_details['page_limit'],
                'updated_at' => current_time('mysql')
            ),
            array('site_id' => $site_id),
            array('%d', '%s'),
            array('%d')
        );
        
        restore_current_blog();
        
        $this->activity_logger->log($site_id, $subscription->get_id(), $subscription->get_user_id(), 'limits_updated', 
            'Limites atualizados - Páginas: ' . $plan_details['page_limit']);
    }
    
    /**
     * Configurar Limiter MKP Pro
     */
    private function configure_limiter_pro($plan_details) {
        // Assumindo que o Limiter MKP Pro tem estas opções
        update_option('limiter_mkp_page_limit', $plan_details['page_limit']);
        update_option('limiter_mkp_post_limit', $plan_details['post_limit'] ?? 50);
        update_option('limiter_mkp_storage_limit', $plan_details['storage_limit'] ?? 1024);
        update_option('limiter_mkp_enabled', true);
        
        // Configurar recursos específicos
        if (isset($plan_details['features'])) {
            foreach ($plan_details['features'] as $feature => $enabled) {
                update_option('limiter_mkp_feature_' . $feature, $enabled);
            }
        }
    }
    
    /**
     * Obter detalhes do plano da assinatura
     */
    private function get_plan_details($subscription) {
        $plan_details = array(
            'page_limit' => 10,
            'post_limit' => 50,
            'storage_limit' => 1024, // MB
            'features' => array()
        );
        
        foreach ($subscription->get_items() as $item) {
            $product = $item->get_product();
            
            if ($product) {
                // Obter limites específicos do produto
                $page_limit = $product->get_meta('_mkp_page_limit');
                if ($page_limit) {
                    $plan_details['page_limit'] = intval($page_limit);
                }
                
                $post_limit = $product->get_meta('_mkp_post_limit');
                if ($post_limit) {
                    $plan_details['post_limit'] = intval($post_limit);
                }
                
                $storage_limit = $product->get_meta('_mkp_storage_limit');
                if ($storage_limit) {
                    $plan_details['storage_limit'] = intval($storage_limit);
                }
                
                // Recursos adicionais
                $features = $product->get_meta('_mkp_features');
                if ($features) {
                    $plan_details['features'] = maybe_unserialize($features);
                }
                
                // Detectar plano baseado no nome/categoria
                $this->detect_plan_by_product($product, $plan_details);
            }
        }
        
        return $plan_details;
    }
    
    /**
     * Detectar plano baseado no produto
     */
    private function detect_plan_by_product($product, &$plan_details) {
        $product_name = strtolower($product->get_name());
        
        // Planos básicos
        if (strpos($product_name, 'básico') !== false || strpos($product_name, 'starter') !== false) {
            $plan_details['page_limit'] = 5;
            $plan_details['post_limit'] = 20;
            $plan_details['storage_limit'] = 512;
        }
        
        // Planos profissionais
        elseif (strpos($product_name, 'profissional') !== false || strpos($product_name, 'pro') !== false) {
            $plan_details['page_limit'] = 25;
            $plan_details['post_limit'] = 100;
            $plan_details['storage_limit'] = 2048;
            $plan_details['features']['custom_css'] = true;
            $plan_details['features']['advanced_widgets'] = true;
        }
        
        // Planos premium/empresariais
        elseif (strpos($product_name, 'premium') !== false || strpos($product_name, 'empresarial') !== false) {
            $plan_details['page_limit'] = 100;
            $plan_details['post_limit'] = 500;
            $plan_details['storage_limit'] = 5120;
            $plan_details['features']['custom_css'] = true;
            $plan_details['features']['advanced_widgets'] = true;
            $plan_details['features']['custom_plugins'] = true;
            $plan_details['features']['priority_support'] = true;
        }
    }
    
    /**
     * Rastrear criação de páginas
     */
    public function track_page_creation($post_id, $post, $update) {
        // Só rastrear se não for update e for página
        if ($update || $post->post_type !== 'page' || $post->post_status !== 'publish') {
            return;
        }
        
        // Verificar se estamos em um subsite
        $site_id = get_current_blog_id();
        if ($site_id == 1) {
            return; // Site principal
        }
        
        // Atualizar contagem de páginas
        $this->update_page_count($site_id);
        
        // Verificar se excedeu o limite
        $this->check_page_limit($site_id, $post_id);
    }
    
    /**
     * Rastrear exclusão de páginas
     */
    public function track_page_deletion($post_id) {
        $post = get_post($post_id);
        
        if ($post && $post->post_type === 'page' && $post->post_status === 'publish') {
            $site_id = get_current_blog_id();
            
            if ($site_id != 1) {
                // Atualizar contagem após exclusão
                wp_schedule_single_event(time() + 5, 'mkp_update_page_count_after_deletion', array($site_id));
            }
        }
    }
    
    /**
     * Atualizar contagem de páginas
     */
    private function update_page_count($site_id) {
        // Contar páginas publicadas
        $page_count = wp_count_posts('page');
        $current_pages = intval($page_count->publish);
        
        // Atualizar na nossa tabela
        global $wpdb;
        $table = $wpdb->base_prefix . 'mkp_subdomain_config';
        
        $wpdb->update(
            $table,
            array('current_pages' => $current_pages, 'updated_at' => current_time('mysql')),
            array('site_id' => $site_id),
            array('%d', '%s'),
            array('%d')
        );
        
        return $current_pages;
    }
    
    /**
     * Verificar limite de páginas
     */
    private function check_page_limit($site_id, $post_id) {
        global $wpdb;
        
        $table = $wpdb->base_prefix . 'mkp_subdomain_config';
        $config = $wpdb->get_row($wpdb->prepare(
            "SELECT page_limit, current_pages FROM $table WHERE site_id = %d",
            $site_id
        ));
        
        if ($config && $config->current_pages > $config->page_limit) {
            // Limite excedido - reverter para rascunho
            wp_update_post(array(
                'ID' => $post_id,
                'post_status' => 'draft'
            ));
            
            // Adicionar mensagem de erro
            add_action('admin_notices', function() use ($config) {
                echo '<div class="notice notice-error"><p>';
                printf(
                    __('Limite de páginas excedido! Seu plano permite %d páginas, mas você tem %d. A página foi salva como rascunho.', 'mkp-multisite-woo'),
                    $config->page_limit,
                    $config->current_pages
                );
                echo '</p></div>';
            });
            
            // Log do evento
            $this->activity_logger->log($site_id, 0, get_current_user_id(), 'page_limit_exceeded', 
                "Limite excedido: {$config->current_pages}/{$config->page_limit}");
        }
    }
    
    /**
     * Verificar se usuário pode criar mais páginas
     */
    public function can_create_page($site_id) {
        global $wpdb;
        
        $table = $wpdb->base_prefix . 'mkp_subdomain_config';
        $config = $wpdb->get_row($wpdb->prepare(
            "SELECT page_limit, current_pages FROM $table WHERE site_id = %d",
            $site_id
        ));
        
        if (!$config) {
            return true; // Se não há configuração, permitir
        }
        
        return $config->current_pages < $config->page_limit;
    }
    
    /**
     * Obter informações de uso do site
     */
    public function get_site_usage($site_id) {
        global $wpdb;
        
        switch_to_blog($site_id);
        
        // Contagem de posts e páginas
        $page_count = wp_count_posts('page');
        $post_count = wp_count_posts('post');
        
        // Obter configurações
        $table = $wpdb->base_prefix . 'mkp_subdomain_config';
        $config = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE site_id = %d",
            $site_id
        ));
        
        restore_current_blog();
        
        $usage = array(
            'pages' => array(
                'current' => intval($page_count->publish),
                'limit' => $config ? $config->page_limit : 10,
                'percentage' => $config ? round(($page_count->publish / $config->page_limit) * 100, 2) : 0
            ),
            'posts' => array(
                'current' => intval($post_count->publish),
                'limit' => 50, // Padrão
                'percentage' => round(($post_count->publish / 50) * 100, 2)
            )
        );
        
        return $usage;
    }
}

// Hook para atualizar contagem após exclusão
add_action('mkp_update_page_count_after_deletion', function($site_id) {
    switch_to_blog($site_id);
    $page_count = wp_count_posts('page');
    $current_pages = intval($page_count->publish);
    
    global $wpdb;
    $table = $wpdb->base_prefix . 'mkp_subdomain_config';
    
    $wpdb->update(
        $table,
        array('current_pages' => $current_pages, 'updated_at' => current_time('mysql')),
        array('site_id' => $site_id),
        array('%d', '%s'),
        array('%d')
    );
    
    restore_current_blog();
});