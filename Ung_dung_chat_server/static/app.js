// Global variables
let ws = null;
let currentUser = null;
let typingTimer = null;
let isTyping = false;

// DOM elements
const loginScreen = document.getElementById('login-screen');
const chatScreen = document.getElementById('chat-screen');
const loginForm = document.getElementById('login-form');
const nicknameInput = document.getElementById('nickname');
const messageForm = document.getElementById('message-form');
const messageInput = document.getElementById('message-input');
const messagesContainer = document.getElementById('messages');
const userList = document.getElementById('user-list');
const userCount = document.getElementById('user-count');
const currentUserName = document.getElementById('current-user-name');
const typingIndicator = document.getElementById('typing-indicator');
const logoutBtn = document.getElementById('logout-btn');
const emojiBtn = document.getElementById('emoji-btn');
const emojiPicker = document.getElementById('emoji-picker');

// Emojis phổ biến
const emojis = ['😊', '😂', '❤️', '👍', '🎉', '🔥', '✨', '💯', '🙌', '👏', 
                '😍', '🤔', '😎', '🥳', '😢', '😅', '🤗', '😴', '🤩', '😜'];

// Initialize emoji picker
function initEmojiPicker() {
    emojiPicker.innerHTML = emojis.map(emoji => 
        `<span class="emoji" style="cursor: pointer; padding: 8px; display: inline-block; transition: transform 0.2s;" 
         onmouseover="this.style.transform='scale(1.3)'" 
         onmouseout="this.style.transform='scale(1)'">${emoji}</span>`
    ).join('');
    
    // Add click handlers to emojis
    emojiPicker.querySelectorAll('.emoji').forEach(emoji => {
        emoji.addEventListener('click', () => {
            messageInput.value += emoji.textContent;
            messageInput.focus();
            emojiPicker.classList.remove('show');
        });
    });
}

// Toggle emoji picker
emojiBtn.addEventListener('click', () => {
    emojiPicker.classList.toggle('show');
});

// Close emoji picker when clicking outside
document.addEventListener('click', (e) => {
    if (!emojiBtn.contains(e.target) && !emojiPicker.contains(e.target)) {
        emojiPicker.classList.remove('show');
    }
});

// Login
loginForm.addEventListener('submit', (e) => {
    e.preventDefault();
    const nickname = nicknameInput.value.trim();
    if (nickname) {
        currentUser = nickname;
        connectWebSocket();
    }
});

// Connect to WebSocket
function connectWebSocket() {
    const protocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
    const wsUrl = `${protocol}//${window.location.host}/ws`;
    
    ws = new WebSocket(wsUrl);
    
    ws.onopen = () => {
        console.log('Connected to server');
        // Send join message
        ws.send(JSON.stringify({
            type: 'join',
            nickname: currentUser
        }));
        
        // Switch to chat screen
        loginScreen.classList.remove('active');
        chatScreen.classList.add('active');
        currentUserName.textContent = currentUser;
        messageInput.focus();
        
        showToast('Đã kết nối thành công! 🎉');
    };
    
    ws.onmessage = (event) => {
        const data = JSON.parse(event.data);
        handleMessage(data);
    };
    
    ws.onerror = (error) => {
        console.error('WebSocket error:', error);
        showToast('Lỗi kết nối! ❌', 'error');
    };
    
    ws.onclose = () => {
        console.log('Disconnected from server');
        showToast('Đã ngắt kết nối khỏi server', 'warning');
        setTimeout(() => {
            loginScreen.classList.add('active');
            chatScreen.classList.remove('active');
        }, 2000);
    };
}

// Handle incoming messages
function handleMessage(data) {
    switch (data.type) {
        case 'welcome':
            addSystemMessage(data.message, data.timestamp);
            break;
        
        case 'history':
            // Load message history
            data.messages.forEach(msg => {
                addMessage(msg.nickname, msg.message, msg.timestamp, msg.nickname === currentUser);
            });
            break;
        
        case 'message':
            addMessage(data.nickname, data.message, data.timestamp, data.nickname === currentUser);
            break;
        
        case 'system':
            addSystemMessage(data.message, data.timestamp);
            break;
        
        case 'users':
            updateUserList(data.users, data.count);
            break;
        
        case 'typing':
            handleTypingIndicator(data.nickname, data.isTyping);
            break;
    }
}

// Add message to chat
function addMessage(sender, message, timestamp, isOwn = false) {
    const messageDiv = document.createElement('div');
    messageDiv.className = `message ${isOwn ? 'own' : ''}`;
    
    const avatar = sender.charAt(0).toUpperCase();
    
    messageDiv.innerHTML = `
        <div class="message-avatar">${avatar}</div>
        <div class="message-content">
            <div class="message-header">
                <span class="message-sender">${sender}</span>
                <span class="message-time">${timestamp}</span>
            </div>
            <div class="message-text">${escapeHtml(message)}</div>
        </div>
    `;
    
    messagesContainer.appendChild(messageDiv);
    scrollToBottom();
}

// Add system message
function addSystemMessage(message, timestamp) {
    const messageDiv = document.createElement('div');
    messageDiv.className = 'message system';
    
    messageDiv.innerHTML = `
        <div class="message-content">
            <div class="message-text">
                ${escapeHtml(message)}
                ${timestamp ? `<span style="margin-left: 8px; opacity: 0.7;">${timestamp}</span>` : ''}
            </div>
        </div>
    `;
    
    messagesContainer.appendChild(messageDiv);
    scrollToBottom();
}

// Update user list
function updateUserList(users, count) {
    userCount.textContent = count;
    userList.innerHTML = users.map(user => {
        const avatar = user.nickname.charAt(0).toUpperCase();
        return `
            <li>
                <div class="user-avatar">${avatar}</div>
                <div class="user-info">
                    <div class="user-name">${escapeHtml(user.nickname)}</div>
                    <div class="user-joined">Tham gia lúc ${user.joined_at}</div>
                </div>
            </li>
        `;
    }).join('');
}

// Send message
messageForm.addEventListener('submit', (e) => {
    e.preventDefault();
    const message = messageInput.value.trim();
    
    if (message && ws && ws.readyState === WebSocket.OPEN) {
        ws.send(JSON.stringify({
            type: 'message',
            message: message
        }));
        
        messageInput.value = '';
        messageInput.focus();
        
        // Stop typing indicator
        sendTypingStatus(false);
    }
});

// Typing indicator
messageInput.addEventListener('input', () => {
    if (!isTyping) {
        isTyping = true;
        sendTypingStatus(true);
    }
    
    clearTimeout(typingTimer);
    typingTimer = setTimeout(() => {
        isTyping = false;
        sendTypingStatus(false);
    }, 1000);
});

function sendTypingStatus(typing) {
    if (ws && ws.readyState === WebSocket.OPEN) {
        ws.send(JSON.stringify({
            type: 'typing',
            isTyping: typing
        }));
    }
}

let typingUsers = new Set();

function handleTypingIndicator(nickname, isTyping) {
    if (isTyping) {
        typingUsers.add(nickname);
    } else {
        typingUsers.delete(nickname);
    }
    
    if (typingUsers.size > 0) {
        const users = Array.from(typingUsers);
        if (users.length === 1) {
            typingIndicator.textContent = `${users[0]} đang nhập...`;
        } else if (users.length === 2) {
            typingIndicator.textContent = `${users[0]} và ${users[1]} đang nhập...`;
        } else {
            typingIndicator.textContent = `${users[0]} và ${users.length - 1} người khác đang nhập...`;
        }
    } else {
        typingIndicator.textContent = '';
    }
}

// Logout
logoutBtn.addEventListener('click', () => {
    if (ws) {
        ws.close();
    }
    currentUser = null;
    messagesContainer.innerHTML = '';
    nicknameInput.value = '';
});

// Utility functions
function scrollToBottom() {
    messagesContainer.scrollTop = messagesContainer.scrollHeight;
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function showToast(message, type = 'success') {
    const toast = document.getElementById('toast');
    toast.textContent = message;
    toast.className = `toast show ${type}`;
    
    setTimeout(() => {
        toast.classList.remove('show');
    }, 3000);
}

// Initialize
initEmojiPicker();

// Auto-focus nickname input on load
nicknameInput.focus();
