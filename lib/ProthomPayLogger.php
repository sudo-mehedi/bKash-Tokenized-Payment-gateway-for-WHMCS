<?php
class ProthomPayLogger {
    private $enabled;
    private $logFile;

    public function __construct($enabled = true) {
        $this->enabled = $enabled;
        $this->logFile = dirname(__DIR__, 3) . '/storage/logs/prothompay.log';
        $this->ensureLogDirectory();
    }

    public function log($message, $context = []) {
        if (!$this->enabled) return;

        $entry = sprintf(
            "[%s] %s %s\n",
            date('Y-m-d H:i:s'),
            $message,
            json_encode($context, JSON_PRETTY_PRINT)
        );

        file_put_contents($this->logFile, $entry, FILE_APPEND);
    }

    private function ensureLogDirectory() {
        $dir = dirname($this->logFile);
        if (!file_exists($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    public static function getLogs($lines = 100) {
        $logFile = dirname(__DIR__, 3) . '/storage/logs/prothompay.log';
        if (!file_exists($logFile)) return [];
        
        $content = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        return array_slice($content, -$lines);
    }
}
?>