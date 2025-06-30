<?php
/**
 * Gerenciador de backups para MKP Multisite WooCommerce
 * 
 * @package MKP_Multisite_Woo
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class MKP_Backup_Manager {
    
    private $activity_logger;
    private $backup_dir;
    
    public function __construct($activity_logger = null) {
        $this->activity_logger = $activity_logger;
        $this->backup_dir = WP_CONTENT_DIR . '/mkp-backups/';
        $this->init_hooks();
        $this->ensure_backup_directory();
    }
    
    /**
     * Inicializar hooks
     */
    private function init_hooks() {
        add_action('mkp_daily_backup', array($this, 'daily_backup_routine'));
        add_action('mkp_before_site_deletion', array($this, 'backup_before_deletion'), 10, 2);
        
        // Agendar backup diário se não existir
        if (!wp_next_scheduled('mkp_daily_backup')) {
            wp_schedule_event(time(), 'daily', 'mkp_daily_backup');
        }
    }
    
    /**
     * Garantir que diretório de backup existe
     */
    private function ensure_backup_directory() {
        if (!file_exists($this->backup_dir)) {
            wp_mkdir_p($this->backup_dir);
            
            // Criar arquivo .htaccess para proteger backups
            $htaccess_content = "Order deny,allow\nDeny from all\n";
            file_put_contents($this->backup_dir . '.htaccess', $htaccess_content);
        }
    }
    
    /**
     * Fazer backup completo de um site
     */
    public function backup_site($site_id, $include_files = true, $include_database = true) {
        try {
            $site_details = get_blog_details($site_id);
            
            if (!$site_details) {
                throw new Exception("Site {$site_id} não encontrado");
            }
            
            $backup_name = 'site-' . $site_id . '-' . date('Y-m-d-H-i-s');
            $backup_path = $this->backup_dir . $backup_name . '/';
            
            wp_mkdir_p($backup_path);
            
            $backup_info = array(
                'site_id' => $site_id,
                'site_name' => $site_details->blogname,
                'site_url' => $site_details->siteurl,
                'backup_date' => current_time('mysql'),
                'include_files' => $include_files,
                'include_database' => $include_database,
                'files' => array(),
                'database_file' => null
            );
            
            // Backup do banco de dados
            if ($include_database) {
                $backup_info['database_file'] = $this->backup_site_database($site_id, $backup_path);
            }
            
            // Backup dos arquivos
            if ($include_files) {
                $backup_info['files'] = $this->backup_site_files($site_id, $backup_path);
            }
            
            // Salvar informações do backup
            file_put_contents($backup_path . 'backup-info.json', wp_json_encode($backup_info, JSON_PRETTY_PRINT));
            
            // Comprimir backup
            $zip_file = $this->compress_backup($backup_path, $backup_name);
            
            // Remover pasta temporária
            $this->remove_directory($backup_path);
            
            // Log do backup
            if ($this->activity_logger) {
                $this->activity_logger->log(
                    $site_id,
                    get_site_meta($site_id, '_mkp_subscription_id', true),
                    0,
                    'backup_completed',
                    "Backup criado: {$backup_name}.zip",
                    'info'
                );
            }
            
            return $zip_file;
            
        } catch (Exception $e) {
            if ($this->activity_logger) {
                $this->activity_logger->log(
                    $site_id,
                    get_site_meta($site_id, '_mkp_subscription_id', true),
                    0,
                    'backup_failed',
                    "Falha no backup: " . $e->getMessage(),
                    'error'
                );
            }
            
            throw $e;
        }
    }
    
    /**
     * Fazer backup do banco de dados do site
     */
    private function backup_site_database($site_id, $backup_path) {
        global $wpdb;
        
        switch_to_blog($site_id);
        
        try {
            $tables = $wpdb->get_col("SHOW TABLES LIKE '{$wpdb->prefix}%'");
            $sql_file = $backup_path . 'database.sql';
            $handle = fopen($sql_file, 'w');
            
            if (!$handle) {
                throw new Exception("Não foi possível criar arquivo de backup do banco");
            }
            
            // Header do arquivo SQL
            fwrite($handle, "-- MKP Multisite Backup\n");
            fwrite($handle, "-- Site ID: {$site_id}\n");
            fwrite($handle, "-- Date: " . current_time('mysql') . "\n\n");
            fwrite($handle, "SET FOREIGN_KEY_CHECKS=0;\n\n");
            
            foreach ($tables as $table) {
                // Estrutura da tabela
                $create_table = $wpdb->get_row("SHOW CREATE TABLE `{$table}`", ARRAY_N);
                fwrite($handle, "DROP TABLE IF EXISTS `{$table}`;\n");
                fwrite($handle, $create_table[1] . ";\n\n");
                
                // Dados da tabela
                $rows = $wpdb->get_results("SELECT * FROM `{$table}`", ARRAY_A);
                
                if (!empty($rows)) {
                    foreach ($rows as $row) {
                        $values = array();
                        foreach ($row as $value) {
                            if ($value === null) {
                                $values[] = 'NULL';
                            } else {
                                $values[] = "'" . $wpdb->_escape($value) . "'";
                            }
                        }
                        
                        $columns = implode('`, `', array_keys($row));
                        $values_str = implode(', ', $values);
                        
                        fwrite($handle, "INSERT INTO `{$table}` (`{$columns}`) VALUES ({$values_str});\n");
                    }
                    fwrite($handle, "\n");
                }
            }
            
            fwrite($handle, "SET FOREIGN_KEY_CHECKS=1;\n");
            fclose($handle);
            
            restore_current_blog();
            
            return 'database.sql';
            
        } catch (Exception $e) {
            restore_current_blog();
            throw $e;
        }
    }
    
    /**
     * Fazer backup dos arquivos do site
     */
    private function backup_site_files($site_id, $backup_path) {
        switch_to_blog($site_id);
        
        try {
            $upload_dir = wp_upload_dir();
            $files_path = $backup_path . 'files/';
            wp_mkdir_p($files_path);
            
            $backed_up_files = array();
            
            // Backup dos uploads
            if (is_dir($upload_dir['basedir'])) {
                $this->copy_directory($upload_dir['basedir'], $files_path . 'uploads/');
                $backed_up_files[] = 'uploads/';
            }
            
            // Backup do tema ativo (se personalizado)
            $theme_dir = get_template_directory();
            $theme_name = basename($theme_dir);
            
            // Só fazer backup se não for um tema padrão do WordPress
            $default_themes = array('twentytwentyfour', 'twentytwentythree', 'twentytwentytwo');
            
            if (!in_array($theme_name, $default_themes)) {
                $this->copy_directory($theme_dir, $files_path . 'theme/');
                $backed_up_files[] = 'theme/';
            }
            
            // Backup de plugins específicos do site (se houver)
            $plugins_dir = WP_PLUGIN_DIR;
            $site_specific_plugins = get_option('active_plugins', array());
            
            foreach ($site_specific_plugins as $plugin) {
                $plugin_dir = dirname($plugins_dir . '/' . $plugin);
                $plugin_name = basename($plugin_dir);
                
                // Só fazer backup de plugins que não são padrão
                if (!in_array($plugin_name, array('akismet', 'hello'))) {
                    $dest_dir = $files_path . 'plugins/' . $plugin_name . '/';
                    
                    if (is_dir($plugin_dir)) {
                        $this->copy_directory($plugin_dir, $dest_dir);
                        $backed_up_files[] = 'plugins/' . $plugin_name . '/';
                    }
                }
            }
            
            restore_current_blog();
            
            return $backed_up_files;
            
        } catch (Exception $e) {
            restore_current_blog();
            throw $e;
        }
    }
    
    /**
     * Comprimir backup em arquivo ZIP
     */
    private function compress_backup($backup_path, $backup_name) {
        $zip_file = $this->backup_dir . $backup_name . '.zip';
        
        if (!class_exists('ZipArchive')) {
            throw new Exception('ZipArchive não está disponível');
        }
        
        $zip = new ZipArchive();
        
        if ($zip->open($zip_file, ZipArchive::CREATE) !== TRUE) {
            throw new Exception('Não foi possível criar arquivo ZIP');
        }
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($backup_path),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        
        foreach ($iterator as $file) {
            if (!$file->isDir()) {
                $file_path = $file->getRealPath();
                $relative_path = substr($file_path, strlen($backup_path));
                
                $zip->addFile($file_path, $relative_path);
            }
        }
        
        $zip->close();
        
        return $zip_file;
    }
    
    /**
     * Copiar diretório recursivamente
     */
    private function copy_directory($source, $dest) {
        if (!is_dir($source)) {
            return false;
        }
        
        wp_mkdir_p($dest);
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ($iterator as $item) {
            $dest_path = $dest . $iterator->getSubPathName();
            
            if ($item->isDir()) {
                wp_mkdir_p($dest_path);
            } else {
                copy($item, $dest_path);
            }
        }
        
        return true;
    }
    
    /**
     * Remover diretório recursivamente
     */
    private function remove_directory($dir) {
        if (!is_dir($dir)) {
            return false;
        }
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        
        foreach ($iterator as $item) {
            if ($item->isDir()) {
                rmdir($item);
            } else {
                unlink($item);
            }
        }
        
        rmdir($dir);
        
        return true;
    }
    
    /**
     * Rotina diária de backup
     */
    public function daily_backup_routine() {
        $sites = get_sites(array(
            'meta_key' => '_mkp_subscription_id',
            'meta_value' => '',
            'meta_compare' => '!='
        ));
        
        foreach ($sites as $site) {
            $site_status = get_site_meta($site->blog_id, '_mkp_status', true);
            
            // Só fazer backup de sites ativos
            if ($site_status === 'active' || empty($site_status)) {
                try {
                    $this->backup_site($site->blog_id, true, true);
                } catch (Exception $e) {
                    error_log('MKP Backup: Falha no backup diário do site ' . $site->blog_id . ' - ' . $e->getMessage());
                }
            }
        }
        
        // Limpar backups antigos
        $this->cleanup_old_backups();
    }
    
    /**
     * Backup antes da exclusão
     */
    public function backup_before_deletion($site_id, $subscription_id = null) {
        try {
            $backup_file = $this->backup_site($site_id, true, true);
            
            // Marcar como backup de exclusão
            $backup_info_file = str_replace('.zip', '/backup-info.json', $backup_file);
            
            if (file_exists($backup_info_file)) {
                $backup_info = json_decode(file_get_contents($backup_info_file), true);
                $backup_info['deletion_backup'] = true;
                $backup_info['deletion_date'] = current_time('mysql');
                
                file_put_contents($backup_info_file, wp_json_encode($backup_info, JSON_PRETTY_PRINT));
            }
            
            return $backup_file;
            
        } catch (Exception $e) {
            error_log('MKP Backup: Falha no backup antes da exclusão - ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Limpar backups antigos
     */
    public function cleanup_old_backups($retention_days = 30) {
        $retention_timestamp = time() - ($retention_days * 24 * 60 * 60);
        
        if (!is_dir($this->backup_dir)) {
            return;
        }
        
        $files = scandir($this->backup_dir);
        $removed_count = 0;
        
        foreach ($files as $file) {
            if ($file === '.' || $file === '..' || $file === '.htaccess') {
                continue;
            }
            
            $file_path = $this->backup_dir . $file;
            
            if (is_file($file_path) && filemtime($file_path) < $retention_timestamp) {
                unlink($file_path);
                $removed_count++;
            }
        }
        
        if ($removed_count > 0 && $this->activity_logger) {
            $this->activity_logger->log(
                0,
                0,
                0,
                'backups_cleanup',
                "Limpeza de backups: {$removed_count} arquivos removidos",
                'info'
            );
        }
    }
    
    /**
     * Listar backups disponíveis
     */
    public function list_backups($site_id = null) {
        if (!is_dir($this->backup_dir)) {
            return array();
        }
        
        $files = scandir($this->backup_dir);
        $backups = array();
        
        foreach ($files as $file) {
            if (pathinfo($file, PATHINFO_EXTENSION) === 'zip') {
                $file_path = $this->backup_dir . $file;
                $file_size = filesize($file_path);
                $file_date = filemtime($file_path);
                
                // Extrair ID do site do nome do arquivo
                preg_match('/site-(\d+)-/', $file, $matches);
                $backup_site_id = isset($matches[1]) ? intval($matches[1]) : null;
                
                // Filtrar por site se especificado
                if ($site_id && $backup_site_id !== $site_id) {
                    continue;
                }
                
                $backups[] = array(
                    'file' => $file,
                    'path' => $file_path,
                    'site_id' => $backup_site_id,
                    'size' => $file_size,
                    'size_formatted' => size_format($file_size),
                    'date' => $file_date,
                    'date_formatted' => date('d/m/Y H:i:s', $file_date)
                );
            }
        }
        
        // Ordenar por data (mais recente primeiro)
        usort($backups, function($a, $b) {
            return $b['date'] - $a['date'];
        });
        
        return $backups;
    }
    
    /**
     * Obter estatísticas de backup
     */
    public function get_backup_stats() {
        $backups = $this->list_backups();
        $total_size = 0;
        $sites_with_backup = array();
        
        foreach ($backups as $backup) {
            $total_size += $backup['size'];
            
            if ($backup['site_id']) {
                $sites_with_backup[] = $backup['site_id'];
            }
        }
        
        $sites_with_backup = array_unique($sites_with_backup);
        
        return array(
            'total_backups' => count($backups),
            'total_size' => $total_size,
            'total_size_formatted' => size_format($total_size),
            'sites_with_backup' => count($sites_with_backup),
            'oldest_backup' => !empty($backups) ? end($backups)['date_formatted'] : null,
            'newest_backup' => !empty($backups) ? $backups[0]['date_formatted'] : null
        );
    }
}