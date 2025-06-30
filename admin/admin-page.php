<?php
/**
 * Template da página principal do admin
 */

if (!defined('ABSPATH')) {
    exit;
}

// Obter estatísticas
$subscription_manager = new MKP_Subscription_Manager(new MKP_Activity_Logger());
$stats = $subscription_manager->get_subscription_stats();

// Obter logs recentes
$activity_logger = new MKP_Activity_Logger();
$recent_logs = $activity_logger->get_recent_logs(10);
?>

<div class="wrap">
    <h1>Dashboard MKP Multisite WooCommerce</h1>
    
    <!-- Cards de Estatísticas -->
    <div class="mkp-dashboard-cards">
        <div class="mkp-card">
            <div class="mkp-card-icon">📊</div>
            <div class="mkp-card-content">
                <h3><?php echo esc_html($stats['total']); ?></h3>
                <p>Total de Assinaturas</p>
            </div>
        </div>
        
        <div class="mkp-card">
            <div class="mkp-card-icon">✅</div>
            <div class="mkp-card-content">
                <h3><?php echo esc_html($stats['active']); ?></h3>
                <p>Assinaturas Ativas</p>
            </div>
        </div>
        
        <div class="mkp-card">
            <div class="mkp-card-icon">⏸️</div>
            <div class="mkp-card-content">
                <h3><?php echo esc_html($stats['suspended']); ?></h3>
                <p>Sites Suspensos</p>
            </div>
        </div>
        
        <div class="mkp-card">
            <div class="mkp-card-icon">🌐</div>
            <div class="mkp-card-content">
                <h3><?php echo esc_html($stats['with_sites']); ?></h3>
                <p>Sites Criados</p>
            </div>
        </div>
    </div>
    
    <!-- Gráficos e Informações -->
    <div class="mkp-dashboard-row">
        <div class="mkp-dashboard-col-8">
            <div class="mkp-widget">
                <h2>Status das Assinaturas</h2>
                <canvas id="subscriptionChart" width="400" height="200"></canvas>
            </div>
        </div>
        
        <div class="mkp-dashboard-col-4">
            <div class="mkp-widget">
                <h2>Ações Rápidas</h2>
                <div class="mkp-quick-actions">
                    <a href="<?php echo network_admin_url('admin.php?page=mkp-multisite-sites'); ?>" class="button button-primary">
                        👥 Gerenciar Sites
                    </a>
                    <a href="<?php echo network_admin_url('admin.php?page=mkp-multisite-logs'); ?>" class="button">
                        📝 Ver Logs
                    </a>
                    <a href="<?php echo network_admin_url('admin.php?page=mkp-multisite-settings'); ?>" class="button">
                        ⚙️ Configurações
                    </a>
                    <button id="sync-subscriptions" class="button">
                        🔄 Sincronizar Assinaturas
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Alertas do Sistema -->
    <div class="mkp-widget">
        <h2>Alertas do Sistema</h2>
        <div id="system-alerts">
            <?php if (!is_multisite()): ?>
                <div class="notice notice-error">
                    <p><strong>Erro:</strong> WordPress Multisite não está habilitado.</p>
                </div>
            <?php endif; ?>
            
            <?php if (!class_exists('WC_Subscriptions')): ?>
                <div class="notice notice-warning">
                    <p><strong>Aviso:</strong> WooCommerce Subscriptions não está instalado.</p>
                </div>
            <?php endif; ?>
            
            <?php if (!defined('SUBDOMAIN_INSTALL') || !SUBDOMAIN_INSTALL): ?>
                <div class="notice notice-warning">
                    <p><strong>Aviso:</strong> Configuração de subdomínio não detectada no wp-config.php.</p>
                </div>
            <?php endif; ?>
            
            <?php if ($stats['suspended'] > 0): ?>
                <div class="notice notice-info">
                    <p><strong>Info:</strong> Existem <?php echo $stats['suspended']; ?> sites suspensos que podem precisar de atenção.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Logs Recentes -->
    <div class="mkp-widget">
        <h2>Atividade Recente</h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Hora</th>
                    <th>Ação</th>
                    <th>Site</th>
                    <th>Detalhes</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recent_logs)): ?>
                    <tr>
                        <td colspan="4" style="text-align: center; padding: 20px;">
                            Nenhuma atividade recente encontrada.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($recent_logs as $log): ?>
                        <tr>
                            <td><?php echo esc_html(date('H:i', strtotime($log->created_at))); ?></td>
                            <td>
                                <span class="action-badge action-<?php echo esc_attr($log->action); ?>">
                                    <?php echo esc_html(ucwords(str_replace('_', ' ', $log->action))); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($log->site_id > 0): ?>
                                    <?php $site = get_blog_details($log->site_id); ?>
                                    <?php if ($site): ?>
                                        <a href="<?php echo esc_url($site->siteurl); ?>" target="_blank">
                                            <?php echo esc_html($site->blogname); ?>
                                        </a>
                                    <?php else: ?>
                                        Site #<?php echo esc_html($log->site_id); ?>
                                    <?php endif; ?>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html($log->details); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        
        <p style="text-align: center; margin-top: 15px;">
            <a href="<?php echo network_admin_url('admin.php?page=mkp-multisite-logs'); ?>" class="button">
                Ver Todos os Logs
            </a>
        </p>
    </div>
    
    <!-- Informações do Sistema -->
    <div class="mkp-dashboard-row">
        <div class="mkp-dashboard-col-6">
            <div class="mkp-widget">
                <h2>Informações do Sistema</h2>
                <table class="mkp-info-table">
                    <tr>
                        <td><strong>Versão do Plugin:</strong></td>
                        <td><?php echo MKP_MULTISITE_WOO_VERSION; ?></td>
                    </tr>
                    <tr>
                        <td><strong>WordPress:</strong></td>
                        <td><?php echo get_bloginfo('version'); ?></td>
                    </tr>
                    <tr>
                        <td><strong>PHP:</strong></td>
                        <td><?php echo PHP_VERSION; ?></td>
                    </tr>
                    <tr>
                        <td><strong>WooCommerce:</strong></td>
                        <td><?php echo defined('WC_VERSION') ? WC_VERSION : 'Não instalado'; ?></td>
                    </tr>
                    <tr>
                        <td><strong>Total de Sites:</strong></td>
                        <td><?php echo get_blog_count(); ?></td>
                    </tr>
                </table>
            </div>
        </div>
        
        <div class="mkp-dashboard-col-6">
            <div class="mkp-widget">
                <h2>Uso de Recursos</h2>
                <div class="mkp-resource-usage">
                    <?php
                    $memory_limit = ini_get('memory_limit');
                    $memory_usage = memory_get_usage(true);
                    $memory_percentage = ($memory_usage / wp_convert_hr_to_bytes($memory_limit)) * 100;
                    ?>
                    
                    <div class="resource-item">
                        <label>Uso de Memória:</label>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?php echo min(100, $memory_percentage); ?>%"></div>
                        </div>
                        <small><?php echo size_format($memory_usage); ?> / <?php echo $memory_limit; ?></small>
                    </div>
                    
                    <?php
                    global $wpdb;
                    $table_size = $wpdb->get_var("
                        SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 1) AS 'DB Size in MB' 
                        FROM information_schema.tables 
                        WHERE table_schema='{$wpdb->dbname}'
                    ");
                    ?>
                    
                    <div class="resource-item">
                        <label>Tamanho do Banco:</label>
                        <div class="resource-value"><?php echo $table_size; ?> MB</div>
                    </div>
                    
                    <div class="resource-item">
                        <label>Última Verificação:</label>
                        <div class="resource-value"><?php echo date('d/m/Y H:i'); ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Configurar gráfico de status das assinaturas
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('subscriptionChart').getContext('2d');
    
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Ativas', 'Suspensas', 'Canceladas', 'Expiradas'],
            datasets: [{
                data: [
                    <?php echo $stats['active']; ?>,
                    <?php echo $stats['suspended']; ?>,
                    <?php echo $stats['cancelled']; ?>,
                    <?php echo $stats['expired']; ?>
                ],
                backgroundColor: [
                    '#28a745',
                    '#ffc107',
                    '#dc3545',
                    '#6c757d'
                ]
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
});
</script>
