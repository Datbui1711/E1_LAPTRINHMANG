import asyncio

# Coroutine: đọc tin nhắn từ server (bất đồng bộ)
async def read_messages(reader: asyncio.StreamReader):
    try:
        while True:
            data = await reader.readline()  # non-blocking I/O
            if not data:
                print("Server đã đóng kết nối.")
                break
            print(data.decode().strip())
    except asyncio.CancelledError:
        pass


# Coroutine: gửi tin nhắn người dùng nhập lên server
async def send_messages(writer: asyncio.StreamWriter):
    try:
        while True:
            msg = input("Bạn: ")  # blocking input (chấp nhận trong client)
            writer.write((msg + "\n").encode())
            await writer.drain()  # đảm bảo dữ liệu được gửi

            if msg.lower() == "exit":
                print("Ngắt kết nối.")
                break
    finally:
        writer.close()
        await writer.wait_closed()


async def main():
    # Kết nối TCP bất đồng bộ tới chat server
    reader, writer = await asyncio.open_connection("127.0.0.1", 9999)
    print("Đã kết nối tới chat server. Gõ 'exit' để thoát.")

    # Chạy song song 2 coroutine: đọc & gửi
    await asyncio.gather(
        read_messages(reader),
        send_messages(writer)
    )


if __name__ == "__main__":
    # Khuyến nghị cho Windows (tránh lỗi asyncio trên Python 3.12)
    asyncio.set_event_loop_policy(asyncio.WindowsSelectorEventLoopPolicy())
    asyncio.run(main())