<?php
include("../includes/header.php");

error_reporting(0);
// Set timezone
date_default_timezone_set("Asia/Calcutta");
$current_datetime = '2025-04-25 05:22:16';
$current_user = 'admin';

// Initialize variables
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$report_type = isset($_GET['report_type']) ? $_GET['report_type'] : 'daily';

try {
    // Get sales statistics
    $stats_query = mysqli_query($conn, "
        SELECT 
            COUNT(*) as total_orders,
            SUM(total_amount) as total_sales,
            SUM(delivery_charge) as total_delivery_charges,
            SUM(CASE WHEN delivery_status = 'delayed' THEN 1 ELSE 0 END) as delayed_deliveries,
            SUM(CASE WHEN delivery_status = 'on_time' THEN 1 ELSE 0 END) as ontime_deliveries,
            SUM(CASE WHEN order_status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_orders,
            AVG(CASE 
                WHEN delivery_datetime IS NOT NULL 
                THEN TIMESTAMPDIFF(MINUTE, order_datetime, delivery_datetime)
                ELSE NULL 
            END) as avg_delivery_time
        FROM orders
        WHERE order_datetime BETWEEN '$start_date 00:00:00' AND '$end_date 23:59:59'
    ");
    
    $stats = mysqli_fetch_assoc($stats_query);

    // Get sales by date
    $sales_query = mysqli_query($conn, "
        SELECT 
            DATE(order_datetime) as date,
            COUNT(*) as orders,
            SUM(total_amount) as sales,
            SUM(delivery_charge) as delivery_charges,
            SUM(CASE WHEN delivery_status = 'delayed' THEN 1 ELSE 0 END) as delayed,
            SUM(CASE WHEN delivery_status = 'on_time' THEN 1 ELSE 0 END) as ontime
        FROM orders
        WHERE order_datetime BETWEEN '$start_date 00:00:00' AND '$end_date 23:59:59'
        GROUP BY DATE(order_datetime)
        ORDER BY date
    ");

    $sales_data = [];
    while($row = mysqli_fetch_assoc($sales_query)) {
        $sales_data[] = $row;
    }

    // Get top selling items
    $top_items_query = mysqli_query($conn, "
        SELECT 
            gi.item_id,
            gi.name,
            gi.category,
            COUNT(oi.item_id) as times_ordered,
            SUM(oi.quantity) as total_quantity,
            SUM(oi.quantity * oi.price_per_unit) as total_revenue
        FROM order_items oi
        JOIN grocery_items gi ON oi.item_id = gi.item_id
        JOIN orders o ON oi.order_id = o.order_id
        WHERE o.order_datetime BETWEEN '$start_date 00:00:00' AND '$end_date 23:59:59'
        GROUP BY gi.item_id
        ORDER BY total_revenue DESC
        LIMIT 10
    ");

    // Get sales by category
    $category_sales_query = mysqli_query($conn, "
        SELECT 
            gi.category,
            COUNT(DISTINCT o.order_id) as total_orders,
            SUM(oi.quantity) as total_quantity,
            SUM(oi.quantity * oi.price_per_unit) as total_revenue
        FROM order_items oi
        JOIN grocery_items gi ON oi.item_id = gi.item_id
        JOIN orders o ON oi.order_id = o.order_id
        WHERE o.order_datetime BETWEEN '$start_date 00:00:00' AND '$end_date 23:59:59'
        GROUP BY gi.category
        ORDER BY total_revenue DESC
    ");

} catch(Exception $e) {
    $error_msg = $e->getMessage();
}

// Handle Export
if(isset($_POST['export'])) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="sales_report_'.$start_date.'_to_'.$end_date.'.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Add headers
    fputcsv($output, ['Date', 'Orders', 'Sales', 'Delivery Charges', 'On-time Deliveries', 'Delayed Deliveries']);
    
    // Add data
    foreach($sales_data as $row) {
        fputcsv($output, [
            $row['date'],
            $row['orders'],
            $row['sales'],
            $row['delivery_charges'],
            $row['ontime'],
            $row['delayed']
        ]);
    }
    
    fclose($output);
    exit();
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Include Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
    .sales-container {
        background: #f8f9fa;
        min-height: 100vh;
        padding: 20px;
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
    
    .stats-icon {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
    }
    
    .chart-container {
        position: relative;
        height: 300px;
    }
    
    .delivery-stats {
        display: flex;
        gap: 20px;
    }
    
    .delivery-stat {
        flex: 1;
        padding: 20px;
        border-radius: 12px;
        text-align: center;
    }
    
    .ontime {
        background-color: #d4edda;
        color: #155724;
    }
    
    .delayed {
        background-color: #f8d7da;
        color: #721c24;
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
    
    .profile-section {
        border-top: 1px solid #34495e;
        padding: 20px;
        margin-top: auto;
    }

    .container-fluid {
        padding: 0;
    }

    .col-md-10 {
        padding-left: 0;
    }

    .card {
        margin-bottom: 20px;
        border-radius: 10px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }

    .table th {
        font-weight: 600;
        background: #f8f9fa;
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
                        <a class="nav-link" href="dashboard.php">
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
                        <a class="nav-link active" href="sales_report.php">
                            <i class="fas fa-chart-bar me-2"></i> Sales Report
                        </a>
                    </li>
                </ul>
                
                <div class="profile-section mt-auto">
                    <div class="d-flex align-items-center mb-3">
                        <i class="fas fa-user-circle fa-2x me-2"></i>
                        <div>
                            <small class="d-block">Welcome,</small>
                            <strong><?php echo $current_user; ?></strong>
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
            <div class="sales-container">
                <!-- Current User Info -->
                <div class="current-user-info mb-4">
                    <div class="row align-items-center">
                        <div class="col-auto">
                            <i class="fas fa-user-circle fa-2x text-primary"></i>
                        </div>
                        <div class="col">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <strong>Logged in as:</strong> <?php echo htmlspecialchars($current_user); ?>
                                </div>
                               
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Header -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-chart-line me-2"></i>Sales Report</h2>
                    
                </div>

                <!-- Date Range Filter -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3 align-items-end">
                            <div class="col-md-3">
                                <label class="form-label">Start Date</label>
                                <input type="date" class="form-control" name="start_date" 
                                       value="<?php echo $start_date; ?>" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">End Date</label>
                                <input type="date" class="form-control" name="end_date" 
                                       value="<?php echo $end_date; ?>" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Report Type</label>
                                <select class="form-control" name="report_type">
                                    <option value="daily" <?php echo $report_type == 'daily' ? 'selected' : ''; ?>>Daily</option>
                                    <option value="weekly" <?php echo $report_type == 'weekly' ? 'selected' : ''; ?>>Weekly</option>
                                    <option value="monthly" <?php echo $report_type == 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-filter me-2"></i>Generate Report
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Statistics Cards -->
                <div class="row g-4 mb-4">
                    <div class="col-md-3">
                        <div class="card stats-card">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="stats-icon bg-primary text-white">
                                        <i class="fas fa-shopping-cart"></i>
                                    </div>
                                    <div class="ms-3">
                                        <h6 class="card-subtitle text-muted mb-1">Total Orders</h6>
                                        <h3 class="card-title mb-0"><?php echo number_format($stats['total_orders']); ?></h3>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stats-card">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="stats-icon bg-success text-white">
                                        <i class="fas fa-rupee-sign"></i>
                                    </div>
                                    <div class="ms-3">
                                        <h6 class="card-subtitle text-muted mb-1">Total Sales</h6>
                                        <h3 class="card-title mb-0">Rs.<?php echo number_format($stats['total_sales'], 2); ?></h3>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stats-card">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="stats-icon bg-warning text-white">
                                        <i class="fas fa-truck"></i>
                                    </div>
                                    <div class="ms-3">
                                        <h6 class="card-subtitle text-muted mb-1">Avg. Delivery Time</h6>
                                        <h3 class="card-title mb-0"><?php echo round($stats['avg_delivery_time']); ?> mins</h3>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stats-card">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="stats-icon bg-danger text-white">
                                        <i class="fas fa-times-circle"></i>
                                    </div>
                                    <div class="ms-3">
                                        <h6 class="card-subtitle text-muted mb-1">Cancelled Orders</h6>
                                        <h3 class="card-title mb-0"><?php echo number_format($stats['cancelled_orders']); ?></h3>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Delivery Performance -->
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title mb-4">Delivery Performance</h5>
                        <div class="delivery-stats">
                            <div class="delivery-stat ontime">
                                <h3><?php echo number_format($stats['ontime_deliveries']); ?></h3>
                                <p class="mb-0">On-time Deliveries</p>
                                <small>
                                    <?php 
                                    $ontime_percent = $stats['total_orders'] ? 
                                        round(($stats['ontime_deliveries'] / $stats['total_orders']) * 100, 1) : 0;
                                    echo $ontime_percent . '%';
                                    ?>
                                </small>
                            </div>
                            <div class="delivery-stat delayed">
                                <h3><?php echo number_format($stats['delayed_deliveries']); ?></h3>
                                <p class="mb-0">Delayed Deliveries</p>
                                <small>
                                    <?php 
                                    $delayed_percent = $stats['total_orders'] ? 
                                        round(($stats['delayed_deliveries'] / $stats['total_orders']) * 100, 1) : 0;
                                    echo $delayed_percent . '%';
                                    ?>
                                </small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sales Chart -->
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title mb-4">Sales Trend</h5>
                        <div class="chart-container">
                            <canvas id="salesChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Top Selling Items and Category Charts -->
                <div class="row">
                    <div class="col-md-6">
                        <div class="card mb-4">
                            <div class="card-body">
                                <h5 class="card-title mb-4">Top Selling Items</h5>
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Item</th>
                                                <th>Category</th>
                                                <th>Quantity</th>
                                                <th>Revenue</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while($item = mysqli_fetch_assoc($top_items_query)): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($item['name']); ?></td>
                                                <td><?php echo ucwords(str_replace('-', ' ', $item['category'])); ?></td>
                                                <td><?php echo number_format($item['total_quantity']); ?></td>
                                                <td>Rs.<?php echo number_format($item['total_revenue'], 2); ?></td>
                                            </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card mb-4">
                            <div class="card-body">
                                <h5 class="card-title mb-4">Category-wise Sales</h5>
                                <div class="chart-container">
                                    <canvas id="categoryChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Sales Chart
const salesCtx = document.getElementById('salesChart').getContext('2d');
new Chart(salesCtx, {
    type: 'line',
    data: {
        labels: <?php echo json_encode(array_column($sales_data, 'date')); ?>,
        datasets: [{
            label: 'Sales (?)',
            data: <?php echo json_encode(array_column($sales_data, 'sales')); ?>,
            borderColor: 'rgb(75, 192, 192)',
            tension: 0.1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true
            }
        }
    }
});

// Category Chart
const categoryCtx = document.getElementById('categoryChart').getContext('2d');
new Chart(categoryCtx, {
    type: 'doughnut',
    data: {
        labels: <?php 
            $categories = [];
            $revenues = [];
            mysqli_data_seek($category_sales_query, 0);
            while($cat = mysqli_fetch_assoc($category_sales_query)) {
                $categories[] = ucwords(str_replace('-', ' ', $cat['category']));
                $revenues[] = $cat['total_revenue'];
            }
            echo json_encode($categories);
        ?>,
        datasets: [{
            data: <?php echo json_encode($revenues); ?>,
            backgroundColor: [
                '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF',
                '#FF9F40', '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0'
            ]
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false
    }
});

// Auto-hide alerts
setTimeout(function() {
    $('.alert').alert('close');
}, 5000);
</script>

<?php include("../includes/footer.php"); ?>