







<?php
include("../includes/header.php");

// Check if customer is logged in
if(!isset($_SESSION['customer_id'])) {
    header("Location: login.php");
    exit();
}

// Set timezone
date_default_timezone_set('Asia/Kolkata');

 $current_datetime = date('Y-m-d H:i:s');
$customer_id = $_SESSION['customer_id'];
$current_user = isset($_SESSION['customer_name']) ? $_SESSION['customer_name'] : 'testt453';	
// Initialize variables
$error_msg = $success_msg = "";
$cart_items = [];
$subtotal = 0;
$delivery_charge = 50.00; // Fixed delivery charge
$total = 0;

// Fetch customer details
$customer_query = mysqli_query($conn, "
    SELECT * FROM customers 
    WHERE customer_id = $customer_id
");
$customer = mysqli_fetch_assoc($customer_query);

// Fetch cart items
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
    WHERE c.customer_id = $customer_id
");

// Calculate totals and check stock
$stock_valid = true;
while($item = mysqli_fetch_assoc($cart_query)) {
    if($item['quantity'] > $item['stock_quantity']) {
        $stock_valid = false;
        $error_msg = "Some items are out of stock. Please review your cart.";
        break;
    }
    $cart_items[] = $item;
    $subtotal += $item['subtotal'];
}

$total = $subtotal + $delivery_charge;

// If cart is empty, redirect to cart page
if(empty($cart_items)) {
    header("Location: cart.php");
    exit();
}

// Handle order placement
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['place_order'])) {
    // Get shipping address based on selection
	
	
	
	date_default_timezone_set('Asia/Kolkata');

 $current_datetime = date('Y-m-d H:i:s');
    $address_type = $_POST['address_type'] ?? 'default';
    $shipping_address = '';
    
	
	
	
    if($address_type == 'default') {
        $shipping_address = $customer['address'];
    } else {
        $shipping_address = mysqli_real_escape_string($conn, trim($_POST['shipping_address']));
    }
    
    if(empty($shipping_address)) {
        $error_msg = "Please provide a shipping address";
    } elseif(!$stock_valid) {
        $error_msg = "Some items are out of stock. Please review your cart.";
    } else {
        try {
            mysqli_begin_transaction($conn);
            
            // Create order with current UTC time
            $order_insert = mysqli_query($conn, "
                INSERT INTO orders (
                    customer_id,
                    total_amount,
                    delivery_charge,
                    order_status,
                    delivery_status,
                    order_datetime,
                    shipping_address
                ) VALUES (
                    $customer_id,
                    $total,
                    $delivery_charge,
                    'pending',
                    'on_time',
                    '$current_datetime',
                    '$shipping_address'
                )
            ");
            
            if($order_insert) {
                $order_id = mysqli_insert_id($conn);
                
                // Insert order items and update stock
                foreach($cart_items as $item) {
                    // Insert order item
                    $item_insert = mysqli_query($conn, "
                        INSERT INTO order_items (
                            order_id,
                            item_id,
                            quantity,
                            price_per_unit
                        ) VALUES (
                            $order_id,
                            ".$item['item_id'].",
                            ".$item['quantity'].",
                            ".$item['price']."
                        )
                    ");
                    
                    // Update stock
                    $stock_update = mysqli_query($conn, "
                        UPDATE grocery_items 
                        SET stock_quantity = stock_quantity - ".$item['quantity']."
                        WHERE item_id = ".$item['item_id']
                    );
                    
                    if(!$item_insert || !$stock_update) {
                        throw new Exception("Failed to process order items");
                    }
                }
                
                // Clear cart
                mysqli_query($conn, "
                    DELETE FROM cart 
                    WHERE customer_id = $customer_id
                ");
                
                mysqli_commit($conn);
                
                // Redirect to order success page
                header("Location: order_success.php?order_id=".$order_id);
                exit();
                
            } else {
                throw new Exception("Failed to create order");
            }
            
        } catch(Exception $e) {
            mysqli_rollback($conn);
            $error_msg = $e->getMessage();
        }
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <style>
    .checkout-container {
        background: #f8f9fa;
        min-height: 100vh;
        padding: 30px 0;
    }

    .checkout-header {
        background: linear-gradient(45deg, #007bff, #0056b3);
        color: white;
        padding: 3rem 0;
        margin-bottom: 2rem;
    }

    .checkout-step {
        background: white;
        border-radius: 15px;
        padding: 2rem;
        margin-bottom: 1.5rem;
        border: none;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    }

    .step-number {
        width: 30px;
        height: 30px;
        background: #007bff;
        color: white;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        margin-right: 1rem;
    }

    .order-summary {
        background: white;
        border-radius: 15px;
        padding: 1.5rem;
        position: sticky;
        top: 20px;
        border: none;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    }

    .cart-item {
        display: flex;
        align-items: center;
        margin-bottom: 1rem;
        padding-bottom: 1rem;
        border-bottom: 1px solid #dee2e6;
    }

    .cart-item:last-child {
        border-bottom: none;
        padding-bottom: 0;
        margin-bottom: 0;
    }

    .cart-item img {
        width: 60px;
        height: 60px;
        object-fit: cover;
        border-radius: 8px;
        margin-right: 1rem;
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

    .address-card {
        border: 2px solid #dee2e6;
        border-radius: 10px;
        padding: 1rem;
        margin-bottom: 1rem;
        cursor: pointer;
        transition: all 0.3s;
    }

    .address-card:hover {
        border-color: #007bff;
        background: #f8f9fa;
    }

    .address-card .form-check-input:checked ~ .form-check-label {
        color: #007bff;
    }

    .address-card.selected {
        border-color: #007bff;
        background: #f8f9fa;
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

<!-- Checkout Header -->
<header class="checkout-header">
    <div class="container text-center">
        <span class="timer-badge mb-3">
            <i class="fas fa-clock me-2"></i>
            Current time: <?php echo $current_datetime; ?> IST
        </span>
        <h1 class="display-4">Checkout</h1>
        <p class="lead mb-0">Complete your order</p>
    </div>
</header>

<div class="checkout-container">
    <div class="container">
        <?php if($error_msg): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?php echo $error_msg; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <form method="POST" id="checkoutForm">
            <div class="row">
                <!-- Checkout Steps -->
                <div class="col-lg-8">
                    <!-- Review Items -->
                    <div class="checkout-step">
                        <h3 class="mb-4">
                            <span class="step-number">1</span>
                            Review Your Order
                        </h3>
                        <?php foreach($cart_items as $item): ?>
                            <div class="cart-item">
                                <img src="../assets/images/grocery/<?php echo $item['image']; ?>" 
                                     alt="<?php echo htmlspecialchars($item['name']); ?>">
                                <div class="flex-grow-1">
                                    <h5 class="mb-1"><?php echo htmlspecialchars($item['name']); ?></h5>
                                    <p class="mb-0">
                                        <span class="item-price">Rs.<?php echo number_format($item['price'], 2); ?></span>
                                        x <?php echo $item['quantity']; ?> <?php echo $item['unit']; ?>
                                    </p>
                                </div>
                                <div class="ms-3">
                                    <h5 class="mb-0">Rs.<?php echo number_format($item['subtotal'], 2); ?></h5>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Delivery Address -->
                    <div class="checkout-step">
                        <h3 class="mb-4">
                            <span class="step-number">2</span>
                            Delivery Address
                        </h3>
                        
                        <!-- Default Address -->
                        <div class="address-card selected mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="address_type" 
                                       id="defaultAddress" value="default" checked>
                                <label class="form-check-label" for="defaultAddress">
                                    <strong>Default Address</strong>
                                    <p class="mb-0"><?php echo nl2br(htmlspecialchars($customer['address'])); ?></p>
                                </label>
                            </div>
                        </div>

                        <!-- New Address -->
                        <div class="address-card">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="address_type" 
                                       id="newAddress" value="new">
                                <label class="form-check-label" for="newAddress">
                                    <strong>Use Different Address</strong>
                                </label>
                            </div>
                            <div class="mt-3" id="newAddressForm" style="display: none;">
                                <textarea class="form-control" name="shipping_address" rows="3" 
                                          placeholder="Enter complete delivery address"></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Information -->
                    <div class="checkout-step">
                        <h3 class="mb-4">
                            <span class="step-number">3</span>
                            Payment Method
                        </h3>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="radio" name="payment_method" 
                                   id="cod" value="cod" checked>
                            <label class="form-check-label" for="cod">
                                Cash on Delivery
                            </label>
                        </div>
                        <small class="text-muted">
                            <i class="fas fa-info-circle me-1"></i>
                            Currently, we only support Cash on Delivery
                        </small>
                    </div>
                </div>

                <!-- Order Summary -->
                <div class="col-lg-4">
                    <div class="order-summary">
                        <h3 class="mb-4">Order Summary</h3>
                        
                        <div class="price-detail">
                            <span>Items Total</span>
                            <span>Rs.<?php echo number_format($subtotal, 2); ?></span>
                        </div>
                        <div class="price-detail">
                            <span>Delivery Charge</span>
                            <span>Rs.<?php echo number_format($delivery_charge, 2); ?></span>
							
                            
                        </div>
						<small class="text-muted d-block">
                                <i class="fas fa-info-circle me-1"></i>
                                Free if delivery exceeds 20 minutes
                            </small>
                        <hr>
                        <div class="price-detail total-price">
                            <span>Total</span>
                            <span>Rs.<?php echo number_format($total, 2); ?></span>
                        </div>
                        
                        <button type="submit" name="place_order" class="btn btn-primary btn-lg w-100 mt-4">
                            <i class="fas fa-shopping-bag me-2"></i>Place Order
                        </button>
                        
                        <div class="text-center mt-3">
                            <a href="cart.php" class="text-decoration-none">
                                <i class="fas fa-arrow-left me-1"></i>
                                Return to Cart
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
// Handle address selection
document.querySelectorAll('input[name="address_type"]').forEach(radio => {
    radio.addEventListener('change', function() {
        const newAddressForm = document.getElementById('newAddressForm');
        const textarea = newAddressForm.querySelector('textarea');
        
        if(this.value === 'new') {
            newAddressForm.style.display = 'block';
            textarea.setAttribute('required', 'required');
        } else {
            newAddressForm.style.display = 'none';
            textarea.removeAttribute('required');
            textarea.value = ''; // Clear the textarea
        }
        
        // Update card selection
        document.querySelectorAll('.address-card').forEach(card => {
            card.classList.remove('selected');
        });
        this.closest('.address-card').classList.add('selected');
    });
});

// Allow clicking anywhere on the address card to select it
document.querySelectorAll('.address-card').forEach(card => {
    card.addEventListener('click', function(e) {
        if (!e.target.classList.contains('form-control')) {  // Don't trigger when clicking on textarea
            const radio = this.querySelector('input[type="radio"]');
            radio.checked = true;
            radio.dispatchEvent(new Event('change'));
        }
    });
});

// Form validation
document.getElementById('checkoutForm').addEventListener('submit', function(e) {
    const addressType = document.querySelector('input[name="address_type"]:checked').value;
    const shippingAddress = document.querySelector('textarea[name="shipping_address"]');
    
    if(addressType === 'new' && !shippingAddress.value.trim()) {
        e.preventDefault();
        alert('Please enter a shipping address');
        shippingAddress.focus();
    }
});

// Auto-hide alerts
setTimeout(function() {
    $('.alert').alert('close');
}, 5000);
</script>

<?php include("../includes/footer.php"); ?>
