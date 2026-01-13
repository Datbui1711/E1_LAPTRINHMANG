const net = require("net");
const readline = require("readline");

const HOST = "127.0.0.1";
const PORT = 9001;

const rl = readline.createInterface({
  input: process.stdin,
  output: process.stdout,
});

const socket = net.createConnection({ host: HOST, port: PORT }, () => {
  console.log("Connected. Type message and press Enter. Use /quit to exit.");
  rl.setPrompt("> ");
  rl.prompt();
});

socket.setEncoding("utf8");

socket.on("data", (data) => {
  process.stdout.write("\n" + data);
  rl.prompt();
});

socket.on("end", () => {
  console.log("\nServer closed connection.");
  rl.close();
});

socket.on("error", (err) => {
  console.log(`\nError: ${err.message}`);
  rl.close();
});

rl.on("line", (line) => {
  socket.write(line + "\n"); // newline để server tách message theo dòng
  if (line.trim().toLowerCase() === "/quit") {
    rl.close();
  } else {
    rl.prompt();
  }
});

rl.on("close", () => {
  socket.end();
  process.exit(0);
});
