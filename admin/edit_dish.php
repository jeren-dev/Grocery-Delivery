<?php
include("../includes/header.php");


// Set timezone and format current time
date_default_timezone_set('UTC');
$current_datetime = date('Y-m-d H:i:s');
$current_user = $_SESSION['admin_name'];

// Initialize variables
$success_msg = $error_msg = "";
$dish_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$selected_items = [];

try {
    // Check if dish exists and get its details
    $dish_query = mysqli_prepare($conn, "
        SELECT d.*, GROUP_CONCAT(dgi.item_id) as selected_items 
        FROM dishes d 
        LEFT JOIN dish_grocery_items dgi ON d.dish_id = dgi.dish_id 
        WHERE d.dish_id = ?
        GROUP BY d.dish_id
    ");
    
    mysqli_stmt_bind_param($dish_query, "i", $dish_id);
    mysqli_stmt_execute($dish_query);
    $result = mysqli_stmt_get_result($dish_query);

    if(mysqli_num_rows($result) == 0) {
        header("Location: dishes.php");
        exit();
    }

    $dish = mysqli_fetch_assoc($result);
    
    // Initialize form variables with existing data
    $name = $dish['name'];
    $description = $dish['description'];
    $status = $dish['status'];
    $current_image = $dish['image'];
    $selected_items = $dish['selected_items'] ? explode(',', $dish['selected_items']) : [];

    // Get all active grocery items for recommendations
    $grocery_items_query = mysqli_query($conn, "
        SELECT 
            gi.item_id,
            gi.name,
            gi.brand,
            gi.category,
            gi.price,
            gi.unit
        FROM grocery_items gi 
        WHERE gi.status = 'active' 
        ORDER BY gi.category, gi.name
    ");

    $grocery_items = [];
    while($item = mysqli_fetch_assoc($grocery_items_query)) {
        $grocery_items[] = $item;
    }

    // Group items by category
    $grouped_items = [];
    foreach($grocery_items as $item) {
        $grouped_items[$item['category']][] = $item;
    }

} catch(Exception $e) {
    $error_msg = "Error loading dish: " . $e->getMessage();
}

// Process form submission
if(isset($_POST['submit'])) {
    try {
        // Sanitize inputs
        $name = mysqli_real_escape_string($conn, $_POST['name']);
        $description = mysqli_real_escape_string($conn, $_POST['description']);
        $status = mysqli_real_escape_string($conn, $_POST['status']);
        $selected_items = isset($_POST['recommended_items']) ? array_map('intval', $_POST['recommended_items']) : [];

        // Validate inputs
        $errors = [];
        
        if(empty($name)) {
            $errors[] = "Dish name is required";
        }
        
        if(empty($description)) {
            $errors[] = "Description is required";
        }
        
        if(empty($selected_items)) {
            $errors[] = "Please select at least one recommended item";
        }

        // Image upload handling
        $image = $current_image;
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
                $upload_path = "../assets/images/dishes/";

                if(!file_exists($upload_path)) {
                    mkdir($upload_path, 0777, true);
                }

                if(move_uploaded_file($file['tmp_name'], $upload_path . $filename)) {
                    // Delete old image if exists
                    if($current_image && file_exists($upload_path . $current_image)) {
                        unlink($upload_path . $current_image);
                    }
                    $image = $filename;
                } else {
                    throw new Exception("Failed to upload image");
                }
            }
        }

        // Handle image removal
        if(isset($_POST['remove_image']) && $current_image) {
            $upload_path = "../assets/images/dishes/";
            if(file_exists($upload_path . $current_image)) {
                unlink($upload_path . $current_image);
            }
            $image = '';
        }

        // If no errors, update database
        if(empty($errors)) {
            mysqli_begin_transaction($conn);
            
            try {
                // Update dish
                $update_query = "UPDATE dishes SET 
                    name = ?,
                    description = ?,
                    image = ?,
                    status = ?,
                    updated_at = ?
                    WHERE dish_id = ?";
                
                $stmt = mysqli_prepare($conn, $update_query);
                mysqli_stmt_bind_param($stmt, "sssssi", 
                    $name, $description, $image, $status, $current_datetime, $dish_id
                );

                if(!mysqli_stmt_execute($stmt)) {
                    throw new Exception("Error updating dish: " . mysqli_stmt_error($stmt));
                }

                // Delete existing recommendations
                mysqli_query($conn, "DELETE FROM dish_grocery_items WHERE dish_id = $dish_id");
                
                // Insert new recommendations
                foreach($selected_items as $item_id) {
                    $item_query = "INSERT INTO dish_grocery_items (dish_id, item_id) VALUES (?, ?)";
                    $stmt = mysqli_prepare($conn, $item_query);
                    mysqli_stmt_bind_param($stmt, "ii", $dish_id, $item_id);
                    
                    if(!mysqli_stmt_execute($stmt)) {
                        throw new Exception("Error updating recommended items");
                    }
                }
                
                mysqli_commit($conn);
                $success_msg = "Dish updated successfully!";
                
                // Refresh dish data
                $dish_query = mysqli_query($conn, "
                    SELECT d.*, GROUP_CONCAT(dgi.item_id) as selected_items 
                    FROM dishes d 
                    LEFT JOIN dish_grocery_items dgi ON d.dish_id = dgi.dish_id 
                    WHERE d.dish_id = $dish_id
                    GROUP BY d.dish_id
                ");
                $dish = mysqli_fetch_assoc($dish_query);
                $selected_items = $dish['selected_items'] ? explode(',', $dish['selected_items']) : [];
                
            } catch(Exception $e) {
                mysqli_rollback($conn);
                throw $e;
            }
        } else {
            $error_msg = "Please correct the following errors:<br>" . implode("<br>", $errors);
        }
    } catch(Exception $e) {
        $error_msg = "Error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <style>
       .add-dish-container {
        background: #f8f9fa;
        min-height: 100vh;
        padding: 20px;
    }
    
    .form-card {
        border: none;
        border-radius: 15px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    }
    
    .recommended-items-container {
        max-height: 400px;
        overflow-y: auto;
    }
    
    .item-card {
        border: 1px solid #dee2e6;
        border-radius: 8px;
        transition: all 0.3s;
        cursor: pointer;
    }
    
    .item-card:hover {
        border-color: #4ECB71;
        background-color: #f8fff9;
    }
    
    .item-card.selected {
        border-color: #4ECB71;
        background-color: #f0fff3;
    }
    
    .category-header {
        background: #f8f9fa;
        padding: 10px 15px;
        margin-bottom: 10px;
        border-radius: 8px;
        font-weight: 600;
    }
    
    .preview-container {
        width: 200px;
        height: 200px;
        border: 2px dashed #ddd;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        position: relative;
    }
    
    .preview-container img {
        max-width: 100%;
        max-height: 100%;
        object-fit: contain;
    }
    
    .search-box {
        border-radius: 50px;
        padding: 10px 20px;
        border: 2px solid #eee;
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
    .edit-dish-container {
        background: #f8f9fa;
        min-height: 100vh;
        padding: 20px;
    }
    
    .form-card {
        border: none;
        border-radius: 15px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    }
    
    /* Add new styles specific to edit page */
    .dish-info {
        background: #fff;
        border-radius: 10px;
        padding: 15px;
        margin-bottom: 20px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    }
    
    .last-updated {
        font-size: 0.9rem;
        color: #6c757d;
    }
    
    .preview-controls {
        position: absolute;
        top: 10px;
        right: 10px;
        background: rgba(255,255,255,0.9);
        border-radius: 5px;
        padding: 5px;
    }
    </style>
</head>
<body>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar - Include your sidebar code here -->
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
    <div class="edit-dish-container">
        <!-- Current User Info -->
        <div class="current-user-info mb-4">
            <div class="row align-items-center">
                <div class="col-auto">
                    <i class="fas fa-user-circle fa-2x text-primary"></i>
                </div>
                <div class="col">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <strong>Logged in as:</strong> testt453
                        </div>
                        <div>
                            <i class="fas fa-clock me-2"></i>
                            2025-04-24 13:33:58 UTC
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2>
                    <i class="fas fa-edit me-2"></i>Edit Dish
                    <small class="text-muted">#<?php echo $dish_id; ?></small>
                </h2>
                <div class="last-updated">
                    <i class="fas fa-history me-2"></i>Last updated: 
                    <?php echo $dish['updated_at']; ?> UTC
                </div>
            </div>
            <a href="dishes.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-2"></i>Back to List
            </a>
        </div>

        <!-- Alert Messages -->
        <?php if($success_msg): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i><?php echo $success_msg; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <?php if($error_msg): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i><?php echo $error_msg; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Edit Form -->
        <div class="card form-card">
            <div class="card-body p-4">
                <form method="POST" enctype="multipart/form-data" id="editDishForm" class="needs-validation" novalidate>
                    <div class="row">
                        <div class="col-md-8">
                            <!-- Basic Information -->
                            <div class="mb-4">
                                <label for="name" class="form-label required-field">Dish Name</label>
                                <input type="text" class="form-control" id="name" name="name"    pattern="^[A-Za-z\s\-&.,'()]+$"
           title="Product name can only contain letters, spaces, and the following characters: - & . , ' ( )"
           oninput="this.value = this.value.replace(/[0-9]/g, '')"
                                       value="<?php echo htmlspecialchars($name); ?>" required>
                                <div class="invalid-feedback">
                                    Please enter dish name
                                </div>
                            </div>

                            <div class="mb-4">
                                <label for="description" class="form-label required-field">Description</label>
                                <textarea class="form-control" id="description" name="description" 
                                          rows="4" required><?php echo htmlspecialchars($description); ?></textarea>
                                <div class="invalid-feedback">
                                    Please enter dish description
                                </div>
                            </div>

                            <div class="mb-4">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-control" id="status" name="status">
                                    <option value="active" <?php echo $status == 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo $status == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                </select>
                            </div>

                            <!-- Recommended Items Selection -->
                            <div class="mb-4">
                                <label class="form-label required-field">Recommended Grocery Items</label>
                                <input type="text" class="form-control search-box mb-3" 
                                       id="itemSearch" placeholder="Search items...">
                                
                                <div class="recommended-items-container">
                                    <?php foreach($grouped_items as $category => $items): ?>
                                    <div class="category-section mb-4">
                                        <div class="category-header">
                                            <i class="fas fa-tags me-2"></i><?php echo ucwords(str_replace('-', ' ', $category)); ?>
                                        </div>
                                        <div class="row g-3 mt-2">
                                            <?php foreach($items as $item): ?>
                                            <div class="col-md-6 item-container">
                                                <div class="item-card p-3 <?php echo in_array($item['item_id'], $selected_items) ? 'selected' : ''; ?>">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" 
                                                               name="recommended_items[]" 
                                                               value="<?php echo $item['item_id']; ?>"
                                                               id="item_<?php echo $item['item_id']; ?>"
                                                               <?php echo in_array($item['item_id'], $selected_items) ? 'checked' : ''; ?>>
                                                        <label class="form-check-label" for="item_<?php echo $item['item_id']; ?>">
                                                            <strong><?php echo htmlspecialchars($item['name']); ?></strong>
                                                            <?php if($item['brand']): ?>
                                                            <small class="d-block text-muted">Brand: <?php echo htmlspecialchars($item['brand']); ?></small>
                                                            <?php endif; ?>
                                                            <small class="d-block text-success">
                                                                Rs.<?php echo number_format($item['price'], 2); ?> per <?php echo $item['unit']; ?>
                                                            </small>
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <!-- Image Upload -->
                            <div class="mb-4">
                                <label class="form-label">Dish Image</label>
                                <div class="preview-container mb-3">
                                    <img src="<?php echo $current_image ? '../assets/images/dishes/'.$current_image : '../assets/images/placeholder.png'; ?>" 
                                         alt="Preview" 
                                         id="imagePreview" 
                                         class="img-fluid rounded">
                                    <?php if($current_image): ?>
                                    <div class="preview-controls">
                                        <button type="button" class="btn btn-sm btn-danger remove-image">
                                            <i class="fas fa-times"></i> Remove
                                        </button>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <input type="file" class="form-control" id="image" name="image" 
                                       accept="image/png, image/jpeg, image/jpg">
                                <input type="hidden" name="remove_image" id="removeImage" value="0">
                                <div class="form-text text-muted">
                                    Max file size: 5MB. Allowed formats: JPG, JPEG, PNG
                                </div>
                            </div>

                            <!-- Selected Items Summary -->
                            <div class="card mt-4">
                                <div class="card-body">
                                    <h6 class="card-title">
                                        <i class="fas fa-shopping-basket me-2"></i>Selected Items
                                        <span class="badge bg-primary ms-2" id="selectedCount">0</span>
                                    </h6>
                                    <div id="selectedItemsSummary" class="mt-3">
                                        Loading...
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <hr class="my-4">

                    <!-- Form Actions -->
                    <div class="text-end">
                        <button type="button" class="btn btn-secondary me-2" id="resetForm">
                            <i class="fas fa-undo me-2"></i>Reset Changes
                        </button>
                        <button type="submit" name="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Last Edit Info -->
        <div class="card mt-4">
            <div class="card-body">
                <h6 class="card-title">
                    <i class="fas fa-history me-2"></i>Edit History
                </h6>
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <small class="text-muted">Created at:</small>
                        <div><?php echo $dish['created_at']; ?> UTC</div>
                    </div>
                    <div class="col-md-6">
                        <small class="text-muted">Last updated at:</small>
                        <div><?php echo $dish['updated_at']; ?> UTC</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Include the same JavaScript from add_dish.php with these modifications -->
<script>
// Add these modifications to handle image removal
document.querySelector('.remove-image')?.addEventListener('click', function() {
    if(confirm('Are you sure you want to remove the current image?')) {
        document.getElementById('removeImage').value = '1';
        document.getElementById('imagePreview').src = '../assets/images/placeholder.png';
        this.closest('.preview-controls').style.display = 'none';
    }
});

// Add form reset confirmation
document.getElementById('resetForm').addEventListener('click', function() {
    if(confirm('Are you sure you want to reset all changes?')) {
        location.reload();
    }
});

// Add unsaved changes warning
let formChanged = false;
document.getElementById('editDishForm').addEventListener('change', function() {
    formChanged = true;
});

window.addEventListener('beforeunload', function(e) {
    if(formChanged) {
        e.preventDefault();
        e.returnValue = '';
    }
});
// Image preview
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
document.querySelector('.remove-image')?.addEventListener('click', function() {
    const imageInput = document.getElementById('image');
    const preview = document.getElementById('imagePreview');
    
    imageInput.value = '';
    preview.src = '../assets/images/placeholder.png';
    this.style.display = 'none';
});

// Item search functionality
document.getElementById('itemSearch').addEventListener('input', function(e) {
    const searchTerm = e.target.value.toLowerCase();
    const items = document.querySelectorAll('.item-container');
    
    items.forEach(item => {
        const itemName = item.querySelector('label').textContent.toLowerCase();
        if(itemName.includes(searchTerm)) {
            item.style.display = '';
        } else {
            item.style.display = 'none';
        }
    });
});

// Update selected items summary
function updateSelectedItemsSummary() {
    const selectedItems = document.querySelectorAll('input[name="recommended_items[]"]:checked');
    const summaryDiv = document.getElementById('selectedItemsSummary');
    
    if(selectedItems.length === 0) {
        summaryDiv.innerHTML = 'No items selected';
        return;
    }
    
    let html = `<div class="list-group list-group-flush">`;
    selectedItems.forEach(item => {
        const label = document.querySelector(`label[for="${item.id}"]`).textContent;
        html += `
            <div class="list-group-item px-0 py-2">
                <i class="fas fa-check-circle text-success me-2"></i>${label}
            </div>
        `;
    });
    html += `</div>`;
    
    summaryDiv.innerHTML = html;
}

// Item selection handling
document.querySelectorAll('input[name="recommended_items[]"]').forEach(checkbox => {
    checkbox.addEventListener('change', function() {
        const card = this.closest('.item-card');
        if(this.checked) {
            card.classList.add('selected');
        } else {
            card.classList.remove('selected');
        }
        updateSelectedItemsSummary();
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
                
                // Check if at least one item is selected
                const selectedItems = document.querySelectorAll('input[name="recommended_items[]"]:checked');
                if(selectedItems.length === 0) {
                    event.preventDefault();
                    alert('Please select at least one recommended item');
                }
                
                form.classList.add('was-validated')
            }, false)
        })
})()

// Initialize image upload preview
document.getElementById('image').addEventListener('change', function() {
    previewImage(this);
});

// Form reset handling
document.querySelector('button[type="reset"]').addEventListener('click', function(e) {
    e.preventDefault();
    if(confirm('Are you sure you want to reset the form? All entered data will be lost.')) {
        document.getElementById('addDishForm').reset();
        document.getElementById('imagePreview').src = '../assets/images/placeholder.png';
        document.querySelector('.remove-image').style.display = 'none';
        document.querySelectorAll('.item-card').forEach(card => {
            card.classList.remove('selected');
        });
        updateSelectedItemsSummary();
        document.getElementById('addDishForm').classList.remove('was-validated');
    }
});

// Initialize page
document.addEventListener('DOMContentLoaded', function() {
    updateSelectedItemsSummary();
});
</script>

<?php include("../includes/footer.php"); ?>