# chat_server_async.py
import asyncio

clients: set[asyncio.StreamWriter] = set()
clients_lock = asyncio.Lock()

async def broadcast(message: str, sender: asyncio.StreamWriter | None = None):
    data = (message + "\n").encode()
    async with clients_lock:
        dead = []
        for w in clients:
            if w is sender:
                continue
            try:
                w.write(data)
                await w.drain()
            except Exception:
                dead.append(w)
        for w in dead:
            clients.discard(w)

async def handle_client(reader: asyncio.StreamReader, writer: asyncio.StreamWriter):
    addr = writer.get_extra_info("peername")
    name = f"{addr[0]}:{addr[1]}"
    async with clients_lock:
        clients.add(writer)

    writer.write(f"Welcome {name}! Type and press ENTER.\n".encode())
    await writer.drain()
    await broadcast(f"[+] {name} joined", sender=None)

    try:
        while True:
            line = await reader.readline()
            if not line:
                break
            msg = line.decode(errors="ignore").rstrip("\n")
            if msg.strip().lower() == "/quit":
                break
            await broadcast(f"[{name}] {msg}", sender=writer)
    except asyncio.CancelledError:
        raise
    except Exception as e:
        print(f"[!] {name} error: {e}")
    finally:
        async with clients_lock:
            clients.discard(writer)
        await broadcast(f"[-] {name} left", sender=None)
        writer.close()
        await writer.wait_closed()

async def main(host="0.0.0.0", port=9001):
    server = await asyncio.start_server(handle_client, host, port)
    addrs = ", ".join(str(sock.getsockname()) for sock in server.sockets)
    print(f"Chat server listening on {addrs}")
    async with server:
        await server.serve_forever()

if __name__ == "__main__":
    asyncio.run(main())
