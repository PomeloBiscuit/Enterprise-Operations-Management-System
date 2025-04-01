<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $stmt = $pdo->prepare("
            UPDATE Shipment
            SET EmployeeID = :EmployeeID,
                OrderID = :OrderID,
                ShipDate = :ShipDate,
                TrackingNumber = :TrackingNumber,
                ShipMethod = :ShipMethod,
                status = :status
            WHERE ShipmentID = :ShipmentID
        ");
        $stmt->execute([
            ':EmployeeID' => $_POST['EmployeeID'],
            ':OrderID' => $_POST['OrderID'],
            ':ShipDate' => $_POST['ShipDate'],
            ':TrackingNumber' => $_POST['TrackingNumber'],
            ':ShipMethod' => $_POST['ShipMethod'],
            ':status' => isset($_POST['status']) ? 1 : 0,
            ':ShipmentID' => $_POST['ShipmentID']
        ]);
        header("Location: index.php?Act=470");
        exit();
    } catch (PDOException $e) {
        echo "<p>錯誤：" . $e->getMessage() . "</p>";
    }
} else {
    $stmt = $pdo->prepare("SELECT * FROM Shipment WHERE ShipmentID = :ShipmentID");
    $stmt->execute([':ShipmentID' => $_GET['id']]);
    $row = $stmt->fetch();
}

// Fetch employees and orders for selection
$employees = $pdo->query("SELECT EmployeeID, EmployeeName FROM Employee")->fetchAll(PDO::FETCH_ASSOC);
$orders = $pdo->query("SELECT OrderID FROM Orders")->fetchAll(PDO::FETCH_ASSOC);
?>

<div style='background-color: white; padding: 20px; border-radius: 10px; box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1); width: 100%;'>
    <h3 style="text-align: center; font-family: 'Noto Sans TC', 'Times New Roman', serif;">編輯出貨紀錄</h3>
    <hr>
    <form method="POST">
        <input type="hidden" name="ShipmentID" value="<?php echo $row['ShipmentID']; ?>">
        <div class="form-group">
            <label>員工ID</label>
            <input type="text" id="employeeSearch" class="form-control" placeholder="搜尋員工ID或名稱">
            <select name="EmployeeID" id="employeeID" class="form-control" required>
                <option value="">選擇員工</option>
                <?php foreach ($employees as $employee): ?>
                    <option value="<?php echo $employee['EmployeeID']; ?>" <?php echo $employee['EmployeeID'] == $row['EmployeeID'] ? 'selected' : ''; ?>><?php echo $employee['EmployeeID'] . ' - ' . $employee['EmployeeName']; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>訂單ID</label>
            <input type="text" id="orderSearch" class="form-control" placeholder="搜尋訂單ID">
            <select name="OrderID" id="orderID" class="form-control" required>
                <option value="">選擇訂單</option>
                <?php foreach ($orders as $order): ?>
                    <option value="<?php echo $order['OrderID']; ?>" <?php echo $order['OrderID'] == $row['OrderID'] ? 'selected' : ''; ?>><?php echo $order['OrderID']; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>出貨日期</label>
            <input type="date" name="ShipDate" class="form-control" value="<?php echo $row['ShipDate']; ?>" required>
        </div>
        <div class="form-group">
            <label>追蹤編號</label>
            <input type="text" name="TrackingNumber" class="form-control" value="<?php echo $row['TrackingNumber']; ?>" required>
        </div>
        <div class="form-group">
            <label>運輸方式</label>
            <input type="text" id="shipMethodSearch" class="form-control" placeholder="搜尋運輸方式">
            <select name="ShipMethod" id="shipMethod" class="form-control" required>
                <option value="">選擇運輸方式</option>
                <option value="Land" <?php echo $row['ShipMethod'] == 'Land' ? 'selected' : ''; ?>>Land</option>
                <option value="Sea" <?php echo $row['ShipMethod'] == 'Sea' ? 'selected' : ''; ?>>Sea</option>
                <option value="Air" <?php echo $row['ShipMethod'] == 'Air' ? 'selected' : ''; ?>>Air</option>
            </select>
        </div>
        <div class="form-group">
            <label>狀態</label>
            <input type="checkbox" name="status" <?php echo $row['status'] ? 'checked' : ''; ?>> 完成
        </div>
        <br>
        <div style="text-align: center;">
            <a href="index.php?Act=470" class="btn btn-secondary">返回</a>
            <button type="reset" class="btn btn-warning text-white">清除</button>
            <button type="submit" class="btn btn-primary">更新</button>
        </div>
    </form>
</div>

<script>
document.getElementById('employeeSearch').addEventListener('input', function() {
    const searchValue = this.value.toLowerCase();
    const options = <?php echo json_encode($employees); ?>;
    const filteredOptions = options.filter(option => 
        option.EmployeeName.toLowerCase().includes(searchValue) || 
        option.EmployeeID.toString().includes(searchValue)
    );
    const employeeSelect = document.getElementById('employeeID');
    employeeSelect.innerHTML = '<option value="">選擇員工</option>';
    filteredOptions.forEach(option => {
        const opt = document.createElement('option');
        opt.value = option.EmployeeID;
        opt.textContent = `${option.EmployeeID} - ${option.EmployeeName}`;
        employeeSelect.appendChild(opt);
    });
    if (filteredOptions.length === 1) {
        employeeSelect.value = filteredOptions[0].EmployeeID;
    }
});

document.getElementById('orderSearch').addEventListener('input', function() {
    const searchValue = this.value.toLowerCase();
    const options = <?php echo json_encode($orders); ?>;
    const filteredOptions = options.filter(option => 
        option.OrderID.toString().includes(searchValue)
    );
    const orderSelect = document.getElementById('orderID');
    orderSelect.innerHTML = '<option value="">選擇訂單</option>';
    filteredOptions.forEach(option => {
        const opt = document.createElement('option');
        opt.value = option.OrderID;
        opt.textContent = option.OrderID;
        orderSelect.appendChild(opt);
    });
    if (filteredOptions.length === 1) {
        orderSelect.value = filteredOptions[0].OrderID;
    }
});

document.getElementById('shipMethodSearch').addEventListener('input', function() {
    const searchValue = this.value.toLowerCase();
    const options = ['Land', 'Sea', 'Air'];
    const filteredOptions = options.filter(option => 
        option.toLowerCase().includes(searchValue)
    );
    const shipMethodSelect = document.getElementById('shipMethod');
    shipMethodSelect.innerHTML = '<option value="">選擇運輸方式</option>';
    filteredOptions.forEach(option => {
        const opt = document.createElement('option');
        opt.value = option;
        opt.textContent = option;
        shipMethodSelect.appendChild(opt);
    });
    if (filteredOptions.length === 1) {
        shipMethodSelect.value = filteredOptions[0];
    }
});

document.getElementById('employeeSearch').addEventListener('blur', function() {
    const searchValue = this.value.toLowerCase();
    const options = <?php echo json_encode($employees); ?>;
    const filteredOptions = options.filter(option => 
        option.EmployeeName.toLowerCase().includes(searchValue) || 
        option.EmployeeID.toString().includes(searchValue)
    );
    if (filteredOptions.length === 1) {
        document.getElementById('employeeID').value = filteredOptions[0].EmployeeID;
    } else {
        const exactMatch = options.find(option => option.EmployeeID.toString() === searchValue || option.EmployeeName.toLowerCase() === searchValue);
        if (exactMatch) {
            document.getElementById('employeeID').value = exactMatch.EmployeeID;
        } else {
            document.getElementById('employeeID').value = '';
        }
    }
});

document.getElementById('orderSearch').addEventListener('blur', function() {
    const searchValue = this.value.toLowerCase();
    const options = <?php echo json_encode($orders); ?>;
    const filteredOptions = options.filter(option => 
        option.OrderID.toString().includes(searchValue)
    );
    if (filteredOptions.length === 1) {
        document.getElementById('orderID').value = filteredOptions[0].OrderID;
    } else {
        const exactMatch = options.find(option => option.OrderID.toString() === searchValue);
        if (exactMatch) {
            document.getElementById('orderID').value = exactMatch.OrderID;
        } else {
            document.getElementById('orderID').value = '';
        }
    }
});

document.getElementById('shipMethodSearch').addEventListener('blur', function() {
    const searchValue = this.value.toLowerCase();
    const options = ['Land', 'Sea', 'Air'];
    const filteredOptions = options.filter(option => 
        option.toLowerCase().includes(searchValue)
    );
    if (filteredOptions.length === 1) {
        document.getElementById('shipMethod').value = filteredOptions[0];
    } else {
        const exactMatch = options.find(option => option.toLowerCase() === searchValue);
        if (exactMatch) {
            document.getElementById('shipMethod').value = exactMatch;
        } else {
            document.getElementById('shipMethod').value = '';
        }
    }
});
</script>
