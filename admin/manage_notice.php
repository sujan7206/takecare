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
$notice = ['id' => '', 'title' => '', 'description' => '', 'image' => '', 'pdf' => ''];
$error = '';

// Handle edit mode
if (isset($_GET['edit_id'])) {
    $edit_id = filter_input(INPUT_GET, 'edit_id', FILTER_VALIDATE_INT);
    if ($edit_id) {
        try {
            $stmt = $conn->prepare("SELECT * FROM notices WHERE id = :id");
            $stmt->execute(['id' => $edit_id]);
            $notice = $stmt->fetch();
            if ($notice) {
                $editMode = true;
            } else {
                $error = "Notice not found.";
            }
        } catch (PDOException $e) {
            $error = "Failed to fetch notice: " . $e->getMessage();
        }
    } else {
        $error = "Invalid notice ID.";
    }
}

// Handle form submission (insert or update)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $title = filter_input(INPUT_POST, 'title', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $imageName = $editMode ? $notice['image'] : '';
    $pdfName = $editMode ? $notice['pdf'] : '';

    // Validate input
    if (empty($title) || empty($description)) {
        $error = "Title and description are required.";
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
                } elseif ($editMode && !empty($notice['image']) && file_exists($uploadDir . $notice['image'])) {
                    unlink($uploadDir . $notice['image']);
                }
            } else {
                $error = "Invalid image file type or size exceeds 5MB.";
            }
        }

        // Upload PDF if provided
        if (!empty($_FILES['pdf']['name']) && $_FILES['pdf']['error'] === UPLOAD_ERR_OK) {
            if ($_FILES['pdf']['type'] === 'application/pdf' && $_FILES['pdf']['size'] <= 10 * 1024 * 1024) {
                $pdfName = uniqid() . '_' . preg_replace('/[^A-Za-z0-9._-]/', '_', $_FILES['pdf']['name']);
                $pdfPath = $uploadDir . $pdfName;
                if (!move_uploaded_file($_FILES['pdf']['tmp_name'], $pdfPath)) {
                    $error = "Failed to upload PDF.";
                } elseif ($editMode && !empty($notice['pdf']) && file_exists($uploadDir . $notice['pdf'])) {
                    unlink($uploadDir . $notice['pdf']);
                }
            } else {
                $error = "Invalid PDF file type or size exceeds 10MB.";
            }
        }

        // Insert or update data if no errors
        if (empty($error)) {
            try {
                if ($editMode) {
                    $stmt = $conn->prepare("UPDATE notices SET title = :title, description = :description, image = :image, pdf = :pdf WHERE id = :id");
                    $stmt->execute([
                        'title' => $title,
                        'description' => $description,
                        'image' => $imageName,
                        'pdf' => $pdfName,
                        'id' => $edit_id
                    ]);
                    header("Location: manage_notice.php?msg=success&action=edit");
                    exit();
                } else {
                    $stmt = $conn->prepare("INSERT INTO notices (title, description, image, pdf, created_at) VALUES (:title, :description, :image, :pdf, NOW())");
                    $stmt->execute([
                        'title' => $title,
                        'description' => $description,
                        'image' => $imageName,
                        'pdf' => $pdfName
                    ]);
                    header("Location: manage_notice.php?msg=success&action=add");
                    exit();
                }
            } catch (PDOException $e) {
                $error = "Failed to " . ($editMode ? "update" : "insert") . " notice: " . $e->getMessage();
            }
        }
    }
}

// Handle delete action
if (isset($_GET['delete_id'])) {
    $delete_id = filter_input(INPUT_GET, 'delete_id', FILTER_VALIDATE_INT);
    if ($delete_id) {
        try {
            // Fetch notice to get file paths
            $stmt = $conn->prepare("SELECT image, pdf FROM notices WHERE id = :id");
            $stmt->execute(['id' => $delete_id]);
            $notice = $stmt->fetch();
            if ($notice) {
                // Delete files
                if (!empty($notice['image']) && file_exists($uploadDir . $notice['image'])) {
                    unlink($uploadDir . $notice['image']);
                }
                if (!empty($notice['pdf']) && file_exists($uploadDir . $notice['pdf'])) {
                    unlink($uploadDir . $notice['pdf']);
                }
                // Delete notice from database
                $stmt = $conn->prepare("DELETE FROM notices WHERE id = :id");
                $stmt->execute(['id' => $delete_id]);
                header("Location: manage_notice.php?msg=deleted");
                exit();
            } else {
                $error = "Notice not found.";
            }
        } catch (PDOException $e) {
            $error = "Failed to delete notice: " . $e->getMessage();
        }
    } else {
        $error = "Invalid notice ID.";
    }
}

// Fetch all notices
try {
    $stmt = $conn->query("SELECT * FROM notices ORDER BY created_at DESC");
    $notices = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Failed to fetch notices: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Notices - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet" integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous" referrerpolicy="no-referrer">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .main-content {
            margin-left: 260px; /* Adjust based on sidebar width */
            padding: 20px;
        }
        @media (max-width: 992px) {
            .main-content {
                margin-left: 0;
            }
        }
        .card-header {
            background-color: #6f42c1;
            color: white;
        }
        .btn-purple {
            background-color: #6f42c1;
            color: white;
            border: none;
        }
        .btn-purple:hover {
            background-color: #5a32a3;
        }
        .form-control:focus {
            box-shadow: 0 0 0 0.2rem rgba(111, 66, 193, 0.25);
            border-color: #6f42c1;
        }
        .notice-card {
            border-radius: 12px;
            transition: transform 0.2s ease;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        .notice-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.12);
        }
        .error-message {
            color: #dc3545;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    <div class="container-fluid">
        <div class="row">
            <?php include 'sidebar.php'; ?>
            <main class="main-content col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <h2 class="mb-4 text-center text-primary fw-bold">Manage Health Notices</h2>

                <!-- Messages -->
                <?php if (isset($_GET['msg']) && $_GET['msg'] === 'success'): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        Notice <?php echo isset($_GET['action']) && $_GET['action'] === 'edit' ? 'updated' : 'added'; ?> successfully!
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php elseif (isset($_GET['msg']) && $_GET['msg'] === 'deleted'): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        Notice deleted successfully!
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php elseif (isset($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <!-- Notice Form (Add/Edit) -->
                <div class="card mb-5 shadow-sm">
                    <div class="card-header"><?php echo $editMode ? 'Edit Notice' : 'Add New Notice'; ?></div>
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data" novalidate>
                            <input type="hidden" name="action" value="<?php echo $editMode ? 'edit' : 'add'; ?>">
                            <?php if ($editMode): ?>
                                <input type="hidden" name="id" value="<?php echo $notice['id']; ?>">
                            <?php endif; ?>
                            <div class="mb-3">
                                <label for="title" class="form-label">Notice Title <span class="text-danger">*</span></label>
                                <input type="text" id="title" name="title" class="form-control" required maxlength="255" value="<?php echo htmlspecialchars($notice['title']); ?>" />
                            </div>
                            <div class="mb-3">
                                <label for="description" class="form-label">Notice Description <span class="text-danger">*</span></label>
                                <textarea id="description" name="description" rows="4" class="form-control" required><?php echo htmlspecialchars($notice['description']); ?></textarea>
                            </div>
                            <div class="mb-3">
                                <label for="image" class="form-label">Upload Image (optional, JPEG/PNG/GIF, max 5MB)</label>
                                <input type="file" id="image" name="image" accept="image/jpeg,image/png,image/gif" class="form-control" />
                                <?php if ($editMode && !empty($notice['image']) && file_exists($uploadDir . $notice['image'])): ?>
                                    <small class="form-text text-muted">Current: <a href="/takecare/uploads/<?php echo rawurlencode($notice['image']); ?>" target="_blank"><?php echo htmlspecialchars($notice['image']); ?></a></small>
                                <?php endif; ?>
                            </div>
                            <div class="mb-3">
                                <label for="pdf" class="form-label">Upload PDF (optional, max 10MB)</label>
                                <input type="file" id="pdf" name="pdf" accept="application/pdf" class="form-control" />
                                <?php if ($editMode && !empty($notice['pdf']) && file_exists($uploadDir . $notice['pdf'])): ?>
                                    <small class="form-text text-muted">Current: <a href="/takecare/uploads/<?php echo rawurlencode($notice['pdf']); ?>" target="_blank"><?php echo htmlspecialchars($notice['pdf']); ?></a></small>
                                <?php endif; ?>
                            </div>
                            <button type="submit" class="btn btn-purple"><?php echo $editMode ? 'Update Notice' : 'Add Notice'; ?></button>
                            <?php if ($editMode): ?>
                                <a href="manage_notice.php" class="btn btn-secondary">Cancel</a>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>

                <!-- Notices Grid -->
                <div class="row g-4">
                    <?php if (!empty($notices)): ?>
                        <?php foreach ($notices as $row): ?>
                            <div class="col-md-6 col-lg-4">
                                <div class="card notice-card h-100 shadow-sm">
                                    <?php if (!empty($row['image']) && file_exists($uploadDir . $row['image'])): ?>
                                        <img src="/takecare/uploads/<?php echo htmlspecialchars($row['image']); ?>" alt="Notice Image" class="card-img-top" style="height: 200px; object-fit: cover;" />
                                    <?php endif; ?>
                                    <div class="card-body">
                                        <h5 class="card-title"><?php echo htmlspecialchars($row['title']); ?></h5>
                                        <p class="card-text"><?php echo nl2br(htmlspecialchars($row['description'])); ?></p>
                                    </div>
                                    <div class="card-footer d-flex justify-content-between align-items-center">
                                        <small class="text-muted"><?php echo date('F j, Y', strtotime($row['created_at'])); ?></small>
                                        <div class="d-flex gap-2">
                                            <?php if (!empty($row['pdf']) && file_exists($uploadDir . $row['pdf'])): ?>
                                                <a href="/takecare/uploads/<?php echo rawurlencode($row['pdf']); ?>" target="_blank" class="btn btn-sm btn-purple" title="View PDF">
                                                    <i class="fas fa-file-pdf"></i>
                                                </a>
                                            <?php endif; ?>
                                            <a href="manage_notice.php?edit_id=<?php echo $row['id']; ?>" class="btn btn-sm btn-warning" title="Edit Notice">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="manage_notice.php?delete_id=<?php echo $row['id']; ?>" onclick="return confirm('Are you sure you want to delete this notice?');" class="btn btn-sm btn-danger" title="Delete Notice">
                                                <i class="fas fa-trash-alt"></i>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-center text-muted">No notices added yet.</p>
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
            const title = document.getElementById('title').value.trim();
            const description = document.getElementById('description').value.trim();
            const pdf = document.getElementById('pdf').files[0];
            let errors = [];

            if (!title) errors.push('Title is required.');
            if (!description) errors.push('Description is required.');
            if (pdf && /[^A-Za-z0-9._-]/.test(pdf.name)) {
                errors.push('PDF filename contains invalid characters. Use letters, numbers, underscores, hyphens, or periods only.');
            }

            if (errors.length > 0) {
                e.preventDefault();
                alert(errors.join('\n'));
            }
        });
    </script>
</body>
</html>