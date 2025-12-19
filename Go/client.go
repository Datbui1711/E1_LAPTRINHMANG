package main

import (
    "fmt"
    "net"
)

func main() {
    conn, err := net.Dial("tcp", "127.0.0.1:9000")
    if err != nil {
        panic(err)
    }
    defer conn.Close()

    // Gửi dữ liệu
    conn.Write([]byte("Hello optimized TCP client"))

    // Nhận phản hồi
    buffer := make([]byte, 4096)
    n, _ := conn.Read(buffer)

    fmt.Println("Server responded:", string(buffer[:n]))
}
