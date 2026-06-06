<?php
include("../includes/header.php");


// Initialize variables
$success_msg = $error_msg = "";

// Handle Delete Operation
if(isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $delete_query = mysqli_query($conn, "DELETE FROM grocery_items WHERE item_id = $id");
    if($delete_query) {
        $success_msg = "Item deleted successfully!";
    } else {
        $error_msg = "Error deleting item!";
    }
}

// Handle Status Toggle
if(isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $status_query = mysqli_query($conn, "UPDATE grocery_items SET 
        status = CASE WHEN status = 'active' THEN 'inactive' ELSE 'active' END 
        WHERE item_id = $id");
    if($status_query) {
        $success_msg = "Status updated successfully!";
    } else {
        $error_msg = "Error updating status!";
    }
}

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = 10;
$offset = ($page - 1) * $records_per_page;

// Search functionality
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$search_condition = $search ? "WHERE name LIKE '%$search%' OR description LIKE '%$search%'" : "";

// Get total records for pagination
$total_query = mysqli_query($conn, "SELECT COUNT(*) as count FROM grocery_items $search_condition");
$total_records = mysqli_fetch_assoc($total_query)['count'];
$total_pages = ceil($total_records / $records_per_page);

// Fetch grocery items
$items_query = mysqli_query($conn, "SELECT * FROM grocery_items 
    $search_condition 
    ORDER BY created_at DESC 
    LIMIT $offset, $records_per_page");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <style>
	
	
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
    .grocery-container {
        background: #f8f9fa;
        min-height: 100vh;
        padding: 20px;
    }
    
    .item-card {
        border: none;
        border-radius: 15px;
        transition: transform 0.3s;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    }
    
    .item-card:hover {
        transform: translateY(-5px);
    }
    
    .item-image {
        width: 80px;
        height: 80px;
        object-fit: cover;
        border-radius: 10px;
    }
    
    .status-badge {
        padding: 5px 10px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: bold;
    }
    
    .search-box {
        border-radius: 50px;
        padding: 10px 20px;
        border: 2px solid #eee;
        box-shadow: none;
        transition: all 0.3s;
    }
    
    .search-box:focus {
        border-color: #4ECB71;
        box-shadow: 0 0 0 0.2rem rgba(78, 203, 113, 0.25);
    }
    
    .action-btn {
        width: 35px;
        height: 35px;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: all 0.3s;
    }
    
    .pagination {
        margin: 0;
    }
    
    .page-link {
        border-radius: 5px;
        margin: 0 2px;
        color: #2c3e50;
    }
    
    .page-link:hover {
        background: #4ECB71;
        color: white;
    }
    
    .page-item.active .page-link {
        background: #4ECB71;
        border-color: #4ECB71;
    }
    
    .stock-warning {
        color: #dc3545;
        font-weight: bold;
    }
    
    .unit-badge {
        background: #e9ecef;
        padding: 2px 8px;
        border-radius: 12px;
        font-size: 12px;
    }
    </style>
</head>
<body>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar - Include the same sidebar as dashboard.php -->
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
                    <li class="nav-item active">
                        <a class="nav-link active" href="grocery_items.php">
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
            <div class="grocery-container">
                <!-- Header -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-shopping-basket me-2"></i>Grocery Items</h2>
                    <a href="add_grocery.php" class="btn btn-success">
                        <i class="fas fa-plus me-2"></i>Add New Item
                    </a>
                </div>

                <!-- Alert Messages -->
                <?php if($success_msg): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?php echo $success_msg; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>

                <?php if($error_msg): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo $error_msg; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>

                <!-- Search and Filter -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3 align-items-center">
                            <div class="col-md-8">
                                <div class="input-group">
                                    <span class="input-group-text bg-white">
                                        <i class="fas fa-search text-muted"></i>
                                    </span>
                                    <input type="text" name="search" class="form-control search-box" 
                                           placeholder="Search items..." value="<?php echo $search; ?>">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-filter me-2"></i>Apply Filter
                                </button>
                                <?php if($search): ?>
                                <a href="grocery_items.php" class="btn btn-secondary">
                                    <i class="fas fa-times me-2"></i>Clear
                                </a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Grocery Items List -->
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Image</th>
                                        <th>Name</th>
                                        <th>Price</th>
                                        <th>Stock</th>
                                        <th>Status</th>
                                        <th>Last Updated</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while($item = mysqli_fetch_assoc($items_query)): ?>
                                    <tr>
                                        <td>
                                            <img src="<?php echo $item['image'] ? '../assets/images/grocery/'.$item['image'] : '../assets/images/placeholder.png'; ?>" 
                                                 class="item-image" alt="<?php echo $item['name']; ?>">
                                        </td>
                                        <td>
                                            <div>
                                                <strong><?php echo $item['name']; ?></strong>
                                                <small class="d-block text-muted">
                                                    <?php echo substr($item['description'], 0, 50); ?>...
                                                </small>
                                            </div>
                                        </td>
                                        <td>
                                            <div>
                                                Rs.<?php echo number_format($item['price'], 2); ?>
                                                <span class="unit-badge">
                                                    per <?php echo $item['unit']; ?>
                                                </span>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if($item['stock_quantity'] < 10): ?>
                                            <span class="stock-warning">
                                                <?php echo $item['stock_quantity']; ?> <?php echo $item['unit']; ?>
                                            </span>
                                            <?php else: ?>
                                            <?php echo $item['stock_quantity']; ?> <?php echo $item['unit']; ?>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php
                                            $status_class = $item['status'] == 'active' ? 'bg-success' : 'bg-danger';
                                            ?>
                                            <span class="status-badge <?php echo $status_class; ?>">
                                                <?php echo ucfirst($item['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <small>
                                                <?php echo date('d M Y H:i', strtotime($item['updated_at'])); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <div class="d-flex gap-2">
                                                <a href="edit_grocery.php?id=<?php echo $item['item_id']; ?>" 
                                                   class="btn btn-warning action-btn" 
                                                   data-bs-toggle="tooltip" 
                                                   title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="?toggle=<?php echo $item['item_id']; ?>" 
                                                   class="btn btn-info action-btn"
                                                   data-bs-toggle="tooltip" 
                                                   title="Toggle Status">
                                                    <i class="fas fa-toggle-on"></i>
                                                </a>
                                                <a href="#" 
                                                   onclick="confirmDelete(<?php echo $item['item_id']; ?>)"
                                                   class="btn btn-danger action-btn"
                                                   data-bs-toggle="tooltip" 
                                                   title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            </div>
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
                                <?php echo $total_records; ?> items
                            </div>
                            <nav aria-label="Page navigation">
                                <ul class="pagination">
                                    <?php if($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page-1; ?>&search=<?php echo $search; ?>">
                                            <i class="fas fa-chevron-left"></i>
                                        </a>
                                    </li>
                                    <?php endif; ?>

                                    <?php for($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo $search; ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                    <?php endfor; ?>

                                    <?php if($page < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page+1; ?>&search=<?php echo $search; ?>">
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

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this item? This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <a href="#" id="confirmDelete" class="btn btn-danger">Delete</a>
            </div>
        </div>
    </div>
</div>

<script>
// Initialize tooltips
var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl)
});

// Delete confirmation
function confirmDelete(id) {
    var modal = new bootstrap.Modal(document.getElementById('deleteModal'));
    document.getElementById('confirmDelete').href = '?delete=' + id;
    modal.show();
}

// Auto-hide alerts
document.addEventListener('DOMContentLoaded', function() {
    var alerts = document.querySelectorAll('.alert');
    alerts.forEach(function(alert) {
        setTimeout(function() {
            var bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        }, 5000);
    });
});
</script>

<?php include("../includes/footer.php"); ?>