<?php
include("../includes/header.php");


// Fetch statistics
$today = date('Y-m-d');

// Total Orders Today
$order_query = mysqli_query($conn, "SELECT 
    COUNT(*) as total_orders,
    SUM(total_amount) as total_sales,
    COUNT(CASE WHEN delivery_status = 'delayed' THEN 1 END) as delayed_orders,
    COUNT(CASE WHEN delivery_status = 'on_time' THEN 1 END) as ontime_orders
    FROM orders 
    WHERE DATE(order_datetime) = '$today'");
$order_stats = mysqli_fetch_assoc($order_query);

// Total Products
$products_query = mysqli_query($conn, "SELECT COUNT(*) as total_products FROM grocery_items");
$products_count = mysqli_fetch_assoc($products_query);

// Total Customers
$customers_query = mysqli_query($conn, "SELECT COUNT(*) as total_customers FROM customers");
$customers_count = mysqli_fetch_assoc($customers_query);

// Latest Orders (last 5)
$latest_orders = mysqli_query($conn, "SELECT 
    o.*, 
    c.name as customer_name,
    c.phone as customer_phone
    FROM orders o
    JOIN customers c ON o.customer_id = c.customer_id
    ORDER BY o.order_datetime DESC LIMIT 5");

// Low Stock Items (less than 10 units)
$low_stock = mysqli_query($conn, "SELECT * FROM grocery_items WHERE stock_quantity < 10");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <style>
    .dashboard-container {
        background: #f8f9fa;
        min-height: 100vh;
        padding: 20px;
    }
    
    .stats-card {
        border-radius: 15px;
        border: none;
        transition: transform 0.3s;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    }
    
    .stats-card:hover {
        transform: translateY(-5px);
    }
    
    .icon-box {
        width: 60px;
        height: 60px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
    }
    
    .orders-icon { background: linear-gradient(45deg, #FF6B6B, #FF8E8E); }
    .sales-icon { background: linear-gradient(45deg, #4ECB71, #6EE699); }
    .products-icon { background: linear-gradient(45deg, #4E7DCB, #6E9EE6); }
    .customers-icon { background: linear-gradient(45deg, #CB4EC7, #E66EE2); }
    
    .dashboard-table {
        background: white;
        border-radius: 15px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    }
    
    .sidebar {
        background: #2c3e50;
        color: white;
        min-height: 100vh;
        padding-top: 20px;
    }
    
    .nav-link {
        color: #ecf0f1;
        padding: 10px 20px;
        margin: 5px 0;
        border-radius: 5px;
        transition: all 0.3s;
    }
    
    .nav-link:hover {
        background: #34495e;
        color: #fff;
    }
    
    .nav-link.active {
        background: #3498db;
        color: #fff;
    }
    
    .status-badge {
        padding: 5px 10px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: bold;
    }
    
    .profile-section {
        border-top: 1px solid #34495e;
        padding: 20px;
        margin-top: auto;
    }
    </style>
</head>
<body>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-md-2 sidebar position-fixed">
            <div class="d-flex flex-column h-100">
                <div class="text-center mb-4">
                    <i class="fas fa-store fa-3x mb-2"></i>
                    <h5>Grocery Admin</h5>
                </div>
                
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link active" href="dashboard.php">
                            <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="grocery_items.php">
                            <i class="fas fa-shopping-basket me-2"></i> Grocery Items
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="dishes.php">
                            <i class="fas fa-utensils me-2"></i> Dishes
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="orders.php">
                            <i class="fas fa-shopping-cart me-2"></i> Orders
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="sales_report.php">
                            <i class="fas fa-chart-bar me-2"></i> Sales Report
                        </a>
                    </li>
                </ul>
                
                <div class="profile-section mt-auto">
                    <div class="d-flex align-items-center mb-3">
                        <i class="fas fa-user-circle fa-2x me-2"></i>
                        <div>
                            <small class="d-block">Welcome,</small>
                            <strong><?php echo $_SESSION['admin_name']; ?></strong>
                        </div>
                    </div>
                    <a href="logout.php" class="btn btn-danger btn-sm w-100">
                        <i class="fas fa-sign-out-alt me-2"></i>Logout
                    </a>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="col-md-10 ms-auto">
            <div class="dashboard-container">
                <!-- Header -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-tachometer-alt me-2"></i>Dashboard</h2>
                    <div class="text-muted">
                        <i class="fas fa-clock me-2"></i><?php echo date('Y-m-d H:i:s'); ?>
                    </div>
                </div>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card stats-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-muted">Today's Orders</h6>
                                        <h3 class="mb-0"><?php echo $order_stats['total_orders']; ?></h3>
                                    </div>
                                    <div class="icon-box orders-icon text-white">
                                        <i class="fas fa-shopping-cart"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="card stats-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-muted">Today's Sales</h6>
                                        <h3 class="mb-0">Rs.<?php echo number_format($order_stats['total_sales'], 2); ?></h3>
                                    </div>
                                    <div class="icon-box sales-icon text-white">
                                        <i class="fas fa-rupee-sign"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="card stats-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-muted">Total Products</h6>
                                        <h3 class="mb-0"><?php echo $products_count['total_products']; ?></h3>
                                    </div>
                                    <div class="icon-box products-icon text-white">
                                        <i class="fas fa-box"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="card stats-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-muted">Total Customers</h6>
                                        <h3 class="mb-0"><?php echo $customers_count['total_customers']; ?></h3>
                                    </div>
                                    <div class="icon-box customers-icon text-white">
                                        <i class="fas fa-users"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Delivery Performance -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card dashboard-table">
                            <div class="card-header bg-white">
                                <h5 class="mb-0">
                                    <i class="fas fa-clock me-2"></i>Delivery Performance Today
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row text-center">
                                    <div class="col-6">
                                        <div class="p-3">
                                            <h3 class="text-success">
                                                <?php echo $order_stats['ontime_orders']; ?>
                                            </h3>
                                            <p class="mb-0 text-muted">On-Time Deliveries</p>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="p-3 border-start">
                                            <h3 class="text-danger">
                                                <?php echo $order_stats['delayed_orders']; ?>
                                            </h3>
                                            <p class="mb-0 text-muted">Delayed Deliveries</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="card dashboard-table">
                            <div class="card-header bg-white">
                                <h5 class="mb-0">
                                    <i class="fas fa-exclamation-triangle me-2"></i>Low Stock Alert
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Item</th>
                                                <th>Current Stock</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while($item = mysqli_fetch_assoc($low_stock)): ?>
                                            <tr>
                                                <td><?php echo $item['name']; ?></td>
                                                <td>
                                                    <span class="badge bg-danger">
                                                        <?php echo $item['stock_quantity']; ?> <?php echo $item['unit']; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <a href="edit_grocery.php?id=<?php echo $item['item_id']; ?>" 
                                                       class="btn btn-sm btn-warning">
                                                        <i class="fas fa-edit"></i> Update Stock
                                                    </a>
                                                </td>
                                            </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Latest Orders -->
                <div class="card dashboard-table">
                    <div class="card-header bg-white">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i class="fas fa-shopping-cart me-2"></i>Latest Orders
                            </h5>
                            <a href="orders.php" class="btn btn-primary btn-sm">
                                View All Orders
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Order ID</th>
                                        <th>Customer</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th>Delivery</th>
                                        <th>Time</th>
                                        	
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while($order = mysqli_fetch_assoc($latest_orders)): ?>
                                    <tr>
                                        <td>#<?php echo $order['order_id']; ?></td>
                                        <td>
                                            <div>
                                                <?php echo $order['customer_name']; ?>
                                                <small class="d-block text-muted">
                                                    <?php echo $order['customer_phone']; ?>
                                                </small>
                                            </div>
                                        </td>
                                        <td>Rs.<?php echo number_format($order['total_amount'], 2); ?></td>
                                        <td>
                                            <?php
                                            $status_class = [
                                                'pending' => 'bg-warning',
                                                'processing' => 'bg-info',
                                                'delivered' => 'bg-success',
                                                'cancelled' => 'bg-danger'
                                            ];
                                            ?>
                                            <span class="status-badge <?php echo $status_class[$order['order_status']]; ?>">
                                                <?php echo ucfirst($order['order_status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php
                                            $delivery_class = $order['delivery_status'] == 'delayed' ? 'bg-danger' : 'bg-success';
                                            ?>
                                            <span class="status-badge <?php echo $delivery_class; ?>">
                                                <?php echo ucfirst($order['delivery_status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <small>
                                                <?php echo date('d M Y H:i', strtotime($order['order_datetime'])); ?>
                                            </small>
                                        </td>
                                       
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Add active class to current nav item
document.addEventListener('DOMContentLoaded', function() {
    const currentLocation = window.location.pathname;
    const navLinks = document.querySelectorAll('.nav-link');
    
    navLinks.forEach(link => {
        if(link.getAttribute('href') === currentLocation.split('/').pop()) {
            link.classList.add('active');
        }
    });
});

// Initialize tooltips
var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl)
});
</script>

<?php include("../includes/footer.php"); ?>