<!doctype html>
<html lang="zh-TW">
<head>
     <!-- Required meta tags -->
     <meta charset="utf-8">
     <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
     <meta name="keywords" content="企業作業管理系統" />
     <meta name="description" content="" />
     
     <title>企業作業管理系統</title>
     
     <!-- Bootstrap CSS -->
     <link rel="stylesheet" href="css/bootstrap.min.css">
</head>
<body>
<div class='container'>
     <div class='row'>
          <div class='col-md-12'>
               <?php
               echo "<p>動作開始...";

               // DB owner
               $host = "localhost";
               $root = "root";
               $root_password = "";

               // create new database
               $db = "Fiance2024";
               $user = 'Fiance2024';
               $pass = '123456';

               try {
                    $dbh = new PDO("mysql:host=$host", $root, $root_password);
                    $dbh->exec("
                         CREATE DATABASE {$db}
                         DEFAULT CHARACTER SET utf8
                         DEFAULT COLLATE utf8_general_ci;
                         GRANT ALL PRIVILEGES
                         ON {$db}.*
                         TO {$user}@localhost
                         IDENTIFIED BY '{$pass}';
                         FLUSH PRIVILEGES;
                    ") or die(print_r($dbh->errorInfo(), true));
               } catch (PDOException $e) {
                    die("DB ERROR: " . $e->getMessage());
               }

               // connect to database
               try {
                    $pdo = new PDO("mysql:host=$host;dbname={$db}", "{$user}", "{$pass}");
                    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    $pdo->exec('SET NAMES "utf8"');
               } catch (PDOException $e) {
                    echo "<p>Unable to connect to the database server: <br>" . $e->getMessage();
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
                         $sql = "INSERT INTO $tableName SET
                              datechg = NOW(),
                              dateadd = NOW(),
                              name = 'Admin',
                              id = 'Admin',
                              pw = MD5('123456'),
                              phone = '$phone',
                              phonem = '$mobilePhone',
                              email = 'Admin@gmail.com',
                              enabled = 1,
                              open = 1,
                              status = 1,
                              limited = 1";
                         $pdo->exec($sql);
                         echo "<p>Admin user is added to $tableName table.";
                    } catch (PDOException $e) {
                         echo "<p>Error inserting into $tableName: " . $e->getMessage();
                    }
               }

               // Function to generate random phone number
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
                         $email = $id . '@gmail.com';
                         $phone = generateTaiwanPhoneNumber();
                         $mobilePhone = generateTaiwanMobilePhoneNumber();

                         try {
                              $sql = "INSERT INTO $tableName SET
                                   datechg = NOW(),
                                   dateadd = NOW(),
                                   name = :name,
                                   id = :id,
                                   pw = MD5('123456'),
                                   phone = :phone,
                                   phonem = :phonem,
                                   email = :email,
                                   enabled = 1,
                                   open = 1,
                                   status = 1,
                                   limited = 0";
                              $stmt = $pdo->prepare($sql);
                              $stmt->execute([
                                   ':name' => $name,
                                   ':id' => $id,
                                   ':phone' => $phone,
                                   ':phonem' => $mobilePhone,
                                   ':email' => $email
                              ]);
                              echo "<p>User $name is added to $tableName table.";
                         } catch (PDOException $e) {
                              echo "<p>Error inserting into $tableName: " . $e->getMessage();
                         }
                    }
               }

               // Function to check and insert data
               function checkAndInsert($pdo, $tableName, $checkColumn, $checkValue, $insertSql) {
                    try {
                         $sql = "SELECT COUNT(*) FROM $tableName WHERE $checkColumn = '$checkValue'";
                         $result = $pdo->query($sql);
                         $row = $result->fetch();
                         if ($row[0] == 0) {
                              $pdo->exec($insertSql);
                              echo "<p>$checkValue is added to $tableName table.";
                         } else {
                              echo "<p>$checkValue already exists in $tableName table.";
                         }
                    } catch (PDOException $e) {
                         echo "<p>Error checking $tableName: " . $e->getMessage();
                    }
               }

               // Create User table
               $tableName = "User";
               $sql = "CREATE TABLE $tableName (
                    prikey INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '主索引',
                    datechg TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '最後修改的日期時間',
                    dateadd DATETIME COMMENT '新增的日期時間',
                    name CHAR(255) COMMENT '姓名',
                    id CHAR(255) COMMENT '帳號',
                    pw CHAR(255) COMMENT '密碼,md5 加密',
                    phone CHAR(255) COMMENT '電話',
                    phonem CHAR(20) COMMENT '行動電話',
                    email CHAR(255) COMMENT '電子郵件',
                    enabled INT COMMENT '啟用,1 為啟用,0 為禁用',
                    open INT COMMENT '1 為開放或使用中,0 為不開放或刪除',
                    status INT COMMENT '狀態',
                    limited INT COMMENT '權限'
               ) DEFAULT CHARACTER SET utf8 ENGINE=InnoDB";
               createTable($pdo, $tableName, $sql);

               // Insert admin user
               insertAdmin($pdo, $tableName);

               // Insert initial users
               insertInitialUsers($pdo, $tableName);

                  // Create Employee table
                  $tableName = "Employee";
                  $sql = "CREATE TABLE $tableName (
                         EmployeeID INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '員工編號',
                         EmployeeName CHAR(255) COMMENT '員工姓名'
                  ) DEFAULT CHARACTER SET utf8 ENGINE=InnoDB";
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
                    ProductID INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '產品編號',
                    ProductName CHAR(255) COMMENT '產品名稱',
                    ProductCategory CHAR(255) COMMENT '產品類別',
                    UnitPrice DECIMAL(10,2) COMMENT '單價'
               ) DEFAULT CHARACTER SET utf8 ENGINE=InnoDB";
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
                    CustomerID INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '顧客編號',
                    CustomerName CHAR(255) COMMENT '顧客姓名',
                    CustomerPhoneNumber CHAR(255) COMMENT '顧客電話',
                    CustomerAddress CHAR(255) COMMENT '顧客地址'
               ) DEFAULT CHARACTER SET utf8 ENGINE=InnoDB";
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
               $tableName = "Orders";
               $sql = "CREATE TABLE $tableName (
                    OrderID INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '訂單編號',
                    OrderTime TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '訂單時間',
                    CustomerID INT UNSIGNED COMMENT '顧客編號',
                    ProductID INT UNSIGNED COMMENT '產品編號',
                    EmployeeID INT UNSIGNED COMMENT '員工編號',
                    TrackingNumber CHAR(255) COMMENT '追蹤編號',
                    ShipMethod CHAR(255) COMMENT '運輸方式',
                    FOREIGN KEY (CustomerID) REFERENCES Customer(CustomerID) ON DELETE CASCADE,
                    FOREIGN KEY (ProductID) REFERENCES Product(ProductID) ON DELETE CASCADE,
                    FOREIGN KEY (EmployeeID) REFERENCES Employee(EmployeeID) ON DELETE CASCADE
               ) DEFAULT CHARACTER SET utf8 ENGINE=InnoDB";
               createTable($pdo, $tableName, $sql);

               // Add ShipDate column to Orders table
               try {
                    $pdo->exec("ALTER TABLE Orders ADD ShipDate DATE AFTER OrderTime");
                    echo "<p>ShipDate column added to Orders table.";
               } catch (PDOException $e) {
                    echo "<p>Error adding ShipDate to Orders: " . $e->getMessage();
               }

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
                    ShipmentID INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '出貨紀錄編號',
                    EmployeeID INT UNSIGNED COMMENT '員工編號',
                    OrderID INT UNSIGNED COMMENT '訂單編號',
                    ShipDate DATE COMMENT '出貨日期',
                    TrackingNumber CHAR(255) COMMENT '追蹤編號',
                    ShipMethod CHAR(255) COMMENT '運輸方式',
                    status TINYINT(1) COMMENT '狀態',
                    FOREIGN KEY (EmployeeID) REFERENCES Employee(EmployeeID) ON DELETE CASCADE,
                    FOREIGN KEY (OrderID) REFERENCES Orders(OrderID) ON DELETE CASCADE
               ) DEFAULT CHARACTER SET utf8 ENGINE=InnoDB";
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
               ?>
          </div>
     </div>
</div>
</body>
</html>
