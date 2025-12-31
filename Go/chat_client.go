package main

import (
	"bufio"
	"fmt"
	"net"
	"os"
)

func readMessages(conn net.Conn) {
	scanner := bufio.NewScanner(conn)
	for scanner.Scan() {
		fmt.Println(scanner.Text())
	}
}

func main() {
	conn, err := net.Dial("tcp", "127.0.0.1:9999")
	if err != nil {
		panic(err)
	}
	defer conn.Close()

	fmt.Println("Đã kết nối chat server. Gõ 'exit' để thoát.")

	go readMessages(conn)

	input := bufio.NewReader(os.Stdin)
	for {
		msg, _ := input.ReadString('\n')
		if msg == "exit\n" {
			fmt.Println("Ngắt kết nối.")
			return
		}
		conn.Write([]byte(msg))
	}
}
