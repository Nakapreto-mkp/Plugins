<?php
/**
 * Template para página de pagamento personalizada
 */

if (!defined('ABSPATH')) {
    exit;
}

// Obter informações da assinatura
$site_id = get_current_blog_id();
$subscription_info = mkp_get_subscription_info($site_id);

if (!$subscription_info) {
    wp_redirect(home_url());
    exit;
}

$subscription = $subscription_info['subscription'];
$user = wp_get_current_user();

get_header();
?>

<div class="mkp-payment-page">
    <div class="container">
        <div class="payment-header">
            <h1>💳 Renovar Assinatura</h1>
            <p class="subtitle">Mantenha seu site ativo renovando sua assinatura</p>
        </div>
        
        <div class="payment-content">
            <div class="payment-info">
                <div class="site-info">
                    <h2>📊 Informações do Site</h2>
                    <div class="info-grid">
                        <div class="info-item">
                            <label>Site:</label>
                            <value><?php echo esc_html(get_bloginfo('name')); ?></value>
                        </div>
                        <div class="info-item">
                            <label>URL:</label>
                            <value><?php echo esc_html(home_url()); ?></value>
                        </div>
                        <div class="info-item">
                            <label>Proprietário:</label>
                            <value><?php echo esc_html($user->display_name); ?></value>
                        </div>
                        <div class="info-item">
                            <label>Status:</label>
                            <value class="status-<?php echo esc_attr($subscription->get_status()); ?>">
                                <?php 
                                $statuses = array(
                                    'active' => 'Ativo',
                                    'on-hold' => 'Suspenso',
                                    'cancelled' => 'Cancelado',
                                    'expired' => 'Expirado'
                                );
                                echo esc_html($statuses[$subscription->get_status()] ?? $subscription->get_status());
                                ?>
                            </value>
                        </div>
                    </div>
                </div>
                
                <div class="subscription-details">
                    <h2>📋 Detalhes da Assinatura</h2>
                    <div class="subscription-card">
                        <div class="subscription-header">
                            <h3>Assinatura #<?php echo esc_html($subscription->get_id()); ?></h3>
                            <span class="subscription-status status-<?php echo esc_attr($subscription->get_status()); ?>">
                                <?php echo esc_html($statuses[$subscription->get_status()] ?? $subscription->get_status()); ?>
                            </span>
                        </div>
                        
                        <div class="subscription-details-grid">
                            <div class="detail-item">
                                <label>💰 Valor:</label>
                                <value class="amount">R$ <?php echo esc_html($subscription->get_total()); ?></value>
                            </div>
                            
                            <?php if ($subscription->get_date('next_payment')): ?>
                                <div class="detail-item">
                                    <label>📅 Próximo Pagamento:</label>
                                    <value><?php echo esc_html(date('d/m/Y', strtotime($subscription->get_date('next_payment')))); ?></value>
                                </div>
                            <?php endif; ?>
                            
                            <div class="detail-item">
                                <label>🔄 Frequência:</label>
                                <value>
                                    <?php 
                                    $interval = $subscription->get_billing_interval();
                                    $period = $subscription->get_billing_period();
                                    echo esc_html("A cada $interval " . ($period === 'month' ? 'mês' : $period));
                                    ?>
                                </value>
                            </div>
                            
                            <div class="detail-item">
                                <label>📈 Desde:</label>
                                <value><?php echo esc_html(date('d/m/Y', strtotime($subscription->get_date('date_created')))); ?></value>
                            </div>
                        </div>
                        
                        <?php if ($subscription->needs_payment()): ?>
                            <div class="payment-due-notice">
                                <div class="notice-icon">⚠️</div>
                                <div class="notice-content">
                                    <h4>Pagamento Pendente</h4>
                                    <p>Há um pagamento pendente para esta assinatura. Seu site pode ser suspenso caso o pagamento não seja efetuado.</p>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php
                // Obter informações de uso do site
                $limiter = new MKP_Limiter_Integration(new MKP_Activity_Logger());
                $usage = $limiter->get_site_usage($site_id);
                ?>
                
                <div class="usage-info">
                    <h2>📊 Uso de Recursos</h2>
                    <div class="usage-grid">
                        <div class="usage-item">
                            <label>Páginas:</label>
                            <div class="usage-bar">
                                <div class="usage-fill" style="width: <?php echo min(100, $usage['pages']['percentage']); ?>%"></div>
                            </div>
                            <span class="usage-text"><?php echo esc_html($usage['pages']['current']); ?>/<?php echo esc_html($usage['pages']['limit']); ?> páginas</span>
                        </div>
                        
                        <div class="usage-item">
                            <label>Posts:</label>
                            <div class="usage-bar">
                                <div class="usage-fill" style="width: <?php echo min(100, $usage['posts']['percentage']); ?>%"></div>
                            </div>
                            <span class="usage-text"><?php echo esc_html($usage['posts']['current']); ?>/<?php echo esc_html($usage['posts']['limit']); ?> posts</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="payment-actions">
                <div class="payment-methods">
                    <h2>💳 Opções de Pagamento</h2>
                    
                    <?php if ($subscription->needs_payment()): ?>
                        <div class="payment-option primary">
                            <div class="option-header">
                                <h3>🚀 Pagar Agora</h3>
                                <span class="recommended">Recomendado</span>
                            </div>
                            <p>Efetue o pagamento imediatamente e reative seu site.</p>
                            <a href="<?php echo esc_url($subscription->get_checkout_payment_url()); ?>" class="btn btn-primary btn-large">
                                Pagar R$ <?php echo esc_html($subscription->get_total()); ?>
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="payment-option">
                            <div class="option-header">
                                <h3>✅ Assinatura em Dia</h3>
                            </div>
                            <p>Sua assinatura está ativa e em dia. O próximo pagamento será processado automaticamente.</p>
                        </div>
                    <?php endif; ?>
                    
                    <div class="payment-option">
                        <div class="option-header">
                            <h3>👤 Minha Conta</h3>
                        </div>
                        <p>Acesse sua conta para gerenciar assinaturas e ver histórico de pagamentos.</p>
                        <a href="<?php echo esc_url(wc_get_page_permalink('myaccount')); ?>" class="btn btn-secondary">
                            Acessar Minha Conta
                        </a>
                    </div>
                    
                    <div class="payment-option">
                        <div class="option-header">
                            <h3>📞 Suporte</h3>
                        </div>
                        <p>Precisa de ajuda? Entre em contato com nosso suporte.</p>
                        <div class="support-buttons">
                            <a href="mailto:<?php echo esc_attr(get_option('admin_email')); ?>" class="btn btn-outline">
                                📧 Email
                            </a>
                            <a href="<?php echo esc_url(home_url('/contato')); ?>" class="btn btn-outline">
                                💬 Contato
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="payment-security">
                    <h3>🔒 Pagamento Seguro</h3>
                    <div class="security-features">
                        <div class="feature">
                            <span class="icon">🛡️</span>
                            <span>SSL Certificado</span>
                        </div>
                        <div class="feature">
                            <span class="icon">💳</span>
                            <span>Cartão Protegido</span>
                        </div>
                        <div class="feature">
                            <span class="icon">🔐</span>
                            <span>Dados Criptografados</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="payment-footer">
            <div class="help-section">
                <h3>❓ Dúvidas Frequentes</h3>
                <div class="faq-grid">
                    <div class="faq-item">
                        <h4>Quando meu site será reativado?</h4>
                        <p>Seu site é reativado automaticamente assim que o pagamento é confirmado, geralmente em poucos minutos.</p>
                    </div>
                    <div class="faq-item">
                        <h4>Posso cancelar minha assinatura?</h4>
                        <p>Sim, você pode cancelar a qualquer momento através da sua conta. O site permanecerá ativo até o final do período pago.</p>
                    </div>
                    <div class="faq-item">
                        <h4>O que acontece se eu não pagar?</h4>
                        <p>O site será suspenso após alguns dias em atraso. Após 30 dias, o site pode ser arquivado.</p>
                    </div>
                    <div class="faq-item">
                        <h4>Posso alterar meu plano?</h4>
                        <p>Sim, você pode fazer upgrade ou downgrade do seu plano através da sua conta.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.mkp-payment-page {
    max-width: 1200px;
    margin: 40px auto;
    padding: 0 20px;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.payment-header {
    text-align: center;
    margin-bottom: 40px;
}

.payment-header h1 {
    font-size: 2.5em;
    margin-bottom: 10px;
    color: #2c3e50;
}

.subtitle {
    font-size: 1.2em;
    color: #7f8c8d;
    margin: 0;
}

.payment-content {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 30px;
    margin-bottom: 40px;
}

.info-grid,
.subscription-details-grid,
.usage-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin: 20px 0;
}

.info-item,
.detail-item {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
    border-left: 4px solid #3498db;
}

.info-item label,
.detail-item label {
    display: block;
    font-weight: 600;
    color: #2c3e50;
    margin-bottom: 5px;
    font-size: 14px;
}

.info-item value,
.detail-item value {
    display: block;
    font-size: 16px;
    color: #34495e;
}

.amount {
    font-size: 24px !important;
    font-weight: bold;
    color: #27ae60 !important;
}

.subscription-card {
    background: #fff;
    border: 1px solid #e1e8ed;
    border-radius: 12px;
    padding: 0;
    overflow: hidden;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.subscription-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.subscription-header h3 {
    margin: 0;
    font-size: 1.3em;
}

.subscription-status {
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
}

.status-active { background: rgba(46, 204, 113, 0.2); color: #27ae60; }
.status-on-hold { background: rgba(241, 196, 15, 0.2); color: #f39c12; }
.status-cancelled { background: rgba(231, 76, 60, 0.2); color: #e74c3c; }

.subscription-details-grid {
    padding: 20px;
}

.payment-due-notice {
    display: flex;
    align-items: center;
    background: #fff3cd;
    border: 1px solid #ffeaa7;
    border-radius: 8px;
    padding: 15px;
    margin: 20px;
}

.notice-icon {
    font-size: 24px;
    margin-right: 15px;
}

.notice-content h4 {
    margin: 0 0 5px 0;
    color: #856404;
}

.notice-content p {
    margin: 0;
    color: #856404;
}

.usage-item {
    background: #fff;
    padding: 20px;
    border-radius: 8px;
    border: 1px solid #e1e8ed;
}

.usage-bar {
    width: 100%;
    height: 10px;
    background: #ecf0f1;
    border-radius: 5px;
    overflow: hidden;
    margin: 10px 0;
}

.usage-fill {
    height: 100%;
    background: linear-gradient(90deg, #3498db, #2980b9);
    transition: width 0.3s ease;
}

.usage-text {
    font-size: 14px;
    color: #7f8c8d;
}

.payment-methods {
    background: #fff;
    border-radius: 12px;
    padding: 0;
    border: 1px solid #e1e8ed;
    overflow: hidden;
}

.payment-methods h2 {
    background: #f8f9fa;
    margin: 0;
    padding: 20px;
    border-bottom: 1px solid #e1e8ed;
}

.payment-option {
    padding: 25px;
    border-bottom: 1px solid #e1e8ed;
}

.payment-option:last-child {
    border-bottom: none;
}

.payment-option.primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}

.option-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}

.option-header h3 {
    margin: 0;
    font-size: 1.2em;
}

.recommended {
    background: rgba(255,255,255,0.2);
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
}

.btn {
    display: inline-block;
    padding: 12px 24px;
    border-radius: 6px;
    text-decoration: none;
    font-weight: 600;
    text-align: center;
    transition: all 0.3s ease;
    border: none;
    cursor: pointer;
    margin: 10px 0;
}

.btn-primary {
    background: #3498db;
    color: white;
}

.btn-primary:hover {
    background: #2980b9;
    transform: translateY(-2px);
}

.btn-large {
    padding: 15px 30px;
    font-size: 16px;
}

.btn-secondary {
    background: #95a5a6;
    color: white;
}

.btn-outline {
    background: transparent;
    border: 2px solid #bdc3c7;
    color: #2c3e50;
}

.support-buttons {
    display: flex;
    gap: 10px;
}

.payment-security {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
    margin-top: 20px;
}

.security-features {
    display: flex;
    justify-content: space-around;
    margin-top: 15px;
}

.feature {
    text-align: center;
    font-size: 14px;
}

.feature .icon {
    display: block;
    font-size: 24px;
    margin-bottom: 5px;
}

.faq-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.faq-item {
    background: #fff;
    padding: 20px;
    border-radius: 8px;
    border: 1px solid #e1e8ed;
}

.faq-item h4 {
    margin: 0 0 10px 0;
    color: #2c3e50;
}

.faq-item p {
    margin: 0;
    color: #7f8c8d;
    line-height: 1.5;
}

@media (max-width: 768px) {
    .payment-content {
        grid-template-columns: 1fr;
    }
    
    .info-grid,
    .subscription-details-grid {
        grid-template-columns: 1fr;
    }
    
    .support-buttons {
        flex-direction: column;
    }
    
    .security-features {
        flex-direction: column;
        gap: 10px;
    }
}
</style>

<?php get_footer(); ?>
