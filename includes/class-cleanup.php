<?php
namespace WatermarkManager\Includes;

class Cleanup {
    private const TEMP_FILE_EXPIRY = 86400; // 24 hours
    private const BACKUP_RETENTION = 2592000; // 30 days
    private $logger;
    private $wp_filesystem;

    public function __construct() {
        $this->logger = Logger::get_instance();
        add_action('WM_daily_cleanup', [$this, 'run_cleanup']);
        
        // Initialize WP Filesystem
        global $wp_filesystem;
        if (empty($wp_filesystem)) {
            require_once(ABSPATH . '/wp-admin/includes/file.php');
            WP_Filesystem();
            $this->wp_filesystem = $wp_filesystem;
        } else {
            $this->wp_filesystem = $wp_filesystem;
        }
    }

    public function schedule_cleanup(): void {
        if (!wp_next_scheduled('WM_daily_cleanup')) {
            wp_schedule_event(time(), 'daily', 'WM_daily_cleanup');
        }
    }

    public function run_cleanup(): void {
        try {
            $this->cleanup_temp_files();
            $this->cleanup_old_backups();
            $this->cleanup_logs();
            
            $this->logger->log_with_context(
                'Daily cleanup completed successfully',
                'INFO',
                ['timestamp' => current_time('Y-m-d H:i:s')]
            );
        } catch (\Exception $e) {
            $this->logger->log_with_context(
                'Cleanup failed: ' . $e->getMessage(),
                'ERROR',
                ['exception' => $e]
            );
        }
    }

    private function cleanup_temp_files(): void {
        $temp_dir = wp_upload_dir()['basedir'] . '/WM-temp';
        $this->cleanup_directory($temp_dir, self::TEMP_FILE_EXPIRY);
    }

    private function cleanup_old_backups(): void {
        $backup_dir = wp_upload_dir()['basedir'] . '/WM-backups';
        $this->cleanup_directory($backup_dir, self::BACKUP_RETENTION);
    }

    private function cleanup_logs(): void {
        $log_dir = wp_upload_dir()['basedir'] . '/WM-logs';
        $this->rotate_logs($log_dir);
    }

    private function cleanup_directory(string $directory, int $expiry): void {
        if (!is_dir($directory)) {
            return;
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $file) {
            if ($file->isFile() && (time() - $file->getMTime() >= $expiry)) {
                wp_delete_file($file->getRealPath());
            }
        }
    }

    private function rotate_logs(string $log_dir): void {
        if (!is_dir($log_dir)) {
            return;
        }

        $log_file = $log_dir . '/watermark-manager.log';
        if (file_exists($log_file) && filesize($log_file) > 5 * 1024 * 1024) { // 5MB
            $backup_name = $log_dir . '/watermark-manager-' . gmdate('Y-m-d-His') . '.log';
            
            // Use WP_Filesystem to move file
            $this->wp_filesystem->move($log_file, $backup_name, true);
            
            // Keep only last 5 log files
            $log_files = glob($log_dir . '/watermark-manager-*.log');
            if (count($log_files) > 5) {
                usort($log_files, function($a, $b) {
                    return filemtime($b) - filemtime($a);
                });
                
                $files_to_delete = array_slice($log_files, 5);
                foreach ($files_to_delete as $file) {
                    wp_delete_file($file);
                }
            }
        }
    }
}