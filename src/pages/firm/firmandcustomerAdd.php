<?php
     require_once __DIR__ . '/../../auth.inc.php';
     require_once __DIR__ . '/../../i18n.inc.php';
     if (can_view_business_data()) {
          if (empty($_POST['btadd'])) {

               $L_addTitle = t('firm.add.title');
               $L_blankHint = t('firm.notice.blank_page');
               $L_fc = t('firm.field.fc');
               $L_name = t('field.name');
               $L_address = t('field.address');
               $L_phone = t('field.phone');
               $L_mobile = t('field.mobile');
               $L_email = t('firm.field.email');
               $L_id = t('firm.field.id');
               $L_add = t('common.add');
               $L_clear = t('common.clear');

               echo "
               <form method=post action=index.php?Act=$Act>
               <h3 >$L_addTitle</h3><h5>$L_blankHint</h5><hr>
               <table class=\"table table-bordered table-hover\">
                    <tr>
                         <td>$L_fc*
                         <td><input type='text' name=fc value=''
                         class=\"form-control\">
                    <tr>
                         <td>$L_name*
                         <td><input type='text' name=fcname value=''
                         class=\"form-control\">
                    <tr>
                         <td>$L_address*
                         <td><input type='text' name=fcaddress value=''
                         class=\"form-control\">

                    <tr>
                         <td>$L_phone
                         <td><input type='text' name=fcphone value=''
                         class=\"form-control\">
                    <tr>
                         <td>$L_mobile
                         <td><input type='text' name=fcphonem value=''
                         class=\"form-control\">
                    <tr>
                         <td>$L_email
                         <td><input type='text' name=fcemail value=''
                         class=\"form-control\">
                    <tr>
                         <td>$L_id
                         <td><input type='text' name=fcid value=''
                         class=\"form-control\">
                    <tr>
                         <td>
                         <td>
                         <input type='submit' name=btadd value='$L_add'
                         class=\"btn btn-default\">
                         <input type='reset' value='$L_clear'
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
          echo "<br><br><br><br><p align=center>" . t('common.permission_denied');
     }
     ?>
