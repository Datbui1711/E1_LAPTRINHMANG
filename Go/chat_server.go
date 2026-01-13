package main

import (
	"bufio"
	"fmt"
	"log"
	"net"
	"strings"
	"sync"
)

const (
	HOST = "0.0.0.0"
	PORT = "9001"
)

type Client struct {
	conn net.Conn
	name string
	out  chan string
}

var (
	mu      sync.Mutex
	clients = make(map[net.Conn]*Client)
)

func main() {
	addr := net.JoinHostPort(HOST, PORT)
	ln, err := net.Listen("tcp", addr)
	if err != nil {
		log.Fatalf("[Server error] %v", err)
	}
	defer ln.Close()

	log.Printf("Chat server listening on %s\n", addr)

	for {
		conn, err := ln.Accept()
		if err != nil {
			log.Printf("[Accept error] %v\n", err)
			continue
		}
		go handleClient(conn)
	}
}

func handleClient(conn net.Conn) {
	name := conn.RemoteAddr().String()

	c := &Client{
		conn: conn,
		name: name,
		out:  make(chan string, 32),
	}

	mu.Lock()
	clients[conn] = c
	count := len(clients)
	mu.Unlock()

	log.Printf("[+] %s joined (%d clients)\n", name, count)

	// Writer goroutine
	go func() {
		for msg := range c.out {
			_, _ = conn.Write([]byte(ensureNL(msg)))
		}
	}()

	// Welcome + broadcast join
	c.out <- fmt.Sprintf("Welcome %s! Type message. Use /quit to exit.", name)
	broadcast(fmt.Sprintf("[+] %s joined", name), conn)

	reader := bufio.NewReader(conn)
	for {
		line, err := reader.ReadString('\n') // newline framing như JS
		if err != nil {
			break
		}

		line = strings.TrimRight(line, "\r\n")
		msg := strings.TrimSpace(line)
		if msg == "" {
			continue
		}

		if strings.EqualFold(msg, "/quit") {
			// giống JS: socket.end("Bye!\n")
			c.out <- "Bye!"
			break
		}

		log.Printf("[%s] %s\n", name, msg)
		broadcast(fmt.Sprintf("[%s] %s", name, msg), conn)
	}

	// Cleanup
	mu.Lock()
	delete(clients, conn)
	count = len(clients)
	mu.Unlock()

	log.Printf("[-] %s left (%d clients)\n", name, count)
	broadcast(fmt.Sprintf("[-] %s left", name), nil)

	close(c.out)
	_ = conn.Close()
}

func broadcast(message string, except net.Conn) {
	data := ensureNL(message)
	mu.Lock()
	defer mu.Unlock()
	for conn, c := range clients {
		if except != nil && conn == except {
			continue
		}
		select {
		case c.out <- data:
		default:
			// nếu client chậm, bỏ qua để server không bị kẹt
		}
	}
}

func ensureNL(s string) string {
	if strings.HasSuffix(s, "\n") {
		return s
	}
	return s + "\n"
}
