# chat_client_async.py
import asyncio

async def read_from_server(reader: asyncio.StreamReader):
    while True:
        data = await reader.readline()
        if not data:
            print("Server closed.")
            return
        print(data.decode(errors="ignore").rstrip("\n"))

async def write_to_server(writer: asyncio.StreamWriter):
    try:
        while True:
            line = await asyncio.to_thread(input, "")
            writer.write((line + "\n").encode())
            await writer.drain()
            if line.strip().lower() == "/quit":
                return
    finally:
        writer.close()
        await writer.wait_closed()

async def main(host="127.0.0.1", port=9001):
    reader, writer = await asyncio.open_connection(host, port)
    await asyncio.gather(
        read_from_server(reader),
        write_to_server(writer),
    )

if __name__ == "__main__":
    asyncio.run(main())
