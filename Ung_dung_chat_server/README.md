# 💬 Chat Application với Python Asyncio

Ứng dụng chat real-time với hai phiên bản:
1. **Console Chat** - Chat qua terminal
2. **Web Chat** - Giao diện web hiện đại với WebSocket

## 🌟 Tính năng

### Console Version
- ✅ Xử lý nhiều client kết nối đồng thời
- ✅ Broadcast tin nhắn đến tất cả client
- ✅ Nickname cho mỗi người dùng
- ✅ Hiển thị timestamp cho mỗi tin nhắn
- ✅ Thông báo khi có người tham gia/rời khỏi
- ✅ Các lệnh hữu ích (`/users`, `/quit`)
- ✅ Xử lý lỗi và ngắt kết nối gracefully

### Web Version
- ✅ Giao diện web đẹp, responsive
- ✅ Real-time messaging với WebSocket
- ✅ Typing indicator (hiển thị đang gõ)
- ✅ Online users list với avatar
- ✅ Message history
- ✅ Emoji picker 😊
- ✅ Toast notifications
- ✅ Smooth animations

## 📋 Yêu cầu

- Python 3.7 trở lên
- aiohttp (cho web version): `pip install aiohttp`

## 🚀 Cách sử dụng

### Phiên bản Web (Khuyến nghị) 🌐

#### 1. Cài đặt dependencies

```bash
pip install aiohttp
```

#### 2. Khởi động Web Server

```bash
python chat_web_server.py
```

Server sẽ chạy tại: **http://127.0.0.1:8080**

#### 3. Mở trình duyệt

Truy cập: **http://127.0.0.1:8080**

- Nhập tên của bạn
- Bắt đầu chat với giao diện đẹp mắt!
- Mở nhiều tab/cửa sổ để test chat với nhiều người

### Phiên bản Console 💻

#### 1. Khởi động Console Server

```bash
python chat_server.py
```

Server sẽ chạy trên `127.0.0.1:8888`

#### 2. Kết nối Client

Mở terminal khác và chạy:

```bash
python chat_client.py
```

Bạn có thể mở nhiều terminal để chạy nhiều client.

#### 3. Chat

- Nhập nickname khi được yêu cầu
- Gõ tin nhắn và Enter để gửi
- Sử dụng các lệnh:
  - `/users` - Xem danh sách người dùng online
  - `/quit` hoặc `/exit` - Thoát khỏi chat

## 🏗️ Kiến trúc

### Web Chat Server (`chat_web_server.py`)

- **WebChatServer class**: Quản lý WebSocket connections
  - `handle_websocket()`: Xử lý từng WebSocket connection
  - `broadcast_message()`: Gửi tin nhắn đến tất cả clients
  - `broadcast_user_list()`: Update danh sách users
  - `message_history`: Lưu 50 tin nhắn gần nhất
  
- **Web Framework**: Sử dụng `aiohttp` cho HTTP server và WebSocket
- **Real-time**: WebSocket cho two-way communication
- **Static Files**: Serve HTML/CSS/JS từ thư mục `static/`

### Web Client (HTML/CSS/JS)

- **index.html**: Cấu trúc UI với login và chat screens
- **style.css**: Modern styling với gradients, animations
- **app.js**: WebSocket client logic, DOM manipulation
  - Login flow
  - Send/receive messages
  - User list management
  - Typing indicators
  - Emoji picker

## 🛡️ Xử lý lỗi

- ✅ Xử lý client ngắt kết nối đột ngột
- ✅ Xử lý lỗi network
- ✅ Cleanup resources khi server shutdown
- ✅ Logging đầy đủ để debug

## 📚 Kiến thức áp dụng

- **Asyncio**: Coroutines, tasks, event loop
- **Network Programming**: TCP sockets
- **Concurrent Programming**: Xử lý nhiều kết nối đồng thời
- **Error Handling**: Try/except/finally patterns
- **Resource Management**: Context managers, cleanup

## 📁 Cấu trúc Project

```
chat-app/
├── chat_server.py          # Console TCP server
├── chat_client.py          # Console TCP client
├── chat_web_server.py      # Web server với WebSocket
├── static/
│   ├── index.html          # Web UI
│   ├── style.css           # Styling
│   └── app.js              # Client-side logic
└── README.md
```

## 🎯 Mở rộng thêm

Các tính năng có thể thêm:
- ✨ Private messages giữa các user
- 🏠 Room/channel system  
- 🔐 Authentication với JWT
- 💾 Persistent message storage (database)
- 📎 File transfer & image sharing
- 🔍 Search messages
- 📱 Mobile responsive improvements
- 🌙 Dark/light theme toggle
- 🔔 Desktop notifications
- 📊 Admin dashboard
- 🎤 Voice messages
- 📹 Video chat integration
