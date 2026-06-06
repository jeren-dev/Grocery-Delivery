<?php
include("../includes/header.php");

// Set timezone
date_default_timezone_set('Asia/Kolkata');

 $current_datetime = date('Y-m-d H:i:s');
$current_user = isset($_SESSION['customer_name']) ? $_SESSION['customer_name'] : 'testt453';

// Initialize variables
$error_msg = $success_msg = "";
$category_filter = isset($_GET['category']) ? $_GET['category'] : '';
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = 8;
$offset = ($page - 1) * $records_per_page;

// Build query conditions
$conditions = ["d.status = 'active'"];
if($category_filter) {
    $conditions[] = "EXISTS (
        SELECT 1 FROM dish_grocery_items dgi 
        JOIN grocery_items gi ON dgi.item_id = gi.item_id 
        WHERE dgi.dish_id = d.dish_id AND gi.category = '".mysqli_real_escape_string($conn, $category_filter)."'
    )";
}
if($search_query) {
    $conditions[] = "(
        d.name LIKE '%".mysqli_real_escape_string($conn, $search_query)."%' OR 
        d.description LIKE '%".mysqli_real_escape_string($conn, $search_query)."%'
    )";
}

$where_clause = implode(" AND ", $conditions);

// Get total records for pagination
$total_query = mysqli_query($conn, "
    SELECT COUNT(DISTINCT d.dish_id) as count 
    FROM dishes d
    WHERE $where_clause
");
$total_records = mysqli_fetch_assoc($total_query)['count'];
$total_pages = ceil($total_records / $records_per_page);

// Fetch dishes with their ingredients
$dishes_query = mysqli_query($conn, "
    SELECT 
        d.*,
        GROUP_CONCAT(
            DISTINCT gi.name
            ORDER BY gi.name ASC
            SEPARATOR ', '
        ) as ingredients,
        GROUP_CONCAT(
            DISTINCT gi.category
            ORDER BY gi.category ASC
            SEPARATOR ', '
        ) as categories
    FROM dishes d
    LEFT JOIN dish_grocery_items dgi ON d.dish_id = dgi.dish_id
    LEFT JOIN grocery_items gi ON dgi.item_id = gi.item_id
    WHERE $where_clause
    GROUP BY d.dish_id
    ORDER BY d.created_at DESC
    LIMIT $offset, $records_per_page
");

// Fetch all unique categories for filter
$categories_query = mysqli_query($conn, "
    SELECT DISTINCT category 
    FROM grocery_items 
    WHERE status = 'active' 
    ORDER BY category
");

// Get cart count if user is logged in
$cart_count = 0;
if(isset($_SESSION['customer_id'])) {
    $cart_query = mysqli_query($conn, "
        SELECT COUNT(*) as count 
        FROM cart 
        WHERE customer_id = ".$_SESSION['customer_id']
    );
    $cart_count = mysqli_fetch_assoc($cart_query)['count'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <style>
    .dishes-container {
        background: #f8f9fa;
        min-height: 100vh;
        padding: 20px 0;
    }

    .search-section {
        background: linear-gradient(45deg, #007bff, #0056b3);
        padding: 3rem 0;
        margin-bottom: 2rem;
        color: white;
    }

    .dish-card {
        border: none;
        border-radius: 15px;
        transition: transform 0.3s, box-shadow 0.3s;
        overflow: hidden;
        height: 100%;
    }

    .dish-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    }

    .dish-card img {
        height: 200px;
        object-fit: cover;
    }

    .dish-card .badge {
        font-size: 0.8rem;
        padding: 0.5em 1em;
        margin-right: 0.5rem;
        margin-bottom: 0.5rem;
    }

    .category-filter {
        background: white;
        padding: 1rem;
        border-radius: 10px;
        margin-bottom: 2rem;
    }

    .category-badge {
        cursor: pointer;
        transition: all 0.3s;
        white-space: nowrap;
        margin: 0.25rem;
    }

    .category-badge:hover {
        transform: translateY(-2px);
    }

    .category-badge.active {
        background: #007bff;
        color: white;
    }

    .ingredients-list {
        font-size: 0.9rem;
        color: #6c757d;
    }

    .timer-badge {
        font-size: 0.875rem;
        padding: 0.5rem 1rem;
        border-radius: 10px;
        background: rgba(255,255,255,0.1);
        display: inline-block;
        margin-bottom: 1rem;
    }

    .pagination {
        margin-top: 2rem;
    }

    .pagination .page-link {
        border-radius: 50%;
        margin: 0 0.25rem;
        border: none;
    }

    .pagination .page-item.active .page-link {
        background: #007bff;
        border-color: #007bff;
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
                    <a class="nav-link " href="index.php">Home</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="dishes.php">Dishes</a>
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


<!-- Search Section -->
<section class="search-section">
    <div class="container">
        <div class="text-center mb-4">
            <span class="timer-badge">
                <i class="fas fa-clock me-2"></i>
                Current time: <?php echo $current_datetime; ?> IST
            </span>
            <h1 class="mb-3">Discover Delicious Dishes</h1>
            <p class="lead mb-4">Find recipes and get all ingredients delivered to your door</p>
        </div>

        <div class="row justify-content-center">
            <div class="col-md-6">
                <form action="" method="GET" class="mb-4">
                    <div class="input-group">
                        <input type="text" class="form-control form-control-lg" 
                               name="search" placeholder="Search for dishes..." 
                               value="<?php echo htmlspecialchars($search_query); ?>">
                        <button type="submit" class="btn btn-light">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>

<div class="dishes-container">
    <div class="container">
        <!-- Category Filter -->
      
        <!-- Dishes Grid -->
        <div class="row g-4">
            <?php if(mysqli_num_rows($dishes_query) > 0): ?>
                <?php while($dish = mysqli_fetch_assoc($dishes_query)): ?>
                    <div class="col-md-3">
                        <div class="card dish-card">
                            <img src="../assets/images/dishes/<?php echo $dish['image']; ?>" 
                                 class="card-img-top" 
                                 alt="<?php echo htmlspecialchars($dish['name']); ?>">
                            <div class="card-body">
                                <h5 class="card-title"><?php echo htmlspecialchars($dish['name']); ?></h5>
                                <div class="mb-3">
                                    <?php foreach(explode(', ', $dish['categories']) as $category): ?>
                                        <span class="badge bg-light text-dark">
                                            <?php echo ucwords(str_replace('-', ' ', $category)); ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                                <p class="ingredients-list mb-3">
                                    <i class="fas fa-leaf me-1"></i>
                                    <?php echo htmlspecialchars($dish['ingredients']); ?>
                                </p>
                                <a href="view_dish.php?id=<?php echo $dish['dish_id']; ?>" 
                                   class="btn btn-primary w-100">
                                    <i class="fas fa-info-circle me-1"></i>View Details
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="col-12 text-center py-5">
                    <i class="fas fa-search fa-3x mb-3 text-muted"></i>
                    <h3>No dishes found</h3>
                    <p class="text-muted">Try adjusting your search or filter criteria</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Pagination -->
        <?php if($total_pages > 1): ?>
            <nav aria-label="Page navigation" class="mt-4">
                <ul class="pagination justify-content-center">
                    <?php if($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo $page-1; ?>&category=<?php echo urlencode($category_filter); ?>&search=<?php echo urlencode($search_query); ?>">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        </li>
                    <?php endif; ?>

                    <?php for($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>&category=<?php echo urlencode($category_filter); ?>&search=<?php echo urlencode($search_query); ?>">
                                <?php echo $i; ?>
                            </a>
                        </li>
                    <?php endfor; ?>

                    <?php if($page < $total_pages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo $page+1; ?>&category=<?php echo urlencode($category_filter); ?>&search=<?php echo urlencode($search_query); ?>">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
        <?php endif; ?>
    </div>
</div>

<script>
// Initialize tooltips
var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl)
});

// Auto-hide alerts
setTimeout(function() {
    $('.alert').alert('close');
}, 5000);
</script>

<?php include("../includes/footer.php"); ?>