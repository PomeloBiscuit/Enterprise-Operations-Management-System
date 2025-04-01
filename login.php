<?php
require_once("config.inc.php"); // 確保包含資料庫連接配置

if (empty($_POST["btemplogin"])) {
?>

<link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
<style>
    /* 重置樣式 */
    * {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}
html, body {
    width: 100%;
    height: 100%;
    overflow: hidden; /* 防止捲軸 */
}
body {
    font-family: 'Roboto', sans-serif;
    display: flex;
    justify-content: center;
    align-items: center;
    background: url('./images/bg.jpg') no-repeat center center;
    background-size: cover; /* 確保背景填滿螢幕 */
}
.wrapper-container {
    display: flex;
    justify-content: flex-start;
    align-items: center;
    min-height: 100vh;
    width: 100%;
    padding-left: 35%; /* 表單稍偏左 */
}

    .login-container {
        background-color: rgba(255, 255, 255, 0.9);
        border-radius: 20px;
        padding: 40px 30px;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        text-align: center;
        width: 400px;
    }
    h3 {
        margin-bottom: 20px;
        color: rgb(81, 100, 115);
    }
    input[type='text'], input[type='password'] {
        width: 100%;
        padding: 15px;
        margin: 15px 0;
        border: 1px solid #ccc;
        border-radius: 40px;
        font-size: 16px;
        box-sizing: border-box;
    }
    .button-container {
        display: flex;
        flex-direction: column;
        gap: 10px;
        margin-top: 20px;
    }
    .button-container input[type='submit'],
    .button-container input[type='reset'] {
        width: 100%;
        padding: 15px;
        border: none;
        border-radius: 40px;
        font-size: 16px;
        cursor: pointer;
    }
    .button-container input[type='submit'] {
        background-color: rgb(59, 72, 89);
        color: #fff;
    }
    .button-container input[type='reset'] {
        background-color: #6c757d;
        color: #fff;
    }
    .button-container input[type='button'] {
        /* 與登入、清除相同的大小和圓角 */
        width: 100%;
        padding: 15px;
        border: none;
        border-radius: 40px;
        font-size: 16px;
        cursor: pointer;
        background-color: #6c757d; /* 與清除相同，如需改色可自行調整 */
        color: #fff;
    }
    .message-container {
        margin-top: 20px;
        font-size: 14px;
        color: rgb(81, 100, 115);
        text-align: center;
    }
</style>

<div class="wrapper-container"> <!-- 包裹容器 -->
    <div class="login-container"> <!-- 登入容器 -->
        <?php if (!empty($_GET['error'])): ?> <!-- 若有錯誤 -->
            <div style="color: red; font-size: 14px; margin-bottom: 10px;"> <!-- 顯示錯誤訊息 -->
                <?php echo htmlspecialchars($_GET['error']); ?><br> <!-- 顯示錯誤訊息 -->
                即將在 <span id="countdown">5</span> 秒後自動跳轉 <!-- 顯示倒數計時 -->
            </div> <!-- 結束錯誤訊息 -->
            <script>
            let count = 5; // 設定倒數秒數
            let intervalId = setInterval(function() { // 設定倒數計時
                document.getElementById('countdown').textContent = count; // 顯示倒數秒數
                count--; // 減少秒數
                if (count < 0) { // 若倒數結束
                    clearInterval(intervalId); // 清除倒數計時
                    window.location.href = "index.php?Act=150"; // 跳轉至登入頁面
                } // 結束判斷
            }, 1000); // 設定倒數計時
            </script> <!-- 結束倒數計時 -->
        <?php else: ?> <!-- 若無錯誤 -->
            <form method="post" action="?login"> <!-- 登入表單 -->
                <h3>登入</h3> <!-- 標題 -->
                <input type="text" name="admid" placeholder="帳號*" required> <!-- 帳號輸入框 -->
                <input type="password" name="admpw" placeholder="密碼*" required> <!-- 密碼輸入框 -->
                <div class="button-container"> <!-- 按鈕容器 -->
                    <input type="submit" name="btemplogin" value="登入"> <!-- 登入按鈕 -->
                    <input type="reset" value="清除"> <!-- 清除按鈕 -->
                    <input type="button" value="註冊" onclick="location.href='index.php?Act=160';" /> <!-- 註冊按鈕 -->
                </div> <!-- 結束按鈕容器 -->
            </form> <!-- 結束登入表單 -->

            <!-- 僅在無錯誤時顯示此提示 -->
            <div class="message-container"> <!-- 訊息容器 -->
                首次使用請先執行 create.php 後再使用 admin 登入
            </div>
        <?php endif; ?> <!-- 結束錯誤判斷 -->
    </div> <!-- 結束登入容器 -->
</div> <!-- 結束包裹容器 -->

<?php
} else if (isset($_POST["btemplogin"])) { // 若有登入按鈕
    $admid = $_POST['admid']; // 取得帳號
    $admpw = $_POST['admpw']; // 取得密碼

    try {
        $query = "SELECT * FROM User WHERE id = :admid AND pw = MD5(:admpw) AND enabled > 0"; // 查詢使用者
        $stmt = $pdo->prepare($query); // 準備 SQL
        $stmt->execute(['admid' => $admid, 'admpw' => $admpw]); // 執行 SQL

        if ($stmt->rowCount() > 0) { // 若有查詢結果
            $user = $stmt->fetch(); // 取得使用者資料
            $_SESSION["admprikey"] = $user["prikey"]; // 設定 prikey 
            $_SESSION["admid"] = $user["id"];    // 設定 id
            $_SESSION["admemail"] = $user["email"]; // 設定 email
            $_SESSION["admlogin"] = $user["name"];  // 設定 name
            $_SESSION["admclass"] = $user["class"]; // 設定 class
            $_SESSION["admlimit"] = $user["limited"];   // 設定 limited

            if ($user["limited"] > 0) { // 權限大於 0
                @header("Location: index.php?Act=150"); // 修改為跳轉至 case 150
            } else {    // 權限不足
                @header("Location: ?error=權限不足，請聯絡管理員"); // 修改為跳轉至 case 150
                exit(); // 結束程式
            }   // 結束權限判斷
        } else {
            @header("Location: ?error=帳號或密碼不符，請重新輸入"); // 修改為跳轉至 case 150
            exit(); // 結束程式
        }   // 結束查詢結果判斷
    } catch (PDOException $e) { // 例外處理
        echo "<p style='color:red;'>資料庫錯誤：" . htmlspecialchars($e->getMessage()) . "</p>";    // 顯示錯誤訊息
    }   // 結束例外處理
}   // 結束登入按鈕判斷
?>  

