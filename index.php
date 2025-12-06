<?php
session_start();
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LUXDRIVE</title>
    <link rel="stylesheet" href="styles.css">
</head>

<body>
    <!-- HEADER -->
    <header class="header" id="header">
        <div class="container flex-space">
            <a href="index.php?page=home" class="nav__logo">LUXDRIVE</a>
            <nav class="nav flex nav_ul nav_mobile">
                <ul class="nav__list flex ">
                    <li><a href="index.php?page=home" class="nav__links">Home</a></li>
                    <li><a href="index.php?page=browse" class="nav__links">Browse cars</a></li>
                    <li><a href="#contact" class="nav__links">Contact us</a></li>
                </ul>
                <?php if (isset($_SESSION['user_id'])) { ?>
                    <div class="flex-center gap-1">
                        <div class="profile flex-center"><span>AM</span></div>
                        <button><img width="30" height="30" src="https://img.icons8.com/material-rounded/24/expand-arrow--v1.png" alt="expand-arrow--v1" /></button>
                    </div>
                <?php } else { ?>
                    <ul class="flex auth-list" style="align-items: center;">
                        <li><button class="auth_link btn log_btn" id="openAuth">login</button></li>
                        <li><a href="index.php?page=signup" class="auth_link btn sign_btn">sign Up</a></li>
                    </ul>
                <?php } ?>
            </nav>
        </div>
    </header>

    <!--MAIN -->
    <main class="main">
        <section class="section hero_section">
            <div class="container flex-center hero_container" style="color: white; flex-direction: column; text-align: center;">
                <h1>DON'T RENT A CAR.</h1>
                <h1>RENT THE CAR.</h1>
                <p class="p_hero">Premium car rental at affordable rates. Worldwide.</p>
                <div class="btn_hero flex gap-2">
                    <a href="?page=browse" class="btn btn-brws">Browse Our Fleet</a>
                    <a href="?page=login" class="btn btn-bok">Book Now</a>
                </div>
                <form method="GET" action="?page=browse" class="search-form flex-column">
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
                                <option value="sedan">Sedan</option>
                                <option value="suv">SUV</option>
                                <option value="luxury">Luxury</option>
                                <option value="sports">Sports</option>
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
                    <div>
                        <span class="badge">Premium Benefits</span>
                    </div>
                    <h2 class="main-title">Why choose <span class="italic">LUXDRIVE</span>?</h2>
                    <p class="subtitle">
                        Experience unparalleled luxury with world-class service
                    </p>
                </div>
                <div class="grid-main">
                    <div class="featured-card">
                        <div class="card-blur"></div>
                        <div class="card-content">
                            <div class="icon-box">
                                <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"></path>
                                </svg>
                            </div>
                            <h3 class="card-title">Premium Fleet</h3>
                            <p class="card-description">
                                Drive the finest luxury vehicles from brands like Ferrari, Lamborghini, Rolls-Royce, and more. Meticulously maintained for perfection.
                            </p>
                            <div class="stat-display">
                                <div class="stat-number">350+</div>
                                <div class="stat-label">Exclusive vehicles</div>
                            </div>
                        </div>
                    </div>

                    <!-- Two Stacked Items -->
                    <div class="stacked-cards">
                        <div class="white-card">
                            <div class="small-icon-box">
                                <svg class="small-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"></path>
                                </svg>
                            </div>
                            <h3 class="small-title">Best Prices</h3>
                            <p class="small-description">Transparent pricing with no hidden fees. Premium quality at competitive rates.</p>
                        </div>

                        <div class="white-card">
                            <div class="small-icon-box">
                                <svg class="small-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"></path>
                                </svg>
                            </div>
                            <h3 class="small-title">24/7 Support</h3>
                            <p class="small-description">Dedicated concierge service available around the clock, wherever you are.</p>
                        </div>
                    </div>
                </div>

                <!-- Bottom Row - Three Equal Items -->
                <div class="grid-bottom">
                    <div class="gradient-card">
                        <div class="gradient-blur"></div>
                        <div class="card-content">
                            <svg class="icon" style="width: 3rem; height: 3rem; margin-bottom: 1rem; opacity: 0.9;" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            </svg>
                            <h3 class="small-title">Global Coverage</h3>
                            <p style="color: rgba(255, 255, 255, 0.9);">Available in 105 countries with thousands of locations.</p>
                        </div>
                    </div>

                    <div class="white-card">
                        <div class="small-icon-box">
                            <svg class="small-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <h3 class="small-title">Instant Booking</h3>
                        <p class="small-description">Reserve your dream car in minutes with immediate confirmation.</p>
                    </div>

                    <div class="white-card">
                        <div class="small-icon-box">
                            <svg class="small-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                            </svg>
                        </div>
                        <h3 class="small-title">Fully Insured</h3>
                        <p class="small-description">Comprehensive coverage on all vehicles for complete peace of mind.</p>
                    </div>
                </div>
            </div>
        </section>
        <section class=" section how-it-works-section">
            <div class="container">
                <!-- Header -->
                <div class="section-header">
                    <div>
                        <span class="badge">Simple Process</span>
                    </div>
                    <h2 class="main-title">How it works</h2>
                    <p class="subtitle">
                        Get on the road in three easy steps
                    </p>
                </div>
                <div class="timeline-container">
                    <!-- Horizontal Line -->
                    <div class="timeline-line"></div>

                    <div class="steps-grid">
                        <!-- Step 1 -->
                        <div class="step">
                            <div class="step-icon">
                                <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                </svg>
                            </div>
                            <div class="step-number">01</div>
                            <h3 class="step-title">Search and select</h3>
                            <p class="step-description">
                                Browse our premium collection and filter by brand, type, or features.
                            </p>
                        </div>

                        <!-- Step 2 -->
                        <div class="step">
                            <div class="step-icon">
                                <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                </svg>
                            </div>
                            <div class="step-number">02</div>
                            <h3 class="step-title">Book and customize</h3>
                            <p class="step-description">
                                Select dates, add extras, and get instant confirmation.
                            </p>
                        </div>

                        <!-- Step 3 -->
                        <div class="step">
                            <div class="step-icon">
                                <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"></path>
                                </svg>
                            </div>
                            <div class="step-number">03</div>
                            <h3 class="step-title">Pick up and drive</h3>
                            <p class="step-description">
                                Complete quick paperwork and hit the road in your dream car.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        <section class="section fleet-section" style="margin-top: -10rem;">
            <div class="container flex flex-col" style="align-items: center;">
                <!-- Header -->
                <div style="margin-bottom: 1rem;">
                    <span class="badge" style="margin-bottom: 3rem;">Our Collection</span>
                </div>
                <div class="section-header flex-space">
                    <div class="header-content">
                        <h2>Explore our fleet</h2>
                        <p class="header-subtitle">
                            Choose from our premium selection of luxury vehicles
                        </p>
                    </div>

                    <!-- Navigation Buttons -->
                    <div class="nav-buttons">
                        <button class="nav-button" id="prevBtn">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                            </svg>
                        </button>
                        <button class="nav-button" id="nextBtn">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Slider -->
                <div class="slider-container">
                    <div class="slider-track" id="sliderTrack">
                        <!-- Car 1 -->
                        <div class="slider-item">
                            <div class="car-card">
                                <div class="car-image-container">
                                    <img src="https://images.unsplash.com/photo-1758216383800-7023ee8ed42b?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&ixid=M3w3Nzg4Nzd8MHwxfHNlYXJjaHwxfHxsdXh1cnklMjBjYXIlMjBzZWRhbnxlbnwxfHx8fDE3NjQ5MzE4MzJ8MA&ixlib=rb-4.1.0&q=80&w=1080" alt="Mercedes-Benz S-Class" class="car-image">
                                    <div class="status-badge">
                                        <span class="status-available">● Available</span>
                                    </div>
                                </div>
                                <div class="car-details">
                                    <div>
                                        <h3 class="car-title">Mercedes-Benz</h3>
                                        <p class="car-subtitle">S-Class 2023</p>
                                    </div>
                                    <div class="car-specs">
                                        <div class="spec-item">
                                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                            </svg>
                                            <span>5,000 km</span>
                                        </div>
                                        <div class="spec-item">
                                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"></path>
                                            </svg>
                                            <span>Black</span>
                                        </div>
                                    </div>
                                    <div class="car-footer">
                                        <div>
                                            <div class="car-price">$250</div>
                                            <div class="price-label">per day</div>
                                        </div>
                                        <button class="view-more-button">View More</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Car 2 -->
                        <div class="slider-item">
                            <div class="car-card">
                                <div class="car-image-container">
                                    <img src="https://images.unsplash.com/photo-1696581081901-f8e0f10713b2?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&ixid=M3w3Nzg4Nzd8MHwxfHNlYXJjaHwxfHxzcG9ydHMlMjBjYXIlMjByZWR8ZW58MXx8fHwxNzY0ODc0OTU4fDA&ixlib=rb-4.1.0&q=80&w=1080" alt="Ferrari 488 GTB" class="car-image">
                                    <div class="status-badge">
                                        <span class="status-available">● Available</span>
                                    </div>
                                </div>
                                <div class="car-details">
                                    <div>
                                        <h3 class="car-title">Ferrari</h3>
                                        <p class="car-subtitle">488 GTB 2022</p>
                                    </div>
                                    <div class="car-specs">
                                        <div class="spec-item">
                                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                            </svg>
                                            <span>8,000 km</span>
                                        </div>
                                        <div class="spec-item">
                                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"></path>
                                            </svg>
                                            <span>Red</span>
                                        </div>
                                    </div>
                                    <div class="car-footer">
                                        <div>
                                            <div class="car-price">$850</div>
                                            <div class="price-label">per day</div>
                                        </div>
                                        <button class="view-more-button">View More</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Car 3 -->
                        <div class="slider-item">
                            <div class="car-card">
                                <div class="car-image-container">
                                    <img src="https://images.unsplash.com/photo-1714538701027-790deaef725b?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&ixid=M3w3Nzg4Nzd8MHwxfHNlYXJjaHwxfHxsdXh1cnklMjBzdXYlMjBibGFja3xlbnwxfHx8fDE3NjQ4ODU4OTB8MA&ixlib=rb-4.1.0&q=80&w=1080" alt="Range Rover Sport" class="car-image">
                                    <div class="status-badge">
                                        <span class="status-available">● Available</span>
                                    </div>
                                </div>
                                <div class="car-details">
                                    <div>
                                        <h3 class="car-title">Range Rover</h3>
                                        <p class="car-subtitle">Sport 2024</p>
                                    </div>
                                    <div class="car-specs">
                                        <div class="spec-item">
                                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                            </svg>
                                            <span>2,000 km</span>
                                        </div>
                                        <div class="spec-item">
                                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"></path>
                                            </svg>
                                            <span>Black</span>
                                        </div>
                                    </div>
                                    <div class="car-footer">
                                        <div>
                                            <div class="car-price">$320</div>
                                            <div class="price-label">per day</div>
                                        </div>
                                        <button class="view-more-button">View More</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Car 4 -->
                        <div class="slider-item">
                            <div class="car-card">
                                <div class="car-image-container">
                                    <img src="https://images.unsplash.com/photo-1669254383049-eb9462788446?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&ixid=M3w3Nzg4Nzd8MHwxfHNlYXJjaHwxfHxsdXh1cnklMjBzZWRhbiUyMHdoaXRlfGVufDF8fHx8MTc2NDkwOTc0NHww&ixlib=rb-4.1.0&q=80&w=1080" alt="BMW 7 Series" class="car-image">
                                    <div class="status-badge">
                                        <span class="status-available">● Available</span>
                                    </div>
                                </div>
                                <div class="car-details">
                                    <div>
                                        <h3 class="car-title">BMW</h3>
                                        <p class="car-subtitle">7 Series 2023</p>
                                    </div>
                                    <div class="car-specs">
                                        <div class="spec-item">
                                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                            </svg>
                                            <span>10,000 km</span>
                                        </div>
                                        <div class="spec-item">
                                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"></path>
                                            </svg>
                                            <span>White</span>
                                        </div>
                                    </div>
                                    <div class="car-footer">
                                        <div>
                                            <div class="car-price">$280</div>
                                            <div class="price-label">per day</div>
                                        </div>
                                        <button class="view-more-button">View More</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Car 5 -->
                        <div class="slider-item">
                            <div class="car-card">
                                <div class="car-image-container">
                                    <img src="https://images.unsplash.com/photo-1723847268845-6060bc118ea8?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&ixid=M3w3Nzg4Nzd8MHwxfHNlYXJjaHwxfHxwcmVtaXVtJTIwY2FyJTIwYmx1ZXxlbnwxfHx8fDE3NjQ5MzMyNzZ8MA&ixlib=rb-4.1.0&q=80&w=1080" alt="Audi RS7" class="car-image">
                                    <div class="status-badge">
                                        <span class="status-available">● Available</span>
                                    </div>
                                </div>
                                <div class="car-details">
                                    <div>
                                        <h3 class="car-title">Audi</h3>
                                        <p class="car-subtitle">RS7 2023</p>
                                    </div>
                                    <div class="car-specs">
                                        <div class="spec-item">
                                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                            </svg>
                                            <span>6,000 km</span>
                                        </div>
                                        <div class="spec-item">
                                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"></path>
                                            </svg>
                                            <span>Blue</span>
                                        </div>
                                    </div>
                                    <div class="car-footer">
                                        <div>
                                            <div class="car-price">$380</div>
                                            <div class="price-label">per day</div>
                                        </div>
                                        <button class="view-more-button">View More</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Car 6 -->
                        <div class="slider-item">
                            <div class="car-card">
                                <div class="car-image-container">
                                    <img src="https://images.unsplash.com/photo-1742056024244-02a093dae0b5?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&ixid=M3w3Nzg4Nzd8MHwxfHNlYXJjaHwxfHxsdXh1cnklMjBzcG9ydHMlMjBjYXJ8ZW58MXx8fHwxNzY0OTMxNDk0fDA&ixlib=rb-4.1.0&q=80&w=1080" alt="Porsche 911 Turbo" class="car-image">
                                    <div class="status-badge">
                                        <span class="status-available">● Available</span>
                                    </div>
                                </div>
                                <div class="car-details">
                                    <div>
                                        <h3 class="car-title">Porsche</h3>
                                        <p class="car-subtitle">911 Turbo 2024</p>
                                    </div>
                                    <div class="car-specs">
                                        <div class="spec-item">
                                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                            </svg>
                                            <span>3,000 km</span>
                                        </div>
                                        <div class="spec-item">
                                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"></path>
                                            </svg>
                                            <span>Silver</span>
                                        </div>
                                    </div>
                                    <div class="car-footer">
                                        <div>
                                            <div class="car-price">$750</div>
                                            <div class="price-label">per day</div>
                                        </div>
                                        <button class="view-more-button">View More</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Car 7 -->
                        <div class="slider-item">
                            <div class="car-card">
                                <div class="car-image-container">
                                    <img src="https://images.unsplash.com/photo-1764089859664-30aa6919ef0b?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&ixid=M3w3Nzg4Nzd8MHwxfHNlYXJjaHwxfHxtb2Rlcm4lMjBzZWRhbiUyMGdyZXl8ZW58MXx8fHwxNzY0OTMzMjc3fDA&ixlib=rb-4.1.0&q=80&w=1080" alt="Audi A6" class="car-image">
                                    <div class="status-badge">
                                        <span class="status-available">● Available</span>
                                    </div>
                                </div>
                                <div class="car-details">
                                    <div>
                                        <h3 class="car-title">Audi</h3>
                                        <p class="car-subtitle">A6 2022</p>
                                    </div>
                                    <div class="car-specs">
                                        <div class="spec-item">
                                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                            </svg>
                                            <span>15,000 km</span>
                                        </div>
                                        <div class="spec-item">
                                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"></path>
                                            </svg>
                                            <span>Grey</span>
                                        </div>
                                    </div>
                                    <div class="car-footer">
                                        <div>
                                            <div class="car-price">$200</div>
                                            <div class="price-label">per day</div>
                                        </div>
                                        <button class="view-more-button">View More</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Car 8 -->
                        <div class="slider-item">
                            <div class="car-card">
                                <div class="car-image-container">
                                    <img src="https://images.unsplash.com/photo-1549275087-77cdbc6fc013?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&ixid=M3w3Nzg4Nzd8MHwxfHNlYXJjaHwxfHxsdXh1cnklMjB2ZWhpY2xlJTIwc2lsdmVyfGVufDF8fHx8MTc2NDkzMzI3N3ww&ixlib=rb-4.1.0&q=80&w=1080" alt="Lexus LS 500" class="car-image">
                                    <div class="status-badge">
                                        <span class="status-available">● Available</span>
                                    </div>
                                </div>
                                <div class="car-details">
                                    <div>
                                        <h3 class="car-title">Lexus</h3>
                                        <p class="car-subtitle">LS 500 2023</p>
                                    </div>
                                    <div class="car-specs">
                                        <div class="spec-item">
                                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                            </svg>
                                            <span>7,000 km</span>
                                        </div>
                                        <div class="spec-item">
                                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"></path>
                                            </svg>
                                            <span>Silver</span>
                                        </div>
                                    </div>
                                    <div class="car-footer">
                                        <div>
                                            <div class="car-price">$240</div>
                                            <div class="price-label">per day</div>
                                        </div>
                                        <button class="view-more-button">View More</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- See More Card -->
                        <div class="slider-item">
                            <div class="see-more-card">
                                <div class="see-more-icon">
                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path>
                                    </svg>
                                </div>
                                <h3 class="see-more-title">View All Cars</h3>
                                <p class="see-more-text">
                                    Explore our complete collection of luxury vehicles
                                </p>
                                <button class="see-more-button">
                                    <span>See More</span>
                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        <section class="section contact-section">
            <div class="container">
                <!-- Header -->
                <div class="section-header">
                    <span class="badge">Contact Us</span>
                    <h2>Get In Touch</h2>
                    <p>Have questions? Our luxury concierge team is ready to assist you 24/7</p>
                </div>

                <div class="contact-grid">
                    <!-- Left Side - Contact Form -->
                    <div>
                        <div class="form-card">
                            <h3>Send us a message</h3>
                            <p class="subtitle">Fill out the form and we'll get back to you within 24 hours</p>

                            <form>
                                <div class="form-rowC">
                                    <div class="form-group">
                                        <label>First Name</label>
                                        <input type="text" placeholder="John">
                                    </div>
                                    <div class="form-group">
                                        <label>Last Name</label>
                                        <input type="text" placeholder="Doe">
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label>Email Address</label>
                                    <input type="email" placeholder="john.doe@example.com">
                                </div>

                                <div class="form-group">
                                    <label>Phone Number</label>
                                    <input type="tel" placeholder="+1 (555) 000-0000">
                                </div>

                                <div class="form-group">
                                    <label>Message</label>
                                    <textarea placeholder="Tell us about your luxury car rental needs..."></textarea>
                                </div>

                                <button type="submit" class="submit-btn">
                                    Send Message
                                    <svg class="icon" viewBox="0 0 24 24">
                                        <path d="M5 12h14M12 5l7 7-7 7" />
                                    </svg>
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Right Side - Contact Info -->
                    <div class="info-cards">
                        <!-- Quick Contact Card -->
                        <div class="quick-contact-card">
                            <h4>Quick Contact</h4>
                            <div class="contact-item">
                                <svg class="icon" viewBox="0 0 24 24">
                                    <path d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                </svg>
                                <div>
                                    <div class="contact-item-label">Phone</div>
                                    <div class="contact-item-value">+1 (555) 123-4567</div>
                                </div>
                            </div>
                            <div class="contact-item">
                                <svg class="icon" viewBox="0 0 24 24">
                                    <path d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                </svg>
                                <div>
                                    <div class="contact-item-label">Email</div>
                                    <div class="contact-item-value">info@luxedrive.com</div>
                                </div>
                            </div>
                        </div>

                        <!-- Visit Us Card -->
                        <div class="glass-card">
                            <div class="location-header">
                                <div class="icon-box">
                                    <svg class="icon-large" viewBox="0 0 24 24">
                                        <path d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                        <path d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                </div>
                                <div>
                                    <h4>Visit Our Showroom</h4>
                                    <p class="subtitle">Experience luxury in person</p>
                                </div>
                            </div>
                            <p class="address">123 Luxury Avenue</p>
                            <p class="address">Monaco, MC 98000</p>

                            <div class="divider"></div>

                            <div class="hours-header">
                                <svg class="icon" viewBox="0 0 24 24" style="color: #FF5722;">
                                    <circle cx="12" cy="12" r="10" />
                                    <path d="M12 6v6l4 2" />
                                </svg>
                                <span>Business Hours</span>
                            </div>
                            <p class="hours-text">Mon - Fri: 8:00 AM - 8:00 PM</p>
                            <p class="hours-text">Sat - Sun: 9:00 AM - 6:00 PM</p>
                            <p class="emergency">24/7 Emergency Assistance</p>
                        </div>

                        <!-- Social Media Card -->
                        <div class="glass-card">
                            <h4 style="margin-bottom: 1.5rem;">Follow Our Journey</h4>
                            <div class="social-icons">
                                <a href="#" class="social-icon">
                                    <svg class="icon" viewBox="0 0 24 24" fill="currentColor" stroke="none">
                                        <path d="M18 2h-3a5 5 0 00-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 011-1h3z" />
                                    </svg>
                                </a>
                                <a href="#" class="social-icon">
                                    <svg class="icon" viewBox="0 0 24 24" fill="currentColor" stroke="none">
                                        <path d="M23 3a10.9 10.9 0 01-3.14 1.53 4.48 4.48 0 00-7.86 3v1A10.66 10.66 0 013 4s-4 9 5 13a11.64 11.64 0 01-7 2c9 5 20 0 20-11.5a4.5 4.5 0 00-.08-.83A7.72 7.72 0 0023 3z" />
                                    </svg>
                                </a>
                                <a href="#" class="social-icon">
                                    <svg class="icon" viewBox="0 0 24 24">
                                        <rect x="2" y="2" width="20" height="20" rx="5" ry="5" />
                                        <path d="M16 11.37A4 4 0 1112.63 8 4 4 0 0116 11.37zm1.5-4.87h.01" />
                                    </svg>
                                </a>
                                <a href="#" class="social-icon">
                                    <svg class="icon" viewBox="0 0 24 24" fill="currentColor" stroke="none">
                                        <path d="M16 8a6 6 0 016 6v7h-4v-7a2 2 0 00-2-2 2 2 0 00-2 2v7h-4v-7a6 6 0 016-6zM2 9h4v12H2z" />
                                        <circle cx="4" cy="4" r="2" />
                                    </svg>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        <section class="section CTA-section">
            <div class="container flex flex-col" style="align-items: center;">
                <!-- CTA -->
                <div class="cta-container">
                    <div class="cta-background"></div>
                    <div class="cta-blur-container">
                        <div class="cta-blur-1"></div>
                        <div class="cta-blur-2"></div>
                    </div>
                    <div class="cta-content">
                        <h3 class="cta-title">Start your journey</h3>
                        <p class="cta-text">
                            Experience the thrill of driving luxury cars from the world's most prestigious brands
                        </p>
                        <div class="cta-buttons">
                            <button class="cta-button cta-button-primary">
                                Browse Our Fleet
                            </button>
                            <button class="cta-button cta-button-secondary">
                                Contact Us
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <!-- FOOTER -->
    <footer class="footer">
        <!-- Decorative Elements -->
        <div class="decorative-blur-1"></div>
        <div class="decorative-blur-2"></div>
        <div class="footer-content">

            <!-- Main Footer Content -->
            <div class="footer-main">
                <div class="footer-grid">
                    <!-- Brand Column -->
                    <div class="brand-column">
                        <div class="brand-logo">LUXDRIVE</div>
                        <p class="brand-description">
                            Experience the world's finest luxury vehicles. Premium car rental at your fingertips.
                        </p>
                        <div class="social-links">
                            <a href="#" class="social-link" aria-label="Facebook">
                                <svg fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M18 2h-3a5 5 0 00-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 011-1h3z"></path>
                                </svg>
                            </a>
                            <a href="#" class="social-link" aria-label="Twitter">
                                <svg fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M23 3a10.9 10.9 0 01-3.14 1.53 4.48 4.48 0 00-7.86 3v1A10.66 10.66 0 013 4s-4 9 5 13a11.64 11.64 0 01-7 2c9 5 20 0 20-11.5a4.5 4.5 0 00-.08-.83A7.72 7.72 0 0023 3z"></path>
                                </svg>
                            </a>
                            <a href="#" class="social-link" aria-label="Instagram">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <rect x="2" y="2" width="20" height="20" rx="5" ry="5" stroke-width="2"></rect>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11.37A4 4 0 1112.63 8 4 4 0 0116 11.37zm1.5-4.87h.01"></path>
                                </svg>
                            </a>
                            <a href="#" class="social-link" aria-label="LinkedIn">
                                <svg fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M16 8a6 6 0 016 6v7h-4v-7a2 2 0 00-2-2 2 2 0 00-2 2v7h-4v-7a6 6 0 016-6zM2 9h4v12H2z"></path>
                                    <circle cx="4" cy="4" r="2"></circle>
                                </svg>
                            </a>
                        </div>
                    </div>

                    <!-- Company Column -->
                    <div class="footer-column">
                        <h4 class="footer-column-title">Company</h4>
                        <ul class="footer-links">
                            <li><a href="#">Features</a></li>
                            <li><a href="#">Our Fleet</a></li>
                            <li><a href="#">How It Works</a></li>
                            <li><a href="#">About Us</a></li>
                        </ul>
                    </div>

                    <!-- Support Column -->
                    <div class="footer-column">
                        <h4 class="footer-column-title">Support</h4>
                        <ul class="footer-links">
                            <li><a href="#">Help Center</a></li>
                            <li><a href="#">Contact Us</a></li>
                            <li><a href="#">FAQ</a></li>
                            <li><a href="#">Live Chat</a></li>
                        </ul>
                    </div>

                    <!-- Legal Column -->
                    <div class="footer-column">
                        <h4 class="footer-column-title">Legal</h4>
                        <ul class="footer-links">
                            <li><a href="#">Terms of Service</a></li>
                            <li><a href="#">Privacy Policy</a></li>
                            <li><a href="#">Cookie Policy</a></li>
                            <li><a href="#">Licenses</a></li>
                        </ul>
                    </div>

                    <!-- Contact Column -->
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

                <!-- Bottom Bar -->
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

    <!-- car slide script -->
    <script>
        // Slider functionality
        const sliderTrack = document.getElementById('sliderTrack');
        const prevBtn = document.getElementById('prevBtn');
        const nextBtn = document.getElementById('nextBtn');

        let currentIndex = 0;
        const itemsPerPage = 2.4;
        const totalItems = 9; // 8 cars + 1 see more card
        const maxIndex = Math.max(0, totalItems - itemsPerPage);

        function updateSlider() {
            const translateX = -(currentIndex * (100 / itemsPerPage));
            sliderTrack.style.transform = `translateX(${translateX}%)`;
        }

        prevBtn.addEventListener('click', () => {
            if (currentIndex > 0) {
                currentIndex--;
                updateSlider();
            }
        });

        nextBtn.addEventListener('click', () => {
            if (currentIndex < maxIndex) {
                currentIndex++;
                updateSlider();
            }
        });
    </script>
</body>

</html>