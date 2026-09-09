<?php
     require_once __DIR__ . '/../../auth.inc.php';
     require_once __DIR__ . '/../../i18n.inc.php';
     if (can_view_business_data()) {
          $EK = intval($_GET['EK']);
          if (empty($_POST['btadd'])) {
               try {
                    // $EK 已過 intval()，本身不可注入，但一併改成參數綁定，全模組寫法一致。
                    $sql="select * from admin where prikey = :prikey and
                    enabled>0 order by fcname";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([':prikey' => $EK]);
                    $result = $stmt;
               } catch (PDOException $e) {
                    $error="Error fetching admin: " . $e->getMessage();
                    echo $error;
          }
          if ($row=$result->fetch()) {

               $L_editTitle = t('firm.edit.title');
               $L_blankHint = t('firm.notice.blank_page');
               $L_fc = t('firm.field.fc');
               $L_name = t('field.name');
               $L_address = t('field.address');
               $L_phone = t('field.phone');
               $L_mobile = t('field.mobile');
               $L_email = t('firm.field.email');
               $L_id = t('firm.field.id');
               $L_edit = t('firm.action.edit');
               $L_clear = t('common.clear');

               echo "
               <form method=post action=index.php?Act=$Act&EK=$EK>
               <h3>$L_editTitle</h3><h5>$L_blankHint</h5><hr>

               <div class=\"table-responsive\">
               <table class=\"table table-bordered table-hover\">
                    <tr>
                         <td>$L_fc*
                         <td><input type='text' name=fc
                         value='{$row['fc']}'
                         class=\"form-control\">
                    <tr>
                         <td>$L_name*
                         <td><input type='text' name=fcname
                         value='{$row['fcname']}'
                         class=\"form-control\">
                    <tr>
                         <td>$L_address*
                         <td><input type='text' name=fcaddress
                         value='{$row['fcaddress']}'
                         class=\"form-control\">
                    <tr>
                         <td>$L_phone
                         <td><input type='text' name=fcphone
                         value='{$row['fcphone']}'
                         class=\"form-control\">
                    <tr>
                         <td>$L_mobile
                         <td><input type='text' name=fcphonem
                         value='{$row['fcphonem']}'
                         class=\"form-control\">
                    <tr>
                         <td>$L_email
                         <td><input type='text' name=fcemail
                         value='{$row['fcemail']}'
                         class=\"form-control\">
                    <tr>
                         <td>$L_id
                         <td><input type='text' name=fcid
                         value='{$row['fcid']}'
                         class=\"form-control\">
                    <tr>
                         <td>
                         <td>
                         <input type='submit' name=btadd value='$L_edit'
                              class=\"btn btn-default\">
                         <input type='reset' value='$L_clear'
                              class=\"btn btn-default\">

          </table>
          </div>
          </form>
          ";
          }

     } else {
          try {
               // fc* 欄位走參數綁定修補注入；prikey 亦綁定（已過 intval()），寫法一致。
               $aa="update admin set
                    fc        = :fc,
                    fcname    = :fcname,
                    fcaddress = :fcaddress,
                    fcphone   = :fcphone,
                    fcphonem  = :fcphonem,
                    fcemail   = :fcemail,
                    fcid      = :fcid
                    where prikey = :prikey
                    ";
               $stmt = $pdo->prepare($aa);
               $stmt->execute([
                    ':fc'        => $_POST['fc'],
                    ':fcname'    => $_POST['fcname'],
                    ':fcaddress' => $_POST['fcaddress'],
                    ':fcphone'   => $_POST['fcphone'],
                    ':fcphonem'  => $_POST['fcphonem'],
                    ':fcemail'   => $_POST['fcemail'],
                    ':fcid'      => $_POST['fcid'],
                    ':prikey'    => $EK,
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
