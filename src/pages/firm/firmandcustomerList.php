<?php
     // WO-02 起這個模組已收錄進 index.php 路由（Act=200/210/220/230）。
     // 保留這行 require_once 讓本檔「被直接開啟」時仍拿得到 $pdo 與 $_SESSION；
     // 經由 index.php include 時 require_once 會是 no-op。
     require_once __DIR__ . '/../../config.inc.php';
     require_once __DIR__ . '/../../auth.inc.php';
     require_once __DIR__ . '/../../i18n.inc.php';

     if (can_view_business_data()) {

          $L_title = t('firm.list.title');
          $L_blankHint = t('firm.notice.blank_page');
          $L_addTitle = t('firm.add.title');
          $L_fc = t('firm.field.fc');
          $L_name = t('field.name');
          $L_address = t('field.address');
          $L_phone = t('field.phone');
          $L_mobile = t('field.mobile');
          $L_id = t('firm.field.id');
          $L_action = t('common.action');
          $L_edit = t('firm.action.edit');
          $L_delete = t('common.delete');

          echo "
          <h3>$L_title</h3><br><h5>$L_blankHint</h5><hr>
          <a href=index.php?Act=210 class='btn btn-primary'>$L_addTitle</a><br><br>

          <div class=\"table-responsive\">
          <table class=\"table table-bordered table-hover\" >
          <thead border rules=none cellspacing=0 align=center font-weight:bold>
               <tr >
                    <th>$L_fc
                    <th>$L_name
                    <th>$L_address
                    <th>$L_phone
                    <th>$L_mobile
                    <th>Email
                    <th>$L_id
                    <th width=160>$L_action
          </thead>
          <tbody>
          ";

          
          try {
               // 這條查詢沒有任何變數內插，本身不可注入，維持 query() 即可。
               $sql="select * from admin where enabled>0 order by fcname";
               $result = $pdo->query($sql);
          } catch (PDOException $e) {
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
                         class=\"btn btn-primary\">$L_edit</a>
                         <a href=index.php?Act=220&EK={$row['prikey']}
                         class=\"btn btn-primary\" onClick=\"return confirmSubmit()\">$L_delete</a>
                    ";
          }

          echo "
          </tbody>
          </table>
          </div>
          ";
          } else {
               echo "<p style='text-align:center; color:red;'>" . t('common.permission_denied');
          }
?>
