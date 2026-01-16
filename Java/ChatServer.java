import java.io.*;
import java.net.*;
import java.util.*;
import java.util.concurrent.*;

public class ChatServer {
    private static final int PORT = 9001;
    private static final Set<ClientHandler> clients = ConcurrentHashMap.newKeySet();
    
    public static void main(String[] args) {
        System.out.println("Server chat dang lang nghe tren cong " + PORT);
        
        try (ServerSocket serverSocket = new ServerSocket(PORT)) {
            while (true) {
                Socket clientSocket = serverSocket.accept();
                ClientHandler clientHandler = new ClientHandler(clientSocket);
                clients.add(clientHandler);
                new Thread(clientHandler).start();
            }
        } catch (IOException e) {
            System.err.println("Loi server: " + e.getMessage());
        }
    }
    
    // Phát tin nhắn tới tất cả client (trừ người gửi)
    public static void broadcast(String message, ClientHandler sender) {
        Iterator<ClientHandler> iterator = clients.iterator();
        while (iterator.hasNext()) {
            ClientHandler client = iterator.next();
            if (client != sender && !client.sendMessage(message)) {
                iterator.remove();
            }
        }
    }
    
    public static void removeClient(ClientHandler client) {
        clients.remove(client);
    }
}

class ClientHandler implements Runnable {
    private Socket socket;
    private PrintWriter out;
    private BufferedReader in;
    private String clientName;
    
    public ClientHandler(Socket socket) {
        this.socket = socket;
    }
    
    @Override
    public void run() {
        try {
            InetSocketAddress addr = (InetSocketAddress) socket.getRemoteSocketAddress();
            clientName = addr.getAddress().getHostAddress() + ":" + addr.getPort();
            
            out = new PrintWriter(socket.getOutputStream(), true);
            in = new BufferedReader(new InputStreamReader(socket.getInputStream()));
            
            // Gửi lời chào mừng
            out.println("Chao mung " + clientName + "! Nhap tin nhan va nhan ENTER.");
            ChatServer.broadcast("[+] " + clientName + " da tham gia", null);
            
            String message;
            while ((message = in.readLine()) != null) {
                message = message.trim();
                if (message.equalsIgnoreCase("/quit")) {
                    break;
                }
                ChatServer.broadcast("[" + clientName + "] " + message, this);
            }
        } catch (IOException e) {
            System.out.println("[!] " + clientName + " loi: " + e.getMessage());
        } finally {
            cleanup();
        }
    }
    
    public boolean sendMessage(String message) {
        try {
            out.println(message);
            return !out.checkError();
        } catch (Exception e) {
            return false;
        }
    }
    
    private void cleanup() {
        ChatServer.removeClient(this);
        ChatServer.broadcast("[-] " + clientName + " da thoat ra", null);
        try {
            if (in != null) in.close();
            if (out != null) out.close();
            if (socket != null) socket.close();
        } catch (IOException e) {
            System.err.println("Loi dong ket noi: " + e.getMessage());
        }
    }
}
