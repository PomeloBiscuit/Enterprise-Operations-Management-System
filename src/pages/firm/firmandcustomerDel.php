<?php
     require_once __DIR__ . '/../../auth.inc.php';
     if (can_view_business_data()) {
          $EK = intval($_GET['EK']);
          try {
               $aa="update admin set
               enabled=0
               where prikey = :prikey
               ";
               $stmt = $pdo->prepare($aa);
               $stmt->execute([':prikey' => $EK]);
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
