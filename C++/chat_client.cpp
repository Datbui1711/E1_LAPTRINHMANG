#include <boost/asio.hpp>
#include <iostream>
#include <thread>
#include <atomic>

using boost::asio::ip::tcp;

int main() {
    try {
        boost::asio::io_context io;
        tcp::socket socket(io);

        socket.connect(tcp::endpoint(boost::asio::ip::make_address("127.0.0.1"), 9999));
        std::cout << "Connected. Type messages (exit to quit)\n";

        std::atomic<bool> running{true};

        // Thread đọc từ server (non-blocking kiểu async)
        std::thread reader([&]() {
            try {
                boost::asio::streambuf buf;
                while (running) {
                    boost::system::error_code ec;
                    std::size_t n = boost::asio::read_until(socket, buf, '\n', ec);
                    if (ec) break;

                    std::istream is(&buf);
                    std::string line;
                    std::getline(is, line);
                    std::cout << line << "\n";
                }
            } catch (...) {}
            running = false;
        });

        // Main thread: nhập từ bàn phím và gửi
        std::string line;
        while (running && std::getline(std::cin, line)) {
            if (line == "exit") break;
            line += "\n";
            boost::asio::write(socket, boost::asio::buffer(line));
        }

        running = false;
        boost::system::error_code ignored;
        socket.close(ignored);

        if (reader.joinable()) reader.join();

    } catch (const std::exception& e) {
        std::cerr << "Client error: " << e.what() << "\n";
        return 1;
    }
    return 0;
}
