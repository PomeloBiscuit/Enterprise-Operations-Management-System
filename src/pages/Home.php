<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../auth.inc.php';
require_once __DIR__ . '/../i18n.inc.php';
if (can_access_self()) {
?>
<!DOCTYPE html>
<html lang="<?php echo t('html.lang'); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="keywords" content="<?php echo htmlspecialchars(t('html.meta.keywords')); ?>">
    <meta name="description" content="<?php echo htmlspecialchars(t('html.meta.description')); ?>">
    <title><?php echo t('html.title.home'); ?></title>
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
        <h1><?php echo t('home.greeting', ['name' => $_SESSION["admlogin"] ?? '']); ?></h1>
        <p><?php echo t('home.subtitle'); ?></p>
    </div>
    <section class="hero-section">
        <div class="img-ph" role="img" aria-label="<?php echo htmlspecialchars(t('home.ph.hero_aria')); ?>"><?php echo t('home.ph.hero'); ?></div>
        <div class="hero-content">
            <h1><?php echo t('home.hero.title'); ?></h1>
            <p><?php echo t('home.hero.subtitle'); ?></p>
        </div>
    </section>

    <section class="section">
        <h2><?php echo t('home.features.title'); ?></h2>
        <div class="services-container">
            <div class="service-item">
                <div class="img-ph" role="img" aria-label="<?php echo htmlspecialchars(t('home.ph.feature1_aria')); ?>"><?php echo t('home.ph.feature1'); ?></div>
                <p><?php echo t('home.features.item1'); ?></p>
            </div>
            <div class="service-item">
                <div class="img-ph" role="img" aria-label="<?php echo htmlspecialchars(t('home.ph.feature2_aria')); ?>"><?php echo t('home.ph.feature2'); ?></div>
                <p><?php echo t('home.features.item2'); ?></p>
            </div>
            <div class="service-item">
                <div class="img-ph" role="img" aria-label="<?php echo htmlspecialchars(t('home.ph.feature3_aria')); ?>"><?php echo t('home.ph.feature3'); ?></div>
                <p><?php echo t('home.features.item3'); ?></p>
            </div>
        </div>
    </section>

    <section class="section">
        <h2><?php echo t('home.about.title'); ?></h2>
        <div class="about">
            <div class="img-ph" role="img" aria-label="<?php echo htmlspecialchars(t('home.ph.about_aria')); ?>"><?php echo t('home.ph.about'); ?></div>
            <p><?php echo t('home.about.body'); ?></p>
        </div>
    </section>

    <section class="section">
        <h2><?php echo t('home.contact.title'); ?></h2>
        <div class="contact">
            <div class="img-ph" role="img" aria-label="<?php echo htmlspecialchars(t('home.ph.contact_aria')); ?>"><?php echo t('home.ph.contact'); ?></div>
            <p><?php echo t('home.contact.body'); ?></p>
        </div>
    </section>

</body>
</html>
<?php
} else {
    echo "<p style='text-align:center; color:red;'>" . t('common.permission_denied') . "</p>";
}
?>
