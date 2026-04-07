<?php
session_start();
require_once '../dbConnection.php';
$nickname = $_SESSION['ses_nickname'] ?? '';

// 로그인 확인
if (!isset($_SESSION['ses_userid'])) {
  die("로그인이 필요합니다.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $userId = $_SESSION['ses_userid'];
  $goodName = $_POST['product-name'];
  $price = intval($_POST['price']);
  $location = $_POST['location'];
  $description = $_POST['description'];
  $categoryId = intval($_POST['category']); // 선택한 카테고리 ID

  // 이미지 처리
  if (!isset($_FILES['product-image']) || $_FILES['product-image']['error'] !== UPLOAD_ERR_OK) {
    die("이미지 업로드 실패");
  }
  $imageData = file_get_contents($_FILES['product-image']['tmp_name']);

  $status = "판매 중";

  // 상품 INSERT
  $sql = "INSERT INTO GOOD (
            good_id, good_name, good_price, good_image,
            good_location, good_registration_date,
            user_id, good_description, good_status, category_id
          ) VALUES (
            GOOD_SEQ.NEXTVAL, :name, :price, :image,
            :location, SYSDATE, :user_id, :description, :status, :category_id
          )";

  $stmt = oci_parse($conn, $sql);
  oci_bind_by_name($stmt, ":name", $goodName);
  oci_bind_by_name($stmt, ":price", $price);
  oci_bind_by_name($stmt, ":image", $imageData, -1, SQLT_LBI);
  oci_bind_by_name($stmt, ":location", $location);
  oci_bind_by_name($stmt, ":user_id", $userId);
  oci_bind_by_name($stmt, ":description", $description);
  oci_bind_by_name($stmt, ":status", $status);
  oci_bind_by_name($stmt, ":category_id", $categoryId);

  $result = oci_execute($stmt);
  if ($result) {
    oci_free_statement($stmt);
    oci_close($conn);
    echo "<script>alert('상품이 등록되었습니다.'); location.href='ProfilePage.php';</script>";
    exit;
  } else {
    $e = oci_error($stmt);
    die("DB 오류: " . $e['message']);
  }
}
?>

<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="UTF-8" />
  <title>상품 등록</title>
  <link rel="stylesheet" href="ProductForm.css" />
  <style>
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
  <form action="ProductForm.php" method="POST" enctype="multipart/form-data">
    <div class="product-form-container">
      <div class="img-upload-box">
        <label for="product-img-input" style="cursor:pointer;">
          <img
            src="https://cdn-icons-png.flaticon.com/512/685/685655.png"
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
          required
          style="display:none;"
        />
      </div>

      <input
        type="text"
        class="product-title"
        placeholder="상품명"
        name="product-name"
        required
      />
      <input
        type="text"
        class="product-price"
        placeholder="₩ 판매가격"
        name="price"
        required
      />
      <input
        type="text"
        class="product-location"
        placeholder="거래지역"
        name="location"
        required
      />

      <select name="category" class="product-select" required>
        <option value="">카테고리 선택</option>
        <?php
          $catSql = "SELECT category_id, category_name FROM CATEGORY";
          $catStmt = oci_parse($conn, $catSql);
          oci_execute($catStmt);
          while ($row = oci_fetch_assoc($catStmt)) {
            echo "<option value=\"{$row['CATEGORY_ID']}\">" . htmlspecialchars($row['CATEGORY_NAME']) . "</option>";
          }
          oci_free_statement($catStmt);
        ?>
      </select>

      <textarea
        class="product-detail-desc"
        placeholder="상세 설명을 입력하세요."
        name="description"
        rows="4"
      ></textarea>

      <button type="submit" class="submit-btn">판매하기</button>
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

