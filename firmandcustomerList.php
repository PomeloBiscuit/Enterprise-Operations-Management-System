<?php
     if ($_SESSION["admlimit"]>0) {

          echo "
          <h3>廠商/客戶列表</h3><br><h5>新增 刪除 修改後會呈現空白頁面,請手動更新頁面(再按一次左邊列相同選項)</h5><hr>
          <a href=index.php?Act=210 class='btn btn-primary'>新增廠商/客戶</a><br><br>

          <table class=\"table table-bordered table-hover\" >
          <thead border rules=none cellspacing=0 align=center font-weight:bold>
               <tr >
                    <th>廠商/客戶
                    <th>姓名
                    <th>地址
                    <th>電話
                    <th>行動電話
                    <th>Email
                    <th>編號
                    <th width=160>功能
          </thead>
          <tbody>
          ";

          
          try {
               $sql="select * from admin where enabled>0 order by name";
               $result = $pdo->query($sql);
          } catch (fcPDOException $e) {
               $error="Error fetching fmcr: " . $e->getMessage();
               echo $error;
          }
          while ($row=$result->fetch()) {
               echo "
                    <tr border rules=none cellspacing=0 align=center>
                         <td>{$row['fc']}
                         <td>{$row['fcname']}
                         <td>{$row['fcaddress']}
                         <td>{$row['fcphone']}
                         <td>{$row['fcphonem']}
                         <td>{$row['fcemail']}
                         <td>{$row['fcid']}
                         <td>
                         <a href=index.php?Act=230&EK={$row['prikey']}
                         class=\"btn btn-primary\">修改</a>
                         <a href=index.php?Act=220&EK={$row['prikey']}
                         class=\"btn btn-primary\" onClick=\"return confirmSubmit()\">刪除</a>
                    ";
          }

          echo "
          </tbody>
          </table>
          ";
          } else {
               echo "<p style='text-align:center; color:red;'>權限不足!";
          }
?>
