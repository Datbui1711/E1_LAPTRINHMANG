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
	PORT = "9001"
)

func main() {
	addr := net.JoinHostPort(HOST, PORT)
	conn, err := net.Dial("tcp", addr)
	if err != nil {
		log.Fatalf("Error: %v", err)
	}
	defer conn.Close()

	fmt.Println("Connected. Type message and press Enter. Use /quit to exit.")
	fmt.Print("> ")

	// Reader from server
	go func() {
		r := bufio.NewReader(conn)
		for {
			data, err := r.ReadString('\n')
			if len(data) > 0 {
				// giống JS: process.stdout.write("\n" + data) rồi prompt lại
				fmt.Print("\n" + data)
				fmt.Print("> ")
			}
			if err != nil {
				fmt.Println("\nServer closed connection.")
				os.Exit(0)
			}
		}
	}()

	// Input loop
	in := bufio.NewReader(os.Stdin)
	for {
		line, err := in.ReadString('\n')
		if err != nil {
			return
		}

		msg := strings.TrimRight(line, "\r\n")

		// JS client luôn gửi line + "\n" để server tách message theo dòng
		_, werr := conn.Write([]byte(msg + "\n"))
		if werr != nil {
			fmt.Printf("\nError: %v\n", werr)
			return
		}

		if strings.EqualFold(strings.TrimSpace(msg), "/quit") {
			return
		}

		fmt.Print("> ")
	}
}
