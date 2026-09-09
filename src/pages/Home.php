<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../auth.inc.php';
if (can_access_self()) {
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="keywords" content="企業形象, 作業管理, 資訊系統">
    <meta name="description" content="企業形象頁面，展示企業作業管理系統的功能與優勢。">
    <title>企業形象頁面</title>
    <link rel="stylesheet" href="css/bootstrap.min.css">
    <style>
        body {
            font-family: 'Noto Sans TC', sans-serif;
            margin: 0;
            padding: 0;
            background: #f5f5f5;
        }

        nav {
            background-color: rgba(0, 0, 0, 0.8);
            padding: 10px 20px;
            width: 100%;
            display: flex;
            justify-content: flex-end; /* 讓導覽列內容靠右 */
        }
        nav a {
            color: white;
            text-decoration: none;
            margin-left: 15px;
            font-weight: bold;
        }
        nav a:hover {
            text-decoration: underline;
        }

        .hero-section {
            text-align: center;
            color: #486995;
            padding: 80px 20px;
            position: relative;
        }

        .hero-section img,
        .hero-section .img-ph {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover; /* 確保圖片覆蓋整個區塊並保持比例 */
            z-index: 0; /* 讓圖片位於文字下方 */
            opacity: 0.7; /* 加入半透明效果 */
        }

        .hero-content {
            position: relative;
            z-index: 1; /* 確保文字顯示在圖片上方 */
        }

        .hero-section h1 {
            font-size: 3.5em;
            font-weight: bold;
        }

        .hero-section p {
            font-size: 1.5em;
            margin-top: 20px;
        }

        .section {
            background-color: #ffffff; /* 純白背景 */
            padding: 40px 20px;
            margin: 20px auto;
            max-width: 1200px;
        }

        .section h2 {
            text-align: center;
            font-size: 2.5em;
            font-weight: bold;
            color: #486995;
            margin-bottom: 40px;
        }

        .services-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }

        .service-item {
            text-align: center;
            background: #ffffff;
            padding: 20px;
            border: 1px solid #e0e0e0; /* 純白平面效果 */
        }

        .service-item img,
        .service-item .img-ph {
            width: 240px;
            height: 240px;
            margin-bottom: 20px;
        }

        .service-item p {
            font-size: 1.1em;
        }

        .about, .contact {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .about img, .contact img,
        .about .img-ph, .contact .img-ph {
            width: 150px;
            height: 150px;
            border: 1px solid #e0e0e0;
        }

        /* 圖片佔位框：原圖因版權疑慮已移入 _quarantine/，等待自行產製的替代圖。
           刻意做成明顯的虛線空框，避免佔位被誤認為完成品。

           1440x900 實測（HTTP，非 file://）：hero 1425x375、service-item 240x240、
           about/contact 150x150，文字不溢出，頁面無水平捲動。
           2. 選擇器要蓋過 .about img/.contact img 的 (0,2,0)，故此處也寫成兩層；
              只用 .img-ph (0,1,0) 會被覆蓋成 1px solid，實測過。
           3. flex-shrink:0 是必要的：.about/.contact 是 flex 容器，不寫的話這個框
              會被旁邊的 <p> 壓到 73px（實測值）。註：原本的 <img> 也有同樣的壓縮
              問題（宣告 150px、實測 60px），那是既有行為，本次不動。 */
        .img-ph,
        .about .img-ph, .contact .img-ph {
            display: flex;
            align-items: center;
            justify-content: center;
            box-sizing: border-box;
            flex-shrink: 0;
            padding: 8px;
            border: 2px dashed #b0bcc9;
            background-color: #f4f6f8;
            color: #6b7a8c;
            font-size: 0.95em;
            line-height: 1.4;
            text-align: center;
        }

        .about p, .contact p {
            font-size: 1.2em;
            line-height: 1.6;
        }

        footer {
            text-align: center;
            background-color: #123456;
            color: white;
            padding: 20px;
            margin-top: 40px;
        }
    </style>
</head>
<body>
    <nav>
        <!-- 在此可放置導覽連結，如需連至 index.php?Act=150 -->
        <!-- 您可自行新增或修改 navbar 內容 -->
    </nav>
    <div class="container">
        <h1>歡迎, <?php echo $_SESSION["admlogin"]; ?>!</h1>
        <p>這是您的首頁。</p>
    </div>
    <section class="hero-section">
        <div class="img-ph" role="img" aria-label="待補：首頁主視覺背景圖">＜待補圖：首頁主視覺背景＞</div>
        <div class="hero-content">
            <h1>歡迎來到企業作業管理系統</h1>
            <p>我們提供最先進的作業管理解決方案，助力企業效率提升。</p>
        </div>
    </section>

    <section class="section">
        <h2>系統功能</h2>
        <div class="services-container">
            <div class="service-item">
                <div class="img-ph" role="img" aria-label="待補：功能1 示意圖">＜待補圖：功能1＞</div>
                <p>功能1：即時作業報表，讓您隨時掌握企業作業狀況。</p>
            </div>
            <div class="service-item">
                <div class="img-ph" role="img" aria-label="待補：功能2 示意圖">＜待補圖：功能2＞</div>
                <p>功能2：自動化訂單與發票管理，減少人為錯誤。</p>
            </div>
            <div class="service-item">
                <div class="img-ph" role="img" aria-label="待補：功能3 示意圖">＜待補圖：功能3＞</div>
                <p>功能3：員工與顧客資料管理，提升內部管理效率。</p>
            </div>
        </div>
    </section>

    <section class="section">
        <h2>關於我們</h2>
        <div class="about">
            <div class="img-ph" role="img" aria-label="待補：關於我們 示意圖">＜待補圖：關於我們＞</div>
            <p>我們是一家專注於提供企業作業管理解決方案的公司，幫助企業提升效率與效益。我們的系統功能強大、操作簡便，深受客戶信賴。
                而其使命是簡化您的日常營運，讓您能將更多精力投入到業務的核心發展上。從訂單處理到資料分析，為您打造高效、智慧的管理體驗，
                致力於為每位客戶提供量身訂製的解決方案!
            </p>
        </div>
    </section>

    <section class="section">
        <h2>聯絡我們</h2>
        <div class="contact">
            <div class="img-ph" role="img" aria-label="待補：聯絡我們 示意圖">＜待補圖：聯絡我們＞</div>
            <p>如果您有任何問題或需要進一步了解我們的系統，請隨時聯絡我們。您可以通過電子郵件或電話與我們聯繫，我們的團隊將竭誠為您服務!</p>
        </div>
    </section>

</body>
</html>
<?php
} else {
    echo "<p style='text-align:center; color:red;'>權限不足!</p>";
}
?>
