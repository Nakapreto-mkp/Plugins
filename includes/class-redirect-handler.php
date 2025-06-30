<?php
/**
 * Manipulador de redirecionamentos para sites suspensos
 */

if (!defined('ABSPATH')) {
    exit;
}

class MKP_Redirect_Handler {
    
    public function __construct() {
        // Hook será chamado pelo plugin principal
    }
    
    /**
     * Verificar acesso ao site e redirecionar se necessário
     */
    public function check_site_access() {
        // Só aplicar em subsites
        if (!is_multisite() || is_main_site()) {
            return;
        }
        
        $site_id = get_current_blog_id();
        
        // Verificar status do site
        $site_status = $this->get_site_status($site_id);
        
        // Se site está suspenso ou arquivado, verificar se deve redirecionar
        if ($site_status === 'suspended' || $site_status === 'archived') {
            $this->handle_suspended_site($site_id, $site_status);
            return;
        }
        
        // Se site está ativo, verificar se há problemas de pagamento
        if ($site_status === 'active') {
            $this->check_payment_status($site_id);
        }
    }
    
    /**
     * Manipular site suspenso
     */
    private function handle_suspended_site($site_id, $status) {
        // Permitir acesso ao admin para proprietário
        if (is_admin() && $this->is_site_owner()) {
            return;
        }
        
        // Permitir acesso a páginas específicas (pagamento, etc.)
        if ($this->is_allowed_page()) {
            return;
        }
        
        // Obter informações da assinatura
        $subscription_info = $this->get_subscription_info($site_id);
        
        if ($status === 'suspended') {
            $this->show_suspension_page($subscription_info);
        } else {
            $this->show_archived_page($subscription_info);
        }
        
        exit;
    }
    
    /**
     * Verificar status de pagamento
     */
    private function check_payment_status($site_id) {
        $subscription_info = $this->get_subscription_info($site_id);
        
        if ($subscription_info && isset($subscription_info['subscription'])) {
            $subscription = $subscription_info['subscription'];
            
            // Verificar se há pagamento pendente
            if ($subscription->needs_payment()) {
                // Mostrar aviso discreto para o proprietário
                if ($this->is_site_owner() && !is_admin()) {
                    add_action('wp_footer', array($this, 'show_payment_notice'));
                }
            }
        }
    }
    
    /**
     * Obter status do site
     */
    private function get_site_status($site_id) {
        global $wpdb;
        
        // Verificar status no WordPress
        $blog_details = get_blog_details($site_id);
        
        if ($blog_details->archived == '1') {
            return 'archived';
        }
        
        if ($blog_details->spam == '1') {
            return 'suspended';
        }
        
        // Verificar status na nossa tabela
        $table = $wpdb->base_prefix . 'mkp_subdomain_config';
        $status = $wpdb->get_var($wpdb->prepare(
            "SELECT status FROM $table WHERE site_id = %d",
            $site_id
        ));
        
        return $status ?: 'active';
    }
    
    /**
     * Obter informações da assinatura
     */
    private function get_subscription_info($site_id) {
        global $wpdb;
        
        $table = $wpdb->base_prefix . 'mkp_subdomain_config';
        $config = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE site_id = %d",
            $site_id
        ));
        
        if (!$config) {
            return false;
        }
        
        $subscription = wcs_get_subscription($config->subscription_id);
        
        if (!$subscription) {
            return false;
        }
        
        return array(
            'subscription' => $subscription,
            'config' => $config,
            'user_id' => $subscription->get_user_id(),
            'payment_url' => $subscription->get_checkout_payment_url(),
            'next_payment' => $subscription->get_date('next_payment'),
            'total' => $subscription->get_total()
        );
    }
    
    /**
     * Verificar se é proprietário do site
     */
    private function is_site_owner() {
        if (!is_user_logged_in()) {
            return false;
        }
        
        $current_user = wp_get_current_user();
        $site_id = get_current_blog_id();
        
        // Verificar se é admin do site ou super admin
        return user_can($current_user, 'manage_options') || is_super_admin($current_user->ID);
    }
    
    /**
     * Verificar se é página permitida
     */
    private function is_allowed_page() {
        $allowed_pages = array(
            'pagamento',
            'payment',
            'suspended',
            'suspenso',
            'wp-login.php',
            'wp-admin'
        );
        
        $current_url = $_SERVER['REQUEST_URI'];
        
        foreach ($allowed_pages as $page) {
            if (strpos($current_url, $page) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Mostrar página de suspensão
     */
    private function show_suspension_page($subscription_info) {
        $payment_url = $subscription_info ? $subscription_info['payment_url'] : '#';
        $next_payment = $subscription_info ? $subscription_info['next_payment'] : '';
        $total = $subscription_info ? $subscription_info['total'] : '';
        
        // Carregar template personalizado
        $template_path = MKP_MULTISITE_WOO_PLUGIN_DIR . 'templates/suspension-notice.php';
        
        if (file_exists($template_path)) {
            include $template_path;
        } else {
            $this->show_default_suspension_page($payment_url, $next_payment, $total);
        }
    }
    
    /**
     * Mostrar página padrão de suspensão
     */
    private function show_default_suspension_page($payment_url, $next_payment, $total) {
        ?>
        <!DOCTYPE html>
        <html lang="pt-BR">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Site Temporariamente Suspenso</title>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    margin: 0;
                    padding: 0;
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                }
                .container {
                    background: white;
                    padding: 40px;
                    border-radius: 10px;
                    box-shadow: 0 10px 30px rgba(0,0,0,0.2);
                    text-align: center;
                    max-width: 500px;
                    margin: 20px;
                }
                h1 {
                    color: #e74c3c;
                    margin-bottom: 20px;
                }
                p {
                    color: #555;
                    line-height: 1.6;
                    margin-bottom: 20px;
                }
                .payment-info {
                    background: #f8f9fa;
                    padding: 20px;
                    border-radius: 5px;
                    margin: 20px 0;
                }
                .btn {
                    display: inline-block;
                    background: #3498db;
                    color: white;
                    padding: 12px 30px;
                    text-decoration: none;
                    border-radius: 5px;
                    margin: 10px;
                    transition: background 0.3s;
                }
                .btn:hover {
                    background: #2980b9;
                }
                .btn-primary {
                    background: #e74c3c;
                }
                .btn-primary:hover {
                    background: #c0392b;
                }
            </style>
        </head>
        <body>
            <div class="container">
                <h1>⚠️ Site Temporariamente Suspenso</h1>
                <p>Este site está temporariamente suspenso devido a um pagamento pendente.</p>
                
                <?php if ($total): ?>
                <div class="payment-info">
                    <h3>Informações do Pagamento</h3>
                    <p><strong>Valor:</strong> R$ <?php echo esc_html($total); ?></p>
                    <?php if ($next_payment): ?>
                        <p><strong>Vencimento:</strong> <?php echo esc_html(date('d/m/Y', strtotime($next_payment))); ?></p>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <p>Para reativar seu site, por favor efetue o pagamento da assinatura.</p>
                
                <a href="<?php echo esc_url($payment_url); ?>" class="btn btn-primary">Efetuar Pagamento</a>
                <a href="<?php echo esc_url(wp_login_url()); ?>" class="btn">Fazer Login</a>
                
                <p style="margin-top: 30px; font-size: 12px; color: #888;">
                    Em caso de dúvidas, entre em contato com o suporte.
                </p>
            </div>
        </body>
        </html>
        <?php
    }
    
    /**
     * Mostrar página de arquivamento
     */
    private function show_archived_page($subscription_info) {
        ?>
        <!DOCTYPE html>
        <html lang="pt-BR">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Site Arquivado</title>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    background: linear-gradient(135deg, #ff7b7b 0%, #667eea 100%);
                    margin: 0;
                    padding: 0;
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                }
                .container {
                    background: white;
                    padding: 40px;
                    border-radius: 10px;
                    box-shadow: 0 10px 30px rgba(0,0,0,0.2);
                    text-align: center;
                    max-width: 500px;
                    margin: 20px;
                }
                h1 {
                    color: #e74c3c;
                    margin-bottom: 20px;
                }
                p {
                    color: #555;
                    line-height: 1.6;
                    margin-bottom: 20px;
                }
                .btn {
                    display: inline-block;
                    background: #3498db;
                    color: white;
                    padding: 12px 30px;
                    text-decoration: none;
                    border-radius: 5px;
                    margin: 10px;
                    transition: background 0.3s;
                }
                .btn:hover {
                    background: #2980b9;
                }
            </style>
        </head>
        <body>
            <div class="container">
                <h1>📁 Site Arquivado</h1>
                <p>Este site foi arquivado devido ao cancelamento da assinatura.</p>
                <p>Para reativar seu site, você precisará renovar sua assinatura.</p>
                
                <a href="<?php echo esc_url(home_url()); ?>" class="btn">Voltar ao Site Principal</a>
                <a href="<?php echo esc_url(wp_login_url()); ?>" class="btn">Fazer Login</a>
                
                <p style="margin-top: 30px; font-size: 12px; color: #888;">
                    Para restaurar seu site, entre em contato com o suporte.
                </p>
            </div>
        </body>
        </html>
        <?php
    }
    
    /**
     * Mostrar aviso de pagamento pendente
     */
    public function show_payment_notice() {
        ?>
        <div id="mkp-payment-notice" style="
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: #e74c3c;
            color: white;
            padding: 10px;
            text-align: center;
            z-index: 999999;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        ">
            <strong>⚠️ Pagamento Pendente:</strong> 
            Sua assinatura possui um pagamento pendente. 
            <a href="<?php echo esc_url(wc_get_page_permalink('myaccount')); ?>" style="color: #fff; text-decoration: underline;">
                Clique aqui para regularizar
            </a>
            <button onclick="document.getElementById('mkp-payment-notice').style.display='none'" style="
                background: none;
                border: none;
                color: white;
                font-size: 16px;
                margin-left: 10px;
                cursor: pointer;
            ">×</button>
        </div>
        <script>
            document.body.style.paddingTop = '50px';
        </script>
        <?php
    }
}