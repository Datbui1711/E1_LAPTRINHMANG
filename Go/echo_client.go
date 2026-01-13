package main

import (
	"bufio"
	"fmt"
	"log"
	"net"
	"os"
	"strings"
)

const (
	HOST = "127.0.0.1"
	PORT = "8888"
)

func main() {
	addr := net.JoinHostPort(HOST, PORT)
	conn, err := net.Dial("tcp", addr)
	if err != nil {
		log.Fatalf("Không kết nối được: %v", err)
	}
	defer conn.Close()

	fmt.Println("Kết nối đến server thành công! Nhập 'exit' để thoát.")

	in := bufio.NewReader(os.Stdin)
	serverReader := bufio.NewReader(conn)

	for {
		fmt.Print("Nhập tin nhắn: ")
		line, err := in.ReadString('\n')
		if err != nil {
			fmt.Printf("Lỗi đọc input: %v\n", err)
			return
		}
		msg := strings.TrimRight(line, "\r\n")
		if strings.EqualFold(strings.TrimSpace(msg), "exit") {
			fmt.Println("Đóng kết nối.")
			return
		}

		// JS echo_client viết msg (không kèm newline). Ta giữ giống.
		if _, err := conn.Write([]byte(msg)); err != nil {
			fmt.Printf("Lỗi gửi: %v\n", err)
			return
		}

		// Đọc phản hồi (echo). Vì server echo raw bytes, có thể không có newline.
		// Ta đọc "tạm" theo buffer.
		buf := make([]byte, 4096)
		n, err := serverReader.Read(buf)
		if n > 0 {
			fmt.Printf("Phản hồi từ server: %s\n", strings.TrimSpace(string(buf[:n])))
		}
		if err != nil {
			fmt.Printf("Server đã đóng kết nối (%v)\n", err)
			return
		}
	}
}
