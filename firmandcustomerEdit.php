<?php
     if ($_SESSION["admlimit"]>0) {
          $EK = intval($_GET['EK']);
          if (empty($_POST['btadd'])) {
               try {
                    $sql="select * from admin where prikey='{$EK}' and
                    enabled>0 order by name";
                    $result = $pdo->query($sql);
               } catch (PDOException $e) {
                    $error="Error fetching admin: " . $e->getMessage();
                    echo $error;
          }
          if ($row=$result->fetch()) {

               echo "
               <form method=post action=index.php?Act=$Act&EK=$EK>
               <h3>修改廠商/客戶</h3><h5>新增 刪除 修改後會呈現空白頁面,請手動更新頁面(再按一次左邊列相同選項)</h5><hr>

               <table class=\"table table-bordered table-hover\">
                    <tr>
                         <td>廠商/客戶*
                         <td><input type='text' name=fc
                         value='{$row['fc']}'
                         class=\"form-control\">
                    <tr>
                         <td>姓名*
                         <td><input type='text' name=fcname
                         value='{$row['fcname']}'
                         class=\"form-control\">
                    <tr>
                         <td>地址*
                         <td><input type='text' name=fcaddress
                         value='{$row['fcaddress']}'
                         class=\"form-control\">
                    <tr>
                         <td>電話
                         <td><input type='text' name=fcphone
                         value='{$row['fcphone']}'
                         class=\"form-control\">
                    <tr>
                         <td>行動電話
                         <td><input type='text' name=fcphonem
                         value='{$row['fcphonem']}'
                         class=\"form-control\">
                    <tr>
                         <td>電子郵件
                         <td><input type='text' name=fcemail
                         value='{$row['fcemail']}'
                         class=\"form-control\">
                    <tr>
                         <td>編號
                         <td><input type='text' name=fcid 
                         value='{$row['fcid']}'
                         class=\"form-control\">
                    <tr>
                         <td>
                         <td>
                         <input type='submit' name=btadd value='修改'
                              class=\"btn btn-default\">
                         <input type='reset' value='清除'
                              class=\"btn btn-default\">

          </table>
          </form>
          ";
          }

     } else {
          try {
               $aa="update admin set
                    fc='{$_POST['fc']}',
                    fcname='{$_POST['fcname']}',
                    fcaddress='{$_POST['fcaddress']}',
                    fcphone='{$_POST['fcphone']}',
                    fcphonem='{$_POST['fcphonem']}',
                    fcemail='{$_POST['fcemail']}',
                    fcid='{$_POST['fcid']}'
                    where prikey='{$EK}'
                    ";
               $pdo->exec($aa);
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

