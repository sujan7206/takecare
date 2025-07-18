<?php
if (session_status() === PHP_SESSION_NONE) session_start();


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

// Initialize variables
$blogs = [];
$message = '';
$error = '';
$filter_status = '';

// Fetch all blogs with optional status filter
try {
    $query = "SELECT id, title, content, image, status, created_at FROM blogs WHERE 1=1";
    $params = [];
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['filter'])) {
        if ($_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            $error = "Invalid CSRF token.";
        } else {
            $filter_status = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_STRING) ?: '';
            if ($filter_status) {
                $query .= " AND status = :status";
                $params['status'] = $filter_status;
            }
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
    }
    $query .= " ORDER BY created_at DESC";
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $blogs = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Failed to fetch blogs: " . $e->getMessage();
}

// Handle add blog
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_blog'])) {
    if ($_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Invalid CSRF token.";
    } else {
        $title = filter_input(INPUT_POST, 'title', FILTER_SANITIZE_STRING);
        $content = filter_input(INPUT_POST, 'content', FILTER_SANITIZE_STRING);
        $status = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_STRING) ?: 'draft';
        $image = null;

        if (!$title || !$content) {
            $error = "Title and content are required.";
        } else {
            // Handle image upload
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
                $max_size = 5 * 1024 * 1024; // 5MB
                $file_type = mime_content_type($_FILES['image']['tmp_name']);
                $file_size = $_FILES['image']['size'];
                $file_ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
                $file_name = uniqid() . '.' . $file_ext;
                $upload_dir = 'C:/xampp/htdocs/takecare/uploads/';
                $upload_path = $upload_dir . $file_name;

                if (!in_array($file_type, $allowed_types)) {
                    $error = "Only JPEG, PNG, and GIF images are allowed.";
                } elseif ($file_size > $max_size) {
                    $error = "Image size exceeds 5MB.";
                } elseif (!move_uploaded_file($_FILES['image']['tmp_name'], $upload_path)) {
                    $error = "Failed to upload image.";
                } else {
                    $image = $file_name;
                }
            }

            if (!$error) {
                try {
                    $stmt = $conn->prepare("INSERT INTO blogs (title, content, image, status) VALUES (:title, :content, :image, :status)");
                    $stmt->execute([
                        'title' => $title,
                        'content' => $content,
                        'image' => $image,
                        'status' => $status
                    ]);
                    $message = "Blog added successfully!";
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                } catch (PDOException $e) {
                    $error = "Failed to add blog: " . $e->getMessage();
                }
            }
        }
    }
}

// Handle update blog
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_blog'])) {
    if ($_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Invalid CSRF token.";
    } else {
        $blog_id = filter_input(INPUT_POST, 'blog_id', FILTER_VALIDATE_INT);
        $title = filter_input(INPUT_POST, 'title', FILTER_SANITIZE_STRING);
        $content = filter_input(INPUT_POST, 'content', FILTER_SANITIZE_STRING);
        $status = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_STRING);

        if (!$blog_id || !$title || !$content || !$status) {
            $error = "All fields are required.";
        } else {
            // Handle image upload
            $image = null;
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
                $max_size = 5 * 1024 * 1024; // 5MB
                $file_type = mime_content_type($_FILES['image']['tmp_name']);
                $file_size = $_FILES['image']['size'];
                $file_ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
                $file_name = uniqid() . '.' . $file_ext;
                $upload_dir = 'C:/xampp/htdocs/takecare/uploads/';
                $upload_path = $upload_dir . $file_name;

                if (!in_array($file_type, $allowed_types)) {
                    $error = "Only JPEG, PNG, and GIF images are allowed.";
                } elseif ($file_size > $max_size) {
                    $error = "Image size exceeds 5MB.";
                } elseif (!move_uploaded_file($_FILES['image']['tmp_name'], $upload_path)) {
                    $error = "Failed to upload image.";
                } else {
                    $image = $file_name;
                    // Delete old image
                    $stmt = $conn->prepare("SELECT image FROM blogs WHERE id = :id");
                    $stmt->execute(['id' => $blog_id]);
                    $old_image = $stmt->fetchColumn();
                    if ($old_image && file_exists($upload_dir . $old_image)) {
                        unlink($upload_dir . $old_image);
                    }
                }
            }

            if (!$error) {
                try {
                    $query = "UPDATE blogs SET title = :title, content = :content, status = :status";
                    if ($image) {
                        $query .= ", image = :image";
                    }
                    $query .= " WHERE id = :id";
                    $stmt = $conn->prepare($query);
                    $params = [
                        'id' => $blog_id,
                        'title' => $title,
                        'content' => $content,
                        'status' => $status
                    ];
                    if ($image) {
                        $params['image'] = $image;
                    }
                    $stmt->execute($params);
                    $message = "Blog updated successfully!";
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                } catch (PDOException $e) {
                    $error = "Failed to update blog: " . $e->getMessage();
                }
            }
        }
    }
}

// Handle delete blog
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_blog'])) {
    if ($_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Invalid CSRF token.";
    } else {
        $blog_id = filter_input(INPUT_POST, 'blog_id', FILTER_VALIDATE_INT);
        if ($blog_id) {
            try {
                // Delete image
                $stmt = $conn->prepare("SELECT image FROM blogs WHERE id = :id");
                $stmt->execute(['id' => $blog_id]);
                $image = $stmt->fetchColumn();
                if ($image && file_exists('C:/xampp/htdocs/takecare/uploads/' . $image)) {
                    unlink('C:/xampp/htdocs/takecare/uploads/' . $image);
                }
                // Delete blog
                $stmt = $conn->prepare("DELETE FROM blogs WHERE id = :id");
                $stmt->execute(['id' => $blog_id]);
                $message = "Blog deleted successfully!";
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            } catch (PDOException $e) {
                $error = "Failed to delete blog: " . $e->getMessage();
            }
        } else {
            $error = "Invalid blog ID.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Blogs | Takecare Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet" integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous" referrerpolicy="no-referrer">
    <style>
        :root {
            --primary-color: #6f42c1;
            --dark-primary: #5a32a3;
            --blue-gradient-start: #1a73e8;
            --blue-gradient-end: #0d47a1;
            --text-dark: #333333;
            --white: #ffffff;
            --light-gray: #f0f7ff;
            --light-gray-end: #e1ecfe;
        }

        body {
            background: linear-gradient(135deg, var(--light-gray), var(--light-gray-end));
            color: var(--text-dark);
            line-height: 1.6;
            padding-top: 70px;
            margin-left: 200px;
            transition: margin-left 0.3s ease;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .container-fluid {
            padding: 20px 30px;
            margin-left: 0;
            transition: margin-left 0.3s ease;
        }

        .page-header {
            text-align: center;
            margin-bottom: 30px;
            padding: 20px;
        }

        .page-title {
            font-size: 2.2rem;
            color: var(--blue-gradient-end);
            margin-bottom: 15px;
        }

        .subtitle {
            color: #555;
            font-size: 1.1rem;
            max-width: 700px;
            margin: 0 auto 30px;
        }

        .table {
            background: var(--white);
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.06);
            overflow: hidden;
        }

        .table th, .table td {
            vertical-align: middle;
            padding: 12px;
        }

        .table th {
            background: linear-gradient(to right, var(--blue-gradient-start), var(--blue-gradient-end));
            color: var(--white);
        }

        .table tbody tr {
            transition: background-color 0.3s ease, transform 0.2s ease;
        }

        .table tbody tr:hover {
            background-color: #f8f9fa;
            transform: translateY(-2px);
        }

        .btn-primary, .btn-outline-primary, .btn-danger, .btn-success {
            font-weight: 600;
            padding: 8px 15px;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: linear-gradient(to right, var(--blue-gradient-start), var(--blue-gradient-end));
            border: none;
        }

        .btn-primary:hover {
            background: linear-gradient(to right, var(--blue-gradient-end), var(--blue-gradient-start));
            box-shadow: 0 5px 15px rgba(26, 115, 232, 0.3);
        }

        .btn-outline-primary {
            background: transparent;
            border: 2px solid var(--blue-gradient-start);
            color: var(--blue-gradient-start);
        }

        .btn-outline-primary:hover {
            background: linear-gradient(to right, var(--blue-gradient-end), var(--blue-gradient-start));
            color: var(--white);
        }

        .btn-danger {
            background: linear-gradient(to right, #dc3545, #a71d2a);
            border: none;
        }

        .btn-danger:hover {
            background: linear-gradient(to right, #a71d2a, #dc3545);
        }

        .btn-success {
            background: linear-gradient(to right, #28a745, #1e7e34);
            border: none;
        }

        .btn-success:hover {
            background: linear-gradient(to right, #1e7e34, #28a745);
        }

        .success-message {
            color: #2e7d32;
            font-size: 1.1rem;
            text-align: center;
            margin-bottom: 20px;
            animation: fadeIn 0.3s ease;
        }

        .error-message {
            color: #dc3545;
            font-size: 1.1rem;
            text-align: center;
            margin-bottom: 20px;
            animation: fadeIn 0.3s ease;
        }

        .modal-content {
            border-radius: 10px;
            border: none;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .modal-header {
            background: linear-gradient(to right, var(--blue-gradient-start), var(--blue-gradient-end));
            color: var(--white);
            border-radius: 10px 10px 0 0;
            border-bottom: none;
        }

        .modal-title {
            font-weight: 600;
        }

        .modal-body {
            padding: 20px;
        }

        .modal-footer {
            border-top: none;
            padding: 15px 20px;
        }

        .form-control:focus {
            border-color: var(--blue-gradient-start);
            box-shadow: 0 0 5px rgba(26, 115, 232, 0.3);
        }

        .filter-form {
            background: var(--white);
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.06);
            margin-bottom: 30px;
        }

        .sidebar-toggle {
            position: fixed;
            top: 80px;
            left: 210px;
            z-index: 1001;
            background: var(--primary-color);
            color: var(--white);
            border: none;
            padding: 10px;
            border-radius: 5px;
            cursor: pointer;
            transition: background 0.3s ease, left 0.3s ease;
        }

        .sidebar-toggle.hidden {
            left: 10px;
        }

        .sidebar-toggle:hover {
            background: var(--dark-primary);
        }

        .sidebar-hidden {
            margin-left: 0 !important;
        }

        .blog-image {
            max-width: 100px;
            height: auto;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @media (max-width: 768px) {
            body {
                margin-left: 0;
            }

            .sidebar-toggle {
                top: 70px;
                left: 10px;
            }

            .container-fluid {
                padding: 15px;
            }

            .page-title {
                font-size: 1.8rem;
            }

            .table th, .table td {
                font-size: 0.9rem;
                padding: 8px;
            }

            .filter-form {
                padding: 15px;
            }
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    <?php include 'sidebar.php'; ?>
    <button class="sidebar-toggle" id="sidebarToggle"><i class="fas fa-xmark"></i></button>
    <div class="container-fluid">
        <div class="page-header">
            <h1 class="page-title">Manage Blogs</h1>
            <p class="subtitle">Create, update, or delete blog posts</p>
        </div>

        <?php if ($message): ?>
            <p class="success-message"><?php echo htmlspecialchars($message); ?></p>
        <?php endif; ?>
        <?php if ($error): ?>
            <p class="error-message"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>

        <!-- Add Blog Button -->
        <div class="mb-3">
            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addBlogModal">
                <i class="fas fa-plus"></i> Add Blog
            </button>
        </div>

        <!-- Filter Form -->
        <div class="filter-form">
            <form method="POST" class="row g-3">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <div class="col-md-4">
                    <label for="status" class="form-label">Filter by Status</label>
                    <select id="status" name="status" class="form-select">
                        <option value="">All Statuses</option>
                        <option value="draft" <?php echo $filter_status == 'draft' ? 'selected' : ''; ?>>Draft</option>
                        <option value="published" <?php echo $filter_status == 'published' ? 'selected' : ''; ?>>Published</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" name="filter" class="btn btn-primary w-100">Apply Filter</button>
                </div>
            </form>
        </div>

        <!-- Blogs Table -->
        <div class="table-responsive">
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Title</th>
                        <th>Content</th>
                        <th>Image</th>
                        <th>Status</th>
                        <th>Created At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($blogs)): ?>
                        <?php foreach ($blogs as $blog): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($blog['id']); ?></td>
                                <td><?php echo htmlspecialchars($blog['title']); ?></td>
                                <td><?php echo htmlspecialchars(substr($blog['content'], 0, 50)) . (strlen($blog['content']) > 50 ? '...' : ''); ?></td>
                                <td>
                                    <?php if ($blog['image']): ?>
                                        <img src="/takecare/uploads/<?php echo htmlspecialchars($blog['image']); ?>" alt="Blog Image" class="blog-image">
                                    <?php else: ?>
                                        No Image
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($blog['status']); ?></td>
                                <td><?php echo htmlspecialchars($blog['created_at']); ?></td>
                                <td>
                                    <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#viewModal<?php echo $blog['id']; ?>">
                                        <i class="fas fa-eye"></i> View
                                    </button>
                                    <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#editModal<?php echo $blog['id']; ?>">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this blog?');">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                        <input type="hidden" name="blog_id" value="<?php echo $blog['id']; ?>">
                                        <button type="submit" name="delete_blog" class="btn btn-danger btn-sm">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <!-- View Modal -->
                            <div class="modal fade" id="viewModal<?php echo $blog['id']; ?>" tabindex="-1" aria-labelledby="viewModalLabel<?php echo $blog['id']; ?>" aria-hidden="true">
                                <div class="modal-dialog modal-lg modal-dialog-centered">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="viewModalLabel<?php echo $blog['id']; ?>">Blog Details</h5>
                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            <p><strong>ID:</strong> <?php echo htmlspecialchars($blog['id']); ?></p>
                                            <p><strong>Title:</strong> <?php echo htmlspecialchars($blog['title']); ?></p>
                                            <p><strong>Content:</strong> <?php echo nl2br(htmlspecialchars($blog['content'])); ?></p>
                                            <p><strong>Image:</strong>
                                                <?php if ($blog['image']): ?>
                                                    <img src="/takecare/uploads/<?php echo htmlspecialchars($blog['image']); ?>" alt="Blog Image" class="blog-image">
                                                <?php else: ?>
                                                    No Image
                                                <?php endif; ?>
                                            </p>
                                            <p><strong>Status:</strong> <?php echo htmlspecialchars($blog['status']); ?></p>
                                            <p><strong>Created At:</strong> <?php echo htmlspecialchars($blog['created_at']); ?></p>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-outline-primary" data-bs-dismiss="modal">Close</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- Edit Modal -->
                            <div class="modal fade" id="editModal<?php echo $blog['id']; ?>" tabindex="-1" aria-labelledby="editModalLabel<?php echo $blog['id']; ?>" aria-hidden="true">
                                <div class="modal-dialog modal-lg modal-dialog-centered">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="editModalLabel<?php echo $blog['id']; ?>">Edit Blog</h5>
                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            <form method="POST" enctype="multipart/form-data">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                <input type="hidden" name="blog_id" value="<?php echo $blog['id']; ?>">
                                                <div class="mb-3">
                                                    <label for="title<?php echo $blog['id']; ?>" class="form-label">Title</label>
                                                    <input type="text" class="form-control" id="title<?php echo $blog['id']; ?>" name="title" value="<?php echo htmlspecialchars($blog['title']); ?>" required>
                                                </div>
                                                <div class="mb-3">
                                                    <label for="content<?php echo $blog['id']; ?>" class="form-label">Content</label>
                                                    <textarea class="form-control" id="content<?php echo $blog['id']; ?>" name="content" rows="5" required><?php echo htmlspecialchars($blog['content']); ?></textarea>
                                                </div>
                                                <div class="mb-3">
                                                    <label for="image<?php echo $blog['id']; ?>" class="form-label">Image</label>
                                                    <input type="file" class="form-control" id="image<?php echo $blog['id']; ?>" name="image" accept="image/jpeg,image/png,image/gif">
                                                    <?php if ($blog['image']): ?>
                                                        <p>Current: <img src="/takecare/uploads/<?php echo htmlspecialchars($blog['image']); ?>" alt="Current Image" class="blog-image"></p>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="mb-3">
                                                    <label for="status<?php echo $blog['id']; ?>" class="form-label">Status</label>
                                                    <select id="status<?php echo $blog['id']; ?>" name="status" class="form-select" required>
                                                        <option value="draft" <?php echo $blog['status'] == 'draft' ? 'selected' : ''; ?>>Draft</option>
                                                        <option value="published" <?php echo $blog['status'] == 'published' ? 'selected' : ''; ?>>Published</option>
                                                    </select>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-outline-primary" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" name="update_blog" class="btn btn-primary">Update Blog</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center">No blogs found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Add Blog Modal -->
        <div class="modal fade" id="addBlogModal" tabindex="-1" aria-labelledby="addBlogModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addBlogModalLabel">Add New Blog</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                            <div class="mb-3">
                                <label for="add_title" class="form-label">Title</label>
                                <input type="text" class="form-control" id="add_title" name="title" required>
                            </div>
                            <div class="mb-3">
                                <label for="add_content" class="form-label">Content</label>
                                <textarea class="form-control" id="add_content" name="content" rows="5" required></textarea>
                            </div>
                            <div class="mb-3">
                                <label for="add_image" class="form-label">Image</label>
                                <input type="file" class="form-control" id="add_image" name="image" accept="image/jpeg,image/png,image/gif">
                            </div>
                            <div class="mb-3">
                                <label for="add_status" class="form-label">Status</label>
                                <select id="add_status" name="status" class="form-select" required>
                                    <option value="draft">Draft</option>
                                    <option value="published">Published</option>
                                </select>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-outline-primary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" name="add_blog" class="btn btn-success">Add Blog</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js" integrity="sha512-fD9DI5bZwQxOi7MhYWnnNPlvXdp/2Pj3XSTRrFs5FQa4mizyGLnJcN6tuvUS6LbmgN1ut+XGSABKvjN0H6Aoow==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script>
        document.addEventListener("DOMContentLoaded", () => {
            const sidebar = document.querySelector('.sidebar');
            const toggleButton = document.getElementById('sidebarToggle');
            const toggleIcon = toggleButton.querySelector('i');
            const content = document.querySelector('.container-fluid');

            toggleButton.addEventListener('click', () => {
                sidebar.classList.toggle('hidden');
                content.classList.toggle('sidebar-hidden');
                toggleButton.classList.toggle('hidden');
                if (sidebar.classList.contains('hidden')) {
                    toggleIcon.classList.remove('fa-xmark');
                    toggleIcon.classList.add('fa-bars');
                } else {
                    toggleIcon.classList.remove('fa-bars');
                    toggleIcon.classList.add('fa-xmark');
                }
            });
        });
    </script>
</body>
</html>