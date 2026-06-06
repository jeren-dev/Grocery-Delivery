<?php
include("../includes/header.php");

// Check if customer is logged in
if (!isset($_SESSION['customer_id'])) {
    header("Location: login.php");
    exit();
}

// Set timezone
date_default_timezone_set('Asia/Kolkata');
$utc_datetime = '2025-04-25 09:05:15';
$current_datetime = date('Y-m-d H:i:s', strtotime($utc_datetime) + (5.5 * 3600)); // Converting UTC to IST
$current_user = $_SESSION['customer_name'];
$customer_id = $_SESSION['customer_id'];

// Fetch customer details
$customer_query = mysqli_query($conn, "
    SELECT * FROM customers 
    WHERE customer_id = $customer_id
");
$customer = mysqli_fetch_assoc($customer_query);

// Fetch recent orders
$recent_orders_query = mysqli_query($conn, "
    SELECT 
        o.*,
        GROUP_CONCAT(
            CONCAT(gi.name, ' (', oi.quantity, ' ', gi.unit, ')')
            SEPARATOR ', '
        ) as items_list
    FROM orders o
    LEFT JOIN order_items oi ON o.order_id = oi.order_id
    LEFT JOIN grocery_items gi ON oi.item_id = gi.item_id
    WHERE o.customer_id = $customer_id
    GROUP BY o.order_id
    ORDER BY o.order_datetime DESC
    LIMIT 5
");

// Fetch cart items
$cart_query = mysqli_query($conn, "
    SELECT 
        c.*,
        gi.name,
        gi.price,
        gi.unit,
        gi.image,
        (c.quantity * gi.price) as subtotal
    FROM cart c
    JOIN grocery_items gi ON c.item_id = gi.item_id
    WHERE c.customer_id = $customer_id
");

// Calculate cart total
$cart_total = 0;
$cart_items = [];
while($item = mysqli_fetch_assoc($cart_query)) {
    $cart_total += $item['subtotal'];
    $cart_items[] = $item;
}

// Fetch favorite/recommended dishes
$recommended_dishes_query = mysqli_query($conn, "
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <style>
    .dashboard-container {
        background: #f8f9fa;
        min-height: 100vh;
        padding: 20px 0;
    }

    .welcome-banner {
        background: linear-gradient(45deg, #007bff, #0056b3);
        color: white;
        padding: 2rem;
        border-radius: 15px;
        margin-bottom: 2rem;
    }

    .stats-card {
        border: none;
        border-radius: 15px;
        transition: transform 0.3s;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    }

    .stats-card:hover {
        transform: translateY(-5px);
    }

    .order-card {
        border: none;
        border-radius: 15px;
        margin-bottom: 1rem;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    }

    .dish-card {
        border: none;
        border-radius: 15px;
        overflow: hidden;
        transition: transform 0.3s;
    }

    .dish-card:hover {
        transform: translateY(-5px);
    }

    .dish-card img {
        height: 200px;
        object-fit: cover;
    }

    .status-badge {
        padding: 0.5rem 1rem;
        border-radius: 20px;
        font-size: 0.875rem;
    }

    .status-pending { background: #ffd700; color: #000; }
    .status-processing { background: #87ceeb; color: #000; }
    .status-delivered { background: #90ee90; color: #000; }
    .status-cancelled { background: #ff6b6b; color: #fff; }

    .profile-section {
        background: white;
        border-radius: 15px;
        padding: 2rem;
        margin-bottom: 2rem;
    }

    .cart-preview {
        max-height: 300px;
        overflow-y: auto;
    }
    </style>
</head>
<body>

<!-- Navigation Bar -->
<nav class="navbar navbar-expand-lg navbar-light bg-light">
    <div class="container">
        <a class="navbar-brand" href="index.php">
            <i class="fas fa-store me-2"></i>Grocery Store
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link" href="index.php">Home</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="dishes.php">Dishes</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="orders.php">My Orders</a>
                </li>
            </ul>
            <ul class="navbar-nav">
                <li class="nav-item">
                    <a class="nav-link" href="cart.php">
                        <i class="fas fa-shopping-cart me-1"></i>Cart
                        <?php if(count($cart_items) > 0): ?>
                        <span class="badge bg-primary"><?php echo count($cart_items); ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user-circle me-1"></i><?php echo htmlspecialchars($current_user); ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item active" href="dashboard.php">Dashboard</a></li>
                        <li><a class="dropdown-item" href="profile.php">My Profile</a></li>
                        <li><a class="dropdown-item" href="orders.php">My Orders</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="logout.php">Logout</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>

<div class="dashboard-container">
    <div class="container">
        <!-- Welcome Banner -->
        <div class="welcome-banner">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="mb-2">Welcome back, <?php echo htmlspecialchars($current_user); ?>!</h1>
                    <p class="mb-0">
                        <i class="fas fa-clock me-2"></i>
                        <?php echo $current_datetime; ?> IST
                    </p>
                </div>
                <div class="col-md-4 text-md-end">
                    <a href="cart.php" class="btn btn-light btn-lg">
                        <i class="fas fa-shopping-cart me-2"></i>View Cart
                        <?php if(count($cart_items) > 0): ?>
                        <span class="badge bg-primary"><?php echo count($cart_items); ?></span>
                        <?php endif; ?>
                    </a>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Left Column - Profile and Orders -->
            <div class="col-md-8">
                <!-- Profile Section -->
                <div class="profile-section mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h3>My Profile</h3>
                        <a href="profile.php" class="btn btn-outline-primary">
                            <i class="fas fa-edit me-2"></i>Edit Profile
                        </a>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong><i class="fas fa-user me-2"></i>Name:</strong> <?php echo htmlspecialchars($customer['name']); ?></p>
                            <p><strong><i class="fas fa-envelope me-2"></i>Email:</strong> <?php echo htmlspecialchars($customer['email']); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong><i class="fas fa-phone me-2"></i>Phone:</strong> <?php echo htmlspecialchars($customer['phone']); ?></p>
                            <p><strong><i class="fas fa-map-marker-alt me-2"></i>Address:</strong> <?php echo htmlspecialchars($customer['address']); ?></p>
                        </div>
                    </div>
                </div>

                <!-- Recent Orders -->
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h3>Recent Orders</h3>
                            <a href="orders.php" class="btn btn-outline-primary">
                                View All Orders
                            </a>
                        </div>
                        <?php if(mysqli_num_rows($recent_orders_query) > 0): ?>
                            <?php while($order = mysqli_fetch_assoc($recent_orders_query)): ?>
                            <div class="order-card p-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h5 class="mb-1">Order #<?php echo $order['order_id']; ?></h5>
                                        <p class="text-muted mb-2">
                                            <small>
                                                <i class="fas fa-calendar me-1"></i>
                                                <?php echo date('d M Y, h:i A', strtotime($order['order_datetime'])); ?>
                                            </small>
                                        </p>
                                        <p class="mb-0"><small><?php echo htmlspecialchars($order['items_list']); ?></small></p>
                                    </div>
                                    <div class="text-end">
                                        <span class="status-badge status-<?php echo $order['order_status']; ?>">
                                            <?php echo ucfirst($order['order_status']); ?>
                                        </span>
                                        <p class="mb-0 mt-2">Rs.<?php echo number_format($order['total_amount'], 2); ?></p>
                                    </div>
                                </div>
                            </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-shopping-bag fa-3x mb-3 text-muted"></i>
                                <p class="mb-0">No orders yet</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Right Column - Cart and Recommendations -->
            <div class="col-md-4">
                <!-- Cart Preview -->
                <div class="card mb-4">
                    <div class="card-body">
                        <h3 class="mb-4">Shopping Cart</h3>
                        <?php if(count($cart_items) > 0): ?>
                            <div class="cart-preview">
                                <?php foreach($cart_items as $item): ?>
                                <div class="d-flex align-items-center mb-3">
                                    <img src="../assets/images/grocery/<?php echo $item['image']; ?>" 
                                         alt="<?php echo htmlspecialchars($item['name']); ?>"
                                         class="rounded" style="width: 50px; height: 50px; object-fit: cover;">
                                    <div class="ms-3">
                                        <h6 class="mb-0"><?php echo htmlspecialchars($item['name']); ?></h6>
                                        <small class="text-muted">
                                            <?php echo $item['quantity'].' '.$item['unit']; ?> × Rs.<?php echo number_format($item['price'], 2); ?>
                                        </small>
                                    </div>
                                    <div class="ms-auto">
                                        <strong>Rs.<?php echo number_format($item['subtotal'], 2); ?></strong>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <hr>
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="mb-0">Total:</h5>
                                <h5 class="mb-0">Rs.<?php echo number_format($cart_total, 2); ?></h5>
                            </div>
                            <a href="cart.php" class="btn btn-primary w-100">
                                <i class="fas fa-shopping-cart me-2"></i>Proceed to Checkout
                            </a>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-shopping-cart fa-3x mb-3 text-muted"></i>
                                <p class="mb-0">Your cart is empty</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Recommended Dishes -->
                <div class="card">
                    <div class="card-body">
                        <h3 class="mb-4">Recommended Dishes</h3>
                        <?php while($dish = mysqli_fetch_assoc($recommended_dishes_query)): ?>
                        <div class="dish-card mb-3">
                            <img src="../assets/images/dishes/<?php echo $dish['image']; ?>" 
                                 class="card-img-top" 
                                 alt="<?php echo htmlspecialchars($dish['name']); ?>">
                            <div class="card-body">
                                <h5 class="card-title"><?php echo htmlspecialchars($dish['name']); ?></h5>
                                <p class="card-text small">
                                    <i class="fas fa-leaf me-1"></i>
                                    <?php echo htmlspecialchars($dish['ingredients']); ?>
                                </p>
                                <a href="view_dish.php?id=<?php echo $dish['dish_id']; ?>" 
                                   class="btn btn-outline-primary btn-sm w-100">
                                    View Recipe
                                </a>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Auto-update timestamps
function updateTimestamps() {
    document.querySelectorAll('[data-timestamp]').forEach(element => {
        const timestamp = element.getAttribute('data-timestamp');
        const date = new Date(timestamp);
        element.textContent = date.toLocaleString();
    });
}

// Initialize tooltips
var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl)
});

// Update timestamps every minute
setInterval(updateTimestamps, 60000);
updateTimestamps();
</script>

<?php include("../includes/footer.php"); ?>