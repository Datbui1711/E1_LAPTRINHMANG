<?php
/**
 * Echo Server - Blocking/Synchronous Version (Windows)
 * Yêu cầu bật ext-sockets.
 */

// constants safe-define
defined('MAX_BUFFER_SIZE') || define('MAX_BUFFER_SIZE', 8192);
defined('CONNECTION_TIMEOUT') || define('CONNECTION_TIMEOUT', 60);

if (!extension_loaded('sockets')) {
    fwrite(STDERR, "❌ PHP thiếu extension 'sockets'. Bật extension=sockets trong php.ini rồi chạy lại.\n");
    exit(1);
}

// Tạo TCP socket
$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
if ($socket === false) {
    die("❌ Không thể tạo socket: " . socket_strerror(socket_last_error()) . "\n");
}

socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1);

if (!socket_bind($socket, '127.0.0.1', 5002)) {
    die("❌ Không thể bind: " . socket_strerror(socket_last_error($socket)) . "\n");
}

if (!socket_listen($socket, 5)) {
    die("❌ Không thể listen: " . socket_strerror(socket_last_error($socket)) . "\n");
}

$stats = [
    'total_connections' => 0,
    'messages_received' => 0,
    'start_time' => microtime(true),
];

echo "🚀 ECHO SERVER BLOCKING đang chạy tại 127.0.0.1:5002\n";
echo "⚠️ Chỉ xử lý 1 client tại 1 thời điểm\n\n";

while (true) {
    $client = socket_accept($socket);
    if ($client === false) {
        echo "❌ Không thể accept connection\n";
        continue;
    }

    $stats['total_connections']++;
    $client_ip = '';
    socket_getpeername($client, $client_ip);
    echo "[" . date('H:i:s') . "] ✅ Client kết nối: {$client_ip}\n";

    socket_set_option($client, SOL_SOCKET, SO_RCVTIMEO, ['sec' => CONNECTION_TIMEOUT, 'usec' => 0]);
    socket_set_option($client, SOL_SOCKET, SO_SNDTIMEO, ['sec' => 5, 'usec' => 0]);

    $connection_start = microtime(true);

    while (true) {
        if ((microtime(true) - $connection_start) > CONNECTION_TIMEOUT) {
            echo "[" . date('H:i:s') . "] ⏰ Timeout - Client {$client_ip}\n";
            break;
        }

        $data = @socket_read($client, MAX_BUFFER_SIZE, PHP_NORMAL_READ);

        if ($data === false) {
            $err = socket_last_error($client);
            echo "[" . date('H:i:s') . "] ❌ socket_read lỗi: " . socket_strerror($err) . "\n";
            break;
        }

        if ($data === '' || strlen($data) === 0) {
            echo "[" . date('H:i:s') . "] 🔌 Client {$client_ip} disconnect\n";
            break;
        }

        $message = trim($data);
        if ($message === '') continue;

        $stats['messages_received']++;
        echo "[" . date('H:i:s') . "] 📨 {$client_ip}: '{$message}'\n";

        $response = "ECHO: {$message}\n";
        if (@socket_write($client, $response, strlen($response)) === false) {
            echo "[" . date('H:i:s') . "] ❌ socket_write lỗi\n";
            break;
        }

        if (strtolower($message) === 'quit') {
            echo "[" . date('H:i:s') . "] 👋 Client {$client_ip} yêu cầu disconnect\n";
            break;
        }
    }

    socket_close($client);
    echo "📊 Total connections: {$stats['total_connections']}, Messages: {$stats['messages_received']}\n\n";
}

socket_close($socket);
