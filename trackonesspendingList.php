<?php
// 這個頁面沒有被 index.php 的路由收錄，只能被直接開啟；
// 補上 bootstrap 讓它拿得到 $pdo 與 $_SESSION（與 CustomerAdd.php 等頁面一致）。
require_once __DIR__ . '/config.inc.php';

$sortOrder = isset($_GET['sort']) && $_GET['sort'] === 'asc' ? 'ASC' : 'DESC';
$nextSortOrder = $sortOrder === 'ASC' ? 'desc' : 'asc';

try {
    $stmt = $pdo->query("SELECT * FROM tksg ORDER BY prikey $sortOrder");
    echo "<h3 style='text-align: center; font-family: \"Noto Sans TC\", \"Times New Roman\", serif;'>流水帳清單</h3><hr>";
    echo "<a href='index.php?Act=290' class='btn btn-success mb-3'>新增流水帳</a>";
    echo "<table class='table table-bordered'>";
    echo "<thead>
            <tr>
                <th><a href='index.php?Act=280&sort=$nextSortOrder'>ID</a></th>
                <th>項目</th>
                <th>金額</th>
                <th>數量</th>
                <th>日期</th>
                <th>時間</th>
                <th>操作</th>
            </tr>
          </thead>";
    echo "<tbody>";
    while ($row = $stmt->fetch()) {
        echo "<tr>";
        echo "<td>{$row['prikey']}</td>";
        echo "<td>{$row['item']}</td>";
        echo "<td>{$row['value']}</td>";
        echo "<td>{$row['quantity']}</td>";
        echo "<td>{$row['date']}</td>";
        echo "<td>{$row['time']}</td>";
        echo "<td>
            <a href='index.php?Act=310&id={$row['prikey']}' class='btn btn-primary btn-sm'>編輯</a>
            <a href='index.php?Act=300&id={$row['prikey']}' class='btn btn-danger btn-sm' onclick='return confirm(\"確定要刪除嗎？\");'>刪除</a>
        </td>";
        echo "</tr>";
    }
    echo "</tbody></table>";
} catch (PDOException $e) {
    echo "<p>錯誤：" . $e->getMessage() . "</p>";
}
?>
