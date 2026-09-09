<?php
ob_start();
session_start();
require_once __DIR__ . '/../src/config.inc.php';
require_once __DIR__ . '/../src/auth.inc.php';
require_once __DIR__ . '/../src/i18n.inc.php';

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
    390, 400, 410, 415, 420, 430, 440, 450, 455, 460,
    470, 480, 490, 500, 510,
];

if (in_array($Act, $userManagementActs, true) && !can_manage_users()) {
    $Act = 'forbidden';
} elseif (in_array($Act, $businessDataActs, true) && !can_view_business_data()) {
    $Act = 'forbidden';
}
?>

<!doctype html> <!-- 文件類型 -->
<html lang="<?php echo t('html.lang'); ?>"> <!-- 語言設定（隨 i18n 切換） -->
<head>  <!-- 標頭 -->
    <meta charset="utf-8">  <!-- 編碼 -->
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">  <!-- RWD -->
    <title><?php echo t('html.title'); ?></title>   <!-- 網頁標題 -->
    <link rel="stylesheet" href="css/bootstrap.min.css">    <!-- 引入 bootstrap -->
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+TC:wght@400;700&display=swap" rel="stylesheet">  <!-- 標楷體 -->
    <script>
        (function () {
            try {
                var savedTheme = localStorage.getItem('eoms-theme');
                document.documentElement.dataset.theme = savedTheme === 'light' || savedTheme === 'dark'
                    ? savedTheme : (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
                if (localStorage.getItem('eoms-sidebar-collapsed') !== 'false') document.documentElement.classList.add('sidebar-collapsed');
            } catch (error) {
                document.documentElement.dataset.theme = 'light';
                document.documentElement.classList.add('sidebar-collapsed');
            }
        }());
    </script>
    <style> /* CSS 樣式 */
        :root { --page-background: #dce6f0; --page-image-overlay: #dce6f0; --text-color: #17212b; --surface-color: #ffffff; --surface-muted-color: #f2f6fa; --border-color: #c4d0dc; --nav-background: #17212b; --nav-text-color: #ffffff; --nav-hover-background: #29445b; --nav-focus-color: #ffd54f; --link-color: #075ca8; --link-hover-color: #064a88; --primary-color: #075ca8; --primary-text-color: #ffffff; --secondary-color: #4c5c6b; --success-color: #176b3a; --danger-color: #b42318; --warning-color: #8a5a00; --shadow-color: rgba(23, 33, 43, 0.18); }
        :root[data-theme='dark'] { --page-background: #101820; --page-image-overlay: #101820; --text-color: #f2f6fa; --surface-color: #1d2a35; --surface-muted-color: #263744; --border-color: #587083; --nav-background: #0b1117; --nav-text-color: #f8fbff; --nav-hover-background: #29445b; --nav-focus-color: #ffe082; --link-color: #8dcbff; --link-hover-color: #c1e4ff; --primary-color: #176b9e; --primary-text-color: #ffffff; --secondary-color: #526574; --success-color: #247a47; --danger-color: #c43a32; --warning-color: #9a6900; --shadow-color: rgba(0, 0, 0, 0.42); }
        *, *::before, *::after { box-sizing: border-box; }
        body {  /* 背景 */
            font-family: 'Noto Sans TC', sans-serif;    /* 標楷體 */
            margin: 0;  /* 邊距 */
            padding: 0; /* 內距 */
            min-height: 100vh;  /* 最小高度 100% */
            /* 檔名刻意保持短且純 ASCII：原檔名 162 字元且含非 ASCII 字元，
               在 Windows 上 clone 到稍深的目錄會超過 MAX_PATH 260 而 checkout 失敗
               （實測：clone 根目錄 110 字元時即失敗，43 字元時成功）。 */
            background-color: var(--page-background);
            background-image: linear-gradient(var(--page-image-overlay), var(--page-image-overlay)), url('images/hero-matterhorn.jpg');
            background-repeat: no-repeat;
            background-position: center center;
            background-attachment: fixed;
            background-size: cover; /* 背景圖片填滿 */
            display: flex;  /* 使用 flex 排版 */
            flex-direction: column; /* 垂直排列 */
            color: var(--text-color);
        }
        .app-nav { background-color: var(--nav-background); padding: 8px 20px; width: 100%;
            display: flex; /* 使用 flex 排版 */
            align-items: center; justify-content: space-between; gap: 12px; min-width: 0;
        }
        .nav-primary, .nav-utility, .sidebar-links { display: flex; align-items: center; gap: 4px; min-width: 0; }
        .nav-primary { overflow: hidden; }
        .nav-utility { margin-left: auto; flex-shrink: 0; }
        .app-nav a, .app-nav select, .nav-icon-button { color: var(--nav-text-color); text-decoration: none; font-weight: bold; min-height: 36px; padding: 8px 10px; border-radius: 6px; }
        .app-nav a:hover, .app-nav a:focus, .nav-icon-button:hover, .nav-icon-button:focus, .app-nav select:focus { background-color: var(--nav-hover-background); color: var(--nav-text-color); text-decoration: none; outline: 2px solid var(--nav-focus-color); outline-offset: -2px; }
        .nav-icon-button { display: inline-flex; align-items: center; justify-content: center; border: 0; background: transparent; cursor: pointer; }
        .nav-icon-button svg { width: 20px; height: 20px; fill: currentColor; }
        .lang-switch { border-left: 1px solid var(--border-color); padding-left: 8px; }
        .app-nav select { border: 1px solid var(--border-color); background: var(--nav-background); cursor: pointer; }
        .app-nav select option { background: var(--surface-color); color: var(--text-color); }
        .app-shell { display: flex; flex: 1; min-width: 0; }
        .app-sidebar { flex: 0 0 260px; width: 260px; overflow: hidden; background: var(--surface-color); border-right: 1px solid var(--border-color); transition: flex-basis 180ms ease, width 180ms ease; }
        .sidebar-links { flex-direction: column; align-items: stretch; gap: 2px; padding: 16px 10px; width: 260px; }
        .sidebar-links a { color: var(--text-color); padding: 10px 12px; border-radius: 6px; font-weight: 700; text-decoration: none; }
        .sidebar-links a:hover, .sidebar-links a:focus { color: var(--text-color); background: var(--surface-muted-color); outline: 2px solid var(--nav-focus-color); outline-offset: -2px; }
        .sidebar-collapsed .app-sidebar { flex-basis: 0; width: 0; border-right-width: 0; }
        .sr-only { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; border: 0; }
        main a { color: var(--link-color); } main a:hover { color: var(--link-hover-color); }
        main .table, main .table th, main .table td { color: var(--text-color); border-color: var(--border-color); }
        main .table, main .table tbody tr, main .table tbody tr:nth-of-type(odd) { background-color: var(--surface-color); }
        main .table-hover tbody tr:hover { color: var(--text-color); background-color: var(--surface-muted-color); }
        main .form-control, main .form-select, main input, main select, main textarea { color: var(--text-color); background-color: var(--surface-color); border-color: var(--border-color); }
        main .page-link { color: var(--link-color); background-color: var(--surface-color); border-color: var(--border-color); }
        main .page-item.active .page-link { color: var(--primary-text-color); background-color: var(--primary-color); border-color: var(--primary-color); }
        main .btn-primary { background-color: var(--primary-color) !important; border-color: var(--primary-color) !important; color: var(--primary-text-color) !important; } main .btn-secondary { background-color: var(--secondary-color) !important; border-color: var(--secondary-color) !important; color: var(--primary-text-color) !important; } main .btn-success { background-color: var(--success-color) !important; border-color: var(--success-color) !important; color: var(--primary-text-color) !important; } main .btn-danger { background-color: var(--danger-color) !important; border-color: var(--danger-color) !important; color: var(--primary-text-color) !important; } main .btn-warning { background-color: var(--warning-color) !important; border-color: var(--warning-color) !important; color: var(--primary-text-color) !important; }
        :root[data-theme='dark'] main [style*='background-color: white'] { background-color: var(--surface-color) !important; }
        :root[data-theme='dark'] main [style*='color:red'] { color: var(--danger-color) !important; }
        @media (max-width: 1100px) { .nav-primary > a { display: none; } }
        @media (max-width: 640px) { .app-nav { padding: 8px; gap: 4px; } .app-nav a, .nav-icon-button, .app-nav select { padding: 8px 6px; } .nav-utility a { font-size: .9rem; } .lang-switch { padding-left: 4px; } main { margin-left: 8px; margin-right: 8px; padding: 12px; } }
        main {  /* 主要內容 */
            margin-top: 20px; /* 確保內容與導覽列有間距 */
            flex: 1;    /* 佔滿剩餘空間 */
            padding: 20px;  /* 內距 */
            border-radius: 10px;    /* 圓角 */
            margin: 20px 20px;  /* 上下 20px, 左右自動 */
            height: auto;   /* 高度自動 */
            /* 修復前實測：clientWidth 1265 時 main 右緣 1285，整頁溢出 20px。
               移除 width: 100% 讓 flex item 自然 stretch，保留內容區寬度且不再把左右 margin 推出視窗。 */
        }
        footer {    /* 頁尾 */
            text-align: center; /* 文字置中 */
            padding: 15px 0;    /* 上下 15px */
            background-color: var(--surface-color);
            color: var(--text-color);
        }
    </style>
</head>
<body>  <!-- 頁面主體 -->
<?php $__locale = current_locale(); ?>
    <nav class="app-nav" aria-label="<?php echo htmlspecialchars(t('nav.label')); ?>">
        <div class="nav-primary">
        <?php if (isset($_SESSION['admid'])): ?>
        <button type="button" class="nav-icon-button" id="sidebar-toggle" aria-controls="app-sidebar" aria-expanded="false" aria-label="<?php echo htmlspecialchars(t('nav.sidebar.toggle')); ?>">
            <svg viewBox="0 0 16 16" aria-hidden="true"><path fill-rule="evenodd" d="M2.5 12.5A.5.5 0 0 1 3 12h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5m0-5A.5.5 0 0 1 3 7h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5m0-5A.5.5 0 0 1 3 2h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5"/></svg>
        </button>
        <?php if (can_access_self()): ?><a data-nav-link href="index.php?Act=150"><?php echo t('nav.home'); ?></a><?php endif; ?>
        <?php if (can_manage_users()): ?><a data-nav-link href="index.php?Act=110"><?php echo t('nav.users'); ?></a><?php endif; ?>
        <?php if (can_view_business_data()): ?>
            <a data-nav-link href="index.php?Act=350"><?php echo t('nav.employees'); ?></a><a data-nav-link href="index.php?Act=300"><?php echo t('nav.customers'); ?></a><a data-nav-link href="index.php?Act=390"><?php echo t('nav.products'); ?></a><a data-nav-link href="index.php?Act=430"><?php echo t('nav.orders'); ?></a>
        <?php endif; ?>
        <?php endif; ?>
        </div>
        <div class="nav-utility">
            <button type="button" class="nav-icon-button" id="theme-toggle" aria-label="<?php echo htmlspecialchars(t('nav.theme.to_dark')); ?>"><svg viewBox="0 0 16 16" aria-hidden="true"><path d="M8 0a.5.5 0 0 1 .5.5v1.05a.5.5 0 0 1-1 0V.5A.5.5 0 0 1 8 0m0 14.45a.5.5 0 0 1 .5.5V16a.5.5 0 0 1-1 0v-1.05a.5.5 0 0 1 .5-.5M3.05 2.343a.5.5 0 0 1 .707 0L4.5 3.086a.5.5 0 1 1-.707.707l-.743-.743a.5.5 0 0 1 0-.707m9.193 9.193a.5.5 0 0 1 .707 0l.743.743a.5.5 0 0 1-.707.707l-.743-.743a.5.5 0 0 1 0-.707M0 8a.5.5 0 0 1 .5-.5h1.05a.5.5 0 0 1 0 1H.5A.5.5 0 0 1 0 8m14.45 0a.5.5 0 0 1 .5-.5H16a.5.5 0 0 1 0 1h-1.05a.5.5 0 0 1-.5-.5M3.05 13.657a.5.5 0 0 1 0-.707l.743-.743a.5.5 0 0 1 .707.707l-.743.743a.5.5 0 0 1-.707 0m9.193-9.193a.5.5 0 0 1 0-.707l.743-.743a.5.5 0 0 1 .707.707l-.743.743a.5.5 0 0 1-.707 0M8 4a4 4 0 1 1 0 8 4 4 0 0 1 0-8"/></svg></button>
            <label class="sr-only" for="language-select"><?php echo t('nav.lang.label'); ?></label>
            <span class="lang-switch"><select id="language-select" aria-label="<?php echo htmlspecialchars(t('nav.lang.label')); ?>" onchange="if (this.value) { window.location.href = this.value; }">
                <option value="<?php echo htmlspecialchars(i18n_switch_url('zh-TW')); ?>"<?php echo $__locale === 'zh-TW' ? ' selected' : ''; ?>><?php echo t('nav.lang.zh_tw'); ?></option>
                <option value="<?php echo htmlspecialchars(i18n_switch_url('en')); ?>"<?php echo $__locale === 'en' ? ' selected' : ''; ?>><?php echo t('nav.lang.en'); ?></option>
            </select></span>
        <?php if (isset($_SESSION['admid'])): ?>
            <?php if (can_access_self()): ?><a data-nav-link href="index.php?Act=100"><?php echo t('nav.profile'); ?></a><?php endif; ?>
            <a data-nav-link href="logout.php"><?php echo t('nav.logout'); ?></a>
        <?php endif; ?>
        </div>
    </nav>
    <div class="app-shell">
<?php if (isset($_SESSION['admid'])): ?>
    <aside class="app-sidebar" id="app-sidebar" aria-label="<?php echo htmlspecialchars(t('nav.sidebar.label')); ?>"><div class="sidebar-links">
        <?php if (can_access_self()): ?><a data-nav-link href="index.php?Act=150"><?php echo t('nav.home'); ?></a><?php endif; ?>
        <?php if (can_manage_users()): ?><a data-nav-link href="index.php?Act=110"><?php echo t('nav.users'); ?></a><?php endif; ?>
        <?php if (can_view_business_data()): ?><a data-nav-link href="index.php?Act=350"><?php echo t('nav.employees'); ?></a><a data-nav-link href="index.php?Act=300"><?php echo t('nav.customers'); ?></a><a data-nav-link href="index.php?Act=390"><?php echo t('nav.products'); ?></a><a data-nav-link href="index.php?Act=430"><?php echo t('nav.orders'); ?></a><?php endif; ?>
    </div></aside>
<?php endif; ?>
<main id="main-content">  <!-- 主要內容 -->
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
                    echo "<p style='text-align:center; color:red;'>" . t('common.permission_denied') . "</p>";
                }
                break;
            case "130": // 使用者刪除
                if (is_admin()) {
                    include $PAGES . '/user/adminDel.php';
                } else {
                    echo "<p style='text-align:center; color:red;'>" . t('common.permission_denied') . "</p>";
                }
                break;
            case "135": // 使用者批量刪除
                if (is_admin()) {
                    include $PAGES . '/user/adminDelBatch.php';
                } else {
                    echo "<p style='text-align:center; color:red;'>" . t('common.permission_denied') . "</p>";
                }
                break;
            case "140": // 使用者新增
                if (is_admin()) {
                    include $PAGES . '/user/adminAdd.php';
                } else {
                    echo "<p style='text-align:center; color:red;'>" . t('common.permission_denied') . "</p>";
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
            case 455:   // 訂單批量刪除（OrderList 的刪除表單改走前端控制器；原本直接 POST 到 OrderDelBatch.php）
                include $PAGES . '/order/OrderDelBatch.php';
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
                echo "<p style='text-align:center; color:red;'>" . t('common.permission_denied') . "</p>";
                break;
        }
        ?>
    </div>  <!-- 結束容器 -->
</main> <!-- 結束主要內容 -->
    </div>
<?php if (isset($_SESSION['admid'])): ?>    <!-- 判斷是否有登入 -->
<footer>    <!-- 頁尾 -->
    <div class="footer-content">   <!-- 頁尾內容 -->
        <p>
            <?php echo t('footer.copyright'); ?><br>
            <?php echo t('footer.notice'); ?>
        </p>
    </div>  <!-- 結束頁尾內容 -->
</footer>   <!-- 結束頁尾 -->
<?php endif; ?> <!-- 結束判斷是否有登入 -->
<!-- Optional JavaScript -->
<script src="js/jquery-3.6.0.min.js"></script>  <!-- 引入 jQuery -->
<script src="js/bootstrap.min.js"></script> <!-- 引入 bootstrap -->
<script>
    (function () {
        var root = document.documentElement;
        var themeToggle = document.getElementById('theme-toggle');
        var sidebarToggle = document.getElementById('sidebar-toggle');
        var sidebar = document.getElementById('app-sidebar');
        function setTheme(theme) {
            root.dataset.theme = theme;
            if (themeToggle) themeToggle.setAttribute('aria-label', theme === 'dark' ? <?php echo json_encode(t('nav.theme.to_light')); ?> : <?php echo json_encode(t('nav.theme.to_dark')); ?>);
        }
        setTheme(root.dataset.theme || 'light');
        if (themeToggle) themeToggle.addEventListener('click', function () {
            var nextTheme = root.dataset.theme === 'dark' ? 'light' : 'dark';
            setTheme(nextTheme);
            try { localStorage.setItem('eoms-theme', nextTheme); } catch (error) {}
        });
        if (sidebarToggle && sidebar) {
            function setSidebar(collapsed) { root.classList.toggle('sidebar-collapsed', collapsed); sidebarToggle.setAttribute('aria-expanded', String(!collapsed)); }
            setSidebar(root.classList.contains('sidebar-collapsed'));
            sidebarToggle.addEventListener('click', function () {
                var collapsed = !root.classList.contains('sidebar-collapsed');
                setSidebar(collapsed);
                try { localStorage.setItem('eoms-sidebar-collapsed', String(collapsed)); } catch (error) {}
            });
        }
    }());
</script>
</body> <!-- 結束頁面主體 -->
</html>
