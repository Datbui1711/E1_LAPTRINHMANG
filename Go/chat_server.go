package main

import (
	"bufio"
	"fmt"
	"net"
	"sync"
)

var (
	clients = make(map[net.Conn]bool)
	mutex   sync.Mutex
)

func handleClient(conn net.Conn) {
	defer conn.Close()
	addr := conn.RemoteAddr().String()
	fmt.Println("[Kết nối mới]", addr)

	mutex.Lock()
	clients[conn] = true
	mutex.Unlock()

	scanner := bufio.NewScanner(conn)
	for scanner.Scan() {
		msg := scanner.Text()
		fmt.Printf("%s: %s\n", addr, msg)

		mutex.Lock()
		for c := range clients {
			if c != conn {
				fmt.Fprintf(c, "%s: %s\n", addr, msg)
			}
		}
		mutex.Unlock()
	}

	mutex.Lock()
	delete(clients, conn)
	mutex.Unlock()
	fmt.Println("[Ngắt kết nối]", addr)
}

func main() {
	listener, err := net.Listen("tcp", "127.0.0.1:9999")
	if err != nil {
		panic(err)
	}
	fmt.Println("Chat Server đang chạy tại cổng 9999")

	for {
		conn, err := listener.Accept()
		if err != nil {
			continue
		}
		go handleClient(conn)
	}
}
