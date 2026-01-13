// echo_client.js
const net = require("net");
const readline = require("readline");

const HOST = "127.0.0.1";
const PORT = 8888;

const rl = readline.createInterface({
  input: process.stdin,
  output: process.stdout,
});

const socket = net.createConnection({ host: HOST, port: PORT }, () => {
  console.log("Kết nối đến server thành công! Nhập 'exit' để thoát.");
  prompt();
});

socket.on("data", (chunk) => {
  console.log(`Phản hồi từ server: ${chunk.toString("utf8").trim()}`);
  prompt();
});

socket.on("end", () => {
  console.log("Server đã đóng kết nối.");
  rl.close();
});

socket.on("error", (err) => {
  console.log(`Lỗi: ${err.message}`);
  rl.close();
});

function prompt() {
  rl.question("Nhập tin nhắn: ", (msg) => {
    if (msg.trim().toLowerCase() === "exit") {
      console.log("Đóng kết nối.");
      socket.end();
      rl.close();
      return;
    }
    socket.write(msg);
  });
}
