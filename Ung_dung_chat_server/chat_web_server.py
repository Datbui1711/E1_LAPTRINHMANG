import asyncio
import logging
from datetime import datetime
from aiohttp import web
import aiohttp
import json

# Cấu hình logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(levelname)s - %(message)s'
)
logger = logging.getLogger(__name__)


class WebChatServer:
    def __init__(self):
        self.clients = {}  # {ws: {'nickname': str, 'joined_at': datetime}}
        self.message_history = []  # Lưu 50 tin nhắn gần nhất
        self.max_history = 50
    
    async def handle_websocket(self, request):
        """Xử lý WebSocket connection"""
        ws = web.WebSocketResponse()
        await ws.prepare(request)
        
        nickname = None
        logger.info(f"WebSocket connection từ {request.remote}")
        
        try:
            # Chờ client gửi nickname
            async for msg in ws:
                if msg.type == aiohttp.WSMsgType.TEXT:
                    data = json.loads(msg.data)
                    
                    if data['type'] == 'join':
                        nickname = data['nickname'].strip()
                        if not nickname:
                            nickname = f"User_{request.remote}"
                        
                        # Thêm client vào danh sách
                        self.clients[ws] = {
                            'nickname': nickname,
                            'joined_at': datetime.now()
                        }
                        
                        logger.info(f"User {nickname} đã tham gia")
                        
                        # Gửi message history cho user mới
                        await ws.send_json({
                            'type': 'history',
                            'messages': self.message_history
                        })
                        
                        # Gửi danh sách users
                        await self.broadcast_user_list()
                        
                        # Thông báo user mới tham gia
                        await self.broadcast_message({
                            'type': 'system',
                            'message': f'{nickname} đã tham gia chat',
                            'timestamp': datetime.now().strftime('%H:%M:%S')
                        }, exclude_ws=ws)
                        
                        # Gửi welcome message cho user mới
                        await ws.send_json({
                            'type': 'welcome',
                            'message': f'Chào mừng {nickname}! Hiện có {len(self.clients)} người trong chat.',
                            'timestamp': datetime.now().strftime('%H:%M:%S')
                        })
                    
                    elif data['type'] == 'message':
                        if nickname:
                            message_data = {
                                'type': 'message',
                                'nickname': nickname,
                                'message': data['message'],
                                'timestamp': datetime.now().strftime('%H:%M:%S')
                            }
                            
                            # Lưu vào history
                            self.message_history.append(message_data)
                            if len(self.message_history) > self.max_history:
                                self.message_history.pop(0)
                            
                            # Broadcast tin nhắn
                            await self.broadcast_message(message_data)
                            logger.info(f"{nickname}: {data['message']}")
                    
                    elif data['type'] == 'typing':
                        if nickname:
                            await self.broadcast_message({
                                'type': 'typing',
                                'nickname': nickname,
                                'isTyping': data.get('isTyping', False)
                            }, exclude_ws=ws)
                
                elif msg.type == aiohttp.WSMsgType.ERROR:
                    logger.error(f'WebSocket error: {ws.exception()}')
                    break
        
        except Exception as e:
            logger.error(f"Lỗi xử lý WebSocket: {e}")
        
        finally:
            # Xóa client và thông báo
            if ws in self.clients:
                nickname = self.clients[ws]['nickname']
                del self.clients[ws]
                
                await self.broadcast_message({
                    'type': 'system',
                    'message': f'{nickname} đã rời khỏi chat',
                    'timestamp': datetime.now().strftime('%H:%M:%S')
                })
                
                await self.broadcast_user_list()
                logger.info(f"User {nickname} đã ngắt kết nối")
        
        return ws
    
    async def broadcast_message(self, message, exclude_ws=None):
        """Gửi tin nhắn đến tất cả client"""
        disconnected = []
        for ws in self.clients:
            if ws != exclude_ws:
                try:
                    await ws.send_json(message)
                except Exception as e:
                    logger.error(f"Lỗi gửi tin nhắn: {e}")
                    disconnected.append(ws)
        
        # Xóa các connection bị lỗi
        for ws in disconnected:
            if ws in self.clients:
                del self.clients[ws]
    
    async def broadcast_user_list(self):
        """Gửi danh sách users đến tất cả client"""
        users = [
            {
                'nickname': info['nickname'],
                'joined_at': info['joined_at'].strftime('%H:%M:%S')
            }
            for info in self.clients.values()
        ]
        
        await self.broadcast_message({
            'type': 'users',
            'users': users,
            'count': len(users)
        })
    
    async def handle_index(self, request):
        """Serve trang HTML chính"""
        return web.FileResponse('./static/index.html')


async def init_app():
    """Khởi tạo web application"""
    chat_server = WebChatServer()
    
    app = web.Application()
    
    # Routes
    app.router.add_get('/', chat_server.handle_index)
    app.router.add_get('/ws', chat_server.handle_websocket)
    app.router.add_static('/static/', path='./static', name='static')
    
    return app


def main():
    """Chạy server"""
    logger.info("🚀 Khởi động Web Chat Server...")
    app = init_app()
    web.run_app(app, host='127.0.0.1', port=8080)


if __name__ == '__main__':
    main()
