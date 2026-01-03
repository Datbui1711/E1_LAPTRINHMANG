#include <boost/asio.hpp>
#include <iostream>
#include <memory>
#include <set>
#include <string>

using boost::asio::ip::tcp;

struct Session : std::enable_shared_from_this<Session> {
    tcp::socket socket;
    boost::asio::streambuf buffer;

    // Con trỏ tới tập clients chung (giống biến global `clients` trong Python)
    std::set<std::shared_ptr<Session>>* clients = nullptr;

    explicit Session(tcp::socket s, std::set<std::shared_ptr<Session>>* c)
        : socket(std::move(s)), clients(c) {}

    std::string peer() {
        try {
            auto ep = socket.remote_endpoint();
            return ep.address().to_string() + ":" + std::to_string(ep.port());
        } catch (...) {
            return "unknown";
        }
    }

    void start() {
        std::cout << "[Kết nối mới] " << peer() << "\n";
        do_read_line();
    }

    void do_read_line() {
        auto self = shared_from_this();

        boost::asio::async_read_until(
            socket,
            buffer,
            '\n',
            [this, self](boost::system::error_code ec, std::size_t /*n*/) {
                if (ec) {
                    // disconnect
                    on_disconnect(ec);
                    return;
                }

                std::istream is(&buffer);
                std::string line;
                std::getline(is, line); // bỏ '\n'

                std::cout << peer() << ": " << line << "\n";

                // broadcast cho các client khác
                broadcast(peer() + ": " + line + "\n");

                // đọc tiếp
                do_read_line();
            }
        );
    }

    void broadcast(const std::string& msg) {
        // Gửi cho tất cả client khác (không gửi lại chính mình)
        for (auto& client : *clients) {
            if (client.get() == this) continue;
            client->send(msg);
        }
    }

    void send(const std::string& msg) {
        auto self = shared_from_this();
        // NOTE: để đơn giản như Python, gửi thẳng async_write
        // (Trong app lớn nên có queue để tránh write chồng chéo)
        boost::asio::async_write(
            socket,
            boost::asio::buffer(msg),
            [self](boost::system::error_code /*ec*/, std::size_t /*n*/) {
                // bỏ qua lỗi ở đây; lỗi sẽ lộ ra ở read hoặc write lần sau
            }
        );
    }

    void on_disconnect(const boost::system::error_code& ec) {
        std::cout << "[Ngắt kết nối] " << peer() << " (" << ec.message() << ")\n";
        // xóa khỏi tập clients
        clients->erase(shared_from_this());

        boost::system::error_code ignored;
        socket.shutdown(tcp::socket::shutdown_both, ignored);
        socket.close(ignored);
    }
};

int main() {
    try {
        boost::asio::io_context io;

        tcp::acceptor acceptor(io, tcp::endpoint(boost::asio::ip::make_address("127.0.0.1"), 9999));

        std::set<std::shared_ptr<Session>> clients;

        std::function<void()> do_accept;
        do_accept = [&]() {
            acceptor.async_accept([&](boost::system::error_code ec, tcp::socket socket) {
                if (!ec) {
                    auto session = std::make_shared<Session>(std::move(socket), &clients);
                    clients.insert(session);
                    session->start();
                }
                do_accept();
            });
        };

        std::cout << "Chat server đang chạy tại 127.0.0.1:9999\n";
        do_accept();

        io.run();
    } catch (const std::exception& e) {
        std::cerr << "Server error: " << e.what() << "\n";
        return 1;
    }
    return 0;
}
