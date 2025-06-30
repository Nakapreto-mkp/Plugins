<?php
/**
 * Gerenciador de subdomínios WordPress Multisite
 */

if (!defined('ABSPATH')) {
    exit;
}

class MKP_Subdomain_Manager {
    
    private $activity_logger;
    private $backup_manager;
    
    public function __construct($activity_logger, $backup_manager) {
        $this->activity_logger = $activity_logger;
        $this->backup_manager = $backup_manager;
    }
    
    /**
     * Criar novo subdomínio para usuário
     */
    public function create_subdomain($user_id, $subscription) {
        $user = get_user_by('id', $user_id);
        
        if (!$user) {
            return false;
        }
        
        // Gerar nome do subdomínio
        $subdomain = $this->generate_subdomain($user);
        
        if (!$subdomain) {
            return false;
        }
        
        // Obter domínio principal
        $main_domain = $this->get_main_domain();
        $domain = $subdomain . '.' . $main_domain;
        $path = '/';
        
        // Verificar se subdomínio já existe
        if ($this->subdomain_exists($subdomain)) {
            // Tentar variações do nome
            $counter = 1;
            do {
                $test_subdomain = $subdomain . $counter;
                $counter++;
            } while ($this->subdomain_exists($test_subdomain) && $counter <= 100);
            
            if ($counter > 100) {
                return false; // Não conseguiu encontrar nome disponível
            }
            
            $subdomain = $test_subdomain;
            $domain = $subdomain . '.' . $main_domain;
        }
        
        // Criar site no WordPress Multisite
        $site_id = wpmu_create_blog($domain, $path, $user->display_name . ' Store', $user_id, array(), 1);
        
        if (is_wp_error($site_id)) {
            $this->activity_logger->log(0, $subscription->get_id(), $user_id, 'site_creation_failed', 'Erro ao criar subdomínio: ' . $site_id->get_error_message());
            return false;
        }
        
        // Configurar o novo site
        $this->setup_new_site($site_id, $user_id, $subscription);
        
        // Salvar configuração do subdomínio
        $this->save_subdomain_config($site_id, $subscription->get_id(), $subdomain);
        
        // Log da criação
        $this->activity_logger->log($site_id, $subscription->get_id(), $user_id, 'site_created', "Subdomínio criado: $domain");
        
        return $site_id;
    }
    
    /**
     * Configurar novo site
     */
    private function setup_new_site($site_id, $user_id, $subscription) {
        switch_to_blog($site_id);
        
        // Ativar WooCommerce se disponível
        if (is_plugin_active_for_network('woocommerce/woocommerce.php')) {
            activate_plugin('woocommerce/woocommerce.php');
        }
        
        // Ativar Limiter MKP Pro se disponível
        if (is_plugin_active_for_network('limiter-mkp-pro/limiter-mkp-pro.php')) {
            activate_plugin('limiter-mkp-pro/limiter-mkp-pro.php');
        }
        
        // Configurar usuário como administrador do site
        add_user_to_blog($site_id, $user_id, 'administrator');
        
        // Configurar tema padrão
        switch_theme(get_network_option(null, 'mkp_default_theme', get_option('stylesheet')));
        
        // Configurar páginas básicas
        $this->create_basic_pages($site_id);
        
        // Configurar WooCommerce básico
        $this->setup_woocommerce_basics();
        
        restore_current_blog();
    }
    
    /**
     * Criar páginas básicas do site
     */
    private function create_basic_pages($site_id) {
        $pages = array(
            'Início' => 'Bem-vindo ao seu novo site!',
            'Sobre' => 'Conte sua história aqui.',
            'Contato' => 'Entre em contato conosco.',
            'Loja' => '[woocommerce_shop]'
        );
        
        foreach ($pages as $title => $content) {
            $page_data = array(
                'post_title' => $title,
                'post_content' => $content,
                'post_status' => 'publish',
                'post_type' => 'page',
                'post_author' => get_current_user_id()
            );
            
            $page_id = wp_insert_post($page_data);
            
            // Definir página inicial
            if ($title === 'Início') {
                update_option('page_on_front', $page_id);
                update_option('show_on_front', 'page');
            }
            
            // Definir página da loja
            if ($title === 'Loja') {
                update_option('woocommerce_shop_page_id', $page_id);
            }
        }
    }
    
    /**
     * Configurar WooCommerce básico
     */
    private function setup_woocommerce_basics() {
        if (!class_exists('WooCommerce')) {
            return;
        }
        
        // Configurações básicas do WooCommerce
        update_option('woocommerce_currency', 'BRL');
        update_option('woocommerce_currency_pos', 'left');
        update_option('woocommerce_price_thousand_sep', '.');
        update_option('woocommerce_price_decimal_sep', ',');
        update_option('woocommerce_price_num_decimals', 2);
        
        // Configurar país
        update_option('woocommerce_default_country', 'BR:SP');
        
        // Configurar páginas do WooCommerce
        WC_Install::create_pages();
    }
    
    /**
     * Gerar nome do subdomínio
     */
    private function generate_subdomain($user) {
        // Tentar usar user_login primeiro
        $subdomain = sanitize_title($user->user_login);
        
        // Se não for válido, usar display_name
        if (empty($subdomain) || strlen($subdomain) < 3) {
            $subdomain = sanitize_title($user->display_name);
        }
        
        // Se ainda não for válido, usar parte do email
        if (empty($subdomain) || strlen($subdomain) < 3) {
            $email_parts = explode('@', $user->user_email);
            $subdomain = sanitize_title($email_parts[0]);
        }
        
        // Remover caracteres especiais e garantir formato válido
        $subdomain = preg_replace('/[^a-z0-9\-]/', '', strtolower($subdomain));
        $subdomain = trim($subdomain, '-');
        
        // Garantir tamanho mínimo e máximo
        if (strlen($subdomain) < 3) {
            $subdomain = 'site' . $user->ID;
        }
        
        if (strlen($subdomain) > 50) {
            $subdomain = substr($subdomain, 0, 50);
        }
        
        return $subdomain;
    }
    
    /**
     * Verificar se subdomínio existe
     */
    private function subdomain_exists($subdomain) {
        $main_domain = $this->get_main_domain();
        $domain = $subdomain . '.' . $main_domain;
        
        $site = get_blog_details($domain);
        
        return $site !== false;
    }
    
    /**
     * Obter domínio principal
     */
    private function get_main_domain() {
        $current_site = get_current_site();
        return $current_site->domain;
    }
    
    /**
     * Salvar configuração do subdomínio
     */
    private function save_subdomain_config($site_id, $subscription_id, $subdomain) {
        global $wpdb;
        
        $table = $wpdb->base_prefix . 'mkp_subdomain_config';
        
        $wpdb->insert(
            $table,
            array(
                'site_id' => $site_id,
                'subscription_id' => $subscription_id,
                'subdomain' => $subdomain,
                'status' => 'active',
                'page_limit' => 10,
                'current_pages' => 4, // Páginas básicas criadas
                'created_at' => current_time('mysql')
            ),
            array('%d', '%d', '%s', '%s', '%d', '%d', '%s')
        );
    }
    
    /**
     * Ativar site
     */
    public function activate_site($site_id) {
        // Ativar no WordPress
        update_blog_status($site_id, 'archived', 0);
        update_blog_status($site_id, 'spam', 0);
        update_blog_status($site_id, 'deleted', 0);
        
        // Atualizar status na configuração
        $this->update_subdomain_status($site_id, 'active');
        
        $this->activity_logger->log($site_id, 0, 0, 'site_activated', 'Site ativado');
    }
    
    /**
     * Suspender site
     */
    public function suspend_site($site_id) {
        // Suspender no WordPress (marcar como spam temporariamente)
        update_blog_status($site_id, 'spam', 1);
        
        // Atualizar status na configuração
        $this->update_subdomain_status($site_id, 'suspended');
        
        $this->activity_logger->log($site_id, 0, 0, 'site_suspended', 'Site suspenso');
    }
    
    /**
     * Arquivar site
     */
    public function archive_site($site_id) {
        // Arquivar no WordPress
        update_blog_status($site_id, 'archived', 1);
        
        // Atualizar status na configuração
        $this->update_subdomain_status($site_id, 'archived');
        
        $this->activity_logger->log($site_id, 0, 0, 'site_archived', 'Site arquivado');
    }
    
    /**
     * Atualizar status do subdomínio
     */
    private function update_subdomain_status($site_id, $status) {
        global $wpdb;
        
        $table = $wpdb->base_prefix . 'mkp_subdomain_config';
        
        $wpdb->update(
            $table,
            array('status' => $status, 'updated_at' => current_time('mysql')),
            array('site_id' => $site_id),
            array('%s', '%s'),
            array('%d')
        );
    }
    
    /**
     * Obter status do site
     */
    public function get_site_status($site_id) {
        global $wpdb;
        
        $table = $wpdb->base_prefix . 'mkp_subdomain_config';
        
        $status = $wpdb->get_var($wpdb->prepare(
            "SELECT status FROM $table WHERE site_id = %d",
            $site_id
        ));
        
        return $status ?: 'unknown';
    }
    
    /**
     * Obter site do usuário
     */
    public function get_user_site($user_id) {
        $sites = get_blogs_of_user($user_id);
        
        foreach ($sites as $site) {
            if ($site->userblog_id != 1) { // Não contar site principal
                return $site;
            }
        }
        
        return false;
    }
    
    /**
     * Obter configuração do subdomínio
     */
    public function get_subdomain_config($site_id) {
        global $wpdb;
        
        $table = $wpdb->base_prefix . 'mkp_subdomain_config';
        
        $config = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE site_id = %d",
            $site_id
        ));
        
        return $config;
    }
    
    /**
     * Atualizar limite de páginas
     */
    public function update_page_limit($site_id, $new_limit) {
        global $wpdb;
        
        $table = $wpdb->base_prefix . 'mkp_subdomain_config';
        
        $wpdb->update(
            $table,
            array('page_limit' => $new_limit, 'updated_at' => current_time('mysql')),
            array('site_id' => $site_id),
            array('%d', '%s'),
            array('%d')
        );
        
        $this->activity_logger->log($site_id, 0, 0, 'page_limit_updated', "Limite de páginas atualizado para: $new_limit");
    }
    
    /**
     * Atualizar contagem de páginas
     */
    public function update_page_count($site_id, $current_pages) {
        global $wpdb;
        
        $table = $wpdb->base_prefix . 'mkp_subdomain_config';
        
        $wpdb->update(
            $table,
            array('current_pages' => $current_pages, 'updated_at' => current_time('mysql')),
            array('site_id' => $site_id),
            array('%d', '%s'),
            array('%d')
        );
    }
}