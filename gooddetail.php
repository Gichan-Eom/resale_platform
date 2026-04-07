<?php
// good_detail.php
putenv('NLS_LANG=KOREAN_KOREA.AL32UTF8');
header('Content-Type: text/html; charset=UTF-8');
session_start();
include './dbConnection.php';

$isLoggedIn = isset($_SESSION['ses_userid']);
$nickname   = $_SESSION['ses_nickname'] ?? null;

$goodId = isset($_GET['good_id']) ? (int)$_GET['good_id'] : 0;
if ($goodId <= 0) {
    die('잘못된 접근입니다.');
}
$sql = "
  SELECT
    g.good_name,
    c.category_name,
    g.good_price,
    g.good_location,
    g.good_status,
    TO_CHAR(g.good_registration_date,'YYYY-MM-DD') AS GOOD_DATE,
    g.good_image,
    g.good_description,
    u.nickname AS SELLER_NICKNAME
  FROM GOOD g
  LEFT JOIN CATEGORY c ON g.category_id = c.category_id
  LEFT JOIN USERS u ON g.user_id = u.user_id
  WHERE g.good_id = :gid
";
$stid = oci_parse($conn, $sql);
oci_bind_by_name($stid, ":gid", $goodId, -1, SQLT_INT);
oci_execute($stid);

$row = oci_fetch_array($stid, OCI_ASSOC | OCI_RETURN_LOBS | OCI_RETURN_NULLS);
if (!$row) {
    oci_free_statement($stid);
    oci_close($conn);
    die('존재하지 않는 상품입니다.');
}

$imgBase64 = '';
if (!empty($row['GOOD_IMAGE'])) {
    $imgBase64 = base64_encode($row['GOOD_IMAGE']);
}

$statusText = $row['GOOD_STATUS'];
$regDate    = $row['GOOD_DATE'];
$description = $row['GOOD_DESCRIPTION'] ?? '상품 설명이 없습니다.';
$sellerNickname = $row['SELLER_NICKNAME'] ?? '';
$isMyProduct = ($isLoggedIn && $nickname === $sellerNickname);

oci_free_statement($stid);
oci_close($conn);
?>
<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="UTF-8">
  <title>상품 상세</title>
  <style>
    body { font-family: '맑은 고딕', Arial, sans-serif; background:#fff; margin:0; padding:0; }
    .header { position: relative; background:#000; height:50px; }
    .right-buttons { position:absolute; top:50%; right:20px; transform:translateY(-50%); }
    .right-buttons button,
    .right-buttons span { margin-left:5px; background:#222; color:#fff; border:none; padding:4px 10px; border-radius:10px; cursor:pointer; font-size:12px; }
    .right-buttons .nickname { background:transparent; color:#fff; cursor:default; }
    .title { text-align:center; margin:20px 0 10px; font-size:24px; font-weight:bold; color:#222; cursor: pointer;}
    /* .search-bar { display:flex; justify-content:center; margin-bottom:20px; }
    .search-bar input[type="text"] {
      width:400px; padding:12px 15px; border:1px solid #aaa; border-radius:18px; font-size:16px; box-sizing:border-box;
    }
    .search-bar button {
      margin-left:-40px; border:none; background:none; cursor:pointer; font-size:20px; color:#555;
    } */
    .product-detail-container { width:750px; margin:0 auto 40px; }
    .product-main-row { display:flex; gap:36px; margin-bottom:28px; }
    .product-image-box {
      flex:1.5; border:2px solid #ffb3b3; border-radius:8px;
      display:flex; justify-content:center; align-items:center;
      min-height:220px; background:#fff; padding:22px 0;
    }
    .product-image { width:160px; height:160px; object-fit:cover; border-radius:8px; }
    .product-info-box {
      flex:2.5; border:2px solid #ffb3b3; border-radius:8px;
      padding:34px 38px; font-size:20px; display:flex;
      flex-direction:column; justify-content:center;
    }
    .product-info-box div { margin-bottom:16px; }
    .product-description-box {
      border:2px solid #ffb3b3; border-radius:8px;
      padding:38px; text-align:center; min-height:110px;
      font-size:18px; background:#fff; margin-bottom:25px;
    }
    .chat-btn {
      width:100%; background:#16b13c; color:#fff; font-size:20px;
      font-weight:600; border:none; border-radius:8px; padding:20px 0;
      cursor:pointer; transition:background .2s;
    }
    .chat-btn:hover { background:#11932c; }
  </style>
</head>
<body>

<div class="header">
  <div class="right-buttons">
    <button onclick="location.href='./main.php'">메인</button>
    <?php if ($isLoggedIn): ?>
      <button onclick="location.href='./myPage/profilepage.php'" class="nickname" style="cursor: pointer;">
        <?= htmlspecialchars($nickname, ENT_QUOTES,'UTF-8') ?>
      </button>
      <button onclick="location.href='./loginPage/logout.php'">로그아웃</button>
    <?php else: ?>
      <button onclick="location.href='./loginPage/login.php'">로그인</button>
      <button onclick="location.href='./loginPage/add_form.php'">회원가입</button>
    <?php endif; ?>
  </div>
</div>

<div class="title" onclick="location.href='main.php'">🍅토마토 마켓🍅</div>

<!-- <form class="search-bar" method="get" action="search.php">
  <input type="text" name="query" placeholder="검색어를 입력하세요">
  <button type="submit">&#128269;</button>
</form> -->

<div class="product-detail-container">
  <div class="product-main-row">
    <div class="product-image-box">
      <?php if ($imgBase64): ?>
        <img src="data:image/jpeg;base64,<?=$imgBase64?>" alt="상품 이미지" class="product-image">
      <?php else: ?>
        <div style="width:160px;height:160px;background:#eee;
                    display:flex;align-items:center;justify-content:center;
                    color:#aaa;border-radius:8px;">
          이미지 없음
        </div>
      <?php endif; ?>
    </div>
    <div class="product-info-box">
      <div><b><?=htmlspecialchars($row['GOOD_NAME'], ENT_QUOTES)?></b></div>
      <div>카테고리: <?=htmlspecialchars($row['CATEGORY_NAME'], ENT_QUOTES)?></div>
      <div>가격: <?=number_format($row['GOOD_PRICE'])?> 원</div>
      <div>위치: <?=htmlspecialchars($row['GOOD_LOCATION'], ENT_QUOTES)?></div>
      <div>상태: <?=$statusText?></div>
      <div>등록일: <?=$regDate?></div>
    </div>
  </div>

  <div class="product-description-box">
    <?= nl2br(htmlspecialchars($description, ENT_QUOTES)) ?>
  </div>

  <?php if (!$isLoggedIn): ?>
    <button class="chat-btn" onclick="alert('로그인 후 이용해주세요.')">
      판매자와 채팅하기
    </button>
  <?php elseif ($isMyProduct): ?>
    <button class="chat-btn" onclick="location.href='./myPage/profilepage.php'">
      본인이 등록한 상품입니다.
    </button>
  <?php else: ?>
    <button class="chat-btn" onclick="startChat(<?= (int)$goodId ?>)">
      판매자와 채팅하기
    </button>
  <?php endif; ?>
</div>

<script>
function startChat(goodId) {
  fetch('./chatPage/chatStart.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: 'good_id=' + encodeURIComponent(goodId)
  })
  .then(response => response.json())
  .then(data => {
    if (data.chat_room_id) {
      window.location.href = './chatPage/chatRoom.php?chat_room_id=' + encodeURIComponent(data.chat_room_id);
    } else if (data.error) {
      alert(data.error);
    } else {
      alert('채팅방 생성에 실패했습니다.');
    }
  })
  // .catch(error => {
  //   console.error('에러 발생:', error);
  //   alert('오류가 발생했습니다. 다시 시도해주세요.');
  // });
}
</script>

</body>
</html>
