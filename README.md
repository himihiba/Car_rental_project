# 🚗 LUXDRIVE - Luxury Car Rental Management System

A comprehensive web-based car rental management system built with PHP and MySQL, designed for luxury car rental agencies to manage their fleet, clients, staff, and rental operations.

## 📋 Table of Contents

- [Features](#features)
- [Technologies Used](#technologies-used)
- [Database Schema](#database-schema)
- [Installation](#installation)
- [Configuration](#configuration)
- [User Roles & Default Credentials](#user-roles--default-credentials)
- [Project Structure](#project-structure)
- [Key Functionalities](#key-functionalities)
- [Database Features](#database-features)
- [Security](#security)
- [Usage Guide](#usage-guide)

## ✨ Features

### For Clients
- **User Registration & Authentication** - Secure account creation and login
- **Browse Available Cars** - View luxury car fleet with detailed specifications
- **Advanced Car Search** - Filter by brand, type, transmission, and more
- **Online Booking System** - Reserve cars with date selection and pricing calculation
- **Rental History** - Track current and past rentals
- **Payment Management** - Multiple payment methods (cash, credit card, debit card, bank transfer)
- **Profile Management** - Update personal information
- **Rental Cancellation** - Cancel ongoing rentals when needed

### For Staff
- **Role-Based Access Control** - Different permissions for Super Admin, Admin, Agent, and Mechanic
- **Agency Management** - Manage multiple agency branches
- **Fleet Management** - Add, update, and track vehicle status
- **Client Management** - View and manage client information
- **Rental Processing** - Handle rental bookings and completions
- **Maintenance Tracking** - Schedule and track vehicle maintenance
- **Payment Tracking** - Monitor payment status and revenue
- **Staff Management** - Manage employees across agencies

### System Features
- **Multi-Agency Support** - Manage multiple rental locations
- **Automated Status Updates** - Automatic car status changes based on rental state
- **Revenue Analytics** - View revenue by month
- **Database Triggers** - Automated car status updates on rental events
- **Stored Procedures** - Efficient rental completion processing
- **Sample Data** - Pre-loaded demo data for testing

## 🛠 Technologies Used

- **Backend:** PHP 7.4+
- **Database:** MySQL 5.7+
- **Database Access:** PDO (PHP Data Objects)
- **Session Management:** PHP Sessions
- **Password Security:** PHP password_hash() with bcrypt
- **Frontend:** HTML5, CSS3 (embedded in PHP)
- **Hosting:** Compatible with XAMPP, InfinityFree, and other PHP hosting platforms

## 🗄 Database Schema

### Tables Structure

#### 1. **agencies**
Stores information about different rental agency branches.
```sql
- agency_id (PK)
- name
- city
- address
- contact_email
- created_at
```

#### 2. **clients**
Manages customer information and authentication.
```sql
- client_id (PK)
- first_name, last_name
- email (UNIQUE)
- phone
- driver_license (UNIQUE)
- password_hash
- address
- registration_date
```

#### 3. **staff**
Stores employee information with role-based access.
```sql
- staff_id (PK)
- agency_id (FK)
- first_name, last_name
- email (UNIQUE)
- phone
- role (super_admin, admin, agent, mechanic)
- password_hash
- hire_date
```

#### 4. **cars**
Contains vehicle inventory with detailed specifications.
```sql
- car_id (PK)
- agency_id (FK)
- brand, model, year
- license_plate (UNIQUE)
- color, mileage
- status (available, rented, maintenance)
- daily_price
- image_url
- car_type, seats, transmission, fuel_type
```

#### 5. **rentals**
Tracks all rental transactions.
```sql
- rental_id (PK)
- agency_id, client_id, car_id, staff_id (FKs)
- start_date, end_date
- total_price
- status (ongoing, completed, cancelled)
- extras
- created_at
```

#### 6. **payments**
Records all payment transactions.
```sql
- payment_id (PK)
- rental_id (FK)
- amount
- method (cash, credit_card, debit_card, bank_transfer)
- payment_date
- status (paid, pending, refunded)
```

#### 7. **maintenance**
Tracks vehicle maintenance records.
```sql
- maintenance_id (PK)
- car_id, staff_id (FKs)
- description
- cost
- maintenance_date
- status (pending, in_progress, completed)
- performed_by
```

## 📦 Installation

### Prerequisites
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Web server (Apache/Nginx) or XAMPP
- PDO PHP extension

### Steps

1. **Clone or Download the Project**
   ```bash
   git clone https://github.com/himihiba/Car_rental_project.git
   cd Car_rental_project
   ```

2. **Set Up Web Server**
   - For XAMPP: Place the project folder in `xampp/htdocs/`
   - Access via: `http://localhost/Car_rental_project/himihiba.php`

3. **Configure Database Connection**
   - Edit the database configuration in `himihiba.php` (lines 5-8):
   ```php
   $host = "your_database_host";      // e.g., "localhost" or "sql300.infinityfree.com"
   $user = "your_database_username";
   $pass = "your_database_password";
   $dbname = "your_database_name";
   ```

4. **Initialize Database**
   - The system automatically creates all tables on first run
   - Sample data is populated automatically
   - No manual SQL import required!

5. **Set Permissions**
   - Ensure the `uploads/cars/` directory is writable
   ```bash
   chmod -R 755 uploads/
   ```

6. **Access the Application**
   - Navigate to: `http://localhost/Car_rental_project/himihiba.php`
   - The database will initialize automatically on first load

## ⚙️ Configuration

### Database Configuration
Edit in `himihiba.php`:
```php
$host = "sql300.infinityfree.com";        // Database host
$user = "if0_40660033";                    // Database username
$pass = "garassa08";                       // Database password
$dbname = "if0_40660033_luxdriveDB";      // Database name
```

### Session Configuration
The system uses a custom session name for security:
```php
session_name("himihiba_carRental");
```

### File Upload Directory
Car images are stored in: `uploads/cars/`

## 👥 User Roles & Default Credentials

The system comes with pre-configured demo accounts:

### Client Accounts
| Name | Email | Password | Driver License |
|------|-------|----------|----------------|
| Alice Moreau | client@email.com | client123 | DL-CLIENT-01 |
| Bob Durand | bob.durand@email.com | client456 | DL-CLIENT-02 |

### Staff Accounts

#### Super Administrator
- **Email:** super@luxdrive.com
- **Password:** super123
- **Access:** All agencies, full system control

#### Paris Agency Staff
| Role | Name | Email | Password |
|------|------|-------|----------|
| Admin | Marie Dupont | admin1@luxdrive.com | admin123 |
| Agent | Lucas Girard | agent1@luxdrive.com | agent123 |
| Mechanic | Emma Petit | mech1@luxdrive.com | mech123 |

#### Lyon Agency Staff
| Role | Name | Email | Password |
|------|------|-------|----------|
| Admin | Thomas Bernard | admin2@luxdrive.com | admin123 |
| Agent | Sophie Martin | agent2@luxdrive.com | agent123 |
| Mechanic | Hugo Laurent | mech2@luxdrive.com | mech123 |

## 📁 Project Structure

```
Car_rental_project/
├── himihiba.php              # Main application file (all-in-one)
├── todolist.todo             # Project task list
├── uploads/                  # File upload directory
│   └── cars/                 # Car image storage
└── README.md                 # Project documentation
```

### Code Organization in himihiba.php

The main file is structured into sections:

1. **Configuration** (Lines 1-10)
   - Database credentials
   - Session initialization

2. **Database Functions** (Lines 12-300)
   - `initializeDatabase()` - Database setup
   - `createTables()` - Schema creation
   - `insertSampleData()` - Demo data
   - `createTriggersAndViews()` - Advanced SQL features

3. **Authentication Functions** (Lines 341-474)
   - `handleLogin()` - User login
   - `handleRegister()` - Client registration
   - `handleLogout()` - Session termination
   - Authorization helpers

4. **Business Logic Functions** (Lines 479-694)
   - Car management
   - Rental operations
   - Payment processing
   - Profile management

5. **Frontend/UI** (Lines 714+)
   - HTML/CSS embedded in PHP
   - Responsive design
   - User interface components

## 🔑 Key Functionalities

### 1. Authentication System
- Secure password hashing using PHP's `password_hash()`
- Separate authentication for clients and staff
- Session-based user management
- Role-based access control

### 2. Rental Management
- Real-time car availability checking
- Automatic price calculation
- Date validation
- Status tracking (ongoing, completed, cancelled)

### 3. Payment Processing
- Multiple payment methods
- Payment status tracking
- Automatic payment record creation
- Revenue calculation by month

### 4. Agency Isolation
- Staff can only access their assigned agency data
- Super admins have cross-agency access
- Agency-specific car and rental management

### 5. Car Status Management
- Automatic status updates via database triggers
- Three states: available, rented, maintenance
- Real-time availability tracking

## 🔧 Database Features

### Triggers
1. **after_rental_insert**
   - Automatically sets car status to 'rented' when a new rental is created

2. **after_rental_complete**
   - Sets car status back to 'available' when rental is completed

### Stored Procedures
- **complete_rental(rental_id)** - Efficiently complete rental transactions

### Views
1. **currently_rented_cars**
   - Shows all active rentals with car and client details

2. **revenue_by_month**
   - Aggregates monthly revenue from paid payments

### Foreign Keys
- Full referential integrity
- CASCADE deletes for dependent records
- SET NULL for optional relationships

## 🔒 Security

### Implemented Security Measures
1. **Password Security**
   - Bcrypt hashing via `password_hash()`
   - Salted passwords
   - Never stores plain text passwords

2. **SQL Injection Prevention**
   - All queries use PDO prepared statements
   - Parameterized queries throughout

3. **XSS Prevention**
   - `sanitize()` function with `htmlspecialchars()`
   - Input validation and sanitization

4. **Session Security**
   - Custom session names
   - Secure session handling

5. **Input Validation**
   - Email uniqueness checking
   - Driver license validation
   - Password strength requirements (6+ characters)

### Security Recommendations
⚠️ **Before deploying to production:**

1. **Remove or Secure Demo Credentials**
   - Change all default passwords
   - Remove sample data in production

2. **Environment Variables**
   - Move database credentials to environment variables
   - Never commit credentials to version control

3. **HTTPS**
   - Use SSL/TLS certificates
   - Force HTTPS connections

4. **Additional Security Headers**
   - Implement CSRF tokens
   - Add security headers (CSP, X-Frame-Options)

5. **Password Policy**
   - Increase minimum password length
   - Require password complexity

## 📖 Usage Guide

### For Clients

1. **Registration**
   - Navigate to registration page
   - Fill in personal details and driver license
   - Create secure password
   - Verify email uniqueness

2. **Browsing Cars**
   - View available luxury cars
   - Filter by type, brand, price
   - Check car specifications

3. **Making a Reservation**
   - Select desired car
   - Choose rental dates
   - Review total price
   - Select payment method
   - Confirm booking

4. **Managing Rentals**
   - View active rentals
   - Check rental history
   - Cancel ongoing rentals (if needed)

### For Staff

1. **Admin Functions**
   - Manage agency operations
   - Oversee rentals and payments
   - Staff management
   - Revenue reporting

2. **Agent Functions**
   - Process rental bookings
   - Handle customer inquiries
   - Manage car availability

3. **Mechanic Functions**
   - Update maintenance records
   - Track vehicle service history
   - Manage car repair status

### For Super Admin

1. **System-Wide Management**
   - Access all agencies
   - Manage all staff members
   - View global statistics
   - Configure system settings

## 🚀 Sample Data Included

The system includes pre-loaded data for testing:

- **2 Agencies:** Paris HQ and Lyon Branch
- **7 Staff Members:** 1 Super Admin, 2 Admins, 2 Agents, 2 Mechanics
- **2 Clients:** Test accounts with complete profiles
- **8 Luxury Cars:** Mercedes, Porsche, Ferrari, Range Rover, BMW, Audi, Lamborghini, Bentley
- **3 Sample Rentals:** Ongoing and completed rentals
- **3 Payment Records:** Various payment statuses
- **1 Maintenance Record:** In-progress maintenance

## 🔄 Database Reset

To reset the database to initial state:

1. Delete the `agencies` table from your database
2. Reload the application
3. The system will automatically:
   - Drop all existing tables
   - Recreate schema
   - Insert fresh sample data

## 📝 Notes

- The system uses a single-file architecture (`himihiba.php`) containing all backend and frontend code
- All CSS is embedded within the PHP file
- Database initialization happens automatically on first run
- No separate SQL dump file is needed
- Images are loaded from Unsplash URLs (external links)

## 🐛 Troubleshooting

### Database Connection Issues
- Verify MySQL service is running
- Check database credentials
- Ensure PDO extension is enabled

### Permission Errors
- Check file permissions on uploads directory
- Ensure web server has write access

### Session Issues
- Clear browser cookies
- Check PHP session configuration
- Verify session directory is writable

## 📄 License

This project is developed for educational and commercial purposes. Please ensure compliance with local regulations regarding car rental operations.

## 👨‍💻 Developer

- **Owner:** himihiba
- **Repository:** Car_rental_project
- **Platform:** GitHub

## 🤝 Contributing

Contributions are welcome! Feel free to submit issues or pull requests to improve the system.

---

**LuxDrive** - Elevating Luxury Car Rental Management 🚗✨
