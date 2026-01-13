package main

import (
	"bufio"
	"log"
	"net"
	"strings"
)

const (
	HOST = "127.0.0.1"
	PORT = "8888"
)

func main() {
	addr := net.JoinHostPort(HOST, PORT)

	ln, err := net.Listen("tcp", addr)
	if err != nil {
		log.Fatalf("Server error: %v", err)
	}
	defer ln.Close()

	log.Printf("Server đang chạy tại %s\n", addr)

	for {
		conn, err := ln.Accept()
		if err != nil {
			log.Printf("Accept error: %v\n", err)
			continue
		}
		go handleConn(conn)
	}
}

func handleConn(conn net.Conn) {
	defer conn.Close()

	remote := conn.RemoteAddr().String()
	log.Printf("Kết nối mới: %s\n", remote)

	reader := bufio.NewReader(conn)
	buf := make([]byte, 4096)

	for {
		n, err := reader.Read(buf)
		if n > 0 {
			data := buf[:n]
			msg := strings.TrimSpace(string(data))
			log.Printf("Nhận từ %s: %s\n", remote, msg)

			_, werr := conn.Write(data)
			if werr != nil {
				log.Printf("Lỗi ghi: %v\n", werr)
				return
			}
		}
		if err != nil {
			log.Printf("Ngắt kết nối: %s\n", remote)
			return
		}
	}
}
