const net = require("net");
const readline = require("readline");

const client = new net.Socket();
const rl = readline.createInterface({
  input: process.stdin,
  output: process.stdout
});

client.connect(9000, "127.0.0.1", () => {
  console.log("Đã kết nối tới server. Gõ 'exit' để thoát.");
  askInput();
});

client.on("data", (data) => {
  console.log("Phản hồi từ server:", data.toString().trim());
  askInput();
});

client.on("close", () => {
  console.log("Đã đóng kết nối.");
});

function askInput() {
  rl.question("Bạn: ", (msg) => {
    if (msg.toLowerCase() === "exit") {
      client.destroy();
      rl.close();
      return;
    }
    client.write(msg + "\n");
  });
}
