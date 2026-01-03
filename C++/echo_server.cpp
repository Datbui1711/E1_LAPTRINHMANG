#include <boost/asio.hpp>
#include <iostream>
#include <array>

using boost::asio::ip::tcp;

int main() {
    try {
        boost::asio::io_context io;

        tcp::acceptor acceptor(io, tcp::endpoint(tcp::v4(), 8888));
        std::cout << "Server listening on 127.0.0.1:8888\n";

        tcp::socket socket(io);
        acceptor.accept(socket); // CHỜ 1 client kết nối
        std::cout << "Client connected: " << socket.remote_endpoint() << "\n";

        std::array<char, 4096> buf{};

        while (true) {
            boost::system::error_code ec;
            std::size_t n = socket.read_some(boost::asio::buffer(buf), ec);

            if (ec == boost::asio::error::eof) {
                std::cout << "Client disconnected.\n";
                break;
            }
            if (ec) throw boost::system::system_error(ec);

            std::string msg(buf.data(), n);
            std::cout << "Client says: " << msg << "\n";

            boost::asio::write(socket, boost::asio::buffer(msg)); // trả lại (echo)
        }

    } catch (const std::exception& e) {
        std::cerr << "Server error: " << e.what() << "\n";
        return 1;
    }
    return 0;
}
