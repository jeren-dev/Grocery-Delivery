<?php
include("../includes/header.php");

// Check if customer is logged in
if(!isset($_SESSION['customer_id'])) {
    header("Location: login.php");
    exit();
}

// Set timezone
date_default_timezone_set('Asia/Kolkata');
$utc_datetime = '2025-04-25 09:27:53';
$current_datetime = date('Y-m-d H:i:s', strtotime($utc_datetime) + (5.5 * 3600)); // Converting UTC to IST
$current_user = isset($_SESSION['customer_name']) ? $_SESSION['customer_name'] : 'testt453';	
$customer_id = $_SESSION['customer_id'];

// Initialize variables
$error_msg = $success_msg = "";

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = 10;
$offset = ($page - 1) * $records_per_page;

// Filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$date_filter = isset($_GET['date']) ? $_GET['date'] : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'newest';

// Build query conditions
$conditions = ["o.customer_id = $customer_id"];
if($status_filter) {
    $conditions[] = "o.order_status = '".mysqli_real_escape_string($conn, $status_filter)."'";
}
if($date_filter) {
    $date = mysqli_real_escape_string($conn, $date_filter);
    $conditions[] = "DATE(o.order_datetime) = '$date'";
}

$where_clause = implode(" AND ", $conditions);

// Sort order
$sort_clause = match($sort) {
    'oldest' => 'o.order_datetime ASC',
    'highest' => 'o.total_amount DESC',
    'lowest' => 'o.total_amount ASC',
    default => 'o.order_datetime DESC'
};

// Get total records for pagination
$total_query = mysqli_query($conn, "
    SELECT COUNT(*) as count 
    FROM orders o 
    WHERE $where_clause
");
$total_records = mysqli_fetch_assoc($total_query)['count'];
$total_pages = ceil($total_records / $records_per_page);

// Fetch orders with items
$orders_query = mysqli_query($conn, "
    SELECT 
        o.*,
        GROUP_CONCAT(
            CONCAT(
                gi.name, ' (', 
                oi.quantity, ' ', 
                gi.unit, ')'
            ) SEPARATOR ', '
        ) as items_list
    FROM orders o
    LEFT JOIN order_items oi ON o.order_id = oi.order_id
    LEFT JOIN grocery_items gi ON oi.item_id = gi.item_id
    WHERE $where_clause
    GROUP BY o.order_id
    ORDER BY $sort_clause
    LIMIT $offset, $records_per_page
");

// Get order statuses for filter
$statuses_query = mysqli_query($conn, "
    SELECT DISTINCT order_status 
    FROM orders 
    WHERE customer_id = $customer_id
");

$statuses = [];
while($status = mysqli_fetch_assoc($statuses_query)) {
    $statuses[] = $status['order_status'];
}

// Get order dates for filter
$dates_query = mysqli_query($conn, "
    SELECT DISTINCT DATE(order_datetime) as order_date 
    FROM orders 
    WHERE customer_id = $customer_id
    ORDER BY order_datetime DESC
");

$dates = [];
while($date = mysqli_fetch_assoc($dates_query)) {
    $dates[] = $date['order_date'];
}

// Get statistics
$stats_query = mysqli_query($conn, "
    SELECT 
        COUNT(*) as total_orders,
        SUM(total_amount) as total_spent,
        COUNT(CASE WHEN order_status = 'delivered' THEN 1 END) as delivered_orders,
        COUNT(CASE WHEN order_status = 'pending' THEN 1 END) as pending_orders
    FROM orders 
    WHERE customer_id = $customer_id
");
$stats = mysqli_fetch_assoc($stats_query);

// Handle order cancellation
if(isset($_POST['cancel_order'])) {
    $order_id = (int)$_POST['order_id'];
    
    try {
        mysqli_begin_transaction($conn);
        
        // Check if order can be cancelled
        $order_check = mysqli_query($conn, "
            SELECT order_status 
            FROM orders 
            WHERE order_id = $order_id 
            AND customer_id = $customer_id
        ");
        
        if($order = mysqli_fetch_assoc($order_check)) {
            if($order['order_status'] == 'pending') {
                // Update order status
                mysqli_query($conn, "
                    UPDATE orders 
                    SET order_status = 'cancelled',
                        updated_at = '$current_datetime'
                    WHERE order_id = $order_id
                ");
                
                // Return items to stock
                $items_query = mysqli_query($conn, "
                    SELECT item_id, quantity 
                    FROM order_items 
                    WHERE order_id = $order_id
                ");
                
                while($item = mysqli_fetch_assoc($items_query)) {
                    mysqli_query($conn, "
                        UPDATE grocery_items 
                        SET stock_quantity = stock_quantity + ".$item['quantity']."
                        WHERE item_id = ".$item['item_id']
                    );
                }
                
                mysqli_commit($conn);
                $success_msg = "Order cancelled successfully";
            } else {
                $error_msg = "This order cannot be cancelled";
            }
        } else {
            $error_msg = "Order not found";
        }
    } catch(Exception $e) {
        mysqli_rollback($conn);
        $error_msg = "Failed to cancel order";
    }
}
?>



<!DOCTYPE html>
<html lang="en">
<head>
    <style>
    .orders-container {
        background: #f8f9fa;
        min-height: 100vh;
        padding: 30px 0;
    }

    .orders-header {
        background: linear-gradient(45deg, #007bff, #0056b3);
        color: white;
        padding: 3rem 0;
        margin-bottom: 2rem;
    }

    .stats-card {
        background: white;
        border-radius: 15px;
        padding: 1.5rem;
        margin-bottom: 1rem;
        border: none;
        transition: transform 0.3s;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    }

    .stats-card:hover {
        transform: translateY(-5px);
    }

    .order-card {
        background: white;
        border-radius: 15px;
        margin-bottom: 1rem;
        transition: transform 0.3s;
        border: none;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    }

    .order-card:hover {
        transform: translateY(-3px);
    }

    .status-badge {
        padding: 0.5rem 1rem;
        border-radius: 20px;
        font-size: 0.875rem;
    }

    .status-pending {
        background: #fff3cd;
        color: #856404;
    }

    .status-processing {
        background: #cce5ff;
        color: #004085;
    }

    .status-delivered {
        background: #d4edda;
        color: #155724;
    }

    .status-cancelled {
        background: #f8d7da;
        color: #721c24;
    }

    .timer-badge {
        font-size: 0.875rem;
        padding: 0.5rem 1rem;
        border-radius: 10px;
        background: rgba(255,255,255,0.1);
        display: inline-block;
    }

    .filter-card {
        background: white;
        border-radius: 15px;
        padding: 1.5rem;
        margin-bottom: 1.5rem;
        border: none;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    }

    .total-spent {
        font-size: 1.5rem;
        font-weight: bold;
        color: #28a745;
    }

    .order-items {
        font-size: 0.9rem;
        color: #6c757d;
    }

    .delivery-info {
        font-size: 0.875rem;
        padding: 0.5rem 1rem;
        background: #e9ecef;
        border-radius: 10px;
    }
    </style>
</head>
<body>

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
<!-- Orders Header -->
<header class="orders-header">
    <div class="container text-center">
        <span class="timer-badge mb-3">
            <i class="fas fa-clock me-2"></i>
            Current time: <?php echo $current_datetime; ?> IST
        </span>
        <h1 class="display-4">My Orders</h1>
        <p class="lead mb-0">Track and manage your orders</p>
    </div>
</header>

<div class="orders-container">
    <div class="container">
        <?php if($error_msg): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?php echo $error_msg; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if($success_msg): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <?php echo $success_msg; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Filters and Stats -->
            <div class="col-lg-3">
                <!-- Order Statistics -->
                <div class="stats-card">
                    <h5 class="mb-4">Order Statistics</h5>
                    <div class="mb-3">
                        <small class="text-muted">Total Orders</small>
                        <h3 class="mb-0"><?php echo $stats['total_orders']; ?></h3>
                    </div>
                    <div class="mb-3">
                        <small class="text-muted">Total Spent</small>
                        <h3 class="mb-0 total-spent">Rs.<?php echo number_format($stats['total_spent'], 2); ?></h3>
                    </div>
                    <div class="mb-3">
                        <small class="text-muted">Delivered Orders</small>
                        <h3 class="mb-0"><?php echo $stats['delivered_orders']; ?></h3>
                    </div>
                    <div>
                        <small class="text-muted">Pending Orders</small>
                        <h3 class="mb-0"><?php echo $stats['pending_orders']; ?></h3>
                    </div>
                </div>

                <!-- Filters -->
                <div class="filter-card">
                    <h5 class="mb-3">Filters</h5>
                    <form method="GET" id="filterForm">
                        <div class="mb-3">
                            <label class="form-label">Order Status</label>
                            <select name="status" class="form-select" onChange="this.form.submit()">
                                <option value="">All Statuses</option>
                                <?php foreach($statuses as $status): ?>
                                    <option value="<?php echo $status; ?>" <?php echo $status_filter == $status ? 'selected' : ''; ?>>
                                        <?php echo ucfirst($status); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Order Date</label>
                            <select name="date" class="form-select" onChange="this.form.submit()">
                                <option value="">All Dates</option>
                                <?php foreach($dates as $date): ?>
                                    <option value="<?php echo $date; ?>" <?php echo $date_filter == $date ? 'selected' : ''; ?>>
                                        <?php echo date('d M Y', strtotime($date)); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Sort By</label>
                            <select name="sort" class="form-select" onChange="this.form.submit()">
                                <option value="newest" <?php echo $sort == 'newest' ? 'selected' : ''; ?>>Newest First</option>
                                <option value="oldest" <?php echo $sort == 'oldest' ? 'selected' : ''; ?>>Oldest First</option>
                                <option value="highest" <?php echo $sort == 'highest' ? 'selected' : ''; ?>>Highest Amount</option>
                                <option value="lowest" <?php echo $sort == 'lowest' ? 'selected' : ''; ?>>Lowest Amount</option>
                            </select>
                        </div>

                        <button type="button" class="btn btn-outline-secondary w-100" onClick="clearFilters()">
                            Clear Filters
                        </button>
                    </form>
                </div>
            </div>

            <!-- Orders List -->
            <div class="col-lg-9">
                <?php if(mysqli_num_rows($orders_query) > 0): ?>
                    <?php while($order = mysqli_fetch_assoc($orders_query)): ?>
                        <div class="order-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5 class="mb-0">Order #<?php echo $order['order_id']; ?></h5>
                                    <span class="status-badge status-<?php echo $order['order_status']; ?>">
                                        <?php echo ucfirst($order['order_status']); ?>
                                    </span>
                                </div>

                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <p class="mb-1">
                                            <i class="fas fa-calendar me-2"></i>
                                            <?php echo date('d M Y, h:i A', strtotime($order['order_datetime'])); ?>
                                        </p>
                                        <p class="mb-1">
                                            <i class="fas fa-money-bill-wave me-2"></i>
                                            Total: Rs.<?php echo number_format($order['total_amount'], 2); ?>
                                        </p>
                                        <p class="mb-0 order-items">
                                            <i class="fas fa-shopping-basket me-2"></i>
                                            <?php echo htmlspecialchars($order['items_list']); ?>
                                        </p>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="delivery-info">
                                            <p class="mb-1">
                                                <i class="fas fa-truck me-2"></i>
                                                Delivery Status: <?php echo ucfirst($order['delivery_status']); ?>
                                            </p>
                                            <p class="mb-0">
                                                <i class="fas fa-map-marker-alt me-2"></i>
                                                <?php echo nl2br(htmlspecialchars($order['shipping_address'])); ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-end">
                                    <a href="order_success.php?order_id=<?php echo $order['order_id']; ?>" 
                                       class="btn btn-outline-primary btn-sm me-2">
                                        View Details
                                    </a>
                                   
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>

                    <!-- Pagination -->
                    <?php if($total_pages > 1): ?>
                        <nav aria-label="Page navigation" class="mt-4">
                            <ul class="pagination justify-content-center">
                                <?php for($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>&status=<?php echo $status_filter; ?>&date=<?php echo $date_filter; ?>&sort=<?php echo $sort; ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fas fa-shopping-bag fa-3x mb-3 text-muted"></i>
                        <h3>No orders found</h3>
                        <p class="text-muted">Start shopping to see your orders here</p>
                        <a href="dishes.php" class="btn btn-primary">
                            Browse Dishes
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function clearFilters() {
    window.location.href = 'orders.php';
}

function confirmCancel() {
    return confirm('Are you sure you want to cancel this order?');
}

// Auto-hide alerts
setTimeout(function() {
    $('.alert').alert('close');
}, 5000);
</script>

<?php include("../includes/footer.php"); ?>