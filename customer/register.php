<?php
include("../includes/header.php");

// Redirect if already logged in
if(isset($_SESSION['customer_id'])) {
    header("Location: dashboard.php");
    exit();
}

// Set timezone
date_default_timezone_set('Asia/Kolkata');

 $current_datetime = date('Y-m-d H:i:s'); // Converting UTC to IST

// Initialize variables
$error_msg = $success_msg = "";
$form_data = [
    'name' => '',
    'email' => '',
    'phone' => '',
    'address' => ''
];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get and sanitize form data
    $form_data = [
        'name' => mysqli_real_escape_string($conn, trim($_POST['name'])),
        'email' => mysqli_real_escape_string($conn, trim($_POST['email'])),
        'password' => trim($_POST['password']),
        'confirm_password' => trim($_POST['confirm_password']),
        'phone' => mysqli_real_escape_string($conn, trim($_POST['phone'])),
        'address' => mysqli_real_escape_string($conn, trim($_POST['address']))
    ];

    // Validation
    $errors = [];

    // Name validation
    if (empty($form_data['name'])) {
        $errors[] = "Name is required";
    } elseif (strlen($form_data['name']) < 2 || strlen($form_data['name']) > 100) {
        $errors[] = "Name must be between 2 and 100 characters";
    }

    // Email validation
    if (empty($form_data['email'])) {
        $errors[] = "Email is required";
    } elseif (!filter_var($form_data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    } else {
        // Check if email already exists
        $check_email = mysqli_query($conn, "SELECT customer_id FROM customers WHERE email = '".$form_data['email']."'");
        if (mysqli_num_rows($check_email) > 0) {
            $errors[] = "Email already registered";
        }
    }

    // Password validation
    if (empty($form_data['password'])) {
        $errors[] = "Password is required";
    } elseif (strlen($form_data['password']) < 6) {
        $errors[] = "Password must be at least 6 characters long";
    } elseif ($form_data['password'] !== $form_data['confirm_password']) {
        $errors[] = "Passwords do not match";
    }

    // Phone validation
    if (empty($form_data['phone'])) {
        $errors[] = "Phone number is required";
    } elseif (!preg_match("/^[0-9]{10}$/", $form_data['phone'])) {
        $errors[] = "Invalid phone number format (10 digits required)";
    }

    // Address validation
    if (empty($form_data['address'])) {
        $errors[] = "Address is required";
    } elseif (strlen($form_data['address']) < 10) {
        $errors[] = "Please enter a complete address";
    }

    // If no errors, proceed with registration
    if (empty($errors)) {
        try {
            // Hash password
            $hashed_password = password_hash($form_data['password'], PASSWORD_DEFAULT);
            
            // Begin transaction
            mysqli_begin_transaction($conn);

            // Insert new customer
            $insert_query = mysqli_query($conn, "
                INSERT INTO customers (
                    name, 
                    email, 
                    password, 
                    phone, 
                    address, 
                    created_at
                ) VALUES (
                    '".$form_data['name']."',
                    '".$form_data['email']."',
                    '".$hashed_password."',
                    '".$form_data['phone']."',
                    '".$form_data['address']."',
                    '".$current_datetime."'
                )
            ");

            if ($insert_query) {
                mysqli_commit($conn);
                $success_msg = "Registration successful! You can now login.";
                // Clear form data after successful registration
                $form_data = ['name' => '', 'email' => '', 'phone' => '', 'address' => ''];
            } else {
                throw new Exception("Registration failed");
            }
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $error_msg = $e->getMessage();
        }
    } else {
        $error_msg = implode("<br>", $errors);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <style>
    .register-container {
        max-width: 600px;
        margin: 2rem auto;
        padding: 2rem;
    }

    .form-card {
        border: none;
        border-radius: 15px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }

    .form-label {
        font-weight: 500;
    }

    .required-field::after {
        content: "*";
        color: red;
        margin-left: 4px;
    }

    .password-requirements {
        font-size: 0.875rem;
        color: #6c757d;
        margin-top: 0.5rem;
    }

    .form-control:focus {
        box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.15);
    }

    .btn-register {
        padding: 0.75rem 2rem;
        font-weight: 500;
    }

    .timer-badge {
        font-size: 0.875rem;
        padding: 0.5rem 1rem;
        border-radius: 10px;
        background: #f8f9fa;
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
                    <a class="nav-link" href="login.php">Login</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="register.php">Register</a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<div class="container register-container">
    <!-- Current Time Display -->
    <div class="text-center mb-4">
        <span class="timer-badge">
            <i class="fas fa-clock me-2"></i>
            Current time: <?php echo $current_datetime; ?> IST
        </span>
    </div>

    <div class="card form-card">
        <div class="card-body">
            <h2 class="card-title text-center mb-4">Create an Account</h2>

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

            <form method="POST" action="" class="needs-validation" novalidate>
                <div class="mb-3">
                    <label class="form-label required-field">Full Name</label>
                    <input type="text" class="form-control" name="name" 
                           value="<?php echo htmlspecialchars($form_data['name']); ?>" 
                           required pattern="^[A-Za-z\s\-&.,'()]+$"
              title="Product name can only contain letters, spaces, and the following characters: - & . , ' ( )"
           oninput="this.value = this.value.replace(/[0-9]/g, '')">
                    <div class="invalid-feedback">Please enter your full name</div>
                </div>

                <div class="mb-3">
                    <label class="form-label required-field">Email Address</label>
                    <input type="email" class="form-control" name="email" 
                           value="<?php echo htmlspecialchars($form_data['email']); ?>" 
                           required>
                    <div class="invalid-feedback">Please enter a valid email address</div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label required-field">Password</label>
                        <input type="password" class="form-control" name="password" 
                               required minlength="6">
                        <div class="password-requirements">
                            Must be at least 6 characters long
                        </div>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label required-field">Confirm Password</label>
                        <input type="password" class="form-control" name="confirm_password" 
                               required minlength="6">
                        <div class="invalid-feedback">Passwords must match</div>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label required-field">Phone Number</label>
                    <input type="tel" class="form-control" name="phone" 
                           value="<?php echo htmlspecialchars($form_data['phone']); ?>" 
                           required pattern="[0-9]{10}" 
                           title="Please enter a valid 10-digit phone number">
                    <div class="invalid-feedback">Please enter a valid 10-digit phone number</div>
                </div>

                <div class="mb-4">
                    <label class="form-label required-field">Delivery Address</label>
                    <textarea class="form-control" name="address" rows="3" 
                              required minlength="10"><?php echo htmlspecialchars($form_data['address']); ?></textarea>
                    <div class="invalid-feedback">Please enter your complete delivery address</div>
                </div>

                <div class="d-grid">
                    <button type="submit" class="btn btn-primary btn-register">
                        <i class="fas fa-user-plus me-2"></i>Register
                    </button>
                </div>
            </form>

            <div class="text-center mt-4">
                <p class="mb-0">Already have an account? 
                    <a href="login.php" class="text-primary">Login here</a>
                </p>
            </div>
        </div>
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
setTimeout(function() {
    $('.alert').alert('close');
}, 5000);

// Password match validation
document.querySelector('input[name="confirm_password"]').addEventListener('input', function(e) {
    const password = document.querySelector('input[name="password"]').value;
    if(this.value !== password) {
        this.setCustomValidity('Passwords do not match');
    } else {
        this.setCustomValidity('');
    }
});
</script>

<?php include("../includes/footer.php"); ?>