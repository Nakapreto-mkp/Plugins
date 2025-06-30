<?php
/**
 * Template para avisos de compatibilidade
 * 
 * @package MKP_Multisite_Woo
 * @since 1.0.1
 */

if (!defined('ABSPATH')) {
    exit;
}

$compatibility_checker = MKP_Compatibility_Checker::get_instance();
$issues = $compatibility_checker->get_compatibility_issues();
$has_critical = $compatibility_checker->has_critical_errors();

if (empty($issues)) {
    return;
}
?>

<div class="mkp-compatibility-notice">
    <style>
    .mkp-compatibility-notice {
        margin: 20px 0;
    }
    .mkp-compatibility-details {
        background: #f9f9f9;
        border: 1px solid #ddd;
        border-radius: 4px;
        padding: 15px;
        margin-top: 10px;
    }
    .mkp-compatibility-item {
        margin: 10px 0;
        padding: 8px 12px;
        border-radius: 3px;
    }
    .mkp-compatibility-item.error {
        background: #ffeaea;
        border-left: 4px solid #dc3232;
    }
    .mkp-compatibility-item.warning {
        background: #fff8e5;
        border-left: 4px solid #ffb900;
    }
    .mkp-compatibility-item.info {
        background: #e5f7ff;
        border-left: 4px solid #00a0d2;
    }
    .mkp-compatibility-item.success {
        background: #eafaf1;
        border-left: 4px solid #00a32a;
    }
    .mkp-toggle-details {
        cursor: pointer;
        text-decoration: underline;
        color: #0073aa;
    }
    </style>
    
    <div class="notice notice-<?php echo $has_critical ? 'error' : 'warning'; ?>">
        <h3>MKP Multisite WooCommerce Integrator - Verificação de Compatibilidade</h3>
        
        <?php if ($has_critical): ?>
            <p><strong>⚠️ Erros críticos detectados!</strong> O plugin pode não funcionar corretamente.</p>
        <?php else: ?>
            <p><strong>ℹ️ Avisos de compatibilidade encontrados.</strong> Revise as informações abaixo.</p>
        <?php endif; ?>
        
        <p>
            <span class="mkp-toggle-details" onclick="toggleCompatibilityDetails()">
                Ver detalhes da compatibilidade
            </span>
        </p>
        
        <div id="mkp-compatibility-details" class="mkp-compatibility-details" style="display: none;">
            <h4>Status das Dependências:</h4>
            
            <?php foreach ($issues as $issue): ?>
                <div class="mkp-compatibility-item <?php echo esc_attr($issue['type']); ?>">
                    <strong><?php echo ucfirst($issue['type']); ?>:</strong>
                    <?php echo esc_html($issue['message']); ?>
                </div>
            <?php endforeach; ?>
            
            <hr>
            <h4>Informações do Sistema:</h4>
            <ul>
                <li><strong>WordPress:</strong> <?php echo get_bloginfo('version'); ?> <?php echo is_multisite() ? '(Multisite)' : '(Single Site)'; ?></li>
                <li><strong>PHP:</strong> <?php echo PHP_VERSION; ?></li>
                <li><strong>WooCommerce:</strong> <?php echo class_exists('WooCommerce') ? WC()->version : 'Não instalado'; ?></li>
                <li><strong>WooCommerce Subscriptions:</strong> <?php echo class_exists('WC_Subscriptions') ? WC_Subscriptions::$version : 'Não instalado'; ?></li>
                <li><strong>Plugin MKP:</strong> <?php echo MKP_MULTISITE_WOO_VERSION; ?></li>
            </ul>
            
            <?php if ($has_critical): ?>
                <div style="background: #ffeaea; padding: 10px; border-radius: 4px; margin-top: 15px;">
                    <h4 style="color: #dc3232;">🚨 Ação Necessária:</h4>
                    <p>Para corrigir os erros críticos:</p>
                    <ol>
                        <li>Certifique-se de que o WordPress Multisite está habilitado</li>
                        <li>Instale e ative o WooCommerce</li>
                        <li>Instale e ative o WooCommerce Subscriptions (versão 7.0.0 ou superior)</li>
                        <li>Verifique se todas as funções necessárias estão disponíveis</li>
                    </ol>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
    function toggleCompatibilityDetails() {
        var details = document.getElementById('mkp-compatibility-details');
        if (details.style.display === 'none') {
            details.style.display = 'block';
        } else {
            details.style.display = 'none';
        }
    }
    </script>
</div>
