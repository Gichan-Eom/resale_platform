<?php
session_start();
require_once '../dbConnection.php';

if (!isset($_SESSION['ses_nickname'])) {
    header("Location: /login.php");
    exit;
}

$nickname = $_SESSION['ses_nickname'];
$chatRoomId = isset($_GET['chat_room_id']) ? $_GET['chat_room_id'] : null;
$userId = null;

// 사용자 ID 조회
$sql = "SELECT user_id FROM USERS WHERE nickname = :nickname";
$stid = oci_parse($conn, $sql);
oci_bind_by_name($stid, ":nickname", $nickname);
oci_execute($stid);
$row = oci_fetch_assoc($stid);
if ($row) {
    $userId = $row['USER_ID'];
}

// === 삭제 처리 로직 추가 시작 ===
if (isset($_GET['delete_chatroom_id'])) {
    $deleteChatRoomId = $_GET['delete_chatroom_id'];

    // 로그인한 사용자가 해당 채팅방에 참여자인지 확인
    $checkSql = "SELECT COUNT(*) AS CNT FROM USERS_CHATROOM WHERE user_id = :user_id AND chat_room_id = :chat_room_id";
    $checkStid = oci_parse($conn, $checkSql);
    oci_bind_by_name($checkStid, ":user_id", $userId);
    oci_bind_by_name($checkStid, ":chat_room_id", $deleteChatRoomId);
    oci_execute($checkStid);
    $countRow = oci_fetch_assoc($checkStid);

    if ($countRow && $countRow['CNT'] > 0) {
        // 참여자 맞으면 삭제 시작 (메시지 → USERS_CHATROOM → CHATROOM 순서)
        $deleteMsgsSql = "DELETE FROM MESSAGE WHERE chat_room_id = :chat_room_id";
        $deleteMsgsStid = oci_parse($conn, $deleteMsgsSql);
        oci_bind_by_name($deleteMsgsStid, ":chat_room_id", $deleteChatRoomId);
        oci_execute($deleteMsgsStid);

        $deleteUsersChatSql = "DELETE FROM USERS_CHATROOM WHERE chat_room_id = :chat_room_id";
        $deleteUsersChatStid = oci_parse($conn, $deleteUsersChatSql);
        oci_bind_by_name($deleteUsersChatStid, ":chat_room_id", $deleteChatRoomId);
        oci_execute($deleteUsersChatStid);

        $deleteChatSql = "DELETE FROM CHATROOM WHERE chat_room_id = :chat_room_id";
        $deleteChatStid = oci_parse($conn, $deleteChatSql);
        oci_bind_by_name($deleteChatStid, ":chat_room_id", $deleteChatRoomId);
        oci_execute($deleteChatStid);
    }

    // 삭제 후 리다이렉트(자기 자신 페이지, chat_room_id 제거)
    header("Location: ./chatRoom.php");
    exit;
}
// === 삭제 처리 로직 끝 ===


// 채팅방 목록 조회 및 각 채팅방 마지막 메시지 시간과 상품명도 함께 조회
$chatSql = "
SELECT c.chat_room_id, u.nickname AS other_nickname, g.good_name,
       TO_CHAR(MAX(m.message_sending_time), 'MM/DD HH24:MI') AS last_time
FROM CHATROOM c
JOIN USERS_CHATROOM uc1 ON c.chat_room_id = uc1.chat_room_id
JOIN USERS_CHATROOM uc2 ON c.chat_room_id = uc2.chat_room_id
JOIN USERS u ON uc2.user_id = u.user_id
JOIN GOOD g ON c.good_id = g.good_id
LEFT JOIN MESSAGE m ON c.chat_room_id = m.chat_room_id
WHERE uc1.user_id = :my_user_id AND uc2.user_id != :my_user_id
GROUP BY c.chat_room_id, u.nickname, g.good_name
ORDER BY last_time DESC NULLS LAST, c.chat_room_id DESC
";

$chatStid = oci_parse($conn, $chatSql);
oci_bind_by_name($chatStid, ":my_user_id", $userId);
oci_execute($chatStid);

$chatRooms = [];
while ($row = oci_fetch_assoc($chatStid)) {
    $chatRooms[] = $row;
}

// 메시지 불러오기
$messages = [];
if ($chatRoomId) {
    $msgSql = "
    SELECT u.nickname AS sender, m.message_content, 
           TO_CHAR(m.message_sending_time, 'YYYY-MM-DD HH24:MI:SS') AS sending_time
    FROM MESSAGE m
    JOIN USERS u ON m.user_id = u.user_id
    WHERE m.chat_room_id = :chat_room_id
    ORDER BY m.message_sending_time ASC
    ";
    $msgStid = oci_parse($conn, $msgSql);
    oci_bind_by_name($msgStid, ":chat_room_id", $chatRoomId);
    oci_execute($msgStid);

    while ($msg = oci_fetch_assoc($msgStid)) {
        $messages[] = $msg;
    }
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8" />
    <title>채팅방</title>
    <link rel="stylesheet" href="./chatRoom.css">
    <style>
        .my-header {
          display: flex;
          justify-content: flex-end;
          padding: 10px;
          background-color: #f9f9f9;
        }
        .my-btn {
          margin-left: 10px;
          padding: 5px 10px;
          background: #000;
          color: #fff;
          border: none;
          border-radius: 5px;
          cursor: pointer;
          text-decoration: none;
          font-size: 14px;
        }

        .delete-btn {
          position: absolute;
          right: 5px;
          top: 5px;
          background: transparent;
          border: none;
          color: red;
          font-weight: bold;
          cursor: pointer;
          font-size: 18px;
          line-height: 1;
        }
        .chat-item {
          position: relative; 
        }
    </style>
</head>
<body>
 <div class="my-header">
    <button class="my-btn" onclick="location.href='../main.php'">메인</button>
    <button class="my-btn" onclick="location.href='./chatroom.php'">채팅</button>
    <button class="my-btn" onclick="location.href='../MyPage/ProfilePage.php'">
    <?=htmlspecialchars($nickname, ENT_QUOTES, 'UTF-8')?></button>
    <button class="my-btn" onclick="location.href='../loginPage/logout.php'">로그아웃</button>
  </div>
<div class="chat-container">
    <!-- 채팅방 목록 -->
    <div class="chat-list">
  <?php foreach ($chatRooms as $room): ?>
    <div class="chat-item" onclick="location.href='?chat_room_id=<?= $room['CHAT_ROOM_ID'] ?>'">
      <button class="delete-btn" onclick="event.stopPropagation(); confirmDelete(<?= $room['CHAT_ROOM_ID'] ?>)">❌</button>
      <div class="chat-info">
        <div class="user"><?= htmlspecialchars($room['OTHER_NICKNAME']) ?></div>
        <div class="bottom-line">
          <div class="product-name"><?= htmlspecialchars($room['GOOD_NAME']) ?></div>
          <div class="last-time"><?= htmlspecialchars($room['LAST_TIME']) ?></div>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>

    <!-- 채팅창 영역 -->
    <div class="chat-room">
        <div class="chat-header">
            <?php if ($chatRoomId): ?>
                <?= htmlspecialchars($chatRooms[array_search($chatRoomId, array_column($chatRooms, 'CHAT_ROOM_ID'))]['OTHER_NICKNAME'] ?? '알 수 없음') ?>님과의 대화
            <?php else: ?>
                채팅방을 선택하세요.
            <?php endif; ?>
        </div>

        <div class="chat-messages" id="chatArea">
            <?php if (!$chatRoomId): ?>
            <?php else: ?>
                <?php foreach ($messages as $msg): ?>
                    <?php
                    $class = ($msg['SENDER'] === $nickname) ? 'me' : 'other';
                    $formattedTime = date("A h:i", strtotime($msg['SENDING_TIME']));
                    $formattedTime = str_replace(['AM', 'PM'], ['오전', '오후'], $formattedTime);
                    ?>
                    <div class="message <?= $class ?>">
                        <?php if ($class === 'other'): ?>
                            <div class="name"><?= htmlspecialchars($msg['SENDER']) ?></div>
                        <?php endif; ?>
                        <div class="bubble"><?= nl2br(htmlspecialchars($msg['MESSAGE_CONTENT'])) ?></div>
                        <div class="time"><?= $formattedTime ?></div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <?php if ($chatRoomId): ?>
        <div class="chat-input">
            <input type="text" id="message" placeholder="메시지를 입력하세요" />
            <button id="sendBtn">▶</button>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
function confirmDelete(chatRoomId) {
    if (confirm('이 채팅방을 삭제하시겠습니까?')) {
        // 삭제 요청을 GET 파라미터로 보내기
        location.href = './chatRoom.php?delete_chatroom_id=' + chatRoomId;
    }
}

<?php if ($chatRoomId): ?>
const ws = new WebSocket('ws://35.172.111.23:8080');
ws.onopen = () => {
    ws.send(JSON.stringify({
        type: 'join',
        username: '<?= $nickname ?>',
        chat_room_id: '<?= $chatRoomId ?>'
    }));
};

ws.onmessage = (event) => {
    const data = JSON.parse(event.data);
    if (data.type === 'chat') {
        const chatArea = document.getElementById('chatArea');
        const messageClass = data.from === '<?= $nickname ?>' ? 'me' : 'other';

        const now = new Date();
        const hours = now.getHours();
        const minutes = now.getMinutes().toString().padStart(2, '0');
        const ampm = hours < 12 ? '오전' : '오후';
        const displayHour = hours % 12 || 12;
        const timeStr = `${ampm} ${displayHour}:${minutes}`;

        const messageHTML = `
            <div class="message ${messageClass}">
                ${messageClass === 'other' ? `<div class="name">${data.from}</div>` : ''}
                <div class="bubble">${data.message.replace(/\n/g, '<br>')}</div>
                <div class="time">${timeStr}</div>
            </div>
        `;

        chatArea.insertAdjacentHTML('beforeend', messageHTML);
        chatArea.scrollTop = chatArea.scrollHeight;
    }
};

ws.onclose = () => {
    console.log('WebSocket 연결 종료');
};

document.getElementById('sendBtn').onclick = () => {
    const input = document.getElementById('message');
    const msg = input.value.trim();
    if (!msg) return;

    ws.send(JSON.stringify({
        type: 'chat',
        chat_room_id: '<?= $chatRoomId ?>',
        message: msg,
        from: '<?= $nickname ?>'
    }));

    fetch('./saveMessage.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `chat_room_id=<?= $chatRoomId ?>&message=${encodeURIComponent(msg)}`
    })
    .then(res => res.json())
    .then(data => {
        if (!data.success) {
            console.error('메시지 저장 실패:', data.error);
        }
    })
    .catch(err => console.error('메시지 저장 요청 실패:', err));

    input.value = '';
};

// 엔터키로 메시지 전송 기능 추가
document.getElementById('message').addEventListener('keydown', function(event) {
    if (event.key === 'Enter') {
        event.preventDefault(); // 줄바꿈 방지
        document.getElementById('sendBtn').click();
    }
});
<?php endif; ?>
</script>
</body>
</html>
