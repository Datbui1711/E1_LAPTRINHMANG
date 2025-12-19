<?php
/**
 * Stress Test - Optimized Version
 * Cải tiến: Track real response time, memory usage, better stats
 */

require __DIR__ . '/../vendor/autoload.php';

use React\EventLoop\Factory;
use React\Socket\Connector;

class StressTest {
    private $loop;
    private $connector;
    
    private $config = [
        'num_clients' => 10,
        'messages_per_client' => 50,
        'server_host' => '127.0.0.1',
        'server_port' => 5000,
    ];

    private $stats = [
        'completed_clients' => 0,
        'total_messages' => 0,
        'failed_clients' => 0,
        'response_times' => [],
        'start_time' => 0,
        'first_response_time' => null, // ✅ TỐI ƯU: Track first response
        'last_response_time' => null,
        'memory_peak' => 0,
    ];

    public function __construct($num_clients = 10, $messages = 50, $port = 5000) {
        $this->loop = Factory::create();
        $this->connector = new Connector($this->loop);
        
        $this->config['num_clients'] = $num_clients;
        $this->config['messages_per_client'] = $messages;
        $this->config['server_port'] = $port;
        $this->stats['start_time'] = microtime(true);
    }

    public function run() {
        echo "╔════════════════════════════════════════════════════════════╗\n";
        echo "║ 🔥 STRESS TEST - Performance Testing                     ║\n";
        echo "║                                                            ║\n";
        echo "║ Clients: {$this->config['num_clients']}\n";
        echo "║ Messages per client: {$this->config['messages_per_client']}\n";
        echo "║ Total messages: " . ($this->config['num_clients'] * $this->config['messages_per_client']) . "\n";
        echo "║ Server: {$this->config['server_host']}:{$this->config['server_port']}\n";
        echo "║                                                            ║\n";
        echo "╚════════════════════════════════════════════════════════════╝\n\n";

        // Tạo clients với delay nhỏ để tránh overwhelming
        $delay = 0;
        for ($i = 0; $i < $this->config['num_clients']; $i++) {
            $this->loop->addTimer($delay, function() use ($i) {
                $this->createClient($i);
            });
            $delay += 0.01; // ✅ TỐI ƯU: Stagger connection creation
        }

        $this->loop->run();
        $this->printResults();
    }

    private function createClient($client_num) {
        $server_addr = "{$this->config['server_host']}:{$this->config['server_port']}";
        
        $this->connector->connect($server_addr)
            ->then(
                function ($stream) use ($client_num) {
                    $this->handleClientConnection($stream, $client_num);
                },
                function (Exception $e) use ($client_num) {
                    echo "❌ Client {$client_num}: Lỗi kết nối - {$e->getMessage()}\n";
                    $this->stats['failed_clients']++;
                    $this->checkComplete();
                }
            );
    }

    private function handleClientConnection($stream, $client_num) {
        $message_count = 0;
        $sent_count = 0;
        $last_send_time = null;
        
        echo "✅ Client {$client_num} kết nối thành công\n";

        $stream->on('data', function ($data) use (
            &$message_count,
            &$sent_count,
            &$last_send_time,
            $stream,
            $client_num
        ) {
            $message_count++;
            
            // ✅ TỐI ƯU: Track response time
            if ($last_send_time !== null) {
                $response_time = microtime(true) - $last_send_time;
                $this->stats['response_times'][] = $response_time;
                
                if ($this->stats['first_response_time'] === null) {
                    $this->stats['first_response_time'] = microtime(true);
                }
                $this->stats['last_response_time'] = microtime(true);
            }

            // Gửi tin nhắn tiếp theo
            if ($sent_count < $this->config['messages_per_client']) {
                $msg = "TEST_MESSAGE_{$client_num}_{$sent_count}";
                $last_send_time = microtime(true);
                $stream->write($msg . "\n");
                $sent_count++;
                $this->stats['total_messages']++;
            } else {
                $stream->write("quit\n");
                $stream->end();
            }
        });

        // Gửi tin nhắn đầu tiên
        $last_send_time = microtime(true);
        $stream->write("TEST_MESSAGE_{$client_num}_0\n");
        $sent_count++;
        $this->stats['total_messages']++;

        $stream->on('close', function () use ($client_num) {
            echo "🔌 Client {$client_num} disconnect\n";
            $this->stats['completed_clients']++;
            $this->checkComplete();
        });

        $stream->on('error', function (Exception $e) use ($client_num) {
            echo "❌ Client {$client_num}: {$e->getMessage()}\n";
            $this->stats['failed_clients']++;
            $this->checkComplete();
        });
    }

    private function checkComplete() {
        $total = $this->config['num_clients'];
        $completed = $this->stats['completed_clients'] + $this->stats['failed_clients'];
        
        // ✅ TỐI ƯU: Track memory
        $this->stats['memory_peak'] = max($this->stats['memory_peak'], memory_get_peak_usage(true));
        
        if ($completed >= $total) {
            $this->loop->stop();
        }
    }

    private function printResults() {
        $elapsed = round(microtime(true) - $this->stats['start_time'], 3);
        $total_clients = $this->config['num_clients'];
        $success_rate = round(($this->stats['completed_clients'] / $total_clients) * 100, 2);
        
        // ✅ TỐI ƯU: Tính toán stats thực tế
        $response_times = $this->stats['response_times'];
        $avg_response_time = count($response_times) > 0 
            ? array_sum($response_times) / count($response_times) 
            : 0;
        
        sort($response_times);
        $p95_index = (int)(count($response_times) * 0.95);
        $p95_response_time = isset($response_times[$p95_index]) 
            ? $response_times[$p95_index] 
            : 0;

        echo "\n╔════════════════════════════════════════════════════════════╗\n";
        echo "║ 📊 KẾT QUẢ TEST                                           ║\n";
        echo "╚════════════════════════════════════════════════════════════╝\n\n";

        echo "⏱️  Thời gian tổng cộng: {$elapsed}s\n";
        echo "✅ Clients thành công: {$this->stats['completed_clients']}/{$total_clients}\n";
        echo "❌ Clients thất bại: {$this->stats['failed_clients']}/{$total_clients}\n";
        echo "📈 Tỷ lệ thành công: {$success_rate}%\n";
        echo "📨 Tổng tin nhắn: {$this->stats['total_messages']}\n";
        echo "⚡ Thông lượng: " . round($this->stats['total_messages'] / $elapsed, 2) . " msg/s\n";
        echo "🔄 Response time trung bình: " . round($avg_response_time * 1000, 2) . "ms\n";
        echo "📊 Response time P95: " . round($p95_response_time * 1000, 2) . "ms\n";
        echo "💾 Memory peak: " . round($this->stats['memory_peak'] / 1024 / 1024, 2) . "MB\n\n";

        // Đánh giá performance
        if ($elapsed < 2 && $avg_response_time < 0.01) {
            echo "🚀 KẾT LUẬN: Server xử lý RẤT NHANH - Async hoạt động tối ưu\n";
        } else if ($elapsed < 5 && $avg_response_time < 0.05) {
            echo "⚡ KẾT LUẬN: Server xử lý tốt - Có thể tăng concurrent clients\n";
        } else {
            echo "⚠️  KẾT LUẬN: Server xử lý chậm - Cần tối ưu hoặc kiểm tra\n";
        }
    }
}

// Main execution
$num_clients = isset($argv[1]) ? (int)$argv[1] : 10;
$messages = isset($argv[2]) ? (int)$argv[2] : 50;
$port = isset($argv[3]) ? (int)$argv[3] : 5000;

$test = new StressTest($num_clients, $messages, $port);
$test->run();

echo "\n💡 Gợi ý: So sánh kết quả giữa Async (5000) và Blocking (5002):\n";
echo "   Async: php stress_test.php 10 50 5000\n";
echo "   Blocking: php stress_test.php 10 50 5002\n";
