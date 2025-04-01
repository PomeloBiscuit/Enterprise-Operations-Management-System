<?php
     if ($_SESSION["admlimit"]>0) {
          $EK = intval($_GET['EK']);
          try {
               $aa="update admin set
               enabled=0
               where prikey='{$EK}'
               ";
               $pdo->exec($aa);
          } catch (PDOException $e) {
          $output="Error insert $tableName : " . $e->getMessage();
          echo "<p>$output";
          //exit();
          }
          header("refresh:1;url=index.php?Act=200");
     } else {
          echo "<br><br><br><br><p align=center>權限不足!";
     }
?>
