<?php
require __DIR__ . '/vendor/autoload.php';

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Ratchet\Server\IoServer;
use React\Socket\Server as Reactor;
use React\EventLoop\Factory as LoopFactory;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;

class Chat implements MessageComponentInterface {
    protected $clients;
    protected $chatRooms = []; // chat_room_id => [connections]

    public function __construct() {
        $this->clients = new \SplObjectStorage;
        echo "Chat 서버 객체 생성\n";
    }

    public function onOpen(ConnectionInterface $conn) {
        $this->clients->attach($conn);
        $conn->chat_room_id = null;
        $conn->nickname = null;
        echo "New connection! ({$conn->resourceId})\n";
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        $data = json_decode($msg, true);

        if ($data['type'] === 'join') {
            $from->nickname = $data['username'];
            $from->chat_room_id = $data['chat_room_id'];
            if (!isset($this->chatRooms[$from->chat_room_id])) {
                $this->chatRooms[$from->chat_room_id] = new \SplObjectStorage;
            }
            $this->chatRooms[$from->chat_room_id]->attach($from);
            echo "[{$from->nickname}] joined room {$from->chat_room_id}\n";

        } else if ($data['type'] === 'chat') {
            $roomId = $data['chat_room_id'];
            $message = $data['message'];
            $fromName = $data['from'];

            echo "[Room $roomId] {$fromName}: {$message}\n";

            if (isset($this->chatRooms[$roomId])) {
                foreach ($this->chatRooms[$roomId] as $client) {
                    $client->send(json_encode([
                        'type' => 'chat',
                        'from' => $fromName,
                        'message' => $message
                    ]));
                }
            }
        }
    }

    public function onClose(ConnectionInterface $conn) {
        $this->clients->detach($conn);
        if ($conn->chat_room_id && isset($this->chatRooms[$conn->chat_room_id])) {
            $this->chatRooms[$conn->chat_room_id]->detach($conn);
        }
        echo "Connection {$conn->resourceId} has disconnected\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "Error: {$e->getMessage()}\n";
        $conn->close();
    }

}

$loop = LoopFactory::create();
$reactor = new Reactor('0.0.0.0:8080', $loop);
$wsServer = new WsServer(new Chat());
$httpServer = new HttpServer($wsServer);

$server = new IoServer($httpServer, $reactor, $loop);

echo "서버 시작 (0.0.0.0:8080)\n";

$server->run();
