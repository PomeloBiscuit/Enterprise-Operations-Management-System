<!doctype html>
<html lang="zh-TW">
<head>
     <meta charset="utf-8">
     <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
     <meta name="keywords" content="企業作業管理系統" />
     <meta name="description" content="" />

     <title>企業作業管理系統 — 建立資料庫</title>

     <link rel="stylesheet" href="css/bootstrap.min.css">
</head>
<body>
<div class='container'>
     <div class='row'>
          <div class='col-md-12'>
               <?php
               echo "<p>動作開始...";

               /*
                * SQLite 版本
                * ---------------------------------------------------------------
                * 舊版在這裡用 root 連線做 CREATE DATABASE / GRANT / FLUSH PRIVILEGES。
                * 換成 SQLite 之後那一整段都不需要了：
                *   - 沒有 root、沒有密碼、沒有資料庫使用者
                *   - 資料庫就是一個檔案 data/fiance2024.sqlite
                * 為了讓稽核者「clone 之後一道指令就能看到有資料的畫面」，
                * 本檔可重複執行：每次執行都會刪掉舊檔、重新建表與塞種子資料。
                */

               $dbFile = __DIR__ . '/data/fiance2024.sqlite';
               $dataDir = dirname($dbFile);

               if (!is_dir($dataDir)) {
                    mkdir($dataDir, 0777, true);
               }
               // 重新建立：先刪掉舊的資料庫檔（demo 用途，資料可重建）
               if (file_exists($dbFile)) {
                    unlink($dbFile);
                    echo "<p>已移除舊的資料庫檔，將重新建立。";
               }

               try {
                    $pdo = new PDO('sqlite:' . $dbFile);
                    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_BOTH);
                    // ★ 外鍵約束預設關閉，且是每個連線都要開，否則 ON DELETE CASCADE 靜默失效
                    $pdo->exec('PRAGMA foreign_keys = ON');
               } catch (PDOException $e) {
                    echo "<p>Unable to open the SQLite database: <br>" . htmlspecialchars($e->getMessage());
                    exit();
               }
               echo "<p>Database connection established.";

               // Function to create table
               function createTable($pdo, $tableName, $sql) {
                    try {
                         $pdo->exec($sql);
                         echo "<p>$tableName table successfully created.";
                    } catch (PDOException $e) {
                         echo "<p>Error creating $tableName: " . $e->getMessage();
                    }
               }

               // Function to insert admin
               function insertAdmin($pdo, $tableName) {
                    try {
                         $phone = generateTaiwanPhoneNumber();
                         $mobilePhone = generateTaiwanMobilePhoneNumber();
                         // 舊版用 MySQL 專屬的 INSERT ... SET，密碼用舊式雜湊函式。
                         // SQLite 沒有這兩者：改成標準 INSERT ... VALUES，
                         // 密碼改用 PHP 內建的 password_hash()（bcrypt，$2y$ 開頭、長度 60）。
                         $sql = "INSERT INTO $tableName
                              (datechg, dateadd, name, id, pw, phone, phonem, email, enabled, open, status, limited)
                              VALUES
                              (datetime('now','localtime'), datetime('now','localtime'), :name, :id, :pw, :phone, :phonem, :email, 1, 1, 1, 1)";
                         $stmt = $pdo->prepare($sql);
                         $stmt->execute([
                              ':name'   => 'Admin',
                              ':id'     => 'Admin',
                              ':pw'     => password_hash('123456', PASSWORD_DEFAULT),
                              ':phone'  => $phone,
                              ':phonem' => $mobilePhone,
                              // RFC 2606 保留網域，避免用到真實可註冊的網域
                              ':email'  => 'admin@example.com',
                         ]);
                         echo "<p>Admin user is added to $tableName table.";
                    } catch (PDOException $e) {
                         echo "<p>Error inserting into $tableName: " . $e->getMessage();
                    }
               }

               // Function to generate random phone number
               // 註：以下號碼為亂數產生的「格式正確」示意資料，未逐一比對台灣門號實際配號，
               //     僅供 demo 種子資料使用（見交接說明）。
               function generateTaiwanPhoneNumber() {
                    $prefixes = ['02', '03', '037', '04', '049', '05', '06', '07', '08', '089', '082', '0826', '0836'];
                    $prefix = $prefixes[array_rand($prefixes)];
                    $number = '';
                    for ($i = 0; $i < 7; $i++) {
                         $number .= rand(0, 9);
                    }
                    return "($prefix) " . substr($number, 0, 3) . '-' . substr($number, 3);
               }

               // Function to generate random mobile phone number
               function generateTaiwanMobilePhoneNumber() {
                    $number = '09';
                    for ($i = 0; $i < 8; $i++) {
                         $number .= rand(0, 9);
                    }
                    return substr($number, 0, 4) . '-' . substr($number, 4, 3) . '-' . substr($number, 7);
               }

               // Function to generate random names
               function generateRandomName() {
                    $names = [
                         'Alice', 'Bob', 'Charlie', 'David', 'Eve', 'Frank', 'Grace', 'Hank', 'Ivy', 'Jack',
                         'Kathy', 'Leo', 'Mona', 'Nina', 'Oscar', 'Paul', 'Quincy', 'Rachel', 'Steve', 'Tina',
                         'Uma', 'Vince', 'Wendy', 'Xander', 'Yara', 'Zane', 'Aaron', 'Bella', 'Cody', 'Diana',
                         'Ethan', 'Fiona', 'George', 'Holly', 'Ian', 'Jill', 'Kyle', 'Lara', 'Mason', 'Nora',
                         'Owen', 'Piper', 'Quinn', 'Riley', 'Sam', 'Tara', 'Ulysses', 'Vera', 'Will', 'Xena',
                         'Yvonne', 'Zach', 'Abby', 'Ben', 'Carmen', 'Derek', 'Elena', 'Felix', 'Gina', 'Harry',
                         'Isla', 'Jake', 'Karen', 'Liam', 'Megan', 'Nathan', 'Olivia', 'Peter', 'Queen', 'Ron',
                         'Sophia', 'Tom', 'Ursula', 'Victor', 'Wes', 'Ximena', 'Yosef', 'Zara', 'Adam', 'Brianna',
                         'Chris', 'Daisy', 'Edward', 'Faith', 'Gabe', 'Hannah', 'Isaac', 'Jasmine', 'Kevin', 'Lily'
                    ];
                    return $names[array_rand($names)];
               }

               // Function to insert initial users
               function insertInitialUsers($pdo, $tableName) {
                    for ($i = 0; $i < 10; $i++) { // Insert 10 users
                         $name = generateRandomName();
                         $id = strtolower($name);
                         $email = $id . '@example.com'; // RFC 2606 保留網域
                         $phone = generateTaiwanPhoneNumber();
                         $mobilePhone = generateTaiwanMobilePhoneNumber();

                         try {
                              $sql = "INSERT INTO $tableName
                                   (datechg, dateadd, name, id, pw, phone, phonem, email, enabled, open, status, limited)
                                   VALUES
                                   (datetime('now','localtime'), datetime('now','localtime'), :name, :id, :pw, :phone, :phonem, :email, 1, 1, 1, 0)";
                              $stmt = $pdo->prepare($sql);
                              $stmt->execute([
                                   ':name'   => $name,
                                   ':id'     => $id,
                                   ':pw'     => password_hash('123456', PASSWORD_DEFAULT),
                                   ':phone'  => $phone,
                                   ':phonem' => $mobilePhone,
                                   ':email'  => $email
                              ]);
                              echo "<p>User $name is added to $tableName table.";
                         } catch (PDOException $e) {
                              echo "<p>Error inserting into $tableName: " . $e->getMessage();
                         }
                    }
               }

               // Create User table
               $tableName = "User";
               $sql = "CREATE TABLE $tableName (
                    prikey  INTEGER PRIMARY KEY AUTOINCREMENT, -- 主索引
                    datechg TEXT DEFAULT (datetime('now','localtime')), -- 最後修改的日期時間（見下方 trigger）
                    dateadd TEXT, -- 新增的日期時間
                    name    TEXT, -- 姓名
                    id      TEXT, -- 帳號
                    pw      TEXT, -- 密碼，password_hash() bcrypt
                    phone   TEXT, -- 電話
                    phonem  TEXT, -- 行動電話
                    email   TEXT, -- 電子郵件
                    enabled INTEGER, -- 啟用，1 為啟用，0 為禁用
                    open    INTEGER, -- 1 為開放或使用中，0 為不開放或刪除
                    status  INTEGER, -- 狀態
                    limited INTEGER  -- 權限
               )";
               createTable($pdo, $tableName, $sql);

               // 舊版是 MySQL 的 ON UPDATE CURRENT_TIMESTAMP。SQLite 沒有這個語法，
               // 用 trigger 補上「每次 UPDATE 就把 datechg 設為現在的台北時間」。
               try {
                    $pdo->exec("
                         CREATE TRIGGER User_datechg_on_update
                         AFTER UPDATE ON User
                         FOR EACH ROW
                         WHEN NEW.datechg IS OLD.datechg
                         BEGIN
                              UPDATE User SET datechg = datetime('now','localtime') WHERE prikey = OLD.prikey;
                         END
                    ");
                    echo "<p>User_datechg_on_update trigger created.";
               } catch (PDOException $e) {
                    echo "<p>Error creating trigger: " . $e->getMessage();
               }

               // Insert admin user
               insertAdmin($pdo, $tableName);

               // Insert initial users
               insertInitialUsers($pdo, $tableName);

                  // Create Employee table
                  $tableName = "Employee";
                  $sql = "CREATE TABLE $tableName (
                         EmployeeID   INTEGER PRIMARY KEY AUTOINCREMENT, -- 員工編號
                         EmployeeName TEXT -- 員工姓名
                  )";
                  createTable($pdo, $tableName, $sql);

                  // Insert initial employees
                  for ($i = 0; $i < 10; $i++) {
                         $name = generateRandomName();
                         try {
                               $sql = "INSERT INTO $tableName (EmployeeName) VALUES (:name)";
                               $stmt = $pdo->prepare($sql);
                               $stmt->execute([':name' => $name]);
                               echo "<p>Employee $name is added to $tableName table.";
                         } catch (PDOException $e) {
                               echo "<p>Error inserting into $tableName: " . $e->getMessage();
                         }
                  }

               // Create Product table
               $tableName = "Product";
               $sql = "CREATE TABLE $tableName (
                    ProductID       INTEGER PRIMARY KEY AUTOINCREMENT, -- 產品編號
                    ProductName     TEXT, -- 產品名稱
                    ProductCategory TEXT, -- 產品類別
                    UnitPrice       NUMERIC -- 單價（舊為 DECIMAL(10,2)；SQLite 用 NUMERIC 親和性）
               )";
               createTable($pdo, $tableName, $sql);

               // Insert initial products
               $products = [
                    ['ProductName' => 'Product A', 'ProductCategory' => 'Category ' . rand(1, 10), 'UnitPrice' => 100.00],
                    ['ProductName' => 'Product B', 'ProductCategory' => 'Category ' . rand(1, 10), 'UnitPrice' => 200.00],
                    ['ProductName' => 'Product C', 'ProductCategory' => 'Category ' . rand(1, 10), 'UnitPrice' => 150.00],
                    ['ProductName' => 'Product D', 'ProductCategory' => 'Category ' . rand(1, 10), 'UnitPrice' => 250.00],
                    ['ProductName' => 'Product E', 'ProductCategory' => 'Category ' . rand(1, 10), 'UnitPrice' => 300.00],
                    ['ProductName' => 'Product F', 'ProductCategory' => 'Category ' . rand(1, 10), 'UnitPrice' => 120.00],
                    ['ProductName' => 'Product G', 'ProductCategory' => 'Category ' . rand(1, 10), 'UnitPrice' => 220.00],
                    ['ProductName' => 'Product H', 'ProductCategory' => 'Category ' . rand(1, 10), 'UnitPrice' => 180.00],
                    ['ProductName' => 'Product I', 'ProductCategory' => 'Category ' . rand(1, 10), 'UnitPrice' => 130.00],
                    ['ProductName' => 'Product J', 'ProductCategory' => 'Category ' . rand(1, 10), 'UnitPrice' => 270.00]
               ];
               foreach ($products as $product) {
                    try {
                         $sql = "INSERT INTO $tableName (ProductName, ProductCategory, UnitPrice) VALUES (:ProductName, :ProductCategory, :UnitPrice)";
                         $stmt = $pdo->prepare($sql);
                         $stmt->execute($product);
                         echo "<p>Product {$product['ProductName']} is added to $tableName table.";
                    } catch (PDOException $e) {
                         echo "<p>Error inserting into $tableName: " . $e->getMessage();
                    }
               }

               // Create Customer table
               $tableName = "Customer";
               $sql = "CREATE TABLE $tableName (
                    CustomerID          INTEGER PRIMARY KEY AUTOINCREMENT, -- 顧客編號
                    CustomerName        TEXT, -- 顧客姓名
                    CustomerPhoneNumber TEXT, -- 顧客電話
                    CustomerAddress     TEXT  -- 顧客地址
               )";
               createTable($pdo, $tableName, $sql);

               // Function to insert initial customers
               function insertInitialCustomers($pdo, $tableName) {
                    for ($i = 0; $i < 10; $i++) {
                         $name = generateRandomName();
                         $phone = generateTaiwanMobilePhoneNumber();
                         $address = "Address " . ($i + 1);
                         try {
                              $sql = "INSERT INTO $tableName (CustomerName, CustomerPhoneNumber, CustomerAddress) VALUES (:CustomerName, :CustomerPhoneNumber, :CustomerAddress)";
                              $stmt = $pdo->prepare($sql);
                              $stmt->execute([':CustomerName' => $name, ':CustomerPhoneNumber' => $phone, ':CustomerAddress' => $address]);
                              echo "<p>Customer $name is added to $tableName table.";
                         } catch (PDOException $e) {
                              echo "<p>Error inserting into $tableName: " . $e->getMessage();
                         }
                    }
               }

               // Insert initial customers
               insertInitialCustomers($pdo, $tableName);

               // Create Orders table
               // 舊版另有 ALTER TABLE Orders ADD ShipDate DATE AFTER OrderTime，
               // SQLite 不支援 AFTER，直接把 ShipDate 併進 CREATE TABLE。
               $tableName = "Orders";
               $sql = "CREATE TABLE $tableName (
                    OrderID        INTEGER PRIMARY KEY AUTOINCREMENT, -- 訂單編號
                    OrderTime      TEXT DEFAULT (datetime('now','localtime')), -- 訂單時間
                    ShipDate       TEXT, -- 出貨日期
                    CustomerID     INTEGER, -- 顧客編號
                    ProductID      INTEGER, -- 產品編號
                    EmployeeID     INTEGER, -- 員工編號
                    TrackingNumber TEXT, -- 追蹤編號
                    ShipMethod     TEXT, -- 運輸方式
                    FOREIGN KEY (CustomerID) REFERENCES Customer(CustomerID) ON DELETE CASCADE,
                    FOREIGN KEY (ProductID)  REFERENCES Product(ProductID)   ON DELETE CASCADE,
                    FOREIGN KEY (EmployeeID) REFERENCES Employee(EmployeeID) ON DELETE CASCADE
               )";
               createTable($pdo, $tableName, $sql);

               // Insert initial orders with more varied OrderTime and ShipDate
               $orderCount = 10;
               for ($i = 1; $i <= $orderCount; $i++) {
                    // 訂單時間在過去 30 天的隨機時間
                    $randOrderTimestamp = time() - rand(0, 30*24*60*60);
                    $randomOrderTime = date("Y-m-d H:i:s", $randOrderTimestamp);

                    // 出貨日期在訂單時間同日或最多延後 3 天
                    $randShipTimestamp = $randOrderTimestamp + rand(0, 3*24*60*60);
                    $randomShipDate = date("Y-m-d", $randShipTimestamp);

                    $orderData = [
                         'CustomerID' => rand(1, 10),
                         'ProductID' => rand(1, 10),
                         'EmployeeID' => rand(1, 10),
                         'OrderTime' => $randomOrderTime,
                         'ShipDate' => $randomShipDate,
                         'TrackingNumber' => 'TN' . str_pad($i, 3, '0', STR_PAD_LEFT),
                         'ShipMethod' => (rand(0, 1) ? 'Air' : (rand(0, 1) ? 'Sea' : 'Land'))
                    ];

                    try {
                         $sql = "INSERT INTO Orders
                              (CustomerID, ProductID, EmployeeID, OrderTime, ShipDate, TrackingNumber, ShipMethod)
                              VALUES
                              (:CustomerID, :ProductID, :EmployeeID, :OrderTime, :ShipDate, :TrackingNumber, :ShipMethod)";
                         $stmt = $pdo->prepare($sql);
                         $stmt->execute($orderData);
                         echo "<p>Order with CustomerID {$orderData['CustomerID']} and ProductID {$orderData['ProductID']} is added to $tableName table.";
                    } catch (PDOException $e) {
                         echo "<p>Error inserting into $tableName: " . $e->getMessage();
                    }
               }

               // Create Shipment table
               $tableName = "Shipment";
               $sql = "CREATE TABLE $tableName (
                    ShipmentID     INTEGER PRIMARY KEY AUTOINCREMENT, -- 出貨紀錄編號
                    EmployeeID     INTEGER, -- 員工編號
                    OrderID        INTEGER, -- 訂單編號
                    ShipDate       TEXT, -- 出貨日期
                    TrackingNumber TEXT, -- 追蹤編號
                    ShipMethod     TEXT, -- 運輸方式
                    status         INTEGER, -- 狀態
                    FOREIGN KEY (EmployeeID) REFERENCES Employee(EmployeeID) ON DELETE CASCADE,
                    FOREIGN KEY (OrderID)    REFERENCES Orders(OrderID)      ON DELETE CASCADE
               )";
               createTable($pdo, $tableName, $sql);

               // Insert initial shipments
               $shipments = [
                    ['EmployeeID' => 3, 'OrderID' => 1, 'ShipDate' => '2024-01-01', 'TrackingNumber' => 'TN001', 'ShipMethod' => 'Air'],
                    ['EmployeeID' => 5, 'OrderID' => 2, 'ShipDate' => '2024-01-02', 'TrackingNumber' => 'TN002', 'ShipMethod' => 'Sea'],
                    ['EmployeeID' => 2, 'OrderID' => 3, 'ShipDate' => '2024-01-03', 'TrackingNumber' => 'TN003', 'ShipMethod' => 'Land'],
                    ['EmployeeID' => 7, 'OrderID' => 4, 'ShipDate' => '2024-01-04', 'TrackingNumber' => 'TN004', 'ShipMethod' => 'Air'],
                    ['EmployeeID' => 1, 'OrderID' => 5, 'ShipDate' => '2024-01-05', 'TrackingNumber' => 'TN005', 'ShipMethod' => 'Sea'],
                    ['EmployeeID' => 4, 'OrderID' => 6, 'ShipDate' => '2024-01-06', 'TrackingNumber' => 'TN006', 'ShipMethod' => 'Air'],
                    ['EmployeeID' => 6, 'OrderID' => 7, 'ShipDate' => '2024-01-07', 'TrackingNumber' => 'TN007', 'ShipMethod' => 'Sea'],
                    ['EmployeeID' => 10, 'OrderID' => 8, 'ShipDate' => '2024-01-08', 'TrackingNumber' => 'TN008', 'ShipMethod' => 'Land'],
                    ['EmployeeID' => 8, 'OrderID' => 9, 'ShipDate' => '2024-01-09', 'TrackingNumber' => 'TN009', 'ShipMethod' => 'Air'],
                    ['EmployeeID' => 9, 'OrderID' => 10, 'ShipDate' => '2024-01-10', 'TrackingNumber' => 'TN010', 'ShipMethod' => 'Sea']
               ];
               foreach ($shipments as $shipment) {
                    try {
                         $sql = "INSERT INTO $tableName (EmployeeID, OrderID, ShipDate, TrackingNumber, ShipMethod) VALUES (:EmployeeID, :OrderID, :ShipDate, :TrackingNumber, :ShipMethod)";
                         $stmt = $pdo->prepare($sql);
                         $stmt->execute($shipment);
                         echo "<p>Shipment with EmployeeID {$shipment['EmployeeID']} and OrderID {$shipment['OrderID']} is added to $tableName table.";
                    } catch (PDOException $e) {
                         echo "<p>Error inserting into $tableName: " . $e->getMessage();
                    }
               }

               /* =========================================================
                * 以下三張表：程式碼一直在查，但舊版 create.php 從來沒有建。
                * 欄位是從既有 SQL（Add / Edit / List / Del）反推出來的聯集。
                * ---------------------------------------------------------
                * 已知的既有矛盾（本工單刻意不修，留給使用者決定統一成哪一組）：
                *  1) orderandinvoice：Add 寫 order_id / customer_id / created_at，
                *     Edit 寫 order_number / customer_name；List 的 JOIN 用 order_number。
                *  2) admin：List 用 ORDER BY name，Add 卻只寫 fcname，沒寫 name。
                * ========================================================= */

               // Create orderandinvoice table（訂單與發票）
               $tableName = "orderandinvoice";
               $sql = "CREATE TABLE $tableName (
                    id             INTEGER PRIMARY KEY AUTOINCREMENT, -- 主鍵（Edit/Del/DelBatch 用）
                    order_id       INTEGER, -- 訂單編號（orderandinvoiceAdd.php 寫入）
                    order_number   INTEGER, -- 訂單編號（orderandinvoiceEdit.php 寫入；List 的 JOIN 依據）
                    invoice_number TEXT, -- 發票號碼
                    customer_id    INTEGER, -- 客戶編號（Add 寫入）
                    customer_name  TEXT, -- 客戶名稱（Edit 寫入）
                    amount         NUMERIC, -- 金額
                    status         INTEGER DEFAULT 0, -- 狀態，1 完成 / 0 未完成
                    created_at     TEXT -- 建立時間（Add 以 datetime('now','localtime') 寫入）
               )";
               createTable($pdo, $tableName, $sql);

               // 種子資料：order_id 與 order_number 兩欄都填有效的 OrderID，
               // 讓 orderandinvoiceList.php 的 JOIN（oi.order_number = o.OrderID）抓得到資料。
               $invoiceSeeds = [
                    ['order_id' => 1, 'order_number' => 1, 'invoice_number' => 'INV-0001', 'customer_id' => 1, 'customer_name' => 'Customer 1', 'amount' => 1200, 'status' => 1],
                    ['order_id' => 2, 'order_number' => 2, 'invoice_number' => 'INV-0002', 'customer_id' => 2, 'customer_name' => 'Customer 2', 'amount' => 3400, 'status' => 0],
                    ['order_id' => 3, 'order_number' => 3, 'invoice_number' => 'INV-0003', 'customer_id' => 3, 'customer_name' => 'Customer 3', 'amount' => 5600, 'status' => 1],
               ];
               foreach ($invoiceSeeds as $seed) {
                    try {
                         $sql = "INSERT INTO orderandinvoice
                              (order_id, order_number, invoice_number, customer_id, customer_name, amount, status, created_at)
                              VALUES
                              (:order_id, :order_number, :invoice_number, :customer_id, :customer_name, :amount, :status, datetime('now','localtime'))";
                         $stmt = $pdo->prepare($sql);
                         $stmt->execute($seed);
                         echo "<p>Invoice {$seed['invoice_number']} is added to orderandinvoice table.";
                    } catch (PDOException $e) {
                         echo "<p>Error inserting into orderandinvoice: " . $e->getMessage();
                    }
               }

               // Create tksg table（流水帳 / trackonesspending）
               $tableName = "tksg";
               $sql = "CREATE TABLE $tableName (
                    prikey   INTEGER PRIMARY KEY AUTOINCREMENT, -- 主鍵
                    item     TEXT, -- 項目
                    value    NUMERIC, -- 金額
                    quantity INTEGER, -- 數量
                    date     TEXT, -- 日期（YYYY-MM-DD）
                    time     TEXT, -- 時間（HH:MM）
                    checkid  TEXT  -- 帳目編號（自由文字，非外鍵）
               )";
               createTable($pdo, $tableName, $sql);

               $tksgSeeds = [
                    ['item' => '文具採購', 'value' => 350, 'quantity' => 5, 'date' => '2024-02-01', 'time' => '09:30', 'checkid' => 'CHK-001'],
                    ['item' => '運費',     'value' => 180, 'quantity' => 1, 'date' => '2024-02-03', 'time' => '14:10', 'checkid' => 'CHK-002'],
                    ['item' => '茶水費',   'value' => 90,  'quantity' => 3, 'date' => '2024-02-05', 'time' => '16:45', 'checkid' => 'CHK-003'],
               ];
               foreach ($tksgSeeds as $seed) {
                    try {
                         $sql = "INSERT INTO tksg (item, value, quantity, date, time, checkid)
                                 VALUES (:item, :value, :quantity, :date, :time, :checkid)";
                         $stmt = $pdo->prepare($sql);
                         $stmt->execute($seed);
                         echo "<p>tksg row {$seed['checkid']} added.";
                    } catch (PDOException $e) {
                         echo "<p>Error inserting into tksg: " . $e->getMessage();
                    }
               }

               // Create admin table（廠商與顧客；沿用 User 的欄位命名，但不是使用者表）
               $tableName = "admin";
               $sql = "CREATE TABLE $tableName (
                    prikey    INTEGER PRIMARY KEY AUTOINCREMENT, -- 主鍵
                    name      TEXT DEFAULT '', -- firmandcustomerList.php 以此排序（Add 卻只寫 fcname，既有矛盾）
                    fc        TEXT, -- 廠商 / 客戶
                    fcname    TEXT, -- 姓名
                    fcaddress TEXT, -- 地址
                    fcphone   TEXT, -- 電話
                    fcphonem  TEXT, -- 行動電話
                    fcemail   TEXT, -- 電子郵件
                    fcid      TEXT, -- 編號（自由文字）
                    enabled   INTEGER DEFAULT 1, -- 軟刪除旗標，List 以 enabled>0 過濾
                    open      INTEGER DEFAULT 1, -- 保留
                    status    INTEGER DEFAULT 1  -- 保留
               )";
               createTable($pdo, $tableName, $sql);

               // 種子資料：name 一併填入，讓 firmandcustomerList.php 的 ORDER BY name 有東西可排。
               // 以下皆為虛構資料，email 用 RFC 2606 保留網域。
               $adminSeeds = [
                    ['name' => '甲方採購', 'fc' => '廠商', 'fcname' => '甲方採購', 'fcaddress' => '台北市中正區範例路 1 號', 'fcphone' => '(02) 1234-0001', 'fcphonem' => '0900-000-001', 'fcemail' => 'vendor1@example.com', 'fcid' => 'V-001'],
                    ['name' => '乙方物流', 'fc' => '廠商', 'fcname' => '乙方物流', 'fcaddress' => '新北市板橋區範例街 22 號', 'fcphone' => '(02) 1234-0002', 'fcphonem' => '0900-000-002', 'fcemail' => 'vendor2@example.com', 'fcid' => 'V-002'],
                    ['name' => '丙方客戶', 'fc' => '客戶', 'fcname' => '丙方客戶', 'fcaddress' => '台中市西區範例大道 333 號', 'fcphone' => '(04) 1234-0003', 'fcphonem' => '0900-000-003', 'fcemail' => 'client1@example.com', 'fcid' => 'C-001'],
               ];
               foreach ($adminSeeds as $seed) {
                    try {
                         $sql = "INSERT INTO admin
                              (name, fc, fcname, fcaddress, fcphone, fcphonem, fcemail, fcid, enabled, open, status)
                              VALUES
                              (:name, :fc, :fcname, :fcaddress, :fcphone, :fcphonem, :fcemail, :fcid, 1, 1, 1)";
                         $stmt = $pdo->prepare($sql);
                         $stmt->execute($seed);
                         echo "<p>admin row {$seed['fcid']} added.";
                    } catch (PDOException $e) {
                         echo "<p>Error inserting into admin: " . $e->getMessage();
                    }
               }

               echo "<p><strong>完成。可以用 Admin / 123456 登入。</strong>";
               ?>
          </div>
     </div>
</div>
</body>
</html>
