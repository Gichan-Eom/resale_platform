<?php
header('Content-Type: text/html; charset=UTF-8');
putenv('NLS_LANG=KOREAN_KOREA.AL32UTF8');
session_start();

$userId = $_SESSION['ses_userid'] ?? null;

$tns = "(DESCRIPTION=(ADDRESS=(PROTOCOL=TCP)(HOST=earth.gwangju.ac.kr)(PORT=1521))(CONNECT_DATA=(SID=orcl)))";
$conn = oci_connect("dbuser202046", "ce1234", $tns, "AL32UTF8");
if (!$conn) {
    $e = oci_error();
    die("DB 연결 실패: " . htmlspecialchars($e['message'], ENT_QUOTES));
}

// 로그인한 경우에만 즐겨찾기 추가/삭제 처리
if ($userId) {
    if (isset($_GET['add_fav_good_id'])) {
        $good_id = intval($_GET['add_fav_good_id']);
        $chk_stid = oci_parse($conn, "SELECT COUNT(*) AS CNT FROM FAVORITE WHERE user_id = :user_id AND good_id = :good_id");
        oci_bind_by_name($chk_stid, ":user_id", $userId);
        oci_bind_by_name($chk_stid, ":good_id", $good_id);
        oci_execute($chk_stid);
        $row = oci_fetch_array($chk_stid, OCI_ASSOC);
        oci_free_statement($chk_stid);

        if ($row['CNT'] == 0) {
            $insert_stid = oci_parse($conn, "INSERT INTO FAVORITE (favorite_id, favorite_registration_date, good_id, user_id) VALUES (favorite_seq.NEXTVAL, SYSDATE, :good_id, :user_id)");
            oci_bind_by_name($insert_stid, ":good_id", $good_id);
            oci_bind_by_name($insert_stid, ":user_id", $userId);
            oci_execute($insert_stid, OCI_COMMIT_ON_SUCCESS);
            oci_free_statement($insert_stid);
        }
        header("Location: main.php");
        exit();
    }

    if (isset($_GET['remove_fav_good_id'])) {
        $good_id = intval($_GET['remove_fav_good_id']);
        $del_stid = oci_parse($conn, "DELETE FROM FAVORITE WHERE user_id = :user_id AND good_id = :good_id");
        oci_bind_by_name($del_stid, ":user_id", $userId);
        oci_bind_by_name($del_stid, ":good_id", $good_id);
        oci_execute($del_stid, OCI_COMMIT_ON_SUCCESS);
        oci_free_statement($del_stid);

        header("Location: main.php");
        exit();
    }
}

// 닉네임
$nickname = '';
if ($userId) {
    $stidNick = oci_parse($conn, "SELECT nickname FROM USERS WHERE user_id = :user_id");
    oci_bind_by_name($stidNick, ":user_id", $userId);
    oci_execute($stidNick);
    $row = oci_fetch_array($stidNick, OCI_ASSOC + OCI_RETURN_NULLS);
    $nickname = $row['NICKNAME'] ?? $userId;
    oci_free_statement($stidNick);
}

// 검색 필터
$search   = trim($_GET['search']   ?? '');
$category = trim($_GET['category'] ?? '');
$location = trim($_GET['location'] ?? '');

// 카테고리
$categories = [];
$stid = oci_parse($conn, "SELECT DISTINCT category_name FROM CATEGORY ORDER BY category_name");
oci_execute($stid);
while ($r = oci_fetch_array($stid, OCI_ASSOC + OCI_RETURN_NULLS)) {
    $categories[] = $r['CATEGORY_NAME'];
}
oci_free_statement($stid);

// 위치
$locations = [];
$stid = oci_parse($conn, "SELECT DISTINCT good_location FROM GOOD ORDER BY good_location");
oci_execute($stid);
while ($r = oci_fetch_array($stid, OCI_ASSOC + OCI_RETURN_NULLS)) {
    $locations[] = $r['GOOD_LOCATION'];
}
oci_free_statement($stid);

// 상품 목록
$sql = "
SELECT
  g.good_id,
  g.good_name,
  g.good_location,
  g.good_price,
  g.good_status,
  TO_CHAR(g.good_registration_date, 'YYYY-MM-DD') AS good_date,
  c.category_name,
  g.good_image
FROM GOOD g
LEFT JOIN CATEGORY c ON g.category_id = c.category_id
WHERE 1=1
";

$binds = [];
if ($search !== '') {
    $sql .= " AND LOWER(g.good_name) LIKE :search ";
    $binds[':search'] = '%' . strtolower($search) . '%';
}
if ($category !== '') {
    $sql .= " AND c.category_name = :category ";
    $binds[':category'] = $category;
}
if ($location !== '') {
    $sql .= " AND g.good_location = :location ";
    $binds[':location'] = $location;
}
$sql .= " ORDER BY g.good_registration_date DESC";

$stid = oci_parse($conn, $sql);
foreach ($binds as $ph => &$val) {
    oci_bind_by_name($stid, $ph, $val);
}
oci_execute($stid);

$filtered = [];
while ($row = oci_fetch_array($stid, OCI_ASSOC | OCI_RETURN_LOBS | OCI_RETURN_NULLS)) {
    $imgBase64 = '';
    if (!empty($row['GOOD_IMAGE'])) {
        $imgBase64 = base64_encode($row['GOOD_IMAGE']);
    }
    $filtered[] = [
        'GOOD_ID'        => $row['GOOD_ID'],
        'GOOD_NAME'      => $row['GOOD_NAME'],
        'GOOD_LOCATION'  => $row['GOOD_LOCATION'],
        'GOOD_PRICE'     => $row['GOOD_PRICE'],
        'GOOD_STATUS'    => $row['GOOD_STATUS'],
        'GOOD_DATE'      => $row['GOOD_DATE'],
        'CATEGORY_NAME'  => $row['CATEGORY_NAME'] ?? '-',
        'IMAGE_BASE64'   => $imgBase64,
    ];
}
oci_free_statement($stid);

// 내 즐겨찾기 상품
$myFavGoodIds = [];
if ($userId) {
    $stidFav = oci_parse($conn, "SELECT good_id FROM FAVORITE WHERE user_id = :user_id");
    oci_bind_by_name($stidFav, ":user_id", $userId);
    oci_execute($stidFav);
    while ($r = oci_fetch_array($stidFav, OCI_ASSOC)) {
        $myFavGoodIds[] = $r['GOOD_ID'];
    }
    oci_free_statement($stidFav);
}

oci_close($conn);
?>

<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="UTF-8" />
  <title>🍅토마토 마켓🍅</title>
  <style>
    body { font-family: Arial, sans-serif; margin: 0; background-color: #fff; }
    .header { display:flex; justify-content:flex-end; padding:10px; background:#000; }
    .header .btn { margin-left:10px; padding:5px 10px; background:#fff; color:#000; border:none; border-radius:5px; cursor:pointer; text-decoration:none; }
    .header .nickname { margin-left:10px; padding:5px 10px; color:#fff; }
    .title { text-align:center; margin:30px 0 10px; font-size:24px; font-weight:bold;  cursor: pointer;}
    .search-bar { display:flex; flex-direction:column; align-items:center; margin:30px 0; }
    .search-bar input { width:500px; padding:12px; font-size:18px; border-radius:10px; border:1px solid #aaa; }
    .search-bar div { margin-top:10px; }
    .search-bar select, .search-bar button { padding:5px 10px; border-radius:5px; margin-left:5px; }
    .container { display:flex; padding:20px; max-width:1200px; margin:0 auto; }
    .products { flex:1; border:1px solid red; padding:10px 20px; overflow-y:auto; height:500px; }
    .product-item { display:flex; align-items:center; border-bottom:1px solid #ccc; padding:8px 0; }
    .product-item img { width:100px; height:100px; object-fit:contain; margin-right:20px; border:1px solid #ddd; }
    .product-link { text-decoration:none; color:inherit; display:block; flex:1; }
    .fav-btn {
      margin-left: 15px;
      padding: 8px 12px;
      background-color: #f44336;
      border: none;
      border-radius: 5px;
      color: white;
      cursor: pointer;
      font-weight: bold;
    }
    .fav-btn:hover {
      background-color: #d32f2f;
    }
    .fav-btn.active {
      background-color: #4caf50;
    }
  </style>
</head>
<body>

<div class="header">
  <?php if ($nickname !== ''): ?>
    <a href="./chatPage/chatRoom.php" class="btn">채팅</a>
    <a href="./myPage/profilepage.php" class="nickname">
      <?= htmlspecialchars($nickname, ENT_QUOTES) ?>
    </a>
    <a href="./loginPage/logout.php" class="btn">로그아웃</a>
  <?php else: ?>
    <a href="./loginPage/login_form.php" class="btn">로그인</a>
    <a href="./loginPage/add_form.php" class="btn">회원가입</a>
  <?php endif; ?>
</div>

<div class="title" onclick="location.href='main.php'">🍅토마토 마켓🍅</div>

<form method="GET" class="search-bar">
  <input
    type="text"
    name="search"
    placeholder="검색어 입력..."
    value="<?= htmlspecialchars($search, ENT_QUOTES) ?>"
  />
  <div>
    <label>카테고리:
      <select name="category">
        <option value="">전체</option>
        <?php foreach ($categories as $cat): ?>
          <option value="<?= htmlspecialchars($cat, ENT_QUOTES) ?>"
            <?= $category === $cat ? 'selected' : '' ?>>
            <?= htmlspecialchars($cat, ENT_QUOTES) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </label>
    <label style="margin-left:20px;">위치:
      <select name="location">
        <option value="">전체</option>
        <?php foreach ($locations as $loc): ?>
          <option value="<?= htmlspecialchars($loc, ENT_QUOTES) ?>"
            <?= $location === $loc ? 'selected' : '' ?>>
            <?= htmlspecialchars($loc, ENT_QUOTES) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </label>
    <button type="submit">검색</button>
  </div>
</form>

<div class="container">
  <div class="products">
    <h2>상품목록</h2>
    <?php if (count($filtered) > 0): ?>
      <?php foreach ($filtered as $p): ?>
        <div class="product-item">
          <a href="gooddetail.php?good_id=<?= $p['GOOD_ID'] ?>" class="product-link">
            <?php if ($p['IMAGE_BASE64']): ?>
              <img src="data:image/jpeg;base64,<?= $p['IMAGE_BASE64'] ?>" alt="상품 이미지" />
            <?php else: ?>
              <div style="width:100px;height:100px;background:#eee;display:flex;align-items:center;justify-content:center;color:#888;">이미지 없음</div>
            <?php endif; ?>
          </a>
          <div style="flex:1;">
            <a href="gooddetail.php?good_id=<?= $p['GOOD_ID'] ?>" class="product-link">
              <div><strong><?= htmlspecialchars($p['GOOD_NAME'], ENT_QUOTES) ?></strong></div>
              <div>가격: <?= number_format($p['GOOD_PRICE']) ?>원</div>
              <div>위치: <?= htmlspecialchars($p['GOOD_LOCATION'], ENT_QUOTES) ?></div>
              <div>상태: <?= htmlspecialchars($p['GOOD_STATUS'], ENT_QUOTES) ?></div>
              <div>등록일: <?= $p['GOOD_DATE'] ?></div>
              <div>카테고리: <?= htmlspecialchars($p['CATEGORY_NAME'], ENT_QUOTES) ?></div>
            </a>
          </div>

          <?php
          $isFavorited = in_array($p['GOOD_ID'], $myFavGoodIds);
          if ($isFavorited):
          ?>
            <a href="main.php?remove_fav_good_id=<?= $p['GOOD_ID'] ?>"
               class="fav-btn active" title="즐겨찾기 삭제">♥ 즐겨찾기</a>
          <?php else: ?>
            <a href="main.php?add_fav_good_id=<?= $p['GOOD_ID'] ?>"
               class="fav-btn" title="즐겨찾기 추가">♡ 즐겨찾기</a>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    <?php else: ?>
      <p>조건에 맞는 상품이 없습니다.</p>
    <?php endif; ?>
  </div>
</div>

</body>
</html>