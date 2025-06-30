<?php
/**
 * Gerenciador de backups para sites WordPress Multisite
 */

if (!defined('ABSPATH')) {
    exit;
}

class MKP_Backup_Manager {
    
    private $backup_dir;
    private $activity_logger;
    
    public function __construct() {
        $this->backup_dir = $this->get_backup_directory();
        $this->activity_logger = new MKP_Activity_Logger();
        
        // Criar diretório de backup se não existir
        $this->ensure_backup_directory();
        
        // Hooks para limpeza automática
        add_action('mkp_cleanup_old_backups', array($this, 'cleanup_old_backups'));
        if (!wp_next_scheduled('mkp_cleanup_old_backups')) {
            wp_schedule_event(time(), 'weekly', 'mkp_cleanup_old_backups');
        }
    }
    
    /**
     * Criar backup completo de um site
     */
    public function create_site_backup($site_id, $reason = 'manual') {
        $start_time = microtime(true);
        
        $site_details = get_blog_details($site_id);
        if (!$site_details) {
            $this->activity_logger->log($site_id, 0, 0, 'backup_failed', 'Site não encontrado para backup');
            return false;
        }
        
        // Gerar nome único para o backup
        $backup_name = $this->generate_backup_name($site_id, $reason);
        $backup_path = $this->backup_dir . '/' . $backup_name;
        
        // Criar diretório do backup
        if (!wp_mkdir_p($backup_path)) {
            $this->activity_logger->log($site_id, 0, 0, 'backup_failed', 'Não foi possível criar diretório de backup');
            return false;
        }
        
        try {
            // Backup do banco de dados
            $db_backup = $this->backup_site_database($site_id, $backup_path);
            
            // Backup dos arquivos
            $files_backup = $this->backup_site_files($site_id, $backup_path);
            
            // Backup das configurações
            $config_backup = $this->backup_site_config($site_id, $backup_path);
            
            // Criar arquivo de manifest
            $manifest = $this->create_backup_manifest($site_id, $backup_name, $reason, $db_backup, $files_backup, $config_backup);
            file_put_contents($backup_path . '/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT));
            
            // Comprimir backup se configurado
            $compressed_file = null;
            if (get_network_option(null, 'mkp_compress_backups', true)) {
                $compressed_file = $this->compress_backup($backup_path, $backup_name);
                if ($compressed_file) {
                    // Remover diretório não comprimido
                    $this->remove_directory($backup_path);
                }
            }
            
            $execution_time = microtime(true) - $start_time;
            
            // Log de sucesso
            $this->activity_logger->log($site_id, 0, 0, 'backup_created', 
                "Backup criado com sucesso: $backup_name (Tempo: " . round($execution_time, 2) . "s)");
            
            // Registrar backup na base de dados
            $this->register_backup($site_id, $backup_name, $compressed_file ?: $backup_path, $manifest);
            
            return $compressed_file ?: $backup_path;
            
        } catch (Exception $e) {
            // Limpar backup parcial em caso de erro
            if (is_dir($backup_path)) {
                $this->remove_directory($backup_path);
            }
            
            $this->activity_logger->log($site_id, 0, 0, 'backup_failed', 'Erro durante backup: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Backup do banco de dados do site
     */
    private function backup_site_database($site_id, $backup_path) {
        global $wpdb;
        
        switch_to_blog($site_id);
        
        // Obter prefixo da tabela do site
        $table_prefix = $wpdb->get_blog_prefix($site_id);
        
        // Listar tabelas do site
        $tables = $wpdb->get_results($wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $table_prefix . '%'
        ), ARRAY_N);
        
        $sql_dump = "-- Backup do banco de dados para Site ID: $site_id\n";
        $sql_dump .= "-- Data: " . date('Y-m-d H:i:s') . "\n";
        $sql_dump .= "-- Prefixo das tabelas: $table_prefix\n\n";
        
        foreach ($tables as $table) {
            $table_name = $table[0];
            
            // Estrutura da tabela
            $create_table = $wpdb->get_row("SHOW CREATE TABLE `$table_name`", ARRAY_N);
            $sql_dump .= "\n-- Estrutura da tabela `$table_name`\n";
            $sql_dump .= "DROP TABLE IF EXISTS `$table_name`;\n";
            $sql_dump .= $create_table[1] . ";\n\n";
            
            // Dados da tabela
            $rows = $wpdb->get_results("SELECT * FROM `$table_name`", ARRAY_A);
            
            if (!empty($rows)) {
                $sql_dump .= "-- Dados da tabela `$table_name`\n";
                
                foreach ($rows as $row) {
                    $values = array();
                    foreach ($row as $value) {
                        if ($value === null) {
                            $values[] = 'NULL';
                        } else {
                            $values[] = "'" . $wpdb->_real_escape($value) . "'";
                        }
                    }
                    
                    $columns = implode('`, `', array_keys($row));
                    $values_str = implode(', ', $values);
                    
                    $sql_dump .= "INSERT INTO `$table_name` (`$columns`) VALUES ($values_str);\n";
                }
                
                $sql_dump .= "\n";
            }
        }
        
        restore_current_blog();
        
        // Salvar arquivo SQL
        $db_file = $backup_path . '/database.sql';
        $bytes_written = file_put_contents($db_file, $sql_dump);
        
        return array(
            'file' => 'database.sql',
            'size' => $bytes_written,
            'tables_count' => count($tables),
            'created_at' => date('Y-m-d H:i:s')
        );
    }
    
    /**
     * Backup dos arquivos do site
     */
    private function backup_site_files($site_id, $backup_path) {
        switch_to_blog($site_id);
        
        $upload_dir = wp_upload_dir();
        $site_upload_path = $upload_dir['basedir'];
        
        restore_current_blog();
        
        $files_backup_path = $backup_path . '/files';
        wp_mkdir_p($files_backup_path);
        
        $files_copied = 0;
        $total_size = 0;
        
        if (is_dir($site_upload_path)) {
            $files_copied = $this->copy_directory($site_upload_path, $files_backup_path . '/uploads');
            $total_size = $this->get_directory_size($files_backup_path . '/uploads');
        }
        
        // Backup de temas personalizados se existirem
        $theme_backup = $this->backup_site_theme($site_id, $files_backup_path);
        
        return array(
            'upload_files' => $files_copied,
            'total_size' => $total_size,
            'theme_backup' => $theme_backup,
            'created_at' => date('Y-m-d H:i:s')
        );
    }
    
    /**
     * Backup do tema do site se for personalizado
     */
    private function backup_site_theme($site_id, $files_backup_path) {
        switch_to_blog($site_id);
        
        $current_theme = get_option('stylesheet');
        $theme_root = get_theme_root();
        $theme_path = $theme_root . '/' . $current_theme;
        
        restore_current_blog();
        
        // Verificar se é um tema personalizado (não padrão do WordPress)
        $default_themes = array('twentytwentythree', 'twentytwentytwo', 'twentytwentyone', 'twentytwenty');
        
        if (!in_array($current_theme, $default_themes) && is_dir($theme_path)) {
            $theme_backup_path = $files_backup_path . '/theme';
            wp_mkdir_p($theme_backup_path);
            
            $files_copied = $this->copy_directory($theme_path, $theme_backup_path . '/' . $current_theme);
            
            return array(
                'theme_name' => $current_theme,
                'files_copied' => $files_copied,
                'created_at' => date('Y-m-d H:i:s')
            );
        }
        
        return null;
    }
    
    /**
     * Backup das configurações do site
     */
    private function backup_site_config($site_id, $backup_path) {
        switch_to_blog($site_id);
        
        // Obter todas as opções do site
        $options = wp_load_alloptions();
        
        // Obter configurações específicas do MKP
        global $wpdb;
        $mkp_config = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->base_prefix}mkp_subdomain_config WHERE site_id = %d",
            $site_id
        ), ARRAY_A);
        
        // Obter plugins ativos
        $active_plugins = get_option('active_plugins', array());
        
        // Obter informações do tema
        $theme_info = array(
            'stylesheet' => get_option('stylesheet'),
            'template' => get_option('template'),
            'theme_mods' => get_theme_mods()
        );
        
        // Obter configurações do WooCommerce se existirem
        $woocommerce_config = array();
        if (class_exists('WooCommerce')) {
            $woo_options = array(
                'woocommerce_store_address',
                'woocommerce_store_city',
                'woocommerce_default_country',
                'woocommerce_store_postcode',
                'woocommerce_currency',
                'woocommerce_currency_pos',
                'woocommerce_price_thousand_sep',
                'woocommerce_price_decimal_sep',
                'woocommerce_price_num_decimals'
            );
            
            foreach ($woo_options as $option) {
                $woocommerce_config[$option] = get_option($option);
            }
        }
        
        restore_current_blog();
        
        $config_data = array(
            'site_id' => $site_id,
            'site_url' => get_blog_option($site_id, 'siteurl'),
            'home_url' => get_blog_option($site_id, 'home'),
            'blogname' => get_blog_option($site_id, 'blogname'),
            'blogdescription' => get_blog_option($site_id, 'blogdescription'),
            'admin_email' => get_blog_option($site_id, 'admin_email'),
            'options' => $options,
            'mkp_config' => $mkp_config,
            'active_plugins' => $active_plugins,
            'theme_info' => $theme_info,
            'woocommerce_config' => $woocommerce_config,
            'backup_created_at' => date('Y-m-d H:i:s')
        );
        
        // Salvar configurações
        $config_file = $backup_path . '/config.json';
        $bytes_written = file_put_contents($config_file, json_encode($config_data, JSON_PRETTY_PRINT));
        
        return array(
            'file' => 'config.json',
            'size' => $bytes_written,
            'options_count' => count($options),
            'created_at' => date('Y-m-d H:i:s')
        );
    }
    
    /**
     * Restaurar backup de um site
     */
    public function restore_site_backup($backup_path, $target_site_id = null) {
        if (!is_file($backup_path) && !is_dir($backup_path)) {
            return new WP_Error('backup_not_found', 'Backup não encontrado');
        }
        
        // Se for arquivo comprimido, extrair primeiro
        if (is_file($backup_path) && pathinfo($backup_path, PATHINFO_EXTENSION) === 'zip') {
            $extracted_path = $this->extract_backup($backup_path);
            if (!$extracted_path) {
                return new WP_Error('extraction_failed', 'Falha ao extrair backup');
            }
            $backup_path = $extracted_path;
        }
        
        // Verificar manifest
        $manifest_file = $backup_path . '/manifest.json';
        if (!file_exists($manifest_file)) {
            return new WP_Error('invalid_backup', 'Backup inválido - manifest não encontrado');
        }
        
        $manifest = json_decode(file_get_contents($manifest_file), true);
        $original_site_id = $manifest['site_id'];
        
        // Usar site original se não especificado
        if ($target_site_id === null) {
            $target_site_id = $original_site_id;
        }
        
        try {
            // Restaurar banco de dados
            if (file_exists($backup_path . '/database.sql')) {
                $this->restore_database($backup_path . '/database.sql', $target_site_id, $original_site_id);
            }
            
            // Restaurar arquivos
            if (is_dir($backup_path . '/files')) {
                $this->restore_files($backup_path . '/files', $target_site_id);
            }
            
            // Restaurar configurações
            if (file_exists($backup_path . '/config.json')) {
                $this->restore_config($backup_path . '/config.json', $target_site_id);
            }
            
            $this->activity_logger->log($target_site_id, 0, 0, 'backup_restored', 
                "Backup restaurado com sucesso do site $original_site_id");
            
            return true;
            
        } catch (Exception $e) {
            $this->activity_logger->log($target_site_id, 0, 0, 'restore_failed', 
                'Erro durante restauração: ' . $e->getMessage());
            return new WP_Error('restore_failed', $e->getMessage());
        }
    }
    
    /**
     * Listar backups disponíveis
     */
    public function list_backups($site_id = null) {
        global $wpdb;
        
        $table = $wpdb->base_prefix . 'mkp_backups';
        
        // Criar tabela se não existir
        $this->create_backups_table();
        
        $where_clause = '';
        $where_values = array();
        
        if ($site_id) {
            $where_clause = 'WHERE site_id = %d';
            $where_values[] = $site_id;
        }
        
        $query = "SELECT * FROM $table $where_clause ORDER BY created_at DESC";
        
        if (!empty($where_values)) {
            $backups = $wpdb->get_results($wpdb->prepare($query, $where_values));
        } else {
            $backups = $wpdb->get_results($query);
        }
        
        // Verificar se os arquivos ainda existem
        foreach ($backups as &$backup) {
            $backup->file_exists = file_exists($backup->file_path);
            $backup->file_size_mb = $backup->file_exists ? round(filesize($backup->file_path) / 1024 / 1024, 2) : 0;
        }
        
        return $backups;
    }
    
    /**
     * Deletar backup
     */
    public function delete_backup($backup_id) {
        global $wpdb;
        
        $table = $wpdb->base_prefix . 'mkp_backups';
        
        $backup = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $backup_id
        ));
        
        if (!$backup) {
            return false;
        }
        
        // Remover arquivo
        if (file_exists($backup->file_path)) {
            if (is_dir($backup->file_path)) {
                $this->remove_directory($backup->file_path);
            } else {
                unlink($backup->file_path);
            }
        }
        
        // Remover do banco
        $deleted = $wpdb->delete($table, array('id' => $backup_id), array('%d'));
        
        if ($deleted) {
            $this->activity_logger->log($backup->site_id, 0, 0, 'backup_deleted', 
                "Backup deletado: {$backup->backup_name}");
        }
        
        return $deleted > 0;
    }
    
    /**
     * Limpeza automática de backups antigos
     */
    public function cleanup_old_backups() {
        $retention_days = get_network_option(null, 'mkp_backup_retention_days', 30);
        $max_backups_per_site = get_network_option(null, 'mkp_max_backups_per_site', 5);
        
        global $wpdb;
        $table = $wpdb->base_prefix . 'mkp_backups';
        
        // Remover backups antigos
        $old_backups = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $retention_days
        ));
        
        $deleted_count = 0;
        foreach ($old_backups as $backup) {
            if ($this->delete_backup($backup->id)) {
                $deleted_count++;
            }
        }
        
        // Manter apenas X backups por site
        $sites_with_many_backups = $wpdb->get_results($wpdb->prepare(
            "SELECT site_id, COUNT(*) as backup_count 
             FROM $table 
             GROUP BY site_id 
             HAVING backup_count > %d",
            $max_backups_per_site
        ));
        
        foreach ($sites_with_many_backups as $site) {
            $old_backups = $wpdb->get_results($wpdb->prepare(
                "SELECT id FROM $table 
                 WHERE site_id = %d 
                 ORDER BY created_at DESC 
                 LIMIT %d, 1000",
                $site->site_id,
                $max_backups_per_site
            ));
            
            foreach ($old_backups as $backup) {
                if ($this->delete_backup($backup->id)) {
                    $deleted_count++;
                }
            }
        }
        
        if ($deleted_count > 0) {
            $this->activity_logger->log(0, 0, 0, 'backups_cleaned', 
                "Limpeza automática removeu $deleted_count backups antigos");
        }
        
        return $deleted_count;
    }
    
    /**
     * Obter estatísticas de backup
     */
    public function get_backup_stats() {
        global $wpdb;
        
        $table = $wpdb->base_prefix . 'mkp_backups';
        $this->create_backups_table();
        
        $total_backups = $wpdb->get_var("SELECT COUNT(*) FROM $table");
        $total_size = 0;
        $backups_by_site = $wpdb->get_results(
            "SELECT site_id, COUNT(*) as count FROM $table GROUP BY site_id ORDER BY count DESC"
        );
        
        // Calcular tamanho total
        $backups = $wpdb->get_results("SELECT file_path FROM $table");
        foreach ($backups as $backup) {
            if (file_exists($backup->file_path)) {
                $total_size += filesize($backup->file_path);
            }
        }
        
        return array(
            'total_backups' => intval($total_backups),
            'total_size_mb' => round($total_size / 1024 / 1024, 2),
            'backups_by_site' => $backups_by_site,
            'backup_directory' => $this->backup_dir,
            'directory_exists' => is_dir($this->backup_dir),
            'directory_writable' => is_writable($this->backup_dir)
        );
    }
    
    /**
     * Utilitários privados
     */
    
    private function get_backup_directory() {
        $upload_dir = wp_upload_dir();
        $backup_dir = $upload_dir['basedir'] . '/mkp-backups';
        
        return apply_filters('mkp_backup_directory', $backup_dir);
    }
    
    private function ensure_backup_directory() {
        if (!is_dir($this->backup_dir)) {
            wp_mkdir_p($this->backup_dir);
        }
        
        // Criar arquivo .htaccess para proteger backups
        $htaccess_file = $this->backup_dir . '/.htaccess';
        if (!file_exists($htaccess_file)) {
            file_put_contents($htaccess_file, "Order deny,allow\nDeny from all");
        }
        
        // Criar arquivo index.php vazio
        $index_file = $this->backup_dir . '/index.php';
        if (!file_exists($index_file)) {
            file_put_contents($index_file, '<?php // Silence is golden');
        }
    }
    
    private function generate_backup_name($site_id, $reason) {
        $site_details = get_blog_details($site_id);
        $site_slug = sanitize_file_name($site_details->path);
        $timestamp = date('Y-m-d_H-i-s');
        
        return "site-{$site_id}_{$site_slug}_{$reason}_{$timestamp}";
    }
    
    private function create_backup_manifest($site_id, $backup_name, $reason, $db_backup, $files_backup, $config_backup) {
        return array(
            'backup_name' => $backup_name,
            'site_id' => $site_id,
            'reason' => $reason,
            'created_at' => date('Y-m-d H:i:s'),
            'wordpress_version' => get_bloginfo('version'),
            'plugin_version' => MKP_MULTISITE_WOO_VERSION,
            'database_backup' => $db_backup,
            'files_backup' => $files_backup,
            'config_backup' => $config_backup,
            'backup_format_version' => '1.0'
        );
    }
    
    private function compress_backup($backup_path, $backup_name) {
        if (!class_exists('ZipArchive')) {
            return false;
        }
        
        $zip_file = dirname($backup_path) . '/' . $backup_name . '.zip';
        $zip = new ZipArchive();
        
        if ($zip->open($zip_file, ZipArchive::CREATE) !== TRUE) {
            return false;
        }
        
        $this->add_directory_to_zip($zip, $backup_path, '');
        $zip->close();
        
        return $zip_file;
    }
    
    private function add_directory_to_zip($zip, $dir_path, $zip_path) {
        $files = scandir($dir_path);
        
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;
            
            $file_path = $dir_path . '/' . $file;
            $zip_file_path = $zip_path ? $zip_path . '/' . $file : $file;
            
            if (is_dir($file_path)) {
                $zip->addEmptyDir($zip_file_path);
                $this->add_directory_to_zip($zip, $file_path, $zip_file_path);
            } else {
                $zip->addFile($file_path, $zip_file_path);
            }
        }
    }
    
    private function copy_directory($src, $dst) {
        if (!is_dir($src)) {
            return 0;
        }
        
        wp_mkdir_p($dst);
        $files_copied = 0;
        
        $dir = opendir($src);
        while (($file = readdir($dir)) !== false) {
            if ($file === '.' || $file === '..') continue;
            
            $src_path = $src . '/' . $file;
            $dst_path = $dst . '/' . $file;
            
            if (is_dir($src_path)) {
                $files_copied += $this->copy_directory($src_path, $dst_path);
            } else {
                if (copy($src_path, $dst_path)) {
                    $files_copied++;
                }
            }
        }
        closedir($dir);
        
        return $files_copied;
    }
    
    private function remove_directory($dir) {
        if (!is_dir($dir)) {
            return false;
        }
        
        $files = array_diff(scandir($dir), array('.', '..'));
        
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->remove_directory($path) : unlink($path);
        }
        
        return rmdir($dir);
    }
    
    private function get_directory_size($dir) {
        $size = 0;
        
        if (is_dir($dir)) {
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir)) as $file) {
                $size += $file->getSize();
            }
        }
        
        return $size;
    }
    
    private function register_backup($site_id, $backup_name, $file_path, $manifest) {
        global $wpdb;
        
        $table = $wpdb->base_prefix . 'mkp_backups';
        $this->create_backups_table();
        
        $wpdb->insert(
            $table,
            array(
                'site_id' => $site_id,
                'backup_name' => $backup_name,
                'file_path' => $file_path,
                'file_size' => file_exists($file_path) ? filesize($file_path) : 0,
                'manifest' => json_encode($manifest),
                'created_at' => current_time('mysql')
            ),
            array('%d', '%s', '%s', '%d', '%s', '%s')
        );
    }
    
    private function create_backups_table() {
        global $wpdb;
        
        $table = $wpdb->base_prefix . 'mkp_backups';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            site_id bigint(20) NOT NULL,
            backup_name varchar(255) NOT NULL,
            file_path text NOT NULL,
            file_size bigint(20) DEFAULT 0,
            manifest longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY site_id (site_id),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    private function restore_database($sql_file, $target_site_id, $original_site_id) {
        global $wpdb;
        
        $sql_content = file_get_contents($sql_file);
        
        // Substituir prefixos de tabela se necessário
        if ($target_site_id !== $original_site_id) {
            $original_prefix = $wpdb->get_blog_prefix($original_site_id);
            $target_prefix = $wpdb->get_blog_prefix($target_site_id);
            
            $sql_content = str_replace($original_prefix, $target_prefix, $sql_content);
        }
        
        // Executar SQL em partes
        $statements = explode(';', $sql_content);
        
        foreach ($statements as $statement) {
            $statement = trim($statement);
            if (!empty($statement) && !preg_match('/^--/', $statement)) {
                $wpdb->query($statement);
            }
        }
    }
    
    private function restore_files($files_path, $target_site_id) {
        switch_to_blog($target_site_id);
        
        $upload_dir = wp_upload_dir();
        $target_upload_path = $upload_dir['basedir'];
        
        restore_current_blog();
        
        // Restaurar uploads
        if (is_dir($files_path . '/uploads')) {
            $this->copy_directory($files_path . '/uploads', $target_upload_path);
        }
        
        // Restaurar tema se existir
        if (is_dir($files_path . '/theme')) {
            $theme_root = get_theme_root();
            $this->copy_directory($files_path . '/theme', $theme_root);
        }
    }
    
    private function restore_config($config_file, $target_site_id) {
        $config_data = json_decode(file_get_contents($config_file), true);
        
        if (!$config_data) {
            return;
        }
        
        switch_to_blog($target_site_id);
        
        // Restaurar opções básicas (excluindo algumas sensíveis)
        $skip_options = array('siteurl', 'home', 'upload_path', 'upload_url_path');
        
        foreach ($config_data['options'] as $option_name => $option_value) {
            if (!in_array($option_name, $skip_options)) {
                update_option($option_name, $option_value);
            }
        }
        
        // Restaurar configurações do tema
        if (isset($config_data['theme_info'])) {
            switch_theme($config_data['theme_info']['stylesheet']);
            
            if (isset($config_data['theme_info']['theme_mods'])) {
                foreach ($config_data['theme_info']['theme_mods'] as $mod_name => $mod_value) {
                    set_theme_mod($mod_name, $mod_value);
                }
            }
        }
        
        restore_current_blog();
    }
    
    private function extract_backup($zip_file) {
        if (!class_exists('ZipArchive')) {
            return false;
        }
        
        $zip = new ZipArchive();
        if ($zip->open($zip_file) !== TRUE) {
            return false;
        }
        
        $extract_path = dirname($zip_file) . '/' . pathinfo($zip_file, PATHINFO_FILENAME);
        wp_mkdir_p($extract_path);
        
        $zip->extractTo($extract_path);
        $zip->close();
        
        return $extract_path;
    }
}
