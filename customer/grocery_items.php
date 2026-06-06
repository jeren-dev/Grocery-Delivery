<?php
include("../includes/header.php");

// Check if customer is logged in

// Set timezone
date_default_timezone_set('Asia/Kolkata');

 $current_datetime = date('Y-m-d H:i:s');
$current_user = isset($_SESSION['customer_name']) ? $_SESSION['customer_name'] : 'testt453';
// Initialize variables
$error_msg = $success_msg = "";
$items_per_page = 12;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $items_per_page;

// Get search and filter parameters
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, trim($_GET['search'])) : '';
$category = isset($_GET['category']) ? mysqli_real_escape_string($conn, $_GET['category']) : '';
$sort = isset($_GET['sort']) ? mysqli_real_escape_string($conn, $_GET['sort']) : 'name_asc';
$min_price = isset($_GET['min_price']) ? (float)$_GET['min_price'] : 0;
$max_price = isset($_GET['max_price']) ? (float)$_GET['max_price'] : 10000;

// Build query conditions
$conditions = ["gi.status = 'active'"]; // Specify table name 'gi'
if($search) {
    $conditions[] = "(gi.name LIKE '%$search%' OR gi.description LIKE '%$search%')";
}
if($category) {
    $conditions[] = "gi.category = '$category'";
}
$conditions[] = "gi.price BETWEEN $min_price AND $max_price";
$conditions[] = "gi.stock_quantity > 0";

$where_clause = implode(" AND ", $conditions);

// Sort clause
$sort_clause = match($sort) {
    'price_asc' => 'gi.price ASC',
    'price_desc' => 'gi.price DESC',
    'name_desc' => 'gi.name DESC',
    default => 'gi.name ASC'
};

// Get total records for pagination
$total_query = mysqli_query($conn, "
    SELECT COUNT(*) as count 
    FROM grocery_items gi 
    WHERE $where_clause
");
$total_records = mysqli_fetch_assoc($total_query)['count'];
$total_pages = ceil($total_records / $items_per_page);

// Fetch grocery items with dish information
$items_query = mysqli_query($conn, "
    SELECT 
        gi.*,
        GROUP_CONCAT(DISTINCT d.name) as used_in_dishes
    FROM grocery_items gi
    LEFT JOIN dish_grocery_items dgi ON gi.item_id = dgi.item_id
    LEFT JOIN dishes d ON dgi.dish_id = d.dish_id AND d.status = 'active'
    WHERE $where_clause
    GROUP BY gi.item_id
    ORDER BY $sort_clause
    LIMIT $offset, $items_per_page
");

// Get all categories for filter
$categories_query = mysqli_query($conn, "
    SELECT DISTINCT category 
    FROM grocery_items gi
    WHERE gi.status = 'active'
    ORDER BY category
");

// Get price range
$price_query = mysqli_query($conn, "
    SELECT MIN(price) as min_price, MAX(price) as max_price 
    FROM grocery_items gi
    WHERE gi.status = 'active'
");
$price_range = mysqli_fetch_assoc($price_query);

// Handle Add to Cart
if(isset($_POST['add_to_cart'])) {
    $item_id = (int)$_POST['item_id'];
    $quantity = (int)$_POST['quantity'];
    
    // Validate quantity
    $item_check = mysqli_query($conn, "
        SELECT stock_quantity 
        FROM grocery_items 
        WHERE item_id = $item_id AND status = 'active'
    ");
    
    if($row = mysqli_fetch_assoc($item_check)) {
        if($quantity > 0 && $quantity <= $row['stock_quantity']) {
            // Check if item already in cart
            $cart_check = mysqli_query($conn, "
                SELECT quantity 
                FROM cart 
                WHERE customer_id = ".$_SESSION['customer_id']." 
                AND item_id = $item_id
            ");
            
            if(mysqli_num_rows($cart_check) > 0) {
                // Update existing cart item
                $cart_item = mysqli_fetch_assoc($cart_check);
                $new_quantity = $cart_item['quantity'] + $quantity;
                
                if($new_quantity <= $row['stock_quantity']) {
                    mysqli_query($conn, "
                        UPDATE cart 
                        SET quantity = $new_quantity 
                        WHERE customer_id = ".$_SESSION['customer_id']." 
                        AND item_id = $item_id
                    ");
                    $success_msg = "Cart updated successfully!";
                } else {
                    $error_msg = "Cannot add more of this item. Stock limit reached.";
                }
            } else {
                // Add new cart item
                mysqli_query($conn, "
                    INSERT INTO cart (customer_id, item_id, quantity) 
                    VALUES (".$_SESSION['customer_id'].", $item_id, $quantity)
                ");
                $success_msg = "Item added to cart!";
            }
        } else {
            $error_msg = "Invalid quantity selected.";
        }
    } else {
        $error_msg = "Item not found or not available.";
    }
}

 if(isset($_SESSION['customer_id'])){ 

// Get cart count
$cart_query = mysqli_query($conn, "
    SELECT SUM(quantity) as total_items 
    FROM cart 
    WHERE customer_id = ".$_SESSION['customer_id']
);
$cart_count = mysqli_fetch_assoc($cart_query)['total_items'] ?? 0;

}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <style>
    .items-container {
        background: #f8f9fa;
        min-height: 100vh;
        padding: 30px 0;
    }

    .filter-card {
        background: white;
        border-radius: 15px;
        padding: 1.5rem;
        margin-bottom: 1.5rem;
        border: none;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        position: sticky;
        top: 20px;
    }

    .item-card {
        background: white;
        border-radius: 15px;
        height: 100%;
        transition: transform 0.3s;
        border: none;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    }

    .item-card:hover {
        transform: translateY(-5px);
    }

    .item-image {
        height: 200px;
        object-fit: cover;
        border-radius: 15px 15px 0 0;
    }

    .stock-badge {
        position: absolute;
        top: 10px;
        right: 10px;
        padding: 5px 10px;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: bold;
    }

    .stock-high { background: #d4edda; color: #155724; }
    .stock-medium { background: #fff3cd; color: #856404; }
    .stock-low { background: #f8d7da; color: #721c24; }

    .price-tag {
        font-size: 1.25rem;
        font-weight: bold;
        color: #28a745;
    }

    .used-in-dishes {
        font-size: 0.8rem;
        color: #6c757d;
        margin-top: 0.5rem;
    }

    .timer-badge {
        font-size: 0.875rem;
        padding: 0.5rem 1rem;
        border-radius: 10px;
        background: rgba(0,0,0,0.05);
        display: inline-block;
    }

    .quantity-input {
        width: 80px;
    }

    .cart-badge {
        position: relative;
        top: -8px;
        right: 5px;
        padding: 0.25rem 0.5rem;
        font-size: 0.75rem;
        border-radius: 50%;
        background: #dc3545;
        color: white;
    }

    /* Range Slider Styles */
    .range-slider {
        width: 100%;
        margin: 15px 0;
    }

    .range-slider input[type="range"] {
        width: 100%;
        margin: 10px 0;
    }

    .range-values {
        display: flex;
        justify-content: space-between;
        font-size: 0.9rem;
        color: #6c757d;
    }
	
	
	.search-section {
        background: linear-gradient(45deg, #007bff, #0056b3);
        padding: 3rem 0;
        margin-bottom: 2rem;
        color: white;
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
                    <a class="nav-link " href="index.php">Home</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="dishes.php">Dishes</a>
                </li>
				
				<li class="nav-item">
                    <a class="nav-link active" href="grocery_items.php">Grocery</a>
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
            <h1 class="mb-3">Discover Grocery Here	</h1>
            <p class="lead mb-4">Find recipes and get all ingredients delivered to your door</p>
        </div>

       
    </div>
</section>
<div class="items-container">
    <div class="container">
        <!-- Current Time Display -->
        <div class="text-end mb-4">
            <span class="timer-badge">
                <i class="fas fa-clock me-2"></i>
                <?php echo $current_datetime; ?> IST
            </span>
        </div>

        <?php if($error_msg): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo $error_msg; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if($success_msg): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?php echo $success_msg; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Filters -->
            <div class="col-lg-3">
                <div class="filter-card">
                    <h5 class="mb-3">Filters</h5>
                    <form method="GET" id="filterForm">
                        <div class="mb-3">
                            <label class="form-label">Search</label>
                            <input type="text" class="form-control" name="search" 
                                   value="<?php echo htmlspecialchars($search); ?>" 
                                   placeholder="Search items...">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Category</label>
                            <select class="form-select" name="category">
                                <option value="">All Categories</option>
                                <?php while($cat = mysqli_fetch_assoc($categories_query)): ?>
                                    <option value="<?php echo $cat['category']; ?>" 
                                            <?php echo $category == $cat['category'] ? 'selected' : ''; ?>>
                                        <?php echo ucwords(str_replace('-', ' ', $cat['category'])); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Sort By</label>
                            <select class="form-select" name="sort">
                                <option value="name_asc" <?php echo $sort == 'name_asc' ? 'selected' : ''; ?>>
                                    Name (A-Z)
                                </option>
                                <option value="name_desc" <?php echo $sort == 'name_desc' ? 'selected' : ''; ?>>
                                    Name (Z-A)
                                </option>
                                <option value="price_asc" <?php echo $sort == 'price_asc' ? 'selected' : ''; ?>>
                                    Price (Low to High)
                                </option>
                                <option value="price_desc" <?php echo $sort == 'price_desc' ? 'selected' : ''; ?>>
                                    Price (High to Low)
                                </option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Price Range</label>
                            <div class="range-slider">

                                <input type="range" class="form-range" name="min_price" 
                                       min="<?php echo floor($price_range['min_price']); ?>" 
                                       max="<?php echo ceil($price_range['max_price']); ?>" 
                                       value="<?php echo $min_price; ?>"
                                       oninput="updatePriceRange('min')">
                                <input type="range" class="form-range" name="max_price" 
                                       min="<?php echo floor($price_range['min_price']); ?>" 
                                       max="<?php echo ceil($price_range['max_price']); ?>" 
                                       value="<?php echo $max_price; ?>"
                                       oninput="updatePriceRange('max')">
                                <div class="range-values">
                                    <span>Rs.<span id="minPrice"><?php echo $min_price; ?></span></span>
                                    <span>Rs.<span id="maxPrice"><?php echo $max_price; ?></span></span>
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 mb-2">
                            <i class="fas fa-filter me-2"></i>Apply Filters
                        </button>
                        <button type="button" class="btn btn-outline-secondary w-100" onClick="clearFilters()">
                            <i class="fas fa-times me-2"></i>Clear Filters
                        </button>
                    </form>
                </div>
            </div>

            <!-- Items Grid -->
            <div class="col-lg-9">
                <div class="row g-4">
                    <?php while($item = mysqli_fetch_assoc($items_query)): ?>
                        <div class="col-md-4">
                            <div class="item-card">
                                <div class="position-relative">
                                    <img src="../assets/images/grocery/<?php echo $item['image']; ?>" 
                                         class="card-img-top item-image" 
                                         alt="<?php echo htmlspecialchars($item['name']); ?>">
                                    <?php
                                    $stock_class = match(true) {
                                        $item['stock_quantity'] > 20 => 'stock-high',
                                        $item['stock_quantity'] > 10 => 'stock-medium',
                                        default => 'stock-low'
                                    };
                                    ?>
                                    <span class="stock-badge <?php echo $stock_class; ?>">
                                        <?php echo $item['stock_quantity']; ?> <?php echo $item['unit']; ?> left
                                    </span>
                                </div>
                                <div class="card-body">
                                    <h5 class="card-title"><?php echo htmlspecialchars($item['name']); ?></h5>
                                    <p class="card-text"><?php echo htmlspecialchars($item['description']); ?></p>
                                    <div class="price-tag mb-3">Rs.<?php echo number_format($item['price'], 2); ?>/<?php echo $item['unit']; ?></div>
                                    <?php if($item['used_in_dishes']): ?>
                                        <div class="used-in-dishes">
                                            <i class="fas fa-utensils me-1"></i>
                                            Used in: <?php echo htmlspecialchars($item['used_in_dishes']); ?>
                                        </div>
                                    <?php endif; ?>
                                    <form method="POST" class="mt-3">
                                        <input type="hidden" name="item_id" value="<?php echo $item['item_id']; ?>">
                                        <div class="d-flex align-items-center">
                                            <input type="number" name="quantity" value="1" min="1" 
                                                   max="<?php echo $item['stock_quantity']; ?>" 
                                                   class="form-control form-control-sm quantity-input me-2">
                                           <?php if(isset($_SESSION['customer_id'])): ?>
    <button type="submit" name="add_to_cart" class="btn btn-primary flex-grow-1">
        <i class="fas fa-cart-plus me-1"></i>Add to Cart
    </button>
<?php else: ?>
    <a href="login.php" class="btn btn-outline-primary flex-grow-1">
        <i class="fas fa-sign-in-alt me-1"></i>Login to Buy
    </a>
<?php endif; ?>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>

                <!-- Pagination -->
                <?php if($total_pages > 1): ?>
                    <nav aria-label="Page navigation" class="mt-4">
                        <ul class="pagination justify-content-center">
                            <?php for($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category); ?>&sort=<?php echo urlencode($sort); ?>&min_price=<?php echo $min_price; ?>&max_price=<?php echo $max_price; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function updatePriceRange(type) {
    const minInput = document.querySelector('input[name="min_price"]');
    const maxInput = document.querySelector('input[name="max_price"]');
    const minDisplay = document.getElementById('minPrice');
    const maxDisplay = document.getElementById('maxPrice');
    
    let min = parseInt(minInput.value);
    let max = parseInt(maxInput.value);
    
    if(type === 'min' && min > max) {
        max = min;
        maxInput.value = max;
    } else if(type === 'max' && max < min) {
        min = max;
        minInput.value = min;
    }
    
    minDisplay.textContent = min;
    maxDisplay.textContent = max;
}

function clearFilters() {
    window.location.href = 'grocery_items.php';
}

// Auto-hide alerts
setTimeout(function() {
    $('.alert').alert('close');
}, 5000);
</script>

<?php include("../includes/footer.php"); ?>