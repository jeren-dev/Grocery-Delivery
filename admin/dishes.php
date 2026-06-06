<?php
include("../includes/header.php");


// Initialize variables
$success_msg = $error_msg = "";

// Handle Delete Operation
if(isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    // First delete from mapping table
    mysqli_query($conn, "DELETE FROM dish_grocery_items WHERE dish_id = $id");
    // Then delete the dish
    $delete_query = mysqli_query($conn, "DELETE FROM dishes WHERE dish_id = $id");
    if($delete_query) {
        $success_msg = "Dish deleted successfully!";
    } else {
        $error_msg = "Error deleting dish!";
    }
}

// Handle Status Toggle
if(isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $status_query = mysqli_query($conn, "UPDATE dishes SET 
        status = CASE WHEN status = 'active' THEN 'inactive' ELSE 'active' END 
        WHERE dish_id = $id");
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
$search_condition = $search ? "WHERE d.name LIKE '%$search%' OR d.description LIKE '%$search%'" : "";

// Get total records for pagination
$total_query = mysqli_query($conn, "SELECT COUNT(*) as count FROM dishes d $search_condition");
$total_records = mysqli_fetch_assoc($total_query)['count'];
$total_pages = ceil($total_records / $records_per_page);

// Fetch dishes with their recommended items count
$dishes_query = mysqli_query($conn, "
    SELECT 
        d.*,
        COUNT(dgi.item_id) as recommended_items_count,
        GROUP_CONCAT(gi.name SEPARATOR ', ') as recommended_items
    FROM dishes d
    LEFT JOIN dish_grocery_items dgi ON d.dish_id = dgi.dish_id
    LEFT JOIN grocery_items gi ON dgi.item_id = gi.item_id
    $search_condition
    GROUP BY d.dish_id
    ORDER BY d.created_at DESC 
    LIMIT $offset, $records_per_page
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <style>
    .dishes-container {
        background: #f8f9fa;
        min-height: 100vh;
        padding: 20px;
    }
    
    .dish-card {
        border: none;
        border-radius: 15px;
        transition: transform 0.3s;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    }
    
    .dish-card:hover {
        transform: translateY(-5px);
    }
    
    .dish-image {
        width: 100px;
        height: 100px;
        object-fit: cover;
        border-radius: 10px;
    }
    
    .recommended-items {
        max-height: 60px;
        overflow: hidden;
        text-overflow: ellipsis;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
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
    
    .items-count {
        background: #e9ecef;
        padding: 2px 8px;
        border-radius: 12px;
        font-size: 12px;
        color: #495057;
    }

    .current-user-info {
        background: rgba(255, 255, 255, 0.9);
        border-radius: 10px;
        padding: 10px 15px;
        margin-bottom: 20px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
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
                        <a class="nav-link " href="grocery_items.php">
                            <i class="fas fa-shopping-basket me-2"></i> Grocery Items
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="dishes.php">
                            <i class="fas fa-utensils me-2"></i> Dishes
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link " href="orders.php">
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
            <div class="dishes-container">
                <!-- Current User Info -->
                <div class="current-user-info">
                    <div class="row align-items-center">
                        <div class="col-auto">
                            <i class="fas fa-user-circle fa-2x text-primary"></i>
                        </div>
                        <div class="col">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <strong>Logged in as:</strong> <?php echo htmlspecialchars($_SESSION['admin_name']); ?>
                                </div>
                                <div>
                                    <i class="fas fa-clock me-2"></i>
                                    <?php echo date('Y-m-d H:i:s'); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Header -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-utensils me-2"></i>Dishes</h2>
                    <a href="add_dish.php" class="btn btn-success">
                        <i class="fas fa-plus me-2"></i>Add New Dish
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
                                           placeholder="Search dishes..." value="<?php echo htmlspecialchars($search); ?>">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-filter me-2"></i>Apply Filter
                                </button>
                                <?php if($search): ?>
                                <a href="dishes.php" class="btn btn-secondary">
                                    <i class="fas fa-times me-2"></i>Clear
                                </a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Dishes List -->
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Image</th>
                                        <th>Name</th>
                                        <th>Recommended Items</th>
                                        <th>Status</th>
                                        <th>Last Updated</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while($dish = mysqli_fetch_assoc($dishes_query)): ?>
                                    <tr>
                                        <td>
                                            <img src="<?php echo $dish['image'] ? '../assets/images/dishes/'.$dish['image'] : '../assets/images/placeholder.png'; ?>" 
                                                 class="dish-image" alt="<?php echo htmlspecialchars($dish['name']); ?>">
                                        </td>
                                        <td>
                                            <div>
                                                <strong><?php echo htmlspecialchars($dish['name']); ?></strong>
                                                <small class="d-block text-muted">
                                                    <?php echo substr($dish['description'], 0, 50); ?>...
                                                </small>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="items-count">
                                                <?php echo $dish['recommended_items_count']; ?> items
                                            </span>
                                            <div class="recommended-items small text-muted mt-1">
                                                <?php echo htmlspecialchars($dish['recommended_items'] ?: 'No items recommended'); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?php
                                            $status_class = $dish['status'] == 'active' ? 'bg-success' : 'bg-danger';
                                            ?>
                                            <span class="status-badge <?php echo $status_class; ?>">
                                                <?php echo ucfirst($dish['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <small>
                                                <?php echo date('d M Y H:i', strtotime($dish['updated_at'])); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <div class="d-flex gap-2">
                                                <a href="edit_dish.php?id=<?php echo $dish['dish_id']; ?>" 
                                                   class="btn btn-warning action-btn" 
                                                   data-bs-toggle="tooltip" 
                                                   title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="?toggle=<?php echo $dish['dish_id']; ?>" 
                                                   class="btn btn-info action-btn"
                                                   data-bs-toggle="tooltip" 
                                                   title="Toggle Status">
                                                    <i class="fas fa-toggle-on"></i>
                                                </a>
                                                <a href="#" 
                                                   onclick="confirmDelete(<?php echo $dish['dish_id']; ?>)"
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
                                <?php echo $total_records; ?> dishes
                            </div>
                            <nav aria-label="Page navigation">
                                <ul class="pagination">
                                    <?php if($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>">
                                            <i class="fas fa-chevron-left"></i>
                                        </a>
                                    </li>
                                    <?php endif; ?>

                                    <?php for($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                    <?php endfor; ?>

                                    <?php if($page < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>">
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
                <p>Are you sure you want to delete this dish? This will also remove all recommended item associations.</p>
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
setTimeout(function() {
    $('.alert').alert('close');
}, 5000);
</script>

<?php include("../includes/footer.php"); ?>