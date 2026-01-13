// echo_server.js
const net = require("net");

const HOST = "127.0.0.1";
const PORT = 8888;

const server = net.createServer((socket) => {
  const addr = `${socket.remoteAddress}:${socket.remotePort}`;
  console.log(`[Kết nối mới] ${addr}`);

  // Echo lại đúng dữ liệu nhận được
  socket.on("data", (chunk) => {
    const message = chunk.toString("utf8").trim();
    console.log(`Nhận từ ${addr}: ${message}`);
    socket.write(chunk); // echo
  });

  socket.on("end", () => {
    console.log(`[Ngắt kết nối] ${addr}`);
  });

  socket.on("error", (err) => {
    console.log(`[Lỗi] ${addr}: ${err.message}`);
  });
});

server.on("error", (err) => {
  console.error(`[Server error] ${err.message}`);
});

server.listen(PORT, HOST, () => {
  console.log(`Server đang chạy tại ${HOST}:${PORT}`);
});
