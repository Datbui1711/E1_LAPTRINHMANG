<?php
/**
 * Echo Client - Windows-safe async stdin
 * Fix: Không dùng ReadableResourceStream cho STDIN (tránh lỗi non-blocking trên Windows/VSCode terminal)
 */

require __DIR__ . '/../vendor/autoload.php';

use React\EventLoop\Factory;
use React\Socket\Connector;

$loop = Factory::create();
$connector = new Connector($loop);

$connector->connect('127.0.0.1:5000')->then(
    function ($stream) use ($loop) {
        echo "╔════════════════════════════════════════════════╗\n";
        echo "║ ECHO CLIENT - Kết nối Thành Công              ║\n";
        echo "║                                                ║\n";
        echo "║ Server: 127.0.0.1:5000                         ║\n";
        echo "║ Gõ tin nhắn và nhấn Enter                      ║\n";
        echo "║ Gõ 'quit' để thoát                             ║\n";
        echo "╚════════════════════════════════════════════════╝\n\n";

        $closing = false;

        // Nhận dữ liệu từ server
        $stream->on('data', function ($data) {
            echo "📥 Từ Server: {$data}";
        });

        // Đọc STDIN kiểu non-blocking (Windows-safe)
        $stdin = fopen('php://stdin', 'r');
        stream_set_blocking($stdin, false);

        $loop->addReadStream($stdin, function ($stdin) use ($stream, $loop, &$closing) {
            $line = fgets($stdin);
            if ($line === false) {
                return;
            }

            $line = trim($line);
            if ($line === '') {
                return;
            }

            // Nếu đang đóng thì bỏ qua
            if ($closing) {
                return;
            }

            $stream->write($line . "\n");

            if (strtolower($line) === 'quit') {
                $closing = true;
                echo "✋ Ngắt kết nối...\n";
                $stream->end();
                // loop sẽ stop ở callback close (tránh stop 2 lần)
            }
        });

        $stream->on('close', function () use ($loop, $stdin) {
            // cleanup stdin watcher
            $loop->removeReadStream($stdin);
            if (is_resource($stdin)) {
                fclose($stdin);
            }

            echo "🔌 Kết nối đóng\n";
            $loop->stop();
        });

        $stream->on('error', function (Exception $e) use (&$closing, $stream) {
            $closing = true;
            echo "❌ Lỗi: " . $e->getMessage() . "\n";
            $stream->close();
        });
    },
    function (Exception $e) use ($loop) {
        echo "❌ Không thể kết nối: " . $e->getMessage() . "\n";
        echo "   Kiểm tra xem Echo Server có đang chạy không?\n";
        echo "   Chạy: php echo_server/echo_server_async.php\n";
        $loop->stop();
    }
);

$loop->run();
