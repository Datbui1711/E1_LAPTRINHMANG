package main

import (
    "fmt"
    "net"
)

func main() {
    ln, err := net.Listen("tcp", "127.0.0.1:9000")
    if err != nil {
        panic(err)
    }
    fmt.Println("Server running on 127.0.0.1:9000")

    for {
        conn, err := ln.Accept()
        if err != nil {
            fmt.Println("Accept error:", err)
            continue
        }

        go func(c net.Conn) {
            buffer := make([]byte, 4096)
            n, err := c.Read(buffer)
            if err == nil && n > 0 {
                fmt.Println("Client says:", string(buffer[:n]))
                c.Write([]byte("Hello from server\n"))
            }
            c.Close()
        }(conn)
    }
}
