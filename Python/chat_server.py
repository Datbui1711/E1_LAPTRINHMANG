import asyncio

# Tập các client đang kết nối
clients: set[asyncio.StreamWriter] = set()


# Coroutine xử lý từng client
async def handle_client(
    reader: asyncio.StreamReader,
    writer: asyncio.StreamWriter
):
    addr = writer.get_extra_info("peername")
    print(f"[Kết nối mới] {addr}")
    clients.add(writer)

    try:
        while True:
            data = await reader.readline()  # non-blocking read
            if not data:
                break

            message = data.decode().strip()
            print(f"{addr}: {message}")

            # Broadcast tin nhắn cho các client khác
            for client in clients:
                if client is not writer:
                    client.write(f"{addr}: {message}\n".encode())
                    await client.drain()

    except Exception as e:
        print(f"[Lỗi] {addr}: {e}")

    finally:
        print(f"[Ngắt kết nối] {addr}")
        clients.remove(writer)
        writer.close()
        await writer.wait_closed()


async def main():
    # Tạo TCP chat server bất đồng bộ
    server = await asyncio.start_server(
        handle_client,
        host="127.0.0.1",
        port=9999
    )

    addr = server.sockets[0].getsockname()
    print(f"Chat server đang chạy tại {addr}")

    async with server:
        await server.serve_forever()


if __name__ == "__main__":
    # Khuyến nghị cho Windows
    asyncio.set_event_loop_policy(asyncio.WindowsSelectorEventLoopPolicy())
    asyncio.run(main())
