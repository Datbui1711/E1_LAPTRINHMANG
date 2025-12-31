package main

import (
	"bufio"
	"fmt"
	"net"
	"os"
)

func main() {
	conn, err := net.Dial("tcp", "127.0.0.1:8888")
	if err != nil {
		panic(err)
	}
	defer conn.Close()

	fmt.Println("Kết nối server thành công! Gõ 'exit' để thoát.")
	reader := bufio.NewReader(os.Stdin)
	serverReader := bufio.NewReader(conn)

	for {
		fmt.Print("Nhập tin nhắn: ")
		msg, _ := reader.ReadString('\n')

		if msg == "exit\n" {
			break
		}

		conn.Write([]byte(msg))

		reply, _ := serverReader.ReadString('\n')
		fmt.Println("Phản hồi từ server:", reply)
	}

	fmt.Println("Đóng kết nối.")
}
