<?php
/**
 * Chat Server - Async Version (Optimized)
 * Cải tiến: Better broadcast, connection limits, stats tracking
 */

require __DIR__ . '/../vendor/autoload.php';

use React\EventLoop\Factory;
use React\Socket\Server;
use React\Socket\ConnectionInterface;

// ✅ TỐI ƯU: Constants
define('MAX_MESSAGE_SIZE', 4096);
define('MAX_USERNAME_LENGTH', 50);
define('MAX_CONNECTIONS', 100);

$loop = Factory::create();
$server = new Server('127.0.0.1:5001', $loop);

$clients = [];
$client_names = [];

$stats = [
    'total_connections' => 0,
    'active_connections' => 0,
    'messages_sent' => 0,
    'broadcast_count' => 0, // ✅ TỐI ƯU: Track broadcasts
    'start_time' => microtime(true),
];

/**
 * ✅ TỐI ƯU: Broadcast với error handling
 */
function broadcast($message, $sender_id = null) {
    global $clients, $client_names, $stats;
    
    $timestamp = date('H:i:s');
    $sender_name = isset($sender_id) && isset($client_names[$sender_id])
        ? $client_names[$sender_id]
        : 'System';
    
    $broadcast_msg = "[{$timestamp}] {$sender_name}: {$message}\n";
    $sent_count = 0;
    
    foreach ($clients as $client_id => $connection) {
        if ($connection->isWritable()) {
            try {
                $connection->write($broadcast_msg);
                $sent_count++;
            } catch (Exception $e) {
                echo "[{$timestamp}] ⚠️ Lỗi broadcast đến {$client_id}\n";
            }
        }
    }
    
    $stats['broadcast_count']++;
    return $sent_count;
}

/**
 * ✅ TỐI ƯU: Send với writable check
 */
function send_to_client($connection, $message) {
    if ($connection->isWritable()) {
        try {
            $connection->write($message);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
    return false;
}

$server->on('connection', function (ConnectionInterface $connection) use (&$clients, &$client_names, &$stats, $loop) {
    // ✅ TỐI ƯU: Giới hạn connections
    if ($stats['active_connections'] >= MAX_CONNECTIONS) {
        send_to_client($connection, "❌ Server đầy (max " . MAX_CONNECTIONS . " users)\n");
        $connection->end();
        return;
    }

    $stats['total_connections']++;
    $stats['active_connections']++;
    
    $client_id = uniqid('client_', true);
    $client_ip = $connection->getRemoteAddress();
    $clients[$client_id] = $connection;
    $buffer = ''; // ✅ TỐI ƯU: Buffer
    $username_set = false;
    $connection_time = microtime(true);
    
    echo "[" . date('H:i:s') . "] ✅ Client mới kết nối: {$client_ip} (ID: {$client_id})\n";

    send_to_client($connection, "╔════════════════════════════════════════╗\n");
    send_to_client($connection, "║ 👋 Chào mừng đến CHAT SERVER          ║\n");
    send_to_client($connection, "╚════════════════════════════════════════╝\n\n");
    send_to_client($connection, "📝 Nhập tên của bạn: ");

    $connection->on('data', function ($data) use (
        &$clients,
        &$client_names,
        &$stats,
        $client_id,
        $connection,
        &$username_set,
        &$buffer,
        $client_ip
    ) {
        // ✅ TỐI ƯU: Buffer overflow check
        $buffer .= $data;
        
        if (strlen($buffer) > MAX_MESSAGE_SIZE) {
            send_to_client($connection, "❌ Tin nhắn quá dài\n");
            $connection->end();
            return;
        }

        while (($pos = strpos($buffer, "\n")) !== false) {
            $line = substr($buffer, 0, $pos);
            $buffer = substr($buffer, $pos + 1);
            $message = trim($line);

            if ($message === '') continue;

            // Xử lý username
            if (!$username_set) {
                // ✅ TỐI ƯU: Validate username
                if (strlen($message) > MAX_USERNAME_LENGTH) {
                    send_to_client($connection, "❌ Tên quá dài (max " . MAX_USERNAME_LENGTH . " ký tự)\n");
                    send_to_client($connection, "📝 Nhập tên của bạn: ");
                    continue;
                }

                if (!preg_match('/^[a-zA-Z0-9_\x{0080}-\x{FFFF}]+$/u', $message)) {
                    send_to_client($connection, "❌ Tên chỉ chứa chữ, số và _\n");
                    send_to_client($connection, "📝 Nhập tên của bạn: ");
                    continue;
                }

                $username_set = true;
                $client_names[$client_id] = $message;
                
                echo "[" . date('H:i:s') . "] ✅ {$message} đã tham gia chat\n";

                $active_count = count($clients);
                broadcast("✅ {$message} đã tham gia ({$active_count} người online)");

                send_to_client($connection, "\n🎉 Chào mừng {$message}!\n");
                send_to_client($connection, "📋 Lệnh:\n");
                send_to_client($connection, "   /users - Xem danh sách người dùng\n");
                send_to_client($connection, "   /stats - Xem thống kê server\n");
                send_to_client($connection, "   /help - Xem trợ giúp\n");
                send_to_client($connection, "   /quit - Thoát\n\n");
                continue;
            }

            $username = $client_names[$client_id];
            echo "[" . date('H:i:s') . "] 📨 {$username}: {$message}\n";

            // Xử lý commands
            if (substr($message, 0, 1) === '/') {
                $command = strtolower(substr($message, 1));
                
                switch ($command) {
                    case 'users':
                        $user_list = "👥 Danh sách người dùng online:\n";
                        $count = 0;
                        foreach ($client_names as $uid => $uname) {
                            $count++;
                            $user_list .= "   {$count}. {$uname}\n";
                        }
                        send_to_client($connection, $user_list . "\n");
                        break;

                    case 'stats':
                        $uptime = round(microtime(true) - $stats['start_time']);
                        $stats_msg = "📈 THỐNG KÊ SERVER:\n";
                        $stats_msg .= "   ├─ Tổng kết nối: {$stats['total_connections']}\n";
                        $stats_msg .= "   ├─ Kết nối hiện tại: {$stats['active_connections']}\n";
                        $stats_msg .= "   ├─ Tin nhắn đã gửi: {$stats['messages_sent']}\n";
                        $stats_msg .= "   ├─ Số lần broadcast: {$stats['broadcast_count']}\n";
                        $stats_msg .= "   └─ Thời gian chạy: {$uptime}s\n\n";
                        send_to_client($connection, $stats_msg);
                        break;

                    case 'help':
                        $help_msg = "📋 TRỢ GIÚP:\n";
                        $help_msg .= "   /users - Xem danh sách người dùng\n";
                        $help_msg .= "   /stats - Xem thống kê server\n";
                        $help_msg .= "   /quit - Thoát khỏi chat\n";
                        $help_msg .= "   Gõ tin nhắn bình thường để gửi broadcast\n\n";
                        send_to_client($connection, $help_msg);
                        break;

                    case 'quit':
                        echo "[" . date('H:i:s') . "] 👋 {$username} yêu cầu thoát\n";
                        $connection->end();
                        break;

                    default:
                        send_to_client($connection, "❓ Lệnh không tồn tại: /{$command}\n");
                        send_to_client($connection, "Gõ /help để xem danh sách lệnh\n\n");
                }
                continue;
            }

            // Broadcast tin nhắn
            $stats['messages_sent']++;
            broadcast($message, $client_id);
        }
    });

    $connection->on('error', function (Exception $e) use ($client_id, $client_ip, &$client_names) {
        $username = $client_names[$client_id] ?? 'Unknown';
        echo "[" . date('H:i:s') . "] ❌ Lỗi từ {$username} ({$client_ip}): {$e->getMessage()}\n";
    });

    $connection->on('close', function () use (&$clients, &$client_names, &$stats, $client_id, $connection_time) {
        $stats['active_connections']--;
        $username = $client_names[$client_id] ?? 'Unknown';
        $session_duration = round(microtime(true) - $connection_time);
        
        unset($clients[$client_id]);
        unset($client_names[$client_id]);
        
        echo "[" . date('H:i:s') . "] 🔌 {$username} đã thoát (session: {$session_duration}s)\n";

        $active_count = count($clients);
        broadcast("👋 {$username} đã thoát ({$active_count} người online)");
    });
});

$server->on('error', function (Exception $e) {
    echo "❌ Lỗi Server: " . $e->getMessage() . "\n";
});

echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║ 🚀 CHAT SERVER BẤT ĐỒNG BỘ (ReactPHP)                    ║\n";
echo "║                                                            ║\n";
echo "║ Địa chỉ: 127.0.0.1:5001                                   ║\n";
echo "║ Protocol: TCP                                             ║\n";
echo "║ Mode: Async/Non-blocking - Multiple Clients              ║\n";
echo "║                                                            ║\n";
echo "║ Chức năng:                                                ║\n";
echo "║ ✓ Hỗ trợ multiple clients (max " . MAX_CONNECTIONS . ")                    ║\n";
echo "║ ✓ Broadcast tin nhắn                                     ║\n";
echo "║ ✓ Lệnh: /users, /stats, /help, /quit                    ║\n";
echo "║                                                            ║\n";
echo "║ Client sử dụng: php chat_server/chat_client.php          ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";

// ✅ TỐI ƯU: Giảm interval xuống 30s
$loop->addPeriodicTimer(30, function () use (&$stats) {
    $uptime = round(microtime(true) - $stats['start_time']);
    echo "\n[" . date('H:i:s') . "] 📊 THỐNG KÊ HỆ THỐNG:\n";
    echo "   ├─ Tổng kết nối: {$stats['total_connections']}\n";
    echo "   ├─ Kết nối hiện tại: {$stats['active_connections']}\n";
    echo "   ├─ Tin nhắn đã gửi: {$stats['messages_sent']}\n";
    echo "   ├─ Số lần broadcast: {$stats['broadcast_count']}\n";
    echo "   └─ Thời gian chạy: {$uptime}s\n\n";
});

$loop->run();
