#include <boost/asio.hpp>
#include <iostream>
#include <string>
#include <array>

using boost::asio::ip::tcp;

int main() {
    try {
        boost::asio::io_context io;

        tcp::socket socket(io);
        socket.connect(tcp::endpoint(boost::asio::ip::make_address("127.0.0.1"), 8888));

        std::cout << "Connected to server. Type message (exit to quit)\n";

        while (true) {
            std::string msg;
            std::getline(std::cin, msg);
            if (msg == "exit") break;

            boost::asio::write(socket, boost::asio::buffer(msg));

            std::array<char, 4096> buf{};
            boost::system::error_code ec;
            std::size_t n = socket.read_some(boost::asio::buffer(buf), ec);
            if (ec) throw boost::system::system_error(ec);

            std::cout << "Server echo: " << std::string(buf.data(), n) << "\n";
        }

        socket.close();
    } catch (const std::exception& e) {
        std::cerr << "Client error: " << e.what() << "\n";
        return 1;
    }
    return 0;
}
