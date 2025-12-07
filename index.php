<?php
session_start();

// Database Configuration
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "luxdrive";

$pdo = null;

function initializeDatabase()
{
    global $pdo, $host, $user, $pass, $dbname;

    try {
        $pdo = new PDO("mysql:host=$host", $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);


        $stmt = $pdo->query("SHOW TABLES LIKE 'clients'");
        if ($stmt->rowCount() == 0) {
            createTables();
        }

        return true;
    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        return false;
    }
}

function createTables()
{
    global $pdo;

    $sql = "
    CREATE TABLE IF NOT EXISTS clients (
        client_id INT AUTO_INCREMENT PRIMARY KEY,
        first_name VARCHAR(50) NOT NULL,
        last_name VARCHAR(50) NOT NULL,
        email VARCHAR(100) UNIQUE NOT NULL,
        phone VARCHAR(20),
        driver_license VARCHAR(50) UNIQUE NOT NULL,
        password_hash VARCHAR(255) NOT NULL,
        address VARCHAR(255),
        registration_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS cars (
        car_id INT AUTO_INCREMENT PRIMARY KEY,
        brand VARCHAR(50) NOT NULL,
        model VARCHAR(50) NOT NULL,
        year INT CHECK (year >= 1990),
        license_plate VARCHAR(20) UNIQUE NOT NULL,
        color VARCHAR(30),
        mileage INT DEFAULT 0,
        status ENUM('available', 'rented', 'maintenance') DEFAULT 'available',
        daily_price DECIMAL(10,2) NOT NULL,
        image_url VARCHAR(500) DEFAULT NULL,
        car_type VARCHAR(50) DEFAULT 'sedan',
        seats INT DEFAULT 5,
        transmission VARCHAR(20) DEFAULT 'Automatic',
        fuel_type VARCHAR(20) DEFAULT 'Gasoline'
    );

    CREATE TABLE IF NOT EXISTS staff (
        staff_id INT AUTO_INCREMENT PRIMARY KEY,
        first_name VARCHAR(50) NOT NULL,
        last_name VARCHAR(50) NOT NULL,
        email VARCHAR(100) UNIQUE NOT NULL,
        phone VARCHAR(20),
        role ENUM('admin', 'agent', 'mechanic') DEFAULT 'agent',
        password_hash VARCHAR(255) NOT NULL,
        hire_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS rentals (
        rental_id INT AUTO_INCREMENT PRIMARY KEY,
        client_id INT NOT NULL,
        car_id INT NOT NULL,
        staff_id INT,
        start_date DATE NOT NULL,
        end_date DATE NOT NULL,
        total_price DECIMAL(10,2) NOT NULL,
        status ENUM('ongoing', 'completed', 'cancelled') DEFAULT 'ongoing',
        extras TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (client_id) REFERENCES clients(client_id) ON DELETE CASCADE,
        FOREIGN KEY (car_id) REFERENCES cars(car_id) ON DELETE CASCADE,
        FOREIGN KEY (staff_id) REFERENCES staff(staff_id) ON DELETE SET NULL
    );

    CREATE TABLE IF NOT EXISTS payments (
        payment_id INT AUTO_INCREMENT PRIMARY KEY,
        rental_id INT NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        method ENUM('cash', 'credit_card', 'debit_card', 'bank_transfer') NOT NULL,
        payment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        status ENUM('paid', 'pending', 'refunded') DEFAULT 'pending',
        FOREIGN KEY (rental_id) REFERENCES rentals(rental_id) ON DELETE CASCADE
    );

    CREATE TABLE IF NOT EXISTS maintenance (
        maintenance_id INT AUTO_INCREMENT PRIMARY KEY,
        car_id INT NOT NULL,
        description TEXT NOT NULL,
        cost DECIMAL(10,2) DEFAULT 0.00,
        maintenance_date DATE NOT NULL,
        performed_by VARCHAR(100),
        FOREIGN KEY (car_id) REFERENCES cars(car_id) ON DELETE CASCADE
    );
    ";

    $pdo->exec($sql);
    insertSampleData();
    createTriggersAndViews();
}

function insertSampleData()
{
    global $pdo;

    // Sample clients
    $pdo->exec("INSERT IGNORE INTO clients (first_name, last_name, email, phone, driver_license, password_hash, address) VALUES
        ('Alice', 'Martin', 'alice.martin@email.com', '0612345678', 'DL12345A', '" . password_hash('client123', PASSWORD_DEFAULT) . "', '10 Rue Lafayette, Paris'),
        ('Bob', 'Durand', 'bob.durand@email.com', '0623456789', 'DL67890B', '" . password_hash('client123', PASSWORD_DEFAULT) . "', '22 Avenue Victor Hugo, Lyon')
    ");

    // Sample cars
    $pdo->exec("INSERT IGNORE INTO cars (brand, model, year, license_plate, color, mileage, status, daily_price, image_url, car_type, seats, transmission, fuel_type) VALUES
        ('Mercedes-Benz', 'S-Class', 2023, 'AB-123-CD', 'Black', 5000, 'available', 250.00, 'https://images.unsplash.com/photo-1618843479313-40f8afb4b4d8?w=800&q=80', 'Luxury', 5, 'Automatic', 'Gasoline'),
        ('BMW', '7 Series', 2023, 'EF-456-GH', 'White', 10000, 'available', 280.00, 'https://images.unsplash.com/photo-1555215695-3004980ad54e?w=800&q=80', 'Luxury', 5, 'Automatic', 'Gasoline'),
        ('Audi', 'RS e-tron GT', 2024, 'IJ-789-KL', 'Grey', 2000, 'available', 320.00, 'https://images.unsplash.com/photo-1617788138017-80ad40651399?w=800&q=80', 'Sports', 4, 'Automatic', 'Electric'),
        ('Porsche', 'Panamera', 2023, 'MN-012-OP', 'Silver', 8000, 'available', 350.00, 'https://images.unsplash.com/photo-1601679147136-22d1032399e4?w=800&q=80', 'Sports', 4, 'Automatic', 'Hybrid'),
        ('Range Rover', 'Autobiography', 2024, 'QR-345-ST', 'Black', 3000, 'available', 300.00, 'https://images.unsplash.com/photo-1601362840469-51e4d8d58785?w=800&q=80', 'SUV', 5, 'Automatic', 'Gasoline'),
        ('Bentley', 'Continental GT', 2023, 'UV-678-WX', 'Blue', 6000, 'available', 450.00, 'https://images.unsplash.com/photo-1563720223185-11003d516935?w=800&q=80', 'Luxury', 4, 'Automatic', 'Gasoline'),
        ('Ferrari', '488 GTB', 2022, 'YZ-901-AB', 'Red', 4000, 'available', 850.00, 'https://images.unsplash.com/photo-1592198084033-aade902d1aae?w=800&q=80', 'Sports', 2, 'Automatic', 'Gasoline'),
        ('Lamborghini', 'Huracan', 2023, 'CD-234-EF', 'Yellow', 2500, 'available', 900.00, 'https://images.unsplash.com/photo-1544636331-e26879cd4d9b?w=800&q=80', 'Sports', 2, 'Automatic', 'Gasoline')
    ");

    // Sample staff
    $pdo->exec("INSERT IGNORE INTO staff (first_name, last_name, email, phone, role, password_hash) VALUES
        ('Marie', 'Dupont', 'marie.dupont@luxdrive.com', '0611223344', 'admin', '" . password_hash('admin123', PASSWORD_DEFAULT) . "'),
        ('Lucas', 'Girard', 'lucas.girard@luxdrive.com', '0622334455', 'agent', '" . password_hash('agent123', PASSWORD_DEFAULT) . "'),
        ('Emma', 'Petit', 'emma.petit@luxdrive.com', '0633445566', 'mechanic', '" . password_hash('mech123', PASSWORD_DEFAULT) . "')
    ");
}

function createTriggersAndViews()
{
    global $pdo;

    // Drop and recreate triggers
    $pdo->exec("DROP TRIGGER IF EXISTS after_rental_insert");
    $pdo->exec("CREATE TRIGGER after_rental_insert AFTER INSERT ON rentals FOR EACH ROW BEGIN UPDATE cars SET status = 'rented' WHERE car_id = NEW.car_id; END");

    $pdo->exec("DROP TRIGGER IF EXISTS after_rental_complete");
    $pdo->exec("CREATE TRIGGER after_rental_complete AFTER UPDATE ON rentals FOR EACH ROW BEGIN IF NEW.status = 'completed' THEN UPDATE cars SET status = 'available' WHERE car_id = NEW.car_id; END IF; END");

    // Views
    $pdo->exec("CREATE OR REPLACE VIEW currently_rented_cars AS SELECT r.rental_id, r.car_id, c.brand, c.model, c.license_plate, r.client_id, r.start_date, r.end_date FROM rentals r JOIN cars c ON r.car_id = c.car_id WHERE r.status = 'ongoing'");

    $pdo->exec("CREATE OR REPLACE VIEW revenue_by_month AS SELECT YEAR(payment_date) AS yr, MONTH(payment_date) AS m, SUM(amount) AS revenue FROM payments WHERE status = 'paid' GROUP BY yr, m");
}


function sanitize($data)
{
    return htmlspecialchars(strip_tags(trim($data)));
}

function isLoggedIn()
{
    return isset($_SESSION['user_id']) && isset($_SESSION['user_type']);
}

function isClient()
{
    return isLoggedIn() && $_SESSION['user_type'] === 'client';
}

function isStaff()
{
    return isLoggedIn() && in_array($_SESSION['user_type'], ['admin', 'agent', 'mechanic']);
}

function requireLogin()
{
    if (!isLoggedIn()) {
        header('Location: index.php?page=auth');
        exit;
    }
}

// AUTHENTICATION HANDLERS

function handleLogin()
{
    global $pdo;

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return null;
    if (!isset($_POST['action']) || $_POST['action'] !== 'login') return null;

    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        return ['error' => 'Please fill in all fields'];
    }

    // Check clients first
    $stmt = $pdo->prepare("SELECT client_id, first_name, last_name, email, password_hash FROM clients WHERE email = ?");
    $stmt->execute([$email]);
    $client = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($client && password_verify($password, $client['password_hash'])) {
        $_SESSION['user_id'] = $client['client_id'];
        $_SESSION['user_type'] = 'client';
        $_SESSION['user_name'] = $client['first_name'] . ' ' . $client['last_name'];
        $_SESSION['user_email'] = $client['email'];
        $_SESSION['user_initials'] = strtoupper(substr($client['first_name'], 0, 1) . substr($client['last_name'], 0, 1));
        header('Location: index.php?page=home');
        exit;
    }

    // Check staff
    $stmt = $pdo->prepare("SELECT staff_id, first_name, last_name, email, role, password_hash FROM staff WHERE email = ?");
    $stmt->execute([$email]);
    $staff = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($staff && password_verify($password, $staff['password_hash'])) {
        $_SESSION['user_id'] = $staff['staff_id'];
        $_SESSION['user_type'] = $staff['role'];
        $_SESSION['user_name'] = $staff['first_name'] . ' ' . $staff['last_name'];
        $_SESSION['user_email'] = $staff['email'];
        $_SESSION['user_initials'] = strtoupper(substr($staff['first_name'], 0, 1) . substr($staff['last_name'], 0, 1));
        header('Location: index.php?page=dashboard');
        exit;
    }

    return ['error' => 'Invalid email or password'];
}

function handleRegister()
{
    global $pdo;

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return null;
    if (!isset($_POST['action']) || $_POST['action'] !== 'register') return null;

    $firstName = sanitize($_POST['first_name'] ?? '');
    $lastName = sanitize($_POST['last_name'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $driverLicense = sanitize($_POST['driver_license'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    // Validation
    if (empty($firstName) || empty($lastName) || empty($email) || empty($driverLicense) || empty($password)) {
        return ['error' => 'Please fill in all required fields'];
    }

    if ($password !== $confirmPassword) {
        return ['error' => 'Passwords do not match'];
    }

    if (strlen($password) < 6) {
        return ['error' => 'Password must be at least 6 characters'];
    }

    // Check if email exists
    $stmt = $pdo->prepare("SELECT client_id FROM clients WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        return ['error' => 'Email already registered'];
    }

    // Check if driver license exists
    $stmt = $pdo->prepare("SELECT client_id FROM clients WHERE driver_license = ?");
    $stmt->execute([$driverLicense]);
    if ($stmt->fetch()) {
        return ['error' => 'Driver license already registered'];
    }

    // Insert new client
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO clients (first_name, last_name, email, phone, driver_license, password_hash) VALUES (?, ?, ?, ?, ?, ?)");

    try {
        $stmt->execute([$firstName, $lastName, $email, $phone, $driverLicense, $passwordHash]);
        $clientId = $pdo->lastInsertId();

        $_SESSION['user_id'] = $clientId;
        $_SESSION['user_type'] = 'client';
        $_SESSION['user_name'] = $firstName . ' ' . $lastName;
        $_SESSION['user_email'] = $email;
        $_SESSION['user_initials'] = strtoupper(substr($firstName, 0, 1) . substr($lastName, 0, 1));

        header('Location: index.php?page=home');
        exit;
    } catch (PDOException $e) {
        return ['error' => 'Registration failed. Please try again.'];
    }
}

function handleLogout()
{
    session_destroy();
    header('Location: index.php?page=home');
    exit;
}


// DATA FUNCTIONS

function getCars($filters = [])
{
    global $pdo;

    $sql = "SELECT * FROM cars WHERE 1=1";
    $params = [];

    if (!empty($filters['status'])) {
        $sql .= " AND status = ?";
        $params[] = $filters['status'];
    }

    if (!empty($filters['car_type'])) {
        $sql .= " AND car_type = ?";
        $params[] = $filters['car_type'];
    }

    if (!empty($filters['brand'])) {
        $sql .= " AND brand LIKE ?";
        $params[] = '%' . $filters['brand'] . '%';
    }

    $sql .= " ORDER BY brand, model";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getCarById($carId)
{
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM cars WHERE car_id = ?");
    $stmt->execute([$carId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getAvailableCars()
{
    return getCars(['status' => 'available']);
}

function createRental($clientId, $carId, $startDate, $endDate, $totalPrice, $extras = null)
{
    global $pdo;

    $stmt = $pdo->prepare("INSERT INTO rentals (client_id, car_id, start_date, end_date, total_price, extras, status) VALUES (?, ?, ?, ?, ?, ?, 'ongoing')");
    $stmt->execute([$clientId, $carId, $startDate, $endDate, $totalPrice, $extras]);
    return $pdo->lastInsertId();
}

function createPayment($rentalId, $amount, $method, $status = 'pending')
{
    global $pdo;

    $stmt = $pdo->prepare("INSERT INTO payments (rental_id, amount, method, status) VALUES (?, ?, ?, ?)");
    $stmt->execute([$rentalId, $amount, $method, $status]);
    return $pdo->lastInsertId();
}

function getClientRentals($clientId)
{
    global $pdo;

    $stmt = $pdo->prepare("
        SELECT r.*, c.brand, c.model, c.year, c.image_url, c.license_plate,
               p.status as payment_status, p.method as payment_method
        FROM rentals r
        JOIN cars c ON r.car_id = c.car_id
        LEFT JOIN payments p ON r.rental_id = p.rental_id
        WHERE r.client_id = ?
        ORDER BY r.created_at DESC
    ");
    $stmt->execute([$clientId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getClientProfile($clientId)
{
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM clients WHERE client_id = ?");
    $stmt->execute([$clientId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function updateClientProfile($clientId, $data)
{
    global $pdo;

    $sql = "UPDATE clients SET first_name = ?, last_name = ?, phone = ?, address = ? WHERE client_id = ?";
    $stmt = $pdo->prepare($sql);
    return $stmt->execute([$data['first_name'], $data['last_name'], $data['phone'], $data['address'], $clientId]);
}


// BOOKING HANDLER

function handleBooking()
{
    global $pdo;

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return null;
    if (!isset($_POST['action']) || $_POST['action'] !== 'book') return null;
    if (!isClient()) return ['error' => 'Please login to book'];

    $carId = intval($_POST['car_id'] ?? 0);
    $startDate = sanitize($_POST['start_date'] ?? '');
    $endDate = sanitize($_POST['end_date'] ?? '');
    $totalPrice = floatval($_POST['total_price'] ?? 0);
    $extras = sanitize($_POST['extras'] ?? '');
    $paymentMethod = sanitize($_POST['payment_method'] ?? 'credit_card');

    if (!$carId || !$startDate || !$endDate || !$totalPrice) {
        return ['error' => 'Invalid booking data'];
    }

    // Check car availability
    $car = getCarById($carId);
    if (!$car || $car['status'] !== 'available') {
        return ['error' => 'Car is not available'];
    }

    try {
        $pdo->beginTransaction();

        $rentalId = createRental($_SESSION['user_id'], $carId, $startDate, $endDate, $totalPrice, $extras);
        createPayment($rentalId, $totalPrice, $paymentMethod, 'paid');

        $pdo->commit();

        return ['success' => true, 'rental_id' => $rentalId];
    } catch (Exception $e) {
        $pdo->rollBack();
        return ['error' => 'Booking failed. Please try again.'];
    }
}

function handleProfileUpdate()
{
    global $pdo;

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return null;
    if (!isset($_POST['action']) || $_POST['action'] !== 'update_profile') return null;
    if (!isClient()) return ['error' => 'Please login'];

    $data = [
        'first_name' => sanitize($_POST['first_name'] ?? ''),
        'last_name' => sanitize($_POST['last_name'] ?? ''),
        'phone' => sanitize($_POST['phone'] ?? ''),
        'address' => sanitize($_POST['address'] ?? '')
    ];

    if (updateClientProfile($_SESSION['user_id'], $data)) {
        $_SESSION['user_name'] = $data['first_name'] . ' ' . $data['last_name'];
        $_SESSION['user_initials'] = strtoupper(substr($data['first_name'], 0, 1) . substr($data['last_name'], 0, 1));
        return ['success' => 'Profile updated successfully'];
    }

    return ['error' => 'Failed to update profile'];
}


initializeDatabase();

// Handle form submissions
$authError = handleLogin();
if (!$authError) $authError = handleRegister();
$bookingResult = handleBooking();
$profileResult = handleProfileUpdate();

// Handle logout
if (isset($_GET['page']) && $_GET['page'] === 'logout') {
    handleLogout();
}


function renderHeader($title = 'LUXDRIVE')
{
?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo htmlspecialchars($title); ?></title>
        <link rel="stylesheet" href="styleV09.css">
    </head>

    <body>
        <header class="header" id="header">
            <div class="container flex-space">
                <a href="index.php?page=home" class="nav__logo">LUXDRIVE</a>
                <input type="checkbox" class="input__toggel" id="inputToggel">
                <nav class="nav flex nav_ul nav__menu" style="align-items: center;">
                    <ul class="nav__list flex">
                        <li><a href="index.php?page=home" class="nav__links">Home</a></li>
                        <li><a href="index.php?page=browse" class="nav__links">Browse cars</a></li>
                        <li><a href="index.php?page=home#contactus" class="nav__links">Contact us</a></li>
                    </ul>
                    <?php if (isLoggedIn()) { ?>
                        <div class="flex-center gap-1 user-dropdown">
                            <div class="profile flex-center"><span><?php echo $_SESSION['user_initials']; ?></span></div>
                            <button class="dropdown-toggle">
                                <img width="26" height="26" src="https://img.icons8.com/material-rounded/24/expand-arrow--v1.png" />
                            </button>
                            <div class="dropdown-menu">
                                <?php if (isClient()) { ?>
                                    <a href="index.php?page=rental_history">Rental History</a>
                                    <a href="index.php?page=profile">Profile</a>
                                <?php } else { ?>
                                    <a href="index.php?page=dashboard">Dashboard</a>
                                <?php } ?>
                                <hr>
                                <a href="index.php?page=logout" class="logout">Logout</a>
                            </div>
                        </div>
                    <?php } else { ?>
                        <ul class="flex auth-list">
                            <li><a href="index.php?page=auth" class="auth_link btn log_btn">login</a></li>
                            <li><a href="index.php?page=auth" class="auth_link btn sign_btn">sign Up</a></li>
                        </ul>
                    <?php } ?>
                </nav>
                <label for="inputToggel" class="nav__toggel">
                    <span class="navig_menu"></span>
                </label>
            </div>
        </header>
        <main class="main">
        <?php
    }

    function renderFooter()
    {
        ?>
        </main>
        <footer class="footer">
            <div class="decorative-blur-1"></div>
            <div class="decorative-blur-2"></div>
            <div class="footer-content">
                <div class="footer-main">
                    <div class="footer-grid">
                        <div class="brand-column">
                            <div class="brand-logo">LUXDRIVE</div>
                            <p class="brand-description">Experience the world's finest luxury vehicles. Premium car rental at your fingertips.</p>
                            <div class="social-links">
                                <a href="#" class="social-link" aria-label="Facebook"><svg fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M18 2h-3a5 5 0 00-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 011-1h3z"></path>
                                    </svg></a>
                                <a href="#" class="social-link" aria-label="Twitter"><svg fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M23 3a10.9 10.9 0 01-3.14 1.53 4.48 4.48 0 00-7.86 3v1A10.66 10.66 0 013 4s-4 9 5 13a11.64 11.64 0 01-7 2c9 5 20 0 20-11.5a4.5 4.5 0 00-.08-.83A7.72 7.72 0 0023 3z"></path>
                                    </svg></a>
                                <a href="#" class="social-link" aria-label="Instagram"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <rect x="2" y="2" width="20" height="20" rx="5" ry="5" stroke-width="2"></rect>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11.37A4 4 0 1112.63 8 4 4 0 0116 11.37zm1.5-4.87h.01"></path>
                                    </svg></a>
                                <a href="#" class="social-link" aria-label="LinkedIn"><svg fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M16 8a6 6 0 016 6v7h-4v-7a2 2 0 00-2-2 2 2 0 00-2 2v7h-4v-7a6 6 0 016-6zM2 9h4v12H2z"></path>
                                        <circle cx="4" cy="4" r="2"></circle>
                                    </svg></a>
                            </div>
                        </div>
                        <div class="footer-column">
                            <h4 class="footer-column-title">Company</h4>
                            <ul class="footer-links">
                                <li><a href="#">Features</a></li>
                                <li><a href="#">Our Fleet</a></li>
                                <li><a href="#">How It Works</a></li>
                                <li><a href="#">About Us</a></li>
                            </ul>
                        </div>
                        <div class="footer-column">
                            <h4 class="footer-column-title">Support</h4>
                            <ul class="footer-links">
                                <li><a href="#">Help Center</a></li>
                                <li><a href="#">Contact Us</a></li>
                                <li><a href="#">FAQ</a></li>
                                <li><a href="#">Live Chat</a></li>
                            </ul>
                        </div>
                        <div class="footer-column">
                            <h4 class="footer-column-title">Legal</h4>
                            <ul class="footer-links">
                                <li><a href="#">Terms of Service</a></li>
                                <li><a href="#">Privacy Policy</a></li>
                                <li><a href="#">Cookie Policy</a></li>
                                <li><a href="#">Licenses</a></li>
                            </ul>
                        </div>
                        <div class="footer-column">
                            <h4 class="footer-column-title">Contact</h4>
                            <div class="contact-item">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                </svg>
                                <span>123 Luxury Ave, Monaco</span>
                            </div>
                            <div class="contact-item">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                                </svg>
                                <span>+1 (555) 123-4567</span>
                            </div>
                            <div class="contact-item">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                                </svg>
                                <a href="mailto:info@luxdrive.com">info@luxdrive.com</a>
                            </div>
                        </div>
                    </div>
                    <div class="footer-bottom">
                        <p class="copyright">© 2025 LUXDRIVE. All rights reserved.</p>
                        <div class="support-badge">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <span>24/7 Customer Support</span>
                        </div>
                    </div>
                </div>
            </div>
        </footer>
        <script>
            const toggleBtn = document.querySelector(".dropdown-toggle");
            const menu = document.querySelector(".dropdown-menu");
            if (toggleBtn && menu) {
                toggleBtn.addEventListener("click", () => menu.classList.toggle("show"));
                document.addEventListener("click", (e) => {
                    if (!document.querySelector(".user-dropdown")?.contains(e.target)) {
                        menu.classList.remove("show");
                    }
                });
            }
        </script>
    </body>

    </html>
<?php
    }


    // HOME PAGE

    function renderHomePage()
    {
        $cars = getCars(['status' => 'available']);
?>
    <section class="section hero_section">
        <div class="container flex-center hero_container" style="color: white; flex-direction: column; text-align: center;">
            <h1>DON'T RENT A CAR.</h1>
            <h1>RENT THE CAR.</h1>
            <p class="p_hero">Premium car rental at affordable rates. Worldwide.</p>
            <div class="btn_hero flex gap-2">
                <a href="?page=browse" class="btn btn-brws">Browse Our Fleet</a>
                <?php if (isLoggedIn()) { ?>
                    <a href="?page=browse" class="btn btn-bok">Book Now</a>
                <?php } else { ?>
                    <a href="?page=auth" class="btn btn-bok">Book Now</a>
                <?php } ?>
            </div>
            <form method="GET" action="index.php" class="search-form flex-column">
                <input type="hidden" name="page" value="browse">
                <div class="flex gap-2 form-row">
                    <div class="form-group">
                        <label class="form-label">Pick-up Date</label>
                        <input type="date" name="start_date" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Return Date</label>
                        <input type="date" name="end_date" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Car Type</label>
                        <select name="car_type" class="form-input">
                            <option value="">All Types</option>
                            <option value="Luxury">Luxury</option>
                            <option value="SUV">SUV</option>
                            <option value="Sports">Sports</option>
                        </select>
                    </div>
                </div>
                <button type="submit" class="btn btn-brws btn-serch" style="width: 100%;">Find Available Cars</button>
            </form>
        </div>
    </section>

    <section class="stats">
        <div class="stats-grid">
            <div class="stat-item">
                <h3>350+</h3>
                <p>Exclusive Vehicles</p>
            </div>
            <div class="stat-item">
                <h3>105</h3>
                <p>Countries Worldwide</p>
            </div>
            <div class="stat-item">
                <h3>24/7</h3>
                <p>Customer Support</p>
            </div>
            <div class="stat-item">
                <h3>2000+</h3>
                <p>Satisfied Customers</p>
            </div>
        </div>
    </section>

    <section class="section why-choose-section">
        <div class="decorative-blur-1"></div>
        <div class="decorative-blur-2"></div>
        <div class="container">
            <div class="section-header flex-center flex-col">
                <div><span class="badge">Premium Benefits</span></div>
                <h2 class="main-title">Why choose <span class="italic">LUXDRIVE</span>?</h2>
                <p class="subtitle">Experience unparalleled luxury with world-class service</p>
            </div>
            <div class="grid-main">
                <div class="featured-card">
                    <div class="card-blur"></div>
                    <div class="card-content">
                        <div class="icon-box">
                            <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"></path>
                            </svg>
                        </div>
                        <h3 class="card-title">Premium Fleet</h3>
                        <p class="card-description">Drive the finest luxury vehicles from brands like Ferrari, Lamborghini, Rolls-Royce, and more.</p>
                        <div class="stat-display">
                            <div class="stat-number">350+</div>
                            <div class="stat-label">Exclusive vehicles</div>
                        </div>
                    </div>
                </div>
                <div class="stacked-cards">
                    <div class="white-card">
                        <div class="small-icon-box"><svg class="small-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"></path>
                            </svg></div>
                        <h3 class="small-title">Best Prices</h3>
                        <p class="small-description">Transparent pricing with no hidden fees. Premium quality at competitive rates.</p>
                    </div>
                    <div class="white-card">
                        <div class="small-icon-box"><svg class="small-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"></path>
                            </svg></div>
                        <h3 class="small-title">24/7 Support</h3>
                        <p class="small-description">Dedicated concierge service available around the clock, wherever you are.</p>
                    </div>
                </div>
            </div>
            <div class="grid-bottom">
                <div class="gradient-card">
                    <div class="gradient-blur"></div>
                    <div class="card-content"><svg class="icon" style="width: 3rem; height: 3rem; margin-bottom: 1rem; opacity: 0.9;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        </svg>
                        <h3 class="small-title">Global Coverage</h3>
                        <p style="color: rgba(255, 255, 255, 0.9);">Available in 105 countries with thousands of locations.</p>
                    </div>
                </div>
                <div class="white-card">
                    <div class="small-icon-box"><svg class="small-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg></div>
                    <h3 class="small-title">Instant Booking</h3>
                    <p class="small-description">Reserve your dream car in minutes with immediate confirmation.</p>
                </div>
                <div class="white-card">
                    <div class="small-icon-box"><svg class="small-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                        </svg></div>
                    <h3 class="small-title">Fully Insured</h3>
                    <p class="small-description">Comprehensive coverage on all vehicles for complete peace of mind.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="section how-it-works-section">
        <div class="container">
            <div class="section-header">
                <div><span class="badge">Simple Process</span></div>
                <h2 class="main-title">How it works</h2>
                <p class="subtitle">Get on the road in three easy steps</p>
            </div>
            <div class="timeline-container">
                <div class="timeline-line"></div>
                <div class="steps-grid">
                    <div class="step">
                        <div class="step-icon"><svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg></div>
                        <div class="step-number">01</div>
                        <h3 class="step-title">Search and select</h3>
                        <p class="step-description">Browse our premium collection and filter by brand, type, or features.</p>
                    </div>
                    <div class="step">
                        <div class="step-icon"><svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg></div>
                        <div class="step-number">02</div>
                        <h3 class="step-title">Book and customize</h3>
                        <p class="step-description">Select dates, add extras, and get instant confirmation.</p>
                    </div>
                    <div class="step">
                        <div class="step-icon"><svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"></path>
                            </svg></div>
                        <div class="step-number">03</div>
                        <h3 class="step-title">Pick up and drive</h3>
                        <p class="step-description">Complete quick paperwork and hit the road in your dream car.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="section fleet-section" style="margin-top: -10rem;">
        <div class="container flex flex-col" style="align-items: center;">
            <div style="margin-bottom: 1rem;"><span class="badge" style="margin-bottom: 3rem;">Our Collection</span></div>
            <div class="section-header flex-space">
                <div class="header-content">
                    <h2>Explore our fleet</h2>
                    <p class="header-subtitle">Choose from our premium selection of luxury vehicles</p>
                </div>
                <div class="nav-buttons">
                    <button class="nav-button" id="prevBtn"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                        </svg></button>
                    <button class="nav-button" id="nextBtn"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg></button>
                </div>
            </div>
            <div class="slider-container">
                <div class="slider-track" id="sliderTrack">
                    <?php foreach (array_slice($cars, 0, 8) as $car): ?>
                        <div class="slider-item">
                            <div class="car-card">
                                <div class="car-image-container">
                                    <img src="<?php echo htmlspecialchars($car['image_url'] ?: 'https://images.unsplash.com/photo-1618843479313-40f8afb4b4d8?w=800&q=80'); ?>" alt="<?php echo htmlspecialchars($car['brand']); ?>" class="car-image">
                                    <div class="status-badge"><span class="status-available">● Available</span></div>
                                </div>
                                <div class="car-details">
                                    <div>
                                        <h3 class="car-title"><?php echo htmlspecialchars($car['brand']); ?></h3>
                                        <p class="car-subtitle"><?php echo htmlspecialchars($car['model'] . ' ' . $car['year']); ?></p>
                                    </div>
                                    <div class="car-specs">
                                        <div class="spec-item"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                            </svg><span><?php echo number_format($car['mileage']); ?> km</span></div>
                                        <div class="spec-item"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"></path>
                                            </svg><span><?php echo htmlspecialchars($car['color']); ?></span></div>
                                    </div>
                                    <div class="car-footer">
                                        <div>
                                            <div class="car-price">$<?php echo number_format($car['daily_price']); ?></div>
                                            <div class="price-label">per day</div>
                                        </div>
                                        <a href="index.php?page=car-details&id=<?php echo $car['car_id']; ?>"><button class="view-more-button">View More</button></a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <div class="slider-item">
                        <div class="see-more-card">
                            <a href="index.php?page=browse" style="color:white;">
                                <div class="see-more-icon"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path>
                                    </svg></div>
                            </a>
                            <h3 class="see-more-title">View All Cars</h3>
                            <p class="see-more-text">Explore our complete collection of luxury vehicles</p>
                            <a href="index.php?page=browse"><button class="see-more-button"><span>See More</span><svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path>
                                    </svg></button></a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="section contact-section" id="contactus">
        <div class="container">
            <div class="section-header"><span class="badge">Contact Us</span>
                <h2>Get In Touch</h2>
                <p>Have questions? Our luxury concierge team is ready to assist you 24/7</p>
            </div>
            <div class="contact-grid">
                <div>
                    <div class="form-card">
                        <h3>Send us a message</h3>
                        <p class="subtitle">Fill out the form and we'll get back to you within 24 hours</p>
                        <form>
                            <div class="form-rowC">
                                <div class="form-group"><label>First Name</label><input type="text" placeholder="John"></div>
                                <div class="form-group"><label>Last Name</label><input type="text" placeholder="Doe"></div>
                            </div>
                            <div class="form-group"><label>Email Address</label><input type="email" placeholder="john.doe@example.com"></div>
                            <div class="form-group"><label>Phone Number</label><input type="tel" placeholder="+1 (555) 000-0000"></div>
                            <div class="form-group"><label>Message</label><textarea placeholder="Tell us about your luxury car rental needs..."></textarea></div>
                            <button type="submit" class="submit-btn">Send Message<svg class="icon" viewBox="0 0 24 24">
                                    <path d="M5 12h14M12 5l7 7-7 7" />
                                </svg></button>
                        </form>
                    </div>
                </div>
                <div class="info-cards">
                    <div class="quick-contact-card">
                        <h4>Quick Contact</h4>
                        <div class="contact-item"><svg class="icon" viewBox="0 0 24 24">
                                <path d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                            </svg>
                            <div>
                                <div class="contact-item-label">Phone</div>
                                <div class="contact-item-value">+1 (555) 123-4567</div>
                            </div>
                        </div>
                        <div class="contact-item"><svg class="icon" viewBox="0 0 24 24">
                                <path d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                            </svg>
                            <div>
                                <div class="contact-item-label">Email</div>
                                <div class="contact-item-value">info@luxedrive.com</div>
                            </div>
                        </div>
                    </div>
                    <div class="glass-card">
                        <div class="location-header">
                            <div class="icon-box"><svg class="icon-large" viewBox="0 0 24 24">
                                    <path d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                    <path d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg></div>
                            <div>
                                <h4>Visit Our Showroom</h4>
                                <p class="subtitle">Experience luxury in person</p>
                            </div>
                        </div>
                        <p class="address">123 Luxury Avenue</p>
                        <p class="address">Monaco, MC 98000</p>
                        <div class="divider"></div>
                        <div class="hours-header"><svg class="icon" viewBox="0 0 24 24" style="color: #FF5722;">
                                <circle cx="12" cy="12" r="10" />
                                <path d="M12 6v6l4 2" />
                            </svg><span>Business Hours</span></div>
                        <p class="hours-text">Mon - Fri: 8:00 AM - 8:00 PM</p>
                        <p class="hours-text">Sat - Sun: 9:00 AM - 6:00 PM</p>
                        <p class="emergency">24/7 Emergency Assistance</p>
                    </div>
                    <div class="glass-card">
                        <h4 style="margin-bottom: 1.5rem;">Follow Our Journey</h4>
                        <div class="social-icons"><a href="#" class="social-icon"><svg class="icon" viewBox="0 0 24 24" fill="currentColor" stroke="none">
                                    <path d="M18 2h-3a5 5 0 00-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 011-1h3z" />
                                </svg></a><a href="#" class="social-icon"><svg class="icon" viewBox="0 0 24 24" fill="currentColor" stroke="none">
                                    <path d="M23 3a10.9 10.9 0 01-3.14 1.53 4.48 4.48 0 00-7.86 3v1A10.66 10.66 0 013 4s-4 9 5 13a11.64 11.64 0 01-7 2c9 5 20 0 20-11.5a4.5 4.5 0 00-.08-.83A7.72 7.72 0 0023 3z" />
                                </svg></a><a href="#" class="social-icon"><svg class="icon" viewBox="0 0 24 24">
                                    <rect x="2" y="2" width="20" height="20" rx="5" ry="5" />
                                    <path d="M16 11.37A4 4 0 1112.63 8 4 4 0 0116 11.37zm1.5-4.87h.01" />
                                </svg></a><a href="#" class="social-icon"><svg class="icon" viewBox="0 0 24 24" fill="currentColor" stroke="none">
                                    <path d="M16 8a6 6 0 016 6v7h-4v-7a2 2 0 00-2-2 2 2 0 00-2 2v7h-4v-7a6 6 0 016-6zM2 9h4v12H2z" />
                                    <circle cx="4" cy="4" r="2" />
                                </svg></a></div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="section CTA-section">
        <div class="container flex flex-col" style="align-items: center;">
            <div class="cta-container">
                <div class="cta-background"></div>
                <div class="cta-blur-container">
                    <div class="cta-blur-1"></div>
                    <div class="cta-blur-2"></div>
                </div>
                <div class="cta-content">
                    <h3 class="cta-title">Start your journey</h3>
                    <p class="cta-text">Experience the thrill of driving luxury cars from the world's most prestigious brands</p>
                    <div class="cta-buttons">
                        <a href="index.php?page=browse"><button class="cta-button cta-button-primary">Browse Our Fleet</button></a>
                        <a href="index.php?page=home#contactus"><button class="cta-button cta-button-secondary">Contact Us</button></a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <script>
        const sliderTrack = document.getElementById('sliderTrack');
        const prevBtn = document.getElementById('prevBtn');
        const nextBtn = document.getElementById('nextBtn');
        let currentIndex = 0;
        const itemsPerPage = 2.4;
        const totalItems = sliderTrack ? sliderTrack.children.length : 0;
        const maxIndex = Math.max(0, totalItems - itemsPerPage);

        function updateSlider() {
            if (sliderTrack) sliderTrack.style.transform = `translateX(${-(currentIndex * (100 / itemsPerPage))}%)`;
        }
        if (prevBtn) prevBtn.addEventListener('click', () => {
            if (currentIndex > 0) {
                currentIndex--;
                updateSlider();
            }
        });
        if (nextBtn) nextBtn.addEventListener('click', () => {
            if (currentIndex < maxIndex) {
                currentIndex++;
                updateSlider();
            }
        });
    </script>
<?php
    }


    // BROWSE CARS PAGE

    function renderBrowseCars()
    {
        $filters = ['status' => 'available'];
        if (!empty($_GET['car_type'])) $filters['car_type'] = sanitize($_GET['car_type']);
        $cars = getCars($filters);
?>
    <section class="browse-section">
        <div class="container">
            <div class="browse-header">
                <h2>Browse Available Cars</h2>
                <p class="browse-subtitle">Choose from our exclusive collection of luxury vehicles</p>
            </div>
            <form method="GET" action="index.php" class="browse-search-form">
                <input type="hidden" name="page" value="browse">
                <div class="flex gap-2 form-row">
                    <div class="form-group"><label class="form-label">Pick-up Date</label><input type="date" name="start_date" class="form-input" value="<?php echo htmlspecialchars($_GET['start_date'] ?? ''); ?>"></div>
                    <div class="form-group"><label class="form-label">Return Date</label><input type="date" name="end_date" class="form-input" value="<?php echo htmlspecialchars($_GET['end_date'] ?? ''); ?>"></div>
                    <div class="form-group"><label class="form-label">Car Type</label>
                        <select name="car_type" class="form-input browse-select">
                            <option value="">All Types</option>
                            <option value="Luxury" <?php echo ($_GET['car_type'] ?? '') === 'Luxury' ? 'selected' : ''; ?>>Luxury</option>
                            <option value="SUV" <?php echo ($_GET['car_type'] ?? '') === 'SUV' ? 'selected' : ''; ?>>SUV</option>
                            <option value="Sports" <?php echo ($_GET['car_type'] ?? '') === 'Sports' ? 'selected' : ''; ?>>Sports</option>
                        </select>
                    </div>
                </div>
                <button type="submit" class="btn btn-brws btn-serch">Find Available Cars</button>
            </form>
            <div class="browse-grid">
                <?php foreach ($cars as $car): ?>
                    <div class="car-card">
                        <div class="car-image-container">
                            <img src="<?php echo htmlspecialchars($car['image_url'] ?: 'https://images.unsplash.com/photo-1618843479313-40f8afb4b4d8?w=800&q=80'); ?>" alt="<?php echo htmlspecialchars($car['brand']); ?>" class="car-image">
                            <div class="status-badge"><span class="status-available">● Available</span></div>
                        </div>
                        <div class="car-details">
                            <div>
                                <h3 class="car-title"><?php echo htmlspecialchars($car['brand']); ?></h3>
                                <p class="car-subtitle"><?php echo htmlspecialchars($car['model']); ?></p>
                            </div>
                            <div class="car-specs">
                                <div class="spec-item"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                    </svg><span><?php echo number_format($car['mileage']); ?> km</span></div>
                                <div class="spec-item"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"></path>
                                    </svg><span><?php echo htmlspecialchars($car['color']); ?></span></div>
                            </div>
                            <div class="car-footer">
                                <div>
                                    <div class="car-price">$<?php echo number_format($car['daily_price']); ?></div>
                                    <div class="price-label">per day</div>
                                </div>
                                <a href="index.php?page=car-details&id=<?php echo $car['car_id']; ?>" style="text-decoration: none;"><button class="view-more-button">View More</button></a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($cars)): ?>
                    <div style="grid-column: 1/-1; text-align: center; padding: 3rem;">
                        <h3>No cars available matching your criteria</h3>
                        <p>Try adjusting your filters</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>
<?php
    }


    // CAR DETAILS PAGE

    function renderCarDetails()
    {
        $carId = intval($_GET['id'] ?? 0);
        $car = getCarById($carId);
        if (!$car) {
            echo '<div class="container" style="padding: 3rem; text-align: center;"><h2>Car not found</h2><a href="index.php?page=browse">Back to Browse</a></div>';
            return;
        }
        global $bookingResult;
?>
    <div class="container" style="padding-block: 4rem;">
        <div class="back-nav"><a href="index.php?page=browse" class="back-link"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M19 12H5M12 19l-7-7 7-7" />
                </svg> Back to Browse</a></div>
        <div class="details-grid">
            <div class="gallery-container">
                <img src="<?php echo htmlspecialchars($car['image_url'] ?: 'https://images.unsplash.com/photo-1618843479313-40f8afb4b4d8?w=800&q=80'); ?>" alt="<?php echo htmlspecialchars($car['brand']); ?>" class="main-image" id="mainImage">
            </div>
            <div class="car-info-wrapper">
                <div class="car-info-header">
                    <div class="flex-space" style="align-items: flex-start;">
                        <div>
                            <h1 class="detail-title"><?php echo htmlspecialchars($car['brand']); ?> <br> <?php echo htmlspecialchars($car['model']); ?></h1>
                            <p class="detail-model"><?php echo $car['year']; ?> • <?php echo htmlspecialchars($car['car_type']); ?></p>
                        </div>
                        <span class="status-badge" style="position: static; background: #dcfce7; color: #16a34a;">Available</span>
                    </div>
                    <div class="detail-price-box"><span class="detail-price">$<?php echo number_format($car['daily_price']); ?></span><span class="detail-price-label">/day</span></div>
                </div>
                <div class="specs-section">
                    <h3 class="section-label">Specifications</h3>
                    <div class="specs-grid">
                        <div class="spec-detail-item">
                            <div class="spec-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                                    <circle cx="9" cy="7" r="4" />
                                    <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
                                    <path d="M16 3.13a4 4 0 0 1 0 7.75" />
                                </svg></div>
                            <div class="spec-content"><label>Seats</label><span><?php echo $car['seats']; ?> Passengers</span></div>
                        </div>
                        <div class="spec-detail-item">
                            <div class="spec-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="10" />
                                    <path d="M12 6v6l4 2" />
                                </svg></div>
                            <div class="spec-content"><label>Transmission</label><span><?php echo htmlspecialchars($car['transmission']); ?></span></div>
                        </div>
                        <div class="spec-detail-item">
                            <div class="spec-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M3 3v18h18" />
                                    <path d="M18 17V9" />
                                    <path d="M13 17V5" />
                                    <path d="M8 17v-3" />
                                </svg></div>
                            <div class="spec-content"><label>Fuel Type</label><span><?php echo htmlspecialchars($car['fuel_type']); ?></span></div>
                        </div>
                        <div class="spec-detail-item">
                            <div class="spec-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M13 10V3L4 14h7v7l9-11h-7z" />
                                </svg></div>
                            <div class="spec-content"><label>Mileage</label><span><?php echo number_format($car['mileage']); ?> km</span></div>
                        </div>
                    </div>
                </div>
                <div class="features-section">
                    <h3 class="section-label">Features</h3>
                    <div class="features-list">
                        <?php foreach (['GPS Navigation', 'Bluetooth', 'Leather Seats', 'Sunroof'] as $feature): ?>
                            <div class="feature-check"><svg class="check-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                                    <polyline points="20 6 9 17 4 12"></polyline>
                                </svg><?php echo $feature; ?></div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="info-section">
                    <h3 class="section-label">Additional Information</h3>
                    <div class="info-row"><span class="info-label">Color</span><span class="info-value"><?php echo htmlspecialchars($car['color']); ?></span></div>
                    <div class="info-row"><span class="info-label">License Plate</span><span class="info-value"><?php echo htmlspecialchars($car['license_plate']); ?></span></div>
                    <div class="info-row"><span class="info-label">Year</span><span class="info-value"><?php echo $car['year']; ?></span></div>
                </div>
                <?php if (isClient()): ?>
                    <button class="book-now-btn" onclick="openBookingModal(<?php echo $car['daily_price']; ?>, '<?php echo htmlspecialchars($car['brand'] . ' ' . $car['model'], ENT_QUOTES); ?>', <?php echo $car['car_id']; ?>)">Book This Car</button>
                <?php else: ?>
                    <a href="index.php?page=auth" class="book-now-btn" style="display: block; text-align: center; text-decoration: none;">Login to Book</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php if (isClient()) includeBookingModals($car); ?>
<?php
    }

    function includeBookingModals($car)
    {
        global $bookingResult;
?>
    <div id="booking-modal-step1" class="booking-modal">
        <div class="booking-modal-content">
            <div class="booking-modal-header">
                <h2 id="modal-car-title">Book <?php echo htmlspecialchars($car['brand'] . ' ' . $car['model']); ?></h2><button class="booking-modal-close" onclick="closeBookingModal('step1')">&times;</button>
            </div>
            <div class="booking-modal-body">
                <h3>Select Rental Dates</h3>
                <div class="booking-form-group"><label>Pickup Date</label><input type="date" id="booking-pickup-date" class="booking-date-input"></div>
                <div class="booking-form-group"><label>Return Date</label><input type="date" id="booking-return-date" class="booking-date-input"></div>
                <div class="booking-summary-box">
                    <div class="booking-summary-item"><span>Rental Duration:</span><span id="booking-duration-display">0 days</span></div>
                    <div class="booking-summary-item"><span>Base Price:</span><span id="booking-base-price">$0.00</span></div>
                </div>
                <button class="booking-btn-primary" onclick="proceedToExtras()">Continue to Extras</button>
            </div>
        </div>
    </div>
    <div id="booking-modal-step2" class="booking-modal">
        <div class="booking-modal-content">
            <div class="booking-modal-header">
                <h2>Book <?php echo htmlspecialchars($car['brand'] . ' ' . $car['model']); ?></h2><button class="booking-modal-close" onclick="closeBookingModal('step2')">&times;</button>
            </div>
            <div class="booking-modal-body">
                <h3>Add Extras (Optional)</h3>
                <div class="booking-extras-list">
                    <div class="booking-extra-item" onclick="toggleExtra('gps')">
                        <div class="booking-extra-info"><input type="checkbox" id="booking-gps" class="booking-extra-checkbox" data-price="10"><label for="booking-gps">GPS Navigation</label><span class="booking-extra-price">$10/day</span></div>
                        <div class="booking-extra-total" id="booking-gps-total">$0.00</div>
                    </div>
                    <div class="booking-extra-item" onclick="toggleExtra('insurance')">
                        <div class="booking-extra-info"><input type="checkbox" id="booking-insurance" class="booking-extra-checkbox" data-price="15"><label for="booking-insurance">Full Insurance</label><span class="booking-extra-price">$15/day</span></div>
                        <div class="booking-extra-total" id="booking-insurance-total">$0.00</div>
                    </div>
                    <div class="booking-extra-item" onclick="toggleExtra('child-seat')">
                        <div class="booking-extra-info"><input type="checkbox" id="booking-child-seat" class="booking-extra-checkbox" data-price="7"><label for="booking-child-seat">Child Seat</label><span class="booking-extra-price">$7/day</span></div>
                        <div class="booking-extra-total" id="booking-child-seat-total">$0.00</div>
                    </div>
                </div>
                <div class="booking-price-summary">
                    <div class="booking-summary-item"><span>Base Price (<span id="booking-days-count">0</span> days):</span><span id="booking-base-price-step2">$0.00</span></div>
                    <div class="booking-summary-item"><span>Extras Total:</span><span id="booking-extras-total">$0.00</span></div>
                    <div class="booking-summary-item total"><span>Subtotal:</span><span id="booking-subtotal">$0.00</span></div>
                </div>
                <div class="booking-modal-actions"><button class="booking-btn-secondary" onclick="backToDates()">Back</button><button class="booking-btn-primary" onclick="proceedToPayment()">Continue to Payment</button></div>
            </div>
        </div>
    </div>
    <div id="booking-modal-step3" class="booking-modal">
        <div class="booking-modal-content">
            <div class="booking-modal-header">
                <h2>Book <?php echo htmlspecialchars($car['brand'] . ' ' . $car['model']); ?></h2><button class="booking-modal-close" onclick="closeBookingModal('step3')">&times;</button>
            </div>
            <div class="booking-modal-body">
                <h3>Payment Details</h3>
                <div class="booking-payment-section">
                    <h4>Payment Method</h4>
                    <div class="booking-payment-methods">
                        <label class="booking-payment-option"><input type="radio" name="booking-payment" value="credit_card" checked><span>Credit Card</span></label>
                        <label class="booking-payment-option"><input type="radio" name="booking-payment" value="debit_card"><span>Debit Card</span></label>
                    </div>
                </div>
                <div class="booking-booking-summary">
                    <h4>Booking Summary</h4>
                    <div class="booking-summary-item"><span>Vehicle:</span><span id="booking-vehicle-summary"><?php echo htmlspecialchars($car['brand'] . ' ' . $car['model']); ?></span></div>
                    <div class="booking-summary-item"><span>Rental Period:</span><span id="booking-rental-period-summary">-</span></div>
                    <div class="booking-summary-item"><span>Duration:</span><span id="booking-duration-summary">0 days</span></div>
                    <div class="booking-summary-item"><span>Base Price:</span><span id="booking-base-price-summary">$0.00</span></div>
                    <div class="booking-summary-item"><span>Extras:</span><span id="booking-extras-summary">$0.00</span></div>
                    <div class="booking-summary-item total"><span>Total Amount:</span><span id="booking-total-amount">$0.00</span></div>
                </div>
                <div class="booking-modal-actions"><button class="booking-btn-secondary" onclick="backToExtras()">Back</button><button class="booking-btn-primary" onclick="confirmBooking()">Confirm Booking</button></div>
            </div>
        </div>
    </div>
    <div id="booking-modal-step4" class="booking-modal">
        <div class="booking-modal-content booking-confirmation">
            <div class="booking-modal-body">
                <div class="booking-confirmation-icon">✓</div>
                <h2>Booking Confirmed!</h2>
                <p>Your car has been successfully booked.</p>
                <div class="booking-confirmation-details">
                    <div class="booking-detail-item"><span>Vehicle:</span><span id="confirmed-vehicle"><?php echo htmlspecialchars($car['brand'] . ' ' . $car['model']); ?></span></div>
                    <div class="booking-detail-item"><span>Duration:</span><span id="confirmed-duration">1 day</span></div>
                    <div class="booking-detail-item"><span>Total:</span><span id="confirmed-total">$0.00</span></div>
                </div>
                <button class="booking-btn-primary" onclick="window.location.href='index.php?page=rental_history'">View My Rentals</button>
            </div>
        </div>
    </div>
    <form id="booking-form" method="POST" action="index.php?page=car-details&id=<?php echo $car['car_id']; ?>" style="display:none;">
        <input type="hidden" name="action" value="book">
        <input type="hidden" name="car_id" value="<?php echo $car['car_id']; ?>">
        <input type="hidden" name="start_date" id="form-start-date">
        <input type="hidden" name="end_date" id="form-end-date">
        <input type="hidden" name="total_price" id="form-total-price">
        <input type="hidden" name="extras" id="form-extras">
        <input type="hidden" name="payment_method" id="form-payment-method">
    </form>
    <script>
        const bookingData = {
            vehicle: "<?php echo htmlspecialchars($car['brand'] . ' ' . $car['model'], ENT_QUOTES); ?>",
            basePricePerDay: <?php echo $car['daily_price']; ?>,
            carId: <?php echo $car['car_id']; ?>,
            pickupDate: null,
            returnDate: null,
            duration: 0,
            extras: {},
            paymentMethod: 'credit_card',
            total: 0
        };

        function openBookingModal(price, name, carId) {
            initializeBookingDates();
            document.getElementById('booking-modal-step1').style.display = 'block';
        }

        function closeBookingModal(step) {
            document.getElementById('booking-modal-' + step).style.display = 'none';
        }

        function closeAllBookingModals() {
            document.querySelectorAll('.booking-modal').forEach(m => m.style.display = 'none');
        }

        function initializeBookingDates() {
            const today = new Date(),
                tomorrow = new Date(today);
            tomorrow.setDate(tomorrow.getDate() + 1);
            document.getElementById('booking-pickup-date').value = today.toISOString().split('T')[0];
            document.getElementById('booking-return-date').value = tomorrow.toISOString().split('T')[0];
            calculateBookingDuration();
        }

        function calculateBookingDuration() {
            const pickup = new Date(document.getElementById('booking-pickup-date').value),
                ret = new Date(document.getElementById('booking-return-date').value);
            bookingData.duration = Math.max(1, Math.ceil((ret - pickup) / (1000 * 60 * 60 * 24)));
            bookingData.pickupDate = document.getElementById('booking-pickup-date').value;
            bookingData.returnDate = document.getElementById('booking-return-date').value;
            updateStep1Display();
        }

        function updateStep1Display() {
            const base = bookingData.duration * bookingData.basePricePerDay;
            document.getElementById('booking-duration-display').textContent = bookingData.duration + ' day' + (bookingData.duration !== 1 ? 's' : '');
            document.getElementById('booking-base-price').textContent = '$' + base.toFixed(2);
        }

        function proceedToExtras() {
            if (bookingData.duration < 1) {
                alert('Please select valid dates');
                return;
            }
            closeBookingModal('step1');
            document.getElementById('booking-modal-step2').style.display = 'block';
            updateExtrasDisplay();
        }

        function backToDates() {
            closeBookingModal('step2');
            document.getElementById('booking-modal-step1').style.display = 'block';
        }

        function toggleExtra(id) {
            const cb = document.getElementById('booking-' + id);
            if (cb) {
                cb.checked = !cb.checked;
                updateExtraTotal(id);
                calculateExtrasTotal();
            }
        }

        function updateExtraTotal(id) {
            const cb = document.getElementById('booking-' + id),
                price = parseFloat(cb.dataset.price),
                total = cb.checked ? price * bookingData.duration : 0;
            document.getElementById('booking-' + id + '-total').textContent = '$' + total.toFixed(2);
        }

        function updateExtrasDisplay() {
            document.getElementById('booking-days-count').textContent = bookingData.duration;
            document.getElementById('booking-base-price-step2').textContent = '$' + (bookingData.duration * bookingData.basePricePerDay).toFixed(2);
            ['gps', 'insurance', 'child-seat'].forEach(e => updateExtraTotal(e));
            calculateExtrasTotal();
        }

        function calculateExtrasTotal() {
            let extrasTotal = 0;
            document.querySelectorAll('.booking-extra-checkbox:checked').forEach(cb => extrasTotal += parseFloat(cb.dataset.price) * bookingData.duration);
            document.getElementById('booking-extras-total').textContent = '$' + extrasTotal.toFixed(2);
            document.getElementById('booking-subtotal').textContent = '$' + (bookingData.duration * bookingData.basePricePerDay + extrasTotal).toFixed(2);
        }

        function proceedToPayment() {
            calculateBookingTotal();
            closeBookingModal('step2');
            document.getElementById('booking-modal-step3').style.display = 'block';
            updatePaymentSummary();
        }

        function backToExtras() {
            closeBookingModal('step3');
            document.getElementById('booking-modal-step2').style.display = 'block';
        }

        function calculateBookingTotal() {
            let extrasTotal = 0,
                extrasList = [];
            document.querySelectorAll('.booking-extra-checkbox:checked').forEach(cb => {
                extrasTotal += parseFloat(cb.dataset.price) * bookingData.duration;
                extrasList.push(cb.id.replace('booking-', ''));
            });
            bookingData.total = bookingData.duration * bookingData.basePricePerDay + extrasTotal;
            bookingData.extras = extrasList;
        }

        function updatePaymentSummary() {
            document.getElementById('booking-rental-period-summary').textContent = bookingData.pickupDate + ' to ' + bookingData.returnDate;
            document.getElementById('booking-duration-summary').textContent = bookingData.duration + ' day' + (bookingData.duration !== 1 ? 's' : '');
            document.getElementById('booking-base-price-summary').textContent = '$' + (bookingData.duration * bookingData.basePricePerDay).toFixed(2);
            let extrasTotal = 0;
            document.querySelectorAll('.booking-extra-checkbox:checked').forEach(cb => extrasTotal += parseFloat(cb.dataset.price) * bookingData.duration);
            document.getElementById('booking-extras-summary').textContent = extrasTotal > 0 ? '$' + extrasTotal.toFixed(2) : 'None';
            document.getElementById('booking-total-amount').textContent = '$' + bookingData.total.toFixed(2);
        }

        function confirmBooking() {
            const method = document.querySelector('input[name="booking-payment"]:checked').value;
            document.getElementById('form-start-date').value = bookingData.pickupDate;
            document.getElementById('form-end-date').value = bookingData.returnDate;
            document.getElementById('form-total-price').value = bookingData.total;
            document.getElementById('form-extras').value = bookingData.extras.join(',');
            document.getElementById('form-payment-method').value = method;
            document.getElementById('booking-form').submit();
        }
        document.addEventListener('DOMContentLoaded', function() {
            const p = document.getElementById('booking-pickup-date'),
                r = document.getElementById('booking-return-date');
            if (p && r) {
                p.addEventListener('change', calculateBookingDuration);
                r.addEventListener('change', calculateBookingDuration);
            }
            window.addEventListener('click', e => {
                if (e.target.classList.contains('booking-modal')) e.target.style.display = 'none';
            });
        });
    </script>
    <?php if (isset($bookingResult['success']) && $bookingResult['success']):
            $days = 1;
            if (isset($_POST['start_date']) && isset($_POST['end_date'])) {
                try {
                    $start = new DateTime($_POST['start_date']);
                    $end = new DateTime($_POST['end_date']);
                    $days = max(1, $start->diff($end)->days);
                    // If return date is same as pickup, it's 1 day. If next day, it's 1 day difference? 
                    // In the JS: Math.ceil((ret - pickup) / (1000 * 60 * 60 * 24))
                    // 2023-01-01 to 2023-01-02 is 1 day.
                } catch (Exception $e) {
                }
            }
    ?>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const modal = document.getElementById('booking-modal-step4');
                if (modal) {
                    document.getElementById('confirmed-duration').innerText = '<?php echo $days; ?> day<?php echo $days > 1 ? 's' : ''; ?>';
                    document.getElementById('confirmed-total').innerText = '$<?php echo number_format((float)($_POST['total_price'] ?? 0), 2); ?>';
                    modal.style.display = 'block';
                }
            });
        </script>
    <?php endif; ?>
<?php
    }


    // AUTH PAGE

    function renderAuth()
    {
        global $authError;
?>
    <div class="logcontainer">
        <div class="auth-page">
            <div class="auth-container container" style="max-width: 1200px; display: flex; align-items: flex-start;">
                <div class="decorative-glow-1"></div>
                <div class="decorative-glow-2"></div>
                <div class="brand-side">
                    <div class="logo">LUXDRIVE</div>
                    <p class="brand-tagline">Your Gateway to Luxury</p>
                    <p class="brand-description">Experience the finest collection of premium vehicles from world-renowned brands</p>
                    <div class="brand-features">
                        <div class="feature-item">
                            <div class="feature-icon"><svg class="icon" viewBox="0 0 24 24" style="stroke: white;">
                                    <path d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
                                </svg></div>
                            <div class="feature-text">
                                <h4>Premium Fleet</h4>
                                <p>350+ luxury vehicles worldwide</p>
                            </div>
                        </div>
                        <div class="feature-item">
                            <div class="feature-icon"><svg class="icon" viewBox="0 0 24 24" style="stroke: white;">
                                    <path d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                </svg></div>
                            <div class="feature-text">
                                <h4>Secure Booking</h4>
                                <p>Safe and encrypted transactions</p>
                            </div>
                        </div>
                        <div class="feature-item">
                            <div class="feature-icon"><svg class="icon" viewBox="0 0 24 24" style="stroke: white;">
                                    <path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg></div>
                            <div class="feature-text">
                                <h4>24/7 Support</h4>
                                <p>Dedicated concierge service</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="form-side" style="flex: 2;">
                    <div class="auth-containerF" style="width: 100%;">
                        <div class="auth-tabs"><button class="tab-btn active" onclick="switchTab('login')">Login</button><button class="tab-btn" onclick="switchTab('signup')">Sign Up</button></div>
                        <?php if ($authError && isset($authError['error'])): ?>
                            <div style="background: #fee2e2; color: #dc2626; padding: 1rem; border-radius: 8px; margin-bottom: 1rem;"><?php echo htmlspecialchars($authError['error']); ?></div>
                        <?php endif; ?>
                        <div id="login-form" class="form-content active">
                            <div class="form-card">
                                <h2>Welcome back</h2>
                                <p class="subtitle">Enter your credentials to access your account</p>
                                <form method="POST" action="index.php?page=auth">
                                    <input type="hidden" name="action" value="login">
                                    <div class="form-group"><label>Email Address</label><input type="email" name="email" placeholder="john.doe@example.com" required></div>
                                    <div class="form-group"><label>Password</label><input type="password" name="password" placeholder="Enter your password" required></div>
                                    <button type="submit" class="submit-btn">Login</button>
                                </form>
                            </div>
                        </div>
                        <div id="signup-form" class="form-content">
                            <div class="form-card">
                                <h2>Create account</h2>
                                <p class="subtitle">Join LUXDRIVE and start your luxury journey</p>
                                <form method="POST" action="index.php?page=auth" class="signupform">
                                    <input type="hidden" name="action" value="register">
                                    <div class="form-row">
                                        <div class="form-group"><label>First Name *</label><input type="text" name="first_name" placeholder="John" required></div>
                                        <div class="form-group"><label>Last Name *</label><input type="text" name="last_name" placeholder="Doe" required></div>
                                    </div>
                                    <div class="form-group"><label>Email Address *</label><input type="email" name="email" placeholder="john.doe@example.com" required></div>
                                    <div class="form-group"><label>Phone Number</label><input type="tel" name="phone" placeholder="+1 (555) 000-0000"></div>
                                    <div class="form-group"><label>Driver License * (Required)</label><input type="text" name="driver_license" placeholder="DL12345678" required></div>
                                    <div class="form-group"><label>Password *</label><input type="password" name="password" placeholder="Create a strong password" required></div>
                                    <div class="form-group"><label>Confirm Password *</label><input type="password" name="confirm_password" placeholder="Re-enter your password" required></div>
                                    <button type="submit" class="submit-btn">Create Account</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script>
        function switchTab(tab) {
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            event.target.classList.add('active');
            document.getElementById('login-form').classList.toggle('active', tab === 'login');
            document.getElementById('signup-form').classList.toggle('active', tab !== 'login');
        }
    </script>
<?php
    }


    // Profile Sidebar

    function renderDashboardSidebar($activePage)
    {
        $initials = $_SESSION['user_initials'] ?? 'U';
        $name = $_SESSION['user_name'] ?? 'User';
        $email = $_SESSION['user_email'] ?? '';
?>
    <div class="dashboard-sidebar">
        <div class="user-profile-summary">
            <div class="user-avatar"><?php echo htmlspecialchars($initials); ?></div>
            <h3 style="font-size: 1.2rem; font-weight: 600; margin-bottom: 0.25rem;"><?php echo htmlspecialchars($name); ?></h3>
            <p style="color: var(--text-light); font-size: 0.9rem;"><?php echo htmlspecialchars($email); ?></p>
        </div>
        <nav class="sidebar-nav">
            <a href="index.php?page=profile" class="sidebar-link <?php echo $activePage === 'profile' ? 'active' : ''; ?>">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                </svg>
                My Account
            </a>
            <a href="index.php?page=rental_history" class="sidebar-link <?php echo $activePage === 'rentals' ? 'active' : ''; ?>">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path>
                </svg>
                My Rentals
            </a>
            <a href="index.php?page=logout" class="sidebar-link" style="color: #ef4444;">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                </svg>
                Sign Out
            </a>
        </nav>
    </div>
<?php
    }


    // PROFILE PAGE

    function renderProfile()
    {
        if (!isClient()) {
            header('Location: index.php?page=auth');
            exit;
        }
        global $profileResult;
        $profile = getClientProfile($_SESSION['user_id']);
        $rentals = getClientRentals($_SESSION['user_id']);

        $totalRentals = count($rentals);
        $activeRentals = 0;
        $totalSpent = 0;
        foreach ($rentals as $r) {
            if ($r['status'] === 'ongoing') $activeRentals++;
            if ($r['status'] !== 'cancelled') $totalSpent += $r['total_price'];
        }

        $initials = $_SESSION['user_initials'] ?? 'U';
?>
    <div class="main-content">
        <div class="container">
            <div class="page-header">
                <div class="title-wrapper">
                    <div class="title-accent"></div>
                    <h2 class="page-title">My Profile</h2>
                </div>
                <p class="page-subtitle">Manage your personal information and preferences</p>
            </div>

            <?php if ($profileResult && isset($profileResult['success'])): ?>
                <div style="background: #dcfce7; color: #16a34a; padding: 1rem; border-radius: 8px; margin-bottom: 2rem; border: 1px solid #bbf7d0;"><?php echo htmlspecialchars($profileResult['success']); ?></div>
            <?php endif; ?>
            <?php if ($profileResult && isset($profileResult['error'])): ?>
                <div style="background: #fee2e2; color: #dc2626; padding: 1rem; border-radius: 8px; margin-bottom: 2rem; border: 1px solid #fecaca;"><?php echo htmlspecialchars($profileResult['error']); ?></div>
            <?php endif; ?>

            <div class="profile-layout">
                <aside class="profile-sidebar">
                    <div class="profile-card">
                        <div class="profile-image-wrapper">
                            <div class="profile-image-container">
                                <div class="profile-image-border">
                                    <div class="profile-image-inner">
                                        <div style="width:100%; height:100%; border-radius:50%; background:var(--primary); display:flex; align-items:center; justify-content:center; color:white; font-size:2.5rem; font-weight:600;">
                                            <?php echo $initials; ?>
                                        </div>
                                    </div>
                                </div>
                                <button class="camera-btn">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path>
                                        <circle cx="12" cy="13" r="4"></circle>
                                    </svg>
                                </button>
                            </div>
                        </div>


                        <div class="user-info">
                            <h3 class="user-name"><?php echo htmlspecialchars($profile['first_name'] . ' ' . $profile['last_name']); ?></h3>
                            <p class="user-email"><?php echo htmlspecialchars($profile['email']); ?></p>
                        </div>


                        <div class="user-stats">
                            <div class="stat-row">
                                <span class="stat-label">Member Since</span>
                                <span class="stat-value"><?php echo date('Y', strtotime($profile['registration_date'])); ?></span>
                            </div>
                            <div class="stat-row">
                                <span class="stat-label">Total Rentals</span>
                                <span class="stat-value"><?php echo $totalRentals; ?> trips</span>
                            </div>
                        </div>


                        <div class="quick-actions">
                            <a href="index.php?page=rental_history"><button class="action-btn">View Rental History</button></a>
                        </div>
                    </div>
                </aside>


                <main class="profile-main">
                    <div class="form-card">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                            <h3 class="form-title" style="margin-bottom: 0;">Personal Information</h3>
                            <button type="button" id="editProfileBtn" class="action-btn" style="width: auto;" onclick="toggleEditMode()">Edit Profile</button>
                        </div>

                        <form class="profile-form" method="POST" action="index.php?page=profile" id="profileForm">
                            <input type="hidden" name="action" value="update_profile">


                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">First Name</label>
                                    <div class="input-wrapper">
                                        <input type="text" name="first_name" class="form-input editable-input" value="<?php echo htmlspecialchars($profile['first_name']); ?>" required disabled>
                                        <svg class="input-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                            <circle cx="12" cy="7" r="4"></circle>
                                        </svg>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label class="form-label">Last Name</label>
                                    <div class="input-wrapper">
                                        <input type="text" name="last_name" class="form-input editable-input" value="<?php echo htmlspecialchars($profile['last_name']); ?>" required disabled>
                                        <svg class="input-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                            <circle cx="12" cy="7" r="4"></circle>
                                        </svg>
                                    </div>
                                </div>
                            </div>


                            <div class="form-group">
                                <label class="form-label">Email</label>
                                <div class="input-wrapper">
                                    <input type="email" class="form-input" value="<?php echo htmlspecialchars($profile['email']); ?>" disabled style="background: #f3f4f6; cursor: not-allowed;">
                                    <svg class="input-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                                        <polyline points="22,6 12,13 2,6"></polyline>
                                    </svg>
                                </div>
                            </div>


                            <div class="form-group">
                                <label class="form-label">Phone</label>
                                <div class="input-wrapper">
                                    <input type="tel" name="phone" class="form-input editable-input" value="<?php echo htmlspecialchars($profile['phone'] ?? ''); ?>" disabled>
                                    <svg class="input-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                                    </svg>
                                </div>
                            </div>


                            <div class="form-group">
                                <label class="form-label">Driver License</label>
                                <div class="input-wrapper">
                                    <input type="text" class="form-input" value="<?php echo htmlspecialchars($profile['driver_license']); ?>" disabled style="background: #f3f4f6; cursor: not-allowed;">
                                    <svg class="input-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                                    </svg>
                                </div>
                            </div>


                            <div class="form-group">
                                <label class="form-label">Address</label>
                                <div class="input-wrapper">
                                    <textarea name="address" class="form-textarea editable-input" rows="3" disabled><?php echo htmlspecialchars($profile['address'] ?? ''); ?></textarea>
                                    <svg class="input-icon input-icon-textarea" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                                        <circle cx="12" cy="10" r="3"></circle>
                                    </svg>
                                </div>
                            </div>


                            <div class="form-actions" id="actionButtons" style="display: none;">
                                <button type="submit" class="btn-primary">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                                        <polyline points="17 21 17 13 7 13 7 21"></polyline>
                                        <polyline points="7 3 7 8 15 8"></polyline>
                                    </svg>
                                    <span>Save Changes</span>
                                </button>
                                <button type="button" class="btn-secondary" onclick="toggleEditMode()">Cancel</button>
                            </div>
                        </form>
                    </div>
                </main>
            </div>
        </div>
    </div>
    <script>
        function toggleEditMode() {
            const form = document.getElementById('profileForm');
            const inputs = form.querySelectorAll('.editable-input');
            const actionButtons = document.getElementById('actionButtons');
            const editBtn = document.getElementById('editProfileBtn');

            let isEditing = actionButtons.style.display !== 'none';

            if (isEditing) {
                // Switching to View Mode (Cancel)
                inputs.forEach(input => {
                    input.disabled = true;
                    input.style.backgroundColor = '#f9fafb';
                });
                actionButtons.style.display = 'none';
                editBtn.style.display = 'block';
            } else {
                // Switching to Edit Mode
                inputs.forEach(input => {
                    input.disabled = false;
                    input.style.backgroundColor = 'white';
                });
                actionButtons.style.display = 'flex';
                editBtn.style.display = 'none';
            }
        }
    </script>
<?php
    }


    // Rental History Page

    function renderRentalHistory()
    {
        if (!isClient()) {
            header('Location: index.php?page=auth');
            exit;
        }
        $rentals = getClientRentals($_SESSION['user_id']);


        $totalRentals = count($rentals);
        $milesDriven = 0;
        foreach ($rentals as $r) {
            $milesDriven += ((isset($r['mileage']) && $r['mileage']) ? $r['mileage'] / 100 : 0);
        } // Just a dummy calc for show
        $formattedMiles = number_format($milesDriven > 0 ? $milesDriven : 1250); // Dummy default if 0
?>

    <div class="hero-section">
        <div class="container">
            <div class="hero-card">
                <div class="hero-decorative hero-decorative-1"></div>
                <div class="hero-decorative hero-decorative-2"></div>

                <div class="hero-content">
                    <div class="hero-text">
                        <div class="hero-title-wrapper">
                            <svg class="sparkle-icon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M12 2v20M2 12h20M6.5 6.5l11 11M6.5 17.5l11-11" />
                            </svg>
                            <span class="hero-subtitle">Welcome Back</span>
                        </div>
                        <h2 class="hero-heading">My Rental History</h2>
                        <p class="hero-description">
                            Your journey with the world's finest automobiles. Every mile tells a story.
                        </p>
                    </div>
                    <div class="hero-stats">
                        <div class="stat-item">
                            <div class="stat-value">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline>
                                    <polyline points="17 6 23 6 23 12"></polyline>
                                </svg>
                                <span><?php echo $totalRentals; ?></span>
                            </div>
                            <p class="stat-label">Total Rentals</p>
                        </div>
                        <div class="stat-divider"></div>
                        <div class="stat-item">
                            <p class="stat-value-large"><?php echo $formattedMiles; ?></p>
                            <p class="stat-label">Miles Driven</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <div class="main-content">
        <div class="container">

            <div class="filter-tabs">
                <button class="filter-tab filter-tab-active" onclick="filterRentals('all', this)">All Rentals</button>
                <button class="filter-tab" onclick="filterRentals('ongoing', this)">Ongoing</button>
                <button class="filter-tab" onclick="filterRentals('completed', this)">Completed</button>
            </div>

            <?php if (empty($rentals)): ?>
                <div style="text-align: center; padding: 4rem; background: white; border-radius: 1.5rem; border: 1px solid #f3f4f6;">
                    <h3 style="margin-bottom: 0.5rem; font-size:1.5rem;">No rentals yet</h3>
                    <p style="color: #6b7280; margin-bottom: 2rem;">Start your luxury journey by booking a car!</p>
                    <a href="index.php?page=browse"><button class="btn-primary" style="max-width:200px; margin:auto;">Browse Cars</button></a>
                </div>
            <?php else: ?>

                <div class="rental-grid" id="rentalGrid">
                    <?php foreach ($rentals as $rental):
                        $statusClass = 'status-paid';
                        if ($rental['status'] === 'ongoing') $statusClass = 'status-ongoing';
                        elseif ($rental['status'] === 'completed') $statusClass = 'status-completed';
                        elseif ($rental['status'] === 'cancelled') $statusClass = 'status-cancelled';
                    ?>
                        <div class="rental-card" data-status="<?php echo strtolower($rental['status']); ?>">
                            <div class="rental-image-wrapper">
                                <img src="<?php echo htmlspecialchars($rental['image_url'] ?: 'https://images.unsplash.com/photo-1618843479313-40f8afb4b4d8?w=800&q=80'); ?>" alt="<?php echo htmlspecialchars($rental['brand']); ?>" class="rental-image">
                                <div class="rental-image-overlay"></div>

                                <div class="rental-status <?php echo $statusClass; ?>">
                                    ● <?php echo ucfirst($rental['status']); ?>
                                </div>

                                <div class="rental-car-name">
                                    <h3 class="rental-car-title"><?php echo htmlspecialchars($rental['brand']); ?></h3>
                                    <p class="rental-car-model"><?php echo htmlspecialchars($rental['model']); ?></p>
                                </div>
                            </div>

                            <div class="rental-content">
                                <div class="rental-price-section">
                                    <div>
                                        <p class="rental-price-label">Total Amount</p>
                                        <p class="rental-price">$<?php echo number_format($rental['total_price'], 2); ?></p>
                                    </div>
                                </div>

                                <div class="rental-details">
                                    <div class="rental-detail-row">
                                        <span class="rental-detail-label">Start Date</span>
                                        <span class="rental-detail-value"><?php echo date('Y-m-d', strtotime($rental['start_date'])); ?></span>
                                    </div>
                                    <div class="rental-detail-row">
                                        <span class="rental-detail-label">End Date</span>
                                        <span class="rental-detail-value"><?php echo date('Y-m-d', strtotime($rental['end_date'])); ?></span>
                                    </div>
                                    <div class="rental-detail-row">
                                        <span class="rental-detail-label rental-location">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                                                <circle cx="12" cy="10" r="3"></circle>
                                            </svg>
                                            Location
                                        </span>
                                        <span class="rental-detail-value">Monaco</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <script>
        function filterRentals(status, btn) {
            document.querySelectorAll('.filter-tab').forEach(b => b.classList.remove('filter-tab-active'));
            btn.classList.add('filter-tab-active');
            document.querySelectorAll('.rental-card').forEach(card => {
                if (status === 'all' || card.dataset.status === status) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        }
    </script>
<?php
    }


    // PAGE ROUTER

    $page = sanitize($_GET['page'] ?? 'home');

    renderHeader();

    switch ($page) {
        case 'home':
            renderHomePage();
            break;
        case 'browse':
            renderBrowseCars();
            break;
        case 'car-details':
            renderCarDetails();
            break;
        case 'auth':
            renderAuth();
            break;
        case 'profile':
            renderProfile();
            break;
        case 'rental_history':
            renderRentalHistory();
            break;
        default:
            renderHomePage();
    }

    renderFooter();
?>