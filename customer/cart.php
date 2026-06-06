<?php
include("../includes/header.php");

// Set timezone
date_default_timezone_set('Asia/Kolkata');
$utc_datetime = '2025-04-25 09:15:39';
$current_datetime = date('Y-m-d H:i:s', strtotime($utc_datetime) + (5.5 * 3600)); // Converting UTC to IST
$current_user = isset($_SESSION['customer_name']) ? $_SESSION['customer_name'] : 'testt453';

// Initialize variables
$error_msg = $success_msg = "";
$cart_items = [];
$subtotal = 0;
$delivery_charge = 50.00; // Default delivery charge

// Handle quantity updates
if(isset($_POST['update_quantity'])) {
    $item_id = (int)$_POST['item_id'];
    $quantity = (int)$_POST['quantity'];
    
    try {
        // Check stock availability
        $stock_check = mysqli_query($conn, "
            SELECT stock_quantity 
            FROM grocery_items 
            WHERE item_id = $item_id AND status = 'active'
        ");
        $available_stock = mysqli_fetch_assoc($stock_check)['stock_quantity'];
        
        if($quantity <= $available_stock) {
            if(isset($_SESSION['customer_id'])) {
                // Update database cart
                if($quantity > 0) {
                    mysqli_query($conn, "
                        UPDATE cart 
                        SET quantity = $quantity 
                        WHERE customer_id = ".$_SESSION['customer_id']." 
                        AND item_id = $item_id
                    ");
                } else {
                    mysqli_query($conn, "
                        DELETE FROM cart 
                        WHERE customer_id = ".$_SESSION['customer_id']." 
                        AND item_id = $item_id
                    ");
                }
            } else {
                // Update session cart
                if($quantity > 0) {
                    $_SESSION['temp_cart'][$item_id] = $quantity;
                } else {
                    unset($_SESSION['temp_cart'][$item_id]);
                }
            }
            $success_msg = "Cart updated successfully";
        } else {
            $error_msg = "Not enough stock available";
        }
    } catch(Exception $e) {
        $error_msg = "Failed to update cart";
    }
}

// Handle item removal
if(isset($_POST['remove_item'])) {
    $item_id = (int)$_POST['item_id'];
    
    try {
        if(isset($_SESSION['customer_id'])) {
            mysqli_query($conn, "
                DELETE FROM cart 
                WHERE customer_id = ".$_SESSION['customer_id']." 
                AND item_id = $item_id
            ");
        } else {
            unset($_SESSION['temp_cart'][$item_id]);
        }
        $success_msg = "Item removed from cart";
    } catch(Exception $e) {
        $error_msg = "Failed to remove item";
    }
}

// Clear entire cart
if(isset($_POST['clear_cart'])) {
    try {
        if(isset($_SESSION['customer_id'])) {
            mysqli_query($conn, "
                DELETE FROM cart 
                WHERE customer_id = ".$_SESSION['customer_id']
            );
        } else {
            unset($_SESSION['temp_cart']);
        }
        $success_msg = "Cart cleared successfully";
    } catch(Exception $e) {
        $error_msg = "Failed to clear cart";
    }
}

// Fetch cart items
if(isset($_SESSION['customer_id'])) {
    // Get items from database cart
    $cart_query = mysqli_query($conn, "
        SELECT 
            c.*,
            gi.name,
            gi.price,
            gi.unit,
            gi.stock_quantity,
            gi.image,
            (c.quantity * gi.price) as subtotal
        FROM cart c
        JOIN grocery_items gi ON c.item_id = gi.item_id
        WHERE c.customer_id = ".$_SESSION['customer_id']."
        ORDER BY c.created_at DESC
    ");
    
    while($item = mysqli_fetch_assoc($cart_query)) {
        $cart_items[] = $item;
        $subtotal += $item['subtotal'];
    }
} elseif(isset($_SESSION['temp_cart']) && !empty($_SESSION['temp_cart'])) {
    // Get items from session cart
    $item_ids = implode(',', array_keys($_SESSION['temp_cart']));
    $items_query = mysqli_query($conn, "
        SELECT *
        FROM grocery_items
        WHERE item_id IN ($item_ids)
    ");
    
    while($item = mysqli_fetch_assoc($items_query)) {
        $quantity = $_SESSION['temp_cart'][$item['item_id']];
        $item['quantity'] = $quantity;
        $item['subtotal'] = $quantity * $item['price'];
        $cart_items[] = $item;
        $subtotal += $item['subtotal'];
    }
}

// Calculate total
$total = $subtotal + $delivery_charge;

// Process checkout
if(isset($_POST['checkout']) && !empty($cart_items)) {
    if(!isset($_SESSION['customer_id'])) {
        $_SESSION['redirect_after_login'] = 'cart.php';
        header("Location: login.php");
        exit();
    }
    
    // Validate stock again before proceeding
    $stock_valid = true;
    foreach($cart_items as $item) {
        if($item['quantity'] > $item['stock_quantity']) {
            $stock_valid = false;
            $error_msg = "Some items are out of stock";
            break;
        }
    }
    
    if($stock_valid) {
        // Redirect to checkout page
        header("Location: checkout.php");
        exit();
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <style>
    .cart-container {
        background: #f8f9fa;
        min-height: 100vh;
        padding: 30px 0;
    }

    .cart-header {
        background: linear-gradient(45deg, #007bff, #0056b3);
        color: white;
        padding: 3rem 0;
        margin-bottom: 2rem;
    }

    .cart-item {
        background: white;
        border-radius: 15px;
        margin-bottom: 1rem;
        transition: transform 0.3s;
        border: none;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    }

    .cart-item:hover {
        transform: translateY(-2px);
    }

    .cart-item img {
        width: 100px;
        height: 100px;
        object-fit: cover;
        border-radius: 10px;
    }

    .quantity-control {
        width: 120px;
    }

    .quantity-control .btn {
        padding: 0.25rem 0.5rem;
    }

    .summary-card {
        background: white;
        border-radius: 15px;
        padding: 1.5rem;
        position: sticky;
        top: 20px;
        border: none;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    }

    .price-detail {
        display: flex;
        justify-content: space-between;
        margin-bottom: 0.5rem;
    }

    .total-price {
        font-size: 1.5rem;
        font-weight: bold;
        color: #007bff;
    }

    .timer-badge {
        font-size: 0.875rem;
        padding: 0.5rem 1rem;
        border-radius: 10px;
        background: rgba(255,255,255,0.1);
        display: inline-block;
    }

    .empty-cart {
        text-align: center;
        padding: 3rem 0;
    }

    .empty-cart i {
        font-size: 4rem;
        color: #dee2e6;
        margin-bottom: 1rem;
    }

    .stock-warning {
        color: #dc3545;
        font-size: 0.875rem;
    }

    .item-price {
        font-weight: bold;
        color: #28a745;
    }
    </style>
</head>
<body>

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
<!-- Cart Header -->
<header class="cart-header">
    <div class="container text-center">
        <span class="timer-badge mb-3">
            <i class="fas fa-clock me-2"></i>
            Current time: <?php echo $current_datetime; ?> IST
        </span>
        <h1 class="display-4">Shopping Cart</h1>
        <p class="lead mb-0">Review your items and proceed to checkout</p>
    </div>
</header>

<div class="cart-container">
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

        <?php if(!empty($cart_items)): ?>
            <div class="row">
                <!-- Cart Items -->
                <div class="col-lg-8">
                    <?php foreach($cart_items as $item): ?>
                        <div class="cart-item p-3">
                            <div class="row align-items-center">
                                <div class="col-auto">
                                    <img src="../assets/images/grocery/<?php echo $item['image']; ?>" 
                                         alt="<?php echo htmlspecialchars($item['name']); ?>">
                                </div>
                                <div class="col">
                                    <h5 class="mb-1"><?php echo htmlspecialchars($item['name']); ?></h5>
                                    <p class="mb-1 item-price">Rs.<?php echo number_format($item['price'], 2); ?> per <?php echo $item['unit']; ?></p>
                                    <?php if($item['quantity'] > $item['stock_quantity']): ?>
                                        <p class="stock-warning mb-0">
                                            <i class="fas fa-exclamation-triangle me-1"></i>
                                            Only <?php echo $item['stock_quantity']; ?> units available
                                        </p>
                                    <?php endif; ?>
                                </div>
                                <div class="col-auto">
                                    <form method="POST" class="d-inline-block">
                                        <input type="hidden" name="item_id" value="<?php echo $item['item_id']; ?>">
                                        <div class="quantity-control input-group mb-2">
                                            <button type="button" class="btn btn-outline-secondary" 
                                                    onclick="updateQuantity(this.form, -1)">-</button>
                                            <input type="number" class="form-control text-center" name="quantity" 
                                                   value="<?php echo $item['quantity']; ?>" 
                                                   min="0" max="<?php echo $item['stock_quantity']; ?>">
                                            <button type="button" class="btn btn-outline-secondary" 
                                                    onclick="updateQuantity(this.form, 1)">+</button>
                                        </div>
                                        <button type="submit" name="update_quantity" class="btn btn-sm btn-primary">
                                            Update
                                        </button>
                                        <button type="submit" name="remove_item" class="btn btn-sm btn-danger">
                                            Remove
                                        </button>
                                    </form>
                                </div>
                                <div class="col-auto text-end">
                                    <h5 class="mb-0">Rs.<?php echo number_format($item['subtotal'], 2); ?></h5>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <div class="text-end mt-3">
                        <form method="POST" class="d-inline-block">
                            <button type="submit" name="clear_cart" class="btn btn-outline-danger" 
                                    onclick="return confirm('Are you sure you want to clear your cart?')">
                                <i class="fas fa-trash-alt me-2"></i>Clear Cart
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Order Summary -->
                <div class="col-lg-4">
                    <div class="summary-card">
                        <h3 class="mb-4">Order Summary</h3>
                        <div class="price-detail">
                            <span>Subtotal</span>
                            <span>Rs.<?php echo number_format($subtotal, 2); ?></span>
                        </div>
                        <div class="price-detail">
                            <span>Delivery Charge</span>
                            <span>Rs.<?php echo number_format($delivery_charge, 2); ?></span>
                        </div>
                        <hr>
                        <div class="price-detail total-price">
                            <span>Total</span>
                            <span>Rs.<?php echo number_format($total, 2); ?></span>
                        </div>
                        <form method="POST" class="mt-4">
                            <button type="submit" name="checkout" class="btn btn-primary btn-lg w-100">
                                <i class="fas fa-shopping-cart me-2"></i>Proceed to Checkout
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="empty-cart">
                <i class="fas fa-shopping-cart mb-3"></i>
                <h3>Your cart is empty</h3>
                <p class="text-muted">Browse our items and add something to your cart</p>
                <a href="dishes.php" class="btn btn-primary">
                    <i class="fas fa-utensils me-2"></i>Browse Dishes
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Quantity control function
function updateQuantity(form, change) {
    const input = form.querySelector('input[name="quantity"]');
    let newValue = parseInt(input.value) + change;
    
    // Ensure value is within min/max bounds
    newValue = Math.max(0, Math.min(newValue, parseInt(input.max)));
    
    input.value = newValue;
}

// Auto-hide alerts
setTimeout(function() {
    $('.alert').alert('close');
}, 5000);

// Update subtotal on quantity change
document.querySelectorAll('input[name="quantity"]').forEach(input => {
    input.addEventListener('change', function() {
        this.form.querySelector('button[name="update_quantity"]').click();
    });
});
</script>

<?php include("../includes/footer.php"); ?>