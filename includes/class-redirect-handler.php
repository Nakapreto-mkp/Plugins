<?php
/**
 * Gerenciador de redirecionamentos para sites suspensos
 * 
 * @package MKP_Multisite_Woo
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class MKP_Redirect_Handler {
    
    private $activity_logger;
    
    public function __construct($activity_logger) {
        $this->activity_logger = $activity_logger;
        $this->init_hooks();
    }
    
    /**
     * Inicializar hooks
     */
    private function init_hooks() {
        add_action('template_redirect', array($this, 'handle_site_redirect'), 1);
        add_action('wp_head', array($this, 'add_suspension_meta_tags'));
        add_filter('wp_title', array($this, 'modify_suspended_title'), 10, 2);
    }
    
    /**
     * Gerenciar redirecionamentos de sites
     */
    public function handle_site_redirect() {
        // Verificar se é um site gerenciado
        if (!$this->is_managed_site()) {
            return;
        }
        
        // Verificar status do site
        $site_status = get_site_meta(get_current_blog_id(), '_mkp_status', true);
        
        if ($site_status === 'suspended') {
            $this->handle_suspended_site();
        } elseif ($site_status === 'pending_removal') {
            $this->handle_pending_removal_site();
        }
    }
    
    /**
     * Gerenciar site suspenso
     */
    private function handle_suspended_site() {
        // Permitir acesso ao admin para proprietários
        if (is_admin() && $this->user_can_access_suspended_site()) {
            return;
        }
        
        // Permitir acesso a páginas específicas (ex: pagamento)
        if ($this->is_allowed_page()) {
            return;
        }
        
        // Redirecionar para página de suspensão
        $this->show_suspension_page();
    }
    
    /**
     * Gerenciar site com remoção pendente
     */
    private function handle_pending_removal_site() {
        $removal_date = get_site_meta(get_current_blog_id(), '_mkp_scheduled_removal', true);
        $days_remaining = $this->calculate_days_until_removal($removal_date);
        
        // Permitir acesso ao admin para proprietários
        if (is_admin() && $this->user_can_access_suspended_site()) {
            add_action('admin_notices', function() use ($days_remaining) {
                echo '<div class="notice notice-error"><p><strong>ATENÇÃO:</strong> Este site será removido em ' . $days_remaining . ' dias devido ao cancelamento da assinatura.</p></div>';
            });
            return;
        }
        
        // Mostrar página de remoção pendente
        $this->show_pending_removal_page($days_remaining);
    }
    
    /**
     * Verificar se usuário pode acessar site suspenso
     */
    private function user_can_access_suspended_site() {
        if (!is_user_logged_in()) {
            return false;
        }
        
        $current_user = wp_get_current_user();
        
        // Super admin sempre pode acessar
        if (is_super_admin()) {
            return true;
        }
        
        // Proprietário do site pode acessar
        $site_id = get_current_blog_id();
        $subscription_id = get_site_meta($site_id, '_mkp_subscription_id', true);
        
        if ($subscription_id && function_exists('wcs_get_subscription')) {
            $subscription = wcs_get_subscription($subscription_id);
            if ($subscription && $subscription->get_user_id() == $current_user->ID) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Verificar se é página permitida
     */
    private function is_allowed_page() {
        $allowed_paths = array(
            '/wp-admin/',
            '/wp-login.php',
            '/pagamento/',
            '/reativar/'
        );
        
        $current_path = $_SERVER['REQUEST_URI'];
        
        foreach ($allowed_paths as $path) {
            if (strpos($current_path, $path) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Exibir página de suspensão
     */
    private function show_suspension_page() {
        $site_id = get_current_blog_id();
        $subscription_id = get_site_meta($site_id, '_mkp_subscription_id', true);
        $suspension_reason = get_site_meta($site_id, '_mkp_suspension_reason', true);
        
        // Log do acesso à página de suspensão
        if ($this->activity_logger) {
            $this->activity_logger->log(
                $site_id,
                $subscription_id,
                0,
                'suspension_page_viewed',
                'Usuário acessou página de suspensão',
                'info'
            );
        }
        
        // Definir headers apropriados
        status_header(503);
        nocache_headers();
        
        // Conteúdo da página
        $this->render_suspension_page($suspension_reason, $subscription_id);
        exit;
    }
    
    /**
     * Renderizar página de suspensão
     */
    private function render_suspension_page($reason, $subscription_id) {
        ?>
        <!DOCTYPE html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo('charset'); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <meta name="robots" content="noindex, nofollow">
            <title>Site Temporariamente Suspenso</title>
            <style>
                body {
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    margin: 0;
                    padding: 20px;
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                }
                .suspension-container {
                    background: white;
                    border-radius: 10px;
                    box-shadow: 0 10px 30px rgba(0,0,0,0.2);
                    max-width: 500px;
                    width: 100%;
                    padding: 40px;
                    text-align: center;
                }
                .suspension-icon {
                    width: 80px;
                    height: 80px;
                    background: #ff6b6b;
                    border-radius: 50%;
                    margin: 0 auto 20px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-size: 40px;
                    color: white;
                }
                .suspension-title {
                    color: #333;
                    font-size: 28px;
                    margin-bottom: 15px;
                    font-weight: 600;
                }
                .suspension-message {
                    color: #666;
                    font-size: 16px;
                    line-height: 1.6;
                    margin-bottom: 30px;
                }
                .action-buttons {
                    margin-top: 30px;
                }
                .btn {
                    display: inline-block;
                    padding: 12px 24px;
                    margin: 5px;
                    border-radius: 5px;
                    text-decoration: none;
                    font-weight: 500;
                    transition: all 0.3s ease;
                }
                .btn-primary {
                    background: #4CAF50;
                    color: white;
                }
                .btn-primary:hover {
                    background: #45a049;
                    transform: translateY(-2px);
                }
                .btn-secondary {
                    background: #f1f1f1;
                    color: #333;
                }
                .btn-secondary:hover {
                    background: #e1e1e1;
                }
                .contact-info {
                    margin-top: 30px;
                    padding-top: 20px;
                    border-top: 1px solid #eee;
                    font-size: 14px;
                    color: #888;
                }
            </style>
        </head>
        <body>
            <div class="suspension-container">
                <div class="suspension-icon">⏸️</div>
                
                <h1 class="suspension-title">Site Temporariamente Suspenso</h1>
                
                <div class="suspension-message">
                    <?php
                    switch ($reason) {
                        case 'payment_due':
                            echo '<p>Este site foi suspenso devido a um pagamento pendente da assinatura.</p>';
                            echo '<p>Para reativar seu site, efetue o pagamento em atraso clicando no botão abaixo.</p>';
                            break;
                        case 'subscription_expired':
                            echo '<p>Este site foi suspenso porque a assinatura expirou.</p>';
                            echo '<p>Para reativar seu site, renove sua assinatura.</p>';
                            break;
                        case 'policy_violation':
                            echo '<p>Este site foi suspenso devido a uma violação de nossas políticas de uso.</p>';
                            echo '<p>Entre em contato conosco para mais informações.</p>';
                            break;
                        default:
                            echo '<p>Este site foi temporariamente suspenso.</p>';
                            echo '<p>Entre em contato conosco para mais informações sobre a reativação.</p>';
                            break;
                    }
                    ?>
                </div>
                
                <div class="action-buttons">
                    <?php if ($reason === 'payment_due' && $subscription_id): ?>
                        <?php
                        $payment_url = $this->get_payment_url($subscription_id);
                        if ($payment_url):
                        ?>
                            <a href="<?php echo esc_url($payment_url); ?>" class="btn btn-primary">
                                💳 Efetuar Pagamento
                            </a>
                        <?php endif; ?>
                    <?php endif; ?>
                    
                    <a href="<?php echo esc_url(wp_login_url()); ?>" class="btn btn-secondary">
                        🔐 Área do Cliente
                    </a>
                </div>
                
                <div class="contact-info">
                    <p>Precisa de ajuda?</p>
                    <p>Entre em contato: <strong>suporte@<?php echo esc_html(DOMAIN_CURRENT_SITE); ?></strong></p>
                </div>
            </div>
        </body>
        </html>
        <?php
    }
    
    /**
     * Exibir página de remoção pendente
     */
    private function show_pending_removal_page($days_remaining) {
        status_header(503);
        nocache_headers();
        
        ?>
        <!DOCTYPE html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo('charset'); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <meta name="robots" content="noindex, nofollow">
            <title>Site Será Removido</title>
            <style>
                body {
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
                    background: linear-gradient(135deg, #ff7b7b 0%, #ff4757 100%);
                    margin: 0;
                    padding: 20px;
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                }
                .removal-container {
                    background: white;
                    border-radius: 10px;
                    box-shadow: 0 10px 30px rgba(0,0,0,0.2);
                    max-width: 500px;
                    width: 100%;
                    padding: 40px;
                    text-align: center;
                }
                .removal-icon {
                    width: 80px;
                    height: 80px;
                    background: #ff4757;
                    border-radius: 50%;
                    margin: 0 auto 20px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-size: 40px;
                    color: white;
                }
                .countdown {
                    font-size: 48px;
                    font-weight: bold;
                    color: #ff4757;
                    margin: 20px 0;
                }
                .btn {
                    display: inline-block;
                    padding: 12px 24px;
                    margin: 5px;
                    border-radius: 5px;
                    text-decoration: none;
                    font-weight: 500;
                    background: #4CAF50;
                    color: white;
                    transition: all 0.3s ease;
                }
                .btn:hover {
                    background: #45a049;
                    transform: translateY(-2px);
                }
            </style>
        </head>
        <body>
            <div class="removal-container">
                <div class="removal-icon">🗑️</div>
                
                <h1>Site Será Removido</h1>
                
                <div class="countdown"><?php echo $days_remaining; ?> dias</div>
                
                <p>Este site será <strong>permanentemente removido</strong> em <?php echo $days_remaining; ?> dias devido ao cancelamento da assinatura.</p>
                
                <p>Para manter seu site, renove sua assinatura antes do prazo final.</p>
                
                <a href="<?php echo esc_url(network_home_url('/renovar-assinatura/')); ?>" class="btn">
                    🔄 Renovar Assinatura
                </a>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
    
    /**
     * Verificar se é site gerenciado
     */
    private function is_managed_site() {
        $subscription_id = get_site_meta(get_current_blog_id(), '_mkp_subscription_id', true);
        return !empty($subscription_id);
    }
    
    /**
     * Obter URL de pagamento
     */
    private function get_payment_url($subscription_id) {
        if (!function_exists('wcs_get_subscription')) {
            return false;
        }
        
        $subscription = wcs_get_subscription($subscription_id);
        
        if (!$subscription) {
            return false;
        }
        
        return $subscription->get_checkout_payment_url();
    }
    
    /**
     * Calcular dias até remoção
     */
    private function calculate_days_until_removal($removal_date) {
        if (!$removal_date) {
            return 0;
        }
        
        $removal_timestamp = strtotime($removal_date);
        $current_timestamp = current_time('timestamp');
        
        $diff = $removal_timestamp - $current_timestamp;
        $days = ceil($diff / (24 * 60 * 60));
        
        return max(0, $days);
    }
    
    /**
     * Adicionar meta tags para páginas suspensas
     */
    public function add_suspension_meta_tags() {
        $site_status = get_site_meta(get_current_blog_id(), '_mkp_status', true);
        
        if (in_array($site_status, array('suspended', 'pending_removal'))) {
            echo '<meta name="robots" content="noindex, nofollow">' . "\n";
            echo '<meta http-equiv="refresh" content="86400">' . "\n"; // Refresh após 24h
        }
    }
    
    /**
     * Modificar título para sites suspensos
     */
    public function modify_suspended_title($title, $sep) {
        $site_status = get_site_meta(get_current_blog_id(), '_mkp_status', true);
        
        if ($site_status === 'suspended') {
            return 'Site Suspenso ' . $sep . ' ' . get_bloginfo('name');
        } elseif ($site_status === 'pending_removal') {
            return 'Site Será Removido ' . $sep . ' ' . get_bloginfo('name');
        }
        
        return $title;
    }
}