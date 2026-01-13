package main

import (
	"bufio"
	"fmt"
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
		log.Fatalf("[Server error] %v", err)
	}
	defer ln.Close()

	log.Printf("Server đang chạy tại %s\n", addr)

	for {
		conn, err := ln.Accept()
		if err != nil {
			log.Printf("[Accept error] %v\n", err)
			continue
		}
		go handleConn(conn)
	}
}

func handleConn(conn net.Conn) {
	defer conn.Close()

	remote := conn.RemoteAddr().String()
	log.Printf("[Kết nối mới] %s\n", remote)

	// Giống bản JS: nhận bytes -> log -> echo lại đúng bytes nhận được
	reader := bufio.NewReader(conn)
	buf := make([]byte, 4096)

	for {
		n, err := reader.Read(buf)
		if n > 0 {
			chunk := buf[:n]
			msg := strings.TrimSpace(string(chunk))
			if msg != "" {
				log.Printf("Nhận từ %s: %s\n", remote, msg)
			} else {
				log.Printf("Nhận từ %s: (em
