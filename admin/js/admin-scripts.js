/**
 * Scripts administrativos do MKP Multisite WooCommerce Integrator
 */

jQuery(document).ready(function($) {
    
    // Inicializar componentes
    initDashboard();
    initSiteManagement();
    initBulkActions();
    initFilters();
    initCharts();
    
    /**
     * Inicializar dashboard
     */
    function initDashboard() {
        // Atualizar dados do dashboard periodicamente
        setInterval(function() {
            updateDashboardData();
        }, 300000); // 5 minutos
        
        // Botão de sincronização
        $('#sync-subscriptions').on('click', function() {
            syncSubscriptions();
        });
        
        // Exportar logs
        $('#export-logs').on('click', function() {
            exportLogs();
        });
        
        // Limpar logs antigos
        $('#clear-old-logs').on('click', function() {
            clearOldLogs();
        });
    }
    
    /**
     * Inicializar gerenciamento de sites
     */
    function initSiteManagement() {
        // Botões de ação em sites individuais
        $('.mkp-manage-site').on('click', function() {
            const siteId = $(this).data('site-id');
            const action = $(this).data('action');
            
            manageSite(siteId, action, $(this));
        });
        
        // Checkbox "selecionar todos"
        $('#cb-select-all-1').on('change', function() {
            $('input[name="site[]"]').prop('checked', this.checked);
        });
    }
    
    /**
     * Inicializar ações em lote
     */
    function initBulkActions() {
        $('.action').on('click', function() {
            const action = $('#bulk-action-selector-top').val();
            const selectedSites = $('input[name="site[]"]:checked').map(function() {
                return this.value;
            }).get();
            
            if (action === '-1') {
                alert('Por favor, selecione uma ação.');
                return;
            }
            
            if (selectedSites.length === 0) {
                alert('Por favor, selecione pelo menos um site.');
                return;
            }
            
            if (confirm(`Confirma a ação "${action}" em ${selectedSites.length} sites?`)) {
                bulkManageSites(selectedSites, action);
            }
        });
    }
    
    /**
     * Inicializar filtros
     */
    function initFilters() {
        $('#log-filter-action').on('change', function() {
            filterLogs($(this).val());
        });
    }
    
    /**
     * Inicializar gráficos
     */
    function initCharts() {
        // Chart.js será carregado via CDN no admin
        if (typeof Chart !== 'undefined') {
            loadChartJS();
        } else {
            // Carregar Chart.js dinamicamente
            $('head').append('<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>');
            
            // Aguardar carregamento
            $(document).on('load', 'script[src*="chart.js"]', function() {
                loadChartJS();
            });
        }
    }
    
    /**
     * Carregar Chart.js
     */
    function loadChartJS() {
        // O gráfico será inicializado no PHP template
        console.log('Chart.js carregado');
    }
    
    /**
     * Atualizar dados do dashboard
     */
    function updateDashboardData() {
        $.post(mkp_ajax.ajax_url, {
            action: 'mkp_get_dashboard_data',
            nonce: mkp_ajax.nonce
        }, function(response) {
            if (response.success) {
                updateDashboardCards(response.data);
            }
        });
    }
    
    /**
     * Atualizar cards do dashboard
     */
    function updateDashboardCards(data) {
        $('.mkp-card:eq(0) h3').text(data.total || 0);
        $('.mkp-card:eq(1) h3').text(data.active || 0);
        $('.mkp-card:eq(2) h3').text(data.suspended || 0);
        $('.mkp-card:eq(3) h3').text(data.with_sites || 0);
    }
    
    /**
     * Gerenciar site individual
     */
    function manageSite(siteId, action, button) {
        const originalText = button.text();
        button.text('Processando...').prop('disabled', true);
        
        $.post(mkp_ajax.ajax_url, {
            action: 'mkp_manage_site',
            site_id: siteId,
            action: action,
            nonce: mkp_ajax.nonce
        }, function(response) {
            if (response.success) {
                showNotice('success', response.data);
                
                // Atualizar interface
                if (action === 'activate') {
                    button.text('Suspender').data('action', 'suspend');
                    button.closest('tr').find('.status-badge').removeClass().addClass('status-badge status-active').text('Ativo');
                } else if (action === 'suspend') {
                    button.text('Ativar').data('action', 'activate');
                    button.closest('tr').find('.status-badge').removeClass().addClass('status-badge status-suspended').text('Suspenso');
                } else if (action === 'archive') {
                    button.closest('tr').fadeOut();
                }
                
                // Atualizar dashboard
                updateDashboardData();
            } else {
                showNotice('error', response.data || 'Erro ao processar ação.');
            }
        }).fail(function() {
            showNotice('error', 'Erro de comunicação com o servidor.');
        }).always(function() {
            button.text(originalText).prop('disabled', false);
        });
    }
    
    /**
     * Gerenciar sites em lote
     */
    function bulkManageSites(siteIds, action) {
        const progressBar = $('<div class="mkp-progress-bar"><div class="mkp-progress-fill"></div></div>');
        $('.wrap h1').after(progressBar);
        
        let processed = 0;
        const total = siteIds.length;
        
        // Processar sites um por vez para evitar sobrecarga
        function processNext() {
            if (processed >= total) {
                progressBar.remove();
                showNotice('success', `Ação "${action}" aplicada a ${total} sites.`);
                location.reload();
                return;
            }
            
            const siteId = siteIds[processed];
            const progress = ((processed + 1) / total) * 100;
            progressBar.find('.mkp-progress-fill').css('width', progress + '%');
            
            $.post(mkp_ajax.ajax_url, {
                action: 'mkp_manage_site',
                site_id: siteId,
                action: action,
                nonce: mkp_ajax.nonce
            }, function(response) {
                processed++;
                setTimeout(processNext, 500); // Pequeno delay entre requisições
            }).fail(function() {
                processed++;
                setTimeout(processNext, 500);
            });
        }
        
        processNext();
    }
    
    /**
     * Sincronizar assinaturas
     */
    function syncSubscriptions() {
        const button = $('#sync-subscriptions');
        const originalText = button.text();
        
        button.text('Sincronizando...').prop('disabled', true);
        
        $.post(mkp_ajax.ajax_url, {
            action: 'mkp_sync_subscriptions',
            nonce: mkp_ajax.nonce
        }, function(response) {
            if (response.success) {
                showNotice('success', 'Sincronização concluída com sucesso.');
                updateDashboardData();
            } else {
                showNotice('error', response.data || 'Erro durante a sincronização.');
            }
        }).fail(function() {
            showNotice('error', 'Erro de comunicação com o servidor.');
        }).always(function() {
            button.text(originalText).prop('disabled', false);
        });
    }
    
    /**
     * Exportar logs
     */
    function exportLogs() {
        const form = $('<form method="post" style="display:none;">' +
            '<input name="action" value="mkp_export_logs">' +
            '<input name="nonce" value="' + mkp_ajax.nonce + '">' +
            '</form>');
        
        $('body').append(form);
        form.submit();
        form.remove();
    }
    
    /**
     * Limpar logs antigos
     */
    function clearOldLogs() {
        if (!confirm('Confirma a exclusão de logs antigos? Esta ação não pode ser desfeita.')) {
            return;
        }
        
        $.post(mkp_ajax.ajax_url, {
            action: 'mkp_clear_old_logs',
            nonce: mkp_ajax.nonce
        }, function(response) {
            if (response.success) {
                showNotice('success', 'Logs antigos removidos com sucesso.');
                location.reload();
            } else {
                showNotice('error', response.data || 'Erro ao limpar logs.');
            }
        });
    }
    
    /**
     * Filtrar logs
     */
    function filterLogs(action) {
        const rows = $('.wp-list-table tbody tr');
        
        if (!action) {
            rows.show();
            return;
        }
        
        rows.each(function() {
            const row = $(this);
            const actionBadge = row.find('.action-badge');
            
            if (actionBadge.hasClass('action-' + action)) {
                row.show();
            } else {
                row.hide();
            }
        });
    }
    
    /**
     * Mostrar notificação
     */
    function showNotice(type, message) {
        const notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
        $('.wrap h1').after(notice);
        
        // Auto-remover após 5 segundos
        setTimeout(function() {
            notice.fadeOut(function() {
                notice.remove();
            });
        }, 5000);
        
        // Botão de fechar
        notice.find('.notice-dismiss').on('click', function() {
            notice.remove();
        });
    }
    
    /**
     * Monitorar mudanças em tempo real (WebSocket simulado com polling)
     */
    function startRealTimeMonitoring() {
        setInterval(function() {
            // Verificar se há novas atividades
            $.post(mkp_ajax.ajax_url, {
                action: 'mkp_check_new_activities',
                last_check: localStorage.getItem('mkp_last_activity_check') || 0,
                nonce: mkp_ajax.nonce
            }, function(response) {
                if (response.success && response.data.has_new) {
                    // Atualizar indicador de nova atividade
                    showNotice('info', 'Nova atividade detectada. <a href="#" onclick="location.reload()">Atualizar página</a>');
                    localStorage.setItem('mkp_last_activity_check', response.data.timestamp);
                }
            });
        }, 60000); // 1 minuto
    }
    
    // Iniciar monitoramento se estivermos no dashboard
    if ($('.mkp-dashboard-cards').length > 0) {
        startRealTimeMonitoring();
    }
    
    /**
     * Configurações avançadas
     */
    $('.mkp-advanced-settings').on('click', function() {
        $('.mkp-advanced-options').toggle();
    });
    
    /**
     * Validação de formulários
     */
    $('form').on('submit', function() {
        const form = $(this);
        const requiredFields = form.find('[required]');
        let isValid = true;
        
        requiredFields.each(function() {
            const field = $(this);
            if (!field.val().trim()) {
                field.addClass('error');
                isValid = false;
            } else {
                field.removeClass('error');
            }
        });
        
        if (!isValid) {
            showNotice('error', 'Por favor, preencha todos os campos obrigatórios.');
            return false;
        }
        
        return true;
    });
    
    /**
     * Tooltips informativos
     */
    $('[data-tooltip]').each(function() {
        const element = $(this);
        const tooltip = $('<div class="mkp-tooltip">' + element.data('tooltip') + '</div>');
        
        element.on('mouseenter', function() {
            $('body').append(tooltip);
            tooltip.fadeIn();
        }).on('mouseleave', function() {
            tooltip.remove();
        }).on('mousemove', function(e) {
            tooltip.css({
                left: e.pageX + 10,
                top: e.pageY + 10
            });
        });
    });
});