<?php
session_name("himihiba_carRental");
session_start();

require_once __DIR__ . '/config.example.php';

$pdo = null;

function initializeDatabase()
{
	global $pdo, $host, $user, $pass, $dbname;

	try {
		$pdo = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass);
		$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		
		// Create database if it doesn't exist
		$pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname`");
		$pdo->exec("USE `$dbname`");

		// Check for agencies table 
		$stmt = $pdo->query("SHOW TABLES LIKE 'agencies'");
		if ($stmt->rowCount() == 0) {
			resetDatabase();
		} else {
			updateDatabaseSchema();
		}
		return true;
	} catch (PDOException $e) {
		error_log("Database error: " . $e->getMessage());
		return false;
	}
}

function resetDatabase()
{
	global $pdo;
	try {
		$pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
		$tables = ['payments', 'rentals', 'maintenance', 'cars', 'staff', 'clients', 'agencies'];
		foreach ($tables as $table) {
			$pdo->exec("DROP TABLE IF EXISTS $table");
		}
		$pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
		createTables();
	} catch (PDOException $e) {
		error_log("Reset Database Error: " . $e->getMessage());
	}
}

function createTables()
{
	global $pdo;

	$sql = "
    CREATE TABLE IF NOT EXISTS agencies (
        agency_id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        city VARCHAR(50) NOT NULL,
        address VARCHAR(255) NOT NULL,
        contact_email VARCHAR(100) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );

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

    CREATE TABLE IF NOT EXISTS staff (
        staff_id INT AUTO_INCREMENT PRIMARY KEY,
        agency_id INT,
        first_name VARCHAR(50) NOT NULL,
        last_name VARCHAR(50) NOT NULL,
        email VARCHAR(100) UNIQUE NOT NULL,
        phone VARCHAR(20),
        role ENUM('super_admin', 'admin', 'agent', 'mechanic') DEFAULT 'agent',
        password_hash VARCHAR(255) NOT NULL,
        hire_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (agency_id) REFERENCES agencies(agency_id) ON DELETE SET NULL
    );

    CREATE TABLE IF NOT EXISTS cars (
        car_id INT AUTO_INCREMENT PRIMARY KEY,
        agency_id INT,
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
        fuel_type VARCHAR(20) DEFAULT 'Gasoline',
        FOREIGN KEY (agency_id) REFERENCES agencies(agency_id) ON DELETE CASCADE
    );

    CREATE TABLE IF NOT EXISTS rentals (
        rental_id INT AUTO_INCREMENT PRIMARY KEY,
        agency_id INT,
        client_id INT NOT NULL,
        car_id INT NOT NULL,
        staff_id INT,
        start_date DATE NOT NULL,
        end_date DATE NOT NULL,
        total_price DECIMAL(10,2) NOT NULL,
        status ENUM('ongoing', 'completed', 'cancelled') DEFAULT 'ongoing',
        extras TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (agency_id) REFERENCES agencies(agency_id) ON DELETE CASCADE,
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
        staff_id INT,
        description TEXT NOT NULL,
        cost DECIMAL(10,2) DEFAULT 0.00,
        maintenance_date DATE NOT NULL,
        status ENUM('pending', 'in_progress', 'completed') DEFAULT 'completed',
        performed_by VARCHAR(100),
        FOREIGN KEY (car_id) REFERENCES cars(car_id) ON DELETE CASCADE,
        FOREIGN KEY (staff_id) REFERENCES staff(staff_id) ON DELETE SET NULL
    );
    ";

	$pdo->exec($sql);
	updateDatabaseSchema();
	insertSampleData();
	createTriggersAndViews();
}

function updateDatabaseSchema()
{
	global $pdo;
	try {
		$stmt = $pdo->query("SHOW COLUMNS FROM maintenance LIKE 'staff_id'");
		if ($stmt->rowCount() == 0) {
			$pdo->exec("ALTER TABLE maintenance ADD COLUMN staff_id INT AFTER car_id");
			try {
				$pdo->exec("ALTER TABLE maintenance ADD CONSTRAINT fk_maintenance_staff FOREIGN KEY (staff_id) REFERENCES staff(staff_id) ON DELETE SET NULL");
			} catch (PDOException $e) {
			}
		}
	} catch (PDOException $e) {
	}


	try {
		$stmt = $pdo->query("SHOW COLUMNS FROM maintenance LIKE 'status'");
		if ($stmt->rowCount() == 0) {
			$pdo->exec("ALTER TABLE maintenance ADD COLUMN status ENUM('pending', 'in_progress', 'completed') DEFAULT 'completed'");
		}
	} catch (PDOException $e) {
	}
}

function insertSampleData()
{
	global $pdo;

	// 1. Insert Agencies
	$pdo->exec("INSERT INTO agencies (name, city, address, contact_email) VALUES
        ('LuxDrive Paris HQ', 'Paris', '15 Avenue des Champs-Élysées, 75008 Paris', 'paris@luxdrive.com'),
        ('LuxDrive Lyon Branch', 'Lyon', '88 Rue de la République, 69002 Lyon', 'lyon@luxdrive.com')
    ");
	$idAgency1 = $pdo->lastInsertId(); // Should be 1
	$idAgency2 = $idAgency1 + 1; // Should be 2

	// 2. Insert Staff
	// Super Admin
	$pdo->exec("INSERT INTO staff (agency_id, first_name, last_name, email, phone, role, password_hash) VALUES
        (NULL, 'Super', 'Admin', 'super@luxdrive.com', '0000000000', 'super_admin', '" . password_hash('super123', PASSWORD_DEFAULT) . "')
    ");

	// Agency 1 Staff 
	$pdo->exec("INSERT INTO staff (agency_id, first_name, last_name, email, phone, role, password_hash) VALUES
        ($idAgency1, 'Marie', 'Dupont', 'admin1@luxdrive.com', '0611111111', 'admin', '" . password_hash('admin123', PASSWORD_DEFAULT) . "'),
        ($idAgency1, 'Lucas', 'Girard', 'agent1@luxdrive.com', '0622222222', 'agent', '" . password_hash('agent123', PASSWORD_DEFAULT) . "'),
        ($idAgency1, 'Emma', 'Petit', 'mech1@luxdrive.com', '0633333333', 'mechanic', '" . password_hash('mech123', PASSWORD_DEFAULT) . "')
    ");

	// Agency 2 Staff
	$pdo->exec("INSERT INTO staff (agency_id, first_name, last_name, email, phone, role, password_hash) VALUES
        ($idAgency2, 'Thomas', 'Bernard', 'admin2@luxdrive.com', '0644444444', 'admin', '" . password_hash('admin123', PASSWORD_DEFAULT) . "'),
        ($idAgency2, 'Sophie', 'Martin', 'agent2@luxdrive.com', '0655555555', 'agent', '" . password_hash('agent123', PASSWORD_DEFAULT) . "'),
        ($idAgency2, 'Hugo', 'Laurent', 'mech2@luxdrive.com', '0666666666', 'mechanic', '" . password_hash('mech123', PASSWORD_DEFAULT) . "')
    ");

	// 3. Insert Clients 
	$pdo->exec("INSERT INTO clients (first_name, last_name, email, phone, driver_license, password_hash, address) VALUES
        ('Alice', 'Moreau', 'client@email.com', '0699999999', 'DL-CLIENT-01', '" . password_hash('client123', PASSWORD_DEFAULT) . "', '10 Rue de Rivoli, Paris'),
        ('Bob', 'Durand', 'bob.durand@email.com', '0688888888', 'DL-CLIENT-02', '" . password_hash('client456', PASSWORD_DEFAULT) . "', '22 Avenue Victor Hugo, Lyon')
    ");
	$idClient1 = $pdo->lastInsertId();

	// 4. Insert Cars 
	// Agency 1 Cars 
	$pdo->exec("INSERT INTO cars (agency_id, brand, model, year, license_plate, color, mileage, status, daily_price, image_url, car_type, seats, transmission, fuel_type) VALUES
        ($idAgency1, 'Mercedes-Benz', 'S-Class', 2023, 'PARIS-001', 'Black', 5000, 'available', 250.00, 'https://images.unsplash.com/photo-1618843479313-40f8afb4b4d8?w=800&q=80', 'Luxury', 5, 'Automatic', 'Gasoline'),
        ($idAgency1, 'Porsche', 'Panamera', 2023, 'PARIS-002', 'Silver', 8000, 'rented', 350.00, 'https://images.unsplash.com/photo-1601679147136-22d1032399e4?w=800&q=80', 'Sports', 4, 'Automatic', 'Hybrid'),
        ($idAgency1, 'Ferrari', '488 GTB', 2022, 'PARIS-003', 'Red', 4000, 'maintenance', 850.00, 'https://images.unsplash.com/photo-1592198084033-aade902d1aae?w=800&q=80', 'Sports', 2, 'Automatic', 'Gasoline'),
        ($idAgency1, 'Range Rover', 'Autobiography', 2024, 'PARIS-004', 'Black', 3000, 'available', 300.00, 'https://images.unsplash.com/photo-1601362840469-51e4d8d58785?w=800&q=80', 'SUV', 5, 'Automatic', 'Gasoline')
    ");
	$idCar1 = $pdo->lastInsertId();
	$idCar2 = $idCar1 + 1;
	$idCar3 = $idCar2 + 1;

	// Agency 2 Cars 
	$pdo->exec("INSERT INTO cars (agency_id, brand, model, year, license_plate, color, mileage, status, daily_price, image_url, car_type, seats, transmission, fuel_type) VALUES
        ($idAgency2, 'BMW', '7 Series', 2023, 'LYON-001', 'White', 10000, 'available', 280.00, 'https://images.unsplash.com/photo-1555215695-3004980ad54e?w=800&q=80', 'Luxury', 5, 'Automatic', 'Gasoline'),
        ($idAgency2, 'Audi', 'RS e-tron GT', 2024, 'LYON-002', 'Grey', 2000, 'rented', 320.00, 'https://images.unsplash.com/photo-1617788138017-80ad40651399?w=800&q=80', 'Sports', 4, 'Automatic', 'Electric'),
        ($idAgency2, 'Lamborghini', 'Huracan', 2023, 'LYON-003', 'Yellow', 2500, 'available', 900.00, 'https://images.unsplash.com/photo-1544636331-e26879cd4d9b?w=800&q=80', 'Sports', 2, 'Automatic', 'Gasoline'),
        ($idAgency2, 'Bentley', 'Continental GT', 2023, 'LYON-004', 'Blue', 6000, 'maintenance', 450.00, 'https://images.unsplash.com/photo-1563720223185-11003d516935?w=800&q=80', 'Luxury', 4, 'Automatic', 'Gasoline')
    ");
	$idCarLyon2 = $idCar1 + 5;

	// 5. Insert Rentals
	$pdo->exec("INSERT INTO rentals (agency_id, client_id, car_id, staff_id, start_date, end_date, total_price, status, created_at) VALUES
        ($idAgency1, $idClient1, $idCar2, 2, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 3 DAY), 1050.00, 'ongoing', NOW())
    ");
	$rentalId1 = $pdo->lastInsertId();

	$pdo->exec("INSERT INTO rentals (agency_id, client_id, car_id, staff_id, start_date, end_date, total_price, status, created_at) VALUES
        ($idAgency2, $idClient1, $idCarLyon2, 5, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 2 DAY), 640.00, 'ongoing', NOW())
    ");
	$rentalId2 = $pdo->lastInsertId();

	$pdo->exec("INSERT INTO rentals (agency_id, client_id, car_id, staff_id, start_date, end_date, total_price, status, created_at) VALUES
        ($idAgency1, $idClient1, $idCar1, 2, DATE_SUB(CURDATE(), INTERVAL 10 DAY), DATE_SUB(CURDATE(), INTERVAL 5 DAY), 1250.00, 'completed', DATE_SUB(NOW(), INTERVAL 10 DAY))
    ");
	$rentalId3 = $pdo->lastInsertId();

	$pdo->exec("INSERT INTO payments (rental_id, amount, method, status, payment_date) VALUES
        ($rentalId1, 1050.00, 'credit_card', 'paid', NOW()),
        ($rentalId2, 640.00, 'credit_card', 'pending', NOW()),
        ($rentalId3, 1250.00, 'cash', 'paid', DATE_SUB(NOW(), INTERVAL 5 DAY))
    ");

	// 7. Insert Maintenance

	$pdo->exec("INSERT INTO maintenance (car_id, staff_id, description, cost, maintenance_date, status, performed_by) VALUES
        ($idCar3, 4, 'Engine check and oil change', 200.00, CURDATE(), 'in_progress', 'Garage Luxe Paris')
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

	// Stored Procedures
	$pdo->exec("DROP PROCEDURE IF EXISTS complete_rental");
	$pdo->exec("
		CREATE PROCEDURE complete_rental(IN p_rental_id INT)
		BEGIN
			UPDATE rentals SET status = 'completed' WHERE rental_id = p_rental_id;
		END
	");

	// Views
	$pdo->exec("CREATE OR REPLACE VIEW currently_rented_cars AS SELECT r.rental_id, r.car_id, c.brand, c.model, c.license_plate, r.client_id, r.start_date, r.end_date FROM rentals r JOIN cars c ON r.car_id = c.car_id WHERE r.status = 'ongoing'");

	$pdo->exec("CREATE OR REPLACE VIEW revenue_by_month AS SELECT YEAR(payment_date) AS yr, MONTH(payment_date) AS m, SUM(amount) AS revenue FROM payments WHERE status = 'paid' GROUP BY yr, m");
}

function updateRentalStatuses()
{
	global $pdo;
	$sql = "UPDATE rentals SET status = 'completed' WHERE end_date < CURDATE() AND status = 'ongoing'";
	$pdo->exec($sql);
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
	return isLoggedIn() && in_array($_SESSION['user_type'], ['super_admin', 'admin', 'agent', 'mechanic']);
}

function isSuperAdmin()
{
	return isLoggedIn() && $_SESSION['user_type'] === 'super_admin';
}

function requireLogin()
{
	if (!isLoggedIn()) {
		header('Location: himihiba.php?page=auth');
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
		header('Location: himihiba.php?page=home');
		exit;
	}

	// Check staff
	$stmt = $pdo->prepare("SELECT staff_id, agency_id, first_name, last_name, email, role, password_hash FROM staff WHERE email = ?");
	$stmt->execute([$email]);
	$staff = $stmt->fetch(PDO::FETCH_ASSOC);

	if ($staff && password_verify($password, $staff['password_hash'])) {
		$_SESSION['user_id'] = $staff['staff_id'];
		$_SESSION['user_agency_id'] = $staff['agency_id'];
		$_SESSION['user_type'] = $staff['role'];
		$_SESSION['user_name'] = $staff['first_name'] . ' ' . $staff['last_name'];
		$_SESSION['user_email'] = $staff['email'];
		$_SESSION['user_initials'] = strtoupper(substr($staff['first_name'], 0, 1) . substr($staff['last_name'], 0, 1));

		switch ($staff['role']) {
			case 'super_admin':
				header('Location: himihiba.php?page=super_admin_dashboard');
				break;
			case 'admin':
				header('Location: himihiba.php?page=admin_dashboard');
				break;
			case 'agent':
				header('Location: himihiba.php?page=agent_dashboard');
				break;
			case 'mechanic':
				header('Location: himihiba.php?page=mechanic_dashboard');
				break;
			default:
				header('Location: himihiba.php?page=home');
		}
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

		header('Location: himihiba.php?page=home');
		exit;
	} catch (PDOException $e) {
		return ['error' => 'Registration failed. Please try again.'];
	}
}

function handleLogout()
{
	session_destroy();
	header('Location: himihiba.php?page=home');
	exit;
}


// DATA FUNCTIONS

function getCars($filters = [])
{
	global $pdo;

	$sql = "SELECT * FROM cars WHERE 1=1";
	$params = [];

	// Agency Isolation Logic
	if (isStaff() && !isSuperAdmin()) {
		$sql .= " AND agency_id = ?";
		$params[] = $_SESSION['user_agency_id'];
	} elseif (isSuperAdmin() && !empty($filters['agency_id'])) {
		$sql .= " AND agency_id = ?";
		$params[] = $filters['agency_id'];
	}

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

	// Fetch car's agency
	$car = getCarById($carId);
	$agencyId = $car['agency_id'];

	$stmt = $pdo->prepare("INSERT INTO rentals (agency_id, client_id, car_id, start_date, end_date, total_price, extras, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'ongoing')");
	$stmt->execute([$agencyId, $clientId, $carId, $startDate, $endDate, $totalPrice, $extras]);
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
        WHERE r.client_id = ? AND r.status != 'cancelled'
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

function handleCancelRental()
{
	global $pdo;

	if ($_SERVER['REQUEST_METHOD'] !== 'POST') return null;
	if (!isset($_POST['action']) || $_POST['action'] !== 'cancel_rental') return null;
	if (!isClient()) return ['error' => 'Please login'];

	$rentalId = intval($_POST['rental_id'] ?? 0);
	if (!$rentalId) return ['error' => 'Invalid rental ID'];

	// Verify ownership and status
	$stmt = $pdo->prepare("SELECT car_id, status FROM rentals WHERE rental_id = ? AND client_id = ?");
	$stmt->execute([$rentalId, $_SESSION['user_id']]);
	$rental = $stmt->fetch(PDO::FETCH_ASSOC);

	if (!$rental) {
		return ['error' => 'Rental not found'];
	}

	if ($rental['status'] !== 'ongoing') {
		return ['error' => 'Only ongoing rentals can be cancelled'];
	}

	try {
		$pdo->beginTransaction();

		// Update rental status
		$stmt = $pdo->prepare("UPDATE rentals SET status = 'cancelled' WHERE rental_id = ?");
		$stmt->execute([$rentalId]);

		// Update car status to available
		$stmt = $pdo->prepare("UPDATE cars SET status = 'available' WHERE car_id = ?");
		$stmt->execute([$rental['car_id']]);

		$pdo->commit();
		return ['success' => 'Rental cancelled successfully'];
	} catch (Exception $e) {
		$pdo->rollBack();
		return ['error' => 'Cancellation failed. Please try again.'];
	}
}


initializeDatabase();

// Handle form submissions
$authError = handleLogin();
if (!$authError) $authError = handleRegister();
$bookingResult = handleBooking();
$profileResult = handleProfileUpdate();
$cancelResult = handleCancelRental();

// Handle logout
if (isset($_GET['page']) && $_GET['page'] === 'logout') {
	handleLogout();
}


function renderHeader($title = 'LUXDRIVE')
{
	// Determine the logo link based on user role
	$logoLink = 'himihiba.php?page=home';
	if (isStaff()) {
		if (isAdmin()) {
			$logoLink = 'himihiba.php?page=admin_dashboard';
		} elseif (isAgent()) {
			$logoLink = 'himihiba.php?page=agent_dashboard';
		} elseif (isMechanic()) {
			$logoLink = 'himihiba.php?page=mechanic_dashboard';
		} elseif (isSuperAdmin()) {
			$logoLink = 'himihiba.php?page=super_admin_dashboard';
		}
	}
?>
	<!DOCTYPE html>
	<html lang="en">

	<head>
		<meta charset="UTF-8">
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
		<title><?php echo htmlspecialchars($title); ?></title>
		<style>
			/* ************ global variable ************ */
			:root {
				--primary: #FF5722;
				--primary-light: #ff6b3d;
				--primary-darker: #c44c06;
				--secondary: #364153;
				--surface: #fff7ed;
				--bg-w: #FFFFFF;
				--bg-d: #000000;
				--bg-N: #ECEBE9;
				--white: #FFFFFF;
				--black: #000000;
				--text: #334155;
				--text-dark: #111827;
				--text-light: #4A5565;
				--text-gray: #6b7280;
				--text-label: #808080;
				--bg-light: #f3f4f6;
				--grey-lighter: #D1D5DC;
				--green: #dcfce7;
				--blue: #dbeafe;
				--blue-darker: #155DFC;
				--green-darker: #016630;
				--radius: 12px;
				--shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
				--shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
				/**** Font and typography ****/
				--font: 'Intert', sans-serif;
				--fs-h1: 2.986rem;
				--fs-h2: 2.488rem;
				--fs-h3: 2.074rem;
				--fs-h4: 1.728rem;
				--fs-h5: 1.44rem;
				--fs-h6: 1.2rem;
				--fs-p: 1rem;
				--fs-small: 0.833rem;
				--fs-small-2: 0.694rem;
				/*Font weight*/
				--font-regular: 400;
				--font-medium: 500;
				--font-bold: 700;
				--transition: all 0.3s ease;

			}

			/* ************ Responsive typography ************ */


			@media screen and (max-width: 540px) {
				:root {
					--fs-h1: 1.8rem;
					--fs-h2: 1.55rem;
					--fs-h3: 1.3rem;
					--fs-h4: 1.1rem;
					--fs-h5: 1.3rem;
					--fs-h6: 0.95rem;
					--fs-p: 0.8rem;
					--fs-small: 0.65rem;
					--fs-small-2: 0.65rem;
				}
			}

			/* @media screen and (min-width: 540px) {
	:root {
		--fs-h1: 3.3rem;
		--fs-h2: 2.75rem;
		--fs-h3: 2.25rem;
		--fs-h4: 1.85rem;
		--fs-h5: 1.5rem;
		--fs-h6: 1.22rem;
		--fs-p: 1rem;
		--fs-small: 0.82rem;
		--fs-small-2: 0.68rem;
	}
} */

			@media (min-width: 768px) {
				:root {
					/* Desktop scale */
					--fs-h1: 3.815rem;
					--fs-h2: 3.052rem;
					--fs-h3: 2.441rem;
					--fs-h4: 1.953rem;
					--fs-h5: 1.563rem;
					--fs-h6: 1.25rem;
					--fs-p: 1rem;
					--fs-small: 0.8rem;
					--fs-small-2: 0.64rem;
				}
			}

			/* ************ BASE ************ */

			* {
				box-sizing: border-box;
				padding: 0;
				margin: 0;
			}

			html {
				scroll-behavior: smooth;
			}

			body {
				font-family: var(--font);
				background: var(--bg);
				color: var(--text);
				line-height: 1.6;
			}

			h1,
			h2,
			h3,
			h4 {
				color: var(--title-color);
				font-family: var(--font);
				font-weight: var(--font-bold);
			}

			h1 {
				font-size: var(--fs-h1);
			}

			h2 {
				font-size: var(--fs-h2);
			}

			h3 {
				font-size: var(--fs-h3);
			}

			h4 {
				font-size: var(--fs-h4);
			}

			h5 {
				font-size: var(--fs-h5);
			}

			h6 {
				font-size: var(--fs-h6);
			}

			p {
				font-size: var(--fs-p);
				margin-bottom: 1rem;
			}

			ul {
				list-style: none;
			}

			a {
				text-decoration: none;
				background-color: transparent;
				color: var(--secondary);
				font-size: var(--fs-h6);
				font-weight: normal;
				transition: var(--transition);
				position: relative;
			}

			img {
				display: block;
				max-width: 100%;
				height: auto;
			}

			button {
				appearance: none;
				border: none;
				padding: 0;
				background: none;
				font: inherit;
				cursor: pointer;
			}

			/* ************ REUSABLE CSS CLASSES ************ */
			.mb-4 {
				margin-bottom: 1.5rem;
			}

			.text-center {
				text-align: center;
			}

			.container {
				max-width: 1110px;
				margin-inline: auto;
			}


			.flex {
				display: flex;
			}

			.flex-space {
				display: flex;
				justify-content: space-between;
				align-items: center;
			}

			.flex-col {
				flex-direction: column;
			}

			.flex-center {
				display: flex;
				justify-content: center;
				align-items: center;
			}

			.grid {
				display: grid;
				gap: 1.5rem;
			}

			.gap-1 {
				gap: .3em;
			}

			.gap-2 {
				gap: .6em;
			}

			.section {
				padding-block: 6em 6em;
			}

			.section__title {
				text-align: center;
				font-size: var(--fs-h1);
				margin-bottom: 1.5rem;
			}

			.main {
				overflow: hidden;
			}



			/*********** HEADER & NAV ***********/
			.header {
				position: fixed;
				top: 0;
				left: 0;
				width: 100%;
				background-color: white;
				z-index: 100;
				padding: 2em 0;
				box-shadow: var(--shadow);
			}

			.nav__list {
				gap: 1.5em;
			}

			.nav__logo {
				font-size: var(--fs-h4);
				font-weight: var(--font-bold);
				color: var(--black);
			}

			.auth-list {
				gap: 0.3em;
			}

			.admin-layout {
				margin: 2rem;
				background-color: white;
			}

			.nav_ul {
				width: 90%;
				justify-content: flex-end;
				gap: 2em;
				align-items: center;
			}

			.nav__links:hover {
				color: var(--primary);
			}

			.nav__links::after {
				content: '';
				position: absolute;
				bottom: -5px;
				left: 0;
				width: 0;
				height: 2px;
				background-color: var(--primary);
				transition: var(--transition);
			}

			.nav__links::after {
				content: '';
				position: absolute;
				bottom: -5px;
				left: 0;
				width: 0;
				height: 2px;
				background-color: var(--primary);
				transition: var(--transition);
			}

			.nav__links:hover::after {
				width: 100%;
			}

			.btn {
				padding: 0.6em 1em;
				border-radius: 8px;
				font-weight: 600;
				cursor: pointer;
				transition: all 0.3s ease;
			}

			.btn:hover {
				transform: scale(0.95);
				box-shadow: var(--shadow-lg);
			}

			.log_btn,
			.sign_btn {
				border: 1px solid var(--primary);
				color: white;
			}

			.log_btn {
				color: var(--primary);
			}

			.sign_btn,
			.btn-brws {
				background-color: var(--primary);
				color: var(--white);
			}

			.btn-bok {
				border: 1px solid var(--secondary);
				color: var(--white);
			}

			.btn-bok:hover {
				border: 1px solid var(--primary);
			}

			.btn-brws:hover {
				background-color: var(--primary-darker);
			}

			.profile {
				width: 45px;
				height: 45px;
				border-radius: 50%;
				background-color: var(--primary);

			}

			.profile span {
				color: var(--white);
			}


			/* Navigation for mobile devices */



			/* Responsive header */


			@media screen and (max-width: 1150px) {
				.container {
					max-width: 90%;
				}
			}

			/*********** main ***********/
			.main {
				margin-top: 90px;
				position: relative;
			}

			/* Hero section */
			.hero_section {
				background-image: linear-gradient(rgba(0, 0, 0, 0.6), rgba(0, 0, 0, 0.6)), url(https://images.unsplash.com/photo-1492144534655-ae79c964c9d7?w=1600&q=80);
				background-size: cover;
				background-position: center;
				background-repeat: no-repeat;
			}

			.p_hero {
				font-size: var(--fs-p);
				margin-top: 0.15em;
				font-weight: 500;
			}

			.btn_hero {
				margin-top: 2.5em;
			}

			/* foooormmm */
			.search-form {
				background: white;
				padding: 40px;
				border-radius: 16px;
				box-shadow: var(--shadow);
				margin: -80px auto 40px;
				position: absolute;
				z-index: 10;
				width: 70%;
				top: 137%;
				max-width: 900px;
			}

			.search-form .form-row {
				margin-bottom: 1em;
				padding: 1.5em;
			}

			.btn-serch:hover {
				transform: scale(1);
			}

			.form-group {
				margin-bottom: 24px;
				text-align: left;
				flex-grow: 1;
			}

			.form-label {
				display: block;
				margin-bottom: 8px;
				font-weight: 500;
				color: var(--text);
			}

			.form-input {
				width: 100%;
				padding: 0.75em 1em;
				border: 1px solid #ddd;
				border-radius: 8px;
				font-size: 16px;
				transition: border-color 0.3s;
				color: var(--text-label);
			}

			.form-input:focus {
				outline: none;
				border-color: var(--luminex-blue);
				box-shadow: 0 0 0 3px rgba(101, 171, 234, 0.1);
			}

			.hero_container {
				position: relative;
			}

			/* stats section */
			.stats {
				margin-top: 300px;
				padding: 80px 0;
			}

			.stats-grid {
				display: grid;
				grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
				gap: 40px;
				text-align: center;
			}

			.stat-item h3 {
				font-size: var(--fs-h1);
				margin-bottom: 10px;
				color: var(--primary);
			}


			.stat-item p {
				color: var(--secondary);
				font-size: 18px;
			}


			/* why choose us  */
			.why-choose-section {
				position: relative;
				background: linear-gradient(to bottom right, #f9fafb, #ffffff, #fff7ed);
				overflow: hidden;
			}

			.decorative-blur-1 {
				position: absolute;
				top: 0;
				right: 0;
				width: 24rem;
				height: 24rem;
				background-color: var(--primary);
				opacity: 0.05;
				border-radius: 50%;
				filter: blur(80px);
			}

			.decorative-blur-2 {
				position: absolute;
				bottom: 0;
				left: 0;
				width: 24rem;
				height: 24rem;
				background-color: var(--primary);
				opacity: 0.05;
				border-radius: 50%;
				filter: blur(80px);
			}

			.section-header {
				text-align: center;
				margin-bottom: 5rem;
				width: 100%;
			}

			.badge {
				display: inline-block;
				padding: 0.5rem 1rem;
				background-color: rgba(255, 87, 34, 0.1);
				color: var(--primary);
				border-radius: 9999px;
				font-size: 0.875rem;
				letter-spacing: 0.05em;
				text-transform: uppercase;
				margin-bottom: 1rem;
			}

			.main-title .italic {
				font-style: italic;
			}

			.subtitle {
				color: var(--text-gray);
				font-size: 1.25rem;
				max-width: 42rem;
				margin: 0 auto;
			}

			.grid-main {
				display: grid;
				grid-template-columns: repeat(12, 1fr);
				gap: 1.5rem;
				margin-bottom: 1.5rem;
			}

			.featured-card {
				grid-column: span 7;
				background-color: #000;
				color: #fff;
				padding: 3rem;
				border-radius: 1rem;
				position: relative;
				overflow: hidden;
				transition: all 0.3s ease;
			}

			.featured-card:hover .card-blur {
				opacity: 0.2;
			}

			.card-blur {
				position: absolute;
				top: 0;
				right: 0;
				width: 20rem;
				height: 20rem;
				background-color: var(--primary);
				opacity: 0.1;
				border-radius: 50%;
				filter: blur(80px);
				transition: opacity 0.3s ease;
			}

			.card-content {
				position: relative;
				z-index: 10;
			}

			.card-content .icon {
				width: 3rem;
				height: 2rem;
				opacity: 0.9;
			}

			.icon-box {
				width: 4rem;
				height: 4rem;
				background-color: var(--primary);
				border-radius: 1rem;
				display: flex;
				align-items: center;
				justify-content: center;
				margin-bottom: 1.5rem;
				transition: transform 0.3s ease;
			}

			.featured-card:hover .icon-box {
				transform: scale(1.1);
			}

			.icon-box .icon {
				width: 2rem;
				height: 2rem;
				color: #fff;
			}

			.card-title {
				font-size: var(--fs-h1);
				font-weight: 700;
				margin-bottom: 1rem;
			}

			.card-description {
				color: #d1d5db;
				font-size: var(--fs-h6);
				margin-bottom: 1.5rem;
				max-width: 32rem;
			}

			.stat-display {
				display: flex;
				align-items: center;
				gap: 1rem;
			}

			.stat-number {
				font-size: var(--fs-h5);
				font-weight: 700;
				color: var(--primary);
			}

			.stat-label {
				color: #9ca3af;
			}

			.stacked-cards {
				grid-column: span 5;
				display: flex;
				flex-direction: column;
				gap: 1.5rem;
			}

			.white-card {
				background-color: #fff;
				border: 2px solid #e5e7eb;
				padding: 2rem;
				border-radius: 1rem;
				transition: all 0.3s ease;
			}

			.white-card:hover {
				border-color: var(--primary);
				box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
			}

			.small-icon-box {
				width: 3.5rem;
				height: 3.5rem;
				background-color: rgba(255, 87, 34, 0.1);
				border-radius: 0.75rem;
				display: flex;
				align-items: center;
				justify-content: center;
				margin-bottom: 1rem;
				transition: background-color 0.3s ease;
			}

			.white-card:hover .small-icon-box {
				background-color: var(--primary);
			}

			.small-icon {
				width: 1.75rem;
				height: 1.75rem;
				color: var(--primary);
				transition: color 0.3s ease;
			}

			.white-card:hover .small-icon {
				color: #fff;
			}

			.small-title {
				font-size: var(--fs-h5);
				font-weight: 700;
				margin-bottom: 0.75rem;
			}

			.small-description {
				color: var(--text-gray);
			}

			.grid-bottom {
				display: grid;
				grid-template-columns: repeat(3, 1fr);
				gap: 1.5rem;
			}

			.gradient-card {
				background: linear-gradient(to bottom right, var(--primary), #F4511E);
				color: #fff;
				padding: 2rem;
				border-radius: 1rem;
				position: relative;
				overflow: hidden;
			}

			.gradient-blur {
				position: absolute;
				bottom: -2rem;
				right: -2rem;
				width: 8rem;
				height: 8rem;
				background-color: #fff;
				opacity: 0.1;
				border-radius: 50%;
				transition: transform 0.5s ease;
			}

			.gradient-card:hover .gradient-blur {
				transform: scale(4);
			}

			/* SVG Icons */
			.icon svg {
				width: 100%;
				height: 100%;
			}

			@media (max-width: 768px) {
				.why-choose-section {
					padding: 4rem 0;
				}

				.grid-main {
					grid-template-columns: 1fr;
				}

				.featured-card,
				.stacked-cards {
					grid-column: span 12;
				}

				.grid-bottom {
					grid-template-columns: 1fr;
				}

				.cta-bar {
					flex-direction: column;
					gap: 1.5rem;
					text-align: center;
				}

				.cta-button {
					width: 100%;
				}
			}

			/* how it works section */

			/* Header Styles */

			.main-title {
				font-size: var(--fs-h1);
				font-weight: 700;
				margin-bottom: 0.1em;
				color: var(--black);
			}

			.subtitle {
				color: var(--text-light);
				font-size: var(--fs-h6);
				max-width: 42rem;
				margin: 0 auto;
			}

			.timeline-container {
				position: relative;
				max-width: 70rem;
				margin: 7rem auto 8rem;
			}

			.timeline-line {
				position: absolute;
				top: 3.5rem;
				left: 0;
				right: 0;
				height: 4px;
				background: linear-gradient(to right, #e5e7eb, var(--primary), #e5e7eb);
			}

			.steps-grid {
				display: grid;
				grid-template-columns: repeat(3, 1fr);
				gap: 2rem;
				position: relative;
			}

			.step {
				text-align: center;
				position: relative;
			}

			.step-icon {
				display: inline-flex;
				align-items: center;
				justify-content: center;
				width: 7rem;
				height: 7rem;
				background-color: #ff5622ea;
				border-radius: 50%;
				margin-bottom: 3rem;
				position: relative;
				z-index: 10;
				box-shadow: 0 20px 40px rgba(255, 87, 34, 0.3);
			}

			.step-icon .icon {
				width: 3rem;
				height: 3rem;
				color: #fff;
			}

			.step-number {
				font-size: 5rem;
				color: rgba(255, 86, 34, 0.219);
				font-weight: 700;
				margin-bottom: 3rem;
				line-height: 1;
			}

			.step-title {
				font-size: 1.875rem;
				font-weight: 700;
				margin-bottom: 1.5rem;
				color: var(--black);
			}

			.step-description {
				color: var(--text-gray);
				font-size: 1.125rem;
				line-height: 1.7;
			}

			/* Responsive */
			@media (max-width: 768px) {

				.subtitle {
					font-size: 1rem;
				}

				.timeline-line {
					display: none;
				}

				.steps-grid {
					grid-template-columns: 1fr;
					gap: 4rem;
				}

				.step-icon {
					width: 5rem;
					height: 5rem;
				}

				.step-icon .icon {
					width: 2rem;
					height: 2rem;
				}

				.step-number {
					font-size: 3rem;
				}

				.step-title {
					font-size: 1.5rem;
				}

				.step-description {
					font-size: 1rem;
				}
			}

			/* car slide section */
			.fleet-section {
				background: linear-gradient(to bottom right, #f9fafb, #fff, #f9fafb);

			}

			/* Header */

			.header-content h2 {
				font-size: 3.75rem;
				margin-bottom: 1rem;
				color: #000;
			}

			.badge {
				display: inline-block;
				padding: 0.5rem 1rem;
				background-color: rgba(255, 87, 34, 0.1);
				color: var(--primary);
				border-radius: 9999px;
				font-size: 0.875rem;
				letter-spacing: 0.05em;
				text-transform: uppercase;
				margin-bottom: 1rem;
			}

			.header-subtitle {
				color: var(--text-gray);
				font-size: 1.25rem;
				max-width: 42rem;
			}

			/* Navigation Buttons */
			.nav-buttons {
				display: flex;
				gap: 0.75rem;
			}

			.nav-button {
				width: 3.5rem;
				height: 3.5rem;
				border-radius: 50%;
				border: 2px solid #d1d5db;
				background: white;
				cursor: pointer;
				display: flex;
				align-items: center;
				justify-content: center;
				transition: all 0.3s ease;
			}

			.nav-button:hover {
				border-color: var(--primary);
				background-color: var(--primary);
				color: white;
			}

			.nav-button svg {
				width: 1.5rem;
				height: 1.5rem;
			}

			/* Slider */
			.slider-container {
				overflow: hidden;
				gap: 2em;
				width: 100%;
			}

			.slider-track {
				display: flex;
				transition: transform 0.5s ease-out;
			}

			.slider-item {
				flex-shrink: 0;
				width: 45%;
				padding: 0 0.75rem;
			}

			.car-card {
				background: white;
				border-radius: 1rem;
				overflow: hidden;
				box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
				transition: all 0.3s ease;
				height: 80%;
				display: flex;
				flex-direction: column;
			}

			/* Car Image */
			.car-image-container {
				position: relative;
				aspect-ratio: 1;
				overflow: hidden;
				background-color: var(--bg-light);
			}

			.car-image {
				width: 100%;
				height: 100%;
				object-fit: cover;
				transition: transform 0.5s ease;
			}

			.car-card:hover .car-image {
				transform: scale(1.1);
			}

			.status-badge {
				position: absolute;
				top: 1rem;
				right: 1rem;
				padding: 0.25rem 0.75rem;
				background-color: rgba(255, 255, 255, 0.9);
				backdrop-filter: blur(8px);
				border-radius: 9999px;
				font-size: 0.875rem;
			}

			.status-available {
				color: #16a34a;
			}

			/* Car Details */
			.car-details {
				padding: 1.5rem;
				display: flex;
				flex-direction: column;
				flex-grow: 1;
			}

			.car-title {
				font-size: 1.5rem;
				margin-bottom: 0.25rem;
				color: #000;
			}

			.car-subtitle {
				color: var(--text-gray);
				margin-bottom: 0.75rem;
			}

			/* Specs */
			.car-specs {
				display: flex;
				align-items: center;
				gap: 1rem;
				margin-bottom: 1rem;
				font-size: 0.875rem;
				color: var(--text-gray);
			}

			.spec-item {
				display: flex;
				align-items: center;
				gap: 0.25rem;
			}

			.spec-item svg {
				width: 1rem;
				height: 1rem;
			}

			/* Price Section */
			.car-footer {
				display: flex;
				align-items: center;
				justify-content: space-between;
				padding-top: 1rem;
				border-top: 1px solid var(--bg-light);
				margin-top: auto;
			}

			.car-price {
				font-size: 1.875rem;
				color: var(--primary);
			}

			.price-label {
				font-size: 0.875rem;
				color: var(--text-gray);
			}

			.view-more-button {
				padding: 0.75rem 1.5rem;
				background-color: #000;
				color: white;
				border: none;
				border-radius: 0.5rem;
				cursor: pointer;
				transition: all 0.3s ease;
			}

			.view-more-button:hover {
				background-color: var(--primary);
			}

			/* See More Card */
			.see-more-card {
				background: linear-gradient(to bottom right, var(--primary), #F4511E);
				border-radius: 1rem;
				overflow: hidden;
				box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
				transition: all 0.3s ease;
				height: 80%;
				display: flex;
				flex-direction: column;
				align-items: center;
				justify-content: center;
				padding: 2rem;
				cursor: pointer;
				color: white;
				text-align: center;
				width: 100%;
			}

			.see-more-card:hover {
				box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
			}

			.see-more-icon {
				width: 5rem;
				height: 5rem;
				margin: 0 auto 1.5rem;
				border-radius: 50%;
				background-color: rgba(255, 255, 255, 0.2);
				display: flex;
				align-items: center;
				justify-content: center;
				transition: transform 0.3s ease;
			}

			.see-more-icon:hover {
				transform: scale(1.1);
			}

			.see-more-icon svg {
				width: 2.5rem;
				height: 2.5rem;
			}

			.see-more-title {
				font-size: 1.875rem;
				margin-bottom: 0.75rem;
			}

			.see-more-text {
				color: rgba(255, 255, 255, 0.9);
				font-size: 1.125rem;
				margin-bottom: 1.5rem;
			}

			.see-more-button {
				display: inline-flex;
				align-items: center;
				gap: 0.5rem;
				padding: 0.75rem 1.5rem;
				background-color: white;
				color: var(--primary);
				border: none;
				border-radius: 0.5rem;
				cursor: pointer;
				transition: all 0.3s ease;
			}

			.see-more-card:hover .see-more-button {
				background-color: #000;
				color: white;
			}

			.see-more-button svg {
				width: 1.25rem;
				height: 1.25rem;
				transition: transform 0.3s ease;
			}

			.see-more-card:hover .see-more-button svg {
				transform: translateX(0.25rem);
			}

			/* CTA Section */
			.cta-container {
				position: relative;
				margin: 0 auto;
				border-radius: 1.5rem;
				overflow: hidden;
			}

			.cta-background {
				position: absolute;
				inset: 0;
				background: linear-gradient(to right, #ff5622ee, #f4501ee8);
			}

			.cta-blur-container {
				position: absolute;
				inset: 0;
				opacity: 0.1;
			}

			.cta-blur-1 {
				position: absolute;
				top: 0;
				right: 0;
				width: 24rem;
				height: 24rem;
				background-color: #fff;
				border-radius: 50%;
				filter: blur(80px);
			}

			.cta-blur-2 {
				position: absolute;
				bottom: 0;
				left: 0;
				width: 24rem;
				height: 24rem;
				background-color: #fff;
				border-radius: 50%;
				filter: blur(80px);
			}

			.cta-content {
				position: relative;
				z-index: 10;
				padding: 5rem 3rem;
				text-align: center;
				color: #fff;
				max-width: 900px;
				display: flex;
				flex-direction: column;
				align-items: center;
				justify-content: center;
			}

			.cta-title {
				font-size: var(--fs-h2);
				font-weight: 700;
				margin-bottom: 0.5rem;
			}

			.cta-text {
				font-size: var(--fs-h6);
				margin-bottom: 2.5rem;
				color: rgba(255, 255, 255, 0.9);
				margin-left: auto;
				margin-right: auto;
			}

			.cta-buttons {
				display: flex;
				gap: 1.5rem;
				justify-content: center;
			}

			.cta-button {
				padding: 1.25rem 3rem;
				border: none;
				border-radius: 0.75rem;
				font-size: 1rem;
				font-weight: 600;
				cursor: pointer;
				transition: all 0.3s ease;
				box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
			}

			.cta-button-primary {
				background-color: #fff;
				color: var(--primary);
			}

			.cta-button-primary:hover {
				transform: scale(1.05);
			}

			.cta-button-secondary {
				background-color: #000;
				color: #fff;
			}

			.cta-button-secondary:hover {
				transform: scale(1.05);
			}

			/* Responsive */
			@media (max-width: 1024px) {
				.slider-item {
					width: 33.333%;
					/* 3 items */
				}
			}

			@media (max-width: 768px) {
				.fleet-section {
					padding: 4rem 0;
				}

				.header-content h2 {
					font-size: 2rem;
				}

				.slider-item {
					width: 50%;
					/* 2 items */
				}

				.cta-content {
					padding: 3rem 1.5rem;
				}

				.cta-title {
					font-size: 2rem;
				}

				.cta-text {
					font-size: 1rem;
				}

				.cta-buttons {
					flex-direction: column;
				}

				.cta-button {
					width: 100%;
				}
			}

			@media (max-width: 640px) {
				.slider-item {
					width: 100%;
					/* 1 item */
				}
			}

			/* Contact us section */
			.contact-section {
				position: relative;
				padding: 0 0 10rem 0;
				background: linear-gradient(to bottom right, #f9fafb, #ffffff, #fff7ed);
				color: white;
				overflow: hidden;
			}

			/* Header */
			.section-header {
				text-align: center;
				margin-bottom: 8rem;
			}

			.section-header h2 {
				font-size: var(--fs-h1);
				font-weight: 700;
				margin-bottom: 1.5rem;
				line-height: 1.1;
				color: var(--black);
			}

			.section-header p {
				color: var(--text);
				font-size: var(--fs-h6);
				margin: 0 auto;
			}

			/* Grid Layout */
			.contact-grid {
				display: grid;
				grid-template-columns: 1fr 1fr;
				gap: 5rem;
			}

			.contact-grid>*:first-child {
				order: 2;
			}

			.contact-grid>*:last-child {
				order: 1;
			}

			/* Contact Form Card */

			.form-card {
				background: var(--white);
				border-radius: 1.5rem;
				padding: 4rem;
				color: var(--black);
				max-height: 95%;
				border: rgba(107, 92, 92, 0.267) 1px solid;
			}

			.form-card h3 {
				font-size: var(--fs-h3);
				font-weight: 700;
				margin-bottom: 1rem;
			}

			.form-card .subtitle {
				color: var(--text);
				font-size: var(--fs-h6);
				margin-bottom: 2.6rem;
			}

			.form-group {
				margin-bottom: 1rem;
			}

			.form-rowC {
				display: grid;
				grid-template-columns: 1fr 1fr;
				gap: 1.5rem;
			}

			.form-group label {
				display: block;
				color: var(--text-light);
				margin-bottom: 0.75rem;
				font-size: var(--fs-h6);
				font-weight: 500;
			}

			.form-group input,
			.form-group textarea {
				width: 100%;
				padding: 1rem 1.25rem;
				border: 2px solid #e5e7eb;
				border-radius: 0.75rem;
				font-size: 1rem;
				font-family: inherit;
				transition: border-color 0.3s;
			}

			.form-group input:focus,
			.form-group textarea:focus {
				outline: none;
				border-color: var(--primary);
			}

			.form-group textarea {
				resize: none;
				min-height: 150px;
			}

			.submit-btn {
				width: 100%;
				padding: 1.25rem;
				background: var(--primary);
				color: white;
				border: none;
				border-radius: 0.75rem;
				font-size: 1.125rem;
				font-weight: 500;
				cursor: pointer;
				display: flex;
				align-items: center;
				justify-content: center;
				gap: 0.75rem;
				transition: all 0.3s;
				box-shadow: 0 25px 50px -12px rgba(255, 87, 34, 0.5);
			}

			.submit-btn:hover {
				background: #F4511E;
				transform: scale(1.02);
			}

			/* Info Cards */
			.info-cards {
				display: flex;
				flex-direction: column;
				gap: 1.5em;
			}

			/* Quick Contact Card */
			.quick-contact-card {
				background: linear-gradient(to bottom right, var(--primary), #F4511E);
				border-radius: 1.5rem;
				padding: 3rem;
				position: relative;
				overflow: hidden;
			}

			.quick-contact-card::before {
				content: '';
				position: absolute;
				bottom: -5rem;
				right: -5rem;
				width: 20rem;
				height: 20rem;
				background: white;
				opacity: 0.1;
				border-radius: 50%;
			}

			.quick-contact-card h4 {
				font-size: var(--fs-h4);
				font-weight: 600;
				margin-bottom: 1.6rem;
				position: relative;
				z-index: 1;
			}

			.contact-item {
				display: flex;
				align-items: center;
				gap: 1rem;
				margin-bottom: 1.5rem;
				position: relative;
				z-index: 1;
			}

			.contact-item:last-child {
				margin-bottom: 0;
			}

			.contact-item-label {
				font-size: var(--fs-h6);
				color: var(--bg-w);
			}

			.contact-item-value {
				font-size: var(--fs-h6);
				font-weight: 500;
				color: #d6c8c3;
			}

			/* Glass Card */
			.glass-card {
				background: var(--bg-d);
				backdrop-filter: blur(30px);
				border-radius: 1.5rem;
				padding: 3rem;
			}

			.glass-card h4 {
				font-size: var(--fs-h4);
				font-weight: 600;
			}

			.glass-card .subtitle {
				color: var(--text-label);
				margin-bottom: 1rem;
			}

			.icon-box {
				width: 4rem;
				height: 4rem;
				background: var(--primary);
				border-radius: 1rem;
				display: flex;
				align-items: center;
				justify-content: center;
				flex-shrink: 0;
			}

			.icon-large path {
				fill: rgb(0, 0, 0);
			}


			.location-header {
				display: flex;
				align-items: center;
				gap: 1.5rem;
				margin-bottom: 2rem;
			}

			.address {
				color: #d1d5db;
				font-size: var(--fs-h6);
				margin-bottom: 0.5rem;
			}

			.divider {
				border-top: 1px solid rgba(255, 255, 255, 0.1);
				margin: 1.5rem 0;
			}

			.hours-header {
				display: flex;
				align-items: flex-start;
				gap: 0.75rem;
				color: var(--text-label);
				margin-bottom: 0.5rem;
			}

			.hours-text {
				color: white;
				margin-bottom: 0.25rem;
			}

			.emergency {
				color: var(--primary);
				margin-top: 0.75rem;
			}

			/* Social Media */
			.social-icons {
				display: flex;
				gap: 1rem;
			}

			.social-icon {
				width: 3.5rem;
				height: 3.5rem;
				background: rgba(255, 255, 255, 0.1);
				border-radius: 0.75rem;
				display: flex;
				align-items: center;
				justify-content: center;
				color: white;
				text-decoration: none;
				transition: all 0.3s;
			}

			.social-icon:hover {
				background: var(--primary);
			}

			.icon {
				width: 2rem;
				height: 2rem;
				fill: white;
			}

			/* SVG Icons */
			.hours-header .icon,
			.icon {
				width: 1.5rem;
				height: 1.5rem;
				stroke: currentColor;
				fill: none;
				stroke-width: 2;
				stroke-linecap: round;
				stroke-linejoin: round;
			}

			.contact-item .icon {
				width: 1.25rem;
				height: 1.25rem;
				stroke: var(--white);
				fill: none;
				stroke-width: 2;
				stroke-linecap: round;
				stroke-linejoin: round;
			}

			.icon-large {
				width: 2rem;
				height: 2rem;
			}

			.icon-large path {
				fill: none;
				stroke: black;
				stroke-width: 3;
			}

			/* Responsive */
			@media (max-width: 1024px) {
				.contact-grid {
					grid-template-columns: 1fr;
					gap: 3rem;
				}

				.section-header h2 {
					font-size: 2.5rem;
				}

				.form-card {
					padding: 2.5rem;
				}

				.form-rowC {
					grid-template-columns: 1fr;
				}
			}

			@media (max-width: 768px) {
				.contact-section {
					padding: 5rem 1.5rem;
				}

				.section-header {
					margin-bottom: 4rem;
				}

				.section-header h2 {
					font-size: 2rem;
				}

				.form-card {
					padding: 2rem;
				}

				.form-card h3 {
					font-size: 1.75rem;
				}

				.quick-contact-card,
				.glass-card {
					padding: 2rem;
				}
			}

			/*********** footer ***********/
			footer {
				position: relative;
				background: linear-gradient(to bottom right, #18181b, #000000, #18181b);
				color: white;
				overflow: hidden;
			}

			.footer .brand-description {
				text-align: left;
			}

			/* Decorative Elements */
			.decorative-blur-1 {
				position: absolute;
				top: 0;
				right: 0;
				width: 24rem;
				height: 24rem;
				background-color: var(--primary);
				opacity: 0.05;
				border-radius: 50%;
				filter: blur(80px);
				pointer-events: none;
			}

			.decorative-blur-2 {
				position: absolute;
				bottom: 0;
				left: 0;
				width: 24rem;
				height: 24rem;
				background-color: var(--primary);
				opacity: 0.05;
				border-radius: 50%;
				filter: blur(80px);
				pointer-events: none;
			}

			.footer-content {
				position: relative;
				z-index: 10;
			}

			/* Main Footer Content */
			.footer-main {
				max-width: 80rem;
				margin: 0 auto;
				padding: 4rem 1.5rem;
			}

			.footer-grid {
				display: grid;
				grid-template-columns: repeat(12, 1fr);
				gap: 3rem;
				margin-bottom: 4rem;
			}

			/* Brand Column */
			.brand-column {
				grid-column: span 4;
			}

			.brand-logo {
				font-size: 2.25rem;
				margin-bottom: 1rem;
				letter-spacing: -0.02em;
				font-weight: 600;
			}

			.brand-description {
				color: #9ca3af;
				font-size: 1.125rem;
				margin-bottom: 1.5rem;
				line-height: 1.7;
			}

			.social-links {
				display: flex;
				align-items: center;
				gap: 0.75rem;
			}

			.social-link {
				width: 3rem;
				height: 3rem;
				border-radius: 0.75rem;
				background-color: rgba(255, 255, 255, 0.05);
				border: 1px solid rgba(255, 255, 255, 0.1);
				display: flex;
				align-items: center;
				justify-content: center;
				text-decoration: none;
				color: white;
				transition: all 0.3s ease;
			}

			.social-link:hover {
				background-color: var(--primary);
				border-color: var(--primary);
			}

			.social-link svg {
				width: 1.25rem;
				height: 1.25rem;
			}

			/* Footer Columns */
			.footer-column {
				grid-column: span 2;
			}

			.footer-column-title {
				font-size: 1.125rem;
				margin-bottom: 1.5rem;
				font-weight: 500;
			}

			.footer-links {
				list-style: none;
				padding: 0;
				margin: 0;
			}

			.footer-links li {
				margin-bottom: 0.75rem;
			}

			.footer-links a {
				color: #9ca3af;
				text-decoration: none;
				transition: color 0.3s ease;
			}

			.footer-links a:hover {
				color: var(--primary);
			}

			/* Contact Items */
			.contact-item {
				display: flex;
				align-items: flex-start;
				gap: 0.75rem;
				margin-bottom: 1rem;
			}

			.contact-item svg {
				width: 1.25rem;
				height: 1.25rem;
				flex-shrink: 0;
				margin-top: 0.125rem;
				color: var(--primary);
			}

			.contact-item span,
			.contact-item a {
				color: #9ca3af;
				font-size: 0.875rem;
			}

			.contact-item a {
				text-decoration: none;
				transition: color 0.3s ease;
			}

			.contact-item a:hover {
				color: var(--primary);
			}

			/* Bottom Bar */
			.footer-bottom {
				padding-top: 2rem;
				border-top: 1px solid rgba(255, 255, 255, 0.1);
				display: flex;
				align-items: center;
				justify-content: space-between;
			}

			.copyright {
				color: var(--text-gray);
			}

			.support-badge {
				display: flex;
				align-items: center;
				gap: 0.5rem;
			}

			.support-badge svg {
				width: 1rem;
				height: 1rem;
				color: var(--primary);
			}

			.support-badge span {
				color: #9ca3af;
				font-size: 0.875rem;
			}

			/* Responsive */
			@media (max-width: 1024px) {
				.newsletter-grid {
					grid-template-columns: 1fr;
					gap: 2rem;
				}

				.footer-grid {
					grid-template-columns: repeat(6, 1fr);
				}

				.brand-column {
					grid-column: span 6;
				}

				.footer-column {
					grid-column: span 3;
				}
			}

			@media (max-width: 767px) {
				.newsletter-title {
					font-size: 2rem;
				}

				.newsletter-form {
					flex-direction: column;
				}

				.footer-grid {
					grid-template-columns: 1fr;
				}

				.brand-column,
				.footer-column {
					grid-column: span 1;
				}

				.footer-bottom {
					flex-direction: column;
					gap: 1rem;
					text-align: center;
				}
			}


			/* drop down */

			/* Container */
			.user-dropdown {
				position: relative;
				display: flex;
				align-items: center;
				gap: 10px;
				font-family: Arial, sans-serif;
			}

			/* Profile circle */
			.profile {
				width: 40px;
				height: 40px;
				background-color: #444;
				color: #fff;
				border-radius: 50%;
				display: flex;
				justify-content: center;
				align-items: center;
				font-weight: bold;
			}

			/* Arrow button */
			.dropdown-toggle {
				background: none;
				border: none;
				cursor: pointer;
				padding: 4px;
			}

			/* Dropdown menu */
			.dropdown-menu {
				position: absolute;
				top: 55px;
				right: 0;
				min-width: 180px;
				background: #fff;
				border-radius: 8px;
				padding: 10px 0;
				box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
				display: none;
				flex-direction: column;
				animation: fadeIn 0.15s ease-out;
				z-index: 10;
			}

			/* Items */
			.dropdown-menu a {
				padding: 10px 15px;
				text-decoration: none;
				color: #333;
				display: block;
				transition: 0.2s;
			}

			.dropdown-menu a:hover {
				background-color: #f3f3f3;
			}

			/* Divider */
			.dropdown-menu hr {
				border: none;
				border-top: 1px solid #ddd;
				margin: 8px 0;
			}

			.dropdown-menu .logout {
				color: red;
			}

			/* Show menu */
			.dropdown-menu.show {
				display: flex;
			}

			/* Animation */
			@keyframes fadeIn {
				from {
					opacity: 0;
					transform: translateY(-5px);
				}

				to {
					opacity: 1;
					transform: translateY(0);
				}
			}

			/* Utility */
			.flex-center {
				display: flex;
				justify-content: center;
				align-items: center;
			}

			/* humburger menu */

			.input__toggel {
				display: none;
				transition: top 50ms ease;
			}

			.nav__toggel {
				cursor: pointer;
				display: none;
			}

			.navig_menu {
				position: relative;
			}

			.navig_menu,
			.navig_menu::after,
			.navig_menu::before {
				display: block;
				width: 25px;
				height: 4px;
				background-color: var(--black);
			}

			.navig_menu::after,
			.navig_menu::before {
				content: '';
				position: absolute;
				transition: rotate 500ms ease;
			}

			.navig_menu::after {
				bottom: 7px;
			}

			.navig_menu::before {
				top: 7px;
			}

			/* nav drop */

			.input__toggel:checked~.nav__menu {
				top: 10em;
			}

			.input__toggel:checked~label span::after {
				display: none;
			}

			.input__toggel:checked~label span::before {
				top: 0;
				rotate: 90deg;
			}

			label span {
				transition: rotate 500ms ease;
			}

			.input__toggel:checked~label span {
				rotate: 45deg;
			}

			/* Navigation for mobile devices */

			@media screen and (max-width:1000px) {
				.nav__menu {
					position: fixed;
					top: -200%;
					width: 90%;
					margin: 0 auto;
					text-align: center;
					transition: top 0.7s ease;
					background-color: white;
					flex-direction: column;
					padding: 2.5em;
					border-radius: 1rem;
					box-shadow: var(--shadow-lg);
					left: 1.5em;
				}

				.nav__toggel {
					display: block;
				}

				.nav__list {
					flex-direction: column;
					gap: 2em;
					width: 100%;
					padding: 2em 0;
				}
			}

			/* responsive touches */

			@media screen and (max-width: 330px) {}

			@media screen and (min-width: 540px) {}

			@media screen and (max-width: 768px) {
				.search-form {
					display: none;
				}

				.stats {
					margin-top: 0px;
				}

				.fleet-section .section-header {
					flex-direction: column;
					gap: 3rem;
				}

				.form-card {
					max-height: 100%;
				}

				.contact-section {
					padding: 0 1.5rem;
					margin-top: -3rem;
				}
			}



			/* modal */

			/* Auth Page Layout */
			.logcontainer {
				background: #000;
				color: white;
			}

			.auth-page {
				min-height: 100vh;
				display: flex;
				position: relative;
				overflow: hidden;
				padding: 6rem;
				align-items: flex-start;
			}

			/* Decorative Elements */
			.decorative-glow-1 {
				position: absolute;
				top: -10%;
				right: -10%;
				width: 40rem;
				height: 40rem;
				background: var(--primary);
				opacity: 0.15;
				border-radius: 50%;
				filter: blur(120px);
				animation: pulse 8s ease-in-out infinite;
			}

			.decorative-glow-2 {
				position: absolute;
				bottom: -10%;
				left: -10%;
				width: 40rem;
				height: 40rem;
				background: var(--primary);
				opacity: 0.1;
				border-radius: 50%;
				filter: blur(120px);
				animation: pulse 10s ease-in-out infinite reverse;
			}

			@keyframes pulse {

				0%,
				100% {
					transform: scale(1);
					opacity: 0.15;
				}

				50% {
					transform: scale(1.1);
					opacity: 0.2;
				}
			}

			/* Left Side - Branding */
			.brand-side {
				flex: 1;
				display: flex;
				flex-direction: column;
				justify-content: center;
				align-items: center;
				padding: 2rem 4rem;
				position: relative;
				z-index: 1;
			}

			.logo {
				font-size: var(--fs-h1);
				font-weight: 700;
				letter-spacing: -0.02em;
				margin-bottom: 1rem;
			}

			.brand-tagline {
				font-size: var(--fs-h5);
				color: #d1d5db;
				text-align: center;
				max-width: 500px;
				margin-bottom: 1rem;
			}

			.brand-description {
				color: var(--text-label);
				text-align: center;
				max-width: 500px;
				font-size: 1.125rem;
			}

			.brand-features {
				margin-top: 2rem;
				display: flex;
				flex-direction: column;
				gap: 2rem;
				max-width: 500px;
			}

			.feature-item {
				display: flex;
				align-items: center;
				gap: 1rem;
				padding: 1.5rem;
				background: rgba(255, 255, 255, 0.05);
				backdrop-filter: blur(10px);
				border: 1px solid rgba(255, 255, 255, 0.1);
				border-radius: 1rem;
			}

			.feature-icon {
				width: 3rem;
				height: 3rem;
				background: var(--primary);
				border-radius: 0.75rem;
				display: flex;
				align-items: center;
				justify-content: center;
				flex-shrink: 0;
			}

			.feature-text h4 {
				font-size: 1.125rem;
				margin-bottom: 0.25rem;
			}

			.feature-text p {
				color: #9ca3af;
				font-size: 0.875rem;
			}

			/* Right Side - Auth Forms */
			.form-side {
				display: flex;
				justify-content: center;
				align-items: center;
				padding: 2rem;
				position: relative;
				z-index: 1;
			}



			/* Tabs */
			.auth-tabs {
				display: flex;
				gap: 1rem;
				margin-bottom: 3rem;
				background: rgba(255, 255, 255, 0.05);
				border-radius: 1rem;
				padding: 0.5rem;
			}

			.tab-btn {
				flex: 1;
				padding: 1rem;
				background: transparent;
				color: #9ca3af;
				border: none;
				border-radius: 0.75rem;
				font-size: 1.125rem;
				font-weight: 600;
				cursor: pointer;
				transition: all 0.3s;
			}

			.tab-btn.active {
				background: white;
				color: #000;
			}

			/* Form Cards */

			.form-card {
				background: rgba(255, 255, 255, 0.05);
				backdrop-filter: blur(20px);
				border: 1px solid rgba(255, 255, 255, 0.1);
				border-radius: 1.5rem;
				padding: 2.2rem;
			}

			.form-card h2 {
				font-size: var(--fs-h2);
				margin-bottom: 0.5rem;
				color: white;
			}

			.form-card .subtitle {
				color: #9ca3af;
				margin-bottom: 2.5rem;
				font-size: 1rem;
			}

			.form-content {
				display: none;
			}

			.form-content.active {
				display: block;
			}

			/* Form Groups */
			.authForm .form-group {
				margin-bottom: 1.5rem;
			}

			.signupform .form-row {
				display: grid;
				grid-template-columns: 1fr 1fr;
				gap: 1rem;
			}

			.authForm .form-group label {
				display: block;
				margin-bottom: 0.5rem;
				color: #d1d5db;
				font-size: 0.9375rem;
				font-weight: 500;
			}

			.authForm .form-group input {
				width: 100%;
				padding: 1rem 1.25rem;
				background: rgba(255, 255, 255, 0.1);
				border: 1px solid rgba(255, 255, 255, 0.2);
				border-radius: 0.75rem;
				color: white;
				font-size: 1rem;
				font-family: inherit;
				transition: all 0.3s;
			}

			.authForm .form-group input::placeholder {
				color: var(--text-gray);
			}

			.form-group input:focus {
				outline: none;
				background: rgba(255, 255, 255, 0.15);
				border-color: var(--primary);
			}

			.checkbox-group {
				display: flex;
				align-items: center;
				gap: 0.5rem;
				margin-bottom: 1.5rem;
			}

			.checkbox-group input[type="checkbox"] {
				width: 1.25rem;
				height: 1.25rem;
				cursor: pointer;
			}

			.checkbox-group label {
				color: #d1d5db;
				font-size: 0.875rem;
				cursor: pointer;
			}

			.forgot-password {
				text-align: right;
				margin-top: -1rem;
				margin-bottom: 1.5rem;
			}

			.forgot-password a {
				color: var(--primary);
				text-decoration: none;
				font-size: 0.875rem;
				transition: color 0.3s;
			}

			.forgot-password a:hover {
				color: #F4511E;
			}

			/* Buttons */
			.submit-btn {
				width: 100%;
				padding: 1.25rem;
				background: var(--primary);
				color: white;
				border: none;
				border-radius: 0.75rem;
				font-size: 1.125rem;
				font-weight: 600;
				cursor: pointer;
				transition: all 0.3s;
				box-shadow: 0 10px 20px -5px rgba(255, 87, 34, 0.4);
			}

			.submit-btn:hover {
				background: #F4511E;
				transform: translateY(-2px);
				box-shadow: 0 15px 30px -5px rgba(255, 87, 34, 0.5);
			}

			.divider {
				display: flex;
				align-items: center;
				margin: 2rem 0;
				color: var(--text-gray);
				font-size: 0.875rem;
			}

			.divider::before,
			.divider::after {
				content: '';
				flex: 1;
				height: 1px;
				background: rgba(255, 255, 255, 0.1);
			}

			.divider span {
				padding: 0 1rem;
			}

			/* Social Buttons */
			.social-buttons {
				display: grid;
				grid-template-columns: 1fr 1fr;
				gap: 1rem;
			}

			.social-btn {
				padding: 1rem;
				background: rgba(255, 255, 255, 0.05);
				border: 1px solid rgba(255, 255, 255, 0.1);
				border-radius: 0.75rem;
				color: white;
				cursor: pointer;
				display: flex;
				align-items: center;
				justify-content: center;
				gap: 0.75rem;
				font-size: 1rem;
				font-weight: 500;
				transition: all 0.3s;
			}

			.social-btn:hover {
				background: rgba(255, 255, 255, 0.1);
				border-color: rgba(255, 255, 255, 0.2);
			}

			/* Icons */
			.social-btn.icon {
				width: 1.25rem;
				height: 1.25rem;
				stroke: currentColor;
				fill: none;
				stroke-width: 2;
				stroke-linecap: round;
				stroke-linejoin: round;
			}

			.social-btn .icon-fill {
				fill: currentColor;
				stroke: none;
			}

			/* Terms */
			.terms {
				margin-top: 2rem;
				text-align: center;
				color: #9ca3af;
				font-size: 0.875rem;
			}

			.terms a {
				color: var(--primary);
				text-decoration: none;
			}

			.terms a:hover {
				text-decoration: underline;
			}

			/* Responsive */
			@media (max-width: 1024px) {
				.auth-page {
					flex-direction: column;
					padding: 1rem;
				}

				.auth-container {
					max-width: 70%;
				}

				.brand-side {
					display: none;
				}

				.brand-features {
					display: none;
				}

				.form-side {
					padding: 2rem;
				}
			}

			@media (max-width: 640px) {
				.logo {
					font-size: 2rem;
				}

				.brand-tagline {
					font-size: 1.25rem;
				}

				.form-card {
					padding: 2rem;
				}

				.signupform .form-row {
					grid-template-columns: 1fr;
				}

				.social-buttons {
					grid-template-columns: 1fr;
				}
			}

			/* Browse Page Styles */
			.browse-section {
				background-color: var(--bg-w);
				min-height: 100vh;
				margin-top: 4rem;
			}

			.browse-header {
				margin-bottom: 3.5rem;
			}

			.browse-header h2 {
				font-size: var(--fs-h2);
				font-weight: 700;
				margin-bottom: 0.rem;
				line-height: 1.1;
				color: var(--black);
			}

			.browse-subtitle {
				color: var(--text);
				font-size: var(--fs-h6);
			}

			.browse-search-form {
				position: relative;
				margin: 0 auto 4rem !important;
				box-shadow: none;
				top: auto;
				width: 100%;
				max-width: 100%;
				display: flex;
				align-items: center;
			}

			.browse-search-form .form-row {
				flex: 2;
				align-items: center;
				padding-right: 1em;
			}

			.browse-search-form .btn-brws {
				height: 3.2rem;
				position: relative;
				bottom: -13px;
			}

			.browse-select {
				padding: 1em;
			}

			.browse-grid {
				display: grid;
				grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
				gap: 0 2rem;
				padding: 1rem 0;
			}

			@media (max-width: 768px) {
				.browse-grid {
					grid-template-columns: 1fr;
				}

				.browse-search-form {
					display: block !important;
					padding: 1.5rem;
				}
			}


			/* Car Details Page Styles */
			.car-details-main {
				padding-bottom: 5rem;
				background-color: var(--bg-w);
			}

			.back-nav {
				margin-bottom: 2rem;
			}

			.back-link {
				display: inline-flex;
				align-items: center;
				gap: 0.5rem;
				color: var(--text);
				font-weight: 500;
				transition: color 0.3s;
			}

			.back-link:hover {
				color: var(--primary);
			}

			.details-grid {
				display: grid;
				grid-template-columns: 1.2fr 1fr;
				gap: 4rem;
				align-items: start;
			}

			/* Gallery */
			.gallery-container {
				display: flex;
				flex-direction: column;
				gap: 1rem;
			}

			.main-image {
				width: 100%;
				aspect-ratio: 4/3;
				object-fit: cover;
				border-radius: 1rem;
			}

			.thumbnail-grid {
				display: grid;
				grid-template-columns: repeat(3, 1fr);
				gap: 1rem;
			}

			.thumbnail {
				width: 100%;
				aspect-ratio: 4/3;
				object-fit: cover;
				border-radius: 0.5rem;
				cursor: pointer;
				transition: opacity 0.3s;
			}

			.thumbnail:hover {
				opacity: 0.8;
			}

			/* Info Section */
			.car-info-header {
				margin-bottom: 2rem;
			}

			.detail-title {
				font-size: 2.5rem;
				line-height: 1.1;
				margin-bottom: 0.5rem;
				color: var(--black);
			}

			.detail-model {
				font-size: 1.25rem;
				color: var(--text-light);
				margin-bottom: 1.5rem;
			}

			.detail-price-box {
				display: flex;
				align-items: baseline;
				gap: 0.5rem;
				margin-bottom: 2rem;
			}

			.detail-price {
				font-size: 2.5rem;
				font-weight: 700;
				color: var(--black);
			}

			.detail-price-label {
				font-size: 1.1rem;
				color: var(--text-light);
			}

			/* Specs Grid */
			.specs-section {
				background: #fff;
				border: 1px solid #eee;
				padding: 2rem;
				border-radius: 1rem;
				margin-bottom: 2rem;
			}

			.section-label {
				font-size: 1.25rem;
				font-weight: 700;
				margin-bottom: 1.5rem;
				color: var(--black);
			}

			.specs-grid {
				display: grid;
				grid-template-columns: 1fr 1fr;
				gap: 2rem;
			}

			.spec-detail-item {
				display: flex;
				gap: 1rem;
				align-items: flex-start;
			}

			.spec-icon {
				width: 1.5rem;
				height: 1.5rem;
				color: var(--text-light);
			}

			.spec-content label {
				display: block;
				font-size: 0.875rem;
				color: var(--text-light);
				margin-bottom: 0.25rem;
			}

			.spec-content span {
				font-weight: 600;
				color: var(--black);
				font-size: 1.1rem;
			}

			/* Features */
			.features-section {
				background: #fff;
				border: 1px solid #eee;
				padding: 2rem;
				border-radius: 1rem;
				margin-bottom: 2rem;
			}

			.features-list {
				display: grid;
				grid-template-columns: 1fr 1fr;
				gap: 1rem;
			}

			.feature-check {
				display: flex;
				align-items: center;
				gap: 0.75rem;
				font-weight: 500;
			}

			.check-icon {
				color: var(--green-darker);
				width: 1.25rem;
				height: 1.25rem;
			}

			/* Additional Info */
			.info-section {
				background: #fff;
				border: 1px solid #eee;
				padding: 2rem;
				border-radius: 1rem;
			}

			.info-row {
				display: flex;
				justify-content: space-between;
				padding: 0.75rem 0;
				border-bottom: 1px solid #eee;
			}

			.info-row:last-child {
				border-bottom: none;
			}

			.info-label {
				color: var(--text-light);
			}

			.info-value {
				font-weight: 500;
				color: var(--black);
			}


			/* Action */
			.book-now-btn {
				width: 100%;
				background-color: #ff5500;
				color: white;
				padding: 1.25rem;
				font-size: 1.1rem;
				font-weight: 600;
				border-radius: 0.75rem;
				transition: background-color 0.3s;
				margin-top: 1rem;
				box-shadow: 0 4px 6px -1px rgba(255, 85, 0, 0.2);
			}

			.book-now-btn:hover {
				background-color: #c44c06;
			}

			@media (max-width: 1024px) {
				.details-grid {
					grid-template-columns: 1fr;
					gap: 2rem;
				}
			}

			/* ===== BOOKING MODAL STYLES (CORRECTED) ===== */
			.booking-modal {
				display: none;
				position: fixed;
				top: 0;
				left: 0;
				width: 100%;
				height: 100%;
				background-color: rgba(0, 0, 0, 0.7);
				z-index: 9999;
				overflow-y: auto;
				animation: bookingFadeIn 0.3s ease;
			}

			@keyframes bookingFadeIn {
				from {
					opacity: 0;
				}

				to {
					opacity: 1;
				}
			}

			.booking-modal-content {
				background-color: white;
				margin: 6rem auto;
				padding: 0;
				width: 95%;
				max-width: 520px;
				border-radius: 12px;
				box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
				animation: bookingSlideUp 0.4s ease;
				position: relative;
			}

			@keyframes bookingSlideUp {
				from {
					transform: translateY(50px);
					opacity: 0;
				}

				to {
					transform: translateY(0);
					opacity: 1;
				}
			}

			.booking-modal-header {
				padding: 2rem 1.5rem 0 1.5rem;
				color: var(--black);
				border-radius: 12px 12px 0 0;
				display: flex;
				justify-content: space-between;
				align-items: center;
			}

			.booking-modal-header h2 {
				margin: 0;
				font-size: 1.4rem;
				font-weight: 600;
			}

			.booking-modal-close {
				font-size: 24px;
				cursor: pointer;
				opacity: 0.8;
				transition: opacity 0.2s;
				background: none;
				border: none;
				color: var(--black);
				font-weight: bold;
				line-height: 1;
			}

			.booking-modal-close:hover {
				opacity: 1;
			}

			.booking-modal-body {
				padding: 1.5rem;
				color: var(--text);
			}

			.booking-modal-body h3 {
				color: var(--black);
				margin-bottom: 20px;
				font-size: 1.2rem;
				font-weight: 600;
			}

			/* Form Elements in Booking Modal */
			.booking-form-group {
				margin-bottom: 20px;
			}

			.booking-form-group label {
				display: block;
				margin-bottom: 8px;
				color: var(--text);
				font-weight: 500;
				font-size: 0.95rem;
			}

			.booking-date-input {
				width: 100%;
				padding: 12px 15px;
				border: 1px solid #ddd;
				border-radius: 8px;
				font-size: 16px;
				transition: border-color 0.3s;
				font-family: inherit;
				color: var(--text);
			}

			.booking-date-input:focus {
				outline: none;
				border-color: var(--primary);
				box-shadow: 0 0 0 3px rgba(255, 85, 0, 0.1);
			}

			/* Summary Box in Booking Modal */
			.booking-summary-box,
			.booking-price-summary,
			.booking-booking-summary {
				background: #f8f9fa;
				padding: 18px;
				border-radius: 8px;
				margin: 20px 0;
				border: 1px solid #e9ecef;
			}

			.booking-summary-item {
				display: flex;
				justify-content: space-between;
				margin-bottom: 10px;
				color: var(--text);
				font-size: 0.95rem;
			}

			.booking-summary-item.total {
				font-size: 1.1rem;
				font-weight: 700;
				color: var(--black);
				margin-top: 12px;
				padding-top: 12px;
				border-top: 2px solid #dee2e6;
			}

			/* Extras List in Booking Modal */
			.booking-extras-list {
				margin: 20px 0;
			}

			.booking-extra-item {
				display: flex;
				justify-content: space-between;
				align-items: center;
				padding: 15px;
				background: white;
				border: 1px solid #e9ecef;
				border-radius: 8px;
				margin-bottom: 10px;
				transition: all 0.3s;
				cursor: pointer;
			}

			.booking-extra-item:hover {
				border-color: var(--primary);
				box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
			}

			.booking-extra-info {
				display: flex;
				align-items: center;
				gap: 12px;
				flex: 1;
			}

			.booking-extra-checkbox {
				width: 18px;
				height: 18px;
				accent-color: var(--primary);
				cursor: pointer;
				margin: 0;
			}

			.booking-extra-info label {
				font-weight: 500;
				cursor: pointer;
				color: var(--text);
				flex: 1;
			}

			.booking-extra-price {
				color: var(--text-light);
				font-size: 0.85rem;
				white-space: nowrap;
			}

			.booking-extra-total {
				font-weight: 600;
				color: var(--primary);
				font-size: 0.95rem;
				min-width: 70px;
				text-align: right;
			}

			/* Payment Section in Booking Modal */
			.booking-payment-section {
				margin: 20px 0;
			}

			.booking-payment-section h4 {
				font-size: 1.1rem;
				margin-bottom: 15px;
				color: var(--black);
			}

			.booking-payment-methods {
				display: flex;
				gap: 15px;
				margin-top: 10px;
			}

			.booking-payment-option {
				display: flex;
				align-items: center;
				gap: 8px;
				padding: 10px 16px;
				border: 1px solid #ddd;
				border-radius: 6px;
				cursor: pointer;
				transition: all 0.3s;
				flex: 1;
				background: white;
			}

			.booking-payment-option:hover {
				border-color: var(--primary);
			}

			.booking-payment-option input[type="radio"] {
				accent-color: var(--primary);
			}

			/* Confirmation Booking Modal */
			.booking-confirmation {
				text-align: center;
				padding: 20px;
			}

			.booking-confirmation-icon {
				font-size: 60px;
				color: #28a745;
				margin-bottom: 20px;
			}

			.booking-confirmation-details {
				background: #f8f9fa;
				padding: 20px;
				border-radius: 8px;
				margin: 25px 0;
			}

			.booking-detail-item {
				display: flex;
				justify-content: space-between;
				margin-bottom: 12px;
				font-size: 1rem;
				color: var(--text);
			}

			.booking-detail-item:last-child {
				font-weight: 700;
				font-size: 1.2rem;
				color: var(--black);
				margin-top: 10px;
				padding-top: 10px;
				border-top: 2px solid #dee2e6;
			}

			/* Booking Modal Buttons */
			.booking-btn-primary,
			.booking-btn-secondary {
				padding: 14px 28px;
				border: none;
				border-radius: 8px;
				font-size: 16px;
				font-weight: 600;
				cursor: pointer;
				transition: all 0.3s ease;
				display: inline-flex;
				align-items: center;
				justify-content: center;
				gap: 8px;
				font-family: inherit;
			}

			.booking-btn-primary {
				background: linear-gradient(135deg, var(--primary) 0%, var(--primary-darker) 100%);
				color: white;
				width: 100%;
			}

			.booking-btn-primary:hover {
				transform: translateY(-2px);
				box-shadow: 0 6px 20px rgba(255, 85, 0, 0.3);
			}

			.booking-btn-secondary {
				background: #f0f0f0;
				color: var(--text);
				border: 1px solid #ddd;
			}

			.booking-btn-secondary:hover {
				background: #e0e0e0;
			}

			.booking-modal-actions {
				display: flex;
				gap: 15px;
				margin-top: 25px;
				text-align: center;
			}

			/* Hide number input arrows */
			.booking-date-input::-webkit-inner-spin-button,
			.booking-date-input::-webkit-outer-spin-button {
				-webkit-appearance: none;
				margin: 0;
			}

			/* Responsive Booking Modal */
			@media (max-width: 600px) {
				.booking-modal-content {
					width: 95%;
					margin: 20px auto;
					max-width: 95%;
				}

				.booking-modal-header {
					padding: 18px 20px;
				}

				.booking-modal-body {
					padding: 20px;
				}

				.booking-modal-actions {
					flex-direction: column;
				}

				.booking-payment-methods {
					flex-direction: column;
				}

				.booking-btn-primary,
				.booking-btn-secondary {
					width: 100%;
				}
			}

			/* Ensure modals are above everything */
			.booking-modal {
				z-index: 99999 !important;
			}

			.booking-modal-content {
				z-index: 999999 !important;
			}

			/* Fix for date inputs */
			input[type="date"] {
				appearance: none;
				-webkit-appearance: none;
				-moz-appearance: none;
				min-height: 45px;
			}

			/* Make sure buttons work with your theme */
			.book-now-btn,
			.view-more-button {
				cursor: pointer;
			}

			/* Ensure modals are centered on mobile */
			@media (max-width: 768px) {
				.booking-modal-content {
					margin: 5rem auto;
					max-height: 90vh;
					overflow-y: auto;
				}
			}

			/* ============================================
   DASHBOARD / PROFILE STYLES
   ============================================ */
			.dashboard-container {
				display: grid;
				grid-template-columns: 280px 1fr;
				gap: 2rem;
				max-width: 1400px;
				margin: 0 auto;
				padding: 2rem;
				min-height: calc(100vh - 80px);
				/* Adjust based on header height */
				background-color: var(--bg-N);
			}

			@media (max-width: 900px) {
				.dashboard-container {
					grid-template-columns: 1fr;
				}
			}

			/* Sidebar */
			.dashboard-sidebar {
				background: var(--white);
				border-radius: var(--radius);
				padding: 2rem;
				height: h-fit;
				box-shadow: var(--shadow);
			}

			.user-profile-summary {
				text-align: center;
				margin-bottom: 2rem;
				padding-bottom: 2rem;
				border-bottom: 1px solid var(--grey-lighter);
			}

			.user-avatar {
				width: 80px;
				height: 80px;
				background: var(--blue);
				color: var(--blue-darker);
				border-radius: 50%;
				display: flex;
				align-items: center;
				justify-content: center;
				font-size: 2rem;
				font-weight: 600;
				margin: 0 auto 1rem;
			}

			.sidebar-nav {
				display: flex;
				flex-direction: column;
				gap: 0.5rem;
			}

			.sidebar-link {
				display: flex;
				align-items: center;
				gap: 1rem;
				padding: 1rem;
				border-radius: 8px;
				color: var(--text);
				text-decoration: none;
				transition: var(--transition);
				font-weight: 500;
			}

			.sidebar-link:hover,
			.sidebar-link.active {
				background: #fff7ed;
				/* Light orange bg */
				color: var(--primary);
			}

			.sidebar-link svg {
				width: 20px;
				height: 20px;
			}

			/* Main Content Area */
			.dashboard-content {
				display: flex;
				flex-direction: column;
				gap: 2rem;
			}

			.content-header {
				margin-bottom: 1rem;
			}

			.content-title {
				font-size: var(--fs-h4);
				font-weight: var(--font-bold);
				color: var(--text);
				margin-bottom: 0.5rem;
			}

			.content-subtitle {
				color: var(--text-light);
			}

			/* Stats Cards */
			.stats-grid {
				display: grid;
				grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
				gap: 1.5rem;
				margin-bottom: 2rem;
			}

			.stat-card {
				background: var(--white);
				padding: 1.5rem;
				border-radius: var(--radius);
				box-shadow: var(--shadow);
				display: flex;
				flex-direction: column;
				gap: 0.5rem;
				border: 1px solid transparent;
				transition: var(--transition);
			}

			.stat-card:hover {
				border-color: var(--primary);
			}

			.stat-label {
				color: var(--text-light);
				font-size: var(--fs-small);
				font-weight: 500;
			}

			.stat-value {
				font-weight: var(--font-bold);
				color: var(--primary);
			}

			/* Rental List (Horizontal Cards) */
			.rental-list {
				display: flex;
				flex-direction: column;
				gap: 1.5rem;
			}

			.rental-card-horizontal {
				background: var(--white);
				border-radius: var(--radius);
				box-shadow: var(--shadow);
				display: grid;
				grid-template-columns: 200px 1fr;
				overflow: hidden;
				transition: var(--transition);
			}

			.rental-card-horizontal:hover {
				transform: translateY(-2px);
				box-shadow: var(--shadow-lg);
			}

			.rental-image-wrapper {
				height: 100%;
				min-height: 180px;
				position: relative;
			}

			.rental-image-wrapper img {
				width: 100%;
				height: 100%;
				object-fit: cover;
			}

			.rental-card-content {
				padding: 1.5rem;
				display: flex;
				flex-direction: column;
				justify-content: space-between;
			}

			.rental-header {
				display: flex;
				justify-content: space-between;
				align-items: flex-start;
				margin-bottom: 1rem;
			}

			.rental-title h3 {
				font-size: var(--fs-h5);
				margin-bottom: 0.25rem;
			}

			.rental-subtitle {
				color: var(--text-light);
				font-size: var(--fs-small);
			}

			.rental-price-total {
				text-align: right;
			}

			.total-label {
				display: block;
				font-size: var(--fs-small-2);
				color: var(--text-light);
				text-transform: uppercase;
			}

			.total-amount {
				font-size: var(--fs-h5);
				font-weight: var(--font-bold);
				color: var(--primary);
			}

			.rental-details-grid {
				display: grid;
				grid-template-columns: repeat(3, 1fr);
				gap: 1rem;
				margin-bottom: 1rem;
				padding-bottom: 1rem;
				border-bottom: 1px solid var(--grey-lighter);
			}

			.detail-block label {
				display: block;
				font-size: var(--fs-small-2);
				color: var(--text-light);
				margin-bottom: 0.25rem;
			}

			.detail-block span {
				font-weight: 500;
				color: var(--text);
				font-size: var(--fs-small);
			}

			.rental-footer {
				display: flex;
				justify-content: space-between;
				align-items: center;
			}

			.payment-status.paid {
				color: var(--green-darker);
				font-weight: 500;
			}

			.payment-status.pending {
				color: #ca8a04;
				font-weight: 500;
			}

			.rental-badge {
				display: inline-flex;
				align-items: center;
				gap: 0.5rem;
				padding: 0.25rem 0.75rem;
				border-radius: 20px;
				font-size: var(--fs-small);
				font-weight: 500;
			}

			.rental-badge.ongoing {
				background: var(--blue);
				color: var(--blue-darker);
			}

			.rental-badge.completed {
				background: var(--grey-lighter);
				color: var(--text);
			}

			/* Profile Form */
			.profile-form-grid {
				display: grid;
				grid-template-columns: repeat(2, 1fr);
				gap: 1.5rem;
				margin-bottom: 1.5rem;
			}

			.profile-section {
				background: var(--white);
				padding: 2rem;
				border-radius: var(--radius);
				box-shadow: var(--shadow);
				margin-bottom: 2rem;
			}

			.section-header-row {
				display: flex;
				justify-content: space-between;
				align-items: center;
				margin-bottom: 1.5rem;
			}

			@media (max-width: 768px) {
				.rental-card-horizontal {
					grid-template-columns: 1fr;
				}

				.rental-image-wrapper {
					height: 200px;
				}

				.profile-form-grid {
					grid-template-columns: 1fr;
				}

				.rental-details-grid {
					grid-template-columns: 1fr 1fr;
				}
			}

			/* ========================================= */
			/* NEW UI STYLES (Profile & Rental History) */
			/* ========================================= */

			/* Page Header Override for New UI */
			.page-header {
				margin-bottom: 2rem;
			}

			.title-wrapper {
				display: flex;
				align-items: center;
				gap: 0.75rem;
				margin-bottom: 0.5rem;
			}

			.title-accent {
				width: 0.25rem;
				height: 2rem;
				background: linear-gradient(to bottom, var(--primary), var(--primary-light));
				border-radius: 9999px;
			}

			.page-title {
				font-size: 2rem;
				color: var(--text-dark);
				font-weight: 600;
			}

			.page-subtitle {
				color: var(--text-gray);
				font-size: 1rem;
			}

			/* Profile Layout */
			.profile-layout {
				display: grid;
				grid-template-columns: 1fr 2fr;
				gap: 2rem;
			}

			/* Profile Sidebar */
			.profile-sidebar {
				position: relative;
			}

			.profile-card {
				background: white;
				border-radius: 1.5rem;
				padding: 2rem;
				box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
				border: 1px solid var(--bg-light);
				position: sticky;
				top: 6rem;
			}

			.profile-image-wrapper {
				margin-bottom: 1.5rem;
			}

			.profile-image-container {
				position: relative;
				width: 8rem;
				height: 8rem;
				margin: 0 auto;
			}

			.profile-image-border {
				width: 100%;
				height: 100%;
				border-radius: 50%;
				background: linear-gradient(to bottom right, var(--primary), var(--primary-light), #a855f7);
				padding: 0.25rem;
			}

			.profile-image-inner {
				width: 100%;
				height: 100%;
				border-radius: 50%;
				background: white;
				padding: 0.25rem;
			}

			.profile-image {
				width: 100%;
				height: 100%;
				border-radius: 50%;
				object-fit: cover;
			}

			.camera-btn {
				position: absolute;
				bottom: 0;
				right: 0;
				width: 2.5rem;
				height: 2.5rem;
				background: linear-gradient(to bottom right, var(--primary), var(--primary-light));
				border-radius: 50%;
				border: none;
				display: flex;
				align-items: center;
				justify-content: center;
				color: white;
				cursor: pointer;
				box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
				transition: transform 0.2s;
			}

			.camera-btn:hover {
				transform: scale(1.1);
			}

			.user-info {
				text-align: center;
				margin-bottom: 1.5rem;
			}

			.user-name {
				color: var(--text-dark);
				margin-bottom: 0.25rem;
				font-size: 1.25rem;
			}

			.user-email {
				color: var(--text-gray);
				font-size: 0.875rem;
			}

			.user-stats {
				border-top: 1px solid var(--bg-light);
				padding-top: 1.5rem;
			}

			.stat-row {
				display: flex;
				align-items: center;
				justify-content: space-between;
				margin-bottom: 1rem;
			}

			.stat-row .stat-value,
			.stat-row .stat-label {
				font-size: var(--fs-h6);
			}

			.stat-row:last-child {
				margin-bottom: 0;
			}

			.stat-label {
				color: var(--text-gray);
				font-size: 0.875rem;
			}

			.stat-value {
				color: var(--text-dark);
			}

			.verified-badge {
				display: flex;
				align-items: center;
				gap: 0.25rem;
				color: #10b981;
			}

			.verified-badge svg {
				width: 1rem;
				height: 1rem;
			}

			.verified-badge span {
				font-size: 0.875rem;
			}

			.quick-actions {
				margin-top: 1.5rem;
				padding-top: 1.5rem;
				border-top: 1px solid var(--bg-light);
			}

			.action-btn {
				width: 100%;
				padding: 0.625rem 1rem;
				background: #fd742f;
				color: rgb(235, 233, 233);
				border-radius: 0.75rem;
				border: none;
				cursor: pointer;
				transition: all 0.2s;
				font-size: var(--fs-p);
			}

			.action-btn:hover {
				background: var(--primary);
			}

			/* Profile Form */
			.form-card {
				background: rgba(255, 255, 255, 0.05);
				backdrop-filter: blur(20px);
				border: 1px solid rgba(255, 255, 255, 0.1);
				border-radius: 1.5rem;
				padding: 2.2rem;
			}

			.form-title {
				color: var(--text-dark);
				margin-bottom: 1.5rem;
				font-size: 1.25rem;
			}

			.profile-form {
				display: flex;
				flex-direction: column;
				gap: 1.5rem;
			}

			.form-row {
				display: grid;
				grid-template-columns: repeat(3, 1fr);
				gap: 1.5rem;
			}

			/* Overriding .form-group if needed, but style.css has it. new one adds relative positioning */
			.form-group {
				position: relative;
			}

			.form-label {
				display: block;
				color: #374151;
				margin-bottom: 0.5rem;
				font-size: 0.875rem;
				font-weight: 500;
			}

			.input-wrapper {
				position: relative;
			}

			.form-input,
			.form-textarea {
				width: 100%;
				padding: 1.1rem 1rem;
				background: #f9fafb;
				border: 1px solid #e5e7eb;
				border-radius: 0.75rem;
				font-size: 0.875rem;
				transition: all 0.3s;
				font-family: inherit;
			}

			.form-input:focus,
			.form-textarea:focus {
				outline: none;
				border-color: var(--primary);
				box-shadow: 0 0 0 3px rgba(255, 87, 34, 0.1);
			}

			.form-textarea {
				resize: none;
			}

			.input-icon {
				position: absolute;
				right: 1rem;
				top: 50%;
				transform: translateY(-50%);
				color: #9ca3af;
				pointer-events: none;
			}

			.input-icon-textarea {
				top: 1rem;
				transform: none;
			}

			.security-section {
				padding-top: 1.5rem;
				border-top: 1px solid var(--bg-light);
			}

			.section-title {
				color: var(--text-dark);
				margin-bottom: 1rem;
				font-size: 1rem;
			}

			.form-actions {
				display: flex;
				gap: 1rem;
				padding-top: 1.5rem;
			}

			.btn-primary {
				flex: 1;
				padding: 1rem 1.5rem;
				background: linear-gradient(to right, var(--primary), var(--primary-light));
				color: white;
				border: none;
				border-radius: 0.75rem;
				cursor: pointer;
				transition: all 0.3s;
				display: flex;
				align-items: center;
				justify-content: center;
				gap: 0.5rem;
				font-size: 0.875rem;
				font-weight: 500;
			}

			.btn-primary:hover {
				box-shadow: 0 10px 15px -3px rgba(255, 87, 34, 0.3);
			}

			.btn-secondary {
				padding: 1rem 1.5rem;
				background: var(--bg-light);
				color: #374151;
				border: none;
				border-radius: 0.75rem;
				cursor: pointer;
				transition: all 0.2s;
				font-size: 0.875rem;
				font-weight: 500;
			}

			.btn-secondary:hover {
				background: #e5e7eb;
			}

			/* RENTAL HISTORY STYLES */

			/* Hero Section */
			.hero-section {
				padding: 3rem 0 2rem;
			}

			.hero-card {
				position: relative;
				background: linear-gradient(to right, var(--primary), var(--primary-light), #ff8f66);
				border-radius: 1.5rem;
				padding: 2rem;
				overflow: hidden;
			}

			.hero-decorative {
				position: absolute;
				border-radius: 50%;
				filter: blur(60px);
			}

			.hero-decorative-1 {
				top: 0;
				right: 0;
				width: 16rem;
				height: 16rem;
				background: rgba(255, 255, 255, 0.1);
			}

			.hero-decorative-2 {
				bottom: 0;
				left: 0;
				width: 12rem;
				height: 12rem;
				background: rgba(255, 179, 102, 0.2);
			}

			.hero-content {
				position: relative;
				z-index: 10;
				display: flex;
				align-items: center;
				justify-content: space-between;
			}

			.hero-title-wrapper {
				display: flex;
				align-items: center;
				gap: 0.5rem;
				margin-bottom: 0.75rem;
			}

			.sparkle-icon {
				color: #fde047;
			}

			.hero-subtitle {
				color: rgba(255, 255, 255, 0.9);
			}

			.hero-heading {
				font-size: 2rem;
				color: white;
				margin-bottom: 0.5rem;
				font-weight: 600;
			}

			.hero-description {
				color: rgba(255, 255, 255, 0.8);
				max-width: 42rem;
			}

			.hero-stats {
				background: rgba(255, 255, 255, 0.2);
				backdrop-filter: blur(12px);
				border-radius: 1rem;
				padding: 1.5rem;
				border: 1px solid rgba(255, 255, 255, 0.3);
				display: flex;
				align-items: center;
				gap: 1.5rem;
			}

			.stat-value-large {
				color: white;
				font-size: 1.25rem;
				font-weight: 600;
				margin-bottom: 0;
			}

			.stat-divider {
				width: 1px;
				height: 3rem;
				background: rgba(255, 255, 255, 0.3);
			}

			/* Filter Tabs */
			.filter-tabs {
				display: flex;
				gap: 0.75rem;
				margin-bottom: 2rem;
			}

			.filter-tab {
				padding: 0.75rem 1.5rem;
				background: white;
				border-radius: 9999px;
				border: 1px solid #e5e7eb;
				cursor: pointer;
				font-size: 0.875rem;
				transition: all 0.3s;
				color: #374151;
			}

			.filter-tab:hover {
				border-color: var(--primary);
				color: var(--primary);
			}

			.filter-tab-active {
				background: linear-gradient(to right, var(--primary), var(--primary-light));
				color: white;
				border-color: transparent;
				box-shadow: 0 10px 15px -3px rgba(255, 87, 34, 0.3);
			}

			.main-content {
				margin-top: 3rem;
			}

			/* Rental Grid */
			.rental-grid {
				display: grid;
				grid-template-columns: repeat(3, 1fr);
				gap: 1.5rem;
				padding: 1em 0 3em;
			}

			.rental-card {
				background: white;
				border-radius: 1rem;
				overflow: hidden;
				box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
				border: 1px solid var(--bg-light);
				transition: all 0.5s;
			}

			.rental-card:hover {
				border-color: rgba(255, 175, 151, 0.3);
				box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2);
				transform: translateY(-0.5rem);
			}

			.rental-image-wrapper {
				position: relative;
				height: 14rem;
				overflow: hidden;
				background: #1f2937;
			}

			.rental-image {
				width: 100%;
				height: 100%;
				object-fit: cover;
				transition: transform 0.7s;
			}

			.rental-card:hover .rental-image {
				transform: scale(1.1);
			}

			.rental-image-overlay {
				position: absolute;
				inset: 0;
				background: linear-gradient(to top, rgba(0, 0, 0, 0.6), transparent, transparent);
			}

			.rental-status {
				position: absolute;
				top: 1rem;
				right: 1rem;
				padding: 0.375rem 1rem;
				border-radius: 9999px;
				font-size: 0.75rem;
				backdrop-filter: blur(12px);
				border: 1px solid;
			}

			.status-ongoing {
				background: rgba(16, 185, 129, 0.9);
				color: white;
				border-color: rgba(52, 211, 153, 0.5);
				box-shadow: 0 10px 15px -3px rgba(16, 185, 129, 0.5);
			}

			.status-paid {
				background: rgba(255, 255, 255, 0.9);
				color: #374151;
				border-color: rgba(229, 231, 235, 0.5);
			}

			.status-completed {
				background: rgba(37, 99, 235, 0.9);
				color: white;
				border-color: rgba(96, 165, 250, 0.5);
			}

			.status-cancelled {
				background: rgba(239, 68, 68, 0.9);
				color: white;
				border-color: rgba(248, 113, 113, 0.5);
			}

			.rental-car-name {
				position: absolute;
				bottom: 1rem;
				left: 1rem;
				color: white;
			}

			.rental-car-title {
				font-size: 1.25rem;
				font-weight: 600;
				margin-bottom: 0.125rem;
			}

			.rental-car-model {
				color: rgba(255, 255, 255, 0.8);
				font-size: 0.875rem;
			}

			.rental-content {
				padding: 1.25rem;
			}

			.rental-price-section {
				display: flex;
				align-items: center;
				justify-content: space-between;
				margin-bottom: 1rem;
			}

			.rental-price-label {
				color: var(--text-gray);
				font-size: 0.75rem;
				margin-bottom: 0.25rem;
			}

			.rental-price {
				font-size: 1.5rem;
				font-weight: 600;
				background: linear-gradient(to right, var(--primary), var(--primary-light));
				-webkit-background-clip: text;
				-webkit-text-fill-color: transparent;
				background-clip: text;
			}

			.rental-icon-circle {
				width: 3rem;
				height: 3rem;
				border-radius: 50%;
				background: linear-gradient(to bottom right, #fed7aa, #fdba74);
				display: flex;
				align-items: center;
				justify-content: center;
			}

			.rental-icon-circle svg {
				width: 1.5rem;
				height: 1.5rem;
				color: var(--primary);
			}

			.rental-details {
				padding: 1rem 0;
				border-top: 1px solid var(--bg-light);
			}

			.rental-detail-row {
				display: flex;
				align-items: center;
				justify-content: space-between;
				font-size: 0.875rem;
				margin-bottom: 0.75rem;
			}

			.rental-detail-row:last-child {
				margin-bottom: 0;
			}

			.rental-detail-label {
				color: var(--text-gray);
			}

			.rental-detail-value {
				color: var(--text-dark);
			}

			.rental-location {
				display: flex;
				align-items: center;
				gap: 0.25rem;
			}

			.rental-location svg {
				width: 0.875rem;
				height: 0.875rem;
			}

			.rental-btn {
				width: 100%;
				margin-top: 0.5rem;
				padding: 0.75rem 1rem;
				background: linear-gradient(to right, #f9fafb, var(--bg-light));
				color: #374151;
				border-radius: 0.75rem;
				border: 1px solid #e5e7eb;
				cursor: pointer;
				transition: all 0.3s;
				display: flex;
				align-items: center;
				justify-content: center;
				gap: 0.5rem;
				font-size: 0.875rem;
			}

			.rental-btn:hover {
				background: linear-gradient(to right, var(--primary), var(--primary-light));
				color: white;
				border-color: transparent;
			}

			.rental-btn svg {
				width: 1rem;
				height: 1rem;
				transition: transform 0.3s;
			}

			.rental-btn:hover svg {
				transform: translateX(0.25rem);
			}

			.fleet-section {
				margin-top: -10px;
			}

			.admin-sidebar {}

			@media (max-width: 1024px) {
				.profile-layout {
					grid-template-columns: 1fr;
				}

				.profile-card {
					position: static;
				}

				.rental-grid {
					grid-template-columns: repeat(2, 1fr);
				}
			}

			@media (min-width: 768px) {}

			@media (max-width: 768px) {
				.form-row {
					grid-template-columns: 1fr;
				}

				.rental-grid {
					grid-template-columns: 1fr;
				}

				.hero-content {
					flex-direction: column;
					gap: 1.5rem;
				}

				.fleet-section {
					margin-top: -170px;
				}

				.footer-grid {}
			}

			@media (max-width: 640px) {
				.form-actions {
					flex-direction: column;
				}
			}

			@media (max-width: 540px) {
				.btn_hero {
					margin-top: 0.5em;
				}

				.nav__logo {
					font-size: var(--fs-h3);
				}

				.stats-grid {
					grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
					margin-bottom: 0;
					padding-inline: 1em;
				}

				.stat-item p {
					font-size: var(--fs-h6);
				}

				.stats {
					padding-top: 4em;
					padding-bottom: 0;
				}

				.icon-box {
					width: 3rem;
					height: 3rem;
				}

				.small-icon-box {
					width: 2.5rem;
					height: 2.5rem;
				}

				.icon-box .icon,
				.small-icon {
					width: 1.5rem;
					height: 1.5rem;
				}

				.card-content .icon {
					width: 2rem;
					height: 2rem;
				}

				.section {
					padding-block: 6em 6em;
				}

				.step-title {
					margin-bottom: 0.6rem;
				}

				.main {
					margin-top: 60px;
				}

				.header {
					padding: 1em 0;
				}

				.how-it-works-section {
					margin-top: -50px;
				}

				.fleet-section {
					margin-top: -250px;
				}

				.contact-section {
					margin-top: -190px;
					padding: 5rem 0.7rem;
				}

				.contact-section .container {
					max-width: none;
				}

				.submit-btn {
					padding: 0.6em;
				}

				.form-card {
					padding: 0.8rem;
				}

				.form-content .form-card {
					padding: 1.8rem;
				}

				.CTA-section {
					margin-top: -100px;
				}

				.Developper_credit {
					margin-bottom: 0;
					color: var(--primary);
				}

				.cta-content {
					padding: 3rem 1.5rem;
				}

				.cta-button {
					padding: 1em 3rem;
				}

				.filter-tab {
					padding: 0.75rem 1rem;
				}


			}

			@media (max-width: 350px) {
				.info-cards {
					max-width: 300px;
					margin: auto;
				}

				.filter-tab {
					padding: 0.75rem 0.8rem;
				}
			}
		</style>
	</head>

	<body>
		<header class="header" id="header">
			<div class="container flex-space">
				<a href="<?php echo $logoLink; ?>" class="nav__logo">LUXDRIVE</a>
				<input type="checkbox" class="input__toggel" id="inputToggel">
				<nav class="nav flex nav_ul nav__menu" style="align-items: center;">
					<?php if (isStaff()) { ?>
						<!-- Staff Navigation -->
						<ul class="nav__list flex">
							<?php if (isAdmin()) { ?>
								<li><a href="himihiba.php?page=admin_dashboard" class="nav__links">Dashboard</a></li>
								<li><a href="himihiba.php?page=admin_cars" class="nav__links">Manage Cars</a></li>
								<li><a href="himihiba.php?page=admin_staff" class="nav__links">Manage Staff</a></li>
								<li><a href="himihiba.php?page=admin_clients" class="nav__links">Manage Clients</a></li>
								<li><a href="himihiba.php?page=admin_reports" class="nav__links">Reports</a></li>
							<?php } elseif ($_SESSION['user_type'] === 'agent') { ?>
								<li><a href="himihiba.php?page=agent_dashboard" class="nav__links">Dashboard</a></li>
								<li><a href="himihiba.php?page=agent_rentals" class="nav__links">Rentals</a></li>

								<li><a href="himihiba.php?page=agent_clients" class="nav__links">Clients</a></li>
								<li><a href="himihiba.php?page=agent_payments" class="nav__links">Payments</a></li>
								<li><a href="himihiba.php?page=agent_reports" class="nav__links">Reports</a></li>
							<?php } elseif ($_SESSION['user_type'] === 'mechanic') { ?>
								<li><a href="himihiba.php?page=mechanic_dashboard" class="nav__links">Dashboard</a></li>
								<li><a href="himihiba.php?page=mechanic_cars" class="nav__links">Cars</a></li>
								<li><a href="himihiba.php?page=mechanic_maintenance" class="nav__links">Maintenance</a></li>
								<li><a href="himihiba.php?page=mechanic_reports" class="nav__links">Reports</a></li>
							<?php } elseif ($_SESSION['user_type'] === 'super_admin') { ?>
								<li><a href="himihiba.php?page=super_admin_dashboard" class="nav__links">Dashboard</a></li>
								<li><a href="himihiba.php?page=manage_agencies" class="nav__links">Agencies</a></li>
							<?php } ?>
						</ul>
						<!-- Staff User Dropdown -->
						<div class="flex-center gap-1 user-dropdown">
							<div class="profile flex-center"><span><?php echo $_SESSION['user_initials']; ?></span></div>
							<button class="dropdown-toggle">
								<img width="26" height="26" src="https://img.icons8.com/material-rounded/24/expand-arrow--v1.png" />
							</button>
							<div class="dropdown-menu">
								<a href="himihiba.php?page=logout" class="logout">Logout</a>
							</div>
						</div>
					<?php } else { ?>
						<!-- Client/Guest Navigation -->
						<ul class="nav__list flex">
							<li><a href="himihiba.php?page=home" class="nav__links">Home</a></li>
							<li><a href="himihiba.php?page=browse" class="nav__links">Browse cars</a></li>
							<li><a href="himihiba.php?page=home#contactus" class="nav__links">Contact us</a></li>
						</ul>
						<?php if (isLoggedIn()) { ?>
							<div class="flex-center gap-1 user-dropdown">
								<div class="profile flex-center"><span><?php echo $_SESSION['user_initials']; ?></span></div>
								<button class="dropdown-toggle">
									<img width="26" height="26" src="https://img.icons8.com/material-rounded/24/expand-arrow--v1.png" />
								</button>
								<div class="dropdown-menu">
									<a href="himihiba.php?page=profile">Profile</a>
									<a href="himihiba.php?page=rental_history">Rental History</a>
									<hr>
									<a href="himihiba.php?page=logout" class="logout">Logout</a>
								</div>
							</div>
						<?php } else { ?>
							<ul class="flex auth-list">
								<li><a href="himihiba.php?page=auth" class="auth_link btn log_btn">login</a></li>
								<li><a href="himihiba.php?page=auth" class="auth_link btn sign_btn">sign Up</a></li>
							</ul>
						<?php } ?>
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
						<div class="support-badge">
							<svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
								<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
							</svg>
							<span>24/7 Customer Support</span>
						</div>
						<p class="copyright">© 2025 LUXDRIVE. All rights reserved.</p>
						<p class="Developper_credit">Developed by Himihiba</p>
						<p>github : <a href="https://github.com/himihiba">https://github.com/himihiba</a></p>
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
			<form method="GET" action="himihiba.php" class="search-form flex-column">
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
					<div class="card-content"><svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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

	<section class="section fleet-section">
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
										<a href="himihiba.php?page=car-details&id=<?php echo $car['car_id']; ?>"><button class="view-more-button">View More</button></a>
									</div>
								</div>
							</div>
						</div>
					<?php endforeach; ?>
					<div class="slider-item">
						<div class="see-more-card">
							<a href="himihiba.php?page=browse" style="color:white;">
								<div class="see-more-icon"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
										<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path>
									</svg></div>
							</a>
							<h3 class="see-more-title">View All Cars</h3>
							<p class="see-more-text">Explore our complete collection of luxury vehicles</p>
							<a href="himihiba.php?page=browse"><button class="see-more-button"><span>See More</span><svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
						<a href="himihiba.php?page=browse"><button class="cta-button cta-button-primary">Browse Our Fleet</button></a>
						<a href="himihiba.php?page=home#contactus"><button class="cta-button cta-button-secondary">Contact Us</button></a>
					</div>
				</div>
			</div>
		</div>
	</section>

	<script>
		class CarSlider {
			constructor() {
				this.sliderTrack = document.getElementById('sliderTrack');
				this.prevBtn = document.getElementById('prevBtn');
				this.nextBtn = document.getElementById('nextBtn');
				this.currentIndex = 0;

				this.responsiveConfig = {
					mobile: {
						items: 1,
						breakpoint: 768
					},
					tablet: {
						items: 2,
						breakpoint: 1024
					},
					desktop: {
						items: 3,
						breakpoint: Infinity
					}
				};

				this.init();
			}

			init() {
				if (!this.sliderTrack) return;

				this.totalItems = this.sliderTrack.children.length;
				this.setupEventListeners();
				this.updateItemsPerPage();
				this.updateSlider();

				window.addEventListener('resize', () => {
					this.handleResize();
				});
			}

			setupEventListeners() {
				if (this.prevBtn) {
					this.prevBtn.addEventListener('click', () => this.prev());
				}

				if (this.nextBtn) {
					this.nextBtn.addEventListener('click', () => this.next());
				}

				document.addEventListener('keydown', (e) => {
					if (e.key === 'ArrowLeft') this.prev();
					if (e.key === 'ArrowRight') this.next();
				});
			}

			getItemsPerPage() {
				const width = window.innerWidth;

				for (const [device, config] of Object.entries(this.responsiveConfig)) {
					if (width < config.breakpoint) {
						return config.items;
					}
				}

				return this.responsiveConfig.desktop.items;
			}

			updateItemsPerPage() {
				this.itemsPerPage = this.getItemsPerPage();
				this.maxIndex = Math.max(0, this.totalItems - this.itemsPerPage);

				if (this.currentIndex > this.maxIndex) {
					this.currentIndex = Math.max(0, this.maxIndex);
				}
			}

			handleResize() {
				const oldItemsPerPage = this.itemsPerPage;
				this.updateItemsPerPage();

				if (oldItemsPerPage !== this.itemsPerPage) {
					this.currentIndex = Math.floor(this.currentIndex * (oldItemsPerPage / this.itemsPerPage));
					this.currentIndex = Math.min(this.currentIndex, this.maxIndex);
					this.updateSlider();
				}
			}

			updateSlider() {
				if (!this.sliderTrack) return;

				const itemWidth = 100 / this.itemsPerPage;
				const translateX = -(this.currentIndex * itemWidth);

				this.sliderTrack.style.transform = `translateX(${translateX}%)`;
				this.sliderTrack.style.transition = 'transform 0.3s ease';
				this.updateButtonStates();
			}

			updateButtonStates() {
				if (this.prevBtn) {
					this.prevBtn.disabled = this.currentIndex === 0;
					this.prevBtn.style.opacity = this.currentIndex === 0 ? '0.5' : '1';
				}

				if (this.nextBtn) {
					this.nextBtn.disabled = this.currentIndex >= this.maxIndex;
					this.nextBtn.style.opacity = this.currentIndex >= this.maxIndex ? '0.5' : '1';
				}
			}

			prev() {
				if (this.currentIndex > 0) {
					this.currentIndex--;
					this.updateSlider();
				}
			}

			next() {
				if (this.currentIndex < this.maxIndex) {
					this.currentIndex++;
					this.updateSlider();
				}
			}

			refresh(itemsCount) {
				this.totalItems = itemsCount || this.sliderTrack.children.length;
				this.updateItemsPerPage();
				this.updateSlider();
			}
		}

		document.addEventListener('DOMContentLoaded', () => {
			const carSlider = new CarSlider();

			carSlider.refresh();
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
			<form method="GET" action="himihiba.php" class="browse-search-form">
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
								<a href="himihiba.php?page=car-details&id=<?php echo $car['car_id']; ?>" style="text-decoration: none;"><button class="view-more-button">View More</button></a>
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
			echo '<div class="container" style="padding: 3rem; text-align: center;"><h2>Car not found</h2><a href="himihiba.php?page=browse">Back to Browse</a></div>';
			return;
		}
		global $bookingResult;
?>
	<div class="container" style="padding-block: 4rem;">
		<div class="back-nav"><a href="himihiba.php?page=browse" class="back-link"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
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
					<a href="himihiba.php?page=auth" class="book-now-btn" style="display: block; text-align: center; text-decoration: none;">Login to Book</a>
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
				<button class="booking-btn-primary" onclick="window.location.href='himihiba.php?page=rental_history'">View My Rentals</button>
			</div>
		</div>
	</div>
	<form id="booking-form" method="POST" action="himihiba.php?page=car-details&id=<?php echo $car['car_id']; ?>" style="display:none;">
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
								<form method="POST" action="himihiba.php?page=auth">
									<input type="hidden" name="action" value="login">
									<div class="form-group"><label>Email Address</label><input type="email" name="email" placeholder="john.doe@example.com" required></div>
									<div class="form-group"><label>Password</label><input type="password" name="password" placeholder="Enter your password" required></div>
									<button type="submit" class="submit-btn">Login</button>
								</form>

								<div class="sample-credentials" style="margin-top: 1.5rem; padding-top: 1.5rem;">
									<h3 style="font-size: 0.875rem; color: #6b7280; font-weight: 600; margin-bottom: 1rem; text-transform: uppercase; letter-spacing: 0.05em;">
										Demo Credentials
									</h3>

									<div style="background: transparent; padding: 1rem; border-radius: 0.5rem;">

										<!-- Super Admin -->
										<div style="margin-bottom: 0.75rem; display: flex; justify-content: space-between; align-items: center; font-size: 0.875rem;">
											<span style="font-weight: 600; color: #fff;">Super Admin</span>
											<code style="background: transparent; color: #fff; padding: 0.2rem 0.4rem; border-radius: 0.25rem; font-size: 0.75rem;">
												super@luxdrive.com / super123
											</code>
										</div>

										<!-- Agency 1 -->
										<div style="margin-bottom: 0.5rem; font-weight: 600; color: #6b7280;">Agency 1 (Paris)</div>

										<div style="margin-bottom: 0.75rem; display: flex; justify-content: space-between; align-items: center; font-size: 0.875rem;">
											<span style="font-weight: 600; color: #fff;">Admin</span>
											<code style="background: transparent; color: #fff; padding: 0.2rem 0.4rem; border-radius: 0.25rem; font-size: 0.75rem;">
												admin1@luxdrive.com / admin123
											</code>
										</div>

										<div style="margin-bottom: 0.75rem; display: flex; justify-content: space-between; align-items: center; font-size: 0.875rem;">
											<span style="font-weight: 600; color: #fff;">Agent</span>
											<code style="background: transparent; color: #fff; padding: 0.2rem 0.4rem; border-radius: 0.25rem; font-size: 0.75rem;">
												agent1@luxdrive.com / agent123
											</code>
										</div>

										<div style="margin-bottom: 0.75rem; display: flex; justify-content: space-between; align-items: center; font-size: 0.875rem;">
											<span style="font-weight: 600; color: #fff;">Mechanic</span>
											<code style="background: transparent; color: #fff; padding: 0.2rem 0.4rem; border-radius: 0.25rem; font-size: 0.75rem;">
												mech1@luxdrive.com / mech123
											</code>
										</div>

										<!-- Agency 2 -->
										<div style="margin-bottom: 0.5rem; font-weight: 600; color: #6b7280;">Agency 2 (Lyon)</div>

										<div style="margin-bottom: 0.75rem; display: flex; justify-content: space-between; align-items: center; font-size: 0.875rem;">
											<span style="font-weight: 600; color: #fff;">Admin</span>
											<code style="background: transparent; color: #fff; padding: 0.2rem 0.4rem; border-radius: 0.25rem; font-size: 0.75rem;">
												admin2@luxdrive.com / admin123
											</code>
										</div>

										<div style="margin-bottom: 0.75rem; display: flex; justify-content: space-between; align-items: center; font-size: 0.875rm;">
											<span style="font-weight: 600; color: #fff;">Agent</span>
											<code style="background: transparent; color: #fff; padding: 0.2rem 0.4rem; border-radius: 0.25rem; font-size: 0.75rem;">
												agent2@luxdrive.com / agent123
											</code>
										</div>

										<div style="margin-bottom: 0.75rem; display: flex; justify-content: space-between; align-items: center; font-size: 0.875rem;">
											<span style="font-weight: 600; color: #fff;">Mechanic</span>
											<code style="background: transparent; color: #fff; padding: 0.2rem 0.4rem; border-radius: 0.25rem; font-size: 0.75rem;">
												mech2@luxdrive.com / mech123
											</code>
										</div>

										<!-- Clients -->
										<div style="margin-bottom: 0.5rem; font-weight: 600; color: #6b7280;">Clients</div>

										<div style="margin-bottom: 0.75rem; display: flex; justify-content: space-between; align-items: center; font-size: 0.875rem;">
											<span style="font-weight: 600; color: #fff;">Client 1</span>
											<code style="background: transparent; color: #fff; padding: 0.2rem 0.4rem; border-radius: 0.25rem; font-size: 0.75rem;">
												client@email.com / client123
											</code>
										</div>

										<div style="display: flex; justify-content: space-between; align-items: center; font-size: 0.875rem;">
											<span style="font-weight: 600; color: #fff;">Client 2</span>
											<code style="background: transparent; color: #fff; padding: 0.2rem 0.4rem; border-radius: 0.25rem; font-size: 0.75rem;">
												bob.durand@email.com / client456
											</code>
										</div>

									</div>
								</div>

							</div>
						</div>
						<div id="signup-form" class="form-content">
							<div class="form-card">
								<h2>Create account</h2>
								<p class="subtitle">Join LUXDRIVE and start your luxury journey</p>
								<form method="POST" action="himihiba.php?page=auth" class="signupform">
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
			<a href="himihiba.php?page=profile" class="sidebar-link <?php echo $activePage === 'profile' ? 'active' : ''; ?>">
				<svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
					<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
				</svg>
				My Account
			</a>
			<a href="himihiba.php?page=rental_history" class="sidebar-link <?php echo $activePage === 'rentals' ? 'active' : ''; ?>">
				<svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
					<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path>
				</svg>
				My Rentals
			</a>
			<a href="himihiba.php?page=logout" class="sidebar-link" style="color: #ef4444;">
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
			header('Location: himihiba.php?page=auth');
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
							<a href="himihiba.php?page=rental_history"><button class="action-btn">View Rental History</button></a>
						</div>
					</div>
				</aside>


				<main class="profile-main">
					<div class="form-card">
						<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
							<h3 class="form-title" style="margin-bottom: 0;">Personal Information</h3>
							<button type="button" id="editProfileBtn" class="action-btn" style="width: auto;" onclick="toggleEditMode()">Edit Profile</button>
						</div>

						<form class="profile-form" method="POST" action="himihiba.php?page=profile" id="profileForm">
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
			header('Location: himihiba.php?page=auth');
			exit;
		}

		global $cancelResult;
		updateRentalStatuses();

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

			<?php if ($cancelResult && isset($cancelResult['success'])): ?>
				<div style="background: #dcfce7; color: #16a34a; padding: 1rem; border-radius: 8px; margin-bottom: 2rem; border: 1px solid #bbf7d0;"><?php echo htmlspecialchars($cancelResult['success']); ?></div>
			<?php endif; ?>
			<?php if ($cancelResult && isset($cancelResult['error'])): ?>
				<div style="background: #fee2e2; color: #dc2626; padding: 1rem; border-radius: 8px; margin-bottom: 2rem; border: 1px solid #fecaca;"><?php echo htmlspecialchars($cancelResult['error']); ?></div>
			<?php endif; ?>

			<?php if (empty($rentals)): ?>
				<div style="text-align: center; padding: 4rem; background: white; border-radius: 1.5rem; border: 1px solid #f3f4f6;">
					<h3 style="margin-bottom: 0.5rem; font-size:1.5rem;">No rentals yet</h3>
					<p style="color: #6b7280; margin-bottom: 2rem;">Start your luxury journey by booking a car!</p>
					<a href="himihiba.php?page=browse"><button class="btn-primary" style="max-width:200px; margin:auto;">Browse Cars</button></a>
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

								<?php if ($rental['status'] === 'ongoing'): ?>
									<form method="POST" action="himihiba.php?page=rental_history" style="margin-top: 1rem;" onsubmit="return confirm('Are you sure you want to cancel this booking?');">
										<input type="hidden" name="action" value="cancel_rental">
										<input type="hidden" name="rental_id" value="<?php echo $rental['rental_id']; ?>">
										<button type="submit" class="rental-btn" style="background: #fee2e2; color: #dc2626; border-color: #fca5a5;">
											<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
												<circle cx="12" cy="12" r="10"></circle>
												<line x1="15" y1="9" x2="9" y2="15"></line>
												<line x1="9" y1="9" x2="15" y2="15"></line>
											</svg>
											Cancel Booking
										</button>
									</form>
								<?php endif; ?>
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

	function isAdmin()
	{
		return isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin';
	}

	function isAgent()
	{
		return isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'agent';
	}

	function isMechanic()
	{
		return isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'mechanic';
	}

	function renderSuperAdminDashboard()
	{
		if (!isSuperAdmin()) {
			header('Location: himihiba.php?page=auth');
			exit;
		}

		global $pdo;

		// Filter Logic
		$selectedAgencyId = isset($_GET['agency_id']) && $_GET['agency_id'] !== 'all' ? intval($_GET['agency_id']) : null;
		$agencyFilter = $selectedAgencyId ? "WHERE agency_id = $selectedAgencyId" : "";
		$agencyFilterAnd = $selectedAgencyId ? "AND agency_id = $selectedAgencyId" : "";
		$rentalFilter = $selectedAgencyId ? "WHERE agency_id = $selectedAgencyId" : "";
		$rentalFilterAnd = $selectedAgencyId ? "AND agency_id = $selectedAgencyId" : "";

		// Fetch stats
		$totalCars = $pdo->query("SELECT COUNT(*) FROM cars $agencyFilter")->fetchColumn();
		$totalClients = $pdo->query("SELECT COUNT(*) FROM clients")->fetchColumn();
		$totalStaff = $pdo->query("SELECT COUNT(*) FROM staff " . ($selectedAgencyId ? "WHERE agency_id = $selectedAgencyId" : "WHERE role != 'super_admin'"))->fetchColumn();
		$activeRentals = $pdo->query("SELECT COUNT(*) FROM rentals WHERE status='ongoing' $agencyFilterAnd")->fetchColumn();
		$totalRevenue = $pdo->query("SELECT SUM(amount) FROM payments p JOIN rentals r ON p.rental_id = r.rental_id WHERE p.status='paid' $rentalFilterAnd")->fetchColumn() ?: 0;

		$agencies = $pdo->query("SELECT * FROM agencies")->fetchAll(PDO::FETCH_ASSOC);
?>
	<div class="admin-layout" style="display: flex; min-height: 100vh; background: #f3f4f6;">
		<!-- Sidebar -->
		<aside class="admin-sidebar" style="width: 260px; background: #111827; color: white; position: fixed; height: 100%; overflow-y: auto;">
			<div style="padding: 1.5rem; border-bottom: 1px solid #374151;">
				<h2 style="font-size: 1.5rem; color: white;">LUXDRIVE <span style="font-size: 0.75rem; color: #9ca3af; display: block; text-transform: uppercase; letter-spacing: 1px; margin-top: 5px;">Super Admin</span></h2>
			</div>
			<nav style="padding: 1rem;">
				<ul style="list-style: none;">
					<li><a href="himihiba.php?page=super_admin_dashboard" class="admin-nav-link active" style="display: block; padding: 0.75rem 1rem; color: white; text-decoration: none; border-radius: 0.5rem; margin-bottom: 0.5rem; background: #374151;">Dashboard</a></li>
					<li><a href="himihiba.php?page=manage_agencies" style="display: block; padding: 0.75rem 1rem; color: #d1d5db; text-decoration: none; border-radius: 0.5rem; margin-bottom: 0.5rem;">Manage Agencies</a></li>
					<li style="margin-top: 2rem; border-top: 1px solid #374151; padding-top: 1rem;"><a href="himihiba.php?page=logout" style="display: block; padding: 0.75rem 1rem; color: #ef4444; text-decoration: none; border-radius: 0.5rem;">Logout</a></li>
				</ul>
			</nav>
		</aside>

		<!-- Main Content -->
		<main class="admin-main" style="flex: 1; margin-left: 260px; padding: 2rem;">
			<header style="margin-bottom: 2rem; display: flex; justify-content: space-between; align-items: center;">
				<div>
					<h1 style="font-size: 1.875rem; font-weight: 600; color: #111827;">Global Overview</h1>
					<p style="color: #6b7280;">Welcome back, Super Admin</p>
				</div>
				<form action="" method="GET" style="display: flex; align-items: center; gap: 1rem;">
					<input type="hidden" name="page" value="super_admin_dashboard">
					<select name="agency_id" onchange="this.form.submit()" style="padding: 0.5rem; border-radius: 0.375rem; border: 1px solid #d1d5db;">
						<option value="all">All Agencies</option>
						<?php foreach ($agencies as $agency): ?>
							<option value="<?php echo $agency['agency_id']; ?>" <?php echo $selectedAgencyId == $agency['agency_id'] ? 'selected' : ''; ?>>
								<?php echo htmlspecialchars($agency['name']); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</form>
			</header>

			<!-- Stats Grid -->
			<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.5rem; margin-bottom: 3rem;">
				<div style="background: white; padding: 1.5rem; border-radius: 0.75rem; border: 1px solid #e5e7eb; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">
					<p style="color: #6b7280; font-size: 0.875rem; margin-bottom: 0.5rem;">Global Revenue</p>
					<p style="font-size: 1.5rem; font-weight: 600; color: #111827;">$<?php echo number_format($totalRevenue, 2); ?></p>
				</div>
				<div style="background: white; padding: 1.5rem; border-radius: 0.75rem; border: 1px solid #e5e7eb; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">
					<p style="color: #6b7280; font-size: 0.875rem; margin-bottom: 0.5rem;">Active Rentals</p>
					<p style="font-size: 1.5rem; font-weight: 600; color: #111827;"><?php echo $activeRentals; ?></p>
				</div>
				<div style="background: white; padding: 1.5rem; border-radius: 0.75rem; border: 1px solid #e5e7eb; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">
					<p style="color: #6b7280; font-size: 0.875rem; margin-bottom: 0.5rem;">Total Fleet</p>
					<p style="font-size: 1.5rem; font-weight: 600; color: #111827;"><?php echo $totalCars; ?> Cars</p>
				</div>
				<div style="background: white; padding: 1.5rem; border-radius: 0.75rem; border: 1px solid #e5e7eb; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">
					<p style="color: #6b7280; font-size: 0.875rem; margin-bottom: 0.5rem;">Total Clients</p>
					<p style="font-size: 1.5rem; font-weight: 600; color: #111827;"><?php echo $totalClients; ?></p>
				</div>
			</div>
		</main>
	</div>
<?php
	}

	function renderManageAgencies()
	{
		if (!isSuperAdmin()) {
			header('Location: himihiba.php?page=auth');
			exit;
		}

		global $pdo;
		$message = '';
		$error = '';

		// Handle Actions
		if ($_SERVER['REQUEST_METHOD'] === 'POST') {
			$action = $_POST['action'] ?? '';

			if ($action === 'add') {
				$name = sanitize($_POST['name'] ?? '');
				$city = sanitize($_POST['city'] ?? '');
				$address = sanitize($_POST['address'] ?? '');
				$email = sanitize($_POST['contact_email'] ?? '');

				// Admin Details
				$adminFirstName = sanitize($_POST['admin_first_name'] ?? '');
				$adminLastName = sanitize($_POST['admin_last_name'] ?? '');
				$adminEmail = sanitize($_POST['admin_email'] ?? '');
				$adminPassword = $_POST['admin_password'] ?? '';

				if ($name && $city && $address && $email && $adminFirstName && $adminLastName && $adminEmail && $adminPassword) {
					try {
						$pdo->beginTransaction();

						// 1. Create Agency
						$stmt = $pdo->prepare("INSERT INTO agencies (name, city, address, contact_email) VALUES (?, ?, ?, ?)");
						$stmt->execute([$name, $city, $address, $email]);
						$newAgencyId = $pdo->lastInsertId();

						// 2. Create Admin Staff for this Agency
						$cancelHash = password_hash($adminPassword, PASSWORD_DEFAULT);
						$stmt = $pdo->prepare("INSERT INTO staff (agency_id, first_name, last_name, email, role, password_hash) VALUES (?, ?, ?, ?, 'admin', ?)");
						$stmt->execute([$newAgencyId, $adminFirstName, $adminLastName, $adminEmail, $cancelHash]);

						$pdo->commit();
						$message = "Agency and Admin account created successfully.";
					} catch (Exception $e) {
						$pdo->rollBack();
						$error = "Failed to create agency: " . $e->getMessage();
					}
				} else {
					$error = "All fields are required, including Admin details.";
				}
			} elseif ($action === 'edit') {
				$id = intval($_POST['agency_id'] ?? 0);
				$name = sanitize($_POST['name'] ?? '');
				$city = sanitize($_POST['city'] ?? '');
				$address = sanitize($_POST['address'] ?? '');
				$email = sanitize($_POST['contact_email'] ?? '');

				if ($id && $name && $city && $address && $email) {
					$stmt = $pdo->prepare("UPDATE agencies SET name = ?, city = ?, address = ?, contact_email = ? WHERE agency_id = ?");
					if ($stmt->execute([$name, $city, $address, $email, $id])) {
						$message = "Agency updated successfully.";
					} else {
						$error = "Failed to update agency.";
					}
				} else {
					$error = "All fields are required.";
				}
			} elseif ($action === 'delete') {
				$id = intval($_POST['agency_id'] ?? 0);
				if ($id) {
					$stmt = $pdo->prepare("DELETE FROM agencies WHERE agency_id = ?");
					if ($stmt->execute([$id])) {
						$message = "Agency deleted successfully.";
					} else {
						$error = "Failed to delete agency.";
					}
				}
			}
		}

		$agencies = $pdo->query("SELECT * FROM agencies ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
?>
	<div class="admin-layout" style="display: flex; min-height: 100vh; background: #f3f4f6;">
		<!-- Sidebar -->
		<aside class="admin-sidebar" style="width: 260px; background: #111827; color: white; position: fixed; height: 100%; overflow-y: auto;">
			<div style="padding: 1.5rem; border-bottom: 1px solid #374151;">
				<h2 style="font-size: 1.5rem; color: white;">LUXDRIVE <span style="font-size: 0.75rem; color: #9ca3af; display: block; text-transform: uppercase; letter-spacing: 1px; margin-top: 5px;">Super Admin</span></h2>
			</div>
			<nav style="padding: 1rem;">
				<ul style="list-style: none;">
					<li><a href="himihiba.php?page=super_admin_dashboard" class="admin-nav-link" style="display: block; padding: 0.75rem 1rem; color: #d1d5db; text-decoration: none; border-radius: 0.5rem; margin-bottom: 0.5rem;">Dashboard</a></li>
					<li><a href="himihiba.php?page=manage_agencies" class="admin-nav-link active" style="display: block; padding: 0.75rem 1rem; color: white; text-decoration: none; border-radius: 0.5rem; margin-bottom: 0.5rem; background: #374151;">Manage Agencies</a></li>
					<li style="margin-top: 2rem; border-top: 1px solid #374151; padding-top: 1rem;"><a href="himihiba.php?page=logout" style="display: block; padding: 0.75rem 1rem; color: #ef4444; text-decoration: none; border-radius: 0.5rem;">Logout</a></li>
				</ul>
			</nav>
		</aside>

		<!-- Main Content -->
		<main class="admin-main" style="flex: 1; margin-left: 260px; padding: 2rem;">
			<header style="margin-bottom: 2rem; display: flex; justify-content: space-between; align-items: center;">
				<div>
					<h1 style="font-size: 1.875rem; font-weight: 600; color: #111827;">Manage Agencies</h1>
					<p style="color: #6b7280;">Add, edit, or remove agencies.</p>
				</div>
				<button onclick="document.getElementById('addAgencyModal').style.display='flex'" style="background: #2563eb; color: white; padding: 0.75rem 1.5rem; border-radius: 0.375rem; border: none; font-weight: 500; cursor: pointer;">
					+ Add New Agency
				</button>
			</header>

			<?php if ($message): ?>
				<div style="background: #d1fae5; color: #065f46; padding: 1rem; border-radius: 0.5rem; margin-bottom: 1.5rem;">
					<?php echo htmlspecialchars($message); ?>
				</div>
			<?php endif; ?>
			<?php if ($error): ?>
				<div style="background: #fee2e2; color: #991b1b; padding: 1rem; border-radius: 0.5rem; margin-bottom: 1.5rem;">
					<?php echo htmlspecialchars($error); ?>
				</div>
			<?php endif; ?>

			<!-- Agencies Table -->
			<div style="background: white; border-radius: 0.75rem; border: 1px solid #e5e7eb; overflow: hidden;">
				<table style="width: 100%; border-collapse: collapse; text-align: left;">
					<thead style="background: #f9fafb; border-bottom: 1px solid #e5e7eb;">
						<tr>
							<th style="padding: 1rem; font-weight: 600; color: #4b5563;">Name</th>
							<th style="padding: 1rem; font-weight: 600; color: #4b5563;">City</th>
							<th style="padding: 1rem; font-weight: 600; color: #4b5563;">Address</th>
							<th style="padding: 1rem; font-weight: 600; color: #4b5563;">Contact Email</th>
							<th style="padding: 1rem; font-weight: 600; color: #4b5563;">Actions</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($agencies as $agency): ?>
							<tr style="border-bottom: 1px solid #e5e7eb;">
								<td style="padding: 1rem; font-weight: 500; color: #111827;"><?php echo htmlspecialchars($agency['name']); ?></td>
								<td style="padding: 1rem; color: #6b7280;"><?php echo htmlspecialchars($agency['city']); ?></td>
								<td style="padding: 1rem; color: #6b7280;"><?php echo htmlspecialchars($agency['address']); ?></td>
								<td style="padding: 1rem; color: #6b7280;"><?php echo htmlspecialchars($agency['contact_email']); ?></td>
								<td style="padding: 1rem;">
									<div style="display: flex; gap: 0.5rem;">
										<button onclick='openEditModal(<?php echo json_encode($agency); ?>)' style="padding: 0.25rem 0.75rem; background: #e0f2fe; color: #0284c7; border: none; border-radius: 0.25rem; cursor: pointer; font-size: 0.875rem;">Edit</button>
										<form method="POST" onsubmit="return confirm('Are you sure? This will delete all cars and rentals associated with this agency!');" style="display: inline;">
											<input type="hidden" name="action" value="delete">
											<input type="hidden" name="agency_id" value="<?php echo $agency['agency_id']; ?>">
											<button type="submit" style="padding: 0.25rem 0.75rem; background: #fee2e2; color: #dc2626; border: none; border-radius: 0.25rem; cursor: pointer; font-size: 0.875rem;">Delete</button>
										</form>
									</div>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</main>
	</div>

	<!-- Add Modal -->
	<div id="addAgencyModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); align-items: center; justify-content: center; z-index: 1000;">
		<div style="background: white; padding: 2rem; border-radius: 1rem; width: 100%; max-width: 500px;">
			<h2 style="font-size: 1.5rem; font-weight: 600; margin-bottom: 1.5rem;">Add New Agency</h2>
			<form method="POST">
				<input type="hidden" name="action" value="add">
				<div style="margin-bottom: 1rem;">
					<label style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 0.25rem;">Agency Name</label>
					<input type="text" name="name" required style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem;">
				</div>
				<div style="margin-bottom: 1rem;">
					<label style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 0.25rem;">City</label>
					<input type="text" name="city" required style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem;">
				</div>
				<div style="margin-bottom: 1rem;">
					<label style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 0.25rem;">Address</label>
					<input type="text" name="address" required style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem;">
				</div>
				<div style="margin-bottom: 1.5rem;">
					<label style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 0.25rem;">Contact Email</label>
					<input type="email" name="contact_email" required style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem;">
				</div>

				<div style="border-top: 1px solid #e5e7eb; margin: 1.5rem 0; padding-top: 1.5rem;">
					<h3 style="font-size: 1.125rem; font-weight: 600; margin-bottom: 1rem; color: #1f2937;">Agency Admin Account</h3>
					<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
						<div style="margin-bottom: 1rem;">
							<label style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 0.25rem;">Admin First Name</label>
							<input type="text" name="admin_first_name" required style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem;">
						</div>
						<div style="margin-bottom: 1rem;">
							<label style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 0.25rem;">Admin Last Name</label>
							<input type="text" name="admin_last_name" required style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem;">
						</div>
					</div>
					<div style="margin-bottom: 1rem;">
						<label style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 0.25rem;">Admin Email (Login)</label>
						<input type="email" name="admin_email" required style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem;">
					</div>
					<div style="margin-bottom: 1rem;">
						<label style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 0.25rem;">Admin Password</label>
						<input type="password" name="admin_password" required minlength="6" style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem;">
					</div>
				</div>
				<div style="display: flex; gap: 1rem; justify-content: flex-end;">
					<button type="button" onclick="document.getElementById('addAgencyModal').style.display='none'" style="padding: 0.5rem 1rem; border: 1px solid #d1d5db; background: white; border-radius: 0.375rem; cursor: pointer;">Cancel</button>
					<button type="submit" style="padding: 0.5rem 1rem; background: #2563eb; color: white; border: none; border-radius: 0.375rem; cursor: pointer;">Add Agency</button>
				</div>
			</form>
		</div>
	</div>

	<!-- Edit Modal -->
	<div id="editAgencyModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); align-items: center; justify-content: center; z-index: 1000;">
		<div style="background: white; padding: 2rem; border-radius: 1rem; width: 100%; max-width: 500px;">
			<h2 style="font-size: 1.5rem; font-weight: 600; margin-bottom: 1.5rem;">Edit Agency</h2>
			<form method="POST">
				<input type="hidden" name="action" value="edit">
				<input type="hidden" name="agency_id" id="edit_agency_id">
				<div style="margin-bottom: 1rem;">
					<label style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 0.25rem;">Agency Name</label>
					<input type="text" name="name" id="edit_name" required style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem;">
				</div>
				<div style="margin-bottom: 1rem;">
					<label style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 0.25rem;">City</label>
					<input type="text" name="city" id="edit_city" required style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem;">
				</div>
				<div style="margin-bottom: 1rem;">
					<label style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 0.25rem;">Address</label>
					<input type="text" name="address" id="edit_address" required style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem;">
				</div>
				<div style="margin-bottom: 1.5rem;">
					<label style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 0.25rem;">Contact Email</label>
					<input type="email" name="contact_email" id="edit_contact_email" required style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem;">
				</div>
				<div style="display: flex; gap: 1rem; justify-content: flex-end;">
					<button type="button" onclick="document.getElementById('editAgencyModal').style.display='none'" style="padding: 0.5rem 1rem; border: 1px solid #d1d5db; background: white; border-radius: 0.375rem; cursor: pointer;">Cancel</button>
					<button type="submit" style="padding: 0.5rem 1rem; background: #2563eb; color: white; border: none; border-radius: 0.375rem; cursor: pointer;">Save Changes</button>
				</div>
			</form>
		</div>
	</div>

	<script>
		function openEditModal(agency) {
			document.getElementById('edit_agency_id').value = agency.agency_id;
			document.getElementById('edit_name').value = agency.name;
			document.getElementById('edit_city').value = agency.city;
			document.getElementById('edit_address').value = agency.address;
			document.getElementById('edit_contact_email').value = agency.contact_email;
			document.getElementById('editAgencyModal').style.display = 'flex';
		}
	</script>
<?php
	}

	function renderAdminSidebar($activePage = 'dashboard')
	{
?>
	<aside class="admin-sidebar" style="width: 250px; background: white; border-right: 1px solid #e5e7eb; position: fixed; height: 100%; overflow-y: auto;">
		<div style="padding: 1.5rem; border-bottom: 1px solid #e5e7eb;">
			<h2 style="font-size: 1.5rem; color: #1f2937;">LUXDRIVE <span style="font-size: 0.8rem; color: #6b7280; display: block; font-family: 'Inter', sans-serif; margin-top: 0.25rem;">Admin Panel</span></h2>
		</div>
		<nav style="padding: 1rem;">
			<ul style="list-style: none;">
				<li><a href="?page=admin_dashboard" class="admin-nav-link <?php echo $activePage === 'dashboard' ? 'active' : ''; ?>" style="display: block; padding: 0.75rem 1rem; color: <?php echo $activePage === 'dashboard' ? '#1f2937' : '#4b5563'; ?>; text-decoration: none; border-radius: 0.5rem; margin-bottom: 0.5rem; <?php echo $activePage === 'dashboard' ? 'background: #f3f4f6; font-weight: 500;' : ''; ?>">Dashboard</a></li>
				<li><a href="?page=admin_rentals" class="admin-nav-link <?php echo $activePage === 'rentals' ? 'active' : ''; ?>" style="display: block; padding: 0.75rem 1rem; color: <?php echo $activePage === 'rentals' ? '#1f2937' : '#4b5563'; ?>; text-decoration: none; border-radius: 0.5rem; margin-bottom: 0.5rem; <?php echo $activePage === 'rentals' ? 'background: #f3f4f6; font-weight: 500;' : ''; ?>">Rentals</a></li>
				<li><a href="?page=admin_cars" class="admin-nav-link <?php echo $activePage === 'cars' ? 'active' : ''; ?>" style="display: block; padding: 0.75rem 1rem; color: <?php echo $activePage === 'cars' ? '#1f2937' : '#4b5563'; ?>; text-decoration: none; border-radius: 0.5rem; margin-bottom: 0.5rem; <?php echo $activePage === 'cars' ? 'background: #f3f4f6; font-weight: 500;' : ''; ?>">Fleet Management</a></li>
				<li><a href="?page=admin_maintenance" class="admin-nav-link <?php echo $activePage === 'maintenance' ? 'active' : ''; ?>" style="display: block; padding: 0.75rem 1rem; color: <?php echo $activePage === 'maintenance' ? '#1f2937' : '#4b5563'; ?>; text-decoration: none; border-radius: 0.5rem; margin-bottom: 0.5rem; <?php echo $activePage === 'maintenance' ? 'background: #f3f4f6; font-weight: 500;' : ''; ?>">Maintenance</a></li>
				<li><a href="?page=admin_staff" class="admin-nav-link <?php echo $activePage === 'staff' ? 'active' : ''; ?>" style="display: block; padding: 0.75rem 1rem; color: <?php echo $activePage === 'staff' ? '#1f2937' : '#4b5563'; ?>; text-decoration: none; border-radius: 0.5rem; margin-bottom: 0.5rem; <?php echo $activePage === 'staff' ? 'background: #f3f4f6; font-weight: 500;' : ''; ?>">Staff Management</a></li>
				<li><a href="?page=admin_clients" class="admin-nav-link <?php echo $activePage === 'clients' ? 'active' : ''; ?>" style="display: block; padding: 0.75rem 1rem; color: <?php echo $activePage === 'clients' ? '#1f2937' : '#4b5563'; ?>; text-decoration: none; border-radius: 0.5rem; margin-bottom: 0.5rem; <?php echo $activePage === 'clients' ? 'background: #f3f4f6; font-weight: 500;' : ''; ?>">Clients</a></li>
				<li><a href="?page=admin_reports" class="admin-nav-link <?php echo $activePage === 'reports' ? 'active' : ''; ?>" style="display: block; padding: 0.75rem 1rem; color: <?php echo $activePage === 'reports' ? '#1f2937' : '#4b5563'; ?>; text-decoration: none; border-radius: 0.5rem; margin-bottom: 0.5rem; <?php echo $activePage === 'reports' ? 'background: #f3f4f6; font-weight: 500;' : ''; ?>">Reports</a></li>
				<li style="margin-top: 2rem; border-top: 1px solid #e5e7eb; padding-top: 1rem;"><a href="?page=logout" style="display: block; padding: 0.75rem 1rem; color: #ef4444; text-decoration: none; border-radius: 0.5rem;">Logout</a></li>
			</ul>
		</nav>
	</aside>
<?php
	}

	function renderAdminDashboard()
	{
		if (!isAdmin()) {
			header('Location: himihiba.php?page=auth');
			exit;
		}

		global $pdo;
		$agencyId = $_SESSION['user_agency_id'];

		// Fetch stats filtered by agency
		$totalCars = $pdo->prepare("SELECT COUNT(*) FROM cars WHERE agency_id = ?");
		$totalCars->execute([$agencyId]);
		$totalCars = $totalCars->fetchColumn();

		$totalStaff = $pdo->prepare("SELECT COUNT(*) FROM staff WHERE agency_id = ?");
		$totalStaff->execute([$agencyId]);
		$totalStaff = $totalStaff->fetchColumn();

		$activeRentals = $pdo->prepare("SELECT COUNT(*) FROM rentals WHERE status='ongoing' AND agency_id = ?");
		$activeRentals->execute([$agencyId]);
		$activeRentals = $activeRentals->fetchColumn();

		// Revenue for this agency
		$totalRevenue = $pdo->prepare("
			SELECT SUM(p.amount) 
			FROM payments p 
			JOIN rentals r ON p.rental_id = r.rental_id 
			WHERE p.status='paid' AND r.agency_id = ?
		");
		$totalRevenue->execute([$agencyId]);
		$totalRevenue = $totalRevenue->fetchColumn() ?: 0;
		$rentalsThisMonth = $pdo->prepare("SELECT COUNT(*) FROM rentals WHERE agency_id = ? AND MONTH(start_date) = MONTH(CURRENT_DATE())");
		$rentalsThisMonth->execute([$agencyId]);
		$rentalsThisMonth = $rentalsThisMonth->fetchColumn();
?>
	<div class="admin-layout" style="display: flex; min-height: 100vh; background: #f3f4f6;">
		<?php renderAdminSidebar('dashboard'); ?>

		<!-- Main Content -->
		<main class="admin-main" style="flex: 1; margin-left: 250px; padding: 2rem;">
			<header style="margin-bottom: 2rem;">
				<h1 style="font-size: 1.875rem; font-weight: 600; color: #111827;">Agency Overview</h1>
				<p style="color: #6b7280;">Welcome back, <?php echo htmlspecialchars($_SESSION['user_name']); ?></p>
			</header>

			<!-- Stats Grid -->
			<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.5rem; margin-bottom: 3rem;">
				<div style="background: white; padding: 1.5rem; border-radius: 0.75rem; border: 1px solid #e5e7eb; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">
					<p style="color: #6b7280; font-size: 0.875rem; margin-bottom: 0.5rem;">Agency Revenue</p>
					<p style="font-size: 1.5rem; font-weight: 600; color: #111827;">$<?php echo number_format($totalRevenue, 2); ?></p>
				</div>
				<div style="background: white; padding: 1.5rem; border-radius: 0.75rem; border: 1px solid #e5e7eb; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">
					<p style="color: #6b7280; font-size: 0.875rem; margin-bottom: 0.5rem;">Active Rentals</p>
					<p style="font-size: 1.5rem; font-weight: 600; color: #111827;"><?php echo $activeRentals; ?></p>
				</div>
				<div style="background: white; padding: 1.5rem; border-radius: 0.75rem; border: 1px solid #e5e7eb; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">
					<p style="color: #6b7280; font-size: 0.875rem; margin-bottom: 0.5rem;">Agency Fleet</p>
					<p style="font-size: 1.5rem; font-weight: 600; color: #111827;"><?php echo $totalCars; ?> Cars</p>
				</div>
				<div style="background: white; padding: 1.5rem; border-radius: 0.75rem; border: 1px solid #e5e7eb; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">
					<p style="color: #6b7280; font-size: 0.875rem; margin-bottom: 0.5rem;">Rentals This Month</p>
					<p style="font-size: 1.5rem; font-weight: 600; color: #111827;"><?php echo $rentalsThisMonth; ?></p>
				</div>
			</div>
			<div style="background: white; border-radius: 0.75rem; border: 1px solid #e5e7eb; padding: 1.5rem;">
				<h3 style="font-size: 1.125rem; font-weight: 600; margin-bottom: 1rem;">Agency Status</h3>
				<p>Your agency operations are running smoothly.</p>
			</div>
		</main>
	</div>
<?php
	}

	function handleAddCar()
	{
		global $pdo;
		if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $_POST['action'] !== 'add_car') return null;

		$brand = sanitize($_POST['brand']);
		$model = sanitize($_POST['model']);
		$year = intval($_POST['year']);
		$price = floatval($_POST['daily_price']);
		$license = sanitize($_POST['license_plate']);
		$imageUrl = '';
		$agencyId = $_SESSION['user_agency_id'];

		if (empty($brand) || empty($model) || empty($license)) return ['error' => 'All fields are required.'];

		// Handle image upload
		if (isset($_FILES['car_image']) && $_FILES['car_image']['error'] === UPLOAD_ERR_OK) {
			$uploadDir = 'uploads/cars/';
			if (!is_dir($uploadDir)) {
				mkdir($uploadDir, 0755, true);
			}
			$ext = pathinfo($_FILES['car_image']['name'], PATHINFO_EXTENSION);
			$filename = uniqid('car_') . '.' . $ext;
			$targetPath = $uploadDir . $filename;
			if (move_uploaded_file($_FILES['car_image']['tmp_name'], $targetPath)) {
				$imageUrl = $targetPath;
			}
		}

		try {
			$stmt = $pdo->prepare("INSERT INTO cars (agency_id, brand, model, year, daily_price, license_plate, image_url, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'available')");
			$stmt->execute([$agencyId, $brand, $model, $year, $price, $license, $imageUrl]);
			return ['success' => 'Car added successfully.'];
		} catch (PDOException $e) {
			if ($e->getCode() == 23000) return ['error' => 'License plate already exists.'];
			return ['error' => 'Failed to add car.'];
		}
	}

	function handleUpdateCar()
	{
		global $pdo;
		if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $_POST['action'] !== 'update_car') return null;

		$carId = intval($_POST['car_id']);
		$brand = sanitize($_POST['brand']);
		$model = sanitize($_POST['model']);
		$year = intval($_POST['year']);
		$price = floatval($_POST['daily_price']);
		$license = sanitize($_POST['license_plate']);


		$check = $pdo->prepare("SELECT agency_id FROM cars WHERE car_id = ?");
		$check->execute([$carId]);
		$car = $check->fetch();
		if (!$car || $car['agency_id'] != $_SESSION['user_agency_id']) {
			return ['error' => 'Unauthorized or car not found.'];
		}

		if (empty($brand) || empty($model) || empty($license)) return ['error' => 'All fields are required.'];

		$imageUrl = null;
		if (isset($_FILES['car_image']) && $_FILES['car_image']['error'] === UPLOAD_ERR_OK) {
			$uploadDir = 'uploads/cars/';
			if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
			$ext = pathinfo($_FILES['car_image']['name'], PATHINFO_EXTENSION);
			$filename = uniqid('car_') . '.' . $ext;
			$targetPath = $uploadDir . $filename;
			if (move_uploaded_file($_FILES['car_image']['tmp_name'], $targetPath)) {
				$imageUrl = $targetPath;
			}
		}

		try {
			if ($imageUrl) {
				$stmt = $pdo->prepare("UPDATE cars SET brand = ?, model = ?, year = ?, daily_price = ?, license_plate = ?, image_url = ? WHERE car_id = ?");
				$stmt->execute([$brand, $model, $year, $price, $license, $imageUrl, $carId]);
			} else {
				$stmt = $pdo->prepare("UPDATE cars SET brand = ?, model = ?, year = ?, daily_price = ?, license_plate = ? WHERE car_id = ?");
				$stmt->execute([$brand, $model, $year, $price, $license, $carId]);
			}
			return ['success' => 'Car updated successfully.'];
		} catch (PDOException $e) {
			if ($e->getCode() == 23000) return ['error' => 'License plate already exists.'];
			return ['error' => 'Failed to update car.'];
		}
	}

	function handleDeleteCar()
	{
		global $pdo;
		if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $_POST['action'] !== 'delete_car') return null;

		$carId = intval($_POST['car_id']);

		$check = $pdo->prepare("SELECT agency_id FROM cars WHERE car_id = ?");
		$check->execute([$carId]);
		$car = $check->fetch();
		if (!$car || $car['agency_id'] != $_SESSION['user_agency_id']) {
			return ['error' => 'Unauthorized or car not found.'];
		}

		try {
			$stmt = $pdo->prepare("DELETE FROM cars WHERE car_id = ?");
			$stmt->execute([$carId]);
			return ['success' => 'Car deleted successfully.'];
		} catch (PDOException $e) {
			return ['error' => 'Failed to delete car.'];
		}
	}

	function renderAdminCars()
	{
		if (!isAdmin()) {
			header('Location: himihiba.php?page=auth');
			exit;
		}
		global $pdo;
		$result = handleAddCar();
		if (!$result) $result = handleUpdateCar();
		if (!$result) $result = handleDeleteCar();

		$agencyId = $_SESSION['user_agency_id'];
		$stmt = $pdo->prepare("SELECT * FROM cars WHERE agency_id = ? ORDER BY car_id DESC");
		$stmt->execute([$agencyId]);
		$cars = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
	<div class="admin-layout" style="display: flex; min-height: 100vh; background: #f3f4f6;">
		<?php renderAdminSidebar('cars'); ?>

		<main class="admin-main" style="flex: 1; margin-left: 250px; padding: 2rem;">
			<header style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
				<div>
					<h1 style="font-size: 1.875rem; font-weight: 600; color: #111827;">Fleet Management</h1>
					<p style="color: #6b7280;">Manage your luxury car inventory.</p>
				</div>
				<button onclick="document.getElementById('addCarModal').style.display='flex'" style="background: #1f2937; color: white; padding: 0.75rem 1.5rem; border: none; border-radius: 0.5rem; cursor: pointer; display: flex; align-items: center; gap: 0.5rem;">
					<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
						<line x1="12" y1="5" x2="12" y2="19"></line>
						<line x1="5" y1="12" x2="19" y2="12"></line>
					</svg>
					Add New Car
				</button>
			</header>

			<?php if ($result && isset($result['success'])): ?>
				<div style="background: #dcfce7; color: #16a34a; padding: 1rem; border-radius: 8px; margin-bottom: 2rem; border: 1px solid #bbf7d0;"><?php echo htmlspecialchars($result['success']); ?></div>
			<?php endif; ?>
			<?php if ($result && isset($result['error'])): ?>
				<div style="background: #fee2e2; color: #dc2626; padding: 1rem; border-radius: 8px; margin-bottom: 2rem; border: 1px solid #fecaca;"><?php echo htmlspecialchars($result['error']); ?></div>
			<?php endif; ?>

			<div style="background: white; border-radius: 0.75rem; border: 1px solid #e5e7eb; overflow: hidden;">
				<table style="width: 100%; border-collapse: collapse; text-align: left;">
					<thead style="background: #f9fafb; border-bottom: 1px solid #e5e7eb;">
						<tr>
							<th style="padding: 1rem; font-weight: 600; color: #4b5563;">Vehicle</th>
							<th style="padding: 1rem; font-weight: 600; color: #4b5563;">Year</th>
							<th style="padding: 1rem; font-weight: 600; color: #4b5563;">License Plate</th>
							<th style="padding: 1rem; font-weight: 600; color: #4b5563;">Price/Day</th>
							<th style="padding: 1rem; font-weight: 600; color: #4b5563;">Status</th>
							<th style="padding: 1rem; font-weight: 600; color: #4b5563;">Actions</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($cars as $car): ?>
							<tr style="border-bottom: 1px solid #e5e7eb;">
								<td style="padding: 1rem;">
									<div style="display: flex; align-items: center; gap: 0.75rem;">
										<img src="<?php echo htmlspecialchars($car['image_url'] ?: 'https://images.unsplash.com/photo-1503376763036-066120622c74?w=100&q=80'); ?>" style="width: 48px; height: 32px; object-fit: cover; border-radius: 4px;">
										<span style="font-weight: 500; color: #111827;"><?php echo htmlspecialchars($car['brand'] . ' ' . $car['model']); ?></span>
									</div>
								</td>
								<td style="padding: 1rem; color: #6b7280;"><?php echo $car['year']; ?></td>
								<td style="padding: 1rem; font-family: monospace; color: #6b7280;"><?php echo htmlspecialchars($car['license_plate']); ?></td>
								<td style="padding: 1rem; font-weight: 500;">$<?php echo number_format($car['daily_price'], 2); ?></td>
								<td style="padding: 1rem;">
									<span style="display: inline-block; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 500; background: #dcfce7; color: #166534;">
										<?php echo ucfirst($car['status']); ?>
									</span>
								</td>
								<td style="padding: 1rem;">
									<div style="display: flex; gap: 0.5rem;">
										<button onclick="openEditCarModal(<?php echo htmlspecialchars(json_encode($car)); ?>)" style="padding: 0.25rem 0.5rem; border: 1px solid #d1d5db; border-radius: 0.25rem; background: white; cursor: pointer; color: #4b5563;">Edit</button>
										<form method="POST" action="?page=admin_cars" onsubmit="return confirm('Are you sure you want to delete this car?');" style="display: inline;">
											<input type="hidden" name="action" value="delete_car">
											<input type="hidden" name="car_id" value="<?php echo $car['car_id']; ?>">
											<button type="submit" style="padding: 0.25rem 0.5rem; border: 1px solid #fee2e2; border-radius: 0.25rem; background: #fef2f2; cursor: pointer; color: #dc2626;">Delete</button>
										</form>
									</div>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</main>
	</div>

	<!-- Add Car Modal -->
	<div id="addCarModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); align-items: center; justify-content: center; z-index: 1000;">
		<div style="background: white; padding: 2rem; border-radius: 1rem; width: 100%; max-width: 500px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1);">
			<header style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
				<h2 style="font-size: 1.5rem; font-weight: 600;">Add New Car</h2>
				<button onclick="document.getElementById('addCarModal').style.display='none'" style="background: none; border: none; cursor: pointer; color: #6b7280;">
					<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
						<path d="M18 6L6 18M6 6l12 12"></path>
					</svg>
				</button>
			</header>

			<form method="POST" action="?page=admin_cars" enctype="multipart/form-data">
				<input type="hidden" name="action" value="add_car">
				<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
					<div>
						<label style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 0.25rem;">Brand</label>
						<input type="text" name="brand" required style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem;">
					</div>
					<div>
						<label style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 0.25rem;">Model</label>
						<input type="text" name="model" required style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem;">
					</div>
				</div>
				<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
					<div>
						<label style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 0.25rem;">Year</label>
						<input type="number" name="year" min="1900" max="2099" required style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem;">
					</div>
					<div>
						<label style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 0.25rem;">Daily Price ($)</label>
						<input type="number" name="daily_price" step="0.01" required style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem;">
					</div>
				</div>
				<div style="margin-bottom: 1rem;">
					<label style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 0.25rem;">License Plate</label>
					<input type="text" name="license_plate" required style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem;">
				</div>
				<div style="margin-bottom: 1.5rem;">
					<label style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 0.25rem;">Car Image</label>
					<input type="file" name="car_image" accept="image/*" style="width: 100%;">
				</div>

				<div style="display: flex; gap: 1rem; justify-content: flex-end;">
					<button type="button" onclick="document.getElementById('addCarModal').style.display='none'" style="padding: 0.5rem 1rem; border: 1px solid #d1d5db; background: white; border-radius: 0.375rem; cursor: pointer;">Cancel</button>
					<button type="submit" style="padding: 0.5rem 1rem; background: #1f2937; color: white; border: none; border-radius: 0.375rem; cursor: pointer;">Add Car</button>
				</div>
			</form>
		</div>
	</div>

	<!-- Edit Car Modal -->
	<div id="editCarModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); align-items: center; justify-content: center; z-index: 1000;">
		<div style="background: white; padding: 2rem; border-radius: 1rem; width: 100%; max-width: 500px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1);">
			<header style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
				<h2 style="font-size: 1.5rem; font-weight: 600;">Edit Car</h2>
				<button onclick="document.getElementById('editCarModal').style.display='none'" style="background: none; border: none; cursor: pointer; color: #6b7280;">
					<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
						<path d="M18 6L6 18M6 6l12 12"></path>
					</svg>
				</button>
			</header>

			<form method="POST" action="?page=admin_cars" enctype="multipart/form-data" id="editCarForm">
				<input type="hidden" name="action" value="update_car">
				<input type="hidden" name="car_id" id="edit_car_id">
				<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
					<div>
						<label style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 0.25rem;">Brand</label>
						<input type="text" name="brand" id="edit_brand" required style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem;">
					</div>
					<div>
						<label style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 0.25rem;">Model</label>
						<input type="text" name="model" id="edit_model" required style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem;">
					</div>
				</div>
				<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
					<div>
						<label style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 0.25rem;">Year</label>
						<input type="number" name="year" id="edit_year" min="1900" max="2099" required style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem;">
					</div>
					<div>
						<label style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 0.25rem;">Daily Price ($)</label>
						<input type="number" name="daily_price" id="edit_daily_price" step="0.01" required style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem;">
					</div>
				</div>
				<div style="margin-bottom: 1rem;">
					<label style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 0.25rem;">License Plate</label>
					<input type="text" name="license_plate" id="edit_license_plate" required style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem;">
				</div>
				<div style="margin-bottom: 1.5rem;">
					<label style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 0.25rem;">Car Image (Leave blank to keep current)</label>
					<input type="file" name="car_image" accept="image/*" style="width: 100%;">
				</div>

				<div style="display: flex; gap: 1rem; justify-content: flex-end;">
					<button type="button" onclick="document.getElementById('editCarModal').style.display='none'" style="padding: 0.5rem 1rem; border: 1px solid #d1d5db; background: white; border-radius: 0.375rem; cursor: pointer;">Cancel</button>
					<button type="submit" style="padding: 0.5rem 1rem; background: #1f2937; color: white; border: none; border-radius: 0.375rem; cursor: pointer;">Save Changes</button>
				</div>
			</form>
		</div>
	</div>

	<script>
		function openEditCarModal(car) {
			document.getElementById('edit_car_id').value = car.car_id;
			document.getElementById('edit_brand').value = car.brand;
			document.getElementById('edit_model').value = car.model;
			document.getElementById('edit_year').value = car.year;
			document.getElementById('edit_daily_price').value = car.daily_price;
			document.getElementById('edit_license_plate').value = car.license_plate;
			document.getElementById('editCarModal').style.display = 'flex';
		}
	</script>

<?php
	}

	function handleAddStaff()
	{
		global $pdo;
		if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $_POST['action'] !== 'add_staff') return null;

		$firstName = sanitize($_POST['first_name']);
		$lastName = sanitize($_POST['last_name']);
		$email = sanitize($_POST['email']);
		$password = $_POST['password'];
		$role = sanitize($_POST['role']);
		$agencyId = $_SESSION['user_agency_id'];

		if (empty($firstName) || empty($lastName) || empty($email) || empty($password) || empty($role)) {
			return ['error' => 'All fields are required.'];
		}

		try {
			$hash = password_hash($password, PASSWORD_DEFAULT);
			$stmt = $pdo->prepare("INSERT INTO staff (agency_id, first_name, last_name, email, password_hash, role) VALUES (?, ?, ?, ?, ?, ?)");
			$stmt->execute([$agencyId, $firstName, $lastName, $email, $hash, $role]);
			return ['success' => 'Staff member added successfully.'];
		} catch (PDOException $e) {
			if ($e->getCode() == 23000) return ['error' => 'Email already exists.'];
			return ['error' => 'Failed to add staff.'];
		}
	}

	function handleUpdateStaff()
	{
		global $pdo;
		if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $_POST['action'] !== 'update_staff') return null;

		$staffId = intval($_POST['staff_id']);
		$firstName = sanitize($_POST['first_name']);
		$lastName = sanitize($_POST['last_name']);
		$email = sanitize($_POST['email']);
		$role = sanitize($_POST['role']);
		$agencyId = $_SESSION['user_agency_id'];

		if (empty($firstName) || empty($lastName) || empty($email) || empty($role)) {
			return ['error' => 'All fields are required.'];
		}

		// Verify ownership
		$check = $pdo->prepare("SELECT agency_id FROM staff WHERE staff_id = ?");
		$check->execute([$staffId]);
		$staff = $check->fetch();
		if (!$staff || $staff['agency_id'] != $agencyId) return ['error' => 'Unauthorized'];

		try {
			$stmt = $pdo->prepare("UPDATE staff SET first_name = ?, last_name = ?, email = ?, role = ? WHERE staff_id = ?");
			$stmt->execute([$firstName, $lastName, $email, $role, $staffId]);
			return ['success' => 'Staff member updated successfully.'];
		} catch (PDOException $e) {
			if ($e->getCode() == 23000) return ['error' => 'Email already exists.'];
			return ['error' => 'Failed to update staff.'];
		}
	}

	function handleDeleteStaff()
	{
		global $pdo;
		if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $_POST['action'] !== 'delete_staff') return null;
		$staffId = intval($_POST['staff_id']);
		$agencyId = $_SESSION['user_agency_id'];

		// Verify ownership
		$check = $pdo->prepare("SELECT agency_id FROM staff WHERE staff_id = ?");
		$check->execute([$staffId]);
		$staff = $check->fetch();
		if (!$staff || $staff['agency_id'] != $agencyId) return ['error' => 'Unauthorized'];

		try {
			$stmt = $pdo->prepare("DELETE FROM staff WHERE staff_id = ?");
			$stmt->execute([$staffId]);
			return ['success' => 'Staff member deleted successfully.'];
		} catch (PDOException $e) {
			return ['error' => 'Failed to delete staff.'];
		}
	}

	function renderAdminStaff()
	{
		if (!isAdmin()) {
			header('Location: himihiba.php?page=auth');
			exit;
		}
		global $pdo;
		$agencyId = $_SESSION['user_agency_id'];

		$result = handleAddStaff();
		if (!$result) $result = handleUpdateStaff();
		if (!$result) $result = handleDeleteStaff();

		$stmt = $pdo->prepare("SELECT * FROM staff WHERE agency_id = ? ORDER BY hire_date DESC");
		$stmt->execute([$agencyId]);
		$staffMembers = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
	<div class="admin-layout" style="display: flex; min-height: 100vh; background: #f3f4f6;">
		<aside class="admin-sidebar" style="width: 250px; background: white; border-right: 1px solid #e5e7eb; position: fixed; height: 100%; overflow-y: auto;">
			<div style="padding: 1.5rem; border-bottom: 1px solid #e5e7eb;">
				<h2 style="font-size: 1.5rem; color: #1f2937;">LUXDRIVE <span style="font-size: 0.8rem; color: #6b7280; display: block; font-family: 'Inter', sans-serif; margin-top: 0.25rem;">Admin Panel</span></h2>
			</div>
			<nav style="padding: 1rem;">
				<ul style="list-style: none;">
					<li><a href="?page=admin_dashboard" class="admin-nav-link" style="display: block; padding: 0.75rem 1rem; color: #4b5563; text-decoration: none; border-radius: 0.5rem; margin-bottom: 0.5rem; transition: all 0.2s;">Dashboard</a></li>
					<li><a href="?page=admin_rentals" class="admin-nav-link" style="display: block; padding: 0.75rem 1rem; color: #4b5563; text-decoration: none; border-radius: 0.5rem; margin-bottom: 0.5rem; transition: all 0.2s;">Rentals</a></li>
					<li><a href="?page=admin_cars" class="admin-nav-link" style="display: block; padding: 0.75rem 1rem; color: #4b5563; text-decoration: none; border-radius: 0.5rem; margin-bottom: 0.5rem; transition: all 0.2s;">Fleet Management</a></li>
					<li><a href="?page=admin_maintenance" class="admin-nav-link" style="display: block; padding: 0.75rem 1rem; color: #4b5563; text-decoration: none; border-radius: 0.5rem; margin-bottom: 0.5rem; transition: all 0.2s;">Maintenance</a></li>
					<li><a href="?page=admin_staff" class="admin-nav-link active" style="display: block; padding: 0.75rem 1rem; color: #1f2937; text-decoration: none; border-radius: 0.5rem; margin-bottom: 0.5rem; background: #f3f4f6; font-weight: 500;">Staff Management</a></li>
					<li><a href="?page=admin_clients" class="admin-nav-link" style="display: block; padding: 0.75rem 1rem; color: #4b5563; text-decoration: none; border-radius: 0.5rem; margin-bottom: 0.5rem; transition: all 0.2s;">Clients</a></li>
					<li><a href="?page=admin_reports" class="admin-nav-link" style="display: block; padding: 0.75rem 1rem; color: #4b5563; text-decoration: none; border-radius: 0.5rem; margin-bottom: 0.5rem; transition: all 0.2s;">Reports</a></li>
					<li style="margin-top: 2rem; border-top: 1px solid #e5e7eb; padding-top: 1rem;"><a href="?page=logout" style="display: block; padding: 0.75rem 1rem; color: #ef4444; text-decoration: none; border-radius: 0.5rem;">Logout</a></li>
				</ul>
			</nav>
		</aside>

		<main class="admin-main" style="flex: 1; margin-left: 250px; padding: 2rem;">
			<header style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
				<div>
					<h1 style="font-size: 1.875rem; font-weight: 600; color: #111827;">Staff Management</h1>
					<p style="color: #6b7280;">Manage your agency's team members.</p>
				</div>
				<button onclick="document.getElementById('addStaffModal').style.display='flex'" style="background: #1f2937; color: white; padding: 0.75rem 1.5rem; border: none; border-radius: 0.5rem; cursor: pointer; display: flex; align-items: center; gap: 0.5rem;">
					<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
						<line x1="12" y1="5" x2="12" y2="19"></line>
						<line x1="5" y1="12" x2="19" y2="12"></line>
					</svg>
					Add Staff
				</button>
			</header>

			<?php if ($result && isset($result['success'])): ?>
				<div style="background: #dcfce7; color: #16a34a; padding: 1rem; border-radius: 8px; margin-bottom: 2rem; border: 1px solid #bbf7d0;"><?php echo htmlspecialchars($result['success']); ?></div>
			<?php endif; ?>
			<?php if ($result && isset($result['error'])): ?>
				<div style="background: #fee2e2; color: #dc2626; padding: 1rem; border-radius: 8px; margin-bottom: 2rem; border: 1px solid #fecaca;"><?php echo htmlspecialchars($result['error']); ?></div>
			<?php endif; ?>

			<div style="background: white; border-radius: 0.75rem; border: 1px solid #e5e7eb; overflow: hidden;">
				<table style="width: 100%; border-collapse: collapse; text-align: left;">
					<thead style="background: #f9fafb; border-bottom: 1px solid #e5e7eb;">
						<tr>
							<th style="padding: 1rem; font-weight: 600; color: #4b5563;">Name</th>
							<th style="padding: 1rem; font-weight: 600; color: #4b5563;">Role</th>
							<th style="padding: 1rem; font-weight: 600; color: #4b5563;">Email</th>
							<th style="padding: 1rem; font-weight: 600; color: #4b5563;">Joined</th>
							<th style="padding: 1rem; font-weight: 600; color: #4b5563;">Status</th>
							<th style="padding: 1rem; font-weight: 600; color: #4b5563;">Actions</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($staffMembers as $staff): ?>
							<tr style="border-bottom: 1px solid #e5e7eb;">
								<td style="padding: 1rem;">
									<div style="display: flex; align-items: center; gap: 0.75rem;">
										<div style="width: 32px; height: 32px; background: #e5e7eb; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 600; color: #6b7280;">
											<?php echo strtoupper(substr($staff['first_name'], 0, 1)); ?>
										</div>
										<span style="font-weight: 500; color: #111827;"><?php echo htmlspecialchars($staff['first_name'] . ' ' . $staff['last_name']); ?></span>
									</div>
								</td>
								<td style="padding: 1rem;">
									<span style="display: inline-block; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 500; background: #eff6ff; color: #1d4ed8;">
										<?php echo ucfirst($staff['role']); ?>
									</span>
								</td>
								<td style="padding: 1rem; color: #6b7280;"><?php echo htmlspecialchars($staff['email']); ?></td>
								<td style="padding: 1rem; color: #6b7280;"><?php echo date('M d, Y', strtotime($staff['hire_date'])); ?></td>
								<td style="padding: 1rem;">
									<span style="display: inline-flex; align-items: center; gap: 0.25rem; color: #059669;">
										<span style="width: 6px; height: 6px; background: #059669; border-radius: 50%;"></span>
										Active
									</span>
								</td>
								<td style="padding: 1rem;">
									<div style="display: flex; gap: 0.5rem;">
										<button onclick="openEditStaffModal(<?php echo htmlspecialchars(json_encode($staff)); ?>)" style="padding: 0.25rem 0.5rem; border: 1px solid #d1d5db; border-radius: 0.25rem; background: white; cursor: pointer; color: #4b5563;">Edit</button>
										<form method="POST" action="?page=admin_staff" onsubmit="return confirm('Are you sure you want to delete this staff member?');" style="display: inline;">
											<input type="hidden" name="action" value="delete_staff">
											<input type="hidden" name="staff_id" value="<?php echo $staff['staff_id']; ?>">
											<button type="submit" style="padding: 0.25rem 0.5rem; border: 1px solid #fee2e2; border-radius: 0.25rem; background: #fef2f2; cursor: pointer; color: #dc2626;">Delete</button>
										</form>
									</div>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</main>
	</div>

	<!-- Add Staff Modal -->
	<div id="addStaffModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); align-items: center; justify-content: center; z-index: 1000;">
		<div style="background: white; padding: 2rem; border-radius: 1rem; width: 100%; max-width: 500px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1);">
			<header style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
				<h2 style="font-size: 1.5rem; font-weight: 600;">Add New Staff</h2>
				<button onclick="document.getElementById('addStaffModal').style.display='none'" style="background: none; border: none; cursor: pointer; color: #6b7280;">
					<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
						<path d="M18 6L6 18M6 6l12 12"></path>
					</svg>
				</button>
			</header>

			<form method="POST" action="?page=admin_staff">
				<input type="hidden" name="action" value="add_staff">
				<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
					<div>
						<label style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 0.25rem;">First Name</label>
						<input type="text" name="first_name" required style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem;">
					</div>
					<div>
						<label style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 0.25rem;">Last Name</label>
						<input type="text" name="last_name" required style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem;">
					</div>
				</div>
				<div style="margin-bottom: 1rem;">
					<label style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 0.25rem;">Email Address</label>
					<input type="email" name="email" required style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem;">
				</div>
				<div style="margin-bottom: 1rem;">
					<label style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 0.25rem;">Role</label>
					<select name="role" required style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem;">
						<option value="agent">Agent</option>
						<option value="mechanic">Mechanic</option>
						<option value="admin">Admin</option>
					</select>
				</div>
				<div style="margin-bottom: 1.5rem;">
					<label style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 0.25rem;">Password</label>
					<input type="password" name="password" required style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem;">
				</div>

				<div style="display: flex; gap: 1rem; justify-content: flex-end;">
					<button type="button" onclick="document.getElementById('addStaffModal').style.display='none'" style="padding: 0.5rem 1rem; border: 1px solid #d1d5db; background: white; border-radius: 0.375rem; cursor: pointer;">Cancel</button>
					<button type="submit" style="padding: 0.5rem 1rem; background: #1f2937; color: white; border: none; border-radius: 0.375rem; cursor: pointer;">Create Account</button>
				</div>
			</form>
		</div>
	</div>

	<!-- Edit Staff Modal -->
	<div id="editStaffModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); align-items: center; justify-content: center; z-index: 1000;">
		<div style="background: white; padding: 2rem; border-radius: 1rem; width: 100%; max-width: 500px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1);">
			<header style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
				<h2 style="font-size: 1.5rem; font-weight: 600;">Edit Staff</h2>
				<button onclick="document.getElementById('editStaffModal').style.display='none'" style="background: none; border: none; cursor: pointer; color: #6b7280;">
					<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
						<path d="M18 6L6 18M6 6l12 12"></path>
					</svg>
				</button>
			</header>

			<form method="POST" action="?page=admin_staff" id="editStaffForm">
				<input type="hidden" name="action" value="update_staff">
				<input type="hidden" name="staff_id" id="edit_staff_id">
				<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
					<div>
						<label style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 0.25rem;">First Name</label>
						<input type="text" name="first_name" id="edit_first_name" required style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem;">
					</div>
					<div>
						<label style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 0.25rem;">Last Name</label>
						<input type="text" name="last_name" id="edit_last_name" required style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem;">
					</div>
				</div>
				<div style="margin-bottom: 1rem;">
					<label style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 0.25rem;">Email Address</label>
					<input type="email" name="email" id="edit_email" required style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem;">
				</div>
				<div style="margin-bottom: 1.5rem;">
					<label style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 0.25rem;">Role</label>
					<select name="role" id="edit_role" required style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem;">
						<option value="agent">Agent</option>
						<option value="mechanic">Mechanic</option>
						<option value="admin">Admin</option>
					</select>
				</div>

				<div style="display: flex; gap: 1rem; justify-content: flex-end;">
					<button type="button" onclick="document.getElementById('editStaffModal').style.display='none'" style="padding: 0.5rem 1rem; border: 1px solid #d1d5db; background: white; border-radius: 0.375rem; cursor: pointer;">Cancel</button>
					<button type="submit" style="padding: 0.5rem 1rem; background: #1f2937; color: white; border: none; border-radius: 0.375rem; cursor: pointer;">Save Changes</button>
				</div>
			</form>
		</div>
	</div>

	<script>
		function openEditStaffModal(staff) {
			document.getElementById('edit_staff_id').value = staff.staff_id;
			document.getElementById('edit_first_name').value = staff.first_name;
			document.getElementById('edit_last_name').value = staff.last_name;
			document.getElementById('edit_email').value = staff.email;
			document.getElementById('edit_role').value = staff.role;
			document.getElementById('editStaffModal').style.display = 'flex';
		}
	</script>
<?php
	}

	function handleCreateRental()
	{
		global $pdo;
		if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $_POST['action'] !== 'create_rental') return null;

		$clientId = intval($_POST['client_id']);
		$carId = intval($_POST['car_id']);
		$startDate = sanitize($_POST['start_date']);
		$endDate = sanitize($_POST['end_date']);
		$totalPrice = floatval($_POST['total_price']);
		$staffId = $_SESSION['user_id'];
		$agencyId = $_SESSION['user_agency_id'];

		if (empty($clientId) || empty($carId) || empty($startDate) || empty($endDate)) {
			return ['error' => 'All fields are required.'];
		}

		// Ensure car belongs to agency
		$check = $pdo->prepare("SELECT agency_id FROM cars WHERE car_id = ?");
		$check->execute([$carId]);
		$car = $check->fetch();
		if (!$car || $car['agency_id'] != $agencyId) {
			return ['error' => 'Unauthorized or car not found.'];
		}

		if (strtotime($startDate) > strtotime($endDate)) {
			return ['error' => 'Start date cannot be after end date.'];
		}

		try {
			$stmt = $pdo->prepare("INSERT INTO rentals (agency_id, client_id, car_id, staff_id, start_date, end_date, total_price, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'ongoing')");
			$stmt->execute([$agencyId, $clientId, $carId, $staffId, $startDate, $endDate, $totalPrice]);

			$pdo->prepare("UPDATE cars SET status = 'rented' WHERE car_id = ?")->execute([$carId]);

			return ['success' => 'Rental created successfully.'];
		} catch (PDOException $e) {
			return ['error' => 'Failed to create rental.'];
		}
	}

	function handleUpdateRental()
	{
		global $pdo;
		if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $_POST['action'] !== 'update_rental') return null;

		$rentalId = intval($_POST['rental_id']);
		$carId = intval($_POST['car_id']);
		$startDate = sanitize($_POST['start_date']);
		$endDate = sanitize($_POST['end_date']);
		$totalPrice = floatval($_POST['total_price']);
		$agencyId = $_SESSION['user_agency_id'];

		if (empty($rentalId) || empty($carId) || empty($startDate) || empty($endDate)) {
			return ['error' => 'All fields are required.'];
		}

		// Check ownership
		$check = $pdo->prepare("SELECT agency_id FROM rentals WHERE rental_id = ?");
		$check->execute([$rentalId]);
		$rental = $check->fetch();
		if (!$rental || $rental['agency_id'] != $agencyId) return ['error' => 'Unauthorized'];

		// Check new car ownership
		$checkCar = $pdo->prepare("SELECT agency_id FROM cars WHERE car_id = ?");
		$checkCar->execute([$carId]);
		$car = $checkCar->fetch();
		if (!$car || $car['agency_id'] != $agencyId) return ['error' => 'Invalid car selection'];


		try {
			$stmt = $pdo->prepare("UPDATE rentals SET car_id = ?, start_date = ?, end_date = ?, total_price = ? WHERE rental_id = ?");
			$stmt->execute([$carId, $startDate, $endDate, $totalPrice, $rentalId]);
			return ['success' => 'Rental updated successfully.'];
		} catch (PDOException $e) {
			return ['error' => 'Failed to update rental.'];
		}
	}

	function handleRentalStatus()
	{
		global $pdo;
		if ($_SERVER['REQUEST_METHOD'] !== 'POST' || (!isset($_POST['cancel_rental']) && !isset($_POST['complete_rental']))) return null;

		$rentalId = intval($_POST['rental_id']);
		$newStatus = isset($_POST['complete_rental']) ? 'completed' : 'cancelled';
		$agencyId = $_SESSION['user_agency_id'];

		try {
			$stmt = $pdo->prepare("SELECT car_id, agency_id FROM rentals WHERE rental_id = ?");
			$stmt->execute([$rentalId]);
			$rental = $stmt->fetch();

			if (!$rental || $rental['agency_id'] != $agencyId) return ['error' => 'Unauthorized'];
			$carId = $rental['car_id'];

			$stmt = $pdo->prepare("UPDATE rentals SET status = ? WHERE rental_id = ?");
			$stmt->execute([$newStatus, $rentalId]);

			if ($carId) {
				$pdo->prepare("UPDATE cars SET status = 'available' WHERE car_id = ?")->execute([$carId]);
			}

			return ['success' => 'Rental status updated to ' . $newStatus . '.'];
		} catch (PDOException $e) {
			return ['error' => 'Failed to update rental status.'];
		}
	}

	function renderAdminRentals()
	{
		if (!isAdmin()) {
			header('Location: himihiba.php?page=auth');
			exit;
		}
		global $pdo;

		$result = handleCreateRental();
		if (!$result) $result = handleUpdateRental();
		if (!$result) $result = handleRentalStatus();

		$agencyId = $_SESSION['user_agency_id'];

		$stmt = $pdo->prepare("
			SELECT r.*, c.first_name AS client_first, c.last_name AS client_last, car.brand, car.model, car.license_plate 
			FROM rentals r 
			JOIN clients c ON r.client_id = c.client_id 
			JOIN cars car ON r.car_id = car.car_id 
			WHERE r.agency_id = ?
			ORDER BY r.created_at DESC
		");
		$stmt->execute([$agencyId]);
		$rentals = $stmt->fetchAll(PDO::FETCH_ASSOC);

		$clients = $pdo->query("SELECT * FROM clients ORDER BY last_name ASC")->fetchAll(PDO::FETCH_ASSOC);

		$stmtCars = $pdo->prepare("SELECT * FROM cars WHERE status = 'available' AND agency_id = ?");
		$stmtCars->execute([$agencyId]);
		$availableCars = $stmtCars->fetchAll(PDO::FETCH_ASSOC);

		$stmtAllCars = $pdo->prepare("SELECT * FROM cars WHERE agency_id = ? ORDER BY brand ASC");
		$stmtAllCars->execute([$agencyId]);
		$allCars = $stmtAllCars->fetchAll(PDO::FETCH_ASSOC);
?>
	<div class="admin-layout" style="display: flex; min-height: 100vh; background: #f3f4f6;">
		<aside class="admin-sidebar" style="width: 250px; background: white; border-right: 1px solid #e5e7eb; position: fixed; height: 100%; overflow-y: auto;">
			<div style="padding: 1.5rem; border-bottom: 1px solid #e5e7eb;">
				<h2 style="font-size: 1.5rem; color: #1f2937;">LUXDRIVE <span style="font-size: 0.8rem; color: #6b7280; display: block; font-family: 'Inter', sans-serif; margin-top: 0.25rem;">Admin Panel</span></h2>
			</div>
			<nav style="padding: 1rem;">
				<ul style="list-style: none;">
					<li><a href="?page=admin_dashboard" class="admin-nav-link" style="display: block; padding: 0.75rem 1rem; color: #4b5563; text-decoration: none; border-radius: 0.5rem; margin-bottom: 0.5rem; transition: all 0.2s;">Dashboard</a></li>
					<li><a href="?page=admin_rentals" class="admin-nav-link active" style="display: block; padding: 0.75rem 1rem; color: #1f2937; text-decoration: none; border-radius: 0.5rem; margin-bottom: 0.5rem; background: #f3f4f6; font-weight: 500;">Rentals</a></li>
					<li><a href="?page=admin_cars" class="admin-nav-link" style="display: block; padding: 0.75rem 1rem; color: #4b5563; text-decoration: none; border-radius: 0.5rem; margin-bottom: 0.5rem; transition: all 0.2s;">Fleet Management</a></li>
					<li><a href="?page=admin_maintenance" class="admin-nav-link" style="display: block; padding: 0.75rem 1rem; color: #4b5563; text-decoration: none; border-radius: 0.5rem; margin-bottom: 0.5rem; transition: all 0.2s;">Maintenance</a></li>
					<li><a href="?page=admin_staff" class="admin-nav-link" style="display: block; padding: 0.75rem 1rem; color: #4b5563; text-decoration: none; border-radius: 0.5rem; margin-bottom: 0.5rem; transition: all 0.2s;">Staff Management</a></li>
					<li><a href="?page=admin_clients" class="admin-nav-link" style="display: block; padding: 0.75rem 1rem; color: #4b5563; text-decoration: none; border-radius: 0.5rem; margin-bottom: 0.5rem; transition: all 0.2s;">Clients</a></li>
					<li><a href="?page=admin_reports" class="admin-nav-link" style="display: block; padding: 0.75rem 1rem; color: #4b5563; text-decoration: none; border-radius: 0.5rem; margin-bottom: 0.5rem; transition: all 0.2s;">Reports</a></li>
					<li style="margin-top: 2rem; border-top: 1px solid #e5e7eb; padding-top: 1rem;"><a href="?page=logout" style="display: block; padding: 0.75rem 1rem; color: #ef4444; text-decoration: none; border-radius: 0.5rem;">Logout</a></li>
				</ul>
			</nav>
		</aside>

		<main class="admin-main" style="flex: 1; margin-left: 250px; padding: 2rem;">
			<header style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
				<div>
					<h1 style="font-size: 1.875rem; font-weight: 600; color: #111827;">Rentals Management</h1>
					<p style="color: #6b7280;">Monitor and manage bookings.</p>
				</div>
				<button onclick="document.getElementById('addRentalModal').style.display='flex'" style="background: #1f2937; color: white; padding: 0.75rem 1.5rem; border: none; border-radius: 0.5rem; cursor: pointer; display: flex; align-items: center; gap: 0.5rem;">
					<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
						<line x1="12" y1="5" x2="12" y2="19"></line>
						<line x1="5" y1="12" x2="19" y2="12"></line>
					</svg>
					New Rental
				</button>
			</header>

			<?php if ($result && isset($result['success'])): ?>
				<div style="background: #dcfce7; color: #16a34a; padding: 1rem; border-radius: 8px; margin-bottom: 2rem; border: 1px solid #bbf7d0;"><?php echo htmlspecialchars($result['success']); ?></div>
			<?php endif; ?>
			<?php if ($result && isset($result['error'])): ?>
				<div style="background: #fee2e2; color: #dc2626; padding: 1rem; border-radius: 8px; margin-bottom: 2rem; border: 1px solid #fecaca;"><?php echo htmlspecialchars($result['error']); ?></div>
			<?php endif; ?>

			<div style="background: white; border-radius: 0.75rem; border: 1px solid #e5e7eb; overflow: hidden;">
				<table style="width: 100%; border-collapse: collapse; text-align: left;">
					<thead style="background: #f9fafb; border-bottom: 1px solid #e5e7eb;">
						<tr>
							<th style="padding: 1rem; font-weight: 600; color: #4b5563;">Rental ID</th>
							<th style="padding: 1rem; font-weight: 600; color: #4b5563;">Client</th>
							<th style="padding: 1rem; font-weight: 600; color: #4b5563;">Car</th>
							<th style="padding: 1rem; font-weight: 600; color: #4b5563;">Dates</th>
							<th style="padding: 1rem; font-weight: 600; color: #4b5563;">Total</th>
							<th style="padding: 1rem; font-weight: 600; color: #4b5563;">Status</th>
							<th style="padding: 1rem; font-weight: 600; color: #4b5563;">Actions</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($rentals as $rental): ?>
							<tr style="border-bottom: 1px solid #e5e7eb;">
								<td style="padding: 1rem; font-family: monospace; color: #6b7280;">#<?php echo str_pad($rental['rental_id'], 5, '0', STR_PAD_LEFT); ?></td>
								<td style="padding: 1rem; font-weight: 500; color: #111827;">
									<?php echo htmlspecialchars($rental['client_first'] . ' ' . $rental['client_last']); ?>
								</td>
								<td style="padding: 1rem;">
									<?php echo htmlspecialchars($rental['brand'] . ' ' . $rental['model']); ?>
									<span style="display: block; font-size: 0.75rem; color: #6b7280; font-family: monospace;"><?php echo htmlspecialchars($rental['license_plate']); ?></span>
								</td>
								<td style="padding: 1rem; color: #6b7280; font-size: 0.875rem;">
									<?php echo date('M d', strtotime($rental['start_date'])) . ' - ' . date('M d, Y', strtotime($rental['end_date'])); ?>
								</td>
								<td style="padding: 1rem; font-weight: 500;">$<?php echo number_format($rental['total_price'], 2); ?></td>
								<td style="padding: 1rem;">
									<?php
									$statusColors = [
										'ongoing' => ['bg' => '#eff6ff', 'text' => '#1d4ed8'],
										'completed' => ['bg' => '#dcfce7', 'text' => '#166534'],
										'cancelled' => ['bg' => '#fee2e2', 'text' => '#dc2626']
									];
									$sc = $statusColors[$rental['status']] ?? $statusColors['ongoing'];
									?>
									<span style="display: inline-block; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 500; background: <?php echo $sc['bg']; ?>; color: <?php echo $sc['text']; ?>;">
										<?php echo ucfirst($rental['status']); ?>
									</span>
								</td>
								<td style="padding: 1rem;">
									<div style="display: flex; gap: 0.5rem;">
										<button onclick="openEditRentalModal(<?php echo htmlspecialchars(json_encode($rental)); ?>)" style="padding: 0.25rem 0.5rem; border: 1px solid #d1d5db; border-radius: 0.25rem; background: white; cursor: pointer; color: #4b5563;">Edit</button>
										<?php if ($rental['status'] === 'ongoing'): ?>
											<form method="POST" action="?page=admin_rentals" style="display: inline;">
												<input type="hidden" name="rental_id" value="<?php echo $rental['rental_id']; ?>">
												<button type="submit" name="complete_rental" value="1" style="padding: 0.25rem 0.5rem; border: 1px solid #bbf7d0; border-radius: 0.25rem; background: #f0fdf4; cursor: pointer; color: #166534;">Complete</button>
											</form>
											<form method="POST" action="?page=admin_rentals" onsubmit="return confirm('Are you sure you want to cancel this rental?');" style="display: inline;">
												<input type="hidden" name="rental_id" value="<?php echo $rental['rental_id']; ?>">
												<button type="submit" name="cancel_rental" value="1" style="padding: 0.25rem 0.5rem; border: 1px solid #fee2e2; border-radius: 0.25rem; background: #fef2f2; cursor: pointer; color: #dc2626;">Cancel</button>
											</form>
										<?php endif; ?>
									</div>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</main>
	</div>

	<!-- Add Rental Modal -->
	<div id="addRentalModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); align-items: center; justify-content: center; z-index: 1000;">
		<div style="background: white; padding: 2rem; border-radius: 1rem; width: 100%; max-width: 500px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1);">
			<header style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
				<h2 style="font-size: 1.5rem; font-weight: 600;">Create Rental</h2>
				<button onclick="document.getElementById('addRentalModal').style.display='none'" style="background: none; border: none; cursor: pointer; color: #6b7280;">
					<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
						<path d="M18 6L6 18M6 6l12 12"></path>
					</svg>
				</button>
			</header>

			<form method="POST" action="?page=admin_rentals">
				<input type="hidden" name="action" value="create_rental">
				<div style="margin-bottom: 1rem;">
					<label style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 0.25rem;">Client</label>
					<select name="client_id" required style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem;">
						<option value="">Select Client</option>
						<?php foreach ($clients as $c): ?>
							<option value="<?php echo $c['client_id']; ?>"><?php echo htmlspecialchars($c['first_name'] . ' ' . $c['last_name'] . ' (' . $c['email'] . ')'); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div style="margin-bottom: 1rem;">
					<label style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 0.25rem;">Car</label>
					<select name="car_id" required style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem;">
						<option value="">Select Car</option>
						<?php foreach ($availableCars as $c): ?>
							<option value="<?php echo $c['car_id']; ?>"><?php echo htmlspecialchars($c['brand'] . ' ' . $c['model']); ?> - $<?php echo $c['daily_price']; ?>/day</option>
						<?php endforeach; ?>
					</select>
				</div>
				<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
					<div>
						<label style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 0.25rem;">Start Date</label>
						<input type="date" name="start_date" required style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem;">
					</div>
					<div>
						<label style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 0.25rem;">End Date</label>
						<input type="date" name="end_date" required style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem;">
					</div>
				</div>
				<div style="margin-bottom: 1.5rem;">
					<label style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 0.25rem;">Total Price Override ($)</label>
					<input type="number" name="total_price" step="0.01" required style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem;" placeholder="Calculate manually for now">
				</div>

				<div style="display: flex; gap: 1rem; justify-content: flex-end;">
					<button type="button" onclick="document.getElementById('addRentalModal').style.display='none'" style="padding: 0.5rem 1rem; border: 1px solid #d1d5db; background: white; border-radius: 0.375rem; cursor: pointer;">Cancel</button>
					<button type="submit" style="padding: 0.5rem 1rem; background: #1f2937; color: white; border: none; border-radius: 0.375rem; cursor: pointer;">Create Rental</button>
				</div>
			</form>
		</div>
	</div>

	<!-- Edit Rental Modal -->
	<div id="editRentalModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); align-items: center; justify-content: center; z-index: 1000;">
		<div style="background: white; padding: 2rem; border-radius: 1rem; width: 100%; max-width: 500px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1);">
			<header style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
				<h2 style="font-size: 1.5rem; font-weight: 600;">Edit Rental</h2>
				<button onclick="document.getElementById('editRentalModal').style.display='none'" style="background: none; border: none; cursor: pointer; color: #6b7280;">
					<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
						<path d="M18 6L6 18M6 6l12 12"></path>
					</svg>
				</button>
			</header>

			<form method="POST" action="?page=admin_rentals">
				<input type="hidden" name="action" value="update_rental">
				<input type="hidden" name="rental_id" id="edit_rental_id">

				<div style="margin-bottom: 1rem;">
					<label style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 0.25rem;">Car</label>
					<select name="car_id" id="edit_rental_car" required style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem;">
						<?php foreach ($allCars as $c): ?>
							<option value="<?php echo $c['car_id']; ?>"><?php echo htmlspecialchars($c['brand'] . ' ' . $c['model']); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
					<div>
						<label style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 0.25rem;">Start Date</label>
						<input type="date" name="start_date" id="edit_rental_start" required style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem;">
					</div>
					<div>
						<label style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 0.25rem;">End Date</label>
						<input type="date" name="end_date" id="edit_rental_end" required style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem;">
					</div>
				</div>
				<div style="margin-bottom: 1.5rem;">
					<label style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 0.25rem;">Total Price ($)</label>
					<input type="number" name="total_price" id="edit_rental_price" step="0.01" required style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem;">
				</div>

				<div style="display: flex; gap: 1rem; justify-content: flex-end;">
					<button type="button" onclick="document.getElementById('editRentalModal').style.display='none'" style="padding: 0.5rem 1rem; border: 1px solid #d1d5db; background: white; border-radius: 0.375rem; cursor: pointer;">Cancel</button>
					<button type="submit" style="padding: 0.5rem 1rem; background: #1f2937; color: white; border: none; border-radius: 0.375rem; cursor: pointer;">Save Changes</button>
				</div>
			</form>
		</div>
	</div>

	<script>
		function openEditRentalModal(rental) {
			document.getElementById('edit_rental_id').value = rental.rental_id;
			document.getElementById('edit_rental_car').value = rental.car_id;
			document.getElementById('edit_rental_start').value = rental.start_date.split(' ')[0];
			document.getElementById('edit_rental_end').value = rental.end_date.split(' ')[0];
			document.getElementById('edit_rental_price').value = rental.total_price;
			document.getElementById('editRentalModal').style.display = 'flex';
		}

		window.onclick = function(event) {
			const modals = ['addStaffModal', 'editStaffModal', 'addCarModal', 'editCarModal', 'addClientModal', 'editClientModal', 'addRentalModal', 'editRentalModal'];
			modals.forEach(id => {
				const modal = document.getElementById(id);
				if (modal && event.target == modal) {
					modal.style.display = 'none';
				}
			});
		}
	</script>

<?php
	}


	function handleAddClient()
	{
		global $pdo;
		if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $_POST['action'] !== 'add_client') return null;

		$firstName = sanitize($_POST['first_name']);
		$lastName = sanitize($_POST['last_name']);
		$email = sanitize($_POST['email']);
		$password = $_POST['password'];
		$phone = sanitize($_POST['phone']);
		$license = sanitize($_POST['driver_license']);

		if (empty($firstName) || empty($lastName) || empty($email) || empty($password) || empty($license)) {
			return ['error' => 'All fields are required.'];
		}

		try {
			$hash = password_hash($password, PASSWORD_DEFAULT);
			$stmt = $pdo->prepare("INSERT INTO clients (first_name, last_name, email, password_hash, phone, driver_license) VALUES (?, ?, ?, ?, ?, ?)");
			$stmt->execute([$firstName, $lastName, $email, $hash, $phone, $license]);
			return ['success' => 'Client added successfully.'];
		} catch (PDOException $e) {
			if ($e->getCode() == 23000) return ['error' => 'Email or Driver License already exists.'];
			return ['error' => 'Failed to add client.'];
		}
	}

	function handleUpdateClient()
	{
		global $pdo;
		if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $_POST['action'] !== 'update_client') return null;

		$clientId = intval($_POST['client_id']);
		$firstName = sanitize($_POST['first_name']);
		$lastName = sanitize($_POST['last_name']);
		$email = sanitize($_POST['email']);
		$phone = sanitize($_POST['phone']);
		$license = sanitize($_POST['driver_license']);

		if (empty($firstName) || empty($lastName) || empty($email) || empty($license)) {
			return ['error' => 'All fields are required.'];
		}

		try {
			$stmt = $pdo->prepare("UPDATE clients SET first_name = ?, last_name = ?, email = ?, phone = ?, driver_license = ? WHERE client_id = ?");
			$stmt->execute([$firstName, $lastName, $email, $phone, $license, $clientId]);
			return ['success' => 'Client updated successfully.'];
		} catch (PDOException $e) {
			if ($e->getCode() == 23000) return ['error' => 'Email or Driver License already exists.'];
			return ['error' => 'Failed to update client.'];
		}
	}

	function handleCreateMaintenance()
	{
		global $pdo;
		if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $_POST['action'] !== 'create_maintenance') return null;

		$carId = intval($_POST['car_id']);
		$description = sanitize($_POST['description']);
		$cost = floatval($_POST['cost']);
		$date = sanitize($_POST['maintenance_date']);
		$setMaintenance = isset($_POST['set_maintenance']) ? true : false;
		$staffId = $_SESSION['user_id'];
		$agencyId = $_SESSION['user_agency_id'];

		if (empty($carId) || empty($description) || empty($date)) {
			return ['error' => 'All fields are required.'];
		}

		// Verify car ownership
		$check = $pdo->prepare("SELECT agency_id FROM cars WHERE car_id = ?");
		$check->execute([$carId]);
		$car = $check->fetch();
		if (!$car || $car['agency_id'] != $agencyId) return ['error' => 'Unauthorized or car not found.'];

		try {
			$stmt = $pdo->prepare("INSERT INTO maintenance (car_id, staff_id, description, cost, maintenance_date) VALUES (?, ?, ?, ?, ?)");
			$stmt->execute([$carId, $staffId, $description, $cost, $date]);

			if ($setMaintenance) {
				$pdo->prepare("UPDATE cars SET status = 'maintenance' WHERE car_id = ?")->execute([$carId]);
			}

			return ['success' => 'Maintenance record logged successfully.'];
		} catch (PDOException $e) {
			return ['error' => 'Failed to log maintenance.'];
		}
	}

	function handleUpdateMaintenance()
	{
		global $pdo;
		if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $_POST['action'] !== 'update_maintenance') return null;

		$id = intval($_POST['maintenance_id']);
		$description = sanitize($_POST['description']);
		$cost = floatval($_POST['cost']);
		$date = sanitize($_POST['maintenance_date']);
		$agencyId = $_SESSION['user_agency_id'];

		if (empty($id) || empty($description) || empty($date)) {
			return ['error' => 'All fields are required.'];
		}

		// Verify ownership via car
		$check = $pdo->prepare("SELECT c.agency_id FROM maintenance m JOIN cars c ON m.car_id = c.car_id WHERE m.maintenance_id = ?");
		$check->execute([$id]);
		$record = $check->fetch();
		if (!$record || $record['agency_id'] != $agencyId) return ['error' => 'Unauthorized'];

		try {
			$stmt = $pdo->prepare("UPDATE maintenance SET description = ?, cost = ?, maintenance_date = ? WHERE maintenance_id = ?");
			$stmt->execute([$description, $cost, $date, $id]);
			return ['success' => 'Maintenance record updated successfully.'];
		} catch (PDOException $e) {
			return ['error' => 'Failed to update maintenance.'];
		}
	}

	function handleDeleteMaintenance()
	{
		global $pdo;
		if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $_POST['action'] !== 'delete_maintenance') return null;

		$id = intval($_POST['maintenance_id']);
		$agencyId = $_SESSION['user_agency_id'];

		// Verify ownership
		$check = $pdo->prepare("SELECT c.agency_id FROM maintenance m JOIN cars c ON m.car_id = c.car_id WHERE m.maintenance_id = ?");
		$check->execute([$id]);
		$record = $check->fetch();
		if (!$record || $record['agency_id'] != $agencyId) return ['error' => 'Unauthorized'];

		try {
			$pdo->prepare("DELETE FROM maintenance WHERE maintenance_id = ?")->execute([$id]);
			return ['success' => 'Maintenance record deleted successfully.'];
		} catch (PDOException $e) {
			return ['error' => 'Failed to delete maintenance.'];
		}
	}

	function handleFinishMaintenance()
	{
		global $pdo;
		if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['finish_maintenance'])) return null;

		$carId = intval($_POST['car_id']);
		$agencyId = $_SESSION['user_agency_id'];

		// Verify ownership
		$check = $pdo->prepare("SELECT agency_id FROM cars WHERE car_id = ?");
		$check->execute([$carId]);
		$car = $check->fetch();
		if (!$car || $car['agency_id'] != $agencyId) return ['error' => 'Unauthorized'];

		try {
			$pdo->prepare("UPDATE cars SET status = 'available' WHERE car_id = ?")->execute([$carId]);
			return ['success' => 'Car status updated to Available.'];
		} catch (PDOException $e) {
			return ['error' => 'Failed to update car status.'];
		}
	}

	function renderAdminMaintenance()
	{
		if (!isAdmin()) {
			header('Location: himihiba.php?page=auth');
			exit;
		}
		global $pdo;
		$agencyId = $_SESSION['user_agency_id'];

		$result = handleCreateMaintenance();
		if (!$result) $result = handleUpdateMaintenance();
		if (!$result) $result = handleDeleteMaintenance();
		if (!$result) $result = handleFinishMaintenance();

		$stmt = $pdo->prepare("
			SELECT m.*, c.brand, c.model, c.license_plate, c.status as car_status, s.first_name, s.last_name 
			FROM maintenance m 
			JOIN cars c ON m.car_id = c.car_id 
			LEFT JOIN staff s ON m.staff_id = s.staff_id 
			WHERE c.agency_id = ?
			ORDER BY m.maintenance_date DESC
		");
		$stmt->execute([$agencyId]);
		$maintenance = $stmt->fetchAll(PDO::FETCH_ASSOC);

		$stmtCars = $pdo->prepare("SELECT * FROM cars WHERE agency_id = ? ORDER BY brand ASC");
		$stmtCars->execute([$agencyId]);
		$allCars = $stmtCars->fetchAll(PDO::FETCH_ASSOC);
?>
	<div class="admin-layout" style="display: flex; min-height: 100vh; background: #f3f4f6;">
		<aside class="admin-sidebar" style="width: 250px; background: white; border-right: 1px solid #e5e7eb; position: fixed; height: 100%; overflow-y: auto;">
			<div style="padding: 1.5rem; border-bottom: 1px solid #e5e7eb;">
				<h2 style="font-size: 1.5rem; color: #1f2937;">LUXDRIVE <span style="font-size: 0.8rem; color: #6b7280; display: block; font-family: 'Inter', sans-serif; margin-top: 0.25rem;">Admin Panel</span></h2>
			</div>
			<nav style="padding: 1rem;">
				<ul style="list-style: none;">
					<li><a href="?page=admin_dashboard" class="admin-nav-link" style="display: block; padding: 0.75rem 1rem; color: #4b5563; text-decoration: none; border-radius: 0.5rem; margin-bottom: 0.5rem; transition: all 0.2s;">Dashboard</a></li>
					<li><a href="?page=admin_rentals" class="admin-nav-link" style="display: block; padding: 0.75rem 1rem; color: #4b5563; text-decoration: none; border-radius: 0.5rem; margin-bottom: 0.5rem; transition: all 0.2s;">Rentals</a></li>
					<li><a href="?page=admin_cars" class="admin-nav-link" style="display: block; padding: 0.75rem 1rem; color: #4b5563; text-decoration: none; border-radius: 0.5rem; margin-bottom: 0.5rem; transition: all 0.2s;">Fleet Management</a></li>
					<li><a href="?page=admin_maintenance" class="admin-nav-link active" style="display: block; padding: 0.75rem 1rem; color: #1f2937; text-decoration: none; border-radius: 0.5rem; margin-bottom: 0.5rem; background: #f3f4f6; font-weight: 500;">Maintenance</a></li>
					<li><a href="?page=admin_staff" class="admin-nav-link" style="display: block; padding: 0.75rem 1rem; color: #4b5563; text-decoration: none; border-radius: 0.5rem; margin-bottom: 0.5rem; transition: all 0.2s;">Staff Management</a></li>
					<li><a href="?page=admin_clients" class="admin-nav-link" style="display: block; padding: 0.75rem 1rem; color: #4b5563; text-decoration: none; border-radius: 0.5rem; margin-bottom: 0.5rem; transition: all 0.2s;">Clients</a></li>
					<li><a href="?page=admin_reports" class="admin-nav-link" style="display: block; padding: 0.75rem 1rem; color: #4b5563; text-decoration: none; border-radius: 0.5rem; margin-bottom: 0.5rem; transition: all 0.2s;">Reports</a></li>
					<li style="margin-top: 2rem; border-top: 1px solid #e5e7eb; padding-top: 1rem;"><a href="?page=logout" style="display: block; padding: 0.75rem 1rem; color: #ef4444; text-decoration: none; border-radius: 0.5rem;">Logout</a></li>
				</ul>
			</nav>
		</aside>

		<main class="admin-main" style="flex: 1; margin-left: 250px; padding: 2rem;">
			<header style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
				<div>
					<h1 style="font-size: 1.875rem; font-weight: 600; color: #111827;">Maintenance</h1>
					<p style="color: #6b7280;">Manage vehicle repairs and servicing.</p>
				</div>
				<button onclick="document.getElementById('addMaintenanceModal').style.display='flex'" style="background: #1f2937; color: white; padding: 0.75rem 1.5rem; border: none; border-radius: 0.5rem; cursor: pointer; display: flex; align-items: center; gap: 0.5rem;">
					<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
						<line x1="12" y1="5" x2="12" y2="19"></line>
						<line x1="5" y1="12" x2="19" y2="12"></line>
					</svg>
					Log Maintenance
				</button>
			</header>

			<?php if ($result && isset($result['success'])): ?>
				<div style="background: #dcfce7; color: #16a34a; padding: 1rem; border-radius: 8px; margin-bottom: 2rem; border: 1px solid #bbf7d0;"><?php echo htmlspecialchars($result['success']); ?></div>
			<?php endif; ?>
			<?php if ($result && isset($result['error'])): ?>
				<div style="background: #fee2e2; color: #dc2626; padding: 1rem; border-radius: 8px; margin-bottom: 2rem; border: 1px solid #fecaca;"><?php echo htmlspecialchars($result['error']); ?></div>
			<?php endif; ?>

			<div style="background: white; border-radius: 0.75rem; border: 1px solid #e5e7eb; overflow: hidden;">
				<table style="width: 100%; border-collapse: collapse; text-align: left;">
					<thead style="background: #f9fafb; border-bottom: 1px solid #e5e7eb;">
						<tr>
							<th style="padding: 1rem; font-weight: 600; color: #4b5563;">Date</th>
							<th style="padding: 1rem; font-weight: 600; color: #4b5563;">Car</th>
							<th style="padding: 1rem; font-weight: 600; color: #4b5563;">Description</th>
							<th style="padding: 1rem; font-weight: 600; color: #4b5563;">Cost</th>
							<th style="padding: 1rem; font-weight: 600; color: #4b5563;">Logged By</th>
							<th style="padding: 1rem; font-weight: 600; color: #4b5563;">Actions</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($maintenance as $record): ?>
							<tr style="border-bottom: 1px solid #e5e7eb;">
								<td style="padding: 1rem; color: #6b7280;"><?php echo date('M d, Y', strtotime($record['maintenance_date'])); ?></td>
								<td style="padding: 1rem;">
									<div style="font-weight: 500; color: #111827;"><?php echo htmlspecialchars($record['brand'] . ' ' . $record['model']); ?></div>
									<div style="font-size: 0.75rem; color: #6b7280; font-family: monospace;"><?php echo htmlspecialchars($record['license_plate']); ?></div>
									<?php if ($record['car_status'] === 'maintenance'): ?>
										<span style="font-size: 0.7rem; background: #fee2e2; color: #dc2626; padding: 2px 6px; border-radius: 4px;">In Maintenance</span>
									<?php endif; ?>
								</td>
								<td style="padding: 1rem; color: #4b5563; max-width: 300px;"><?php echo htmlspecialchars($record['description']); ?></td>
								<td style="padding: 1rem; font-weight: 500;">$<?php echo number_format($record['cost'], 2); ?></td>
								<td style="padding: 1rem; color: #6b7280;"><?php echo htmlspecialchars($record['first_name'] . ' ' . $record['last_name']); ?></td>
								<td style="padding: 1rem;">
									<div style="display: flex; gap: 0.5rem;">
										<button onclick="openEditMaintenanceModal(<?php echo htmlspecialchars(json_encode($record)); ?>)" style="padding: 0.25rem 0.5rem; border: 1px solid #d1d5db; border-radius: 0.25rem; background: white; cursor: pointer; color: #4b5563;">Edit</button>
										<?php if ($record['car_status'] === 'maintenance'): ?>
											<form method="POST" action="?page=admin_maintenance" style="display: inline;">
												<input type="hidden" name="finish_maintenance" value="1">
												<input type="hidden" name="car_id" value="<?php echo $record['car_id']; ?>">
												<button type="submit" title="Mark as Fixed & Available" style="padding: 0.25rem 0.5rem; border: 1px solid #bbf7d0; border-radius: 0.25rem; background: #f0fdf4; cursor: pointer; color: #166534;">Finish</button>
											</form>
										<?php endif; ?>
										<form method="POST" action="?page=admin_maintenance" onsubmit="return confirm('Delete this record?');" style="display: inline;">
											<input type="hidden" name="action" value="delete_maintenance">
											<input type="hidden" name="maintenance_id" value="<?php echo $record['maintenance_id']; ?>">
											<button type="submit" style="padding: 0.25rem 0.5rem; border: 1px solid #fee2e2; border-radius: 0.25rem; background: #fef2f2; cursor: pointer; color: #dc2626;">Delete</button>
										</form>
									</div>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</main>
	</div>

	<!-- Add Maintenance Modal -->
	<div id="addMaintenanceModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); align-items: center; justify-content: center; z-index: 1000;">
		<div style="background: white; padding: 2rem; border-radius: 1rem; width: 100%; max-width: 500px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1);">
			<header style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
				<h2 style="font-size: 1.5rem; font-weight: 600;">Log Maintenance</h2>
				<button onclick="document.getElementById('addMaintenanceModal').style.display='none'" style="background: none; border: none; cursor: pointer; color: #6b7280;">
					<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
						<path d="M18 6L6 18M6 6l12 12"></path>
					</svg>
				</button>
			</header>

			<form method="POST" action="?page=admin_maintenance">
				<input type="hidden" name="action" value="create_maintenance">
				<div style="margin-bottom: 1rem;">
					<label style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 0.25rem;">Vehicle</label>
					<select name="car_id" required style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem;">
						<option value="">Select Car</option>
						<?php foreach ($allCars as $car): ?>
							<option value="<?php echo $car['car_id']; ?>"><?php echo htmlspecialchars($car['brand'] . ' ' . $car['model'] . ' (' . $car['license_plate'] . ')'); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div style="margin-bottom: 1rem;">
					<label style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 0.25rem;">Description</label>
					<textarea name="description" required rows="3" style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem;"></textarea>
				</div>
				<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
					<div>
						<label style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 0.25rem;">Cost ($)</label>
						<input type="number" step="0.01" name="cost" value="0.00" style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem;">
					</div>
					<div>
						<label style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 0.25rem;">Date</label>
						<input type="date" name="maintenance_date" value="<?php echo date('Y-m-d'); ?>" required style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem;">
					</div>
				</div>
				<div style="margin-bottom: 1.5rem;">
					<label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
						<input type="checkbox" name="set_maintenance" checked>
						<span style="font-size: 0.875rem; color: #374151;">Set car status to "Maintenance"</span>
					</label>
				</div>
				<div style="display: flex; gap: 1rem; justify-content: flex-end;">
					<button type="button" onclick="document.getElementById('addMaintenanceModal').style.display='none'" style="padding: 0.5rem 1rem; border: 1px solid #d1d5db; background: white; border-radius: 0.375rem; cursor: pointer;">Cancel</button>
					<button type="submit" style="padding: 0.5rem 1rem; background: #1f2937; color: white; border: none; border-radius: 0.375rem; cursor: pointer;">Log Maintenance</button>
				</div>
			</form>
		</div>
	</div>

	<!-- Edit Maintenance Modal -->
	<div id="editMaintenanceModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); align-items: center; justify-content: center; z-index: 1000;">
		<div style="background: white; padding: 2rem; border-radius: 1rem; width: 100%; max-width: 500px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1);">
			<header style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
				<h2 style="font-size: 1.5rem; font-weight: 600;">Edit Record</h2>
				<button onclick="document.getElementById('editMaintenanceModal').style.display='none'" style="background: none; border: none; cursor: pointer; color: #6b7280;">
					<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
						<path d="M18 6L6 18M6 6l12 12"></path>
					</svg>
				</button>
			</header>

			<form method="POST" action="?page=admin_maintenance">
				<input type="hidden" name="action" value="update_maintenance">
				<input type="hidden" name="maintenance_id" id="edit_maintenance_id">

				<div style="margin-bottom: 1rem;">
					<label style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 0.25rem;">Description</label>
					<textarea name="description" id="edit_description" required rows="3" style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem;"></textarea>
				</div>
				<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
					<div>
						<label style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 0.25rem;">Cost ($)</label>
						<input type="number" step="0.01" name="cost" id="edit_cost" required style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem;">
					</div>
					<div>
						<label style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 0.25rem;">Date</label>
						<input type="date" name="maintenance_date" id="edit_date" required style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem;">
					</div>
				</div>

				<div style="display: flex; gap: 1rem; justify-content: flex-end;">
					<button type="button" onclick="document.getElementById('editMaintenanceModal').style.display='none'" style="padding: 0.5rem 1rem; border: 1px solid #d1d5db; background: white; border-radius: 0.375rem; cursor: pointer;">Cancel</button>
					<button type="submit" style="padding: 0.5rem 1rem; background: #1f2937; color: white; border: none; border-radius: 0.375rem; cursor: pointer;">Save Changes</button>
				</div>
			</form>
		</div>
	</div>

	<script>
		function openEditMaintenanceModal(record) {
			document.getElementById('edit_maintenance_id').value = record.maintenance_id;
			document.getElementById('edit_description').value = record.description;
			document.getElementById('edit_cost').value = record.cost;
			document.getElementById('edit_date').value = record.maintenance_date;
			document.getElementById('editMaintenanceModal').style.display = 'flex';
		}
		window.onclick = function(event) {
			if (event.target == document.getElementById('addMaintenanceModal')) {
				document.getElementById('addMaintenanceModal').style.display = 'none';
			}
			if (event.target == document.getElementById('editMaintenanceModal')) {
				document.getElementById('editMaintenanceModal').style.display = 'none';
			}
		}
	</script>
<?php
	}

	function handleDeleteClient()
	{
		global $pdo;
		if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $_POST['action'] !== 'delete_client') return null;

		$clientId = intval($_POST['client_id']);

		try {
			$stmt = $pdo->prepare("DELETE FROM clients WHERE client_id = ?");
			$stmt->execute([$clientId]);
			return ['success' => 'Client deleted successfully.'];
		} catch (PDOException $e) {
			return ['error' => 'Failed to delete client.'];
		}
	}

	function renderAdminClients()
	{
		if (!isAdmin()) {
			header('Location: himihiba.php?page=auth');
			exit;
		}
		global $pdo;

		$result = handleAddClient();
		if (!$result) $result = handleUpdateClient();
		if (!$result) $result = handleDeleteClient();

		$clients = $pdo->query("SELECT * FROM clients ORDER BY registration_date DESC")->fetchAll(PDO::FETCH_ASSOC);
?>
	<div class="admin-layout" style="display: flex; min-height: 100vh; background: #f3f4f6;">
		<!-- Sidebar (Reused) -->
		<aside class="admin-sidebar" style="width: 250px; background: white; border-right: 1px solid #e5e7eb; position: fixed; height: 100%; overflow-y: auto;">
			<div style="padding: 1.5rem; border-bottom: 1px solid #e5e7eb;">
				<h2 style="font-size: 1.5rem; color: #1f2937;">LUXDRIVE <span style="font-size: 0.8rem; color: #6b7280; display: block; font-family: 'Inter', sans-serif; margin-top: 0.25rem;">Admin Panel</span></h2>
			</div>
			<nav style="padding: 1rem;">
				<ul style="list-style: none;">
					<li><a href="?page=admin_dashboard" class="admin-nav-link" style="display: block; padding: 0.75rem 1rem; color: #4b5563; text-decoration: none; border-radius: 0.5rem; margin-bottom: 0.5rem; transition: all 0.2s;">Dashboard</a></li>
					<li><a href="?page=admin_rentals" class="admin-nav-link" style="display: block; padding: 0.75rem 1rem; color: #4b5563; text-decoration: none; border-radius: 0.5rem; margin-bottom: 0.5rem; transition: all 0.2s;">Rentals</a></li>
					<li><a href="?page=admin_cars" class="admin-nav-link" style="display: block; padding: 0.75rem 1rem; color: #4b5563; text-decoration: none; border-radius: 0.5rem; margin-bottom: 0.5rem; transition: all 0.2s;">Fleet Management</a></li>
					<li><a href="?page=admin_maintenance" class="admin-nav-link" style="display: block; padding: 0.75rem 1rem; color: #4b5563; text-decoration: none; border-radius: 0.5rem; margin-bottom: 0.5rem; transition: all 0.2s;">Maintenance</a></li>
					<li><a href="?page=admin_staff" class="admin-nav-link" style="display: block; padding: 0.75rem 1rem; color: #4b5563; text-decoration: none; border-radius: 0.5rem; margin-bottom: 0.5rem; transition: all 0.2s;">Staff Management</a></li>
					<li><a href="?page=admin_clients" class="admin-nav-link active" style="display: block; padding: 0.75rem 1rem; color: #1f2937; text-decoration: none; border-radius: 0.5rem; margin-bottom: 0.5rem; background: #f3f4f6; font-weight: 500;">Clients</a></li>
					<li><a href="?page=admin_reports" class="admin-nav-link" style="display: block; padding: 0.75rem 1rem; color: #4b5563; text-decoration: none; border-radius: 0.5rem; margin-bottom: 0.5rem; transition: all 0.2s;">Reports</a></li>
					<li style="margin-top: 2rem; border-top: 1px solid #e5e7eb; padding-top: 1rem;"><a href="?page=logout" style="display: block; padding: 0.75rem 1rem; color: #ef4444; text-decoration: none; border-radius: 0.5rem;">Logout</a></li>
				</ul>
			</nav>
		</aside>

		<main class="admin-main" style="flex: 1; margin-left: 250px; padding: 2rem;">
			<header style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
				<div>
					<h1 style="font-size: 1.875rem; font-weight: 600; color: #111827;">Client Management</h1>
					<p style="color: #6b7280;">Manage your customer base.</p>
				</div>
				<button onclick="document.getElementById('addClientModal').style.display='flex'" style="background: #1f2937; color: white; padding: 0.75rem 1.5rem; border: none; border-radius: 0.5rem; cursor: pointer; display: flex; align-items: center; gap: 0.5rem;">
					<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
						<line x1="12" y1="5" x2="12" y2="19"></line>
						<line x1="5" y1="12" x2="19" y2="12"></line>
					</svg>
					Add Client
				</button>
			</header>

			<?php if ($result && isset($result['success'])): ?>
				<div style="background: #dcfce7; color: #16a34a; padding: 1rem; border-radius: 8px; margin-bottom: 2rem; border: 1px solid #bbf7d0;"><?php echo htmlspecialchars($result['success']); ?></div>
			<?php endif; ?>
			<?php if ($result && isset($result['error'])): ?>
				<div style="background: #fee2e2; color: #dc2626; padding: 1rem; border-radius: 8px; margin-bottom: 2rem; border: 1px solid #fecaca;"><?php echo htmlspecialchars($result['error']); ?></div>
			<?php endif; ?>

			<div style="background: white; border-radius: 0.75rem; border: 1px solid #e5e7eb; overflow: hidden;">
				<table style="width: 100%; border-collapse: collapse; text-align: left;">
					<thead style="background: #f9fafb; border-bottom: 1px solid #e5e7eb;">
						<tr>
							<th style="padding: 1rem; font-weight: 600; color: #4b5563;">Name</th>
							<th style="padding: 1rem; font-weight: 600; color: #4b5563;">Email</th>
							<th style="padding: 1rem; font-weight: 600; color: #4b5563;">Phone</th>
							<th style="padding: 1rem; font-weight: 600; color: #4b5563;">License</th>
							<th style="padding: 1rem; font-weight: 600; color: #4b5563;">Joined</th>
							<th style="padding: 1rem; font-weight: 600; color: #4b5563;">Actions</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($clients as $client): ?>
							<tr style="border-bottom: 1px solid #e5e7eb;">
								<td style="padding: 1rem;">
									<div style="display: flex; align-items: center; gap: 0.75rem;">
										<div style="width: 32px; height: 32px; background: #e5e7eb; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 600; color: #6b7280;">
											<?php echo strtoupper(substr($client['first_name'], 0, 1)); ?>
										</div>
										<span style="font-weight: 500; color: #111827;"><?php echo htmlspecialchars($client['first_name'] . ' ' . $client['last_name']); ?></span>
									</div>
								</td>
								<td style="padding: 1rem; color: #6b7280;"><?php echo htmlspecialchars($client['email']); ?></td>
								<td style="padding: 1rem; color: #6b7280;"><?php echo htmlspecialchars($client['phone'] ?? 'N/A'); ?></td>
								<td style="padding: 1rem; color: #6b7280;"><?php echo htmlspecialchars($client['driver_license']); ?></td>
								<td style="padding: 1rem; color: #6b7280;"><?php echo date('M d, Y', strtotime($client['registration_date'])); ?></td>
								<td style="padding: 1rem;">
									<div style="display: flex; gap: 0.5rem;">
										<button onclick="openEditClientModal(<?php echo htmlspecialchars(json_encode($client)); ?>)" style="padding: 0.25rem 0.5rem; border: 1px solid #d1d5db; border-radius: 0.25rem; background: white; cursor: pointer; color: #4b5563;">Edit</button>
										<form method="POST" action="?page=admin_clients" onsubmit="return confirm('Are you sure you want to delete this client?');" style="display: inline;">
											<input type="hidden" name="action" value="delete_client">
											<input type="hidden" name="client_id" value="<?php echo $client['client_id']; ?>">
											<button type="submit" style="padding: 0.25rem 0.5rem; border: 1px solid #fee2e2; border-radius: 0.25rem; background: #fef2f2; cursor: pointer; color: #dc2626;">Delete</button>
										</form>
									</div>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</main>
	</div>

	<!-- Add Client Modal -->
	<div id="addClientModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); align-items: center; justify-content: center; z-index: 1000;">
		<div style="background: white; padding: 2rem; border-radius: 1rem; width: 100%; max-width: 500px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1);">
			<header style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
				<h2 style="font-size: 1.5rem; font-weight: 600;">Add New Client</h2>
				<button onclick="document.getElementById('addClientModal').style.display='none'" style="background: none; border: none; cursor: pointer; color: #6b7280;">
					<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
						<path d="M18 6L6 18M6 6l12 12"></path>
					</svg>
				</button>
			</header>

			<form method="POST" action="?page=admin_clients">
				<input type="hidden" name="action" value="add_client">
				<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
					<div>
						<label style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 0.25rem;">First Name</label>
						<input type="text" name="first_name" required style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem;">
					</div>
					<div>
						<label style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 0.25rem;">Last Name</label>
						<input type="text" name="last_name" required style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem;">
					</div>
				</div>
				<div style="margin-bottom: 1rem;">
					<label style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 0.25rem;">Email Address</label>
					<input type="email" name="email" required style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem;">
				</div>
				<div style="margin-bottom: 1rem;">
					<label style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 0.25rem;">Phone</label>
					<input type="tel" name="phone" style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem;">
				</div>
				<div style="margin-bottom: 1rem;">
					<label style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 0.25rem;">Driver License</label>
					<input type="text" name="driver_license" required style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem;">
				</div>
				<div style="margin-bottom: 1.5rem;">
					<label style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 0.25rem;">Password</label>
					<input type="password" name="password" required style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem;">
				</div>

				<div style="display: flex; gap: 1rem; justify-content: flex-end;">
					<button type="button" onclick="document.getElementById('addClientModal').style.display='none'" style="padding: 0.5rem 1rem; border: 1px solid #d1d5db; background: white; border-radius: 0.375rem; cursor: pointer;">Cancel</button>
					<button type="submit" style="padding: 0.5rem 1rem; background: #1f2937; color: white; border: none; border-radius: 0.375rem; cursor: pointer;">Add Client</button>
				</div>
			</form>
		</div>
	</div>

	<!-- Edit Client Modal -->
	<div id="editClientModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); align-items: center; justify-content: center; z-index: 1000;">
		<div style="background: white; padding: 2rem; border-radius: 1rem; width: 100%; max-width: 500px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1);">
			<header style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
				<h2 style="font-size: 1.5rem; font-weight: 600;">Edit Client</h2>
				<button onclick="document.getElementById('editClientModal').style.display='none'" style="background: none; border: none; cursor: pointer; color: #6b7280;">
					<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
						<path d="M18 6L6 18M6 6l12 12"></path>
					</svg>
				</button>
			</header>

			<form method="POST" action="?page=admin_clients" id="editClientForm">
				<input type="hidden" name="action" value="update_client">
				<input type="hidden" name="client_id" id="edit_client_id">
				<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
					<div>
						<label style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 0.25rem;">First Name</label>
						<input type="text" name="first_name" id="edit_first_name" required style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem;">
					</div>
					<div>
						<label style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 0.25rem;">Last Name</label>
						<input type="text" name="last_name" id="edit_last_name" required style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem;">
					</div>
				</div>
				<div style="margin-bottom: 1rem;">
					<label style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 0.25rem;">Email Address</label>
					<input type="email" name="email" id="edit_email" required style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem;">
				</div>
				<div style="margin-bottom: 1rem;">
					<label style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 0.25rem;">Phone</label>
					<input type="tel" name="phone" id="edit_phone" style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem;">
				</div>
				<div style="margin-bottom: 1.5rem;">
					<label style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 0.25rem;">Driver License</label>
					<input type="text" name="driver_license" id="edit_driver_license" required style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem;">
				</div>

				<div style="display: flex; gap: 1rem; justify-content: flex-end;">
					<button type="button" onclick="document.getElementById('editClientModal').style.display='none'" style="padding: 0.5rem 1rem; border: 1px solid #d1d5db; background: white; border-radius: 0.375rem; cursor: pointer;">Cancel</button>
					<button type="submit" style="padding: 0.5rem 1rem; background: #1f2937; color: white; border: none; border-radius: 0.375rem; cursor: pointer;">Save Changes</button>
				</div>
			</form>
		</div>
	</div>

	<script>
		function openEditClientModal(client) {
			document.getElementById('edit_client_id').value = client.client_id;
			document.getElementById('edit_first_name').value = client.first_name;
			document.getElementById('edit_last_name').value = client.last_name;
			document.getElementById('edit_email').value = client.email;
			document.getElementById('edit_phone').value = client.phone || '';
			document.getElementById('edit_driver_license').value = client.driver_license;
			document.getElementById('editClientModal').style.display = 'flex';
		}

		// Close modal if clicked outside
		window.onclick = function(event) {
				if (event.target == document.getElementById('addClientModal')) {
					document.getElementById('addClientModal').style.display = 'none';
				}
				if (event.target == document.getElementById('editClientModal')) {
					document.getElementById('editClientModal').style.display = 'none';
				} <
				/div>
			<?php
		}

		function handleCreatePayment()
		{
			global $pdo;
			if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $_POST['action'] !== 'create_payment') return null;

			$rentalId = intval($_POST['rental_id']);
			$amount = floatval($_POST['amount']);
			$method = sanitize($_POST['method']);
			$date = sanitize($_POST['payment_date']);
			$status = sanitize($_POST['status']);

			if (empty($rentalId) || empty($amount) || empty($method) || empty($date)) {
				return ['error' => 'All fields are required.'];
			}

			try {
				$stmt = $pdo->prepare("INSERT INTO payments (rental_id, amount, method, payment_date, status) VALUES (?, ?, ?, ?, ?)");
				$stmt->execute([$rentalId, $amount, $method, $date, $status]);
				return ['success' => 'Payment logged successfully.'];
			} catch (PDOException $e) {
				return ['error' => 'Failed to log payment.'];
			}
		}

		function handleUpdatePayment()
		{
			global $pdo;
			if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $_POST['action'] !== 'update_payment') return null;

			$paymentId = intval($_POST['payment_id']);
			$amount = floatval($_POST['amount']);
			$method = sanitize($_POST['method']);
			$date = sanitize($_POST['payment_date']);
			$status = sanitize($_POST['status']);

			try {
				$stmt = $pdo->prepare("UPDATE payments SET amount = ?, method = ?, payment_date = ?, status = ? WHERE payment_id = ?");
				$stmt->execute([$amount, $method, $date, $status, $paymentId]);
				return ['success' => 'Payment updated successfully.'];
			} catch (PDOException $e) {
				return ['error' => 'Failed to update payment.'];
			}
		}

		function handleRefundPayment()
		{
			global $pdo;
			if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $_POST['action'] !== 'refund_payment') return null;

			$paymentId = intval($_POST['payment_id']);

			try {
				$stmt = $pdo->prepare("UPDATE payments SET status = 'refunded' WHERE payment_id = ?");
				$stmt->execute([$paymentId]);
				return ['success' => 'Payment refunded successfully.'];
			} catch (PDOException $e) {
				return ['error' => 'Failed to refund payment.'];
			}
		}

		function renderAdminReports()
		{
			if (!isAdmin()) {
				header('Location: himihiba.php?page=auth');
				exit;
			}
			global $pdo;
			$agencyId = $_SESSION['user_agency_id'];

			$result = handleCreatePayment();
			if (!$result) $result = handleUpdatePayment();
			if (!$result) $result = handleRefundPayment();

			// Stats
			$stmtRev = $pdo->prepare("SELECT COALESCE(SUM(p.amount), 0) FROM payments p JOIN rentals r ON p.rental_id = r.rental_id WHERE p.status='paid' AND r.agency_id = ?");
			$stmtRev->execute([$agencyId]);
			$totalRevenue = $stmtRev->fetchColumn();

			$stmtPending = $pdo->prepare("SELECT COALESCE(SUM(p.amount), 0) FROM payments p JOIN rentals r ON p.rental_id = r.rental_id WHERE p.status='pending' AND r.agency_id = ?");
			$stmtPending->execute([$agencyId]);
			$pendingRevenue = $stmtPending->fetchColumn();

			$stmtRentals = $pdo->prepare("SELECT COUNT(*) FROM rentals WHERE agency_id = ?");
			$stmtRentals->execute([$agencyId]);
			$totalRentals = $stmtRentals->fetchColumn();

			$stmtCompleted = $pdo->prepare("SELECT COUNT(*) FROM rentals WHERE status='completed' AND agency_id = ?");
			$stmtCompleted->execute([$agencyId]);
			$completedRentals = $stmtCompleted->fetchColumn();

			$stmtOngoing = $pdo->prepare("SELECT COUNT(*) FROM rentals WHERE status='ongoing' AND agency_id = ?");
			$stmtOngoing->execute([$agencyId]);
			$ongoingRentals = $stmtOngoing->fetchColumn();

			$stmtMaint = $pdo->prepare("SELECT COALESCE(SUM(m.cost), 0) FROM maintenance m JOIN cars c ON m.car_id = c.car_id WHERE c.agency_id = ?");
			$stmtMaint->execute([$agencyId]);
			$maintenanceCost = $stmtMaint->fetchColumn();

			// New Stats: Utilization Rate
			$stmtCars = $pdo->prepare("SELECT COUNT(*) FROM cars WHERE agency_id = ?");
			$stmtCars->execute([$agencyId]);
			$totalCars = $stmtCars->fetchColumn();
			$utilizationRate = ($totalCars > 0) ? round(($ongoingRentals / $totalCars) * 100, 1) : 0;



			// New Stats: Top Cars
			$stmtTop = $pdo->prepare("
			SELECT c.brand, c.model, COUNT(r.rental_id) as rental_count 
			FROM rentals r 
			JOIN cars c ON r.car_id = c.car_id 
			WHERE r.agency_id = ?
			GROUP BY r.car_id 
			ORDER BY rental_count DESC 
			LIMIT 5
		");
			$stmtTop->execute([$agencyId]);
			$topCars = $stmtTop->fetchAll(PDO::FETCH_ASSOC);

			// All payments (Removed LIMIT)
			$stmtPayments = $pdo->prepare("
			SELECT p.*, c.first_name, c.last_name, ca.brand, ca.model, r.rental_id
			FROM payments p
			JOIN rentals r ON p.rental_id = r.rental_id
			JOIN clients c ON r.client_id = c.client_id
			JOIN cars ca ON r.car_id = ca.car_id
			WHERE r.agency_id = ?
			ORDER BY p.payment_date DESC
		");
			$stmtPayments->execute([$agencyId]);
			$recentPayments = $stmtPayments->fetchAll(PDO::FETCH_ASSOC);

			// Fetch rentals for dropdown
			$stmtDropdown = $pdo->prepare("
			SELECT r.rental_id, c.first_name, c.last_name, ca.brand, ca.model 
			FROM rentals r 
			JOIN clients c ON r.client_id = c.client_id 
			JOIN cars ca ON r.car_id = ca.car_id 
			WHERE r.agency_id = ?
			ORDER BY r.created_at DESC
		");
			$stmtDropdown->execute([$agencyId]);
			$rentals = $stmtDropdown->fetchAll(PDO::FETCH_ASSOC);
			?>
					<
					div class = "admin-layout"
				style = "display: flex; min-height: 100vh; background: #f3f4f6;" >
					<
					!--Sidebar-- >
					<
					aside class = "admin-sidebar"
				style = "width: 250px; background: white; border-right: 1px solid #e5e7eb; position: fixed; height: 100%; overflow-y: auto;" >
					<
					div style = "padding: 1.5rem; border-bottom: 1px solid #e5e7eb;" >
					<
					h2 style = "font-size: 1.5rem; color: #1f2937;" > LUXDRIVE < span style = "font-size: 0.8rem; color: #6b7280; display: block; font-family: 'Inter', sans-serif; margin-top: 0.25rem;" > Admin Panel < /span></h2 >
					<
					/div> <
					nav style = "padding: 1rem;" >
					<
					ul style = "list-style: none;" >
					<
					li > < a href = "?page=admin_dashboard"
				class = "admin-nav-link"
				style = "display: block; padding: 0.75rem 1rem; color: #4b5563; text-decoration: none; border-radius: 0.5rem; margin-bottom: 0.5rem; transition: all 0.2s;" > Dashboard < /a></li >
					<
					li > < a href = "?page=admin_rentals"
				class = "admin-nav-link"
				style = "display: block; padding: 0.75rem 1rem; color: #4b5563; text-decoration: none; border-radius: 0.5rem; margin-bottom: 0.5rem; transition: all 0.2s;" > Rentals < /a></li >
					<
					li > < a href = "?page=admin_cars"
				class = "admin-nav-link"
				style = "display: block; padding: 0.75rem 1rem; color: #4b5563; text-decoration: none; border-radius: 0.5rem; margin-bottom: 0.5rem; transition: all 0.2s;" > Fleet Management < /a></li >
					<
					li > < a href = "?page=admin_maintenance"
				class = "admin-nav-link"
				style = "display: block; padding: 0.75rem 1rem; color: #4b5563; text-decoration: none; border-radius: 0.5rem; margin-bottom: 0.5rem; transition: all 0.2s;" > Maintenance < /a></li >
					<
					li > < a href = "?page=admin_staff"
				class = "admin-nav-link"
				style = "display: block; padding: 0.75rem 1rem; color: #4b5563; text-decoration: none; border-radius: 0.5rem; margin-bottom: 0.5rem; transition: all 0.2s;" > Staff Management < /a></li >
					<
					li > < a href = "?page=admin_clients"
				class = "admin-nav-link"
				style = "display: block; padding: 0.75rem 1rem; color: #4b5563; text-decoration: none; border-radius: 0.5rem; margin-bottom: 0.5rem; transition: all 0.2s;" > Clients < /a></li >
					<
					li > < a href = "?page=admin_reports"
				class = "admin-nav-link active"
				style = "display: block; padding: 0.75rem 1rem; color: #1f2937; text-decoration: none; border-radius: 0.5rem; margin-bottom: 0.5rem; background: #f3f4f6; font-weight: 500;" > Reports < /a></li >
					<
					li style = "margin-top: 2rem; border-top: 1px solid #e5e7eb; padding-top: 1rem;" > < a href = "?page=logout"
				style = "display: block; padding: 0.75rem 1rem; color: #ef4444; text-decoration: none; border-radius: 0.5rem;" > Logout < /a></li >
					<
					/ul> <
					/nav> <
					/aside>

					<
					main class = "admin-main"
				style = "flex: 1; margin-left: 250px; padding: 2rem;" >
					<
					header style = "margin-bottom: 2rem; display: flex; justify-content: space-between; align-items: center;" >
					<
					div >
					<
					h1 style = "font-size: 1.875rem; font-weight: 600; color: #111827;" > Reports & Analytics < /h1> <
					p style = "color: #6b7280;" > Agency financial overview and statistics. < /p> <
					/div> <
					button onclick = "document.getElementById('addPaymentModal').style.display='flex'"
				style = "background: #1f2937; color: white; padding: 0.75rem 1.5rem; border: none; border-radius: 0.5rem; cursor: pointer; display: flex; align-items: center; gap: 0.5rem;" >
					<
					svg width = "20"
				height = "20"
				viewBox = "0 0 24 24"
				fill = "none"
				stroke = "currentColor"
				stroke - width = "2" >
					<
					line x1 = "12"
				y1 = "5"
				x2 = "12"
				y2 = "19" > < /line> <
					line x1 = "5"
				y1 = "12"
				x2 = "19"
				y2 = "12" > < /line> <
					/svg>
				Log Payment
					<
					/button> <
					/header>

				<?php if ($result && isset($result['success'])): ?>
						<
						div style = "background: #dcfce7; color: #16a34a; padding: 1rem; border-radius: 8px; margin-bottom: 2rem; border: 1px solid #bbf7d0;" > <?php echo htmlspecialchars($result['success']); ?> < /div>
				<?php endif; ?>
				<?php if ($result && isset($result['error'])): ?>
						<
						div style = "background: #fee2e2; color: #dc2626; padding: 1rem; border-radius: 8px; margin-bottom: 2rem; border: 1px solid #fecaca;" > <?php echo htmlspecialchars($result['error']); ?> < /div>
				<?php endif; ?>

					<
					!--Financial Overview-- >
					<
					div style = "display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;" >
					<
					div style = "background: linear-gradient(135deg, #059669, #10b981); padding: 1.5rem; border-radius: 0.75rem; color: white;" >
					<
					h3 style = "font-size: 0.875rem; opacity: 0.9; margin-bottom: 0.5rem;" > Total Revenue < /h3> <
					p style = "font-size: 1.875rem; font-weight: 700;" > $<?php echo number_format($totalRevenue, 2); ?> < /p> <
					/div> <
					div style = "background: white; padding: 1.5rem; border-radius: 0.75rem; border: 1px solid #e5e7eb;" >
					<
					h3 style = "font-size: 0.875rem; color: #6b7280; margin-bottom: 0.5rem;" > Pending Payments < /h3> <
					p style = "font-size: 1.875rem; font-weight: 700; color: #f59e0b;" > $<?php echo number_format($pendingRevenue, 2); ?> < /p> <
					/div> <
					div style = "background: white; padding: 1.5rem; border-radius: 0.75rem; border: 1px solid #e5e7eb;" >
					<
					h3 style = "font-size: 0.875rem; color: #6b7280; margin-bottom: 0.5rem;" > Net Profit < /h3>
				<?php $netProfit = $totalRevenue - $maintenanceCost; ?>
					<
					p style = "font-size: 1.875rem; font-weight: 700; color: <?php echo $netProfit >= 0 ? '#1f2937' : '#dc2626'; ?>;" > $<?php echo number_format($netProfit, 2); ?> < /p> <
					div style = "font-size: 0.875rem; color: #6b7280; margin-top: 0.5rem;" > Before Maintenance: $<?php echo number_format($totalRevenue, 2); ?> < /div> <
					/div> <
					div style = "background: white; padding: 1.5rem; border-radius: 0.75rem; border: 1px solid #e5e7eb;" >
					<
					h3 style = "font-size: 0.875rem; color: #6b7280; margin-bottom: 0.5rem;" > Utilization Rate < /h3> <
					p style = "font-size: 1.875rem; font-weight: 700; color: #3b82f6;" > <?php echo $utilizationRate; ?> % < /p> <
					div style = "width: 100%; height: 4px; background: #e5e7eb; border-radius: 2px; margin-top: 0.5rem;" >
					<
					div style = "width: <?php echo $utilizationRate; ?>%; height: 100%; background: #3b82f6; border-radius: 2px;" > < /div> <
					/div> <
					/div> <
					/div>

					<
					!--Advanced Stats Grid-- >
					<
					div style = "display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem; margin-bottom: 2rem;" >
					<
					!--Monthly Revenue Chart-- >


					<
					!--Top Cars-- >
					<
					div style = "background: white; padding: 1.5rem; border-radius: 0.75rem; border: 1px solid #e5e7eb;" >
					<
					h3 style = "font-size: 1.125rem; font-weight: 600; color: #1f2937; margin-bottom: 1rem;" > Top Performing Fleet < /h3> <
					div style = "display: flex; flex-direction: column; gap: 1rem;" >
					<?php foreach ($topCars as $index => $car): ?> <
						div style = "display: flex; align-items: center; justify-content: space-between;" >
						<
						div style = "display: flex; align-items: center; gap: 0.75rem;" >
						<
						div style = "width: 24px; height: 24px; background: <?php echo $index == 0 ? '#fef3c7' : '#f3f4f6'; ?>; color: <?php echo $index == 0 ? '#d97706' : '#6b7280'; ?>; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.75rem; font-weight: 600;" >
						<?php echo $index + 1; ?> <
						/div> <
						span style = "font-weight: 500; color: #374151;" > <?php echo htmlspecialchars($car['brand'] . ' ' . $car['model']); ?> < /span> <
						/div> <
						span style = "font-size: 0.875rem; color: #6b7280;" > <?php echo $car['rental_count']; ?> rentals < /span> <
						/div>
			<?php endforeach; ?>
			<?php if (empty($topCars)): ?>
					<
					div style = "text-align: center; color: #9ca3af; padding: 2rem;" > No rental data available. < /div>
			<?php endif; ?>
				<
				/div> <
				/div> <
				/div>

				<
				!--Payments List-- >
				<
				div style = "background: white; border-radius: 0.75rem; border: 1px solid #e5e7eb; overflow: hidden;" >
				<
				div style = "padding: 1.5rem; border-bottom: 1px solid #e5e7eb; background: #f9fafb;" >
				<
				h2 style = "font-size: 1.125rem; font-weight: 600; color: #1f2937;" > All Payments < /h2> <
				/div> <
				table style = "width: 100%; border-collapse: collapse; text-align: left;" >
				<
				thead style = "background: #f9fafb; border-bottom: 1px solid #e5e7eb;" >
				<
				tr >
				<
				th style = "padding: 1rem; font-weight: 600; color: #4b5563;" > Date < /th> <
				th style = "padding: 1rem; font-weight: 600; color: #4b5563;" > Client < /th> <
				th style = "padding: 1rem; font-weight: 600; color: #4b5563;" > Vehicle < /th> <
				th style = "padding: 1rem; font-weight: 600; color: #4b5563;" > Amount < /th> <
				th style = "padding: 1rem; font-weight: 600; color: #4b5563;" > Method < /th> <
				th style = "padding: 1rem; font-weight: 600; color: #4b5563;" > Status < /th> <
				th style = "padding: 1rem; font-weight: 600; color: #4b5563;" > Actions < /th> <
				/tr> <
				/thead> <
				tbody >
				<?php foreach ($recentPayments as $payment): ?> <
					tr style = "border-bottom: 1px solid #e5e7eb;" >
					<
					td style = "padding: 1rem; color: #6b7280;" > <?php echo date('M d, Y', strtotime($payment['payment_date'])); ?> < /td> <
					td style = "padding: 1rem; font-weight: 500; color: #1f2937;" > <?php echo htmlspecialchars($payment['first_name'] . ' ' . $payment['last_name']); ?> < /td> <
					td style = "padding: 1rem; color: #6b7280;" > <?php echo htmlspecialchars($payment['brand'] . ' ' . $payment['model']); ?> < /td> <
					td style = "padding: 1rem; font-weight: 600; color: #1f2937;" > $<?php echo number_format($payment['amount'], 2); ?> < /td> <
					td style = "padding: 1rem;" >
					<
					span style = "text-transform: capitalize; padding: 2px 8px; background: #f3f4f6; border-radius: 4px; font-size: 0.875rem;" > <?php echo str_replace('_', ' ', $payment['method']); ?> < /span> <
					/td> <
					td style = "padding: 1rem;" >
					<?php
					$statusClass = match ($payment['status']) {
						'paid' => 'background: #dcfce7; color: #166534;',
						'pending' => 'background: #fef3c7; color: #92400e;',
						'refunded' => 'background: #fee2e2; color: #b91c1c;',
						default => 'background: #f3f4f6; color: #374151;'
					};
					?> <
					span style = "font-size: 0.75rem; font-weight: 500; padding: 0.25rem 0.75rem; border-radius: 9999px; <?php echo $statusClass; ?>" >
					<?php echo ucfirst($payment['status']); ?> <
					/span> <
					/td> <
					td style = "padding: 1rem;" >
					<
					div style = "display: flex; gap: 0.5rem;" >
					<
					button onclick = "openEditPaymentModal(<?php echo htmlspecialchars(json_encode($payment)); ?>)"
			style = "padding: 0.25rem 0.5rem; border: 1px solid #d1d5db; border-radius: 0.25rem; background: white; cursor: pointer; color: #4b5563;" > Edit < /button>
			<?php if ($payment['status'] !== 'refunded'): ?>
					<
					form method = "POST"
				action = "?page=admin_reports"
				onsubmit = "return confirm('Refund this payment?');"
				style = "display: inline;" >
					<
					input type = "hidden"
				name = "action"
				value = "refund_payment" >
					<
					input type = "hidden"
				name = "payment_id"
				value = "<?php echo $payment['payment_id']; ?>" >
					<
					button type = "submit"
				style = "padding: 0.25rem 0.5rem; border: 1px solid #fee2e2; border-radius: 0.25rem; background: #fef2f2; cursor: pointer; color: #dc2626;" > Refund < /button> <
					/form>
			<?php endif; ?>
				<
				/div> <
				/td> <
				/tr>
			<?php endforeach; ?>
				<
				/tbody> <
				/table> <
				/div> <
				/main> <
				/div>

				<
				!--Add Payment Modal-- >
				<
				div id = "addPaymentModal"
			style = "display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); align-items: center; justify-content: center; z-index: 1000;" >
				<
				div style = "background: white; padding: 2rem; border-radius: 1rem; width: 100%; max-width: 500px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1);" >
				<
				header style = "display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;" >
				<
				h2 style = "font-size: 1.5rem; font-weight: 600;" > Log New Payment < /h2> <
				button onclick = "document.getElementById('addPaymentModal').style.display='none'"
			style = "background: none; border: none; cursor: pointer; color: #6b7280;" >
				<
				svg width = "24"
			height = "24"
			viewBox = "0 0 24 24"
			fill = "none"
			stroke = "currentColor"
			stroke - width = "2" >
				<
				path d = "M18 6L6 18M6 6l12 12" > < /path> <
				/svg> <
				/button> <
				/header>

				<
				form method = "POST"
			action = "?page=admin_reports" >
				<
				input type = "hidden"
			name = "action"
			value = "create_payment" >
				<
				div style = "margin-bottom: 1rem;" >
				<
				label style = "display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 0.25rem;" > Rental(Client - Car) < /label> <
				select name = "rental_id"
			required style = "width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem;" >
				<
				option value = "" > Select Rental < /option>
			<?php foreach ($rentals as $rental): ?>
					<
					option value = "<?php echo $rental['rental_id']; ?>" > <?php echo htmlspecialchars($rental['first_name'] . ' ' . $rental['last_name'] . ' - ' . $rental['brand'] . ' ' . $rental['model']); ?> < /option>
			<?php endforeach; ?>
				<
				/select> <
				/div> <
				div style = "display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;" >
				<
				div >
				<
				label style = "display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 0.25rem;" > Amount($) < /label> <
				input type = "number"
			step = "0.01"
			name = "amount"
			required style = "width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem;" >
				<
				/div> <
				div >
				<
				label style = "display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 0.25rem;" > Date < /label> <
				input type = "date"
			name = "payment_date"
			value = "<?php echo date('Y-m-d'); ?>"
			required style = "width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem;" >
				<
				/div> <
				/div> <
				div style = "display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem;" >
				<
				div >
				<
				label style = "display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 0.25rem;" > Method < /label> <
				select name = "method"
			required style = "width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem;" >
				<
				option value = "cash" > Cash < /option> <
				option value = "credit_card" > Credit Card < /option> <
				option value = "debit_card" > Debit Card < /option> <
				option value = "bank_transfer" > Bank Transfer < /option> <
				/select> <
				/div> <
				div >
				<
				label style = "display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 0.25rem;" > Status < /label> <
				select name = "status"
			required style = "width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem;" >
				<
				option value = "paid" > Paid < /option> <
				option value = "pending" > Pending < /option> <
				/select> <
				/div> <
				/div>

				<
				div style = "display: flex; gap: 1rem; justify-content: flex-end;" >
				<
				button type = "button"
			onclick = "document.getElementById('addPaymentModal').style.display='none'"
			style = "padding: 0.5rem 1rem; border: 1px solid #d1d5db; background: white; border-radius: 0.375rem; cursor: pointer;" > Cancel < /button> <
				button type = "submit"
			style = "padding: 0.5rem 1rem; background: #1f2937; color: white; border: none; border-radius: 0.375rem; cursor: pointer;" > Log Payment < /button> <
				/div> <
				/form> <
				/div> <
				/div>

				<
				!--Edit Payment Modal-- >
				<
				div id = "editPaymentModal"
			style = "display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); align-items: center; justify-content: center; z-index: 1000;" >
				<
				div style = "background: white; padding: 2rem; border-radius: 1rem; width: 100%; max-width: 500px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1);" >
				<
				header style = "display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;" >
				<
				h2 style = "font-size: 1.5rem; font-weight: 600;" > Edit Payment < /h2> <
				button onclick = "document.getElementById('editPaymentModal').style.display='none'"
			style = "background: none; border: none; cursor: pointer; color: #6b7280;" >
				<
				svg width = "24"
			height = "24"
			viewBox = "0 0 24 24"
			fill = "none"
			stroke = "currentColor"
			stroke - width = "2" >
				<
				path d = "M18 6L6 18M6 6l12 12" > < /path> <
				/svg> <
				/button> <
				/header>

				<
				form method = "POST"
			action = "?page=admin_reports" >
				<
				input type = "hidden"
			name = "action"
			value = "update_payment" >
				<
				input type = "hidden"
			name = "payment_id"
			id = "edit_payment_id" >

				<
				div style = "display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;" >
				<
				div >
				<
				label style = "display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 0.25rem;" > Amount($) < /label> <
				input type = "number"
			step = "0.01"
			name = "amount"
			id = "edit_amount"
			required style = "width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem;" >
				<
				/div> <
				div >
				<
				label style = "display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 0.25rem;" > Date < /label> <
				input type = "date"
			name = "payment_date"
			id = "edit_date"
			required style = "width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem;" >
				<
				/div> <
				/div> <
				div style = "display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem;" >
				<
				div >
				<
				label style = "display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 0.25rem;" > Method < /label> <
				select name = "method"
			id = "edit_method"
			required style = "width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem;" >
				<
				option value = "cash" > Cash < /option> <
				option value = "credit_card" > Credit Card < /option> <
				option value = "debit_card" > Debit Card < /option> <
				option value = "bank_transfer" > Bank Transfer < /option> <
				/select> <
				/div> <
				div >
				<
				label style = "display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 0.25rem;" > Status < /label> <
				select name = "status"
			id = "edit_status"
			required style = "width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem;" >
				<
				option value = "paid" > Paid < /option> <
				option value = "pending" > Pending < /option> <
				option value = "refunded" > Refunded < /option> <
				/select> <
				/div> <
				/div>

				<
				div style = "display: flex; gap: 1rem; justify-content: flex-end;" >
				<
				button type = "button"
			onclick = "document.getElementById('editPaymentModal').style.display='none'"
			style = "padding: 0.5rem 1rem; border: 1px solid #d1d5db; background: white; border-radius: 0.375rem; cursor: pointer;" > Cancel < /button> <
				button type = "submit"
			style = "padding: 0.5rem 1rem; background: #1f2937; color: white; border: none; border-radius: 0.375rem; cursor: pointer;" > Save Changes < /button> <
				/div> <
				/form> <
				/div> <
				/div>

				<
				script >
				function openEditPaymentModal(payment) {
					document.getElementById('edit_payment_id').value = payment.payment_id;
					document.getElementById('edit_amount').value = payment.amount;
					document.getElementById('edit_date').value = payment.payment_date.split(' ')[0]; // Handle datetime if needed
					document.getElementById('edit_method').value = payment.method;
					document.getElementById('edit_status').value = payment.status;
					document.getElementById('editPaymentModal').style.display = 'flex';
				}

			// Add window onclick for closing modals
			window.onclick = function(event) {
				if (event.target == document.getElementById('addPaymentModal')) {
					document.getElementById('addPaymentModal').style.display = 'none';
				}
				if (event.target == document.getElementById('editPaymentModal')) {
					document.getElementById('editPaymentModal').style.display = 'none';
				}
				// Keep existing modal close logic working if any
				if (event.target == document.getElementById('addMaintenanceModal')) {
					document.getElementById('addMaintenanceModal').style.display = 'none';
				}
				if (event.target == document.getElementById('editMaintenanceModal')) {
					document.getElementById('editMaintenanceModal').style.display = 'none';
				}
			}
			<?php
		}

		// =============================================
		// AGENT (FRONT-OFFICE) FUNCTIONS
		// =============================================

		function renderAgentSidebar($activePage = 'dashboard')
		{
			?>
					<
					aside class = "admin-sidebar"
				style = "width: 250px; background: white; border-right: 1px solid #e5e7eb; position: fixed; height: 100%; overflow-y: auto;" >
					<
					div style = "padding: 1.5rem; border-bottom: 1px solid #e5e7eb;" >
					<
					h2 style = "font-size: 1.5rem; color: #1f2937;" > LUXDRIVE < span style = "font-size: 0.8rem; color: #6b7280; display: block; font-family: 'Inter', sans-serif; margin-top: 0.25rem;" > Agent Panel < /span></h2 >
					<
					/div> <
					nav style = "padding: 1rem;" >
					<
					ul style = "list-style: none;" >
					<
					li > < a href = "?page=agent_dashboard"
				class = "admin-nav-link <?php echo $activePage === 'dashboard' ? 'active' : ''; ?>"
				style = "display: block; padding: 0.75rem 1rem; color: <?php echo $activePage === 'dashboard' ? '#1f2937' : '#4b5563'; ?>; text-decoration: none; border-radius: 0.5rem; margin-bottom: 0.5rem; <?php echo $activePage === 'dashboard' ? 'background: #f3f4f6; font-weight: 500;' : ''; ?>" > Dashboard < /a></li >
					<
					li > < a href = "?page=agent_rentals"
				class = "admin-nav-link <?php echo $activePage === 'rentals' ? 'active' : ''; ?>"
				style = "display: block; padding: 0.75rem 1rem; color: <?php echo $activePage === 'rentals' ? '#1f2937' : '#4b5563'; ?>; text-decoration: none; border-radius: 0.5rem; margin-bottom: 0.5rem; <?php echo $activePage === 'rentals' ? 'background: #f3f4f6; font-weight: 500;' : ''; ?>" > Rentals < /a></li >
					<
					li > < a href = "?page=agent_clients"
				class = "admin-nav-link <?php echo $activePage === 'clients' ? 'active' : ''; ?>"
				style = "display: block; padding: 0.75rem 1rem; color: <?php echo $activePage === 'clients' ? '#1f2937' : '#4b5563'; ?>; text-decoration: none; border-radius: 0.5rem; margin-bottom: 0.5rem; <?php echo $activePage === 'clients' ? 'background: #f3f4f6; font-weight: 500;' : ''; ?>" > Clients < /a></li >
					<
					li > < a href = "?page=agent_cars"
				class = "admin-nav-link <?php echo $activePage === 'cars' ? 'active' : ''; ?>"
				style = "display: block; padding: 0.75rem 1rem; color: <?php echo $activePage === 'cars' ? '#1f2937' : '#4b5563'; ?>; text-decoration: none; border-radius: 0.5rem; margin-bottom: 0.5rem; <?php echo $activePage === 'cars' ? 'background: #f3f4f6; font-weight: 500;' : ''; ?>" > Fleet < /a></li >
					<
					li > < a href = "?page=agent_payments"
				class = "admin-nav-link <?php echo $activePage === 'payments' ? 'active' : ''; ?>"
				style = "display: block; padding: 0.75rem 1rem; color: <?php echo $activePage === 'payments' ? '#1f2937' : '#4b5563'; ?>; text-decoration: none; border-radius: 0.5rem; margin-bottom: 0.5rem; <?php echo $activePage === 'payments' ? 'background: #f3f4f6; font-weight: 500;' : ''; ?>" > Payments < /a></li >
					<
					li > < a href = "?page=agent_maintenance"
				class = "admin-nav-link <?php echo $activePage === 'maintenance' ? 'active' : ''; ?>"
				style = "display: block; padding: 0.75rem 1rem; color: <?php echo $activePage === 'maintenance' ? '#1f2937' : '#4b5563'; ?>; text-decoration: none; border-radius: 0.5rem; margin-bottom: 0.5rem; <?php echo $activePage === 'maintenance' ? 'background: #f3f4f6; font-weight: 500;' : ''; ?>" > Maintenance < /a></li >
					<
					li > < a href = "?page=agent_reports"
				class = "admin-nav-link <?php echo $activePage === 'reports' ? 'active' : ''; ?>"
				style = "display: block; padding: 0.75rem 1rem; color: <?php echo $activePage === 'reports' ? '#1f2937' : '#4b5563'; ?>; text-decoration: none; border-radius: 0.5rem; margin-bottom: 0.5rem; <?php echo $activePage === 'reports' ? 'background: #f3f4f6; font-weight: 500;' : ''; ?>" > Reports < /a></li >
					<
					li style = "margin-top: 2rem; border-top: 1px solid #e5e7eb; padding-top: 1rem;" > < a href = "?page=logout"
				style = "display: block; padding: 0.75rem 1rem; color: #ef4444; text-decoration: none; border-radius: 0.5rem;" > Logout < /a></li >
					<
					/ul> <
					/nav> <
					/aside>
			<?php
		}

		function renderAgentDashboard()
		{
			if (!isAgent()) {
				header('Location: himihiba.php?page=auth');
				exit;
			}
			global $pdo;
			$agencyId = $_SESSION['user_agency_id'];

			// Fetch dashboard stats
			$stmtActive = $pdo->prepare("SELECT COUNT(*) FROM rentals WHERE status = 'ongoing' AND agency_id = ?");
			$stmtActive->execute([$agencyId]);
			$activeRentals = $stmtActive->fetchColumn();

			$stmtAvail = $pdo->prepare("SELECT COUNT(*) FROM cars WHERE status = 'available' AND agency_id = ?");
			$stmtAvail->execute([$agencyId]);
			$availableCars = $stmtAvail->fetchColumn();

			$stmtPending = $pdo->prepare("SELECT COUNT(*) as count, COALESCE(SUM(p.amount), 0) as total FROM payments p JOIN rentals r ON p.rental_id = r.rental_id WHERE p.status = 'pending' AND r.agency_id = ?");
			$stmtPending->execute([$agencyId]);
			$pendingPaymentsData = $stmtPending->fetch(PDO::FETCH_ASSOC);

			$stmtReturns = $pdo->prepare("
			SELECT r.*, c.first_name, c.last_name, ca.brand, ca.model 
			FROM rentals r 
			JOIN clients c ON r.client_id = c.client_id 
			JOIN cars ca ON r.car_id = ca.car_id 
			WHERE r.status = 'ongoing' AND r.agency_id = ? AND r.end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 3 DAY)
			ORDER BY r.end_date ASC
			LIMIT 10
		");
			$stmtReturns->execute([$agencyId]);
			$upcomingReturns = $stmtReturns->fetchAll(PDO::FETCH_ASSOC);

			// Recent active rentals
			$stmtRecent = $pdo->prepare("
			SELECT r.*, c.first_name, c.last_name, ca.brand, ca.model 
			FROM rentals r 
			JOIN clients c ON r.client_id = c.client_id 
			JOIN cars ca ON r.car_id = ca.car_id 
			WHERE r.status = 'ongoing' AND r.agency_id = ?
			ORDER BY r.start_date DESC
			LIMIT 5
		");
			$stmtRecent->execute([$agencyId]);
			$recentRentals = $stmtRecent->fetchAll(PDO::FETCH_ASSOC);
			?>
					<
					div class = "admin-layout"
				style = "display: flex; min-height: 100vh; background: #f3f4f6;" >
					<?php renderAgentSidebar('dashboard'); ?>

					<
					main class = "admin-main"
				style = "flex: 1; margin-left: 250px; padding: 2rem;" >
					<
					header style = "display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;" >
					<
					div >
					<
					h1 style = "font-size: 1.875rem; font-weight: 600; color: #111827;" > Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?> < /h1> <
					p style = "color: #6b7280;" > Front Office Dashboard - <?php echo date('l, F j, Y'); ?> < /p> <
					/div> <
					div style = "display: flex; gap: 1rem;" >
					<
					a href = "?page=agent_rentals&action=new"
				style = "background: #1f2937; color: white; padding: 0.75rem 1.5rem; border: none; border-radius: 0.5rem; text-decoration: none; display: flex; align-items: center; gap: 0.5rem;" >
					<
					svg width = "20"
				height = "20"
				viewBox = "0 0 24 24"
				fill = "none"
				stroke = "currentColor"
				stroke - width = "2" >
					<
					line x1 = "12"
				y1 = "5"
				x2 = "12"
				y2 = "19" > < /line> <
					line x1 = "5"
				y1 = "12"
				x2 = "19"
				y2 = "12" > < /line> <
					/svg>
				New Rental
					<
					/a> <
					/div> <
					/header>

					<
					!--Stats Cards-- >
					<
					div style = "display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;" >
					<
					div style = "background: linear-gradient(135deg, #f6893b, #d8851d); padding: 1.5rem; border-radius: 0.75rem; color: white;" >
					<
					div style = "display: flex; justify-content: space-between; align-items: flex-start;" >
					<
					div >
					<
					h3 style = "font-size: 0.875rem; opacity: 0.9; margin-bottom: 0.5rem;" > Active Rentals < /h3> <
					p style = "font-size: 2rem; font-weight: 700;" > <?php echo $activeRentals; ?> < /p> <
					/div> <
					div style = "background: rgba(255,255,255,0.2); padding: 0.75rem; border-radius: 0.5rem;" >
					<
					svg width = "24"
				height = "24"
				viewBox = "0 0 24 24"
				fill = "none"
				stroke = "currentColor"
				stroke - width = "2" >
					<
					path d = "M19 17h2c.6 0 1-.4 1-1v-3c0-.9-.7-1.7-1.5-1.9C18.7 10.6 16 10 16 10s-1.3-1.4-2.2-2.3c-.5-.4-1.1-.7-1.8-.7H5c-.6 0-1.1.4-1.4.9l-1.4 2.9A3.7 3.7 0 0 0 2 12v4c0 .6.4 1 1 1h2" / >
					<
					circle cx = "7"
				cy = "17"
				r = "2" / >
					<
					circle cx = "17"
				cy = "17"
				r = "2" / >
					<
					/svg> <
					/div> <
					/div> <
					/div> <
					div style = "background: white; padding: 1.5rem; border-radius: 0.75rem; border: 1px solid #e5e7eb;" >
					<
					div style = "display: flex; justify-content: space-between; align-items: flex-start;" >
					<
					div >
					<
					h3 style = "font-size: 0.875rem; color: #6b7280; margin-bottom: 0.5rem;" > Cars Available < /h3> <
					p style = "font-size: 2rem; font-weight: 700; color: #059669;" > <?php echo $availableCars; ?> < /p> <
					/div> <
					div style = "background: #dcfce7; padding: 0.75rem; border-radius: 0.5rem;" >
					<
					svg width = "24"
				height = "24"
				viewBox = "0 0 24 24"
				fill = "none"
				stroke = "#059669"
				stroke - width = "2" >
					<
					path d = "M5 13l4 4L19 7" / >
					<
					/svg> <
					/div> <
					/div> <
					/div> <
					div style = "background: white; padding: 1.5rem; border-radius: 0.75rem; border: 1px solid #e5e7eb;" >
					<
					div style = "display: flex; justify-content: space-between; align-items: flex-start;" >
					<
					div >
					<
					h3 style = "font-size: 0.875rem; color: #6b7280; margin-bottom: 0.5rem;" > Pending Payments < /h3> <
					p style = "font-size: 2rem; font-weight: 700; color: #f59e0b;" > <?php echo $pendingPaymentsData['count']; ?> < /p> <
					p style = "font-size: 0.875rem; color: #6b7280;" > $<?php echo number_format($pendingPaymentsData['total'], 2); ?> total < /p> <
					/div> <
					div style = "background: #fef3c7; padding: 0.75rem; border-radius: 0.5rem;" >
					<
					svg width = "24"
				height = "24"
				viewBox = "0 0 24 24"
				fill = "none"
				stroke = "#f59e0b"
				stroke - width = "2" >
					<
					rect x = "1"
				y = "4"
				width = "22"
				height = "16"
				rx = "2"
				ry = "2" / >
					<
					line x1 = "1"
				y1 = "10"
				x2 = "23"
				y2 = "10" / >
					<
					/svg> <
					/div> <
					/div> <
					/div> <
					div style = "background: white; padding: 1.5rem; border-radius: 0.75rem; border: 1px solid #e5e7eb;" >
					<
					div style = "display: flex; justify-content: space-between; align-items: flex-start;" >
					<
					div >
					<
					h3 style = "font-size: 0.875rem; color: #6b7280; margin-bottom: 0.5rem;" > Upcoming Returns < /h3> <
					p style = "font-size: 2rem; font-weight: 700; color: #8b5cf6;" > <?php echo count($upcomingReturns); ?> < /p> <
					p style = "font-size: 0.875rem; color: #6b7280;" > Next 3 days < /p> <
					/div> <
					div style = "background: #ede9fe; padding: 0.75rem; border-radius: 0.5rem;" >
					<
					svg width = "24"
				height = "24"
				viewBox = "0 0 24 24"
				fill = "none"
				stroke = "#8b5cf6"
				stroke - width = "2" >
					<
					rect x = "3"
				y = "4"
				width = "18"
				height = "18"
				rx = "2"
				ry = "2" / >
					<
					line x1 = "16"
				y1 = "2"
				x2 = "16"
				y2 = "6" / >
					<
					line x1 = "8"
				y1 = "2"
				x2 = "8"
				y2 = "6" / >
					<
					line x1 = "3"
				y1 = "10"
				x2 = "21"
				y2 = "10" / >
					<
					/svg> <
					/div> <
					/div> <
					/div> <
					/div>

					<
					!--Quick Actions-- >
					<
					div style = "display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem; margin-bottom: 2rem;" >
					<
					a href = "?page=agent_clients"
				style = "background: white; padding: 1rem; border-radius: 0.5rem; border: 1px solid #e5e7eb; text-decoration: none; display: flex; align-items: center; gap: 0.75rem; transition: all 0.2s;" >
					<
					div style = "background: #dbeafe; padding: 0.5rem; border-radius: 0.375rem;" >
					<
					svg width = "20"
				height = "20"
				viewBox = "0 0 24 24"
				fill = "none"
				stroke = "#3b82f6"
				stroke - width = "2" >
					<
					path d = "M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" / >
					<
					circle cx = "12"
				cy = "7"
				r = "4" / >
					<
					/svg> <
					/div> <
					span style = "color: #374151; font-weight: 500;" > Register Client < /span> <
					/a> <
					a href = "?page=agent_payments"
				style = "background: white; padding: 1rem; border-radius: 0.5rem; border: 1px solid #e5e7eb; text-decoration: none; display: flex; align-items: center; gap: 0.75rem; transition: all 0.2s;" >
					<
					div style = "background: #dcfce7; padding: 0.5rem; border-radius: 0.375rem;" >
					<
					svg width = "20"
				height = "20"
				viewBox = "0 0 24 24"
				fill = "none"
				stroke = "#059669"
				stroke - width = "2" >
					<
					line x1 = "12"
				y1 = "1"
				x2 = "12"
				y2 = "23" / >
					<
					path d = "M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" / >
					<
					/svg> <
					/div> <
					span style = "color: #374151; font-weight: 500;" > Record Payment < /span> <
					/a> <
					a href = "?page=agent_cars"
				style = "background: white; padding: 1rem; border-radius: 0.5rem; border: 1px solid #e5e7eb; text-decoration: none; display: flex; align-items: center; gap: 0.75rem; transition: all 0.2s;" >
					<
					div style = "background: #fef3c7; padding: 0.5rem; border-radius: 0.375rem;" >
					<
					svg width = "20"
				height = "20"
				viewBox = "0 0 24 24"
				fill = "none"
				stroke = "#f59e0b"
				stroke - width = "2" >
					<
					circle cx = "11"
				cy = "11"
				r = "8" / >
					<
					line x1 = "21"
				y1 = "21"
				x2 = "16.65"
				y2 = "16.65" / >
					<
					/svg> <
					/div> <
					span style = "color: #374151; font-weight: 500;" > Check Fleet < /span> <
					/a> <
					/div>

					<
					div style = "display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem;" >
					<
					!--Active Rentals-- >
					<
					div style = "background: white; border-radius: 0.75rem; border: 1px solid #e5e7eb; overflow: hidden;" >
					<
					div style = "padding: 1.5rem; border-bottom: 1px solid #e5e7eb; display: flex; justify-content: space-between; align-items: center;" >
					<
					h2 style = "font-size: 1.125rem; font-weight: 600; color: #1f2937;" > Active Rentals < /h2> <
					a href = "?page=agent_rentals"
				style = "color: #3b82f6; text-decoration: none; font-size: 0.875rem;" > View All→ < /a> <
					/div>
				<?php if (empty($recentRentals)): ?>
						<
						div style = "padding: 3rem; text-align: center; color: #9ca3af;" > No active rentals < /div>
				<?php else: ?>
						<
						table style = "width: 100%; border-collapse: collapse;" >
						<
						thead style = "background: #f9fafb;" >
						<
						tr >
						<
						th style = "padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 600; color: #6b7280; text-transform: uppercase;" > Client < /th> <
						th style = "padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 600; color: #6b7280; text-transform: uppercase;" > Vehicle < /th> <
						th style = "padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 600; color: #6b7280; text-transform: uppercase;" > Dates < /th> <
						th style = "padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 600; color: #6b7280; text-transform: uppercase;" > Total < /th> <
						/tr> <
						/thead> <
						tbody >
						<?php foreach ($recentRentals as $rental): ?> <
							tr style = "border-bottom: 1px solid #e5e7eb;" >
							<
							td style = "padding: 1rem; font-weight: 500; color: #111827;" > <?php echo htmlspecialchars($rental['first_name'] . ' ' . $rental['last_name']); ?> < /td> <
							td style = "padding: 1rem; color: #6b7280;" > <?php echo htmlspecialchars($rental['brand'] . ' ' . $rental['model']); ?> < /td> <
							td style = "padding: 1rem; color: #6b7280; font-size: 0.875rem;" > <?php echo date('M d', strtotime($rental['start_date'])); ?> - <?php echo date('M d', strtotime($rental['end_date'])); ?> < /td> <
							td style = "padding: 1rem; font-weight: 600; color: #1f2937;" > $<?php echo number_format($rental['total_price'], 2); ?> < /td> <
							/tr>
				<?php endforeach; ?>
					<
					/tbody> <
					/table>
			<?php endif; ?>
				<
				/div>

				<
				!--Upcoming Returns-- >
				<
				div style = "background: white; border-radius: 0.75rem; border: 1px solid #e5e7eb; overflow: hidden;" >
				<
				div style = "padding: 1.5rem; border-bottom: 1px solid #e5e7eb;" >
				<
				h2 style = "font-size: 1.125rem; font-weight: 600; color: #1f2937;" > Upcoming Returns < /h2> <
				p style = "font-size: 0.875rem; color: #6b7280;" > Due in the next 3 days < /p> <
				/div>
			<?php if (empty($upcomingReturns)): ?>
					<
					div style = "padding: 3rem; text-align: center; color: #9ca3af;" > No upcoming returns < /div>
			<?php else: ?>
					<
					div style = "padding: 1rem;" >
					<?php foreach ($upcomingReturns as $return):
						$daysLeft = (strtotime($return['end_date']) - strtotime('today')) / 86400;
						$urgencyColor = $daysLeft <= 0 ? '#dc2626' : ($daysLeft <= 1 ? '#f59e0b' : '#6b7280');
					?> <
						div style = "padding: 0.75rem; border-radius: 0.5rem; background: #f9fafb; margin-bottom: 0.5rem;" >
						<
						div style = "display: flex; justify-content: space-between; align-items: center;" >
						<
						div >
						<
						p style = "font-weight: 500; color: #111827; font-size: 0.875rem;" > <?php echo htmlspecialchars($return['first_name'] . ' ' . $return['last_name']); ?> < /p> <
						p style = "font-size: 0.75rem; color: #6b7280;" > <?php echo htmlspecialchars($return['brand'] . ' ' . $return['model']); ?> < /p> <
						/div> <
						span style = "font-size: 0.75rem; font-weight: 600; color: <?php echo $urgencyColor; ?>;" >
						<?php
						if ($daysLeft <= 0) echo 'Today';
						elseif ($daysLeft <= 1) echo 'Tomorrow';
						else echo date('M d', strtotime($return['end_date']));
						?> <
						/span> <
						/div> <
						/div>
			<?php endforeach; ?>
				<
				/div>
			<?php endif; ?>
				<
				/div> <
				/div> <
				/main> <
				/div>
			<?php
		}

		// AGENT CLIENTS MODULE
		function handleAgentAddClient()
		{
			global $pdo;
			if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_POST['action'] ?? '') !== 'agent_add_client') return null;

			$firstName = sanitize($_POST['first_name']);
			$lastName = sanitize($_POST['last_name']);
			$email = sanitize($_POST['email']);
			$phone = sanitize($_POST['phone'] ?? '');
			$driverLicense = sanitize($_POST['driver_license']);
			$address = sanitize($_POST['address'] ?? '');
			$password = $_POST['password'];

			if (empty($firstName) || empty($lastName) || empty($email) || empty($driverLicense) || empty($password)) {
				return ['error' => 'Required fields are missing.'];
			}

			try {
				$stmt = $pdo->prepare("INSERT INTO clients (first_name, last_name, email, phone, driver_license, address, password_hash) VALUES (?, ?, ?, ?, ?, ?, ?)");
				$stmt->execute([$firstName, $lastName, $email, $phone, $driverLicense, $address, password_hash($password, PASSWORD_DEFAULT)]);
				return ['success' => 'Client registered successfully.'];
			} catch (PDOException $e) {
				return ['error' => 'Failed to register client. Email or license may already exist.'];
			}
		}

		function handleAgentUpdateClient()
		{
			global $pdo;
			if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_POST['action'] ?? '') !== 'agent_update_client') return null;

			$clientId = intval($_POST['client_id']);
			$firstName = sanitize($_POST['first_name']);
			$lastName = sanitize($_POST['last_name']);
			$email = sanitize($_POST['email']);
			$phone = sanitize($_POST['phone'] ?? '');
			$address = sanitize($_POST['address'] ?? '');

			try {
				$stmt = $pdo->prepare("UPDATE clients SET first_name = ?, last_name = ?, email = ?, phone = ?, address = ? WHERE client_id = ?");
				$stmt->execute([$firstName, $lastName, $email, $phone, $address, $clientId]);
				return ['success' => 'Client updated successfully.'];
			} catch (PDOException $e) {
				return ['error' => 'Failed to update client.'];
			}
		}

		function renderAgentClients()
		{
			if (!isAgent()) {
				header('Location: himihiba.php?page=auth');
				exit;
			}
			global $pdo;

			$result = handleAgentAddClient();
			if (!$result) $result = handleAgentUpdateClient();

			$clients = $pdo->query("SELECT client_id, first_name, last_name, email, phone, address, registration_date FROM clients ORDER BY registration_date DESC")->fetchAll(PDO::FETCH_ASSOC);
			?>
					<
					div class = "admin-layout"
				style = "display: flex; min-height: 100vh; background: #f3f4f6;" >
					<?php renderAgentSidebar('clients'); ?> <
					main class = "admin-main"
				style = "flex: 1; margin-left: 250px; padding: 2rem;" >
					<
					header style = "display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;" >
					<
					div >
					<
					h1 style = "font-size: 1.875rem; font-weight: 600; color: #111827;" > Client Management < /h1> <
					p style = "color: #6b7280;" > Register and manage customers. < /p> <
					/div> <
					button onclick = "document.getElementById('addClientModal').style.display='flex'"
				style = "background: #1f2937; color: white; padding: 0.75rem 1.5rem; border: none; border-radius: 0.5rem; cursor: pointer; display: flex; align-items: center; gap: 0.5rem;" >
					<
					svg width = "20"
				height = "20"
				viewBox = "0 0 24 24"
				fill = "none"
				stroke = "currentColor"
				stroke - width = "2" > < line x1 = "12"
				y1 = "5"
				x2 = "12"
				y2 = "19" > < /line><line x1="5" y1="12" x2="19" y2="12"></line > < /svg>
				Register Client
					<
					/button> <
					/header>

				<?php if ($result && isset($result['success'])): ?>
						<
						div style = "background: #dcfce7; color: #16a34a; padding: 1rem; border-radius: 8px; margin-bottom: 2rem;" > <?php echo htmlspecialchars($result['success']); ?> < /div>
				<?php endif; ?>
				<?php if ($result && isset($result['error'])): ?>
						<
						div style = "background: #fee2e2; color: #dc2626; padding: 1rem; border-radius: 8px; margin-bottom: 2rem;" > <?php echo htmlspecialchars($result['error']); ?> < /div>
				<?php endif; ?>

					<
					div style = "background: white; border-radius: 0.75rem; border: 1px solid #e5e7eb; overflow: hidden;" >
					<
					table style = "width: 100%; border-collapse: collapse; text-align: left;" >
					<
					thead style = "background: #f9fafb; border-bottom: 1px solid #e5e7eb;" >
					<
					tr >
					<
					th style = "padding: 1rem; font-weight: 600; color: #4b5563;" > Name < /th> <
					th style = "padding: 1rem; font-weight: 600; color: #4b5563;" > Email < /th> <
					th style = "padding: 1rem; font-weight: 600; color: #4b5563;" > Phone < /th> <
					th style = "padding: 1rem; font-weight: 600; color: #4b5563;" > Joined < /th> <
					th style = "padding: 1rem; font-weight: 600; color: #4b5563;" > Actions < /th> <
					/tr> <
					/thead> <
					tbody >
					<?php foreach ($clients as $client): ?> <
						tr style = "border-bottom: 1px solid #e5e7eb;" >
						<
						td style = "padding: 1rem; font-weight: 500; color: #111827;" > <?php echo htmlspecialchars($client['first_name'] . ' ' . $client['last_name']); ?> < /td> <
						td style = "padding: 1rem; color: #6b7280;" > <?php echo htmlspecialchars($client['email']); ?> < /td> <
						td style = "padding: 1rem; color: #6b7280;" > <?php echo htmlspecialchars($client['phone'] ?? 'N/A'); ?> < /td> <
						td style = "padding: 1rem; color: #6b7280;" > <?php echo date('M d, Y', strtotime($client['registration_date'])); ?> < /td> <
						td style = "padding: 1rem;" >
						<
						button onclick = "openAgentEditClient(<?php echo htmlspecialchars(json_encode($client)); ?>)"
				style = "padding: 0.25rem 0.5rem; border: 1px solid #d1d5db; border-radius: 0.25rem; background: white; cursor: pointer; color: #4b5563;" > Edit < /button> <
					/td> <
					/tr>
			<?php endforeach; ?>
				<
				/tbody> <
				/table> <
				/div> <
				/main> <
				/div>

				<
				!--Add Client Modal-- >
				<
				div id = "addClientModal"
			style = "display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); align-items: center; justify-content: center; z-index: 1000;" >
				<
				div style = "background: white; padding: 2rem; border-radius: 1rem; width: 100%; max-width: 500px;" >
				<
				header style = "display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;" >
				<
				h2 style = "font-size: 1.5rem; font-weight: 600;" > Register New Client < /h2> <
				button onclick = "document.getElementById('addClientModal').style.display='none'"
			style = "background: none; border: none; cursor: pointer; color: #6b7280;" > & times; < /button> <
			/header> <
			form method = "POST"
			action = "?page=agent_clients" >
				<
				input type = "hidden"
			name = "action"
			value = "agent_add_client" >
				<
				div style = "display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;" >
				<
				div > < label style = "display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 0.25rem;" > First Name < /label><input type="text" name="first_name" required style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem;"></div >
				<
				div > < label style = "display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 0.25rem;" > Last Name < /label><input type="text" name="last_name" required style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem;"></div >
				<
				/div> <
				div style = "margin-bottom: 1rem;" > < label style = "display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 0.25rem;" > Email < /label><input type="email" name="email" required style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem;"></div >
				<
				div style = "margin-bottom: 1rem;" > < label style = "display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 0.25rem;" > Phone < /label><input type="tel" name="phone" style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem;"></div >
				<
				div style = "margin-bottom: 1rem;" > < label style = "display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 0.25rem;" > Driver License < /label><input type="text" name="driver_license" required style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem;"></div >
				<
				div style = "margin-bottom: 1rem;" > < label style = "display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 0.25rem;" > Address < /label><input type="text" name="address" style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem;"></div >
				<
				div style = "margin-bottom: 1.5rem;" > < label style = "display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 0.25rem;" > Password < /label><input type="password" name="password" required style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem;"></div >
				<
				div style = "display: flex; gap: 1rem; justify-content: flex-end;" >
				<
				button type = "button"
			onclick = "document.getElementById('addClientModal').style.display='none'"
			style = "padding: 0.5rem 1rem; border: 1px solid #d1d5db; background: white; border-radius: 0.375rem; cursor: pointer;" > Cancel < /button> <
				button type = "submit"
			style = "padding: 0.5rem 1rem; background: #1f2937; color: white; border: none; border-radius: 0.375rem; cursor: pointer;" > Register < /button> <
				/div> <
				/form> <
				/div> <
				/div>

				<
				!--Edit Client Modal(No License Field) -- >
				<
				div id = "editClientModal"
			style = "display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); align-items: center; justify-content: center; z-index: 1000;" >
				<
				div style = "background: white; padding: 2rem; border-radius: 1rem; width: 100%; max-width: 500px;" >
				<
				header style = "display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;" >
				<
				h2 style = "font-size: 1.5rem; font-weight: 600;" > Edit Client < /h2> <
				button onclick = "document.getElementById('editClientModal').style.display='none'"
			style = "background: none; border: none; cursor: pointer; color: #6b7280;" > & times; < /button> <
			/header> <
			form method = "POST"
			action = "?page=agent_clients" >
				<
				input type = "hidden"
			name = "action"
			value = "agent_update_client" >
				<
				input type = "hidden"
			name = "client_id"
			id = "edit_agent_client_id" >
				<
				div style = "display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;" >
				<
				div > < label style = "display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 0.25rem;" > First Name < /label><input type="text" name="first_name" id="edit_agent_first_name" required style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem;"></div >
				<
				div > < label style = "display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 0.25rem;" > Last Name < /label><input type="text" name="last_name" id="edit_agent_last_name" required style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem;"></div >
				<
				/div> <
				div style = "margin-bottom: 1rem;" > < label style = "display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 0.25rem;" > Email < /label><input type="email" name="email" id="edit_agent_email" required style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem;"></div >
				<
				div style = "margin-bottom: 1rem;" > < label style = "display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 0.25rem;" > Phone < /label><input type="tel" name="phone" id="edit_agent_phone" style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem;"></div >
				<
				div style = "margin-bottom: 1.5rem;" > < label style = "display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 0.25rem;" > Address < /label><input type="text" name="address" id="edit_agent_address" style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem;"></div >
				<
				div style = "display: flex; gap: 1rem; justify-content: flex-end;" >
				<
				button type = "button"
			onclick = "document.getElementById('editClientModal').style.display='none'"
			style = "padding: 0.5rem 1rem; border: 1px solid #d1d5db; background: white; border-radius: 0.375rem; cursor: pointer;" > Cancel < /button> <
				button type = "submit"
			style = "padding: 0.5rem 1rem; background: #1f2937; color: white; border: none; border-radius: 0.375rem; cursor: pointer;" > Save < /button> <
				/div> <
				/form> <
				/div> <
				/div> <
				script >
				function openAgentEditClient(c) {
					document.getElementById('edit_agent_client_id').value = c.client_id;
					document.getElementById('edit_agent_first_name').value = c.first_name;
					document.getElementById('edit_agent_last_name').value = c.last_name;
					document.getElementById('edit_agent_email').value = c.email;
					document.getElementById('edit_agent_phone').value = c.phone || '';
					document.getElementById('edit_agent_address').value = c.address || '';
					document.getElementById('editClientModal').style.display = 'flex';
				}
	</script>
<?php
		}

		// AGENT CARS MODULE (VIEW ONLY)
		function renderAgentCars()
		{
			if (!isAgent()) {
				header('Location: himihiba.php?page=auth');
				exit;
			}
			global $pdo;
			$agencyId = $_SESSION['user_agency_id'];
			$stmt = $pdo->prepare("SELECT car_id, brand, model, year, color, status, daily_price, image_url, car_type, seats, transmission, fuel_type FROM cars WHERE agency_id = ? ORDER BY brand, model");
			$stmt->execute([$agencyId]);
			$cars = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
	<div class="admin-layout" style="display: flex; min-height: 100vh; background: #f3f4f6;">
		<?php renderAgentSidebar('cars'); ?>
		<main class="admin-main" style="flex: 1; margin-left: 250px; padding: 2rem;">
			<header style="margin-bottom: 2rem;">
				<h1 style="font-size: 1.875rem; font-weight: 600; color: #111827;">Fleet Overview</h1>
				<p style="color: #6b7280;">View available vehicles (read-only).</p>
			</header>
			<div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 1.5rem;">
				<?php foreach ($cars as $car):
					$statusColor = match ($car['status']) {
						'available' => '#059669',
						'rented' => '#3b82f6',
						'maintenance' => '#f59e0b',
						default => '#6b7280'
					};
				?>
					<div style="background: white; border-radius: 0.75rem; border: 1px solid #e5e7eb; overflow: hidden;">
						<div style="height: 160px; background: #f3f4f6; position: relative;">
							<?php if ($car['image_url']): ?>
								<img src="<?php echo htmlspecialchars($car['image_url']); ?>" alt="<?php echo htmlspecialchars($car['brand']); ?>" style="width: 100%; height: 100%; object-fit: cover;">
							<?php endif; ?>
							<span style="position: absolute; top: 0.5rem; right: 0.5rem; background: <?php echo $statusColor; ?>; color: white; padding: 0.25rem 0.5rem; border-radius: 0.25rem; font-size: 0.75rem; text-transform: capitalize;"><?php echo $car['status']; ?></span>
						</div>
						<div style="padding: 1rem;">
							<h3 style="font-weight: 600; color: #111827;"><?php echo htmlspecialchars($car['brand'] . ' ' . $car['model']); ?></h3>
							<p style="color: #6b7280; font-size: 0.875rem;"><?php echo $car['year']; ?> • <?php echo $car['car_type']; ?></p>
							<div style="display: flex; gap: 1rem; margin-top: 0.5rem; font-size: 0.75rem; color: #6b7280;">
								<span><?php echo $car['seats']; ?> seats</span>
								<span><?php echo $car['transmission']; ?></span>
								<span><?php echo $car['fuel_type']; ?></span>
							</div>
							<p style="font-size: 1.25rem; font-weight: 700; color: #1f2937; margin-top: 0.5rem;">$<?php echo number_format($car['daily_price'], 2); ?><span style="font-size: 0.875rem; font-weight: 400; color: #6b7280;">/day</span></p>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		</main>
	</div>
<?php
		}

		// AGENT RENTALS MODULE
		function handleAgentCreateRental()
		{
			global $pdo;
			if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_POST['action'] ?? '') !== 'agent_create_rental') return null;
			$clientId = intval($_POST['client_id']);
			$carId = intval($_POST['car_id']);
			$startDate = sanitize($_POST['start_date']);
			$endDate = sanitize($_POST['end_date']);
			$extras = sanitize($_POST['extras'] ?? '');
			$staffId = $_SESSION['user_id'];
			$agencyId = $_SESSION['user_agency_id'];

			// Check car is available AND belongs to agency
			$car = $pdo->prepare("SELECT status, daily_price, agency_id FROM cars WHERE car_id = ?");
			$car->execute([$carId]);
			$carData = $car->fetch(PDO::FETCH_ASSOC);

			if (!$carData || $carData['agency_id'] != $agencyId) {
				return ['error' => 'Invalid car selection.'];
			}
			if ($carData['status'] !== 'available') {
				return ['error' => 'Car is not available for rental.'];
			}

			$days = max(1, (strtotime($endDate) - strtotime($startDate)) / 86400);
			$totalPrice = $days * $carData['daily_price'];

			try {
				// Include agency_id in insert
				$stmt = $pdo->prepare("INSERT INTO rentals (agency_id, client_id, car_id, staff_id, start_date, end_date, total_price, extras, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'ongoing')");
				$stmt->execute([$agencyId, $clientId, $carId, $staffId, $startDate, $endDate, $totalPrice, $extras]);
				return ['success' => 'Rental created successfully.'];
			} catch (PDOException $e) {
				return ['error' => 'Failed to create rental.'];
			}
		}

		function handleAgentCompleteRental()
		{
			global $pdo;
			if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_POST['action'] ?? '') !== 'agent_complete_rental') return null;
			$rentalId = intval($_POST['rental_id']);
			$agencyId = $_SESSION['user_agency_id'];

			// Verify rental belongs to agency
			$check = $pdo->prepare("SELECT agency_id FROM rentals WHERE rental_id = ?");
			$check->execute([$rentalId]);
			$rental = $check->fetch(PDO::FETCH_ASSOC);

			if (!$rental || $rental['agency_id'] != $agencyId) {
				return ['error' => 'Unauthorized action.'];
			}

			try {
				$stmt = $pdo->prepare("CALL complete_rental(?)");
				$stmt->execute([$rentalId]);
				return ['success' => 'Rental completed successfully.'];
			} catch (PDOException $e) {
				return ['error' => 'Failed to complete rental.'];
			}
		}

		function renderAgentRentals()
		{
			if (!isAgent()) {
				header('Location: himihiba.php?page=auth');
				exit;
			}
			global $pdo;
			$agencyId = $_SESSION['user_agency_id'];

			$result = handleAgentCreateRental();
			if (!$result) $result = handleAgentCompleteRental();

			// Filter rentals by agency
			$stmtRentals = $pdo->prepare("
			SELECT r.*, c.first_name, c.last_name, ca.brand, ca.model 
			FROM rentals r 
			JOIN clients c ON r.client_id = c.client_id 
			JOIN cars ca ON r.car_id = ca.car_id 
			WHERE r.agency_id = ?
			ORDER BY r.created_at DESC
		");
			$stmtRentals->execute([$agencyId]);
			$rentals = $stmtRentals->fetchAll(PDO::FETCH_ASSOC);

			$clients = $pdo->query("SELECT client_id, first_name, last_name FROM clients ORDER BY first_name")->fetchAll(PDO::FETCH_ASSOC);

			// Filter available cars by agency
			$stmtCars = $pdo->prepare("SELECT car_id, brand, model, daily_price FROM cars WHERE status = 'available' AND agency_id = ?");
			$stmtCars->execute([$agencyId]);
			$availableCars = $stmtCars->fetchAll(PDO::FETCH_ASSOC);
?>
	<div class="admin-layout" style="display: flex; min-height: 100vh; background: #f3f4f6;">
		<?php renderAgentSidebar('rentals'); ?>
		<main class="admin-main" style="flex: 1; margin-left: 250px; padding: 2rem;">
			<header style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
				<div>
					<h1 style="font-size: 1.875rem; font-weight: 600; color: #111827;">Rental Management</h1>
					<p style="color: #6b7280;">Create and manage rentals.</p>
				</div>
				<button onclick="document.getElementById('addRentalModal').style.display='flex'" style="background: #1f2937; color: white; padding: 0.75rem 1.5rem; border: none; border-radius: 0.5rem; cursor: pointer;">+ New Rental</button>
			</header>
			<?php if ($result && isset($result['success'])): ?><div style="background: #dcfce7; color: #16a34a; padding: 1rem; border-radius: 8px; margin-bottom: 2rem;"><?php echo $result['success']; ?></div><?php endif; ?>
			<?php if ($result && isset($result['error'])): ?><div style="background: #fee2e2; color: #dc2626; padding: 1rem; border-radius: 8px; margin-bottom: 2rem;"><?php echo $result['error']; ?></div><?php endif; ?>
			<div style="background: white; border-radius: 0.75rem; border: 1px solid #e5e7eb; overflow: hidden;">
				<table style="width: 100%; border-collapse: collapse;">
					<thead style="background: #f9fafb;">
						<tr>
							<th style="padding: 1rem; text-align: left; font-weight: 600; color: #4b5563;">Client</th>
							<th style="padding: 1rem; text-align: left; font-weight: 600; color: #4b5563;">Vehicle</th>
							<th style="padding: 1rem; text-align: left; font-weight: 600; color: #4b5563;">Dates</th>
							<th style="padding: 1rem; text-align: left; font-weight: 600; color: #4b5563;">Total</th>
							<th style="padding: 1rem; text-align: left; font-weight: 600; color: #4b5563;">Status</th>
							<th style="padding: 1rem; text-align: left; font-weight: 600; color: #4b5563;">Actions</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($rentals as $r): $sc = match ($r['status']) {
								'ongoing' => 'background:#dbeafe;color:#1e40af;',
								'completed' => 'background:#dcfce7;color:#166534;',
								'cancelled' => 'background:#fee2e2;color:#b91c1c;',
								default => ''
							}; ?>
							<tr style="border-bottom: 1px solid #e5e7eb;">
								<td style="padding: 1rem; font-weight: 500;"><?php echo htmlspecialchars($r['first_name'] . ' ' . $r['last_name']); ?></td>
								<td style="padding: 1rem; color: #6b7280;"><?php echo htmlspecialchars($r['brand'] . ' ' . $r['model']); ?></td>
								<td style="padding: 1rem; color: #6b7280; font-size: 0.875rem;"><?php echo date('M d', strtotime($r['start_date'])); ?> - <?php echo date('M d', strtotime($r['end_date'])); ?></td>
								<td style="padding: 1rem; font-weight: 600;">$<?php echo number_format($r['total_price'], 2); ?></td>
								<td style="padding: 1rem;"><span style="padding: 0.25rem 0.5rem; border-radius: 9999px; font-size: 0.75rem; <?php echo $sc; ?>"><?php echo ucfirst($r['status']); ?></span></td>
								<td style="padding: 1rem;">
									<?php if ($r['status'] === 'ongoing'): ?>
										<form method="POST" style="display:inline;" onsubmit="return confirm('Complete this rental?');">
											<input type="hidden" name="action" value="agent_complete_rental">
											<input type="hidden" name="rental_id" value="<?php echo $r['rental_id']; ?>">
											<button type="submit" style="padding: 0.25rem 0.5rem; border: 1px solid #059669; background: #dcfce7; color: #059669; border-radius: 0.25rem; cursor: pointer;">Complete</button>
										</form>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</main>
	</div>
	<div id="addRentalModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); align-items: center; justify-content: center; z-index: 1000;">
		<div style="background: white; padding: 2rem; border-radius: 1rem; width: 100%; max-width: 500px;">
			<header style="display: flex; justify-content: space-between; margin-bottom: 1.5rem;">
				<h2 style="font-size: 1.5rem; font-weight: 600;">New Rental</h2><button onclick="document.getElementById('addRentalModal').style.display='none'" style="background: none; border: none; cursor: pointer;">&times;</button>
			</header>
			<form method="POST">
				<input type="hidden" name="action" value="agent_create_rental">
				<div style="margin-bottom: 1rem;"><label style="display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.25rem;">Client</label><select name="client_id" required style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem;">
						<option value="">Select Client</option><?php foreach ($clients as $c): ?><option value="<?php echo $c['client_id']; ?>"><?php echo htmlspecialchars($c['first_name'] . ' ' . $c['last_name']); ?></option><?php endforeach; ?>
					</select></div>
				<div style="margin-bottom: 1rem;"><label style="display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.25rem;">Available Car</label><select name="car_id" required style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem;">
						<option value="">Select Car</option><?php foreach ($availableCars as $ac): ?><option value="<?php echo $ac['car_id']; ?>"><?php echo htmlspecialchars($ac['brand'] . ' ' . $ac['model']); ?> - $<?php echo $ac['daily_price']; ?>/day</option><?php endforeach; ?>
					</select></div>
				<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
					<div><label style="display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.25rem;">Start Date</label><input type="date" name="start_date" required value="<?php echo date('Y-m-d'); ?>" style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem;"></div>
					<div><label style="display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.25rem;">End Date</label><input type="date" name="end_date" required style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem;"></div>
				</div>
				<div style="margin-bottom: 1.5rem;"><label style="display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.25rem;">Extras/Notes</label><textarea name="extras" rows="2" style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem;"></textarea></div>
				<div style="display: flex; gap: 1rem; justify-content: flex-end;">
					<button type="button" onclick="document.getElementById('addRentalModal').style.display='none'" style="padding: 0.5rem 1rem; border: 1px solid #d1d5db; background: white; border-radius: 0.375rem; cursor: pointer;">Cancel</button>
					<button type="submit" style="padding: 0.5rem 1rem; background: #1f2937; color: white; border: none; border-radius: 0.375rem; cursor: pointer;">Create Rental</button>
				</div>
			</form>
		</div>
	</div>
<?php
		}

		// AGENT PAYMENTS MODULE
		function handleAgentPayment()
		{
			global $pdo;
			if ($_SERVER['REQUEST_METHOD'] !== 'POST') return null;
			$agencyId = $_SESSION['user_agency_id'];
			$action = $_POST['action'] ?? '';

			if ($action === 'agent_create_payment') {
				$rentalId = intval($_POST['rental_id']);
				$amount = floatval($_POST['amount']);
				$method = sanitize($_POST['method']);
				$status = sanitize($_POST['status']);

				// Verify rental belongs to agency
				$check = $pdo->prepare("SELECT agency_id FROM rentals WHERE rental_id = ?");
				$check->execute([$rentalId]);
				$rental = $check->fetch(PDO::FETCH_ASSOC);
				if (!$rental || $rental['agency_id'] != $agencyId) {
					return ['error' => 'Unauthorized rental.'];
				}

				try {
					$stmt = $pdo->prepare("INSERT INTO payments (rental_id, amount, method, status) VALUES (?, ?, ?, ?)");
					$stmt->execute([$rentalId, $amount, $method, $status]);
					return ['success' => 'Payment recorded.'];
				} catch (PDOException $e) {
					return ['error' => 'Failed to record payment.'];
				}
			}

			if ($action === 'agent_update_payment_status') {
				$paymentId = intval($_POST['payment_id']);
				$status = $_POST['status'] === 'paid' ? 'paid' : 'pending';

				// Verify payment->rental->agency
				$check = $pdo->prepare("SELECT r.agency_id FROM payments p JOIN rentals r ON p.rental_id = r.rental_id WHERE p.payment_id = ?");
				$check->execute([$paymentId]);
				$rental = $check->fetch(PDO::FETCH_ASSOC);
				if (!$rental || $rental['agency_id'] != $agencyId) {
					return ['error' => 'Unauthorized payment.'];
				}

				try {
					$stmt = $pdo->prepare("UPDATE payments SET status = ? WHERE payment_id = ?");
					$stmt->execute([$status, $paymentId]);
					return ['success' => 'Payment status updated.'];
				} catch (PDOException $e) {
					return ['error' => 'Failed to update status.'];
				}
			}
			return null;
		}

		function renderAgentPayments()
		{
			if (!isAgent()) {
				header('Location: himihiba.php?page=auth');
				exit;
			}
			global $pdo;
			$agencyId = $_SESSION['user_agency_id'];

			$result = handleAgentPayment();

			$stmtPayments = $pdo->prepare("
			SELECT p.*, c.first_name, c.last_name, ca.brand, ca.model 
			FROM payments p 
			JOIN rentals r ON p.rental_id = r.rental_id 
			JOIN clients c ON r.client_id = c.client_id 
			JOIN cars ca ON r.car_id = ca.car_id 
			WHERE p.status != 'refunded' AND r.agency_id = ?
			ORDER BY p.payment_date DESC
		");
			$stmtPayments->execute([$agencyId]);
			$payments = $stmtPayments->fetchAll(PDO::FETCH_ASSOC);

			$stmtRentals = $pdo->prepare("
			SELECT r.rental_id, c.first_name, c.last_name, ca.brand, ca.model 
			FROM rentals r 
			JOIN clients c ON r.client_id = c.client_id 
			JOIN cars ca ON r.car_id = ca.car_id 
			WHERE r.agency_id = ?
			ORDER BY r.created_at DESC
		");
			$stmtRentals->execute([$agencyId]);
			$rentals = $stmtRentals->fetchAll(PDO::FETCH_ASSOC);
?>
	<div class="admin-layout" style="display: flex; min-height: 100vh; background: #f3f4f6;">
		<?php renderAgentSidebar('payments'); ?>
		<main class="admin-main" style="flex: 1; margin-left: 250px; padding: 2rem;">
			<header style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
				<div>
					<h1 style="font-size: 1.875rem; font-weight: 600; color: #111827;">Payments</h1>
					<p style="color: #6b7280;">Record and track payments.</p>
				</div>
				<button onclick="document.getElementById('addPaymentAgentModal').style.display='flex'" style="background: #1f2937; color: white; padding: 0.75rem 1.5rem; border: none; border-radius: 0.5rem; cursor: pointer;">+ Record Payment</button>
			</header>
			<?php if ($result && isset($result['success'])): ?><div style="background: #dcfce7; color: #16a34a; padding: 1rem; border-radius: 8px; margin-bottom: 2rem;"><?php echo $result['success']; ?></div><?php endif; ?>
			<?php if ($result && isset($result['error'])): ?><div style="background: #fee2e2; color: #dc2626; padding: 1rem; border-radius: 8px; margin-bottom: 2rem;"><?php echo $result['error']; ?></div><?php endif; ?>
			<div style="background: white; border-radius: 0.75rem; border: 1px solid #e5e7eb; overflow: hidden;">
				<table style="width: 100%; border-collapse: collapse;">
					<thead style="background: #f9fafb;">
						<tr>
							<th style="padding: 1rem; text-align: left; font-weight: 600; color: #4b5563;">Date</th>
							<th style="padding: 1rem; text-align: left; font-weight: 600; color: #4b5563;">Client</th>
							<th style="padding: 1rem; text-align: left; font-weight: 600; color: #4b5563;">Amount</th>
							<th style="padding: 1rem; text-align: left; font-weight: 600; color: #4b5563;">Method</th>
							<th style="padding: 1rem; text-align: left; font-weight: 600; color: #4b5563;">Status</th>
							<th style="padding: 1rem; text-align: left; font-weight: 600; color: #4b5563;">Action</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($payments as $p): $sc = $p['status'] === 'paid' ? 'background:#dcfce7;color:#166534;' : 'background:#fef3c7;color:#92400e;'; ?>
							<tr style="border-bottom: 1px solid #e5e7eb;">
								<td style="padding: 1rem; color: #6b7280;"><?php echo date('M d, Y', strtotime($p['payment_date'])); ?></td>
								<td style="padding: 1rem; font-weight: 500;"><?php echo htmlspecialchars($p['first_name'] . ' ' . $p['last_name']); ?></td>
								<td style="padding: 1rem; font-weight: 600;">$<?php echo number_format($p['amount'], 2); ?></td>
								<td style="padding: 1rem; color: #6b7280; text-transform: capitalize;"><?php echo str_replace('_', ' ', $p['method']); ?></td>
								<td style="padding: 1rem;"><span style="padding: 0.25rem 0.5rem; border-radius: 9999px; font-size: 0.75rem; <?php echo $sc; ?>"><?php echo ucfirst($p['status']); ?></span></td>
								<td style="padding: 1rem;">
									<form method="POST" style="display: inline;"><input type="hidden" name="action" value="agent_update_payment_status"><input type="hidden" name="payment_id" value="<?php echo $p['payment_id']; ?>"><input type="hidden" name="status" value="<?php echo $p['status'] === 'paid' ? 'pending' : 'paid'; ?>"><button type="submit" style="padding: 0.25rem 0.5rem; border: 1px solid #d1d5db; background: white; border-radius: 0.25rem; cursor: pointer; font-size: 0.75rem;">Toggle</button></form>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</main>
	</div>
	<div id="addPaymentAgentModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); align-items: center; justify-content: center; z-index: 1000;">
		<div style="background: white; padding: 2rem; border-radius: 1rem; width: 100%; max-width: 500px;">
			<header style="display: flex; justify-content: space-between; margin-bottom: 1.5rem;">
				<h2 style="font-size: 1.5rem; font-weight: 600;">Record Payment</h2><button onclick="document.getElementById('addPaymentAgentModal').style.display='none'" style="background: none; border: none; cursor: pointer;">&times;</button>
			</header>
			<form method="POST">
				<input type="hidden" name="action" value="agent_create_payment">
				<div style="margin-bottom: 1rem;"><label style="display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.25rem;">Rental</label><select name="rental_id" required style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem;">
						<option value="">Select Rental</option><?php foreach ($rentals as $r): ?><option value="<?php echo $r['rental_id']; ?>"><?php echo htmlspecialchars($r['first_name'] . ' ' . $r['last_name'] . ' - ' . $r['brand'] . ' ' . $r['model']); ?></option><?php endforeach; ?>
					</select></div>
				<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
					<div><label style="display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.25rem;">Amount</label><input type="number" step="0.01" name="amount" required style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem;"></div>
					<div><label style="display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.25rem;">Method</label><select name="method" required style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem;">
							<option value="cash">Cash</option>
							<option value="credit_card">Credit Card</option>
							<option value="debit_card">Debit Card</option>
							<option value="bank_transfer">Bank Transfer</option>
						</select></div>
				</div>
				<div style="margin-bottom: 1.5rem;"><label style="display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.25rem;">Status</label><select name="status" style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem;">
						<option value="paid">Paid</option>
						<option value="pending">Pending</option>
					</select></div>
				<div style="display: flex; gap: 1rem; justify-content: flex-end;">
					<button type="button" onclick="document.getElementById('addPaymentAgentModal').style.display='none'" style="padding: 0.5rem 1rem; border: 1px solid #d1d5db; background: white; border-radius: 0.375rem; cursor: pointer;">Cancel</button>
					<button type="submit" style="padding: 0.5rem 1rem; background: #1f2937; color: white; border: none; border-radius: 0.375rem; cursor: pointer;">Record</button>
				</div>
			</form>
		</div>
	</div>
<?php
		}

		// AGENT MAINTENANCE VIEW (READ ONLY)
		function renderAgentMaintenance()
		{
			if (!isAgent()) {
				header('Location: himihiba.php?page=auth');
				exit;
			}
			global $pdo;
			$agencyId = $_SESSION['user_agency_id'];
			$stmt = $pdo->prepare("SELECT c.car_id, c.brand, c.model, c.year FROM cars c WHERE c.status = 'maintenance' AND c.agency_id = ?");
			$stmt->execute([$agencyId]);
			$maintenanceCars = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
	<div class="admin-layout" style="display: flex; min-height: 100vh; background: #f3f4f6;">
		<?php renderAgentSidebar('maintenance'); ?>
		<main class="admin-main" style="flex: 1; margin-left: 250px; padding: 2rem;">
			<header style="margin-bottom: 2rem;">
				<h1 style="font-size: 1.875rem; font-weight: 600; color: #111827;">Maintenance Status</h1>
				<p style="color: #6b7280;">Cars currently unavailable for rental.</p>
			</header>
			<?php if (empty($maintenanceCars)): ?>
				<div style="background: white; padding: 3rem; border-radius: 0.75rem; border: 1px solid #e5e7eb; text-align: center; color: #6b7280;">
					<svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="margin: 0 auto 1rem;">
						<path d="M5 13l4 4L19 7" />
					</svg>
					<p>All vehicles are currently available!</p>
				</div>
			<?php else: ?>
				<div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 1rem;">
					<?php foreach ($maintenanceCars as $car): ?>
						<div style="background: white; padding: 1.5rem; border-radius: 0.75rem; border: 1px solid #fef3c7; border-left: 4px solid #f59e0b;">
							<div style="display: flex; align-items: center; gap: 0.75rem;">
								<div style="background: #fef3c7; padding: 0.5rem; border-radius: 0.5rem;"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2">
										<path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z" />
									</svg></div>
								<div>
									<h3 style="font-weight: 600; color: #111827;"><?php echo htmlspecialchars($car['brand'] . ' ' . $car['model']); ?></h3>
									<p style="font-size: 0.875rem; color: #6b7280;"><?php echo $car['year']; ?></p>
								</div>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</main>
	</div>
<?php
		}

		// AGENT REPORTS MODULE
		function renderAgentReports()
		{
			if (!isAgent()) {
				header('Location: himihiba.php?page=auth');
				exit;
			}
			global $pdo;
			$staffId = $_SESSION['user_id'];
			$agencyId = $_SESSION['user_agency_id'];

			// Use direct query to filtered by agency
			$stmtCurrentlyRented = $pdo->prepare("
			SELECT r.rental_id, r.car_id, c.brand, c.model, c.license_plate, r.client_id, r.start_date, r.end_date 
			FROM rentals r 
			JOIN cars c ON r.car_id = c.car_id 
			WHERE r.status = 'ongoing' AND r.agency_id = ?
		");
			$stmtCurrentlyRented->execute([$agencyId]);
			$currentlyRented = $stmtCurrentlyRented->fetchAll(PDO::FETCH_ASSOC);

			// Staff's own rentals query is already isolated by staff_id, which is fine.
			$ownRentals = $pdo->prepare("SELECT r.*, c.first_name, c.last_name, ca.brand, ca.model FROM rentals r JOIN clients c ON r.client_id = c.client_id JOIN cars ca ON r.car_id = ca.car_id WHERE r.staff_id = ? ORDER BY r.created_at DESC");
			$ownRentals->execute([$staffId]);
			$myRentals = $ownRentals->fetchAll(PDO::FETCH_ASSOC);

			$todayCount = $pdo->prepare("SELECT COUNT(*) FROM rentals WHERE staff_id = ? AND DATE(created_at) = CURDATE()");
			$todayCount->execute([$staffId]);
			$todayRentals = $todayCount->fetchColumn();
?>
	<div class="admin-layout" style="display: flex; min-height: 100vh; background: #f3f4f6;">
		<?php renderAgentSidebar('reports'); ?>
		<main class="admin-main" style="flex: 1; margin-left: 250px; padding: 2rem;">
			<header style="margin-bottom: 2rem;">
				<h1 style="font-size: 1.875rem; font-weight: 600; color: #111827;">My Reports</h1>
				<p style="color: #6b7280;">Your activity and currently rented cars.</p>
			</header>

			<div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; margin-bottom: 2rem;">
				<div style="background: linear-gradient(135deg, #f6893b, #d8851d); padding: 1.5rem; border-radius: 0.75rem; color: white;">
					<h3 style="font-size: 0.875rem; opacity: 0.9;">Rentals Today</h3>
					<p style="font-size: 2rem; font-weight: 700;"><?php echo $todayRentals; ?></p>
				</div>
				<div style="background: white; padding: 1.5rem; border-radius: 0.75rem; border: 1px solid #e5e7eb;">
					<h3 style="font-size: 0.875rem; color: #6b7280;">Total Handled</h3>
					<p style="font-size: 2rem; font-weight: 700; color: #1f2937;"><?php echo count($myRentals); ?></p>
				</div>
				<div style="background: white; padding: 1.5rem; border-radius: 0.75rem; border: 1px solid #e5e7eb;">
					<h3 style="font-size: 0.875rem; color: #6b7280;">Currently Rented</h3>
					<p style="font-size: 2rem; font-weight: 700; color: #f59e0b;"><?php echo count($currentlyRented); ?></p>
				</div>
			</div>

			<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
				<div style="background: white; border-radius: 0.75rem; border: 1px solid #e5e7eb; overflow: hidden;">
					<div style="padding: 1rem; border-bottom: 1px solid #e5e7eb; font-weight: 600;">Currently Rented Cars</div>
					<?php if (empty($currentlyRented)): ?><div style="padding: 2rem; text-align: center; color: #9ca3af;">No cars currently rented</div>
					<?php else: ?><table style="width: 100%; border-collapse: collapse;">
							<tbody>
								<?php foreach ($currentlyRented as $cr): ?>
									<tr style="border-bottom: 1px solid #e5e7eb;">
										<td style="padding: 0.75rem 1rem; font-weight: 500;"><?php echo htmlspecialchars($cr['brand'] . ' ' . $cr['model']); ?></td>
										<td style="padding: 0.75rem 1rem; color: #6b7280; font-size: 0.875rem;"><?php echo date('M d', strtotime($cr['start_date'])); ?> - <?php echo date('M d', strtotime($cr['end_date'])); ?></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table><?php endif; ?>
				</div>
				<div style="background: white; border-radius: 0.75rem; border: 1px solid #e5e7eb; overflow: hidden;">
					<div style="padding: 1rem; border-bottom: 1px solid #e5e7eb; font-weight: 600;">My Recent Rentals</div>
					<?php if (empty($myRentals)): ?><div style="padding: 2rem; text-align: center; color: #9ca3af;">No rentals handled yet</div>
					<?php else: ?><table style="width: 100%; border-collapse: collapse;">
							<tbody>
								<?php foreach (array_slice($myRentals, 0, 10) as $mr): ?>
									<tr style="border-bottom: 1px solid #e5e7eb;">
										<td style="padding: 0.75rem 1rem; font-weight: 500;"><?php echo htmlspecialchars($mr['first_name'] . ' ' . $mr['last_name']); ?></td>
										<td style="padding: 0.75rem 1rem; color: #6b7280;"><?php echo htmlspecialchars($mr['brand'] . ' ' . $mr['model']); ?></td>
										<td style="padding: 0.75rem 1rem; font-size: 0.75rem;"><span style="padding: 0.125rem 0.375rem; border-radius: 9999px; <?php echo $mr['status'] === 'ongoing' ? 'background:#dbeafe;color:#1e40af;' : 'background:#dcfce7;color:#166534;'; ?>"><?php echo ucfirst($mr['status']); ?></span></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table><?php endif; ?>
				</div>
			</div>
		</main>
	</div>
<?php
		}

		// MECHANIC FUNCTIONS

		function renderMechanicSidebar($activePage = 'dashboard')
		{
?>
	<aside class="admin-sidebar" style="width: 250px; background: white; border-right: 1px solid #e5e7eb; position: fixed; height: 100%; overflow-y: auto;">
		<div style="padding: 1.5rem;">
			<h1 style="font-size: 1.5rem; font-weight: 700; color: #1f2937;">LuxDrive</h1>
			<p style="font-size: 0.875rem; color: #6b7280;">Workshop Panel</p>
		</div>
		<nav style="padding: 0 1rem;">
			<ul style="list-style: none; padding: 0; margin: 0;">
				<li><a href="?page=mechanic_dashboard" style="display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem 1rem; color: <?php echo $activePage === 'dashboard' ? '#f59e0b' : '#374151'; ?>; background: <?php echo $activePage === 'dashboard' ? '#fef3c7' : 'transparent'; ?>; text-decoration: none; border-radius: 0.5rem; margin-bottom: 0.25rem;">
						<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
							<rect x="3" y="3" width="7" height="9" />
							<rect x="14" y="3" width="7" height="5" />
							<rect x="14" y="12" width="7" height="9" />
							<rect x="3" y="16" width="7" height="5" />
						</svg>
						Dashboard
					</a></li>
				<li><a href="?page=mechanic_maintenance" style="display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem 1rem; color: <?php echo $activePage === 'maintenance' ? '#f59e0b' : '#374151'; ?>; background: <?php echo $activePage === 'maintenance' ? '#fef3c7' : 'transparent'; ?>; text-decoration: none; border-radius: 0.5rem; margin-bottom: 0.25rem;">
						<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
							<path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z" />
						</svg>
						Maintenance
					</a></li>
				<li style="margin-top: 2rem; border-top: 1px solid #e5e7eb; padding-top: 1rem;"><a href="?page=logout" style="display: block; padding: 0.75rem 1rem; color: #ef4444; text-decoration: none; border-radius: 0.5rem;">Logout</a></li>
			</ul>
		</nav>
	</aside>
<?php
		}

		function renderMechanicDashboard()
		{
			if (!isMechanic()) {
				header('Location: himihiba.php?page=auth');
				exit;
			}
			global $pdo;
			$userId = $_SESSION['user_id'];

			// Stats
			$stmt = $pdo->prepare("SELECT SUM(cost) FROM maintenance WHERE staff_id = ? AND status = 'completed'");
			$stmt->execute([$userId]);
			$myRevenue = $stmt->fetchColumn() ?: 0;

			// Active Jobs
			$stmt = $pdo->prepare("SELECT COUNT(*) FROM maintenance WHERE staff_id = ? AND status = 'in_progress'");
			$stmt->execute([$userId]);
			$activeJobs = $stmt->fetchColumn();

			// Total Completed
			$stmt = $pdo->prepare("SELECT COUNT(*) FROM maintenance WHERE staff_id = ? AND status = 'completed'");
			$stmt->execute([$userId]);
			$completedJobs = $stmt->fetchColumn();

			// Active Maintenance Jobs (My List)
			$activeList = $pdo->prepare("
			SELECT m.*, c.brand, c.model, c.license_plate, c.image_url 
			FROM maintenance m 
			JOIN cars c ON m.car_id = c.car_id 
			WHERE m.staff_id = ? AND m.status = 'in_progress'
			ORDER BY m.maintenance_date ASC
		");
			$activeList->execute([$userId]);
			$myActiveJobs = $activeList->fetchAll(PDO::FETCH_ASSOC);

			// Recent maintenance history
			$recentHistory = $pdo->prepare("
			SELECT m.*, c.brand, c.model, c.license_plate 
			FROM maintenance m 
			JOIN cars c ON m.car_id = c.car_id 
			WHERE m.staff_id = ? AND m.status = 'completed'
			ORDER BY m.maintenance_date DESC 
			LIMIT 5
		");
			$recentHistory->execute([$userId]);
			$recentHistory = $recentHistory->fetchAll(PDO::FETCH_ASSOC);
?>
	<div class="admin-layout" style="display: flex; min-height: 100vh; background: #f3f4f6;">
		<?php renderMechanicSidebar('dashboard'); ?>

		<main class="admin-main" style="flex: 1; margin-left: 250px; padding: 2rem;">
			<header style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
				<div>
					<h1 style="font-size: 1.875rem; font-weight: 600; color: #111827;">Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?></h1>
					<p style="color: #6b7280;">Revenue Dashboard & Active Jobs</p>
				</div>
				<a href="?page=mechanic_maintenance" style="background: #f59e0b; color: white; padding: 0.75rem 1.5rem; border: none; border-radius: 0.5rem; text-decoration: none; display: flex; align-items: center; gap: 0.5rem;">
					<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
						<line x1="12" y1="5" x2="12" y2="19"></line>
						<line x1="5" y1="12" x2="19" y2="12"></line>
					</svg>
					Go to Maintenance
				</a>
			</header>

			<!-- Stats Cards -->
			<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
				<div style="background: white; padding: 1.5rem; border-radius: 0.75rem; border: 1px solid #e5e7eb; border-left: 4px solid #10b981;">
					<div style="display: flex; justify-content: space-between; align-items: flex-start;">
						<div>
							<h3 style="font-size: 0.875rem; color: #6b7280; margin-bottom: 0.5rem;">My Revenue</h3>
							<p style="font-size: 2rem; font-weight: 700; color: #10b981;">$<?php echo number_format($myRevenue, 2); ?></p>
						</div>
						<div style="background: #d1fae5; padding: 0.75rem; border-radius: 0.5rem;">
							<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#059669" stroke-width="2">
								<path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" />
							</svg>
						</div>
					</div>
				</div>
				<div style="background: white; padding: 1.5rem; border-radius: 0.75rem; border: 1px solid #e5e7eb; border-left: 4px solid #f59e0b;">
					<div style="display: flex; justify-content: space-between; align-items: flex-start;">
						<div>
							<h3 style="font-size: 0.875rem; color: #6b7280; margin-bottom: 0.5rem;">Active Jobs</h3>
							<p style="font-size: 2rem; font-weight: 700; color: #f59e0b;"><?php echo $activeJobs; ?></p>
						</div>
						<div style="background: #fef3c7; padding: 0.75rem; border-radius: 0.5rem;">
							<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#d97706" stroke-width="2">
								<path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z" />
							</svg>
						</div>
					</div>
				</div>
				<div style="background: white; padding: 1.5rem; border-radius: 0.75rem; border: 1px solid #e5e7eb; border-left: 4px solid #3b82f6;">
					<div style="display: flex; justify-content: space-between; align-items: flex-start;">
						<div>
							<h3 style="font-size: 0.875rem; color: #6b7280; margin-bottom: 0.5rem;">Jobs Completed</h3>
							<p style="font-size: 2rem; font-weight: 700; color: #3b82f6;"><?php echo $completedJobs; ?></p>
						</div>
						<div style="background: #dbeafe; padding: 0.75rem; border-radius: 0.5rem;">
							<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2">
								<polyline points="20 6 9 17 4 12" />
							</svg>
						</div>
					</div>
				</div>
			</div>

			<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem;">
				<!-- Active Jobs List -->
				<div style="background: white; border-radius: 0.75rem; border: 1px solid #e5e7eb; overflow: hidden;">
					<div style="padding: 1.5rem; border-bottom: 1px solid #e5e7eb;">
						<h2 style="font-size: 1.125rem; font-weight: 600; color: #1f2937;">My Active Jobs</h2>
						<p style="color: #6b7280; font-size: 0.875rem;">Cars you are currently working on</p>
					</div>
					<?php if (empty($myActiveJobs)): ?>
						<div style="padding: 3rem; text-align: center; color: #9ca3af;">
							<p>No active jobs. Go to Maintenance to accept new cars.</p>
						</div>
					<?php else: ?>
						<div style="padding: 1rem;">
							<?php foreach ($myActiveJobs as $job): ?>
								<div style="display: flex; gap: 1rem; padding: 1rem; border: 1px solid #e5e7eb; border-radius: 0.5rem; margin-bottom: 1rem; background: #fffbeb;">
									<img src="<?php echo htmlspecialchars($job['image_url'] ?: 'https://images.unsplash.com/photo-1503376763036-066120622c74?w=100&q=80'); ?>" style="width: 80px; height: 60px; object-fit: cover; border-radius: 0.25rem;">
									<div style="flex: 1;">
										<h3 style="font-weight: 600; color: #111827;"><?php echo htmlspecialchars($job['brand'] . ' ' . $job['model']); ?></h3>
										<p style="font-size: 0.875rem; color: #6b7280;"><?php echo htmlspecialchars($job['license_plate']); ?></p>
										<p style="font-size: 0.875rem; color: #92400e; margin-top: 0.5rem;">Issue: <?php echo htmlspecialchars($job['description']); ?></p>
									</div>
									<div style="display: flex; flex-direction: column; justify-content: center;">
										<a href="?page=mechanic_maintenance" style="padding: 0.5rem 1rem; background: #f59e0b; color: white; border-radius: 0.375rem; text-decoration: none; font-size: 0.875rem; text-align: center;">Complete Job</a>
									</div>
								</div>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</div>

				<!-- Recent Work History -->
				<div style="background: white; border-radius: 0.75rem; border: 1px solid #e5e7eb; overflow: hidden;">
					<div style="padding: 1.5rem; border-bottom: 1px solid #e5e7eb;">
						<h2 style="font-size: 1.125rem; font-weight: 600; color: #1f2937;">History</h2>
						<p style="font-size: 0.875rem; color: #6b7280;">Recently completed</p>
					</div>
					<?php if (empty($recentHistory)): ?>
						<div style="padding: 3rem; text-align: center; color: #9ca3af;">No history yet</div>
					<?php else: ?>
						<div style="padding: 1rem;">
							<?php foreach ($recentHistory as $record): ?>
								<div style="padding: 0.75rem; border-radius: 0.5rem; background: #f9fafb; margin-bottom: 0.5rem;">
									<div style="display: flex; justify-content: space-between; margin-bottom: 0.25rem;">
										<span style="font-weight: 500; color: #111827; font-size: 0.875rem;"><?php echo htmlspecialchars($record['brand'] . ' ' . $record['model']); ?></span>
										<span style="font-weight: 600; color: #10b981; font-size: 0.875rem;">+$<?php echo number_format($record['cost'], 2); ?></span>
									</div>
									<p style="font-size: 0.75rem; color: #6b7280;"><?php echo htmlspecialchars(substr($record['description'], 0, 40)); ?></p>
									<p style="font-size: 0.75rem; color: #9ca3af; margin-top: 0.25rem;"><?php echo date('M d, Y', strtotime($record['maintenance_date'])); ?></p>
								</div>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</div>
			</div>
		</main>
	</div>
<?php
		}


		// MECHANIC MAINTENANCE MODULE
		function handleMechanicAcceptJob()
		{
			global $pdo;
			if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_POST['action'] ?? '') !== 'mechanic_accept_job') return null;

			$carId = intval($_POST['car_id']);
			$staffId = $_SESSION['user_id'];
			$date = date('Y-m-d');

			try {
				// Check if car is already in progress
				$check = $pdo->prepare("SELECT COUNT(*) FROM maintenance WHERE car_id = ? AND status = 'in_progress'");
				$check->execute([$carId]);
				if ($check->fetchColumn() > 0) return ['error' => 'Car is already being worked on by someone.'];

				$stmt = $pdo->prepare("INSERT INTO maintenance (car_id, staff_id, description, cost, maintenance_date, status, performed_by) VALUES (?, ?, 'Maintenance started', 0, ?, 'in_progress', 'Mechanic')");
				$stmt->execute([$carId, $staffId, $date]);
				return ['success' => 'Job accepted. You can now work on this car.'];
			} catch (PDOException $e) {
				return ['error' => 'Failed to accept job.'];
			}
		}

		function handleMechanicCompleteJob()
		{
			global $pdo;
			if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_POST['action'] ?? '') !== 'mechanic_complete_job') return null;

			$maintenanceId = intval($_POST['maintenance_id']);
			$carId = intval($_POST['car_id']);
			$description = sanitize($_POST['description']);
			$cost = floatval($_POST['cost']);
			$date = date('Y-m-d');

			if (empty($maintenanceId) || empty($description)) {
				return ['error' => 'Description is required.'];
			}

			try {
				// Update maintenance record
				$stmt = $pdo->prepare("UPDATE maintenance SET description = ?, cost = ?, status = 'completed', maintenance_date = ? WHERE maintenance_id = ?");
				$stmt->execute([$description, $cost, $date, $maintenanceId]);

				// Set car back to available
				$pdo->prepare("UPDATE cars SET status = 'available' WHERE car_id = ?")->execute([$carId]);

				return ['success' => 'Job completed. Car is now available. Revenue recorded.'];
			} catch (PDOException $e) {
				return ['error' => 'Failed to complete job.'];
			}
		}

		function renderMechanicMaintenance()
		{
			if (!isMechanic()) {
				header('Location: himihiba.php?page=auth');
				exit;
			}
			global $pdo;
			$userId = $_SESSION['user_id'];

			$result = handleMechanicAcceptJob();
			if (!$result) $result = handleMechanicCompleteJob();

			// 1. Available Cars 
			$availableCars = $pdo->query("
			SELECT c.* 
			FROM cars c 
			WHERE c.status = 'maintenance' 
			AND c.car_id NOT IN (SELECT car_id FROM maintenance WHERE status = 'in_progress')
			ORDER BY c.brand, c.model
		")->fetchAll(PDO::FETCH_ASSOC);

			// 2. My Active Jobs
			$myActiveJobs = $pdo->prepare("
			SELECT m.*, c.brand, c.model, c.license_plate, c.image_url 
			FROM maintenance m 
			JOIN cars c ON m.car_id = c.car_id 
			WHERE m.staff_id = ? AND m.status = 'in_progress'
			ORDER BY m.maintenance_date ASC
		");
			$myActiveJobs->execute([$userId]);
			$myActiveJobs = $myActiveJobs->fetchAll(PDO::FETCH_ASSOC);

			// 3. My History
			$myHistory = $pdo->prepare("
			SELECT m.*, c.brand, c.model, c.license_plate 
			FROM maintenance m 
			JOIN cars c ON m.car_id = c.car_id 
			WHERE m.staff_id = ? AND m.status = 'completed'
			ORDER BY m.maintenance_date DESC
		");
			$myHistory->execute([$userId]);
			$myHistory = $myHistory->fetchAll(PDO::FETCH_ASSOC);
?>
	<div class="admin-layout" style="display: flex; min-height: 100vh; background: #f3f4f6;">
		<?php renderMechanicSidebar('maintenance'); ?>

		<main class="admin-main" style="flex: 1; margin-left: 250px; padding: 2rem;">
			<header style="margin-bottom: 2rem;">
				<h1 style="font-size: 1.875rem; font-weight: 600; color: #111827;">Maintenance Management</h1>
				<p style="color: #6b7280;">Accept jobs and mark them as completed.</p>
			</header>

			<?php if ($result && isset($result['success'])): ?>
				<div style="background: #dcfce7; color: #16a34a; padding: 1rem; border-radius: 8px; margin-bottom: 2rem; border: 1px solid #bbf7d0;"><?php echo htmlspecialchars($result['success']); ?></div>
			<?php endif; ?>
			<?php if ($result && isset($result['error'])): ?>
				<div style="background: #fee2e2; color: #dc2626; padding: 1rem; border-radius: 8px; margin-bottom: 2rem; border: 1px solid #fecaca;"><?php echo htmlspecialchars($result['error']); ?></div>
			<?php endif; ?>

			<div style="display: flex; gap: 2rem; flex-direction: column;">

				<!-- SECTION 1: AVAILABLE CARS -->
				<div style="background: white; border-radius: 0.75rem; border: 1px solid #e5e7eb; overflow: hidden;">
					<div style="padding: 1.5rem; border-bottom: 1px solid #e5e7eb; background: #fff7ed;">
						<h2 style="font-size: 1.25rem; font-weight: 600; color: #9a3412;">Cars Needing Service</h2>
						<p style="font-size: 0.875rem; color: #9a3412; opacity: 0.8;">Cars marked as 'Maintenance' but not yet assigned.</p>
					</div>
					<?php if (empty($availableCars)): ?>
						<div style="padding: 3rem; text-align: center; color: #9ca3af;">No pending cars available.</div>
					<?php else: ?>
						<div style="padding: 1rem; display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 1rem;">
							<?php foreach ($availableCars as $car): ?>
								<div style="border: 1px solid #e5e7eb; border-radius: 0.5rem; padding: 1rem; background: white;">
									<div style="display: flex; gap: 1rem; margin-bottom: 1rem;">
										<img src="<?php echo htmlspecialchars($car['image_url'] ?: 'https://images.unsplash.com/photo-1503376763036-066120622c74?w=100&q=80'); ?>" style="width: 60px; height: 40px; object-fit: cover; border-radius: 0.25rem;">
										<div>
											<h3 style="font-weight: 600; font-size: 1rem;"><?php echo htmlspecialchars($car['brand'] . ' ' . $car['model']); ?></h3>
											<p style="color: #6b7280; font-size: 0.875rem;"><?php echo htmlspecialchars($car['license_plate']); ?></p>
										</div>
									</div>
									<form method="POST" action="?page=mechanic_maintenance">
										<input type="hidden" name="action" value="mechanic_accept_job">
										<input type="hidden" name="car_id" value="<?php echo $car['car_id']; ?>">
										<button type="submit" style="width: 100%; padding: 0.75rem; background: #f59e0b; color: white; border: none; border-radius: 0.5rem; cursor: pointer; font-weight: 500;">Accept Job</button>
									</form>
								</div>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</div>

				<div style="background: white; border-radius: 0.75rem; border: 1px solid #e5e7eb; overflow: hidden;">
					<div style="padding: 1.5rem; border-bottom: 1px solid #e5e7eb; background: #eff6ff;">
						<h2 style="font-size: 1.25rem; font-weight: 600; color: #1e40af;">My Active Jobs</h2>
						<p style="font-size: 0.875rem; color: #1e40af; opacity: 0.8;">Jobs you are currently working on.</p>
					</div>
					<?php if (empty($myActiveJobs)): ?>
						<div style="padding: 3rem; text-align: center; color: #9ca3af;">You have no active jobs. Accept one above!</div>
					<?php else: ?>
						<table style="width: 100%; border-collapse: collapse; text-align: left;">
							<thead style="background: #f9fafb; border-bottom: 1px solid #e5e7eb;">
								<tr>
									<th style="padding: 1rem; font-weight: 600; color: #4b5563;">Vehicle</th>
									<th style="padding: 1rem; font-weight: 600; color: #4b5563;">Started On</th>
									<th style="padding: 1rem; font-weight: 600; color: #4b5563;">Action</th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ($myActiveJobs as $job): ?>
									<tr style="border-bottom: 1px solid #e5e7eb;">
										<td style="padding: 1rem;">
											<div style="font-weight: 500; color: #111827;"><?php echo htmlspecialchars($job['brand'] . ' ' . $job['model']); ?></div>
											<div style="font-size: 0.875rem; color: #6b7280;"><?php echo htmlspecialchars($job['license_plate']); ?></div>
										</td>
										<td style="padding: 1rem; color: #6b7280;"><?php echo date('M d, Y', strtotime($job['maintenance_date'])); ?></td>
										<td style="padding: 1rem;">
											<button onclick='openCompleteModal(<?php echo json_encode($job); ?>)' style="padding: 0.5rem 1rem; background: #10b981; color: white; border: none; border-radius: 0.375rem; cursor: pointer;">Complete Job</button>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					<?php endif; ?>
				</div>

				<div style="background: white; border-radius: 0.75rem; border: 1px solid #e5e7eb; overflow: hidden;">
					<div style="padding: 1.5rem; border-bottom: 1px solid #e5e7eb;">
						<h2 style="font-size: 1.25rem; font-weight: 600; color: #1f2937;">Completed Jobs History</h2>
					</div>
					<?php if (empty($myHistory)): ?>
						<div style="padding: 3rem; text-align: center; color: #9ca3af;">No maintenance records yet.</div>
					<?php else: ?>
						<table style="width: 100%; border-collapse: collapse; text-align: left;">
							<thead style="background: #f9fafb;">
								<tr>
									<th style="padding: 0.75rem 1rem; font-weight: 600; color: #4b5563; font-size: 0.875rem;">Date</th>
									<th style="padding: 0.75rem 1rem; font-weight: 600; color: #4b5563; font-size: 0.875rem;">Vehicle</th>
									<th style="padding: 0.75rem 1rem; font-weight: 600; color: #4b5563; font-size: 0.875rem;">Description</th>
									<th style="padding: 0.75rem 1rem; font-weight: 600; color: #4b5563; font-size: 0.875rem;">Cost</th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ($myHistory as $h): ?>
									<tr style="border-bottom: 1px solid #e5e7eb;">
										<td style="padding: 0.75rem 1rem; color: #6b7280; font-size: 0.875rem;"><?php echo date('M d, Y', strtotime($h['maintenance_date'])); ?></td>
										<td style="padding: 0.75rem 1rem;">
											<p style="font-weight: 500; color: #111827; font-size: 0.875rem;"><?php echo htmlspecialchars($h['brand'] . ' ' . $h['model']); ?></p>
											<p style="font-size: 0.75rem; color: #9ca3af;"><?php echo htmlspecialchars($h['license_plate']); ?></p>
										</td>
										<td style="padding: 0.75rem 1rem; color: #374151; font-size: 0.875rem; max-width: 300px;"><?php echo htmlspecialchars(substr($h['description'], 0, 60)) . (strlen($h['description']) > 60 ? '...' : ''); ?></td>
										<td style="padding: 0.75rem 1rem; font-weight: 600; color: #1f2937;">$<?php echo number_format($h['cost'], 2); ?></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					<?php endif; ?>
				</div>
			</div>
		</main>
	</div>

	<div id="completeModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); align-items: center; justify-content: center; z-index: 1000;">
		<div style="background: white; padding: 2rem; border-radius: 1rem; width: 100%; max-width: 500px;">
			<header style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
				<h2 style="font-size: 1.5rem; font-weight: 600;">Complete Maintenance</h2>
				<button onclick="document.getElementById('completeModal').style.display='none'" style="background: none; border: none; cursor: pointer; font-size: 1.5rem; color: #6b7280;">&times;</button>
			</header>
			<form method="POST" action="?page=mechanic_maintenance">
				<input type="hidden" name="action" value="mechanic_complete_job">
				<input type="hidden" name="maintenance_id" id="comp_maintenance_id">
				<input type="hidden" name="car_id" id="comp_car_id">

				<div style="margin-bottom: 1rem;">
					<label style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 0.25rem;">Vehicle</label>
					<input type="text" id="comp_vehicle_name" disabled style="width: 100%; padding: 0.5rem; background: #f3f4f6; border: 1px solid #d1d5db; border-radius: 0.375rem;">
				</div>

				<div style="margin-bottom: 1rem;">
					<label style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 0.25rem;">Work Description</label>
					<textarea name="description" required rows="4" style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem;" placeholder="What was fixed?"></textarea>
				</div>

				<div style="margin-bottom: 1.5rem;">
					<label style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 0.25rem;">himihiba Cost ($)</label>
					<input type="number" step="0.01" name="cost" required style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem;" placeholder="0.00">
				</div>

				<div style="display: flex; gap: 1rem; justify-content: flex-end;">
					<button type="button" onclick="document.getElementById('completeModal').style.display='none'" style="padding: 0.5rem 1rem; border: 1px solid #d1d5db; background: white; border-radius: 0.375rem; cursor: pointer;">Cancel</button>
					<button type="submit" style="padding: 0.5rem 1rem; background: #10b981; color: white; border: none; border-radius: 0.375rem; cursor: pointer;">Mark Completed</button>
				</div>
			</form>
		</div>
	</div>

	<script>
		function openCompleteModal(job) {
			document.getElementById('comp_maintenance_id').value = job.maintenance_id;
			document.getElementById('comp_car_id').value = job.car_id;
			document.getElementById('comp_vehicle_name').value = job.brand + ' ' + job.model + ' (' + job.license_plate + ')';
			document.getElementById('completeModal').style.display = 'flex';
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
			case 'logout':
				handleLogout();
				break;
			case 'profile':
				renderProfile();
				break;
			case 'rental_history':
				renderRentalHistory();
				break;
			case 'super_admin_dashboard':
				renderSuperAdminDashboard();
				break;
			case 'manage_agencies':
				renderManageAgencies();
				break;
			case 'admin_dashboard':
				renderAdminDashboard();
				break;
			case 'admin_staff':
				renderAdminStaff();
				break;
			case 'admin_cars':
				renderAdminCars();
				break;
			case 'admin_clients':
				renderAdminClients();
				break;
			case 'admin_reports':
				renderAdminReports();
				break;
			case 'admin_rentals':
				renderAdminRentals();
				break;
			case 'admin_maintenance':
				renderAdminMaintenance();
				break;
			// Agent Routes
			case 'agent_dashboard':
				renderAgentDashboard();
				break;
			case 'agent_clients':
				renderAgentClients();
				break;
			case 'agent_cars':
				renderAgentCars();
				break;
			case 'agent_rentals':
				renderAgentRentals();
				break;
			case 'agent_payments':
				renderAgentPayments();
				break;
			case 'agent_maintenance':
				renderAgentMaintenance();
				break;
			case 'agent_reports':
				renderAgentReports();
				break;
			// Mechanic Routes
			case 'mechanic_dashboard':
				renderMechanicDashboard();
				break;
			case 'mechanic_maintenance':
				renderMechanicMaintenance();
				break;
			default:
				renderHomePage();
		}

		renderFooter();
?>