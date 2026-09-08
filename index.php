<?php
ob_start();
session_start();
require_once("config.inc.php");

// 1) 移除原強制轉 int，改以直接取得 GET 參數
$ActParam = $_GET["Act"] ?? 0;

// 2) 未登入時若 ActParam=160 即可註冊，否則進入 no login
if (!isset($_SESSION['admid'])) {
    $Act = ($ActParam == 160) ? 160 : "nologin";
} else {
    $Act = intval($ActParam); // 若已登入再轉 int
}
?>

<!doctype html> <!-- 文件類型 -->
<html lang="zh-TW"> <!-- 語言設定 -->
<head>  <!-- 標頭 -->
    <meta charset="utf-8">  <!-- 編碼 -->
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">  <!-- RWD -->
    <title>企業作業管理系統</title>   <!-- 網頁標題 -->
    <link rel="stylesheet" href="css/bootstrap.min.css">    <!-- 引入 bootstrap -->
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+TC:wght@400;700&display=swap" rel="stylesheet">  <!-- 標楷體 -->
    <style> /* CSS 樣式 */
        body {  /* 背景 */
            font-family: 'Noto Sans TC', sans-serif;    /* 標楷體 */
            margin: 0;  /* 邊距 */
            padding: 0; /* 內距 */
            min-height: 100vh;  /* 最小高度 100% */
            /* 檔名刻意保持短且純 ASCII：原檔名 162 字元且含非 ASCII 字元，
               在 Windows 上 clone 到稍深的目錄會超過 MAX_PATH 260 而 checkout 失敗
               （實測：clone 根目錄 110 字元時即失敗，43 字元時成功）。 */
            background: url('images/hero-matterhorn.jpg') no-repeat center center fixed;
            background-size: cover; /* 背景圖片填滿 */
            display: flex;  /* 使用 flex 排版 */
            flex-direction: column; /* 垂直排列 */
        }
        nav {   /* 導覽列 */
            background-color: rgba(0, 0, 0, 0.8); /* 背景顏色 */
            padding: 10px 20px; /* 上下 10px，左右 20px */
            width: 100%; /* 寬度佔滿畫面 */
            display: flex; /* 使用 flex 排版 */
            justify-content: flex-end; /* 導覽列內容靠右 */
        }
        nav a { /* 導覽列連結 */
            color: white;   /* 文字顏色 */
            text-decoration: none;  /* 移除底線 */
            margin-left: 15px; /* 左間距 */
            font-weight: bold;  /* 粗體 */
        }
        nav a:hover {   /* 滑鼠移入 */
            text-decoration: underline; /* 底線 */
        }
        main {  /* 主要內容 */
            margin-top: 20px; /* 確保內容與導覽列有間距 */
            flex: 1;    /* 佔滿剩餘空間 */
            padding: 20px;  /* 內距 */
            border-radius: 10px;    /* 圓角 */
            margin: 20px 20px;  /* 上下 20px, 左右自動 */
            height: auto;   /* 高度自動 */
            width: 100%; 
        }
        footer {    /* 頁尾 */
            text-align: center; /* 文字置中 */
            padding: 15px 0;    /* 上下 15px */
            background-color: rgba(255, 255, 255, 0.8); /* 透明白色 */
        }
    </style>
</head>
<body>  <!-- 頁面主體 -->
<?php if (isset($_SESSION['admid'])): ?>    <!-- 判斷是否有登入 -->
    <nav>   <!-- 導覽列 -->
        <a href="logout.php">登出</a>    <!-- 登出 -->
        <a href="index.php?Act=100">個人資料</a>    <!-- 個人資料 -->  
        <a href="index.php?Act=150">Home</a>    <!-- 修改目標頁面 -->
        <a href="index.php?Act=110">使用者列表</a>  <!-- 使用者列表 -->
        <a href="index.php?Act=350">員工列表</a>      <!-- 員工列表 -->
        <a href="index.php?Act=300">顧客列表</a>      <!-- 顧客列表 -->
        <a href="index.php?Act=390">貨物列表</a>    <!-- 貨物列表 -->
        <a href="index.php?Act=430">訂單列表</a>  <!-- 訂單列表 -->
    </nav>  
<?php endif; ?> <!-- 結束判斷是否有登入 -->
<main>  <!-- 主要內容 -->
    <div class='container'> <!-- 容器 -->
        <?php   // 判斷要顯示的內容
        switch ($Act) { 
            case "nologin": // 未登入
                include("login.php");
                break;
            case 100:   // 個人資料
                include("profile.php");
                break;
            case 105:   // 修改個人資料
                include("profileEdit.php");
                break;
            case 115:   // 刪除個人資料
                include("profileDelete.php");
                break;
            case 110:   // 使用者列表
                include("adminList.php");   
                break;  
            case "120": //使用者編輯
                if ($_SESSION["admlimit"] == 1 || ($_SESSION["admlimit"] == 2 && $_GET['EK'] == $_SESSION["admprikey"])) {
                    include("adminEdit.php");
                } else {
                    echo "<p style='text-align:center; color:red;'>權限不足!</p>";
                }
                break;
            case "130": // 使用者刪除
                if ($_SESSION["admlimit"] == 1) {
                    include("adminDel.php");
                } else {
                    echo "<p style='text-align:center; color:red;'>權限不足!</p>";
                }
                break;
            case "135": // 使用者批量刪除
                if ($_SESSION["admlimit"] == 1) {
                    include("adminDelBatch.php");
                } else {
                    echo "<p style='text-align:center; color:red;'>權限不足!</p>";
                }
                break;
            case "140": // 使用者新增
                if ($_SESSION["admlimit"] == 1 || $_SESSION["admlimit"] == 2) {
                    include("adminAdd.php");
                } else {
                    echo "<p style='text-align:center; color:red;'>權限不足!</p>";
                }
                break;
            case "150": // 首頁
                include("Home.php");
                break;
            case 160: // 註冊
                include("register.php");
                break;
            case 200:   // 廠商/客戶列表
                include("firmandcustomerList.php");
                break;
            case 210:   // 廠商/客戶新增
                include("firmandcustomerAdd.php");
                break;
            case 220:   // 廠商/客戶刪除（軟刪除 enabled=0）
                include("firmandcustomerDel.php");
                break;
            case 230:   // 廠商/客戶編輯
                include("firmandcustomerEdit.php");
                break;
            case "240": // 訂單/發票列表
                include("orderandinvoiceList.php");
                break;
            case 250:   // 訂單/發票新增
                include("orderandinvoiceAdd.php");
                break;
            case 260:   // 訂單/發票刪除
                include("orderandinvoiceDel.php");
                break;
            case 265:   // 訂單/發票批量刪除
                include("orderandinvoiceDelBatch.php");
                break;
            case 270:   // 訂單/發票編輯
                include("orderandinvoiceEdit.php");
                break;
            case 300:   // 顧客列表
                include("CustomerList.php");
                break;
            case "350": // 員工列表
                include("EmployeeList.php");
                break;
            case "360": // 員工新增
                include("EmployeeAdd.php");
                break;
            case "370": // 員工刪除
                include("EmployeeDel.php");
                break;
            case 375:   // 員工批量刪除
                include("EmployeeDelBatch.php");
                break;
            case "380": // 員工編輯
                include("EmployeeEdit.php");
                break;
            case "320": // 顧客新增
                include("CustomerAdd.php");
                break;
            case "330": // 顧客刪除
                include("CustomerDel.php");
                break;
            case 335:   // 顧客批量刪除
                include("CustomerDelBatch.php");
                break;
            case "340": // 顧客編輯
                include("CustomerEdit.php");
                break;
            case "390": // 貨物列表
                include("ProductList.php");
                break;
            case "400": // 貨物新增
                include("ProductAdd.php");
                break;
            case "410": // 貨物刪除
                include("ProductDel.php");
                break;
            case 415:   // 貨物批量刪除
                include("ProductDelBatch.php");
                break;
            case "420": // 貨物編輯
                include("ProductEdit.php");
                break;
            case "430": // 訂單列表
                include("OrderList.php");
                break;
            case "440": // 訂單新增
                include("OrderAdd.php");
                break;
            case "450": // 訂單刪除
                include("OrderDel.php");
                break;
            case "460": // 訂單編輯
                include("OrderEdit.php");
                break;
            case "470": // 出貨紀錄列表
                include("ShipmentList.php");
                break;
            case "480": // 出貨紀錄新增
                include("ShipmentAdd.php");
                break;
            case "490": // 出貨紀錄編輯
                include("ShipmentEdit.php");
                break;
            case "500": // 出貨紀錄刪除
                include("ShipmentDel.php");
                break;
            case "510": // 出貨紀錄批量刪除
                include("ShipmentDelBatch.php");
                break;
        }
        ?>
    </div>  <!-- 結束容器 -->
</main> <!-- 結束主要內容 -->
<?php if (isset($_SESSION['admid'])): ?>    <!-- 判斷是否有登入 -->
<footer>    <!-- 頁尾 -->
    <div style="width: 100%; height: auto; background-color: rgba(255, 255, 255, 0.8); text-align: center; padding: 20px 0;">   <!-- 頁尾內容 -->
        <p style="color:#000000; margin: 0;">&copy; 2024 &nbsp;
            本專案僅供學習與展示用途，不得用於商業目的或散布。<br>  <!-- 禁止商業使用或分發 -->
            <br><br>   <!-- 用於學術研究 -->
            Official&nbsp;Email&nbsp;(Web/DB situation):&nbsp;[redacted] <br>  <!-- 官方電子郵件 -->
            Official&nbsp;Email&nbsp;(Web/DB situation):&nbsp;[redacted] <br>  <!-- 官方電子郵件 -->
            Web/DB Management and Development Engineering:&nbsp;[redacted] &nbsp; <br>   <!-- 網頁/資料庫管理和開發工程 -->
            Web/DB Management and Development Engineering:&nbsp;[redacted] &nbsp; <br>  <!-- 網頁/資料庫管理和開發工程 -->
        </p>
    </div>  <!-- 結束頁尾內容 -->
</footer>   <!-- 結束頁尾 -->
<?php endif; ?> <!-- 結束判斷是否有登入 -->
<!-- Optional JavaScript -->
<script src="js/jquery-3.6.0.min.js"></script>  <!-- 引入 jQuery -->
<script src="js/bootstrap.min.js"></script> <!-- 引入 bootstrap -->
</body> <!-- 結束頁面主體 -->
</html>