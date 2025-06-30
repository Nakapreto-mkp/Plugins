/**
 * JavaScript para o painel administrativo MKP Multisite WooCommerce
 * 
 * @package MKP_Multisite_Woo
 * @since 1.0.0
 */

jQuery(document).ready(function($) {
    'use strict';
    
    // Inicializar componentes
    initDashboard();
    initSiteManagement();
    initBulkActions();
    initLogFilters();
    initNotifications();
    
    /**
     * Inicializar dashboard
     */
    function initDashboard() {
        // Atualizar estatísticas a cada 5 minutos
        setInterval(function() {
            updateDashboardStats();
        }, 300000);
        
        // Refresh automático de atividades
        setInterval(function() {
            updateRecentActivities();
        }, 60000);
    }
    
    /**
     * Atualizar estatísticas do dashboard
     */
    function updateDashboardStats() {
        $.ajax({
            url: mkp_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'mkp_get_dashboard_stats',
                nonce: mkp_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    updateStatsDisplay(response.data);
                }
            }
        });
    }
    
    /**
     * Atualizar exibição das estatísticas
     */
    function updateStatsDisplay(stats) {
        $('.mkp-stat-item').each(function() {
            var $item = $(this);
            var statType = $item.data('stat-type');
            
            if (stats[statType] !== undefined) {
                $item.find('.mkp-stat-number').text(stats[statType]);
            }
        });
    }
    
    /**
     * Atualizar atividades recentes
     */
    function updateRecentActivities() {
        $.ajax({
            url: mkp_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'mkp_get_recent_activities',
                nonce: mkp_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    updateActivitiesDisplay(response.data);
                }
            }
        });
    }
    
    /**
     * Inicializar gerenciamento de sites
     */
    function initSiteManagement() {
        // Busca em tempo real
        $('#mkp-site-search').on('input', function() {
            var searchTerm = $(this).val().toLowerCase();
            filterSites(searchTerm);
        });
        
        // Select all checkbox
        $('#mkp-select-all-sites').on('change', function() {
            $('.mkp-site-checkbox').prop('checked', $(this).is(':checked'));
            updateBulkActionButton();
        });
        
        // Individual checkboxes
        $(document).on('change', '.mkp-site-checkbox', function() {
            updateBulkActionButton();
            updateSelectAllState();
        });
    }
    
    /**
     * Filtrar sites por termo de busca
     */
    function filterSites(searchTerm) {
        $('.mkp-site-card').each(function() {
            var $card = $(this);
            var siteName = $card.find('h3').text().toLowerCase();
            var siteUrl = $card.find('.mkp-site-url a').text().toLowerCase();
            
            if (siteName.includes(searchTerm) || siteUrl.includes(searchTerm)) {
                $card.show();
            } else {
                $card.hide();
            }
        });
    }
    
    /**
     * Inicializar ações em massa
     */
    function initBulkActions() {
        // Botão de aplicar ação em massa
        $('.mkp-apply-bulk-action').on('click', function() {
            var action = $('#mkp-bulk-action').val();
            
            if (!action) {
                showNotification('Selecione uma ação para continuar', 'warning');
                return;
            }
            
            var selectedSites = getSelectedSites();
            
            if (selectedSites.length === 0) {
                showNotification('Selecione pelo menos um site', 'warning');
                return;
            }
            
            if (confirm(mkp_admin.messages.confirm_bulk_action)) {
                executeBulkAction(action, selectedSites);
            }
        });
    }
    
    /**
     * Obter sites selecionados
     */
    function getSelectedSites() {
        var selectedSites = [];
        
        $('.mkp-site-checkbox:checked').each(function() {
            selectedSites.push($(this).val());
        });
        
        return selectedSites;
    }
    
    /**
     * Executar ação em massa
     */
    function executeBulkAction(action, siteIds) {
        showLoading(true);
        
        $.ajax({
            url: mkp_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'mkp_bulk_action',
                bulk_action: action,
                site_ids: siteIds,
                nonce: mkp_admin.nonce
            },
            success: function(response) {
                showLoading(false);
                
                if (response.success) {
                    showNotification('Ação executada com sucesso em ' + siteIds.length + ' site(s)', 'success');
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    showNotification('Erro ao executar ação: ' + response.data.message, 'error');
                }
            },
            error: function() {
                showLoading(false);
                showNotification('Erro de comunicação com o servidor', 'error');
            }
        });
    }
    
    /**
     * Atualizar estado do botão de ação em massa
     */
    function updateBulkActionButton() {
        var selectedCount = $('.mkp-site-checkbox:checked').length;
        var $button = $('.mkp-apply-bulk-action');
        
        if (selectedCount > 0) {
            $button.prop('disabled', false).text('Aplicar (' + selectedCount + ')');
        } else {
            $button.prop('disabled', true).text('Aplicar');
        }
    }
    
    /**
     * Atualizar estado do select all
     */
    function updateSelectAllState() {
        var totalCheckboxes = $('.mkp-site-checkbox').length;
        var checkedCheckboxes = $('.mkp-site-checkbox:checked').length;
        var $selectAll = $('#mkp-select-all-sites');
        
        if (checkedCheckboxes === 0) {
            $selectAll.prop('indeterminate', false).prop('checked', false);
        } else if (checkedCheckboxes === totalCheckboxes) {
            $selectAll.prop('indeterminate', false).prop('checked', true);
        } else {
            $selectAll.prop('indeterminate', true).prop('checked', false);
        }
    }
    
    /**
     * Inicializar filtros de logs
     */
    function initLogFilters() {
        // Aplicar filtros
        $('.mkp-apply-log-filters').on('click', function() {
            applyLogFilters();
        });
        
        // Exportar logs
        $('.mkp-export-logs').on('click', function() {
            exportLogs();
        });
        
        // Limpar logs antigos
        $('.mkp-clear-old-logs').on('click', function() {
            if (confirm('Tem certeza que deseja limpar logs antigos? Esta ação não pode ser desfeita.')) {
                clearOldLogs();
            }
        });
    }
    
    /**
     * Aplicar filtros de logs
     */
    function applyLogFilters() {
        var filters = {
            severity: $('#mkp-log-severity').val(),
            date_from: $('#mkp-log-date-from').val(),
            date_to: $('#mkp-log-date-to').val()
        };
        
        var queryString = $.param(filters);
        window.location.href = window.location.pathname + '?' + queryString;
    }
    
    /**
     * Exportar logs
     */
    function exportLogs() {
        var filters = {
            action: 'mkp_export_logs',
            severity: $('#mkp-log-severity').val(),
            date_from: $('#mkp-log-date-from').val(),
            date_to: $('#mkp-log-date-to').val(),
            nonce: mkp_admin.nonce
        };
        
        var form = $('<form>', {
            method: 'POST',
            action: mkp_admin.ajax_url
        });
        
        $.each(filters, function(key, value) {
            form.append($('<input>', {
                type: 'hidden',
                name: key,
                value: value
            }));
        });
        
        form.appendTo('body').submit().remove();
    }
    
    /**
     * Limpar logs antigos
     */
    function clearOldLogs() {
        showLoading(true);
        
        $.ajax({
            url: mkp_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'mkp_clear_logs',
                days: 30,
                nonce: mkp_admin.nonce
            },
            success: function(response) {
                showLoading(false);
                
                if (response.success) {
                    showNotification(response.data.message, 'success');
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    showNotification('Erro ao limpar logs: ' + response.data.message, 'error');
                }
            },
            error: function() {
                showLoading(false);
                showNotification('Erro de comunicação com o servidor', 'error');
            }
        });
    }
    
    /**
     * Inicializar sistema de notificações
     */
    function initNotifications() {
        // Auto-hide notifications after 5 seconds
        setTimeout(function() {
            $('.mkp-notification').removeClass('show');
        }, 5000);
    }
    
    /**
     * Mostrar notificação
     */
    function showNotification(message, type) {
        type = type || 'info';
        
        var $notification = $('<div>', {
            class: 'mkp-notification ' + type,
            html: message
        });
        
        $notification.appendTo('body');
        
        setTimeout(function() {
            $notification.addClass('show');
        }, 100);
        
        setTimeout(function() {
            $notification.removeClass('show');
            setTimeout(function() {
                $notification.remove();
            }, 300);
        }, 5000);
    }
    
    /**
     * Mostrar/esconder loading
     */
    function showLoading(show) {
        if (show) {
            $('body').addClass('mkp-loading');
        } else {
            $('body').removeClass('mkp-loading');
        }
    }
    
    /**
     * Funções globais para ações de site
     */
    window.mkpSiteAction = function(action, siteId) {
        if (action === 'delete' && !confirm(mkp_admin.messages.confirm_site_delete)) {
            return;
        }
        
        showLoading(true);
        
        $.ajax({
            url: mkp_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'mkp_site_action',
                site_action: action,
                site_id: siteId,
                nonce: mkp_admin.nonce
            },
            success: function(response) {
                showLoading(false);
                
                if (response.success) {
                    showNotification(response.data.message, 'success');
                    
                    if (action === 'delete') {
                        $('[data-site-id="' + siteId + '"]').fadeOut(300, function() {
                            $(this).remove();
                        });
                    } else {
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    }
                } else {
                    showNotification('Erro: ' + response.data.message, 'error');
                }
            },
            error: function() {
                showLoading(false);
                showNotification('Erro de comunicação com o servidor', 'error');
            }
        });
    };
    
    /**
     * Sincronizar todas as assinaturas
     */
    window.mkpSyncAllSubscriptions = function() {
        if (!confirm('Tem certeza que deseja sincronizar todas as assinaturas? Isso pode demorar alguns minutos.')) {
            return;
        }
        
        showLoading(true);
        showNotification('Sincronização iniciada...', 'info');
        
        $.ajax({
            url: mkp_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'mkp_sync_all_subscriptions',
                nonce: mkp_admin.nonce
            },
            success: function(response) {
                showLoading(false);
                
                if (response.success) {
                    showNotification('Sincronização concluída com sucesso!', 'success');
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    showNotification('Erro na sincronização: ' + response.data.message, 'error');
                }
            },
            error: function() {
                showLoading(false);
                showNotification('Erro de comunicação com o servidor', 'error');
            }
        });
    };
    
    /**
     * Sincronizar assinatura específica
     */
    window.mkpSyncSubscription = function(subscriptionId) {
        showLoading(true);
        
        $.ajax({
            url: mkp_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'mkp_sync_subscription',
                subscription_id: subscriptionId,
                nonce: mkp_admin.nonce
            },
            success: function(response) {
                showLoading(false);
                
                if (response.success) {
                    showNotification('Assinatura sincronizada com sucesso!', 'success');
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    showNotification('Erro ao sincronizar: ' + response.data.message, 'error');
                }
            },
            error: function() {
                showLoading(false);
                showNotification('Erro de comunicação com o servidor', 'error');
            }
        });
    };
    
    /**
     * Executar ação em massa (wrapper para chamada externa)
     */
    window.mkpExecuteBulkAction = function() {
        $('.mkp-apply-bulk-action').trigger('click');
    };
    
    /**
     * Filtrar logs (wrapper para chamada externa)
     */
    window.mkpFilterLogs = function() {
        $('.mkp-apply-log-filters').trigger('click');
    };
    
    /**
     * Exportar logs (wrapper para chamada externa)
     */
    window.mkpExportLogs = function() {
        $('.mkp-export-logs').trigger('click');
    };
    
    /**
     * Limpar logs antigos (wrapper para chamada externa)
     */
    window.mkpClearOldLogs = function() {
        $('.mkp-clear-old-logs').trigger('click');
    };
    
    /**
     * Atualizar contadores em tempo real
     */
    function updateCounters() {
        $('.mkp-counter').each(function() {
            var $counter = $(this);
            var targetValue = parseInt($counter.data('target'));
            var currentValue = parseInt($counter.text());
            var increment = Math.ceil((targetValue - currentValue) / 10);
            
            if (currentValue < targetValue) {
                $counter.text(currentValue + increment);
            }
        });
    }
    
    // Inicializar contadores animados
    $('.mkp-counter').each(function() {
        var $counter = $(this);
        var finalValue = $counter.text();
        $counter.data('target', finalValue).text('0');
        
        var counterInterval = setInterval(function() {
            updateCounters();
            
            if (parseInt($counter.text()) >= parseInt($counter.data('target'))) {
                clearInterval(counterInterval);
                $counter.text($counter.data('target'));
            }
        }, 50);
    });
    
    /**
     * Tooltips
     */
    $('.mkp-tooltip').hover(
        function() {
            var $tooltip = $(this);
            var tooltipText = $tooltip.data('tooltip');
            
            if (tooltipText) {
                $tooltip.attr('title', tooltipText);
            }
        }
    );
    
    /**
     * Confirmação antes de sair da página se há alterações não salvas
     */
    var hasUnsavedChanges = false;
    
    $('input, select, textarea').on('change', function() {
        hasUnsavedChanges = true;
    });
    
    $('form').on('submit', function() {
        hasUnsavedChanges = false;
    });
    
    $(window).on('beforeunload', function() {
        if (hasUnsavedChanges) {
            return 'Você tem alterações não salvas. Tem certeza que deseja sair?';
        }
    });
    
    /**
     * Auto-save para formulários longos
     */
    if ($('.mkp-auto-save').length > 0) {
        setInterval(function() {
            autoSaveForm();
        }, 30000); // Auto-save a cada 30 segundos
    }
    
    function autoSaveForm() {
        var $form = $('.mkp-auto-save');
        var formData = $form.serialize();
        
        $.ajax({
            url: mkp_admin.ajax_url,
            type: 'POST',
            data: formData + '&action=mkp_auto_save&nonce=' + mkp_admin.nonce,
            success: function(response) {
                if (response.success) {
                    showNotification('Progresso salvo automaticamente', 'info');
                }
            }
        });
    }
});