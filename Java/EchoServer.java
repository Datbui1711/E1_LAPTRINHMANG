import java.io.*;
import java.net.*;

public class EchoServer {
    private static final String HOST = "127.0.0.1";
    private static final int PORT = 8888;
    
    public static void main(String[] args) {
        try (ServerSocket serverSocket = new ServerSocket(PORT)) {
            System.out.println("Server dang chay tai " + HOST + ":" + PORT);
            
            while (true) {
                Socket clientSocket = serverSocket.accept();
                new Thread(new ClientHandler(clientSocket)).start();
            }
        } catch (IOException e) {
            System.err.println("Loi: " + e.getMessage());
        }
    }
    
    static class ClientHandler implements Runnable {
        private Socket socket;
        
        public ClientHandler(Socket socket) {
            this.socket = socket;
        }
        
        @Override
        public void run() {
            InetSocketAddress addr = (InetSocketAddress) socket.getRemoteSocketAddress();
            System.out.println("[Ket noi moi] " + addr);
            
            try (
                BufferedReader in = new BufferedReader(
                    new InputStreamReader(socket.getInputStream())
                );
                PrintWriter out = new PrintWriter(socket.getOutputStream(), true)
            ) {
                char[] buffer = new char[100];
                int bytesRead;
                while ((bytesRead = in.read(buffer, 0, buffer.length)) != -1) {
                    String message = new String(buffer, 0, bytesRead).trim();
                    System.out.println("Nhan tu " + addr + ": " + message);
                    // Echo lại tin nhắn
                    out.print(new String(buffer, 0, bytesRead));
                    out.flush();
                }
            } catch (IOException e) {
                System.err.println("Loi: " + e.getMessage());
            } finally {
                System.out.println("[Ngat ket noi] " + addr);
                try {
                    socket.close();
                } catch (IOException e) {
                    e.printStackTrace();
                }
            }
        }
    }
}
