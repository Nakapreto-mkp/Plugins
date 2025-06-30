<?php
/**
 * Integração com sistema de limites MKP
 * 
 * @package MKP_Multisite_Woo
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class MKP_Limiter_Integration {
    
    private $activity_logger;
    
    public function __construct($activity_logger) {
        $this->activity_logger = $activity_logger;
        $this->init_hooks();
    }
    
    /**
     * Inicializar hooks para limitações
     */
    private function init_hooks() {
        // Hooks para páginas/posts
        add_action('wp_insert_post', array($this, 'check_page_limit'), 10, 3);
        add_action('before_delete_post', array($this, 'update_page_count_on_delete'));
        
        // Hooks para uploads
        add_filter('wp_handle_upload_prefilter', array($this, 'check_storage_limit'));
        add_action('delete_attachment', array($this, 'update_storage_on_delete'));
        
        // Hooks para usuários
        add_action('add_user_to_blog', array($this, 'check_user_limit'), 10, 3);
        
        // Hooks para plugins/temas
        add_filter('user_can', array($this, 'limit_plugin_theme_access'), 10, 4);
        
        // Dashboard customizado
        add_action('wp_dashboard_setup', array($this, 'setup_usage_dashboard'));
        
        // Avisos de limite
        add_action('admin_notices', array($this, 'display_limit_warnings'));
    }
    
    /**
     * Verificar limite de páginas ao criar nova página/post
     */
    public function check_page_limit($post_id, $post, $update) {
        // Só verificar em sites gerenciados pelo plugin
        if (!$this->is_managed_site()) {
            return;
        }
        
        // Ignorar revisões e posts automáticos
        if (wp_is_post_revision($post_id) || $post->post_status === 'auto-draft') {
            return;
        }
        
        // Só contar posts publicados
        if ($post->post_status !== 'publish') {
            return;
        }
        
        $limits = $this->get_site_limits();
        $current_count = $this->count_published_content();
        
        if ($limits['page_limit'] > 0 && $current_count > $limits['page_limit']) {
            // Reverter para rascunho se excedeu o limite
            wp_update_post(array(
                'ID' => $post_id,
                'post_status' => 'draft'
            ));
            
            // Log do limite excedido
            $this->activity_logger->log(
                get_current_blog_id(),
                $this->get_site_subscription_id(),
                get_current_user_id(),
                'page_limit_exceeded',
                "Tentativa de publicar excedeu limite de {$limits['page_limit']} páginas",
                'warning'
            );
            
            // Adicionar aviso na sessão
            add_filter('redirect_post_location', function($location) use ($limits) {
                return add_query_arg('mkp_limit_exceeded', 'pages', $location);
            });
        }
    }
    
    /**
     * Atualizar contagem ao deletar post
     */
    public function update_page_count_on_delete($post_id) {
        if (!$this->is_managed_site()) {
            return;
        }
        
        $post = get_post($post_id);
        if ($post && $post->post_status === 'publish') {
            // Atualizar estatísticas de uso
            $this->update_usage_stats();
        }
    }
    
    /**
     * Verificar limite de armazenamento no upload
     */
    public function check_storage_limit($file) {
        if (!$this->is_managed_site()) {
            return $file;
        }
        
        $limits = $this->get_site_limits();
        $current_storage = $this->get_current_storage_usage();
        $file_size = $file['size'];
        
        // Converter limite para bytes
        $storage_limit_bytes = $limits['storage_limit'] * 1024 * 1024; // MB para bytes
        
        if ($storage_limit_bytes > 0 && ($current_storage + $file_size) > $storage_limit_bytes) {
            $file['error'] = sprintf(
                'Limite de armazenamento excedido. Limite: %s MB, Usado: %s MB, Arquivo: %s MB',
                $limits['storage_limit'],
                round($current_storage / 1024 / 1024, 2),
                round($file_size / 1024 / 1024, 2)
            );
            
            // Log do limite excedido
            $this->activity_logger->log(
                get_current_blog_id(),
                $this->get_site_subscription_id(),
                get_current_user_id(),
                'storage_limit_exceeded',
                "Tentativa de upload excedeu limite de {$limits['storage_limit']} MB",
                'warning'
            );
        }
        
        return $file;
    }
    
    /**
     * Atualizar armazenamento ao deletar anexo
     */
    public function update_storage_on_delete($attachment_id) {
        if (!$this->is_managed_site()) {
            return;
        }
        
        // Atualizar estatísticas após delete
        wp_schedule_single_event(time() + 60, 'mkp_update_storage_stats', array(get_current_blog_id()));
    }
    
    /**
     * Verificar limite de usuários
     */
    public function check_user_limit($user_id, $role, $blog_id) {
        if (!$this->is_managed_site($blog_id)) {
            return;
        }
        
        $limits = $this->get_site_limits($blog_id);
        $current_users = count_users()['total_users'];
        
        if ($limits['user_limit'] > 0 && $current_users > $limits['user_limit']) {
            // Remover usuário se excedeu limite
            remove_user_from_blog($user_id, $blog_id);
            
            // Log do limite excedido
            $this->activity_logger->log(
                $blog_id,
                $this->get_site_subscription_id($blog_id),
                $user_id,
                'user_limit_exceeded',
                "Tentativa de adicionar usuário excedeu limite de {$limits['user_limit']} usuários",
                'warning'
            );
            
            // Retornar erro
            wp_die('Limite de usuários excedido para este site.');
        }
    }
    
    /**
     * Limitar acesso a plugins e temas baseado no plano
     */
    public function limit_plugin_theme_access($allcaps, $caps, $args, $user) {
        if (!$this->is_managed_site()) {
            return $allcaps;
        }
        
        $limits = $this->get_site_limits();
        $features = isset($limits['features']) ? $limits['features'] : array();
        
        // Verificar se pode instalar plugins
        if (!in_array('plugin_install', $features) && in_array('install_plugins', $caps)) {
            $allcaps['install_plugins'] = false;
        }
        
        // Verificar se pode ativar plugins
        if (!in_array('plugin_activate', $features) && in_array('activate_plugins', $caps)) {
            $allcaps['activate_plugins'] = false;
        }
        
        // Verificar se pode trocar temas
        if (!in_array('custom_themes', $features) && in_array('switch_themes', $caps)) {
            $allcaps['switch_themes'] = false;
        }
        
        // Verificar se pode editar temas
        if (!in_array('theme_edit', $features) && in_array('edit_themes', $caps)) {
            $allcaps['edit_themes'] = false;
        }
        
        return $allcaps;
    }
    
    /**
     * Configurar dashboard de uso
     */
    public function setup_usage_dashboard() {
        if (!$this->is_managed_site()) {
            return;
        }
        
        wp_add_dashboard_widget(
            'mkp_usage_stats',
            'Uso do Plano',
            array($this, 'display_usage_dashboard_widget')
        );
    }
    
    /**
     * Exibir widget de uso no dashboard
     */
    public function display_usage_dashboard_widget() {
        $limits = $this->get_site_limits();
        $usage = $this->get_current_usage();
        
        ?>
        <div class="mkp-usage-widget">
            <style>
            .mkp-usage-item {
                margin: 10px 0;
                padding: 10px;
                background: #f9f9f9;
                border-radius: 4px;
            }
            .mkp-usage-bar {
                width: 100%;
                height: 20px;
                background: #e0e0e0;
                border-radius: 10px;
                overflow: hidden;
                margin-top: 5px;
            }
            .mkp-usage-fill {
                height: 100%;
                background: linear-gradient(to right, #00a32a, #ffb900, #d63638);
                transition: width 0.3s ease;
            }
            .mkp-usage-text {
                font-size: 12px;
                margin-top: 5px;
            }
            </style>
            
            <!-- Páginas/Posts -->
            <div class="mkp-usage-item">
                <strong>Páginas e Posts</strong>
                <?php if ($limits['page_limit'] > 0): ?>
                    <div class="mkp-usage-bar">
                        <?php $percentage = min(($usage['pages'] / $limits['page_limit']) * 100, 100); ?>
                        <div class="mkp-usage-fill" style="width: <?php echo $percentage; ?>%"></div>
                    </div>
                    <div class="mkp-usage-text">
                        <?php echo $usage['pages']; ?> de <?php echo $limits['page_limit']; ?> (<?php echo round($percentage, 1); ?>%)
                    </div>
                <?php else: ?>
                    <div class="mkp-usage-text">Ilimitado (<?php echo $usage['pages']; ?> criadas)</div>
                <?php endif; ?>
            </div>
            
            <!-- Armazenamento -->
            <div class="mkp-usage-item">
                <strong>Armazenamento</strong>
                <?php if ($limits['storage_limit'] > 0): ?>
                    <div class="mkp-usage-bar">
                        <?php $percentage = min(($usage['storage'] / ($limits['storage_limit'] * 1024 * 1024)) * 100, 100); ?>
                        <div class="mkp-usage-fill" style="width: <?php echo $percentage; ?>%"></div>
                    </div>
                    <div class="mkp-usage-text">
                        <?php echo size_format($usage['storage']); ?> de <?php echo $limits['storage_limit']; ?> MB (<?php echo round($percentage, 1); ?>%)
                    </div>
                <?php else: ?>
                    <div class="mkp-usage-text">Ilimitado (<?php echo size_format($usage['storage']); ?> usado)</div>
                <?php endif; ?>
            </div>
            
            <!-- Usuários -->
            <div class="mkp-usage-item">
                <strong>Usuários</strong>
                <?php if ($limits['user_limit'] > 0): ?>
                    <div class="mkp-usage-bar">
                        <?php $percentage = min(($usage['users'] / $limits['user_limit']) * 100, 100); ?>
                        <div class="mkp-usage-fill" style="width: <?php echo $percentage; ?>%"></div>
                    </div>
                    <div class="mkp-usage-text">
                        <?php echo $usage['users']; ?> de <?php echo $limits['user_limit']; ?> (<?php echo round($percentage, 1); ?>%)
                    </div>
                <?php else: ?>
                    <div class="mkp-usage-text">Ilimitado (<?php echo $usage['users']; ?> usuários)</div>
                <?php endif; ?>
            </div>
            
            <!-- Status da Assinatura -->
            <div class="mkp-usage-item">
                <strong>Status da Assinatura</strong>
                <?php
                $subscription_status = $this->get_subscription_status();
                $status_colors = array(
                    'active' => '#00a32a',
                    'suspended' => '#ffb900',
                    'cancelled' => '#d63638'
                );
                $color = isset($status_colors[$subscription_status]) ? $status_colors[$subscription_status] : '#666';
                ?>
                <div style="color: <?php echo $color; ?>; font-weight: bold;">
                    <?php echo ucfirst($subscription_status); ?>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Exibir avisos de limite
     */
    public function display_limit_warnings() {
        if (!$this->is_managed_site()) {
            return;
        }
        
        // Verificar se houve excesso de limite
        if (isset($_GET['mkp_limit_exceeded'])) {
            $limit_type = sanitize_text_field($_GET['mkp_limit_exceeded']);
            
            switch ($limit_type) {
                case 'pages':
                    echo '<div class="notice notice-error"><p><strong>Limite excedido:</strong> Você atingiu o limite de páginas do seu plano. A página foi salva como rascunho.</p></div>';
                    break;
                case 'storage':
                    echo '<div class="notice notice-error"><p><strong>Limite excedido:</strong> Você atingiu o limite de armazenamento do seu plano.</p></div>';
                    break;
                case 'users':
                    echo '<div class="notice notice-error"><p><strong>Limite excedido:</strong> Você atingiu o limite de usuários do seu plano.</p></div>';
                    break;
            }
        }
        
        // Verificar avisos de proximidade do limite
        $limits = $this->get_site_limits();
        $usage = $this->get_current_usage();
        
        // Aviso de páginas próximo do limite (90%)
        if ($limits['page_limit'] > 0) {
            $page_percentage = ($usage['pages'] / $limits['page_limit']) * 100;
            if ($page_percentage >= 90) {
                echo '<div class="notice notice-warning"><p><strong>Aviso:</strong> Você está próximo do limite de páginas (' . $usage['pages'] . '/' . $limits['page_limit'] . ').</p></div>';
            }
        }
        
        // Aviso de armazenamento próximo do limite (90%)
        if ($limits['storage_limit'] > 0) {
            $storage_percentage = ($usage['storage'] / ($limits['storage_limit'] * 1024 * 1024)) * 100;
            if ($storage_percentage >= 90) {
                echo '<div class="notice notice-warning"><p><strong>Aviso:</strong> Você está próximo do limite de armazenamento (' . size_format($usage['storage']) . '/' . $limits['storage_limit'] . ' MB).</p></div>';
            }
        }
    }
    
    /**
     * Verificar se é um site gerenciado pelo plugin
     */
    private function is_managed_site($blog_id = null) {
        if ($blog_id === null) {
            $blog_id = get_current_blog_id();
        }
        
        $subscription_id = get_site_meta($blog_id, '_mkp_subscription_id', true);
        return !empty($subscription_id);
    }
    
    /**
     * Obter limites do site
     */
    private function get_site_limits($blog_id = null) {
        if ($blog_id === null) {
            $blog_id = get_current_blog_id();
        }
        
        $plan_details = get_site_meta($blog_id, '_mkp_plan_details', true);
        
        $defaults = array(
            'page_limit' => 10,
            'storage_limit' => 1024, // MB
            'user_limit' => 5,
            'features' => array()
        );
        
        return wp_parse_args($plan_details, $defaults);
    }
    
    /**
     * Obter ID da assinatura do site
     */
    private function get_site_subscription_id($blog_id = null) {
        if ($blog_id === null) {
            $blog_id = get_current_blog_id();
        }
        
        return get_site_meta($blog_id, '_mkp_subscription_id', true);
    }
    
    /**
     * Contar conteúdo publicado
     */
    private function count_published_content() {
        $counts = wp_count_posts('page');
        $page_count = isset($counts->publish) ? $counts->publish : 0;
        
        $counts = wp_count_posts('post');
        $post_count = isset($counts->publish) ? $counts->publish : 0;
        
        return $page_count + $post_count;
    }
    
    /**
     * Obter uso atual de armazenamento
     */
    private function get_current_storage_usage() {
        $upload_dir = wp_upload_dir();
        $storage_used = 0;
        
        if (is_dir($upload_dir['basedir'])) {
            $storage_used = $this->get_directory_size($upload_dir['basedir']);
        }
        
        return $storage_used;
    }
    
    /**
     * Calcular tamanho de diretório
     */
    private function get_directory_size($directory) {
        $size = 0;
        
        if (is_dir($directory)) {
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory)) as $file) {
                if ($file->isFile()) {
                    $size += $file->getSize();
                }
            }
        }
        
        return $size;
    }
    
    /**
     * Obter uso atual de todos os recursos
     */
    private function get_current_usage() {
        return array(
            'pages' => $this->count_published_content(),
            'storage' => $this->get_current_storage_usage(),
            'users' => count_users()['total_users']
        );
    }
    
    /**
     * Atualizar estatísticas de uso
     */
    private function update_usage_stats() {
        $usage = $this->get_current_usage();
        update_option('_mkp_current_usage', $usage);
        update_option('_mkp_usage_last_updated', current_time('mysql'));
    }
    
    /**
     * Obter status da assinatura
     */
    private function get_subscription_status() {
        $subscription_id = $this->get_site_subscription_id();
        
        if (!$subscription_id) {
            return 'unknown';
        }
        
        if (function_exists('wcs_get_subscription')) {
            $subscription = wcs_get_subscription($subscription_id);
            if ($subscription) {
                return $subscription->get_status();
            }
        }
        
        return 'unknown';
    }
}