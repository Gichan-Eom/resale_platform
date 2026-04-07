<?php
session_start();
require_once '../dbConnection.php';
// 로그인 확인
if (!isset($_SESSION['ses_userid'])) {
  die("로그인이 필요합니다.");
}

$userId = $_SESSION['ses_userid'];
$nickname = $_SESSION['ses_nickname'] ?? '';

// 상품 ID 받기 (GET)
if (!isset($_GET['good_id'])) {
  die("상품 ID가 없습니다.");
}
$goodId = $_GET['good_id'];

// POST 요청 -> 상품 수정 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $goodName = $_POST['product-name'];
  $price = $_POST['price'];
  $location = $_POST['location'];
  $description = $_POST['description'];
  $categoryId = $_POST['category'];
$goodStatus = $_POST['status'] ?? '';

  // 이미지 처리 (이미지 선택 안 하면 기존 이미지 유지)
  $imageData = null;
  $isImageUpdated = false;
  if (isset($_FILES['product-image']) && $_FILES['product-image']['error'] === UPLOAD_ERR_OK) {
    $imageData = file_get_contents($_FILES['product-image']['tmp_name']);
    $isImageUpdated = true;
  }

  $sql = "UPDATE GOOD SET 
            good_name = :name, 
            good_price = :price, 
            good_location = :location, 
            good_description = :description, 
            category_id = :category_id,
            good_status = :status";

  if ($isImageUpdated) {
    $sql .= ", good_image = :image";
  }

  $sql .= " WHERE good_id = :good_id AND user_id = :user_id";

  $stmt = oci_parse($conn, $sql);
  oci_bind_by_name($stmt, ":name", $goodName);
  oci_bind_by_name($stmt, ":price", $price);
  oci_bind_by_name($stmt, ":location", $location);
  oci_bind_by_name($stmt, ":description", $description);
  oci_bind_by_name($stmt, ":category_id", $categoryId);
  oci_bind_by_name($stmt, ":status", $goodStatus);
  oci_bind_by_name($stmt, ":good_id", $goodId);
  oci_bind_by_name($stmt, ":user_id", $userId);

  if ($isImageUpdated) {
    oci_bind_by_name($stmt, ":image", $imageData, -1, SQLT_LBI);
  }

  $result = oci_execute($stmt);
  if ($result) {
    oci_free_statement($stmt);
    oci_close($conn);
    echo "<script>alert('상품 정보가 수정되었습니다.'); location.href='ProfilePage.php';</script>";
    exit;
  } else {
    $e = oci_error($stmt);
    die("DB 오류: " . $e['message']);
  }
}

// GET 요청 -> 상품 정보 조회
$sql = "SELECT good_name, good_price, good_location, good_description, category_id, good_image 
        FROM GOOD 
        WHERE good_id = :good_id AND user_id = :user_id";

$stmt = oci_parse($conn, $sql);
oci_bind_by_name($stmt, ":good_id", $goodId);
oci_bind_by_name($stmt, ":user_id", $userId);
oci_execute($stmt);
$product = oci_fetch_assoc($stmt);

if (!$product) {
  die("상품 정보를 찾을 수 없습니다.");
}

// 이미지 base64 인코딩 (미리보기 용)
$imageBase64 = "";
if ($product['GOOD_IMAGE'] !== null) {
  $imageBase64 = 'data:image/jpeg;base64,' . base64_encode($product['GOOD_IMAGE']->load());
}
?>

<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="UTF-8" />
  <title>상품 수정</title>
  <link rel="stylesheet" href="ProductForm.css" />
  <style>
    .product-category,
    .product-status,
    .product-detail-desc,
    .product-select,
    .product-location {
  height: 40px;
  padding-left: 12px;
  font-size: 15px;
  border: 1.5px solid #e35e5e;
  border-radius: 2px;
  margin-bottom: 24px;
  box-sizing: border-box;
  width: 100%;
  font-family: inherit;
}
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
  </style>
</head>
<body>
  <div class="my-header">
    <button class="my-btn" onclick="location.href='../main.php'">메인</button>
    <button class="my-btn" onclick="location.href='../chatPage/chatroom.php'">채팅</button>
    <button class="my-btn" onclick="location.href='../MyPage/ProfilePage.php'">
    <?=htmlspecialchars($nickname, ENT_QUOTES, 'UTF-8')?></button>
    <button class="my-btn" onclick="location.href='../loginPage/logout.php'">로그아웃</button>
  </div>
  <form action="ProductEdit.php?good_id=<?= htmlspecialchars($goodId) ?>" method="POST" enctype="multipart/form-data">
    <div class="product-form-container">
      <div class="img-upload-box">
        <label for="product-img-input" style="cursor:pointer;">
          <img
            src="<?= $imageBase64 ?: 'https://cdn-icons-png.flaticon.com/512/685/685655.png' ?>"
            alt="이미지 업로드"
            class="camera-icon"
            id="product-img-preview"
            style="width:100px; height:100px; object-fit:cover;"
          />
        </label>
        <input
          type="file"
          id="product-img-input"
          name="product-image"
          accept="image/*"
          style="display:none;"
        />
      </div>

      <input
        type="text"
        class="product-title"
        placeholder="상품명"
        name="product-name"
        value="<?= htmlspecialchars($product['GOOD_NAME']) ?>"
        required
      />
      <input
        type="text"
        class="product-price"
        placeholder="₩ 판매가격"
        name="price"
        value="<?= htmlspecialchars($product['GOOD_PRICE']) ?>"
        required
      />
      <select name="status" class="product-status"required>
  <option value="">상태 선택</option>
  <option value="판매 중" <?= (isset($product['GOOD_STATUS']) && $product['GOOD_STATUS'] === '판매 중') ? 'selected' : '' ?>>판매 중</option>
  <option value="판매 중지" <?= (isset($product['GOOD_STATUS']) && $product['GOOD_STATUS'] === '판매 중지') ? 'selected' : '' ?>>판매 중지</option>
  <option value="판매 완료" <?= (isset($product['GOOD_STATUS']) && $product['GOOD_STATUS'] === '판매 완료') ? 'selected' : '' ?>>판매 완료</option>
</select>
      <input
        type="text"
        class="product-location"
        placeholder="거래지역 (예: 서울)"
        name="location"
        value="<?= htmlspecialchars($product['GOOD_LOCATION']) ?>"
        required
      />

      <select name="category" class= "product-category" required>
        <option value="">카테고리 선택</option>
        <?php
          $catSql = "SELECT category_id, category_name FROM CATEGORY";
          $catStmt = oci_parse($conn, $catSql);
          oci_execute($catStmt);
          while ($row = oci_fetch_assoc($catStmt)) {
            $selected = ($row['CATEGORY_ID'] == $product['CATEGORY_ID']) ? "selected" : "";
            echo "<option value=\"{$row['CATEGORY_ID']}\" $selected>" . htmlspecialchars($row['CATEGORY_NAME']) . "</option>";
          }
          oci_free_statement($catStmt);
        ?>
      </select>

      <textarea
        class="product-detail-desc"
        placeholder="상세 설명을 입력하세요."
        name="description"
        rows="4"
      ><?= htmlspecialchars($product['GOOD_DESCRIPTION']) ?></textarea>

      <button type="submit" class="submit-btn">수정 완료</button>
    </div>
  </form>

  <script>
    // 이미지 미리보기
    document.getElementById("product-img-input").addEventListener("change", function (event) {
      const file = event.target.files[0];
      if (file) {
        const reader = new FileReader();
        reader.onload = function (e) {
          document.getElementById("product-img-preview").src = e.target.result;
        };
        reader.readAsDataURL(file);
      }
    });
  </script>
</body>
</html>
