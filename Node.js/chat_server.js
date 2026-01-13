const net = require("net");

const HOST = "0.0.0.0";
const PORT = 9001;

const clients = new Set(); // lưu socket các client

function broadcast(message, exceptSocket = null) {
  const data = message.endsWith("\n") ? message : message + "\n";
  for (const client of clients) {
    if (client === exceptSocket) continue;
    if (!client.destroyed) client.write(data);
  }
}

const server = net.createServer((socket) => {
  socket.setEncoding("utf8");

  const name = `${socket.remoteAddress}:${socket.remotePort}`;
  clients.add(socket);

  console.log(`[+] ${name} joined (${clients.size} clients)`);
  socket.write(`Welcome ${name}! Type message. Use /quit to exit.\n`);
  broadcast(`[+] ${name} joined`, socket);

  let buffer = "";

  socket.on("data", (chunk) => {
    buffer += chunk;

    // xử lý theo dòng (newline framing)
    let idx;
    while ((idx = buffer.indexOf("\n")) !== -1) {
      const line = buffer.slice(0, idx).replace(/\r$/, "");
      buffer = buffer.slice(idx + 1);

      const msg = line.trim();
      if (!msg) continue;

      if (msg.toLowerCase() === "/quit") {
        socket.end("Bye!\n");
        return;
      }

      console.log(`[${name}] ${msg}`);
      broadcast(`[${name}] ${msg}`, socket);
    }
  });

  socket.on("end", () => {
    clients.delete(socket);
    console.log(`[-] ${name} left (${clients.size} clients)`);
    broadcast(`[-] ${name} left`, null);
  });

  socket.on("error", (err) => {
    clients.delete(socket);
    console.log(`[!] ${name} error: ${err.message}`);
  });
});

server.on("error", (err) => {
  console.error(`[Server error] ${err.message}`);
});

server.listen(PORT, HOST, () => {
  console.log(`Chat server listening on ${HOST}:${PORT}`);
});
