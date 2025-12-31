package main

import (
	"fmt"
	"io"
	"net"
)

func handleClient(conn net.Conn) {
	defer conn.Close()
	addr := conn.RemoteAddr().String()
	fmt.Println("[Kết nối mới]", addr)

	buf := make([]byte, 1024)
	for {
		n, err := conn.Read(buf)
		if err != nil {
			if err != io.EOF {
				fmt.Println("Lỗi:", err)
			}
			break
		}
		msg := string(buf[:n])
		fmt.Printf("Nhận từ %s: %s\n", addr, msg)

		// Echo lại cho client
		conn.Write(buf[:n])
	}

	fmt.Println("[Ngắt kết nối]", addr)
}

func main() {
	listener, err := net.Listen("tcp", "127.0.0.1:8888")
	if err != nil {
		panic(err)
	}
	fmt.Println("Echo Server đang chạy tại cổng 8888")

	for {
		conn, err := listener.Accept()
		if err != nil {
			continue
		}
		go handleClient(conn) // goroutine = async
	}
}
