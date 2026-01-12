#include <boost/asio.hpp>
#include <deque>
#include <iostream>
#include <memory>
#include <mutex>
#include <set>
#include <string>
#include <vector>

using boost::asio::ip::tcp;

class Session; // forward

class ChatRoom {
public:
    void join(const std::shared_ptr<Session>& s) {
        std::lock_guard<std::mutex> lk(mtx_);
        sessions_.insert(s);
    }

    void leave(const std::shared_ptr<Session>& s) {
        std::lock_guard<std::mutex> lk(mtx_);
        sessions_.erase(s);
    }

    void broadcast(const std::string& msg, const std::shared_ptr<Session>& exclude = nullptr);

private:
    std::mutex mtx_;
    std::set<std::shared_ptr<Session>> sessions_;
};

class Session : public std::enable_shared_from_this<Session> {
public:
    Session(tcp::socket socket, ChatRoom& room)
        : socket_(std::move(socket)), room_(room) {}

    void start() {
        // Lấy ip:port của client để in prefix
        boost::system::error_code ec;
        auto ep = socket_.remote_endpoint(ec);
        if (!ec) {
            id_ = ep.address().to_string() + ":" + std::to_string(ep.port());
        } else {
            id_ = "unknown";
        }

        room_.join(shared_from_this());
        deliver("Chào mừng! Gõ tin nhắn và nhấn Enter. Gõ /quit để thoát.\n");
        do_read_line();
    }

    void deliver(const std::string& msg) {
        bool writing = !outbox_.empty();
        outbox_.push_back(msg);
        if (!writing) do_write();
    }

private:
    void do_read_line() {
        auto self = shared_from_this();
        boost::asio::async_read_until(
            socket_, buffer_, '\n',
            [this, self](const boost::system::error_code& ec, std::size_t /*n*/) {
                if (ec) {
                    room_.leave(self);
                    close();
                    return;
                }

                std::istream is(&buffer_);
                std::string line;
                std::getline(is, line); // bỏ '\n'
                if (!line.empty() && line.back() == '\r') line.pop_back(); // CRLF

                if (line == "/quit") {
                    deliver("Tạm biệt!\n");
                    room_.leave(self);
                    close();
                    return;
                }

                // Gửi cho các client khác: [ip:port] message
                room_.broadcast("[" + id_ + "] " + line + "\n", self);
                do_read_line();
            }
        );
    }

    void do_write() {
        auto self = shared_from_this();
        boost::asio::async_write(
            socket_, boost::asio::buffer(outbox_.front()),
            [this, self](const boost::system::error_code& ec, std::size_t /*n*/) {
                if (ec) {
                    room_.leave(self);
                    close();
                    return;
                }
                outbox_.pop_front();
                if (!outbox_.empty()) do_write();
            }
        );
    }

    void close() {
        boost::system::error_code ignored;
        socket_.shutdown(tcp::socket::shutdown_both, ignored);
        socket_.close(ignored);
    }

private:
    tcp::socket socket_;
    ChatRoom& room_;
    boost::asio::streambuf buffer_;
    std::deque<std::string> outbox_;
    std::string id_;
};

void ChatRoom::broadcast(const std::string& msg, const std::shared_ptr<Session>& exclude) {
    std::vector<std::shared_ptr<Session>> snapshot;
    {
        std::lock_guard<std::mutex> lk(mtx_);
        snapshot.assign(sessions_.begin(), sessions_.end());
    }
    for (auto& s : snapshot) {
        if (exclude && s == exclude) continue;
        s->deliver(msg);
    }
}

class Server {
public:
    Server(boost::asio::io_context& io, const tcp::endpoint& ep)
        : acceptor_(io, ep) {
        do_accept();
    }

private:
    void do_accept() {
        acceptor_.async_accept([this](const boost::system::error_code& ec, tcp::socket socket) {
            if (!ec) {
                std::make_shared<Session>(std::move(socket), room_)->start();
            }
            do_accept();
        });
    }

private:
    tcp::acceptor acceptor_;
    ChatRoom room_;
};

int main(int argc, char* argv[]) {
    try {
        std::string host = "0.0.0.0";
        unsigned short port = 9001;

        if (argc >= 2) host = argv[1];
        if (argc >= 3) port = static_cast<unsigned short>(std::stoi(argv[2]));

        boost::asio::io_context io;
        tcp::endpoint ep(boost::asio::ip::make_address(host), port);

        Server server(io, ep);
        std::cout << "Chat server running at " << host << ":" << port << "\n";
        io.run();
    } catch (const std::exception& e) {
        std::cerr << "Fatal: " << e.what() << "\n";
        return 1;
    }
    return 0;
}
