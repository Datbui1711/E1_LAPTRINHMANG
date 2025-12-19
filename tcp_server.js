const net = require("net");

const server = net.createServer((socket) => {
  const addr = socket.remoteAddress + ":" + socket.remotePort;
  console.log("[Kết nối mới]", addr);

  socket.on("data", (data) => {
    const msg = data.toString().trim();
    console.log(`${addr}: ${msg}`);

    // echo lại cho client
    socket.write("Server nhận: " + msg + "\n");
  });

  socket.on("close", () => {
    console.log("[Ngắt kết nối]", addr);
  });
});

// Server listen port 9000
server.listen(9000, "127.0.0.1", () => {
  console.log("TCP Server đang chạy tại 127.0.0.1:9000");
});
