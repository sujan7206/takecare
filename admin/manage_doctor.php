<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: login.php");
    exit();
}

// Database connection
$host = 'localhost';
$db   = 'takecare_db';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

try {
    $conn = new PDO("mysql:host=$host;dbname=$db;charset=$charset", $user, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error: Database connection failed: " . $e->getMessage());
}

// Define uploads directory
$uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/takecare/uploads/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Initialize variables
$editMode = false;
$doctor = [
    'id' => '',
    'name' => '',
    'specialty' => '',
    'location' => '',
    'phone' => '',
    'email' => '',
    'about' => '',
    'education' => [],
    'specializations' => [],
    'languages' => '',
    'image' => ''
];
$error = '';

// Handle edit mode
if (isset($_GET['edit_id'])) {
    $edit_id = filter_input(INPUT_GET, 'edit_id', FILTER_VALIDATE_INT);
    if ($edit_id) {
        try {
            $stmt = $conn->prepare("SELECT * FROM doctors WHERE id = :id");
            $stmt->execute(['id' => $edit_id]);
            $doctor = $stmt->fetch();
            if ($doctor) {
                $editMode = true;
                $doctor['education'] = json_decode($doctor['education'], true) ?? [];
                $doctor['specializations'] = json_decode($doctor['specializations'], true) ?? [];
            } else {
                $error = "Doctor not found.";
            }
        } catch (PDOException $e) {
            $error = "Failed to fetch doctor: " . $e->getMessage();
        }
    } else {
        $error = "Invalid doctor ID.";
    }
}

// Handle form submission (insert or update)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $specialty = filter_input(INPUT_POST, 'specialty', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $location = filter_input(INPUT_POST, 'location', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $phone = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $about = filter_input(INPUT_POST, 'about', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $education = array_filter($_POST['education'] ?? [], 'trim');
    $specializations = array_filter($_POST['specializations'] ?? [], 'trim');
    $languages = filter_input(INPUT_POST, 'languages', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $imageName = $editMode ? $doctor['image'] : '';

    // Validate input
    if (empty($name) || empty($specialty) || empty($location) || empty($phone) || empty($email)) {
        $error = "Name, specialty, location, phone, and email are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } else {
        // Ensure uploads directory is writable
        if (!is_writable($uploadDir)) {
            $error = "Uploads directory '$uploadDir' is not writable.";
        }

        // Upload image if provided
        if (!empty($_FILES['image']['name']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $allowedImageTypes = ['image/jpeg', 'image/png', 'image/gif'];
            if (in_array($_FILES['image']['type'], $allowedImageTypes) && $_FILES['image']['size'] <= 5 * 1024 * 1024) {
                $imageName = uniqid() . '_' . preg_replace('/[^A-Za-z0-9._-]/', '_', $_FILES['image']['name']);
                $imagePath = $uploadDir . $imageName;
                if (!move_uploaded_file($_FILES['image']['tmp_name'], $imagePath)) {
                    $error = "Failed to upload image.";
                } elseif ($editMode && !empty($doctor['image']) && file_exists($uploadDir . $doctor['image'])) {
                    unlink($uploadDir . $doctor['image']);
                }
            } else {
                $error = "Invalid image file type or size exceeds 5MB.";
            }
        }

        // Insert or update data if no errors
        if (empty($error)) {
            try {
                $educationJson = json_encode($education);
                $specializationsJson = json_encode($specializations);
                if ($editMode) {
                    $stmt = $conn->prepare("UPDATE doctors SET name = :name, specialty = :specialty, location = :location, phone = :phone, email = :email, about = :about, education = :education, specializations = :specializations, languages = :languages, image = :image WHERE id = :id");
                    $stmt->execute([
                        'name' => $name,
                        'specialty' => $specialty,
                        'location' => $location,
                        'phone' => $phone,
                        'email' => $email,
                        'about' => $about,
                        'education' => $educationJson,
                        'specializations' => $specializationsJson,
                        'languages' => $languages,
                        'image' => $imageName,
                        'id' => $edit_id
                    ]);
                    header("Location: manage_doctor.php?msg=success&action=edit");
                    exit();
                } else {
                    $stmt = $conn->prepare("INSERT INTO doctors (name, specialty, location, phone, email, about, education, specializations, languages, image, created_at) VALUES (:name, :specialty, :location, :phone, :email, :about, :education, :specializations, :languages, :image, NOW())");
                    $stmt->execute([
                        'name' => $name,
                        'specialty' => $specialty,
                        'location' => $location,
                        'phone' => $phone,
                        'email' => $email,
                        'about' => $about,
                        'education' => $educationJson,
                        'specializations' => $specializationsJson,
                        'languages' => $languages,
                        'image' => $imageName
                    ]);
                    header("Location: manage_doctor.php?msg=success&action=add");
                    exit();
                }
            } catch (PDOException $e) {
                $error = "Failed to " . ($editMode ? "update" : "insert") . " doctor: " . $e->getMessage();
            }
        }
    }
}

// Handle delete action
if (isset($_GET['delete_id'])) {
    $delete_id = filter_input(INPUT_GET, 'delete_id', FILTER_VALIDATE_INT);
    if ($delete_id) {
        try {
            $stmt = $conn->prepare("SELECT image FROM doctors WHERE id = :id");
            $stmt->execute(['id' => $delete_id]);
            $doctor = $stmt->fetch();
            if ($doctor) {
                if (!empty($doctor['image']) && file_exists($uploadDir . $doctor['image'])) {
                    unlink($uploadDir . $doctor['image']);
                }
                $stmt = $conn->prepare("DELETE FROM doctors WHERE id = :id");
                $stmt->execute(['id' => $delete_id]);
                header("Location: manage_doctor.php?msg=deleted");
                exit();
            } else {
                $error = "Doctor not found.";
            }
        } catch (PDOException $e) {
            $error = "Failed to delete doctor: " . $e->getMessage();
        }
    } else {
        $error = "Invalid doctor ID.";
    }
}

// Fetch all doctors
try {
    $stmt = $conn->query("SELECT * FROM doctors ORDER BY created_at DESC");
    $doctors = $stmt->fetchAll();
    foreach ($doctors as &$doc) {
        $doc['education'] = json_decode($doc['education'], true) ?? [];
        $doc['specializations'] = json_decode($doc['specializations'], true) ?? [];
    }
} catch (PDOException $e) {
    $error = "Failed to fetch doctors: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Doctors - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet" integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous" referrerpolicy="no-referrer">
    <style>
        :root {
            --dark-blue: #1A2B40;
            --purple: #8A2BE2;
            --light-gray: #F0F0F0;
            --text-color: #333333;
        }

        body {
            background: linear-gradient(135deg, #f0f7ff, #e1ecfe);
            color: var(--text-color);
            line-height: 1.6;
        }

        .main-content {
            margin-left: 250px;
            padding: 20px;
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0 !important;
            }
        }

        .card-header {
            background: linear-gradient(to right, #1a73e8, #0d47a1);
            color: white;
        }

        .btn-purple {
            background: linear-gradient(to right, #1a73e8, #0d47a1);
            color: white;
            border: none;
        }

        .btn-purple:hover {
            background: linear-gradient(to right, #0d47a1, #1a73e8);
            box-shadow: 0 5px 15px rgba(26, 115, 232, 0.3);
        }

        .form-control:focus, .form-select:focus {
            box-shadow: 0 0 0 0.2rem rgba(26, 115, 232, 0.25);
            border-color: #1a73e8;
        }

        .doctor-card {
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.06);
            transition: all 0.3s;
        }

        .doctor-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }

        .doctor-img {
            height: 180px;
            overflow: hidden;
        }

        .doctor-img img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .section-title {
            text-align: center;
            color: #0d47a1;
            font-size: 1.8rem;
            position: relative;
            padding-bottom: 15px;
            margin-bottom: 25px;
        }

        .section-title::after {
            content: '';
            display: block;
            width: 60px;
            height: 4px;
            background: linear-gradient(to right, #1a73e8, #0d47a1);
            margin: 10px auto 0;
            border-radius: 2px;
        }

        .error-message {
            color: #dc3545;
            font-size: 0.9rem;
        }

        /* Dynamic input fields */
        .dynamic-input-group {
            display: flex;
            gap: 10px;
            margin-bottom: 10px;
        }

        .dynamic-input {
            flex-grow: 1;
        }

        .btn-remove {
            background-color: #dc3545;
            color: white;
            border: none;
            padding: 8px;
            border-radius: 5px;
        }

        .btn-remove:hover {
            background-color: #c82333;
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    <div class="container-fluid">
        <div class="row">
            <?php include 'sidebar.php'; ?>
            <main class="main-content col-md-9 col-lg-10 px-md-4">
                <h2 class="section-title">Manage Doctors</h2>

                <!-- Messages -->
                <?php if (isset($_GET['msg']) && $_GET['msg'] === 'success'): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        Doctor <?php echo isset($_GET['action']) && $_GET['action'] === 'edit' ? 'updated' : 'added'; ?> successfully!
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php elseif (isset($_GET['msg']) && $_GET['msg'] === 'deleted'): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        Doctor deleted successfully!
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php elseif (isset($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <!-- Doctor Form (Add/Edit) -->
                <div class="card mb-5 shadow-sm">
                    <div class="card-header"><?php echo $editMode ? 'Edit Doctor' : 'Add New Doctor'; ?></div>
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data" novalidate>
                            <input type="hidden" name="action" value="<?php echo $editMode ? 'edit' : 'add'; ?>">
                            <?php if ($editMode): ?>
                                <input type="hidden" name="id" value="<?php echo $doctor['id']; ?>">
                            <?php endif; ?>
                            <div class="mb-3">
                                <label for="name" class="form-label">Doctor Name <span class="text-danger">*</span></label>
                                <input type="text" id="name" name="name" class="form-control" required maxlength="255" value="<?php echo htmlspecialchars($doctor['name']); ?>">
                            </div>
                            <div class="mb-3">
                                <label for="specialty" class="form-label">Specialty <span class="text-danger">*</span></label>
                                <select id="specialty" name="specialty" class="form-select" required>
                                    <option value="" <?php echo $doctor['specialty'] === '' ? 'selected' : ''; ?>>Select Specialty</option>
                                    <option value="Cardiologist" <?php echo $doctor['specialty'] === 'Cardiologist' ? 'selected' : ''; ?>>Cardiologist</option>
                                    <option value="Neurologist" <?php echo $doctor['specialty'] === 'Neurologist' ? 'selected' : ''; ?>>Neurologist</option>
                                    <option value="Pediatrician" <?php echo $doctor['specialty'] === 'Pediatrician' ? 'selected' : ''; ?>>Pediatrician</option>
                                    <option value="Dermatologist" <?php echo $doctor['specialty'] === 'Dermatologist' ? 'selected' : ''; ?>>Dermatologist</option>
                                    <option value="Orthopedic Surgeon" <?php echo $doctor['specialty'] === 'Orthopedic Surgeon' ? 'selected' : ''; ?>>Orthopedic Surgeon</option>
                                    <option value="Ophthalmologist" <?php echo $doctor['specialty'] === 'Ophthalmologist' ? 'selected' : ''; ?>>Ophthalmologist</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="location" class="form-label">Location <span class="text-danger">*</span></label>
                                <input type="text" id="location" name="location" class="form-control" required maxlength="255" value="<?php echo htmlspecialchars($doctor['location']); ?>">
                            </div>
                            <div class="mb-3">
                                <label for="phone" class="form-label">Phone <span class="text-danger">*</span></label>
                                <input type="text" id="phone" name="phone" class="form-control" required maxlength="20" value="<?php echo htmlspecialchars($doctor['phone']); ?>">
                            </div>
                            <div class="mb-3">
                                <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                                <input type="email" id="email" name="email" class="form-control" required maxlength="255" value="<?php echo htmlspecialchars($doctor['email']); ?>">
                            </div>
                            <div class="mb-3">
                                <label for="about" class="form-label">About</label>
                                <textarea id="about" name="about" rows="4" class="form-control"><?php echo htmlspecialchars($doctor['about']); ?></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Education</label>
                                <div id="education-fields">
                                    <?php foreach ($doctor['education'] as $index => $edu): ?>
                                        <div class="dynamic-input-group">
                                            <input type="text" name="education[]" class="form-control dynamic-input" value="<?php echo htmlspecialchars($edu); ?>">
                                            <button type="button" class="btn btn-remove" onclick="removeInput(this)"><i class="fas fa-trash"></i></button>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <button type="button" class="btn btn-outline-primary mt-2" onclick="addInput('education-fields')"><i class="fas fa-plus"></i> Add Education</button>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Specializations</label>
                                <div id="specialization-fields">
                                    <?php foreach ($doctor['specializations'] as $index => $spec): ?>
                                        <div class="dynamic-input-group">
                                            <input type="text" name="specializations[]" class="form-control dynamic-input" value="<?php echo htmlspecialchars($spec); ?>">
                                            <button type="button" class="btn btn-remove" onclick="removeInput(this)"><i class="fas fa-trash"></i></button>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <button type="button" class="btn btn-outline-primary mt-2" onclick="addInput('specialization-fields')"><i class="fas fa-plus"></i> Add Specialization</button>
                            </div>
                            <div class="mb-3">
                                <label for="languages" class="form-label">Languages</label>
                                <input type="text" id="languages" name="languages" class="form-control" placeholder="e.g., English, Spanish" value="<?php echo htmlspecialchars($doctor['languages']); ?>">
                            </div>
                            <div class="mb-3">
                                <label for="image" class="form-label">Upload Image (optional, JPEG/PNG/GIF, max 5MB)</label>
                                <input type="file" id="image" name="image" accept="image/jpeg,image/png,image/gif" class="form-control">
                                <?php if ($editMode && !empty($doctor['image']) && file_exists($uploadDir . $doctor['image'])): ?>
                                    <small class="form-text text-muted">Current: <a href="/takecare/uploads/<?php echo rawurlencode($doctor['image']); ?>" target="_blank"><?php echo htmlspecialchars($doctor['image']); ?></a></small>
                                <?php endif; ?>
                            </div>
                            <button type="submit" class="btn btn-purple"><?php echo $editMode ? 'Update Doctor' : 'Add Doctor'; ?></button>
                            <?php if ($editMode): ?>
                                <a href="manage_doctor.php" class="btn btn-outline-secondary ms-2">Cancel</a>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>

                <!-- Doctors Grid -->
                <h2 class="section-title">All Doctors</h2>
                <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
                    <?php if (!empty($doctors)): ?>
                        <?php foreach ($doctors as $row): ?>
                            <div class="col">
                                <div class="card doctor-card h-100">
                                    <?php if (!empty($row['image']) && file_exists($uploadDir . $row['image'])): ?>
                                        <div class="doctor-img">
                                            <img src="/takecare/uploads/<?php echo htmlspecialchars($row['image']); ?>" alt="<?php echo htmlspecialchars($row['name']); ?>" class="img-fluid">
                                        </div>
                                    <?php endif; ?>
                                    <div class="card-body">
                                        <h5 class="card-title text-primary"><?php echo htmlspecialchars($row['name']); ?></h5>
                                        <p class="card-text text-info"><?php echo htmlspecialchars($row['specialty']); ?></p>
                                        <p class="card-text"><i class="fas fa-map-marker-alt me-2 text-info"></i><?php echo htmlspecialchars($row['location']); ?></p>
                                        <p class="card-text"><i class="fas fa-phone me-2 text-info"></i><?php echo htmlspecialchars($row['phone']); ?></p>
                                        <p class="card-text"><i class="fas fa-envelope me-2 text-info"></i><?php echo htmlspecialchars($row['email']); ?></p>
                                    </div>
                                    <div class="card-footer d-flex justify-content-between align-items-center">
                                        <small class="text-muted"><?php echo date('F j, Y', strtotime($row['created_at'])); ?></small>
                                        <div class="d-flex gap-2">
                                            <a href="manage_doctor.php?edit_id=<?php echo $row['id']; ?>" class="btn btn-sm btn-warning" title="Edit Doctor">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="manage_doctor.php?delete_id=<?php echo $row['id']; ?>" onclick="return confirm('Are you sure you want to delete this doctor?');" class="btn btn-sm btn-danger" title="Delete Doctor">
                                                <i class="fas fa-trash-alt"></i>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-center text-muted">No doctors added yet.</p>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js" integrity="sha512-fD9DI5bZwQxOi7MhYWnnNPlvXdp/2Pj3XSTRrFs5FQa4mizyGLnJcN6tuvUS6LbmgN1ut+XGSABKvjN0H6Aoow==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script>
        // Client-side form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const name = document.getElementById('name').value.trim();
            const specialty = document.getElementById('specialty').value;
            const location = document.getElementById('location').value.trim();
            const phone = document.getElementById('phone').value.trim();
            const email = document.getElementById('email').value.trim();
            const image = document.getElementById('image').files[0];
            let errors = [];

            if (!name) errors.push('Name is required.');
            if (!specialty) errors.push('Specialty is required.');
            if (!location) errors.push('Location is required.');
            if (!phone) errors.push('Phone is required.');
            if (!email) errors.push('Email is required.');
            if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) errors.push('Invalid email format.');
            if (image && /[^A-Za-z0-9._-]/.test(image.name)) {
                errors.push('Image filename contains invalid characters. Use letters, numbers, underscores, hyphens, or periods only.');
            }

            if (errors.length > 0) {
                e.preventDefault();
                alert(errors.join('\n'));
            }
        });

        // Dynamic input fields for education and specializations
        function addInput(containerId) {
            const container = document.getElementById(containerId);
            const inputGroup = document.createElement('div');
            inputGroup.className = 'dynamic-input-group';
            inputGroup.innerHTML = `
                <input type="text" name="${containerId === 'education-fields' ? 'education' : 'specializations'}[]" class="form-control dynamic-input">
                <button type="button" class="btn btn-remove" onclick="removeInput(this)"><i class="fas fa-trash"></i></button>
            `;
            container.appendChild(inputGroup);
        }

        function removeInput(button) {
            button.parentElement.remove();
        }
    </script>
</body>
</html>