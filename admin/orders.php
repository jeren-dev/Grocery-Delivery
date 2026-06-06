<?php
include("../includes/header.php");


// Set timezone
 date_default_timezone_set("Asia/Calcutta"); 
$current_datetime = date('Y-m-d H:i:s');
$current_user = $_SESSION['admin_name'];

// Initialize variables
$success_msg = $error_msg = "";
function sendSMS($mobile, $message) {
    // Validate inputs
    if (empty($mobile) || empty($message)) {
        return [
            'success' => false,
            'error' => 'Mobile number or message is empty',
            'debug_info' => ['mobile' => $mobile, 'message_length' => strlen($message)]
        ];
    }

    $apikey = '6555c521622c1';
    $sender = 'FSSMSS';
    $route = 'transsms';
    
    // URL encode message and create full message
    $full_message = "Dear customer your msg is " . $message . " Sent By FSMSG FSSMSS";
    $encoded_message = urlencode($full_message);
    
    // Create the API URL
    $url = "http://sms.creativepoint.in/api/push.json?"
         . "apikey=" . $apikey 
         . "&route=" . $route 
         . "&sender=" . $sender 
         . "&mobileno=" . $mobile 
         . "&text=" . $encoded_message;
    
    // Debug information array
    $debug_info = [
        'url_length' => strlen($url),
        'message_length' => strlen($full_message),
        'encoded_length' => strlen($encoded_message)
    ];
    
    // Initialize cURL with verbose debugging
    $ch = curl_init();
    
    // Create temp file for CURL debugging
    $verbose = fopen('php://temp', 'w+');
    
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_VERBOSE => true,
        CURLOPT_STDERR => $verbose
    ]);
    
    // Execute request
    $response = curl_exec($ch);
    $curl_error = curl_error($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    // Get verbose debug information
    rewind($verbose);
    $verbose_log = stream_get_contents($verbose);
    fclose($verbose);
    
    // Get additional curl debug info
    $debug_info['curl_info'] = curl_getinfo($ch);
    $debug_info['verbose_log'] = $verbose_log;
    
    curl_close($ch);
    
    // Log attempt with detailed information
    $log_message = date('Y-m-d H:i:s') . " - SMS Attempt\n"
                 . "Mobile: $mobile\n"
                 . "HTTP Code: $http_code\n"
                 . "cURL Error: " . ($curl_error ?: 'None') . "\n"
                 . "Response: " . ($response ?: 'No response') . "\n"
                 . "Debug Info: " . print_r($debug_info, true) . "\n"
                 . "------------------------\n";
    
    error_log($log_message, 3, __DIR__ . '/detailed_sms_log.txt');
    
    // Parse response if exists
    $response_data = null;
    if ($response) {
        $response_data = json_decode($response, true);
    }
    
    return [
        'success' => ($response !== false && $http_code == 200 && 
                     isset($response_data['status']) && $response_data['status'] == 'success'),
        'response' => $response_data,
        'error' => $curl_error,
        'http_code' => $http_code,
        'debug_info' => $debug_info
    ];
}


// Handle Status Updates
// Handle Status Updates
if(isset($_POST['update_status'])) {
    $order_id = (int)$_POST['order_id'];
    $new_status = mysqli_real_escape_string($conn, $_POST['order_status']);
    $delivery_datetime = ($new_status == 'delivered') ? $current_datetime : NULL;
    
    try {
        mysqli_begin_transaction($conn);
        
        // Get order and customer details first
        $order_query = mysqli_query($conn, "
            SELECT o.*, c.phone, c.name 
            FROM orders o 
            JOIN customers c ON o.customer_id = c.customer_id 
            WHERE o.order_id = $order_id
        ");
        
        if(!$order_query) {
            throw new Exception("Failed to fetch order details");
        }
        
        $order = mysqli_fetch_assoc($order_query);
        
        // Check delivery time if status is being set to delivered
        if($new_status == 'delivered') {
            $order_time = strtotime($order['order_datetime']);
            $delivery_time = strtotime($current_datetime);
            $time_diff_minutes = ($delivery_time - $order_time) / 60;
            
            // If delivery took more than 20 minutes
            if($time_diff_minutes > 20) {
                // Update order to remove delivery charge and adjust total
                mysqli_query($conn, "
                    UPDATE orders 
                    SET 
                        delivery_charge = 0,
                        total_amount = total_amount - 50.00,
                        delivery_status = 'delayed'
                    WHERE order_id = $order_id
                ");
                
                // Get updated total amount
                $order['total_amount'] -= 50.00;
            }
        }
        
        // Update order status
        $update_query = mysqli_query($conn, "
            UPDATE orders 
            SET order_status = '$new_status',
                delivery_datetime = " . ($delivery_datetime ? "'$delivery_datetime'" : "NULL") . "
            WHERE order_id = $order_id
        ");
        
        if($update_query) {
            // If status is changed to delivered, send SMS
            if($new_status == 'delivered' && !empty($order['phone'])) {
                // Prepare message
                $message = "Your order #$order_id has been delivered. ";
                if($time_diff_minutes > 20) {
                   
                }
                $message = "Your order has been delivered";
                
                // Send SMS
                $sms_result = sendSMS($order['phone'], $message);
                
                // Log SMS result
                error_log(
                    date('Y-m-d H:i:s') . " - Order #$order_id SMS Result\n" .
                    "Phone: " . $order['phone'] . "\n" .
                    "Message: " . $message . "\n" .
                    "Result: " . print_r($sms_result, true) . "\n" .
                    "------------------------\n",
                    3,
                    __DIR__ . '/order_sms_log.txt'
                );
            }
            
            mysqli_commit($conn);
            $success_msg = "Order status updated successfully!" . 
                          ($new_status == 'delivered' ? 
                           ($sms_result['success'] ? " SMS notification sent." : " SMS sending failed.") : "");
        } else {
            throw new Exception("Error updating order status");
        }
    } catch(Exception $e) {
        mysqli_rollback($conn);
        $error_msg = $e->getMessage();
        error_log("Order status update failed: " . $e->getMessage());
    }
}

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = 10;
$offset = ($page - 1) * $records_per_page;

// Filters
$status_filter = isset($_GET['status']) ? mysqli_real_escape_string($conn, $_GET['status']) : '';
$date_filter = isset($_GET['date']) ? mysqli_real_escape_string($conn, $_GET['date']) : '';
$delivery_status_filter = isset($_GET['delivery_status']) ? mysqli_real_escape_string($conn, $_GET['delivery_status']) : '';

// Build filter conditions
$conditions = [];
if($status_filter) {
    $conditions[] = "o.order_status = '$status_filter'";
}
if($date_filter) {
    $conditions[] = "DATE(o.order_datetime) = '$date_filter'";
}
if($delivery_status_filter) {
    $conditions[] = "o.delivery_status = '$delivery_status_filter'";
}

$where_clause = $conditions ? "WHERE " . implode(" AND ", $conditions) : "";

// Get total records for pagination
$total_query = mysqli_query($conn, "
    SELECT COUNT(*) as count 
    FROM orders o 
    $where_clause
");
$total_records = mysqli_fetch_assoc($total_query)['count'];
$total_pages = ceil($total_records / $records_per_page);

// Fetch orders with customer details and items
$orders_query = mysqli_query($conn, "
    SELECT 
        o.*,
        c.name as customer_name,
        c.phone as customer_phone,
        COUNT(oi.item_id) as total_items,
        GROUP_CONCAT(
            CONCAT(gi.name, ' (', oi.quantity, ' ', gi.unit, ')')
            SEPARATOR ', '
        ) as items_list
    FROM orders o
    LEFT JOIN customers c ON o.customer_id = c.customer_id
    LEFT JOIN order_items oi ON o.order_id = oi.order_id
    LEFT JOIN grocery_items gi ON oi.item_id = gi.item_id
    $where_clause
    GROUP BY o.order_id
    ORDER BY o.order_datetime DESC
    LIMIT $offset, $records_per_page
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <style>
    .orders-container {
        background: #f8f9fa;
        min-height: 100vh;
        padding: 20px;
    }
    
    .status-badge {
        padding: 5px 10px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: bold;
    }
    
    .status-pending { background-color: #ffd700; color: #000; }
    .status-processing { background-color: #87ceeb; color: #000; }
    .status-delivered { background-color: #90ee90; color: #000; }
    .status-cancelled { background-color: #ff6b6b; color: #fff; }
    
    .delivery-status-on_time { background-color: #98FB98; color: #000; }
    .delivery-status-delayed { background-color: #FFB6C1; color: #000; }
    
    .order-items {
        max-height: 100px;
        overflow-y: auto;
    }
    
    .delivery-timer {
        font-size: 0.9rem;
        color: #6c757d;
    }
    
    .delivery-warning {
        color: #dc3545;
        font-weight: bold;
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
                        <a class="nav-link " href="dashboard.php">
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
                        <a class="nav-link active" href="orders.php">
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
            <div class="orders-container">
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
                                <div>
                                    <i class="fas fa-clock me-2"></i>
                                    <?php echo $current_datetime; ?> IST
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Header -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-shopping-bag me-2"></i>Orders</h2>
                </div>

                <!-- Alert Messages -->
                <?php if($success_msg): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?php echo $success_msg; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <?php if($error_msg): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo $error_msg; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Order Status</label>
                                <select class="form-control" name="status">
                                    <option value="">All Status</option>
                                    <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="processing" <?php echo $status_filter == 'processing' ? 'selected' : ''; ?>>Processing</option>
                                    <option value="delivered" <?php echo $status_filter == 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                                  
                                </select>
                            </div>
                            
                            <div class="col-md-3">
                                <label class="form-label">Delivery Status</label>
                                <select class="form-control" name="delivery_status">
                                    <option value="">All</option>
                                    <option value="on_time" <?php echo $delivery_status_filter == 'on_time' ? 'selected' : ''; ?>>On Time</option>
                                    <option value="delayed" <?php echo $delivery_status_filter == 'delayed' ? 'selected' : ''; ?>>Delayed</option>
                                </select>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">Date</label>
                                <input type="date" class="form-control" name="date" value="<?php echo $date_filter; ?>">
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">&nbsp;</label>
                                <div>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-filter me-2"></i>Apply Filters
                                    </button>
                                    <?php if($status_filter || $date_filter || $delivery_status_filter): ?>
                                    <a href="orders.php" class="btn btn-secondary">
                                        <i class="fas fa-times me-2"></i>Clear
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Orders List -->
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Order ID</th>
                                        <th>Customer</th>
                                        <th>Items</th>
                                        <th>Amount</th>
                                        <th>Delivery Charge</th>
                                        <th>Status</th>
                                        <th>Order Time</th>
                                        <th>Delivery Time</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while($order = mysqli_fetch_assoc($orders_query)): ?>
                                    <tr>
                                        <td>#<?php echo $order['order_id']; ?></td>
                                        <td>
                                            <div>
                                                <strong><?php echo htmlspecialchars($order['customer_name']); ?></strong>
                                                <small class="d-block text-muted">
                                                    <i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($order['customer_phone']); ?>
                                                </small>
                                                <small class="d-block text-muted">
                                                    <i class="fas fa-map-marker-alt me-1"></i>
                                                    <?php echo htmlspecialchars(substr($order['shipping_address'], 0, 50)); ?>...
                                                </small>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="order-items">
                                                <?php echo nl2br(htmlspecialchars($order['items_list'])); ?>
                                            </div>
                                        </td>
                                        <td>
                                            Rs.<?php echo number_format($order['total_amount'], 2); ?>
                                        </td>
                                        <td>
                                            Rs.<?php echo number_format($order['delivery_charge'], 2); ?>
                                            <?php if($order['delivery_charge'] == 0): ?>
                                            <span class="badge bg-danger">Free</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="status-badge status-<?php echo $order['order_status']; ?>">
                                                <?php echo ucfirst($order['order_status']); ?>
                                            </span>
                                            <?php if($order['delivery_status']): ?>
                                            <span class="status-badge delivery-status-<?php echo $order['delivery_status']; ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $order['delivery_status'])); ?>
                                            </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php echo date('Y-m-d H:i:s', strtotime($order['order_datetime'])); ?>
                                            <?php if($order['order_status'] == 'pending' || $order['order_status'] == 'processing'): ?>
                                            <div class="delivery-timer">
                                                <?php
                                                $time_elapsed = floor((strtotime($current_datetime) - strtotime($order['order_datetime'])) / 60);
                                                if($time_elapsed > 20): ?>
                                                <span class="delivery-warning">
                                                    <i class="fas fa-exclamation-triangle me-1"></i>
                                                    Delayed by <?php echo $time_elapsed - 20; ?> minutes
                                                </span>
                                                <?php else: ?>
                                                <span>
                                                    <i class="fas fa-clock me-1"></i>
                                                    <?php echo 20 - $time_elapsed; ?> minutes remaining
                                                </span>
                                                <?php endif; ?>
                                            </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php echo $order['delivery_datetime'] ? date('Y-m-d H:i:s', strtotime($order['delivery_datetime'])) : '-'; ?>
                                        </td>
                                        <td>
                                            <?php if($order['order_status'] != 'delivered' && $order['order_status'] != 'cancelled'): ?>
                                            <form method="POST" class="d-inline-block">
                                                <input type="hidden" name="order_id" value="<?php echo $order['order_id']; ?>">
                                                <select class="form-control form-control-sm d-inline-block w-auto" name="order_status" onChange="this.form.submit()">
                                                    <option value="">Update Status</option>
                                                    <option value="processing" <?php echo $order['order_status'] == 'processing' ? 'disabled' : ''; ?>>Processing</option>
                                                    <option value="delivered">Delivered</option>
                                                  
                                                </select>
                                                <input type="hidden" name="update_status" value="1">
                                            </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <?php if($total_pages > 1): ?>
                        <div class="d-flex justify-content-between align-items-center mt-4">
                            <div class="text-muted">
                                Showing <?php echo $offset + 1; ?> to 
                                <?php echo min($offset + $records_per_page, $total_records); ?> of 
                                <?php echo $total_records; ?> orders
                            </div>
                            <nav aria-label="Page navigation">
                                <ul class="pagination">
                                    <?php if($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page-1; ?>&status=<?php echo $status_filter; ?>&date=<?php echo $date_filter; ?>&delivery_status=<?php echo $delivery_status_filter; ?>">
                                            <i class="fas fa-chevron-left"></i>
                                        </a>
                                    </li>
                                    <?php endif; ?>

                                    <?php for($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>&status=<?php echo $status_filter; ?>&date=<?php echo $date_filter; ?>&delivery_status=<?php echo $delivery_status_filter; ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                    <?php endfor; ?>

                                    <?php if($page < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page+1; ?>&status=<?php echo $status_filter; ?>&date=<?php echo $date_filter; ?>&delivery_status=<?php echo $delivery_status_filter; ?>">
                                            <i class="fas fa-chevron-right"></i>
                                        </a>
                                    </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Auto-refresh page every minute to update timers
setTimeout(function() {
    location.reload();
}, 60000);

// Auto-hide alerts
setTimeout(function() {
    $('.alert').alert('close');
}, 5000);

// Confirm status update
document.querySelectorAll('select[name="order_status"]').forEach(function(select) {
    select.addEventListener('change', function(e) {
        if(!confirm('Are you sure you want to update the order status?')) {
            e.preventDefault();
            this.value = '';
            return false;
        }
    });
});
</script>

<?php include("../includes/footer.php"); ?>