#include <boost/asio.hpp>
#include <deque>
#include <iostream>
#include <string>
#include <thread>

using boost::asio::ip::tcp;

class Client {
public:
    Client(boost::asio::io_context& io, std::string host, unsigned short port)
        : io_(io), socket_(io), resolver_(io),
          host_(std::move(host)), port_(port) {}

    void start() {
        auto eps = resolver_.resolve(host_, std::to_string(port_));
        boost::asio::async_connect(socket_, eps,
            [this](const boost::system::error_code& ec, const tcp::endpoint&) {
                if (ec) {
                    std::cerr << "Lỗi kết nối: " << ec.message() << "\n";
                    return;
                }
                do_read_line();
            });
    }

    void send_line(std::string line) {
        if (line.empty() || line.back() != '\n') line.push_back('\n');

        boost::asio::post(io_, [this, line = std::move(line)]() mutable {
            if (!socket_.is_open()) return;
            bool writing = !outbox_.empty();
            outbox_.push_back(std::move(line));
            if (!writing) do_write();
        });
    }

    void close() {
        boost::asio::post(io_, [this]() {
            boost::system::error_code ignored;
            socket_.shutdown(tcp::socket::shutdown_both, ignored);
            socket_.close(ignored);
        });
    }

private:
    void do_read_line() {
        boost::asio::async_read_until(socket_, buffer_, '\n',
            [this](const boost::system::error_code& ec, std::size_t /*n*/) {
                if (ec) {
                    if (ec != boost::asio::error::operation_aborted)
                        std::cout << "Đã ngắt kết nối.\n";
                    return;
                }
                std::istream is(&buffer_);
                std::string line;
                std::getline(is, line);
                if (!line.empty() && line.back() == '\r') line.pop_back();
                std::cout << line << "\n";
                do_read_line();
            });
    }

    void do_write() {
        boost::asio::async_write(socket_, boost::asio::buffer(outbox_.front()),
            [this](const boost::system::error_code& ec, std::size_t /*n*/) {
                if (ec) {
                    std::cerr << "Lỗi gửi dữ liệu: " << ec.message() << "\n";
                    close();
                    return;
                }
                outbox_.pop_front();
                if (!outbox_.empty()) do_write();
            });
    }

private:
    boost::asio::io_context& io_;
    tcp::socket socket_;
    tcp::resolver resolver_;
    boost::asio::streambuf buffer_;
    std::deque<std::string> outbox_;
    std::string host_;
    unsigned short port_;
};

int main(int argc, char* argv[]) {
    std::string host = "127.0.0.1";
    unsigned short port = 9001;
    if (argc >= 2) host = argv[1];
    if (argc >= 3) port = static_cast<unsigned short>(std::stoi(argv[2]));

    boost::asio::io_context io;
    Client client(io, host, port);
    client.start();

    std::thread input_thread([&]() {
        std::string line;
        while (std::getline(std::cin, line)) {
            client.send_line(line);
            if (line == "/quit") {
                client.close();
                break;
            }
        }
    });

    io.run();
    if (input_thread.joinable()) input_thread.join();
    return 0;
}
