<?php
class WowGatewayLogger {
    private $enabled;
    private $logFile;

    public function __construct($enabled = true) {
        $this->enabled = $enabled;
        $this->logFile = dirname(__DIR__, 3) . '/storage/logs/wowgateway.log';
        $this->ensureLogDirectory();
    }

    public function log($message, $context = []) {
        if (!$this->enabled) return;

        $logEntry = sprintf(
            "[%s] %s %s\n",
            date('Y-m-d H:i:s'),
            $message,
            json_encode($context, JSON_PRETTY_PRINT)
        );

        file_put_contents(
            $this->logFile,
            $logEntry,
            FILE_APPEND | LOCK_EX
        );
    }

    private function ensureLogDirectory() {
        $logDir = dirname($this->logFile);
        if (!file_exists($logDir)) {
            mkdir($logDir, 0755, true);
        }
    }

    public static function getRecentLogs($limit = 100) {
        $logFile = dirname(__DIR__, 3) . '/storage/logs/wowgateway.log';
        if (!file_exists($logFile)) return [];
        
        $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $logs = array_map('json_decode', array_slice($lines, -$limit));
        return array_reverse($logs);
    }
}
?>