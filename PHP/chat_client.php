<?php
/**
 * Chat Client (Windows-safe, low CPU)
 * Fix: Không dùng ReadableResourceStream cho STDIN trên Windows/VSCode terminal.
 * Opt: Dùng addReadStream thay vì addPeriodicTimer(0.05).
 */

require __DIR__ . '/../vendor/autoload.php';

use React\EventLoop\Factory;
use React\Socket\Connector;

$loop = Factory::create();
$connector = new Connector($loop);

$connector->connect('127.0.0.1:5001')->then(
    function ($stream) use ($loop) {
        echo "\n✅ Kết nối đến Chat Server thành công!\n\n";

        $closing = false;
        $inputBuffer = '';

        // In message từ server + re-print prompt
        $stream->on('data', function ($data) use (&$inputBuffer) {
            echo "\033[2K\r"; // clear current line
            echo $data;
            echo "📝 Bạn: " . $inputBuffer;
            flush();
        });

        // STDIN non-blocking (Windows-safe)
        $stdin = fopen('php://stdin', 'r');
        stream_set_blocking($stdin, false);

        echo "📝 Bạn: ";
        flush();

        // Đọc theo event (không polling)
        $loop->addReadStream($stdin, function ($stdin) use ($stream, $loop, &$inputBuffer, &$closing) {
            // fgets sẽ trả về 1 dòng khi nhấn Enter
            $line = fgets($stdin);
            if ($line === false) {
                return;
            }

            $msg = rtrim($line, "\r\n");

            // reset buffer hiển thị
            $inputBuffer = '';

            if ($msg === '') {
                echo "📝 Bạn: ";
                flush();
                return;
            }

            if ($closing) {
                return;
            }

            $stream->write($msg . "\n");

            if (strtolower(trim($msg)) === '/quit') {
                $closing = true;
                $stream->end();
                return; // loop stop ở on('close')
            }

            echo "📝 Bạn: ";
            flush();
        });

        // Nếu bạn vẫn muốn “gõ từng ký tự” (backspace realtime) thì cần lib khác;
        // còn cách này ổn định trên Windows và ít lỗi nhất.

        $stream->on('close', function () use ($loop, $stdin) {
            $loop->removeReadStream($stdin);
            if (is_resource($stdin)) {
                fclose($stdin);
            }
            echo "\n🔌 Đã ngắt kết nối khỏi server\n";
            $loop->stop();
        });

        $stream->on('error', function (Exception $e) use (&$closing, $stream) {
            $closing = true;
            echo "\n❌ Lỗi: " . $e->getMessage() . "\n";
            $stream->close();
        });
    },
    function (Exception $e) {
        echo "❌ Không thể kết nối: " . $e->getMessage() . "\n";
        echo "📌 Kiểm tra xem Chat Server có đang chạy không?\n";
        echo "   Chạy: php chat_server/chat_server_async.php\n";
        exit(1);
    }
);

$loop->run();
