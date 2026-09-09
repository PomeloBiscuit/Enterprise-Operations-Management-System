<?php
ob_start();
session_start();
require_once __DIR__ . '/../src/config.inc.php';
require_once __DIR__ . '/../src/auth.inc.php';

// 1) 移除原強制轉 int，改以直接取得 GET 參數
$ActParam = $_GET["Act"] ?? 0;

// 2) 未登入時若 ActParam=160 即可註冊，否則進入 no login
if (!isset($_SESSION['admid'])) {
    $Act = ($ActParam == 160) ? 160 : "nologin";
} else {
    $Act = intval($ActParam); // 若已登入再轉 int
}

// 以路由白名單封住沒有頁內權限檢查的 CRUD 頁面；新增角色不會因數字大小而自動取得權限。
$userManagementActs = [110, 120, 130, 135, 140];
$businessDataActs = [
    200, 210, 220, 230, 240, 250, 260, 265, 270,
    300, 320, 330, 335, 340, 350, 360, 370, 375, 380,
    390, 400, 410, 415, 420, 430, 440, 450, 460,
    470, 480, 490, 500, 510,
];

if (in_array($Act, $userManagementActs, true) && !can_manage_users()) {
    $Act = 'forbidden';
} elseif (in_array($Act, $businessDataActs, true) && !can_view_business_data()) {
    $Act = 'forbidden';
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
        <?php if (can_access_self()): ?>
        <a href="index.php?Act=100">個人資料</a>    <!-- 個人資料 -->  
        <a href="index.php?Act=150">Home</a>    <!-- 修改目標頁面 -->
        <?php endif; ?>
        <?php if (can_manage_users()): ?>
        <a href="index.php?Act=110">使用者列表</a>  <!-- 使用者列表 -->
        <?php endif; ?>
        <?php if (can_view_business_data()): ?>
        <a href="index.php?Act=350">員工列表</a>      <!-- 員工列表 -->
        <a href="index.php?Act=300">顧客列表</a>      <!-- 顧客列表 -->
        <a href="index.php?Act=390">貨物列表</a>    <!-- 貨物列表 -->
        <a href="index.php?Act=430">訂單列表</a>  <!-- 訂單列表 -->
        <?php endif; ?>
    </nav>  
<?php endif; ?> <!-- 結束判斷是否有登入 -->
<main>  <!-- 主要內容 -->
    <div class='container'> <!-- 容器 -->
        <?php   // 判斷要顯示的內容
        // 頁面檔已移出 web root；一律以 __DIR__ 相對載入（本檔在 public/，頁面在 ../src/pages/）。
        $PAGES = __DIR__ . '/../src/pages';
        switch ($Act) {
            case "nologin": // 未登入
                include __DIR__ . '/login.php';
                break;
            case 100:   // 個人資料
                include $PAGES . '/profile/profile.php';
                break;
            case 105:   // 修改個人資料
                include $PAGES . '/profile/profileEdit.php';
                break;
            case 115:   // 刪除個人資料
                include $PAGES . '/profile/profileDelete.php';
                break;
            case 110:   // 使用者列表
                include $PAGES . '/user/adminList.php';
                break;
            case "120": //使用者編輯
                if (is_admin()) {
                    include $PAGES . '/user/adminEdit.php';
                } else {
                    echo "<p style='text-align:center; color:red;'>權限不足!</p>";
                }
                break;
            case "130": // 使用者刪除
                if (is_admin()) {
                    include $PAGES . '/user/adminDel.php';
                } else {
                    echo "<p style='text-align:center; color:red;'>權限不足!</p>";
                }
                break;
            case "135": // 使用者批量刪除
                if (is_admin()) {
                    include $PAGES . '/user/adminDelBatch.php';
                } else {
                    echo "<p style='text-align:center; color:red;'>權限不足!</p>";
                }
                break;
            case "140": // 使用者新增
                if (is_admin()) {
                    include $PAGES . '/user/adminAdd.php';
                } else {
                    echo "<p style='text-align:center; color:red;'>權限不足!</p>";
                }
                break;
            case "150": // 首頁
                include $PAGES . '/Home.php';
                break;
            case 160: // 註冊
                include __DIR__ . '/register.php';
                break;
            case 200:   // 廠商/客戶列表
                include $PAGES . '/firm/firmandcustomerList.php';
                break;
            case 210:   // 廠商/客戶新增
                include $PAGES . '/firm/firmandcustomerAdd.php';
                break;
            case 220:   // 廠商/客戶刪除（軟刪除 enabled=0）
                include $PAGES . '/firm/firmandcustomerDel.php';
                break;
            case 230:   // 廠商/客戶編輯
                include $PAGES . '/firm/firmandcustomerEdit.php';
                break;
            case "240": // 訂單/發票列表
                include $PAGES . '/invoice/orderandinvoiceList.php';
                break;
            case 250:   // 訂單/發票新增
                include $PAGES . '/invoice/orderandinvoiceAdd.php';
                break;
            case 260:   // 訂單/發票刪除
                include $PAGES . '/invoice/orderandinvoiceDel.php';
                break;
            case 265:   // 訂單/發票批量刪除
                include $PAGES . '/invoice/orderandinvoiceDelBatch.php';
                break;
            case 270:   // 訂單/發票編輯
                include $PAGES . '/invoice/orderandinvoiceEdit.php';
                break;
            case 300:   // 顧客列表
                include $PAGES . '/customer/CustomerList.php';
                break;
            case "350": // 員工列表
                include $PAGES . '/employee/EmployeeList.php';
                break;
            case "360": // 員工新增
                include $PAGES . '/employee/EmployeeAdd.php';
                break;
            case "370": // 員工刪除
                include $PAGES . '/employee/EmployeeDel.php';
                break;
            case 375:   // 員工批量刪除
                include $PAGES . '/employee/EmployeeDelBatch.php';
                break;
            case "380": // 員工編輯
                include $PAGES . '/employee/EmployeeEdit.php';
                break;
            case "320": // 顧客新增
                include $PAGES . '/customer/CustomerAdd.php';
                break;
            case "330": // 顧客刪除
                include $PAGES . '/customer/CustomerDel.php';
                break;
            case 335:   // 顧客批量刪除
                include $PAGES . '/customer/CustomerDelBatch.php';
                break;
            case "340": // 顧客編輯
                include $PAGES . '/customer/CustomerEdit.php';
                break;
            case "390": // 貨物列表
                include $PAGES . '/product/ProductList.php';
                break;
            case "400": // 貨物新增
                include $PAGES . '/product/ProductAdd.php';
                break;
            case "410": // 貨物刪除
                include $PAGES . '/product/ProductDel.php';
                break;
            case 415:   // 貨物批量刪除
                include $PAGES . '/product/ProductDelBatch.php';
                break;
            case "420": // 貨物編輯
                include $PAGES . '/product/ProductEdit.php';
                break;
            case "430": // 訂單列表
                include $PAGES . '/order/OrderList.php';
                break;
            case "440": // 訂單新增
                include $PAGES . '/order/OrderAdd.php';
                break;
            case "450": // 訂單刪除
                include $PAGES . '/order/OrderDel.php';
                break;
            case "460": // 訂單編輯
                include $PAGES . '/order/OrderEdit.php';
                break;
            case "470": // 出貨紀錄列表
                include $PAGES . '/shipment/ShipmentList.php';
                break;
            case "480": // 出貨紀錄新增
                include $PAGES . '/shipment/ShipmentAdd.php';
                break;
            case "490": // 出貨紀錄編輯
                include $PAGES . '/shipment/ShipmentEdit.php';
                break;
            case "500": // 出貨紀錄刪除
                include $PAGES . '/shipment/ShipmentDel.php';
                break;
            case "510": // 出貨紀錄批量刪除
                include $PAGES . '/shipment/ShipmentDelBatch.php';
                break;
            case "forbidden":
                echo "<p style='text-align:center; color:red;'>權限不足!</p>";
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
