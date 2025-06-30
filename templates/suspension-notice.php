<?php
/**
 * Template customizado para página de suspensão
 */

if (!defined('ABSPATH')) {
    exit;
}

// Variáveis passadas pelo redirect handler
$payment_url = isset($payment_url) ? $payment_url : '#';
$next_payment = isset($next_payment) ? $next_payment : '';
$total = isset($total) ? $total : '';
$site_name = get_bloginfo('name');
$site_url = home_url();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Site Temporariamente Suspenso - <?php echo esc_html($site_name); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            color: #2c3e50;
        }
        
        .suspension-container {
            background: white;
            padding: 0;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.15);
            max-width: 600px;
            width: 100%;
            overflow: hidden;
            position: relative;
        }
        
        .suspension-header {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            color: white;
            padding: 40px 30px 30px;
            text-align: center;
            position: relative;
        }
        
        .suspension-header::before {
            content: '⚠️';
            font-size: 4em;
            position: absolute;
            top: -20px;
            left: 50%;
            transform: translateX(-50%);
            background: white;
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 5px 20px rgba(0,0,0,0.2);
        }
        
        .suspension-header h1 {
            font-size: 2em;
            font-weight: 700;
            margin-bottom: 10px;
            margin-top: 20px;
        }
        
        .suspension-header p {
            font-size: 1.1em;
            opacity: 0.9;
            font-weight: 300;
        }
        
        .suspension-content {
            padding: 40px 30px;
        }
        
        .site-info {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 30px;
            border-left: 4px solid #3498db;
        }
        
        .site-info h3 {
            color: #2c3e50;
            margin-bottom: 15px;
            font-size: 1.2em;
            font-weight: 600;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: auto 1fr;
            gap: 10px 20px;
            align-items: center;
        }
        
        .info-label {
            font-weight: 600;
            color: #7f8c8d;
            font-size: 14px;
        }
        
        .info-value {
            color: #2c3e50;
            word-break: break-all;
        }
        
        .payment-info {
            background: linear-gradient(135deg, #ffeaa7 0%, #fab1a0 100%);
            padding: 25px;
            border-radius: 12px;
            margin: 30px 0;
            text-align: center;
        }
        
        .payment-info h3 {
            color: #2d3436;
            margin-bottom: 15px;
            font-size: 1.3em;
            font-weight: 600;
        }
        
        .payment-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        
        .payment-detail {
            background: rgba(255,255,255,0.3);
            padding: 15px;
            border-radius: 8px;
        }
        
        .payment-detail .label {
            font-size: 12px;
            color: #2d3436;
            opacity: 0.8;
            margin-bottom: 5px;
            text-transform: uppercase;
            font-weight: 600;
        }
        
        .payment-detail .value {
            font-size: 18px;
            font-weight: 700;
            color: #2d3436;
        }
        
        .actions {
            display: flex;
            flex-direction: column;
            gap: 15px;
            margin: 30px 0;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 15px 25px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            font-size: 16px;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(231, 76, 60, 0.3);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(231, 76, 60, 0.4);
        }
        
        .btn-secondary {
            background: #ecf0f1;
            color: #2c3e50;
            border: 2px solid #bdc3c7;
        }
        
        .btn-secondary:hover {
            background: #d5dbdb;
            transform: translateY(-1px);
        }
        
        .help-section {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 12px;
            margin-top: 30px;
        }
        
        .help-section h3 {
            color: #2c3e50;
            margin-bottom: 15px;
            font-size: 1.2em;
            font-weight: 600;
        }
        
        .help-grid {
            display: grid;
            gap: 15px;
        }
        
        .help-item {
            background: white;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #3498db;
        }
        
        .help-item h4 {
            color: #2c3e50;
            margin-bottom: 8px;
            font-size: 14px;
            font-weight: 600;
        }
        
        .help-item p {
            color: #7f8c8d;
            font-size: 13px;
            line-height: 1.4;
            margin: 0;
        }
        
        .footer {
            background: #34495e;
            color: #ecf0f1;
            padding: 20px 30px;
            text-align: center;
            font-size: 13px;
            line-height: 1.5;
        }
        
        .footer a {
            color: #3498db;
            text-decoration: none;
        }
        
        .status-indicator {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 15px;
            background: rgba(231, 76, 60, 0.1);
            color: #e74c3c;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            margin: 10px 0;
        }
        
        .pulse {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        
        .countdown {
            background: #2c3e50;
            color: white;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            margin: 20px 0;
        }
        
        .countdown-text {
            font-size: 14px;
            margin-bottom: 10px;
            opacity: 0.8;
        }
        
        .countdown-timer {
            font-size: 24px;
            font-weight: 700;
            font-family: 'Courier New', monospace;
        }
        
        @media (max-width: 480px) {
            .suspension-container {
                margin: 10px;
                border-radius: 15px;
            }
            
            .suspension-header {
                padding: 30px 20px 20px;
            }
            
            .suspension-header h1 {
                font-size: 1.5em;
            }
            
            .suspension-content {
                padding: 30px 20px;
            }
            
            .payment-details {
                grid-template-columns: 1fr;
            }
            
            .actions {
                gap: 10px;
            }
            
            .btn {
                padding: 12px 20px;
                font-size: 14px;
            }
        }
    </style>
</head>
<body>
    <div class="suspension-container">
        <div class="suspension-header">
            <h1>Site Temporariamente Suspenso</h1>
            <p>Ação necessária para reativar seu site</p>
        </div>
        
        <div class="suspension-content">
            <div class="site-info">
                <h3>📊 Informações do Site</h3>
                <div class="info-grid">
                    <span class="info-label">Site:</span>
                    <span class="info-value"><?php echo esc_html($site_name); ?></span>
                    
                    <span class="info-label">URL:</span>
                    <span class="info-value"><?php echo esc_html($site_url); ?></span>
                    
                    <span class="info-label">Status:</span>
                    <span class="info-value">
                        <span class="status-indicator">
                            🔴 Suspenso por Pagamento Pendente
                        </span>
                    </span>
                </div>
            </div>
            
            <?php if ($total || $next_payment): ?>
            <div class="payment-info">
                <h3>💳 Informações do Pagamento</h3>
                <div class="payment-details">
                    <?php if ($total): ?>
                    <div class="payment-detail">
                        <div class="label">Valor em Atraso</div>
                        <div class="value">R$ <?php echo esc_html($total); ?></div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($next_payment): ?>
                    <div class="payment-detail">
                        <div class="label">Vencimento</div>
                        <div class="value"><?php echo esc_html(date('d/m/Y', strtotime($next_payment))); ?></div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="countdown" id="countdown">
                    <div class="countdown-text">Tempo para reativação automática:</div>
                    <div class="countdown-timer" id="timer">Carregando...</div>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="actions">
                <a href="<?php echo esc_url($payment_url); ?>" class="btn btn-primary pulse">
                    💳 Efetuar Pagamento Agora
                </a>
                
                <a href="<?php echo esc_url(wp_login_url($site_url)); ?>" class="btn btn-secondary">
                    🔐 Acessar Painel Administrativo
                </a>
                
                <a href="<?php echo esc_url(wc_get_page_permalink('myaccount')); ?>" class="btn btn-secondary">
                    👤 Gerenciar Minha Conta
                </a>
            </div>
            
            <div class="help-section">
                <h3>❓ Precisa de Ajuda?</h3>
                <div class="help-grid">
                    <div class="help-item">
                        <h4>Por que meu site foi suspenso?</h4>
                        <p>Seu site foi temporariamente suspenso devido a um pagamento pendente da sua assinatura. Isso é uma medida preventiva para manter o serviço funcionando.</p>
                    </div>
                    
                    <div class="help-item">
                        <h4>Quando será reativado?</h4>
                        <p>Seu site será reativado automaticamente assim que o pagamento for confirmado, geralmente em poucos minutos após a aprovação.</p>
                    </div>
                    
                    <div class="help-item">
                        <h4>Meus dados estão seguros?</h4>
                        <p>Sim! Todos os seus dados, conteúdo e configurações estão seguros e serão restaurados completamente após a reativação.</p>
                    </div>
                    
                    <div class="help-item">
                        <h4>Problemas com o pagamento?</h4>
                        <p>Se você está enfrentando dificuldades com o pagamento, entre em contato conosco através do email <a href="mailto:<?php echo esc_attr(get_option('admin_email')); ?>"><?php echo esc_html(get_option('admin_email')); ?></a></p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="footer">
            <p>
                🔒 Este é um sistema automático de gerenciamento de assinaturas.<br>
                Todos os dados são processados de forma segura e criptografada.<br>
                <strong>Suporte:</strong> <a href="mailto:<?php echo esc_attr(get_option('admin_email')); ?>"><?php echo esc_html(get_option('admin_email')); ?></a>
            </p>
        </div>
    </div>
    
    <script>
        // Contador regressivo simples
        function startCountdown() {
            const timer = document.getElementById('timer');
            const countdown = document.getElementById('countdown');
            
            if (!timer || !countdown) return;
            
            // Simular 48 horas para reativação
            let timeLeft = 48 * 60 * 60; // 48 horas em segundos
            
            function updateTimer() {
                const hours = Math.floor(timeLeft / 3600);
                const minutes = Math.floor((timeLeft % 3600) / 60);
                const seconds = timeLeft % 60;
                
                timer.textContent = `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
                
                if (timeLeft <= 0) {
                    countdown.innerHTML = '<div class="countdown-text">⚠️ Prazo expirado - Entre em contato com o suporte</div>';
                    return;
                }
                
                timeLeft--;
            }
            
            updateTimer();
            setInterval(updateTimer, 1000);
        }
        
        // Verificar status do pagamento periodicamente
        function checkPaymentStatus() {
            // Esta função seria implementada para verificar via AJAX
            // se o pagamento foi efetuado e redirecionar automaticamente
            console.log('Verificando status do pagamento...');
        }
        
        // Inicializar quando a página carregar
        document.addEventListener('DOMContentLoaded', function() {
            startCountdown();
            
            // Verificar status a cada 30 segundos
            setInterval(checkPaymentStatus, 30000);
        });
        
        // Efeito de piscar no botão principal
        setInterval(function() {
            const btn = document.querySelector('.btn-primary');
            if (btn) {
                btn.style.transform = 'scale(1.02)';
                setTimeout(() => {
                    btn.style.transform = 'scale(1)';
                }, 200);
            }
        }, 3000);
    </script>
</body>
</html>