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
			<nav class="nav flex-space nav_ul">
				<ul class="nav__list flex ">
					<li><a href="index.php?page=home" class="nav__links">Home</a></li>
					<li><a href="index.php?page=browse" class="nav__links">Browse cars</a></li>
					<li><a href="#contact" class="nav__links">Contact us</a></li>
				</ul>
				<?php if (isset($_SESSION['user_id'])){ ?>
				<div class="flex-center gap-1">
					<div class="profile flex-center"><span>AM</span></div>
					<button><img width="30" height="30" src="https://img.icons8.com/material-rounded/24/expand-arrow--v1.png" alt="expand-arrow--v1"/></button>
				</div>
				<?php } else { ?>
				<ul class="flex auth-list">
					<li><a href="index.php?page=login" class="auth_link btn log_btn">login</a></li>
					<li><a href="index.php?page=signup" class="auth_link btn sign_btn">sign Up</a></li>
				</ul>
				<?php } ?>
			</nav>

		</div>
	</header>

	<!--MAIN -->
	<main class="main">

	</main>

	<!-- FOOTER -->
	<footer class="footer">

	</footer>
</body>

</html>