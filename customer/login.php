<?php
include("../includes/header.php");

// Verify session is started (in case it's not in header.php)
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Redirect if already logged in
if(isset($_SESSION['customer_id'])) {
    header("Location: index.php");
    exit();
}

date_default_timezone_set('Asia/Kolkata');

$current_datetime = date('Y-m-d H:i:s'); // Converting UTC to IST

// Initialize variables
$error_msg = "";
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$redirect = isset($_GET['redirect']) ? $_GET['redirect'] : 'index.php';

// Handle login
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = mysqli_real_escape_string($conn, trim($_POST['email']));
    $password = trim($_POST['password']);
    
    // Validation
    if(empty($email) || empty($password)) {
        $error_msg = "Please enter both email and password";
    } else {
        // Check credentials - Move try/catch to specific operations
        $query = mysqli_query($conn, "
            SELECT customer_id, name, email, password, phone, address 
            FROM customers 
            WHERE email = '$email'
            LIMIT 1
        ");

        if(!$query) {
            $error_msg = "Database error: " . mysqli_error($conn);
        } elseif(mysqli_num_rows($query) > 0) {
            $customer = mysqli_fetch_assoc($query);
            
            // Verify password
            if(password_verify($password, $customer['password'])) {
                // Set session variables
                $_SESSION['customer_id'] = $customer['customer_id'];
                $_SESSION['customer_name'] = $customer['name'];
                $_SESSION['customer_email'] = $customer['email'];
                $_SESSION['customer_phone'] = $customer['phone'];
                $_SESSION['customer_address'] = $customer['address'];
                
              
                
                // Transfer cart items from session to database if any
                if(isset($_SESSION['temp_cart']) && !empty($_SESSION['temp_cart'])) {
                    foreach($_SESSION['temp_cart'] as $item_id => $quantity) {
                        $cart_query = mysqli_query($conn, "
                            INSERT INTO cart (customer_id, item_id, quantity, created_at) 
                            VALUES (".$customer['customer_id'].", $item_id, $quantity, '$current_datetime')
                            ON DUPLICATE KEY UPDATE quantity = quantity + $quantity
                        ");
                        
                        if(!$cart_query) {
                            // Log error but don't stop the login process
                            error_log("Failed to transfer cart item: " . mysqli_error($conn));
                        }
                    }
                    unset($_SESSION['temp_cart']);
                }
                
                // Make sure session is written before redirect
                session_write_close();
                
                // Redirect
                header("Location: $redirect");
                exit();
            } else {
                $error_msg = "Invalid password";
            }
        } else {
            $error_msg = "Email not registered";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <style>
    .login-container {
        max-width: 450px;
        margin: 2rem auto;
    }

    .form-card {
        border: none;
        border-radius: 15px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }

    .login-header {
        text-align: center;
        margin-bottom: 2rem;
    }

    .login-icon {
        font-size: 3rem;
        color: #007bff;
        margin-bottom: 1rem;
    }

    .form-floating {
        margin-bottom: 1rem;
    }

    .form-control:focus {
        border-color: #007bff;
        box-shadow: 0 0 0 0.25rem rgba(0, 123, 255, 0.25);
    }

    .btn-login {
        padding: 0.75rem;
        font-weight: 500;
    }

    .divider {
        display: flex;
        align-items: center;
        text-align: center;
        margin: 1.5rem 0;
    }

    .divider::before,
    .divider::after {
        content: '';
        flex: 1;
        border-bottom: 1px solid #dee2e6;
    }

    .divider span {
        padding: 0 1rem;
        color: #6c757d;
        background: #fff;
    }

    .timer-badge {
        font-size: 0.875rem;
        padding: 0.5rem 1rem;
        border-radius: 10px;
        background: #f8f9fa;
        margin-bottom: 1.5rem;
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
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="nav-link" href="index.php">Home</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="login.php">Login</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="register.php">Register</a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<div class="container login-container">
    <!-- Current Time Display -->
    <div class="text-center">
        <span class="timer-badge d-inline-block">
            <i class="fas fa-clock me-2"></i>
            Current time: <?php echo $current_datetime; ?> IST
        </span>
    </div>

    <div class="card form-card">
        <div class="card-body p-4">
            <!-- Login Header -->
            <div class="login-header">
                <i class="fas fa-user-circle login-icon"></i>
                <h2>Welcome Back!</h2>
                <p class="text-muted">Please login to your account</p>
            </div>

            <?php if($error_msg): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?php echo $error_msg; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <!-- Login Form -->
            <form method="POST" action="" class="needs-validation" novalidate>
                <div class="form-floating mb-3">
                    <input type="email" class="form-control" id="email" name="email" 
                           placeholder="name@example.com" value="<?php echo htmlspecialchars($email); ?>" 
                           required>
                    <label for="email">Email address</label>
                    <div class="invalid-feedback">
                        Please enter your email address
                    </div>
                </div>

                <div class="form-floating mb-4">
                    <input type="password" class="form-control" id="password" name="password" 
                           placeholder="Password" required>
                    <label for="password">Password</label>
                    <div class="invalid-feedback">
                        Please enter your password
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="remember" name="remember">
                        <label class="form-check-label" for="remember">
                            Remember me
                        </label>
                    </div>
                    <a href="forgot_password.php" class="text-primary text-decoration-none">
                        Forgot Password?
                    </a>
                </div>

                <div class="d-grid">
                    <button type="submit" class="btn btn-primary btn-login">
                        <i class="fas fa-sign-in-alt me-2"></i>Login
                    </button>
                </div>
            </form>

            <div class="divider">
                <span>OR</span>
            </div>

            <div class="text-center">
                <p class="mb-0">Don't have an account? 
                    <a href="register.php" class="text-primary">Register here</a>
                </p>
            </div>
        </div>
    </div>

    <!-- Security Notice -->
    <div class="text-center mt-4">
        <small class="text-muted">
            <i class="fas fa-shield-alt me-1"></i>
            Your connection to this site is secure
        </small>
    </div>
</div>

<script>
// Form validation
(function () {
    'use strict'
    var forms = document.querySelectorAll('.needs-validation')
    Array.prototype.slice.call(forms).forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!form.checkValidity()) {
                event.preventDefault()
                event.stopPropagation()
            }
            form.classList.add('was-validated')
        }, false)
    })
})()

// Auto-hide alerts
document.addEventListener('DOMContentLoaded', function() {
    // Use vanilla JS instead of jQuery for better compatibility
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(function(alert) {
        setTimeout(function() {
            alert.classList.remove('show');
            setTimeout(function() {
                alert.remove();
            }, 150);
        }, 5000);
    });
});

// Remember me functionality
document.addEventListener('DOMContentLoaded', function() {
    const emailField = document.getElementById('email');
    const rememberCheckbox = document.getElementById('remember');
    
    // Load saved email if exists
    const savedEmail = localStorage.getItem('remembered_email');
    if (savedEmail) {
        emailField.value = savedEmail;
        rememberCheckbox.checked = true;
    }
    
    // Save email when form is submitted with remember checked
    const form = document.querySelector('form');
    form.addEventListener('submit', function() {
        if (rememberCheckbox.checked) {
            localStorage.setItem('remembered_email', emailField.value);
        } else {
            localStorage.removeItem('remembered_email');
        }
    });
});
</script>

<?php include("../includes/footer.php"); ?>