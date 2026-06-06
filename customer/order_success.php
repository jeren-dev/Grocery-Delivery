<?php
include("../includes/header.php");

// Check if customer is logged in
if(!isset($_SESSION['customer_id'])) {
    header("Location: login.php");
    exit();
}

// Set timezone
date_default_timezone_set('Asia/Kolkata');
$utc_datetime = '2025-04-25 10:19:47';
$current_datetime = date('Y-m-d H:i:s', strtotime($utc_datetime) + (5.5 * 3600)); // Converting UTC to IST
$current_user = isset($_SESSION['customer_name']) ? $_SESSION['customer_name'] : 'testt453';	

// Get order ID
$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

if(!$order_id) {
    header("Location: orders.php");
    exit();
}

// Fetch order details
$order_query = mysqli_query($conn, "
    SELECT 
        o.*,
        c.name as customer_name,
        c.email,
        c.phone,
        TIMESTAMPDIFF(MINUTE, o.order_datetime, NOW()) as minutes_elapsed
    FROM orders o
    JOIN customers c ON o.customer_id = c.customer_id
    WHERE o.order_id = $order_id 
    AND o.customer_id = ".$_SESSION['customer_id']
);

if(mysqli_num_rows($order_query) == 0) {
    header("Location: orders.php");
    exit();
}

$order = mysqli_fetch_assoc($order_query);

// Calculate delivery times
$order_time = strtotime($order['order_datetime']);
$delivery_time = date('Y-m-d H:i:s', $order_time + (20 * 60)); // 20 minutes from order time
$current_time = strtotime($current_datetime);
$time_remaining = (strtotime($delivery_time) - $current_time);
$minutes_remaining = max(0, ceil($time_remaining / 60));
$is_delayed = $time_remaining < 0;

// Fetch order items
$items_query = mysqli_query($conn, "
    SELECT 
        oi.*,
        gi.name,
        gi.image,
        gi.unit,
        (oi.quantity * oi.price_per_unit) as subtotal
    FROM order_items oi
    JOIN grocery_items gi ON oi.item_id = gi.item_id
    WHERE oi.order_id = $order_id
");

$order_items = [];
$subtotal = 0;
while($item = mysqli_fetch_assoc($items_query)) {
    $order_items[] = $item;
    $subtotal += $item['subtotal'];
}

// Calculate totals
$delivery_charge = $is_delayed ? 0 : 50.00;
$total = $subtotal + $delivery_charge;

// Get delivery status message
function getDeliveryStatus($order, $minutes_remaining, $is_delayed) {
    if($order['order_status'] == 'delivered') {
        return $order['delivery_charge'] == 0 ? 
            '<span class="text-danger">Delivered (Delayed - Delivery Charge Waived)</span>' : 
            '<span class="text-success">Delivered On Time</span>';
    } else if($order['order_status'] == 'cancelled') {
        return '<span class="text-danger">Order Cancelled</span>';
    } else if($is_delayed) {
        return '<span class="text-danger">Delivery Delayed - Delivery will be Free</span>';
    } else {
        return '<span class="text-primary">' . $minutes_remaining . ' minutes remaining</span>';
    }
}
?>



<!DOCTYPE html>
<html lang="en">
<head>
    <style>
    .success-container {
        background: #f8f9fa;
        min-height: 100vh;
        padding: 30px 0;
    }

    .success-header {
        background: linear-gradient(45deg, #28a745, #20c997);
        color: white;
        padding: 3rem 0;
        margin-bottom: 2rem;
    }

    .success-animation {
        width: 100px;
        height: 100px;
        background: rgba(255,255,255,0.2);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 2rem;
        animation: pulse 2s infinite;
    }

    @keyframes pulse {
        0% { transform: scale(1); opacity: 1; }
        50% { transform: scale(1.1); opacity: 0.8; }
        100% { transform: scale(1); opacity: 1; }
    }

    .success-animation i {
        font-size: 3rem;
        animation: checkmark 0.8s cubic-bezier(0.65, 0, 0.45, 1) forwards;
    }

    @keyframes checkmark {
        0% { transform: scale(0); opacity: 0; }
        100% { transform: scale(1); opacity: 1; }
    }

    .order-card {
        background: white;
        border-radius: 15px;
        padding: 2rem;
        margin-bottom: 1.5rem;
        border: none;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    }

    .order-items {
        max-height: 300px;
        overflow-y: auto;
    }

    .item-card {
        display: flex;
        align-items: center;
        padding: 1rem;
        border-bottom: 1px solid #dee2e6;
    }

    .item-card:last-child {
        border-bottom: none;
    }

    .item-card img {
        width: 60px;
        height: 60px;
        object-fit: cover;
        border-radius: 8px;
        margin-right: 1rem;
    }

    .price-detail {
        display: flex;
        justify-content: space-between;
        margin-bottom: 0.5rem;
    }

    .total-price {
        font-size: 1.5rem;
        font-weight: bold;
        color: #28a745;
    }

    .timer-badge {
        font-size: 0.875rem;
        padding: 0.5rem 1rem;
        border-radius: 10px;
        background: rgba(255,255,255,0.1);
        display: inline-block;
    }

    .delivery-status {
        padding: 0.5rem 1rem;
        border-radius: 20px;
        font-size: 0.875rem;
        background: rgba(40, 167, 69, 0.1);
    }

    .countdown {
        font-size: 1.2rem;
        font-weight: bold;
        margin-top: 10px;
    }

    .text-danger {
        color: #dc3545 !important;
    }

    .text-success {
        color: #28a745 !important;
    }
    </style>
</head>
<body>

<!-- Navigation -->
<!-- Navigation -->
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

<!-- Success Header -->
<header class="success-header">
    <div class="container text-center">
        
        <div class="success-animation">
            <i class="fas fa-check"></i>
        </div>
        <h1 class="display-4 mb-3">Order Placed Successfully!</h1>
        <p class="lead mb-0">Order ID: #<?php echo $order['order_id']; ?></p>
        <div class="countdown mt-3" id="deliveryTimer">
            <?php echo getDeliveryStatus($order, $minutes_remaining, $is_delayed); ?>
        </div>
    </div>
</header>

<div class="success-container">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <!-- Order Details -->
                <div class="order-card">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h3 class="mb-0">Order Details</h3>
                        <span class="delivery-status">
                            <i class="fas fa-truck me-2"></i>
                            Expected by: <?php echo date('h:i A', strtotime($delivery_time)); ?>
                        </span>
                    </div>

                    <div class="row mb-4">
                        <div class="col-md-6">
                            <h5>Order Information</h5>
                            <p class="mb-1"><strong>Order Date:</strong> <?php echo date('d M Y, h:i A', strtotime($order['order_datetime'])); ?></p>
                            <p class="mb-1"><strong>Status:</strong> <?php echo ucfirst($order['order_status']); ?></p>
                            <p class="mb-0"><strong>Payment Method:</strong> Cash on Delivery</p>
                        </div>
                        <div class="col-md-6">
                            <h5>Delivery Information</h5>
                            <p class="mb-1"><strong>Name:</strong> <?php echo htmlspecialchars($order['customer_name']); ?></p>
                            <p class="mb-1"><strong>Phone:</strong> <?php echo htmlspecialchars($order['phone']); ?></p>
                            <p class="mb-0"><strong>Address:</strong> <?php echo nl2br(htmlspecialchars($order['shipping_address'])); ?></p>
                        </div>
                    </div>

                    <h5 class="mb-3">Order Items</h5>
                    <div class="order-items mb-4">
                        <?php foreach($order_items as $item): ?>
                            <div class="item-card">
                                <img src="../assets/images/grocery/<?php echo $item['image']; ?>" 
                                     alt="<?php echo htmlspecialchars($item['name']); ?>">
                                <div class="flex-grow-1">
                                    <h6 class="mb-1"><?php echo htmlspecialchars($item['name']); ?></h6>
                                    <p class="mb-0">
                                        <?php echo $item['quantity'].' '.$item['unit']; ?> x 
                                        Rs.<?php echo number_format($item['price_per_unit'], 2); ?>
                                    </p>
                                </div>
                                <div class="ms-3">
                                    <strong>Rs.<?php echo number_format($item['subtotal'], 2); ?></strong>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="border-top pt-3">
                        <div class="price-detail">
                            <span>Subtotal</span>
                            <span>Rs.<?php echo number_format($subtotal, 2); ?></span>
                        </div>
                        <div class="price-detail">
                            <span>Delivery Charge</span>
                            <span><?php echo $is_delayed ? '<del>RS.50.00</del> ?0.00 (Waived)' : 'Rs.50.00'; ?></span>
                        </div>
                        <hr>
                        <div class="price-detail total-price">
                            <span>Total Amount</span>
                            <span>Rs.<?php echo number_format($total, 2); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="text-center">
                    <a href="orders.php" class="btn btn-primary me-2">
                        <i class="fas fa-list me-2"></i>View All Orders
                    </a>
                    <a href="dishes.php" class="btn btn-outline-primary">
                        <i class="fas fa-utensils me-2"></i>Continue Shopping
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function updateDeliveryTimer() {
    const orderTime = new Date('<?php echo $order['order_datetime']; ?>').getTime();
    const deliveryTime = orderTime + (20 * 60 * 1000); // 20 minutes in milliseconds
    const now = new Date().getTime();
    const distance = deliveryTime - now;
    const timerElement = document.getElementById('deliveryTimer');
    
    if (distance < 0) {
        if('<?php echo $order['order_status']; ?>' === 'delivered') {
            timerElement.innerHTML = '<?php echo $order['delivery_charge'] == 0 ? 
                "<span class=\"text-danger\">Delivered (Delayed - Delivery Charge Waived)</span>" : 
                "<span class=\"text-success\">Delivered On Time</span>"; ?>';
        } else if('<?php echo $order['order_status']; ?>' === 'cancelled') {
            timerElement.innerHTML = '<span class="text-danger">Order Cancelled</span>';
        } else {
            timerElement.innerHTML = '<span class="text-danger">Delivery Delayed - Delivery will be Free</span>';
        }
    } else {
        const minutes = Math.floor(distance / (1000 * 60));
        const seconds = Math.floor((distance % (1000 * 60)) / 1000);
        timerElement.innerHTML = `<span class="text-primary">${minutes}m ${seconds}s remaining</span>`;
    }
}

// Update timer every second
setInterval(updateDeliveryTimer, 1000);
updateDeliveryTimer();
</script>

<?php include("../includes/footer.php"); ?>