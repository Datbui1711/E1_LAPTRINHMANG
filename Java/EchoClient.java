import java.io.*;
import java.net.*;
import java.util.Scanner;

public class EchoClient {
    private static final String HOST = "127.0.0.1";
    private static final int PORT = 8888;
    
    public static void main(String[] args) {
        try (Socket socket = new Socket(HOST, PORT)) {
            System.out.println("Ket noi đen server thanh cong! Nhap 'exit' de thoat.");
            
            BufferedReader in = new BufferedReader(
                new InputStreamReader(socket.getInputStream())
            );
            PrintWriter out = new PrintWriter(socket.getOutputStream(), true);
            Scanner scanner = new Scanner(System.in);
            
            while (true) {
                System.out.print("Nhap tin nhan: ");
                String message = scanner.nextLine();
                
                if (message.equalsIgnoreCase("exit")) {
                    break;
                }
                
                out.print(message);
                out.flush();
                
                // Đọc phản hồi từ server
                char[] buffer = new char[100];
                int bytesRead = in.read(buffer, 0, buffer.length);
                if (bytesRead != -1) {
                    String response = new String(buffer, 0, bytesRead);
                    System.out.println("Phan hoi tu server: " + response);
                }
            }
            
            System.out.println("Dong ket noi.");
            scanner.close();
        } catch (IOException e) {
            System.err.println("Loi: " + e.getMessage());
        }
    }
}
