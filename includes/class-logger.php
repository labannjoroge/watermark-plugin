<?php
namespace WatermarkManager\Includes;

/**
 * Logger Class
 * 
 * Handles centralized logging for the Watermark Manager plugin.
 *
 * @since 1.1.0
 */
class Logger {
    // Log levels as class constants
    private const LOG_LEVELS = [
        'DEBUG' => 'debug',
        'INFO' => 'info',
        'WARNING' => 'warning',
        'ERROR' => 'error',
        'CRITICAL' => 'critical'
    ];

    private const MAX_LOG_SIZE = 5 * 1024 * 1024; // 5MB
    private const MAX_LOG_FILES = 5;

    private static $instance = null;
    private $log_enabled;
    private $log_file;
    private $debug_mode;
    private $filesystem;

    /**
     * Constructor
     */
    private function __construct() {
        $this->log_enabled = get_option('WM_logging_enabled', true);
        $this->debug_mode = defined('WP_DEBUG') && WP_DEBUG;
        $upload_dir = wp_upload_dir();
        $this->log_file = $upload_dir['basedir'] . '/WM-logs/watermark-manager.log';
        
        // Initialize WP_Filesystem
        global $wp_filesystem;
        if (empty($wp_filesystem)) {
            require_once ABSPATH . '/wp-admin/includes/file.php';
            WP_Filesystem();
        }
        $this->filesystem = $wp_filesystem;
        
        // Ensure log directory exists with proper permissions
        $log_dir = dirname($this->log_file);
        if (!$this->filesystem->exists($log_dir)) {
            wp_mkdir_p($log_dir);
            // Use WP_Filesystem to create files instead of direct file_put_contents
            $this->filesystem->put_contents($log_dir . '/.htaccess', 'deny from all', FS_CHMOD_FILE);
            $this->filesystem->put_contents($log_dir . '/index.php', '<?php // Silence is golden', FS_CHMOD_FILE);
        }
    }

    /**
     * Get logger instance
     */
    public static function get_instance(): Logger {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Log a message with context
     */
    public function log_with_context(string $message, string $level, array $context = []): void {
        if (!$this->should_log($level)) {
            return;
        }

        $this->rotate_logs_if_needed();

        $log_entry = $this->format_log_entry($message, $level, $context);
        $this->write_log($log_entry);

        if ($this->is_critical_level($level)) {
            $this->notify_admin($log_entry);
        }
    }

    /**
     * Check if the log level should be logged
     */
    private function should_log(string $level): bool {
        if (!$this->log_enabled && $level !== self::LOG_LEVELS['ERROR']) {
            return false;
        }

        return true;
    }

    /**
     * Format the log entry
     */
    private function format_log_entry(string $message, string $level, array $context): array {
        // Get and sanitize server variables
        $request_uri = isset($_SERVER['REQUEST_URI']) ? esc_url_raw(wp_unslash($_SERVER['REQUEST_URI'])) : '';
        $remote_addr = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
        
        return [
            'timestamp' => current_time('Y-m-d H:i:s'),
            'level' => $level,
            'message' => $message,
            'context' => $context,
            'user_id' => get_current_user_id(),
            'url' => $request_uri,
            'ip' => $remote_addr,
        ];
    }

    /**
     * Write the log entry to the log file
     */
    private function write_log(array $log_entry): void {
        // Ensure required keys exist
        $log_entry = array_merge([
            'timestamp' => current_time('mysql'),
            'level' => 'info',
            'message' => 'No message provided',
            'context' => []
        ], $log_entry);
    
        $formatted_message = sprintf(
            "[%s] [%s] %s - Context: %s\n",
            $log_entry['timestamp'],
            $log_entry['level'],
            $log_entry['message'],
            wp_json_encode(is_array($log_entry['context']) ? $log_entry['context'] : [])
        );
    
        // Check if filesystem exists
        if (!$this->filesystem) {
            error_log("Filesystem API not initialized");
            return;
        }
    
        // Ensure log file path exists
        if (!$this->filesystem->exists($this->log_file)) {
            $this->filesystem->put_contents($this->log_file, "Log Start\n", FS_CHMOD_FILE);
        }
    
        // Append to log file
        $this->filesystem->put_contents($this->log_file, $formatted_message, FS_CHMOD_FILE | FILE_APPEND);

    }

    /**
     * Check if the log level is critical
     */
    private function is_critical_level(string $level): bool {
        return in_array($level, [self::LOG_LEVELS['ERROR'], self::LOG_LEVELS['CRITICAL']], true);
    }

    /**
     * Notify the admin about critical logs
     */
    private function notify_admin(array $log_entry): void {
        // Implement your notification logic here
        // For example, send an email to the admin
        $subject = 'Critical Log Entry in Watermark Manager';
        $message = "A critical log entry was recorded:\n\n";
        
        // Create a readable message for the email
        foreach ($log_entry as $key => $value) {
            if ($key === 'context' && is_array($value)) {
                $message .= "$key: " . wp_json_encode($value) . "\n";
            } else {
                $message .= "$key: $value\n";
            }
        }
        
        wp_mail(get_option('admin_email'), $subject, $message);
    }

    /**
     * Rotate logs if the file size exceeds the maximum allowed size
     */
    private function rotate_logs_if_needed(): void {
        if ($this->filesystem->exists($this->log_file)) {
            $file_size = $this->filesystem->size($this->log_file);
            if ($file_size > self::MAX_LOG_SIZE) {
                $this->rotate_logs();
            }
        }
    }

    /**
     * Rotate logs
     */
    private function rotate_logs(): void {
        $log_dir = dirname($this->log_file);
        $log_files = glob($log_dir . '/watermark-manager.log.*');
        if (count($log_files) >= self::MAX_LOG_FILES) {
            // Sort files by creation time and remove the oldest ones
            usort($log_files, function($a, $b) {
                return filemtime($a) - filemtime($b);
            });
            $files_to_delete = count($log_files) - self::MAX_LOG_FILES + 1;
            for ($i = 0; $i < $files_to_delete; $i++) {
                // Use wp_delete_file instead of unlink
                wp_delete_file($log_files[$i]);
            }
        }

        // Use gmdate instead of date for timezone independence
        $rotated_file = $this->log_file . '.' . gmdate('Y-m-d_His');
        
        // Use WP_Filesystem to move files instead of rename
        if ($this->filesystem->exists($this->log_file)) {
            $this->filesystem->move($this->log_file, $rotated_file);
        }
    }

    /**
     * Log debug message
     */
    public function debug(string $message, array $context = []): void {
        $this->log_with_context($message, self::LOG_LEVELS['DEBUG'], $context);
    }

    /**
     * Log info message
     */
    public function info(string $message, array $context = []): void {
        $this->log_with_context($message, self::LOG_LEVELS['INFO'], $context);
    }

    /**
     * Log warning message
     */
    public function warning(string $message, array $context = []): void {
        $this->log_with_context($message, self::LOG_LEVELS['WARNING'], $context);
    }

    /**
     * Log error message
     */
    public function error(string $message, array $context = []): void {
        $this->log_with_context($message, self::LOG_LEVELS['ERROR'], $context);
    }

    /**
     * Log critical message
     */
    public function critical(string $message, array $context = []): void {
        $this->log_with_context($message, self::LOG_LEVELS['CRITICAL'], $context);
    }

    /**
     * Get log file contents
     */
    public function get_logs(int $lines = 100): array {
        if (!$this->filesystem->exists($this->log_file)) {
            return [];
        }

        $content = $this->filesystem->get_contents($this->log_file);
        if (!$content) {
            return [];
        }
        
        $logs = explode("\n", $content);
        return array_slice(array_filter($logs), -$lines);
    }

    /**
     * Clear log file
     */
    public function clear_logs(): bool {
        return $this->filesystem->put_contents($this->log_file, '', FS_CHMOD_FILE) !== false;
    }

    /**
     * Check if logging is enabled
     */
    public function is_enabled(): bool {
        return $this->log_enabled;
    }

    /**
     * Enable or disable logging
     */
    public function set_enabled(bool $enabled): void {
        $this->log_enabled = $enabled;
        update_option('WM_logging_enabled', $enabled);
    }
}