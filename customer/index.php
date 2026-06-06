<?php
include("../includes/header.php");

// Set timezone and current time
date_default_timezone_set("UTC");
$current_datetime = '2025-04-25 07:53:50';
$current_user = isset($_SESSION['customer_name']) ? $_SESSION['customer_name'] : 'testt453';

// Fetch featured dishes
$featured_dishes_query = mysqli_query($conn, "
    SELECT 
        d.*,
        GROUP_CONCAT(gi.name SEPARATOR ', ') as ingredients
    FROM dishes d
    LEFT JOIN dish_grocery_items dgi ON d.dish_id = dgi.dish_id
    LEFT JOIN grocery_items gi ON dgi.item_id = gi.item_id
    WHERE d.status = 'active'
    GROUP BY d.dish_id
    LIMIT 4
");

// Fetch grocery categories with items
$categories_query = mysqli_query($conn, "
    SELECT DISTINCT category 
    FROM grocery_items 
    WHERE status = 'active'
    ORDER BY category
");

// Fetch recent items
$recent_items_query = mysqli_query($conn, "
    SELECT *
    FROM grocery_items
    WHERE status = 'active'
    ORDER BY created_at DESC
    LIMIT 8
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <style>
    .hero-section {
        background: linear-gradient(45deg, #007bff, #0056b3);
        background-size: cover;
        background-position: center;
        padding: 100px 0;
        color: white;
    }

    .category-card {
        border: none;
        border-radius: 15px;
        transition: transform 0.3s;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    }

    .category-card:hover {
        transform: translateY(-5px);
    }

    .dish-card {
        border: none;
        border-radius: 15px;
        transition: transform 0.3s;
        overflow: hidden;
    }

    .dish-card:hover {
        transform: translateY(-5px);
    }

    .dish-card img {
        height: 200px;
        object-fit: cover;
    }

    .item-card {
        border: none;
        border-radius: 15px;
        transition: transform 0.3s;
    }

    .item-card:hover {
        transform: translateY(-5px);
    }

    .item-card img {
        height: 150px;
        object-fit: cover;
        border-radius: 15px 15px 0 0;
    }

    .welcome-banner {
        background: #f8f9fa;
        padding: 15px;
        border-radius: 10px;
        margin-bottom: 30px;
    }

    .section-title {
        position: relative;
        margin-bottom: 30px;
        padding-bottom: 10px;
    }

    .section-title::after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 0;
        width: 50px;
        height: 3px;
        background: #007bff;
    }
    </style>
</head>
<body>

<!-- Navigation -->
<nav class="navbar navbar-expand-lg navbar-light bg-light">
    <div class="container">
        <a class="navbar-brand" href="../admin/index.php">
            <i class="fas fa-store me-2"></i>Grocery Store
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link active" href="index.php">Home</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="dishes.php">Dishes</a>
                </li>
				
				<li class="nav-item">
                    <a class="nav-link" href="grocery_items.php">Grocery</a>
                </li>
				
               
            </ul>
			 
            <ul class="navbar-nav">
			
			<?php if(isset($_SESSION['customer_id'])) { ?>
                <li class="nav-item">
                    <a class="nav-link" href="cart.php">
                        <i class="fas fa-shopping-cart me-1"></i>Cart
                        <?php
                        if(isset($_SESSION['customer_id'])) {
                            $cart_count = mysqli_fetch_assoc(mysqli_query($conn, 
                                "SELECT COUNT(*) as count FROM cart WHERE customer_id = ".$_SESSION['customer_id']
                            ))['count'];
                            if($cart_count > 0) {
                                echo "<span class=\"badge bg-primary\">$cart_count</span>";
                            }
                        }
                        ?>
                    </a>
                </li>
				
			<?php	} ?>
                <?php if(isset($_SESSION['customer_id'])): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle me-1"></i><?php echo htmlspecialchars($current_user); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">

                            <li><a class="dropdown-item" href="orders.php">My Orders</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php">Logout</a></li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link" href="login.php">Login</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="register.php">Register</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<!-- Hero Section -->
<section class="hero-section text-center">
    <div class="container">
        <h1 class="display-4 mb-4">Fresh Groceries Delivered to Your Door</h1>
        <p class="lead mb-4">Order fresh groceries and discover delicious recipes for your favorite dishes</p>
        <a href="dishes.php" class="btn btn-primary btn-lg me-3">
            <i class="fas fa-utensils me-2"></i>Browse Dishes
        </a>
        <a href="#categories" class="btn btn-outline-light btn-lg">
            <i class="fas fa-shopping-basket me-2"></i>Shop Groceries
        </a>
    </div>
</section>

<!-- Welcome Banner -->


<!-- Featured Dishes -->
<section class="container my-5">
    <h2 class="section-title">Featured Dishes</h2>
    <div class="row g-4">
        <?php while($dish = mysqli_fetch_assoc($featured_dishes_query)): ?>
        <div class="col-md-3">
            <div class="card dish-card">
                <img src="../assets/images/dishes/<?php echo $dish['image']; ?>" class="card-img-top" alt="<?php echo htmlspecialchars($dish['name']); ?>">
                <div class="card-body">
                    <h5 class="card-title"><?php echo htmlspecialchars($dish['name']); ?></h5>
                    <p class="card-text small text-muted">
                        <i class="fas fa-leaf me-1"></i>Ingredients: <?php echo htmlspecialchars($dish['ingredients']); ?>
                    </p>
                    <a href="view_dish.php?id=<?php echo $dish['dish_id']; ?>" class="btn btn-primary w-100">
                        <i class="fas fa-info-circle me-1"></i>View Details
                    </a>
                </div>
            </div>
        </div>
        <?php endwhile; ?>
    </div>
</section>

<!-- Categories -->
<section id="categories" class="container my-5">
    <h2 class="section-title">Shop by Category</h2>
    <div class="row g-4">
        <?php while($category = mysqli_fetch_assoc($categories_query)): ?>
        <div class="col-md-3">
            <div class="card category-card">
                <div class="card-body text-center">
                    <i class="fas fa-box fa-3x mb-3 text-primary"></i>
                    <h5 class="card-title">
                        <?php echo ucwords(str_replace('-', ' ', $category['category'])); ?>
                    </h5>
                    <a href="grocery_items.php?category=<?php echo urlencode($category['category']); ?>" 
                       class="btn btn-outline-primary">Browse Items</a>
                </div>
            </div>
        </div>
        <?php endwhile; ?>
    </div>
</section>

<!-- Features -->
<section class="container my-5">
    <div class="row g-4">
        <div class="col-md-4">
            <div class="text-center">
                <i class="fas fa-truck fa-3x text-primary mb-3"></i>
                <h4>Fast Delivery</h4>
                <p>Get your groceries delivered within 20 minutes</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="text-center">
                <i class="fas fa-leaf fa-3x text-primary mb-3"></i>
                <h4>Fresh Products</h4>
                <p>High-quality fresh groceries sourced daily</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="text-center">
                <i class="fas fa-shield-alt fa-3x text-primary mb-3"></i>
                <h4>Secure Payments</h4>
                <p>100% secure payment processing</p>
            </div>
        </div>
    </div>
</section>

<?php include("../includes/footer.php"); ?>