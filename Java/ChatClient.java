import java.io.*;
import java.net.*;
import java.util.Scanner;

public class ChatClient {
    private static final String HOST = "127.0.0.1";
    private static final int PORT = 9001;
    
    public static void main(String[] args) {
        try (Socket socket = new Socket(HOST, PORT)) {
            BufferedReader in = new BufferedReader(
                new InputStreamReader(socket.getInputStream())
            );
            PrintWriter out = new PrintWriter(socket.getOutputStream(), true);
            
            // Tạo thread riêng để đọc tin nhắn từ server
            Thread readerThread = new Thread(new ServerReader(in));
            readerThread.start();
            
            // Thread chính để gửi tin nhắn
            Scanner scanner = new Scanner(System.in);
            String line;
            while (scanner.hasNextLine()) {
                line = scanner.nextLine();
                out.println(line);
                if (line.trim().equalsIgnoreCase("/quit")) {
                    break;
                }
            }
            
            readerThread.join(1000);
            scanner.close();
        } catch (IOException | InterruptedException e) {
            System.err.println("Loi client: " + e.getMessage());
        }
    }
}

class ServerReader implements Runnable {
    private BufferedReader in;
    
    public ServerReader(BufferedReader in) {
        this.in = in;
    }
    
    @Override
    public void run() {
        try {
            String message;
            while ((message = in.readLine()) != null) {
                System.out.println(message);
            }
            System.out.println("Server da dong ket noi.");
        } catch (IOException e) {
            System.err.println("Mat ket noi: " + e.getMessage());
        }
    }
}
