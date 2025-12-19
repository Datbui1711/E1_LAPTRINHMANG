<?php
/**
 * performance_test/benchmark.php
 *
 * Benchmark so sánh Echo Server Async (5000) vs Blocking (5002)
 * - Tự chạy stress_test.php 2 lần (async và blocking)
 * - Parse kết quả: thời gian, throughput, latency
 *
 * Yêu cầu:
 * - composer install (ReactPHP đã có) [stress_test.php dùng React]
 * - echo_server_async.php chạy port 5000
 * - echo_server_blocking.php chạy port 5002
 *
 * Chạy:
 *   php performance_test/benchmark.php
 *   php performance_test/benchmark.php 50 100   (clients=50, messages=100)
 */

$root = realpath(__DIR__ . '/..');
$stressFile = $root . DIRECTORY_SEPARATOR . 'stress_test.php'; // file bạn đang để ở root [file:5]

if (!file_exists($stressFile)) {
    // Nếu bạn đặt stress_test.php trong performance_test/ thì sửa path ở đây:
    $stressFileAlt = __DIR__ . DIRECTORY_SEPARATOR . 'stress_test.php';
    if (file_exists($stressFileAlt)) {
        $stressFile = $stressFileAlt;
    } else {
        fwrite(STDERR, "❌ Không tìm thấy stress_test.php ở:\n- {$stressFile}\n- {$stressFileAlt}\n");
        exit(1);
    }
}

$clients = isset($argv[1]) ? max(1, (int)$argv[1]) : 20;
$messages = isset($argv[2]) ? max(1, (int)$argv[2]) : 50;

echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║ 🧪 BENCHMARK - Async (5000) vs Blocking (5002)             ║\n";
echo "╠════════════════════════════════════════════════════════════╣\n";
echo "║ Clients: {$clients}\n";
echo "║ Messages/client: {$messages}\n";
echo "║ Total messages: " . ($clients * $messages) . "\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";

function runStress(string $php, string $stressFile, int $clients, int $messages, int $port): array
{
    $cmd = escapeshellarg($php) . ' ' . escapeshellarg($stressFile) . ' ' .
        (int)$clients . ' ' . (int)$messages . ' ' . (int)$port;

    $start = microtime(true);
    $output = [];
    $exitCode = 0;

    // exec lấy output theo dòng
    exec($cmd . ' 2>&1', $output, $exitCode);
    $elapsed = microtime(true) - $start;

    $text = implode("\n", $output);

    // Parse các dòng in ra từ stress_test.php gốc của bạn [file:5]
    $result = [
        'port' => $port,
        'exit_code' => $exitCode,
        'raw' => $text,
        'wall_time_s' => round($elapsed, 4),
        'total_messages' => null,
        'throughput_msg_s' => null,
        'avg_latency_ms' => null,
        'success_rate' => null,
    ];

    // Tổng tin nhắn
    if (preg_match('/Tổng tin nhắn:\s*([0-9]+)/u', $text, $m)) {
        $result['total_messages'] = (int)$m[1];
    }

    // Thông lượng
    if (preg_match('/Thông lượng:\s*([0-9.]+)\s*msg\/s/u', $text, $m)) {
        $result['throughput_msg_s'] = (float)$m[1];
    }

    // Latency trung bình
    if (preg_match('/Latency trung bình:\s*([0-9.]+)\s*ms/u', $text, $m)) {
        $result['avg_latency_ms'] = (float)$m[1];
    }

    // Tỷ lệ thành công
    if (preg_match('/Tỷ lệ thành công:\s*([0-9.]+)\%/u', $text, $m)) {
        $result['success_rate'] = (float)$m[1];
    }

    return $result;
}

function printBlock(string $title, array $r): void
{
    $port = $r['port'];
    echo "==== {$title} (port {$port}) ====\n";
    echo "Exit code: {$r['exit_code']}\n";
    echo "Wall time: {$r['wall_time_s']}s\n";
    echo "Success rate: " . ($r['success_rate'] !== null ? $r['success_rate'] . '%' : 'N/A') . "\n";
    echo "Total messages: " . ($r['total_messages'] !== null ? $r['total_messages'] : 'N/A') . "\n";
    echo "Throughput: " . ($r['throughput_msg_s'] !== null ? $r['throughput_msg_s'] . ' msg/s' : 'N/A') . "\n";
    echo "Avg latency: " . ($r['avg_latency_ms'] !== null ? $r['avg_latency_ms'] . ' ms' : 'N/A') . "\n\n";
}

$php = PHP_BINARY;

// Run Async (5000)
$async = runStress($php, $stressFile, $clients, $messages, 5000);
printBlock('ASYNC', $async);

// Run Blocking (5002)
$blocking = runStress($php, $stressFile, $clients, $messages, 5002);
printBlock('BLOCKING', $blocking);

// So sánh nhanh
echo "==== SO SÁNH ====\n";
if ($async['throughput_msg_s'] !== null && $blocking['throughput_msg_s'] !== null) {
    $ratio = $blocking['throughput_msg_s'] > 0 ? $async['throughput_msg_s'] / $blocking['throughput_msg_s'] : null;
    if ($ratio !== null) {
        echo "Throughput async/blocking: " . round($ratio, 2) . "x\n";
    }
}

if ($async['avg_latency_ms'] !== null && $blocking['avg_latency_ms'] !== null) {
    echo "Avg latency (ms): async={$async['avg_latency_ms']} | blocking={$blocking['avg_latency_ms']}\n";
}

echo "\nGhi chú:\n";
echo "- Trước khi chạy benchmark, hãy mở 2 terminal và chạy server:\n";
echo "  1) php echo_server/echo_server_async.php   (port 5000)\n";
echo "  2) php echo_server/echo_server_blocking.php (port 5002)\n";
echo "- Nếu port bị 'connection refused' thì server chưa chạy hoặc chạy sai port.\n";
