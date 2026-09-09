<?php
     require_once __DIR__ . '/../../auth.inc.php';
     if (can_view_business_data()) {
          if (empty($_POST['btadd'])) {

               echo "
               <form method=post action=index.php?Act=$Act>
               <h3 >新增廠商/客戶</h3><h5>新增 刪除 修改後會呈現空白頁面,請手動更新頁面(再按一次左邊列相同選項)</h5><hr>
               <table class=\"table table-bordered table-hover\">
                    <tr>
                         <td>廠商/客戶*
                         <td><input type='text' name=fc value=''
                         class=\"form-control\">
                    <tr>
                         <td>姓名*
                         <td><input type='text' name=fcname value=''
                         class=\"form-control\">
                    <tr>
                         <td>地址*
                         <td><input type='text' name=fcaddress value=''
                         class=\"form-control\">
                   
                    <tr>
                         <td>電話
                         <td><input type='text' name=fcphone value=''
                         class=\"form-control\">
                    <tr>
                         <td>行動電話
                         <td><input type='text' name=fcphonem value=''
                         class=\"form-control\">
                    <tr>
                         <td>電子郵件
                         <td><input type='text' name=fcemail value=''
                         class=\"form-control\">
                    <tr>
                         <td>編號
                         <td><input type='text' name=fcid value=''
                         class=\"form-control\">
                    <tr>
                         <td>
                         <td>
                         <input type='submit' name=btadd value='新增'
                         class=\"btn btn-default\">
                         <input type='reset' value='清除'
                         class=\"btn btn-default\">
               </table>
               </form>
               ";

     } else {
          try {
               // 參數綁定：fc* 欄位一律走 prepared statement（修補 SQL injection）。
               // enabled / open / status 是常數，不是使用者輸入，直接寫成字面值 1，
               // 與舊版「insert ... set enabled=1, open=1, status=1」行為一致，
               // 也確保列表頁 where enabled>0 一定篩得到（admin 表另有 DEFAULT 1 作後盾）。
               // 同時把舊版 MySQL 專屬的 INSERT ... SET 改成 SQLite 相容的 (欄位) VALUES (...)。
               $aa = "insert into admin
                    (fc, fcname, fcaddress, fcphone, fcphonem, fcemail, fcid, enabled, open, status)
                    values
                    (:fc, :fcname, :fcaddress, :fcphone, :fcphonem, :fcemail, :fcid, 1, 1, 1)";
               $stmt = $pdo->prepare($aa);
               $stmt->execute([
                    ':fc'        => $_POST['fc'],
                    ':fcname'    => $_POST['fcname'],
                    ':fcaddress' => $_POST['fcaddress'],
                    ':fcphone'   => $_POST['fcphone'],
                    ':fcphonem'  => $_POST['fcphonem'],
                    ':fcemail'   => $_POST['fcemail'],
                    ':fcid'      => $_POST['fcid'],
               ]);
          } catch (PDOException $e) {
               $output="Error insert $tableName : " . $e->getMessage();
               echo "<p>$output";
               //exit();
          }
          header("refresh:1;url=index.php?Act=200");
          }
     } else {
          echo "<br><br><br><br><p align=center>權限不足!";
     }
     ?>
