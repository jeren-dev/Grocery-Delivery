<?php
include("../includes/header.php");
extract($_POST);

if(isset($_POST['btn'])) {
  
    
    $qry = mysqli_query($conn, "SELECT * FROM admin WHERE name='$uname' AND psw='$password'");
    $num = mysqli_num_rows($qry);
    
    if($num == 1) {
        $row = mysqli_fetch_assoc($qry);
        $_SESSION['admin_id'] = $row['admin_id'];
        $_SESSION['admin_name'] = $row['name'];
        
        echo "<script>alert('Welcome to admin dashboard');</script>";
        header("location: dashboard.php");
        exit();
    } else {
        echo "<script>alert('Invalid Username or Password');</script>";
    }
}
?>

<style>
.login-container {
    min-height: 100vh;
    background: linear-gradient(135deg, #00b09b, #96c93d);
    padding: 40px 0;
}

.login-card {
    border: none;
    border-radius: 15px;
    box-shadow: 0 10px 20px rgba(0,0,0,0.19), 0 6px 6px rgba(0,0,0,0.23);
}

.login-header {
    background: #fff;
    border-radius: 15px 15px 0 0;
    padding: 20px;
    border-bottom: 2px solid #f8f9fa;
}

.login-title {
    color: #2c3e50;
    font-weight: 700;
    margin: 0;
    font-size: 24px;
}

.login-body {
    padding: 30px;
}

.form-control {
    border-radius: 10px;
    padding: 12px 15px;
    border: 2px solid #eee;
    transition: all 0.3s ease;
}

.form-control:focus {
    border-color: #96c93d;
    box-shadow: 0 0 0 0.2rem rgba(150, 201, 61, 0.25);
}

.input-group-text {
    background: transparent;
    border: 2px solid #eee;
    border-right: none;
    border-radius: 10px 0 0 10px;
}

.form-control {
    border-left: none;
    border-radius: 0 10px 10px 0;
}

.btn-login {
    padding: 12px 30px;
    border-radius: 10px;
    background: linear-gradient(135deg, #00b09b, #96c93d);
    border: none;
    font-weight: 600;
    width: 100%;
    transition: all 0.3s ease;
}

.btn-login:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}

.login-icon {
    font-size: 80px;
    color: #00b09b;
    margin-bottom: 20px;
}

.input-icon {
    color: #00b09b;
}
</style>

<div class="login-container">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-5">
                <div class="text-center mb-4">
                    <i class="fas fa-store login-icon"></i>
                </div>
                <div class="login-card card">
                    <div class="login-header">
                        <h3 class="login-title text-center">
                            <i class="fas fa-user-shield me-2"></i>Admin Login
                        </h3>
                    </div>
                    <div class="login-body">
                        <form method="post" class="needs-validation" novalidate>
                            <div class="mb-4">
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-user input-icon"></i>
                                    </span>
                                    <input type="text" class="form-control" id="uname" name="uname" 
                                           placeholder="Enter username" required>
                                </div>
                            </div>
                            <div class="mb-4">
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-lock input-icon"></i>
                                    </span>
                                    <input type="password" class="form-control" id="password" name="password" 
                                           placeholder="Enter password" required>
                                </div>
                            </div>
                            <div class="mb-4">
                                <button type="submit" name="btn" class="btn btn-primary btn-login">
                                    <i class="fas fa-sign-in-alt me-2"></i>Login
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                <div class="text-center text-white mt-4">
                    <p><i class="fas fa-clock me-2"></i><?php echo date('Y-m-d H:i:s'); ?></p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Form validation
(function () {
    'use strict'
    var forms = document.querySelectorAll('.needs-validation')
    Array.prototype.slice.call(forms)
        .forEach(function (form) {
            form.addEventListener('submit', function (event) {
                if (!form.checkValidity()) {
                    event.preventDefault()
                    event.stopPropagation()
                }
                form.classList.add('was-validated')
            }, false)
        })
})()

// Add floating effect to input fields
document.querySelectorAll('.form-control').forEach(input => {
    input.addEventListener('focus', () => {
        input.parentElement.classList.add('focused');
    });
    input.addEventListener('blur', () => {
        if (!input.value) {
            input.parentElement.classList.remove('focused');
        }
    });
});
</script>

<?php include("../includes/footer.php"); ?>