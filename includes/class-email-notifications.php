<?php
/**
 * Gerenciador de notificações por email
 */

if (!defined('ABSPATH')) {
    exit;
}

class MKP_Email_Notifications {
    
    public function __construct() {
        // Configurar hooks para personalizar emails
        add_filter('wp_mail_from', array($this, 'custom_mail_from'));
        add_filter('wp_mail_from_name', array($this, 'custom_mail_from_name'));
    }
    
    /**
     * Enviar email de boas-vindas
     */
    public function send_welcome_email($user_id, $site_id) {
        $user = get_user_by('id', $user_id);
        $site_details = get_blog_details($site_id);
        
        if (!$user || !$site_details) {
            return false;
        }
        
        $subject = 'Bem-vindo! Seu novo site foi criado';
        
        $message = $this->get_email_template('welcome', array(
            'user_name' => $user->display_name,
            'site_url' => $site_details->siteurl,
            'site_name' => $site_details->blogname,
            'admin_url' => $site_details->siteurl . '/wp-admin/',
            'login_url' => wp_login_url($site_details->siteurl)
        ));
        
        return $this->send_email($user->user_email, $subject, $message);
    }
    
    /**
     * Enviar email de reativação
     */
    public function send_reactivation_email($user_id, $site_id) {
        $user = get_user_by('id', $user_id);
        $site_details = get_blog_details($site_id);
        
        if (!$user || !$site_details) {
            return false;
        }
        
        $subject = 'Seu site foi reativado!';
        
        $message = $this->get_email_template('reactivation', array(
            'user_name' => $user->display_name,
            'site_url' => $site_details->siteurl,
            'site_name' => $site_details->blogname
        ));
        
        return $this->send_email($user->user_email, $subject, $message);
    }
    
    /**
     * Enviar email de suspensão
     */
    public function send_suspension_email($user_id, $site_id) {
        $user = get_user_by('id', $user_id);
        $site_details = get_blog_details($site_id);
        
        if (!$user || !$site_details) {
            return false;
        }
        
        // Obter informações da assinatura
        $subscription_info = $this->get_subscription_info($site_id);
        
        $subject = 'Importante: Seu site foi temporariamente suspenso';
        
        $message = $this->get_email_template('suspension', array(
            'user_name' => $user->display_name,
            'site_url' => $site_details->siteurl,
            'site_name' => $site_details->blogname,
            'payment_url' => $subscription_info ? $subscription_info['payment_url'] : '',
            'amount' => $subscription_info ? $subscription_info['total'] : '',
            'due_date' => $subscription_info ? $subscription_info['next_payment'] : ''
        ));
        
        return $this->send_email($user->user_email, $subject, $message);
    }
    
    /**
     * Enviar email de cancelamento
     */
    public function send_cancellation_email($user_id, $site_id) {
        $user = get_user_by('id', $user_id);
        $site_details = get_blog_details($site_id);
        
        if (!$user || !$site_details) {
            return false;
        }
        
        $subject = 'Seu site foi arquivado';
        
        $message = $this->get_email_template('cancellation', array(
            'user_name' => $user->display_name,
            'site_url' => $site_details->siteurl,
            'site_name' => $site_details->blogname,
            'support_email' => get_option('admin_email'),
            'main_site_url' => get_site_url(1)
        ));
        
        return $this->send_email($user->user_email, $subject, $message);
    }
    
    /**
     * Enviar email de lembrete de pagamento
     */
    public function send_payment_reminder($user_id, $site_id, $days_until_due = 3) {
        $user = get_user_by('id', $user_id);
        $site_details = get_blog_details($site_id);
        
        if (!$user || !$site_details) {
            return false;
        }
        
        $subscription_info = $this->get_subscription_info($site_id);
        
        $subject = "Lembrete: Pagamento vence em $days_until_due dias";
        
        $message = $this->get_email_template('payment_reminder', array(
            'user_name' => $user->display_name,
            'site_name' => $site_details->blogname,
            'days_until_due' => $days_until_due,
            'payment_url' => $subscription_info ? $subscription_info['payment_url'] : '',
            'amount' => $subscription_info ? $subscription_info['total'] : '',
            'due_date' => $subscription_info ? $subscription_info['next_payment'] : ''
        ));
        
        return $this->send_email($user->user_email, $subject, $message);
    }
    
    /**
     * Obter template de email
     */
    private function get_email_template($template_name, $variables = array()) {
        $templates = array(
            'welcome' => '
                <h2>Bem-vindo, {{user_name}}!</h2>
                <p>Seu novo site foi criado com sucesso!</p>
                
                <h3>Detalhes do seu site:</h3>
                <ul>
                    <li><strong>Nome:</strong> {{site_name}}</li>
                    <li><strong>URL:</strong> <a href="{{site_url}}">{{site_url}}</a></li>
                    <li><strong>Painel Admin:</strong> <a href="{{admin_url}}">Acessar</a></li>
                </ul>
                
                <p>Você pode começar a personalizar seu site imediatamente!</p>
                
                <p><a href="{{admin_url}}" style="background: #0073aa; color: white; padding: 10px 20px; text-decoration: none; border-radius: 3px;">Acessar Painel</a></p>
                
                <p>Em caso de dúvidas, não hesite em entrar em contato conosco.</p>
            ',
            
            'reactivation' => '
                <h2>Ótimas notícias, {{user_name}}!</h2>
                <p>Seu site <strong>{{site_name}}</strong> foi reativado com sucesso!</p>
                
                <p>Você pode acessar seu site normalmente em: <a href="{{site_url}}">{{site_url}}</a></p>
                
                <p>Obrigado por manter sua assinatura em dia!</p>
            ',
            
            'suspension' => '
                <h2>Ação Necessária: Site Suspenso</h2>
                <p>Olá {{user_name}},</p>
                
                <p>Infelizmente, seu site <strong>{{site_name}}</strong> foi temporariamente suspenso devido a um pagamento pendente.</p>
                
                <h3>Detalhes do Pagamento:</h3>
                <ul>
                    <li><strong>Valor:</strong> R$ {{amount}}</li>
                    <li><strong>Vencimento:</strong> {{due_date}}</li>
                </ul>
                
                <p>Para reativar seu site imediatamente, por favor efetue o pagamento:</p>
                
                <p><a href="{{payment_url}}" style="background: #e74c3c; color: white; padding: 10px 20px; text-decoration: none; border-radius: 3px;">Pagar Agora</a></p>
                
                <p>Seu site será reativado automaticamente assim que o pagamento for confirmado.</p>
            ',
            
            'cancellation' => '
                <h2>Site Arquivado</h2>
                <p>Olá {{user_name}},</p>
                
                <p>Seu site <strong>{{site_name}}</strong> foi arquivado devido ao cancelamento da assinatura.</p>
                
                <p>Todos os seus dados foram preservados e podem ser restaurados caso você deseje renovar sua assinatura no futuro.</p>
                
                <p>Para renovar ou obter mais informações, visite nosso site principal: <a href="{{main_site_url}}">{{main_site_url}}</a></p>
                
                <p>Se você tiver alguma dúvida, entre em contato conosco em: {{support_email}}</p>
                
                <p>Obrigado por ter sido nosso cliente!</p>
            ',
            
            'payment_reminder' => '
                <h2>Lembrete de Pagamento</h2>
                <p>Olá {{user_name}},</p>
                
                <p>Este é um lembrete amigável de que o pagamento do seu site <strong>{{site_name}}</strong> vence em {{days_until_due}} dias.</p>
                
                <h3>Detalhes:</h3>
                <ul>
                    <li><strong>Valor:</strong> R$ {{amount}}</li>
                    <li><strong>Vencimento:</strong> {{due_date}}</li>
                </ul>
                
                <p>Para evitar a suspensão do seu site, efetue o pagamento antes do vencimento:</p>
                
                <p><a href="{{payment_url}}" style="background: #27ae60; color: white; padding: 10px 20px; text-decoration: none; border-radius: 3px;">Pagar Agora</a></p>
            '
        );
        
        if (!isset($templates[$template_name])) {
            return 'Template não encontrado.';
        }
        
        $message = $templates[$template_name];
        
        // Substituir variáveis
        foreach ($variables as $key => $value) {
            $message = str_replace('{{' . $key . '}}', $value, $message);
        }
        
        // Aplicar template HTML básico
        return $this->wrap_email_template($message);
    }
    
    /**
     * Envolver template de email em HTML
     */
    private function wrap_email_template($content) {
        return '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>MKP Multisite</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                h2 { color: #2c3e50; }
                h3 { color: #34495e; }
                ul { padding-left: 20px; }
                li { margin-bottom: 5px; }
                a { color: #3498db; }
                .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; font-size: 12px; color: #888; }
            </style>
        </head>
        <body>
            <div class="container">
                ' . $content . '
                
                <div class="footer">
                    <p>Este é um email automático do sistema MKP Multisite. Por favor, não responda a este email.</p>
                </div>
            </div>
        </body>
        </html>';
    }
    
    /**
     * Enviar email
     */
    private function send_email($to, $subject, $message) {
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $this->custom_mail_from_name() . ' <' . $this->custom_mail_from() . '>'
        );
        
        return wp_mail($to, $subject, $message, $headers);
    }
    
    /**
     * Customizar remetente
     */
    public function custom_mail_from($original_email_address) {
        return get_option('mkp_email_from', $original_email_address);
    }
    
    /**
     * Customizar nome do remetente
     */
    public function custom_mail_from_name($original_email_from) {
        return get_option('mkp_email_from_name', 'MKP Multisite');
    }
    
    /**
     * Obter informações da assinatura para email
     */
    private function get_subscription_info($site_id) {
        global $wpdb;
        
        $table = $wpdb->base_prefix . 'mkp_subdomain_config';
        $config = $wpdb->get_row($wpdb->prepare(
            "SELECT subscription_id FROM $table WHERE site_id = %d",
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
            'payment_url' => $subscription->get_checkout_payment_url(),
            'total' => $subscription->get_total(),
            'next_payment' => $subscription->get_date('next_payment') ? date('d/m/Y', strtotime($subscription->get_date('next_payment'))) : ''
        );
    }
}
