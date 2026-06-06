<?php
include("../includes/header.php");

// Set timezone
date_default_timezone_set('Asia/Kolkata');
$utc_datetime = '2025-04-25 09:11:34';
$current_datetime = date('Y-m-d H:i:s', strtotime($utc_datetime) + (5.5 * 3600)); // Converting UTC to IST
$current_user = isset($_SESSION['customer_name']) ? $_SESSION['customer_name'] : 'testt453';

// Get dish ID
$dish_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if(!$dish_id) {
    header("Location: dishes.php");
    exit();
}

// Initialize variables
$error_msg = $success_msg = "";

// Fetch dish details with recommended ingredients
$dish_query = mysqli_query($conn, "
    SELECT 
        d.*,
        GROUP_CONCAT(
            CONCAT(
                gi.item_id, '::',
                gi.name, '::',
                gi.price, '::',
                gi.unit, '::',
                gi.stock_quantity, '::',
                gi.image
            ) SEPARATOR '||'
        ) as recommended_items
    FROM dishes d
    LEFT JOIN dish_grocery_items dgi ON d.dish_id = dgi.dish_id
    LEFT JOIN grocery_items gi ON dgi.item_id = gi.item_id
    WHERE d.dish_id = $dish_id AND d.status = 'active'
    GROUP BY d.dish_id
");

if(mysqli_num_rows($dish_query) == 0) {
    header("Location: dishes.php");
    exit();
}

$dish = mysqli_fetch_assoc($dish_query);

// Parse recommended items
$recommended_items = [];
if($dish['recommended_items']) {
    foreach(explode('||', $dish['recommended_items']) as $item_str) {
        list($id, $name, $price, $unit, $stock, $image) = explode('::', $item_str);
        $recommended_items[$id] = [
            'item_id' => $id,
            'name' => $name,
            'price' => $price,
            'unit' => $unit,
            'stock_quantity' => $stock,
            'image' => $image,
            'is_recommended' => true
        ];
    }
}

// Fetch all other grocery items (non-recommended)
$other_items_query = mysqli_query($conn, "
    SELECT item_id, name, price, unit, stock_quantity, image
    FROM grocery_items
    WHERE status = 'active'
    AND item_id NOT IN (" . implode(',', array_keys($recommended_items)) . ")
    ORDER BY category, name
");

$other_items = [];
while($item = mysqli_fetch_assoc($other_items_query)) {
    $other_items[$item['item_id']] = array_merge($item, ['is_recommended' => false]);
}

// Handle Add to Cart
if(isset($_POST['add_to_cart'])) {
    $selected_items = isset($_POST['items']) ? $_POST['items'] : [];
    $quantities = isset($_POST['quantity']) ? $_POST['quantity'] : [];
    
    if(empty($selected_items)) {
        $error_msg = "Please select at least one item";
    } else {
        try {
            mysqli_begin_transaction($conn);
            
            foreach($selected_items as $item_id) {
                $quantity = isset($quantities[$item_id]) ? (int)$quantities[$item_id] : 0;
                if($quantity > 0) {
                    // Check stock availability
                    $stock_check = mysqli_query($conn, "
                        SELECT stock_quantity 
                        FROM grocery_items 
                        WHERE item_id = $item_id AND status = 'active'
                    ");
                    $available_stock = mysqli_fetch_assoc($stock_check)['stock_quantity'];
                    
                    if($quantity <= $available_stock) {
                        if(isset($_SESSION['customer_id'])) {
                            // Add to database cart
                            mysqli_query($conn, "
                                INSERT INTO cart (customer_id, item_id, quantity, created_at)
                                VALUES (".$_SESSION['customer_id'].", $item_id, $quantity, '$current_datetime')
                                ON DUPLICATE KEY UPDATE quantity = quantity + $quantity
                            ");
                        } else {
                            // Add to session cart
                            if(!isset($_SESSION['temp_cart'])) {
                                $_SESSION['temp_cart'] = [];
                            }
                            if(isset($_SESSION['temp_cart'][$item_id])) {
                                $_SESSION['temp_cart'][$item_id] += $quantity;
                            } else {
                                $_SESSION['temp_cart'][$item_id] = $quantity;
                            }
                        }
                    } else {
                        throw new Exception("Not enough stock for some items");
                    }
                }
            }
            
            mysqli_commit($conn);
            $success_msg = "Items added to cart successfully!";
            
            // Redirect to cart if requested
            if(isset($_POST['proceed_to_cart'])) {
                header("Location: cart.php");
                exit();
            }
        } catch(Exception $e) {
            mysqli_rollback($conn);
            $error_msg = $e->getMessage();
        }
    }
}

// Get cart count
$cart_count = 0;
if(isset($_SESSION['customer_id'])) {
    $cart_query = mysqli_query($conn, "
        SELECT COUNT(*) as count 
        FROM cart 
        WHERE customer_id = ".$_SESSION['customer_id']
    );
    $cart_count = mysqli_fetch_assoc($cart_query)['count'];
} elseif(isset($_SESSION['temp_cart'])) {
    $cart_count = count($_SESSION['temp_cart']);
}
?>



<!DOCTYPE html>
<html lang="en">
<head>
    <style>
    .dish-container {
        background: #f8f9fa;
        min-height: 100vh;
        padding: 30px 0;
    }

    .dish-header {
        background: linear-gradient(45deg, #007bff, #0056b3);
        color: white;
        padding: 3rem 0;
        margin-bottom: 2rem;
    }

    .dish-image {
        width: 100%;
        height: 400px;
        object-fit: cover;
        border-radius: 15px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    }

    .item-card {
        border: none;
        border-radius: 15px;
        transition: transform 0.3s;
        height: 100%;
    }

    .item-card:hover {
        transform: translateY(-5px);
    }

    .item-card.selected {
        border: 2px solid #007bff;
    }

    .item-card img {
        height: 150px;
        object-fit: cover;
        border-radius: 15px 15px 0 0;
    }

    .quantity-control {
        width: 120px;
        margin: 0 auto;
    }

    .quantity-control .btn {
        padding: 0.25rem 0.5rem;
    }

    .section-title {
        position: relative;
        padding-bottom: 10px;
        margin-bottom: 20px;
    }

    .section-title::after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 0;
        width: 50px;
        height: 3px;
        background: #007bff;
    }

    .recommended-badge {
        position: absolute;
        top: 10px;
        right: 10px;
        z-index: 1;
    }

    .timer-badge {
        font-size: 0.875rem;
        padding: 0.5rem 1rem;
        border-radius: 10px;
        background: rgba(255,255,255,0.1);
        display: inline-block;
    }

    .floating-cart {
        position: fixed;
        bottom: 20px;
        right: 20px;
        z-index: 1000;
    }

    .item-price {
        font-size: 1.25rem;
        font-weight: bold;
        color: #28a745;
    }

    .stock-status {
        font-size: 0.875rem;
    }

    .in-stock { color: #28a745; }
    .low-stock { color: #ffc107; }
    .out-of-stock { color: #dc3545; }
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

<!-- Dish Header -->
<header class="dish-header">
    <div class="container">
        <div class="text-center">
            <span class="timer-badge mb-3">
                <i class="fas fa-clock me-2"></i>
                Current time: <?php echo $current_datetime; ?> IST
            </span>
            <h1 class="display-4 mb-3"><?php echo htmlspecialchars($dish['name']); ?></h1>
            <p class="lead mb-0"><?php echo htmlspecialchars($dish['description']); ?></p>
        </div>
    </div>
</header>

<div class="dish-container">
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
            <!-- Dish Image -->
            <div class="col-md-6 mb-4">
                <img src="../assets/images/dishes/<?php echo $dish['image']; ?>" 
                     alt="<?php echo htmlspecialchars($dish['name']); ?>" 
                     class="dish-image">
            </div>

            <!-- Dish Info -->
            <div class="col-md-6 mb-4">
                <h2 class="section-title">Recipe Details</h2>
                <p><?php echo nl2br(htmlspecialchars($dish['description'])); ?></p>
                
                <hr>
                
                <h3 class="section-title">Required Ingredients</h3>
                <form method="POST" action="" id="ingredientForm">
                    <!-- Recommended Items -->
                    <div class="mb-4">
                        <h4 class="mb-3">Recommended Items:</h4>
                        <div class="row g-3">
                            <?php foreach($recommended_items as $item): ?>
                                <div class="col-md-6">
                                    <div class="card item-card">
                                        <span class="badge bg-primary recommended-badge">Recommended</span>
                                        <img src="../assets/images/grocery/<?php echo $item['image']; ?>" 
                                             class="card-img-top" 
                                             alt="<?php echo htmlspecialchars($item['name']); ?>">
                                        <div class="card-body">
                                            <h5 class="card-title"><?php echo htmlspecialchars($item['name']); ?></h5>
                                            <p class="item-price mb-2">Rs.<?php echo number_format($item['price'], 2); ?> per <?php echo $item['unit']; ?></p>
                                            
                                            <div class="stock-status mb-3">
                                                <?php if($item['stock_quantity'] > 10): ?>
                                                    <i class="fas fa-check-circle in-stock"></i> In Stock
                                                <?php elseif($item['stock_quantity'] > 0): ?>
                                                    <i class="fas fa-exclamation-circle low-stock"></i> Low Stock (<?php echo $item['stock_quantity']; ?> left)
                                                <?php else: ?>
                                                    <i class="fas fa-times-circle out-of-stock"></i> Out of Stock
                                                <?php endif; ?>
                                            </div>

                                            <div class="form-check mb-2">
                                                <input class="form-check-input" type="checkbox" 
                                                       name="items[]" value="<?php echo $item['item_id']; ?>"
                                                       id="item<?php echo $item['item_id']; ?>"
                                                       <?php echo $item['stock_quantity'] == 0 ? 'disabled' : ''; ?>>
                                                <label class="form-check-label" for="item<?php echo $item['item_id']; ?>">
                                                    Select this item
                                                </label>
                                            </div>

                                            <div class="quantity-control input-group">
											
						
											
											
                                               					<?php if(isset($_SESSION['customer_id'])): ?>
   <button type="button" class="btn btn-outline-secondary" onClick="updateQuantity(<?php echo $item['item_id']; ?>, -1)">-</button>
                                                <input type="number" class="form-control text-center" 
                                                       name="quantity[<?php echo $item['item_id']; ?>]" 
                                                       value="1" min="1" max="<?php echo $item['stock_quantity']; ?>"
                                                       id="quantity<?php echo $item['item_id']; ?>">
                                                <button type="button" class="btn btn-outline-secondary" onClick="updateQuantity(<?php echo $item['item_id']; ?>, 1)">+</button>
<?php else: ?>
    <a href="login.php" class="btn btn-outline-primary flex-grow-1">
        <i class="fas fa-sign-in-alt me-1"></i>Login to Buy
    </a>
<?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Other Available Items -->
                    <div class="mb-4">
                        <h4 class="mb-3">Additional Items:</h4>
                        <div class="row g-3">
                            <?php foreach($other_items as $item): ?>
                                <div class="col-md-6">
                                    <div class="card item-card">
                                        <img src="../assets/images/grocery/<?php echo $item['image']; ?>" 
                                             class="card-img-top" 
                                             alt="<?php echo htmlspecialchars($item['name']); ?>">
                                        <div class="card-body">
                                            <h5 class="card-title"><?php echo htmlspecialchars($item['name']); ?></h5>
                                            <p class="item-price mb-2">Rs.<?php echo number_format($item['price'], 2); ?> per <?php echo $item['unit']; ?></p>
                                            
                                            <div class="stock-status mb-3">
                                                <?php if($item['stock_quantity'] > 10): ?>
                                                    <i class="fas fa-check-circle in-stock"></i> In Stock
                                                <?php elseif($item['stock_quantity'] > 0): ?>
                                                    <i class="fas fa-exclamation-circle low-stock"></i> Low Stock (<?php echo $item['stock_quantity']; ?> left)
                                                <?php else: ?>
                                                    <i class="fas fa-times-circle out-of-stock"></i> Out of Stock
                                                <?php endif; ?>
                                            </div>

                                            <div class="form-check mb-2">
                                                <input class="form-check-input" type="checkbox" 
                                                       name="items[]" value="<?php echo $item['item_id']; ?>"
                                                       id="item<?php echo $item['item_id']; ?>"
                                                       <?php echo $item['stock_quantity'] == 0 ? 'disabled' : ''; ?>>
                                                <label class="form-check-label" for="item<?php echo $item['item_id']; ?>">
                                                    Select this item
                                                </label>
                                            </div>

                                            <div class="quantity-control input-group">
                                                <button type="button" class="btn btn-outline-secondary" onClick="updateQuantity(<?php echo $item['item_id']; ?>, -1)">-</button>
                                                <input type="number" class="form-control text-center" 
                                                       name="quantity[<?php echo $item['item_id']; ?>]" 
                                                       value="1" min="1" max="<?php echo $item['stock_quantity']; ?>"
                                                       id="quantity<?php echo $item['item_id']; ?>">
                                                <button type="button" class="btn btn-outline-secondary" onClick="updateQuantity(<?php echo $item['item_id']; ?>, 1)">+</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="floating-cart">
					
					
										<?php if(isset($_SESSION['customer_id'])){ ?>

					
                        <button type="submit" name="add_to_cart" class="btn btn-primary btn-lg me-2">
                            <i class="fas fa-cart-plus me-2"></i>Add to Cart
                        </button>
						
						<?php }  ?>
                       
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Quantity control function
function updateQuantity(itemId, change) {
    const input = document.getElementById('quantity' + itemId);
    let newValue = parseInt(input.value) + change;
    
    // Ensure value is within min/max bounds
    newValue = Math.max(1, Math.min(newValue, parseInt(input.max)));
    
    input.value = newValue;
}

// Enable/disable quantity input based on checkbox
document.querySelectorAll('input[type="checkbox"]').forEach(checkbox => {
    checkbox.addEventListener('change', function() {
        const itemId = this.value;
        const quantityInput = document.getElementById('quantity' + itemId);
        const quantityButtons = quantityInput.parentElement.querySelectorAll('button');
        
        quantityInput.disabled = !this.checked;
        quantityButtons.forEach(button => button.disabled = !this.checked);
    });
});

// Form validation
document.getElementById('ingredientForm').addEventListener('submit', function(e) {
    const checkedBoxes = document.querySelectorAll('input[name="items[]"]:checked');
    if(checkedBoxes.length === 0) {
        e.preventDefault();
        alert('Please select at least one item');
    }
});

// Auto-hide alerts
setTimeout(function() {
    $('.alert').alert('close');
}, 5000);
</script>

<?php include("../includes/footer.php"); ?>