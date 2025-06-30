<?php
/**
 * Sistema de notificações por email
 * 
 * @package MKP_Multisite_Woo
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class MKP_Email_Notifications {
    
    private $activity_logger;
    
    public function __construct($activity_logger) {
        $this->activity_logger = $activity_logger;
        $this->init_hooks();
    }
    
    /**
     * Inicializar hooks
     */
    private function init_hooks() {
        add_action('mkp_send_welcome_email', array($this, 'send_welcome_email'), 10, 3);
        add_action('mkp_send_payment_reminder', array($this, 'send_payment_reminder'), 10, 2);
        add_action('mkp_send_suspension_notice', array($this, 'send_suspension_notice'), 10, 3);
        add_action('mkp_send_reactivation_notice', array($this, 'send_reactivation_notice'), 10, 2);
        
        // Configurar templates de email
        add_filter('wp_mail_content_type', array($this, 'set_html_mail_content_type'));
    }
    
    /**
     * Definir tipo de conteúdo HTML para emails
     */
    public function set_html_mail_content_type() {
        return 'text/html';
    }
    
    /**
     * Enviar email de boas-vindas
     */
    public function send_welcome_email($site_id, $user_id, $subscription_id) {
        $user = get_user_by('id', $user_id);
        $site_details = get_blog_details($site_id);
        
        if (!$user || !$site_details) {
            return false;
        }
        
        $site_url = 'https://' . $site_details->domain . $site_details->path;
        $admin_url = $site_url . 'wp-admin/';
        
        $template_data = array(
            'user_name' => $user->display_name,
            'site_name' => $site_details->blogname,
            'site_url' => $site_url,
            'admin_url' => $admin_url,
            'username' => $user->user_login,
            'support_email' => 'suporte@' . DOMAIN_CURRENT_SITE
        );
        
        $subject = $this->get_email_template('welcome', 'subject', $template_data);
        $message = $this->get_email_template('welcome', 'body', $template_data);
        
        $sent = wp_mail($user->user_email, $subject, $message, $this->get_email_headers());
        
        // Log do email enviado
        $this->activity_logger->log(
            $site_id,
            $subscription_id,
            $user_id,
            'welcome_email_sent',
            'Email de boas-vindas enviado para ' . $user->user_email,
            $sent ? 'info' : 'error'
        );
        
        return $sent;
    }
    
    /**
     * Enviar lembrete de pagamento
     */
    public function send_payment_reminder($subscription_id, $days_overdue = 0) {
        if (!function_exists('wcs_get_subscription')) {
            return false;
        }
        
        $subscription = wcs_get_subscription($subscription_id);
        
        if (!$subscription) {
            return false;
        }
        
        $user = get_user_by('id', $subscription->get_user_id());
        $site_id = $this->get_site_by_subscription($subscription_id);
        
        if (!$user || !$site_id) {
            return false;
        }
        
        $site_details = get_blog_details($site_id);
        $payment_url = $subscription->get_checkout_payment_url();
        
        $template_data = array(
            'user_name' => $user->display_name,
            'site_name' => $site_details->blogname,
            'amount_due' => $subscription->get_total(),
            'currency' => $subscription->get_currency(),
            'payment_url' => $payment_url,
            'days_overdue' => $days_overdue,
            'support_email' => 'suporte@' . DOMAIN_CURRENT_SITE
        );
        
        $subject = $this->get_email_template('payment_reminder', 'subject', $template_data);
        $message = $this->get_email_template('payment_reminder', 'body', $template_data);
        
        $sent = wp_mail($user->user_email, $subject, $message, $this->get_email_headers());
        
        // Log do email enviado
        $this->activity_logger->log(
            $site_id,
            $subscription_id,
            $user->ID,
            'payment_reminder_sent',
            "Lembrete de pagamento enviado ({$days_overdue} dias em atraso)",
            $sent ? 'info' : 'error'
        );
        
        return $sent;
    }
    
    /**
     * Enviar aviso de suspensão
     */
    public function send_suspension_notice($site_id, $subscription_id, $reason = 'payment_due') {
        $subscription = wcs_get_subscription($subscription_id);
        
        if (!$subscription) {
            return false;
        }
        
        $user = get_user_by('id', $subscription->get_user_id());
        $site_details = get_blog_details($site_id);
        
        if (!$user || !$site_details) {
            return false;
        }
        
        $template_data = array(
            'user_name' => $user->display_name,
            'site_name' => $site_details->blogname,
            'site_url' => 'https://' . $site_details->domain . $site_details->path,
            'reason' => $reason,
            'payment_url' => $subscription->get_checkout_payment_url(),
            'support_email' => 'suporte@' . DOMAIN_CURRENT_SITE
        );
        
        $subject = $this->get_email_template('suspension_notice', 'subject', $template_data);
        $message = $this->get_email_template('suspension_notice', 'body', $template_data);
        
        $sent = wp_mail($user->user_email, $subject, $message, $this->get_email_headers());
        
        // Log do email enviado
        $this->activity_logger->log(
            $site_id,
            $subscription_id,
            $user->ID,
            'suspension_notice_sent',
            "Aviso de suspensão enviado (motivo: {$reason})",
            $sent ? 'info' : 'error'
        );
        
        return $sent;
    }
    
    /**
     * Enviar aviso de reativação
     */
    public function send_reactivation_notice($site_id, $subscription_id) {
        $subscription = wcs_get_subscription($subscription_id);
        
        if (!$subscription) {
            return false;
        }
        
        $user = get_user_by('id', $subscription->get_user_id());
        $site_details = get_blog_details($site_id);
        
        if (!$user || !$site_details) {
            return false;
        }
        
        $template_data = array(
            'user_name' => $user->display_name,
            'site_name' => $site_details->blogname,
            'site_url' => 'https://' . $site_details->domain . $site_details->path,
            'admin_url' => 'https://' . $site_details->domain . $site_details->path . 'wp-admin/',
            'support_email' => 'suporte@' . DOMAIN_CURRENT_SITE
        );
        
        $subject = $this->get_email_template('reactivation_notice', 'subject', $template_data);
        $message = $this->get_email_template('reactivation_notice', 'body', $template_data);
        
        $sent = wp_mail($user->user_email, $subject, $message, $this->get_email_headers());
        
        // Log do email enviado
        $this->activity_logger->log(
            $site_id,
            $subscription_id,
            $user->ID,
            'reactivation_notice_sent',
            'Aviso de reativação enviado',
            $sent ? 'info' : 'error'
        );
        
        return $sent;
    }
    
    /**
     * Obter template de email
     */
    private function get_email_template($template_type, $part, $data) {
        $templates = $this->get_email_templates();
        
        if (!isset($templates[$template_type][$part])) {
            return '';
        }
        
        $template = $templates[$template_type][$part];
        
        // Substituir variáveis no template
        foreach ($data as $key => $value) {
            $template = str_replace('{' . $key . '}', $value, $template);
        }
        
        return $template;
    }
    
    /**
     * Definir templates de email
     */
    private function get_email_templates() {
        $templates = array(
            'welcome' => array(
                'subject' => 'Seu novo site {site_name} está pronto!',
                'body' => '
                    <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
                        <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 30px; text-align: center;">
                            <h1 style="color: white; margin: 0;">🎉 Bem-vindo!</h1>
                        </div>
                        
                        <div style="padding: 30px; background: white;">
                            <h2 style="color: #333;">Olá {user_name},</h2>
                            
                            <p style="font-size: 16px; line-height: 1.6; color: #666;">
                                Seu site foi criado com sucesso! Agora você pode começar a criar conteúdo incrível.
                            </p>
                            
                            <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;">
                                <h3 style="color: #333; margin-top: 0;">📋 Detalhes do seu site:</h3>
                                <ul style="list-style: none; padding: 0;">
                                    <li style="margin: 10px 0;"><strong>🌐 URL do site:</strong> <a href="{site_url}" style="color: #007cba;">{site_url}</a></li>
                                    <li style="margin: 10px 0;"><strong>🔐 Painel Admin:</strong> <a href="{admin_url}" style="color: #007cba;">{admin_url}</a></li>
                                    <li style="margin: 10px 0;"><strong>👤 Usuário:</strong> {username}</li>
                                </ul>
                            </div>
                            
                            <div style="text-align: center; margin: 30px 0;">
                                <a href="{site_url}" style="background: #007cba; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; display: inline-block; font-weight: bold;">
                                    🚀 Acessar Meu Site
                                </a>
                            </div>
                            
                            <div style="background: #e8f4f8; padding: 15px; border-radius: 5px; margin: 20px 0;">
                                <p style="margin: 0; color: #31708f;">
                                    <strong>💡 Dica:</strong> Comece criando algumas páginas e personalizando o visual do seu site!
                                </p>
                            </div>
                        </div>
                        
                        <div style="background: #f1f1f1; padding: 20px; text-align: center; color: #666; font-size: 14px;">
                            <p>Precisa de ajuda? Entre em contato: <a href="mailto:{support_email}" style="color: #007cba;">{support_email}</a></p>
                        </div>
                    </div>
                '
            ),
            
            'payment_reminder' => array(
                'subject' => 'Pagamento pendente - {site_name}',
                'body' => '
                    <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
                        <div style="background: #ff6b6b; padding: 30px; text-align: center;">
                            <h1 style="color: white; margin: 0;">⚠️ Pagamento Pendente</h1>
                        </div>
                        
                        <div style="padding: 30px; background: white;">
                            <h2 style="color: #333;">Olá {user_name},</h2>
                            
                            <p style="font-size: 16px; line-height: 1.6; color: #666;">
                                Sua assinatura do site <strong>{site_name}</strong> tem um pagamento pendente.
                            </p>
                            
                            <div style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 20px; border-radius: 8px; margin: 20px 0;">
                                <h3 style="color: #856404; margin-top: 0;">📋 Detalhes do pagamento:</h3>
                                <ul style="list-style: none; padding: 0;">
                                    <li style="margin: 10px 0;"><strong>💰 Valor:</strong> {currency} {amount_due}</li>
                                    <li style="margin: 10px 0;"><strong>📅 Dias em atraso:</strong> {days_overdue} dias</li>
                                    <li style="margin: 10px 0;"><strong>🌐 Site:</strong> {site_name}</li>
                                </ul>
                            </div>
                            
                            <div style="text-align: center; margin: 30px 0;">
                                <a href="{payment_url}" style="background: #28a745; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; display: inline-block; font-weight: bold;">
                                    💳 Efetuar Pagamento
                                </a>
                            </div>
                            
                            <div style="background: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; border-radius: 5px; margin: 20px 0;">
                                <p style="margin: 0; color: #721c24;">
                                    <strong>⚠️ Importante:</strong> Após 7 dias sem pagamento, seu site será suspenso temporariamente.
                                </p>
                            </div>
                        </div>
                        
                        <div style="background: #f1f1f1; padding: 20px; text-align: center; color: #666; font-size: 14px;">
                            <p>Dúvidas? Entre em contato: <a href="mailto:{support_email}" style="color: #007cba;">{support_email}</a></p>
                        </div>
                    </div>
                '
            ),
            
            'suspension_notice' => array(
                'subject' => 'Site {site_name} foi suspenso',
                'body' => '
                    <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
                        <div style="background: #dc3545; padding: 30px; text-align: center;">
                            <h1 style="color: white; margin: 0;">⏸️ Site Suspenso</h1>
                        </div>
                        
                        <div style="padding: 30px; background: white;">
                            <h2 style="color: #333;">Olá {user_name},</h2>
                            
                            <p style="font-size: 16px; line-height: 1.6; color: #666;">
                                Infelizmente, seu site <strong>{site_name}</strong> foi temporariamente suspenso.
                            </p>
                            
                            <div style="background: #f8d7da; border: 1px solid #f5c6cb; padding: 20px; border-radius: 8px; margin: 20px 0;">
                                <h3 style="color: #721c24; margin-top: 0;">📋 Informações:</h3>
                                <ul style="list-style: none; padding: 0;">
                                    <li style="margin: 10px 0;"><strong>🌐 Site:</strong> {site_name}</li>
                                    <li style="margin: 10px 0;"><strong>📍 URL:</strong> {site_url}</li>
                                    <li style="margin: 10px 0;"><strong>❓ Motivo:</strong> Pagamento em atraso</li>
                                </ul>
                            </div>
                            
                            <p style="font-size: 16px; line-height: 1.6; color: #666;">
                                Para reativar seu site, efetue o pagamento pendente clicando no botão abaixo:
                            </p>
                            
                            <div style="text-align: center; margin: 30px 0;">
                                <a href="{payment_url}" style="background: #28a745; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; display: inline-block; font-weight: bold;">
                                    💳 Reativar Site
                                </a>
                            </div>
                        </div>
                        
                        <div style="background: #f1f1f1; padding: 20px; text-align: center; color: #666; font-size: 14px;">
                            <p>Precisa de ajuda? Entre em contato: <a href="mailto:{support_email}" style="color: #007cba;">{support_email}</a></p>
                        </div>
                    </div>
                '
            ),
            
            'reactivation_notice' => array(
                'subject' => 'Site {site_name} foi reativado!',
                'body' => '
                    <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
                        <div style="background: #28a745; padding: 30px; text-align: center;">
                            <h1 style="color: white; margin: 0;">✅ Site Reativado!</h1>
                        </div>
                        
                        <div style="padding: 30px; background: white;">
                            <h2 style="color: #333;">Olá {user_name},</h2>
                            
                            <p style="font-size: 16px; line-height: 1.6; color: #666;">
                                Ótimas notícias! Seu site <strong>{site_name}</strong> foi reativado com sucesso.
                            </p>
                            
                            <div style="background: #d4edda; border: 1px solid #c3e6cb; padding: 20px; border-radius: 8px; margin: 20px 0;">
                                <h3 style="color: #155724; margin-top: 0;">🎉 Seu site está online novamente!</h3>
                                <ul style="list-style: none; padding: 0;">
                                    <li style="margin: 10px 0;"><strong>🌐 Acesse:</strong> <a href="{site_url}" style="color: #007cba;">{site_url}</a></li>
                                    <li style="margin: 10px 0;"><strong>🔐 Admin:</strong> <a href="{admin_url}" style="color: #007cba;">{admin_url}</a></li>
                                </ul>
                            </div>
                            
                            <div style="text-align: center; margin: 30px 0;">
                                <a href="{site_url}" style="background: #007cba; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; display: inline-block; font-weight: bold;">
                                    🚀 Acessar Meu Site
                                </a>
                            </div>
                            
                            <p style="font-size: 16px; line-height: 1.6; color: #666;">
                                Obrigado por manter sua assinatura em dia. Continue criando conteúdo incrível!
                            </p>
                        </div>
                        
                        <div style="background: #f1f1f1; padding: 20px; text-align: center; color: #666; font-size: 14px;">
                            <p>Dúvidas? Entre em contato: <a href="mailto:{support_email}" style="color: #007cba;">{support_email}</a></p>
                        </div>
                    </div>
                '
            )
        );
        
        // Permitir personalização via filtros
        return apply_filters('mkp_email_templates', $templates);
    }
    
    /**
     * Obter headers padrão para emails
     */
    private function get_email_headers() {
        $headers = array();
        $headers[] = 'Content-Type: text/html; charset=UTF-8';
        $headers[] = 'From: ' . get_bloginfo('name') . ' <noreply@' . DOMAIN_CURRENT_SITE . '>';
        
        return apply_filters('mkp_email_headers', $headers);
    }
    
    /**
     * Obter site por ID da assinatura
     */
    private function get_site_by_subscription($subscription_id) {
        $sites = get_sites(array(
            'meta_key' => '_mkp_subscription_id',
            'meta_value' => $subscription_id,
            'number' => 1
        ));
        
        return !empty($sites) ? $sites[0]->blog_id : false;
    }
}