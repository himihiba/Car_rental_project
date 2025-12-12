# LUXDRIVE - Luxury Car Rental Management System

A comprehensive web-based car rental management system built with PHP and MySQL, designed for luxury car rental agencies to manage their fleet, clients, staff, and rental operations.

## Live Demo

**[View Live Demo →](https://luxdrive.infinityfreeapp.com/himihiba.php)**

Try out the system with the demo accounts listed under the login form

## Table of Contents

- [Features](#features)
- [Technologies Used](#technologies-used)
- [Installation](#installation)
- [Configuration](#configuration)
- [User Roles & Default Credentials](#user-roles--default-credentials)
- [Project Structure](#project-structure)
- [Key Functionalities](#key-functionalities)
- [Security](#security)
- [Usage Guide](#usage-guide)

##Features

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

###System Features
- **Multi-Agency Support** - Manage multiple rental locations
- **Automated Status Updates** - Automatic car status changes based on rental state
- **Revenue Analytics** - View revenue by month
- **Database Triggers** - Automated car status updates on rental events
- **Stored Procedures** - Efficient rental completion processing
- **Sample Data** - Pre-loaded demo data for testing

##Technologies Used

- **Backend:** PHP 7.4+
- **Database:** MySQL 5.7+
- **Database Access:** PDO (PHP Data Objects)
- **Session Management:** PHP Sessions
- **Password Security:** PHP password_hash() with bcrypt
- **Frontend:** HTML5, CSS3 (embedded in PHP)
- **Hosting:** Compatible with XAMPP, InfinityFree, and other PHP hosting platforms

##Installation

###Prerequisites
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Web server (Apache/Nginx) or XAMPP

###Steps

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
   $host = "your_database_host";     
   $user = "your_database_username";
   $pass = "your_database_password";
   $dbname = "your_database_name";
   ```

4. **Initialize Database**
   - The system automatically creates all tables on first run
   - Sample data is populated automatically
   - No manual SQL import required!


6. **Access the Application**
   - Navigate to: `http://localhost/Car_rental_project/himihiba.php`
   - The database will initialize automatically on first load

###Session Configuration
The system uses a custom session name for security:
```php
session_name("himihiba_carRental");
```

###File Upload Directory
Car images are stored in: `uploads/cars/`

##User Roles & Default Credentials

The system comes with pre-configured demo accounts:

###Client Accounts
| Name | Email | Password | Driver License |
|------|-------|----------|----------------|
| Alice Moreau | client@email.com | client123 | DL-CLIENT-01 |
| Bob Durand | bob.durand@email.com | client456 | DL-CLIENT-02 |

###Staff Accounts

####Super Administrator
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

##Project Structure

```
Car_rental_project/
├── himihiba.php              # Main application file (all-in-one)
├── uploads/                  # File upload directory
│   └── cars/                 # Car image storage
└── README.md                 # Project documentation
```



##Key Functionalities

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

## Security

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

## Notes

- The system uses a single-file architecture (`himihiba.php`) containing all backend and frontend code
- All CSS is embedded within the PHP file
- Database initialization happens automatically on first run
- No separate SQL dump file is needed
- Images are loaded from Unsplash URLs (external links)

## License

This project is developed for educational and commercial purposes.

## Developer

- **Owner:** himihiba
- **Repository:** Car_rental_project
- **Platform:** GitHub

## Contributing

Contributions are welcome! Feel free to submit issues or pull requests to improve the system.

---

**LuxDrive** - Elevating Luxury Car Rental Management 
