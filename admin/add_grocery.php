



<?php
include("../includes/header.php");


// Initialize variables
$success_msg = $error_msg = "";
$name = $brand = $description = $price = $stock_quantity = $unit = "";
$image = "";
$category = "";

// Process form submission
if(isset($_POST['submit'])) {
    // Sanitize inputs
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $brand = !empty($_POST['brand']) ? mysqli_real_escape_string($conn, $_POST['brand']) : NULL;
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $price = (float)$_POST['price'];
    $stock_quantity = (int)$_POST['stock_quantity'];
    $unit = mysqli_real_escape_string($conn, $_POST['unit']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);
	
	    $category = mysqli_real_escape_string($conn, $_POST['category']);

    // Validate inputs
    $errors = [];
    
    if(empty($name)) {
        $errors[] = "Name is required";
    }
    
    if(empty($description)) {
        $errors[] = "Description is required";
    }
    
    if($price <= 0) {
        $errors[] = "Price must be greater than 0";
    }
    
	 if(empty($category)) {
        $errors[] = "Category is required";
    }
	
	
    if($stock_quantity < 0) {
        $errors[] = "Stock quantity cannot be negative";
    }
    
    if(empty($unit)) {
        $errors[] = "Unit is required";
    }

    // Image upload handling
    if(isset($_FILES['image']) && $_FILES['image']['name']) {
        $file = $_FILES['image'];
        $allowed_types = ['image/jpeg', 'image/png', 'image/jpg'];
        $max_size = 5 * 1024 * 1024; // 5MB

        if(!in_array($file['type'], $allowed_types)) {
            $errors[] = "Only JPG, JPEG & PNG files are allowed";
        }

        if($file['size'] > $max_size) {
            $errors[] = "File size must be less than 5MB";
        }

        if(empty($errors)) {
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = uniqid() . '_' . time() . '.' . $extension;
            $upload_path = "../assets/images/grocery/";

            // Create directory if it doesn't exist
            if(!file_exists($upload_path)) {
                mkdir($upload_path, 0777, true);
            }

            if(move_uploaded_file($file['tmp_name'], $upload_path . $filename)) {
                $image = $filename;
            } else {
                $errors[] = "Failed to upload image";
            }
        }
    }

    // If no errors, insert into database
    if(empty($errors)) {
        $current_datetime = date('Y-m-d H:i:s');
        
        // Prepare brand value for SQL
        $brand_sql = $brand === NULL ? "NULL" : "'$brand'";
        
        $query = "INSERT INTO grocery_items (
        name, brand, category, description, price, stock_quantity, unit, 
        image, status, created_at, updated_at
    ) VALUES (
        '$name', $brand_sql, '$category', '$description', $price, $stock_quantity, '$unit', 
        '$image', '$status', '$current_datetime', '$current_datetime'
    )";

        if(mysqli_query($conn, $query)) {
            $success_msg = "Grocery item added successfully!";
            // Clear form
            $name = $brand = $description = $price = $stock_quantity = $unit = "";
        } else {
            $error_msg = "Error adding item: " . mysqli_error($conn);
        }
    } else {
        $error_msg = "Please correct the following errors:<br>" . implode("<br>", $errors);
    }
}

// Get list of common brands for datalist
$brands_query = mysqli_query($conn, "SELECT DISTINCT brand FROM grocery_items WHERE brand IS NOT NULL ORDER BY brand");
$brands = [];
while($row = mysqli_fetch_assoc($brands_query)) {
    $brands[] = $row['brand'];
}















$categories = [
    // Fruits & Vegetables
    'fresh-vegetables' => 'Fresh Vegetables',
    'fresh-fruits' => 'Fresh Fruits',
    'herbs-seasonings' => 'Fresh Herbs & Seasonings',
    'exotic-vegetables' => 'Exotic Vegetables',
    'exotic-fruits' => 'Exotic Fruits',
    'organic-produce' => 'Organic Produce',
    
    // Staples
    'rice-grains' => 'Rice & Other Grains',
    'dals-pulses' => 'Dals & Pulses',
    'flours-sooji' => 'Flours & Sooji',
    'oils-ghee' => 'Cooking Oils & Ghee',
    'dried-beans' => 'Dried Beans',
    'sugar-jaggery' => 'Sugar & Jaggery',
    'salt' => 'Salt & Salt Products',
    
    // Spices & Masalas
    'whole-spices' => 'Whole Spices',
    'powdered-spices' => 'Powdered Spices',
    'blended-masalas' => 'Blended Masalas',
    'cooking-pastes' => 'Cooking Pastes',
    'indian-spice-mixes' => 'Indian Spice Mixes',
    
    // Snacks & Packaged Foods
    'biscuits-cookies' => 'Biscuits & Cookies',
    'namkeen-snacks' => 'Namkeen & Snacks',
    'chips-crisps' => 'Chips & Crisps',
    'breakfast-cereals' => 'Breakfast Cereals',
    'noodles-pasta' => 'Noodles & Pasta',
    'ready-to-eat' => 'Ready to Eat Foods',
    'canned-foods' => 'Canned Foods',
    
    // Dairy & Eggs
    'milk' => 'Milk & Milk Products',
    'butter-cheese' => 'Butter & Cheese',
    'paneer-tofu' => 'Paneer & Tofu',
    'eggs' => 'Eggs',
    'curd-yogurt' => 'Curd & Yogurt',
    
    // Beverages
    'tea' => 'Tea & Tea Bags',
    'coffee' => 'Coffee & Coffee Products',
    'health-drinks' => 'Health Drinks',
    'fruit-juices' => 'Fruit Juices',
    'soft-drinks' => 'Soft Drinks',
    'water' => 'Packaged Water',
    
    // Bread & Bakery
    'breads' => 'Breads & Buns',
    'cakes-pastries' => 'Cakes & Pastries',
    'cookies-rusks' => 'Cookies & Rusks',
    'bakery-snacks' => 'Bakery Snacks',
    
    // Personal Care
    'soap-handwash' => 'Soap & Handwash',
    'oral-care' => 'Oral Care',
    'hair-care' => 'Hair Care',
    'skin-care' => 'Skin Care',
    'feminine-hygiene' => 'Feminine Hygiene',
    
    // Household
    'detergents' => 'Detergents & Dishwash',
    'cleaning-supplies' => 'Cleaning Supplies',
    'pooja-needs' => 'Pooja Needs',
    'kitchen-supplies' => 'Kitchen Supplies',
    'repellents' => 'Repellents & Fresheners',
    
    // Baby Care
    'baby-food' => 'Baby Food & Formula',
    'diapers-wipes' => 'Diapers & Wipes',
    'baby-care' => 'Baby Care Products',
    
    // Pet Care
    'dog-food' => 'Dog Food & Supplies',
    'cat-food' => 'Cat Food & Supplies',
    'pet-accessories' => 'Pet Accessories',
    
    // Condiments & Sauces
    'jams-spreads' => 'Jams & Spreads',
    'sauces-ketchup' => 'Sauces & Ketchup',
    'pickles-chutneys' => 'Pickles & Chutneys',
    'vinegar-cooking-wine' => 'Vinegar & Cooking Wine',
    
    // Dry Fruits & Nuts
    'almonds' => 'Almonds',
    'cashews' => 'Cashews',
    'raisins' => 'Raisins',
    'other-dry-fruits' => 'Other Dry Fruits',
    'mixed-dry-fruits' => 'Mixed Dry Fruits',
    
    // International Cuisine
    'chinese' => 'Chinese & Asian Foods',
    'italian' => 'Italian & Continental',
    'mexican' => 'Mexican & Other International',
    
    // Others
    'seasonal' => 'Seasonal Items',
    'organic' => 'Organic Products',
    'special-diet' => 'Special Dietary Products',
];

// Group categories by main types
$category_groups = [
    'Fruits & Vegetables' => ['fresh-vegetables', 'fresh-fruits', 'herbs-seasonings', 'exotic-vegetables', 'exotic-fruits', 'organic-produce'],
    'Staples' => ['rice-grains', 'dals-pulses', 'flours-sooji', 'oils-ghee', 'dried-beans', 'sugar-jaggery', 'salt'],
    'Spices & Masalas' => ['whole-spices', 'powdered-spices', 'blended-masalas', 'cooking-pastes', 'indian-spice-mixes'],
    // ... (continue for other groups)
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <style>
    .add-grocery-container {
        background: #f8f9fa;
        min-height: 100vh;
        padding: 20px;
    }
    
    .form-card {
        border: none;
        border-radius: 15px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    }
    
    .form-label {
        font-weight: 500;
        color: #2c3e50;
    }
    
    .form-control {
        border-radius: 10px;
        border: 2px solid #eee;
        padding: 10px 15px;
        transition: all 0.3s;
    }
    
    .form-control:focus {
        border-color: #4ECB71;
        box-shadow: 0 0 0 0.2rem rgba(78, 203, 113, 0.25);
    }
    
    .image-preview {
        width: 200px;
        height: 200px;
        border-radius: 10px;
        border: 2px dashed #ddd;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
    }
    
    .image-preview img {
        max-width: 100%;
        max-height: 100%;
        object-fit: cover;
    }
    
    .required-field::after {
        content: "*";
        color: red;
        margin-left: 4px;
    }
    
    .btn-submit {
        padding: 12px 30px;
        border-radius: 10px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 1px;
    }
    
    .select2-container--default .select2-selection--single {
        border-radius: 10px;
        height: 45px;
        border: 2px solid #eee;
    }
    
    .select2-container--default .select2-selection--single .select2-selection__rendered {
        line-height: 43px;
        padding-left: 15px;
    }
    
    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 43px;
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
	
	 .brand-input-group {
        position: relative;
    }
    
    .clear-brand {
        position: absolute;
        right: 10px;
        top: 50%;
        transform: translateY(-50%);
        z-index: 10;
        cursor: pointer;
        color: #dc3545;
        display: none;

    }
    
    .preview-container {
        position: relative;
        width: 200px;
        height: 200px;
    }
    
    .preview-container .remove-image {
        position: absolute;
        top: 5px;
        right: 5px;
        background: rgba(255, 255, 255, 0.8);
        border-radius: 50%;
        padding: 5px;
        cursor: pointer;
        display: none;
    }
    
	
	
	.select2-container--classic .select2-results__option {
    padding: 8px 12px;
}

.select2-container--classic .select2-results__group {
    background-color: #f8f9fa;
    font-weight: bold;
    padding: 6px 12px;
}

.select2-container--classic .select2-results__options--nested {
    padding-left: 10px;
}

.select2-container--classic .select2-selection--single {
    height: 45px;
    border: 2px solid #eee;
    border-radius: 10px;
}

.select2-container--classic .select2-selection--single .select2-selection__rendered {
    line-height: 41px;
    padding-left: 15px;
}

.select2-container--classic .select2-selection--single .select2-selection__arrow {
    height: 41px;
}
   
    </style>
    
    <!-- Include Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
</head>
<body>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar - Same as grocery_items.php -->
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
            <div class="add-grocery-container">
                <!-- Header -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-plus-circle me-2"></i>Add New Grocery Item</h2>
                    <a href="grocery_items.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Back to List
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

                <!-- Add Form -->
                <div class="card form-card">
                    <div class="card-body p-4">
                        <form method="POST" enctype="multipart/form-data" id="addGroceryForm" class="needs-validation" novalidate>
                            <div class="row">
                                <div class="col-md-8">
                                    <!-- Basic Information -->
                                    <div class="mb-4">
                                        <label for="name" class="form-label required-field">Item Name</label>
                                        <input type="text" class="form-control" id="name" name="name" 
                                               value="<?php echo htmlspecialchars($name); ?>"
											   
											   
											    pattern="^[A-Za-z\s\-&.,'()]+$"
           title="Product name can only contain letters, spaces, and the following characters: - & . , ' ( )"
           oninput="this.value = this.value.replace(/[0-9]/g, '')">
											   
											   
											   
											   
											   
											    required>
                                        <div class="invalid-feedback">
                                            Please enter item name
                                        </div>
                                    </div>


 <div class="mb-4">
                <label for="brand" class="form-label">Brand (Optional)</label>
                <div class="brand-input-group">
                    <input type="text" class="form-control" id="brand" name="brand" 
                           value="<?php echo htmlspecialchars($brand); ?>" 
                           list="brandsList">
                    <i class="fas fa-times clear-brand"></i>
                    <datalist id="brandsList">
                        <?php foreach($brands as $brand): ?>
                        <option value="<?php echo htmlspecialchars($brand); ?>">
                        <?php endforeach; ?>
                    </datalist>
                    <div class="form-text">
                        Leave empty for items without brand (e.g., fruits, vegetables)
                    </div>
                </div>
				
				
				
				
            </div>
			
			
			<div class="mb-4">
    <label for="category" class="form-label required-field">Category</label>
    <select class="form-control select2" id="category" name="category" required>
        <option value="">Select Category</option>
        <?php foreach($category_groups as $group => $items): ?>
        <optgroup label="<?php echo htmlspecialchars($group); ?>">
            <?php foreach($items as $key): ?>
            <option value="<?php echo $key; ?>" <?php echo ($category == $key) ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($categories[$key]); ?>
            </option>
            <?php endforeach; ?>
        </optgroup>
        <?php endforeach; ?>
    </select>
    <div class="invalid-feedback">
        Please select a category
    </div>
</div>

			
			
			
			
			
			
			
			
			
			
                                    <div class="mb-4">
                                        <label for="description" class="form-label required-field">Description</label>
                                        <textarea class="form-control" id="description" name="description" 
                                                  rows="4" required><?php echo htmlspecialchars($description); ?></textarea>
                                        <div class="invalid-feedback">
                                            Please enter item description
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-4">
                                                <label for="price" class="form-label required-field">Price (?)</label>
                                                <input type="number" class="form-control" id="price" name="price" 
                                                       step="0.01" min="0" value="<?php echo $price; ?>" required>
                                                <div class="invalid-feedback">
                                                    Please enter a valid price
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-4">
                                                <label for="stock_quantity" class="form-label required-field">Stock Quantity</label>
                                                <input type="number" class="form-control" id="stock_quantity" 
                                                       name="stock_quantity" min="0" value="<?php echo $stock_quantity; ?>" required>
                                                <div class="invalid-feedback">
                                                    Please enter valid stock quantity
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-4">
                                                <label for="unit" class="form-label required-field">Unit</label>
                                                <select class="form-control select2" id="unit" name="unit" required>
                                                    <option value="">Select Unit</option>
                                                    <option value="kg" <?php echo $unit == 'kg' ? 'selected' : ''; ?>>Kilogram (kg)</option>
                                                    <option value="g" <?php echo $unit == 'g' ? 'selected' : ''; ?>>Gram (g)</option>
                                                    <option value="l" <?php echo $unit == 'l' ? 'selected' : ''; ?>>Litre (l)</option>
                                                    <option value="ml" <?php echo $unit == 'ml' ? 'selected' : ''; ?>>Millilitre (ml)</option>
                                                    <option value="piece" <?php echo $unit == 'piece' ? 'selected' : ''; ?>>Piece</option>
                                                    <option value="dozen" <?php echo $unit == 'dozen' ? 'selected' : ''; ?>>Dozen</option>
                                                    <option value="packet" <?php echo $unit == 'packet' ? 'selected' : ''; ?>>Packet</option>
                                                </select>
                                                <div class="invalid-feedback">
                                                    Please select a unit
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-4">
                                                <label for="status" class="form-label required-field">Status</label>
                                                <select class="form-control" id="status" name="status" required>
                                                    <option value="active">Active</option>
                                                    <option value="inactive">Inactive</option>
                                                </select>
                                                <div class="invalid-feedback">
                                                    Please select status
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-4">
                <!-- Updated Image Upload Section -->
                <div class="mb-4">
                    <label class="form-label">Item Image</label>
                    <div class="preview-container mb-3">
                        <img src="../assets/images/placeholder.png" 
                             alt="Preview" 
                             id="imagePreview" 
                             class="img-fluid rounded" 
                             style="max-width: 100%; max-height: 200px; object-fit: contain;">
                        <div class="remove-image" title="Remove image">
                            <i class="fas fa-times text-danger"></i>
                        </div>
                    </div>
                    <input type="file" class="form-control" id="image" name="image" 
                           accept="image/png, image/jpeg, image/jpg" 
                           onchange="previewImage(this);">
                    <div class="form-text text-muted">
                        Max file size: 5MB. Allowed formats: JPG, JPEG, PNG
                    </div>
                </div>
            </div>
        </div>
    </div>


                            <hr class="my-4">

                            <!-- Submit Buttons -->
                            <div class="text-end">
                                <button type="reset" class="btn btn-secondary me-2">
                                    <i class="fas fa-redo me-2"></i>Reset
                                </button>
                                <button type="submit" name="submit" class="btn btn-success btn-submit">
                                    <i class="fas fa-plus-circle me-2"></i>Add Item
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Include Select2 JS -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
// Initialize Select2
$(document).ready(function() {
    $('.select2').select2({
        theme: 'classic',
        width: '100%'
    });
});

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

// Image preview function
function previewImage(input) {
    const preview = document.getElementById('imagePreview');
    const removeBtn = document.querySelector('.remove-image');
    
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        
        reader.onload = function(e) {
            preview.src = e.target.result;
            removeBtn.style.display = 'block';
        }
        
        reader.readAsDataURL(input.files[0]);
    } else {
        preview.src = '../assets/images/placeholder.png';
        removeBtn.style.display = 'none';
    }
}

// Remove image
document.querySelector('.remove-image').addEventListener('click', function() {
    const imageInput = document.getElementById('image');
    const preview = document.getElementById('imagePreview');
    const removeBtn = document.querySelector('.remove-image');
    
    imageInput.value = '';
    preview.src = '../assets/images/placeholder.png';
    removeBtn.style.display = 'none';
});

// Brand field handling
document.getElementById('brand').addEventListener('input', function() {
    const clearBtn = document.querySelector('.clear-brand');
    clearBtn.style.display = this.value ? 'block' : 'none';
});

document.querySelector('.clear-brand').addEventListener('click', function() {
    const brandInput = document.getElementById('brand');
    brandInput.value = '';
    this.style.display = 'none';
});

// Initialize brand clear button display
window.addEventListener('load', function() {
    const brandInput = document.getElementById('brand');
    const clearBtn = document.querySelector('.clear-brand');
    clearBtn.style.display = brandInput.value ? 'block' : 'none';
});
// Auto-hide alerts
setTimeout(function() {
    $('.alert').alert('close');
}, 5000);

// Price formatting
$('#price').on('input', function() {
    let value = $(this).val();
    if(value && !isNaN(value)) {
        $(this).val(parseFloat(value).toFixed(2));
    }
});

// Form reset confirmation
$('button[type="reset"]').click(function(e) {
    e.preventDefault();
    if(confirm('Are you sure you want to reset the form? All entered data will be lost.')) {
        $('#addGroceryForm')[0].reset();
        $('#preview').attr('src', '../assets/images/placeholder.png');
        $('.select2').val('').trigger('change');
        $('#addGroceryForm').removeClass('was-validated');
    }
});








$('button[type="reset"]').click(function(e) {
    e.preventDefault();
    if(confirm('Are you sure you want to reset the form? All entered data will be lost.')) {
        $('#addGroceryForm')[0].reset();
        $('#imagePreview').attr('src', '../assets/images/placeholder.png');
        $('.remove-image').hide();
        $('.clear-brand').hide();
        $('.select2').val('').trigger('change');
        $('#addGroceryForm').removeClass('was-validated');
    }
});

// Current date and time display
document.addEventListener('DOMContentLoaded', function() {
    const currentUser = '<?php echo $_SESSION["admin_name"]; ?>';
    const currentDateTime = '<?php echo date("Y-m-d H:i:s"); ?>';
    
    // Add to your header or wherever you want to display it
    const dateTimeDisplay = document.createElement('div');
    dateTimeDisplay.className = 'text-muted mb-3';
    dateTimeDisplay.innerHTML = `
        <small>
            <i class="fas fa-user me-2"></i>Logged in as: ${currentUser}<br>
            <i class="fas fa-clock me-2"></i>${currentDateTime}
        </small>
    `;
    
    document.querySelector('.card-header').appendChild(dateTimeDisplay);
});



$(document).ready(function() {
    $('#category').select2({
        theme: 'classic',
        width: '100%',
        placeholder: 'Select Category',
        allowClear: true
    });
});

// Add to your form reset handler
$('button[type="reset"]').click(function(e) {
    // ... (previous reset code)
    $('#category').val('').trigger('change');
});
</script>

<?php include("../includes/footer.php"); ?>






