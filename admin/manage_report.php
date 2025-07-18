<?php
if (session_status() === PHP_SESSION_NONE) session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: login.php");
    exit();
}

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
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

// Fetch reports with optional status filter
$reports = [];
$message = '';
$error = '';
$filter_status = '';

try {
    $query = "SELECT id, name, email, address, issue_type, description, pdf_file, video_file, status, created_at FROM reports WHERE 1=1";
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
    $reports = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Failed to fetch reports: " . $e->getMessage();
}

// Handle update report
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_report'])) {
    if ($_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Invalid CSRF token.";
    } else {
        $report_id = filter_input(INPUT_POST, 'report_id', FILTER_VALIDATE_INT);
        $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        $address = filter_input(INPUT_POST, 'address', FILTER_SANITIZE_STRING) ?: NULL;
        $issue_type = filter_input(INPUT_POST, 'issue_type', FILTER_SANITIZE_STRING);
        $description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_STRING);
        $status = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_STRING);
        $pdf_file = NULL;
        $video_file = NULL;

        if (!$report_id || !$name || !$email || !$issue_type || !$description || !$status) {
            $error = "All required fields must be filled.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Invalid email format.";
        } else {
            // Handle PDF/image upload
            if (isset($_FILES['pdf_upload']) && $_FILES['pdf_upload']['error'] === UPLOAD_ERR_OK) {
                $allowed_types = ['image/jpeg', 'image/png', 'application/pdf'];
                $max_size = 5 * 1024 * 1024; // 5MB
                $file_type = mime_content_type($_FILES['pdf_upload']['tmp_name']);
                $file_size = $_FILES['pdf_upload']['size'];
                $file_ext = strtolower(pathinfo($_FILES['pdf_upload']['name'], PATHINFO_EXTENSION));
                $file_name = 'pdf_' . uniqid() . '.' . $file_ext;
                $upload_dir = 'C:/xampp/htdocs/takecare/uploads/';
                $upload_path = $upload_dir . $file_name;

                if (!in_array($file_type, $allowed_types)) {
                    $error = "Only PDF, JPEG, or PNG files are allowed.";
                } elseif ($file_size > $max_size) {
                    $error = "File size exceeds 5MB.";
                } elseif (!move_uploaded_file($_FILES['pdf_upload']['tmp_name'], $upload_path)) {
                    $error = "Failed to upload file.";
                } else {
                    $pdf_file = $file_name;
                    // Delete old PDF
                    $stmt = $conn->prepare("SELECT pdf_file FROM reports WHERE id = :id");
                    $stmt->execute(['id' => $report_id]);
                    $old_pdf = $stmt->fetchColumn();
                    if ($old_pdf && file_exists($upload_dir . $old_pdf)) {
                        unlink($upload_dir . $old_pdf);
                    }
                }
            }

            // Handle video upload
            if (!$error && isset($_FILES['video_upload']) && $_FILES['video_upload']['error'] === UPLOAD_ERR_OK) {
                $allowed_types = ['video/mp4', 'video/mov', 'video/avi'];
                $max_size = 5 * 1024 * 1024; // 5MB
                $file_type = mime_content_type($_FILES['video_upload']['tmp_name']);
                $file_size = $_FILES['video_upload']['size'];
                $file_ext = strtolower(pathinfo($_FILES['video_upload']['name'], PATHINFO_EXTENSION));
                $file_name = 'video_' . uniqid() . '.' . $file_ext;
                $upload_path = $upload_dir . $file_name;

                if (!in_array($file_type, $allowed_types)) {
                    $error = "Only MP4, MOV, or AVI videos are allowed.";
                } elseif ($file_size > $max_size) {
                    $error = "Video size exceeds 5MB.";
                } elseif (!move_uploaded_file($_FILES['video_upload']['tmp_name'], $upload_path)) {
                    $error = "Failed to upload video.";
                } else {
                    $video_file = $file_name;
                    // Delete old video
                    $stmt = $conn->prepare("SELECT video_file FROM reports WHERE id = :id");
                    $stmt->execute(['id' => $report_id]);
                    $old_video = $stmt->fetchColumn();
                    if ($old_video && file_exists($upload_dir . $old_video)) {
                        unlink($upload_dir . $old_video);
                    }
                }
            }

            if (!$error) {
                try {
                    $query = "UPDATE reports SET name = :name, email = :email, address = :address, issue_type = :issue_type, description = :description, status = :status";
                    $params = [
                        'id' => $report_id,
                        'name' => $name,
                        'email' => $email,
                        'address' => $address,
                        'issue_type' => $issue_type,
                        'description' => $description,
                        'status' => $status
                    ];
                    if ($pdf_file) {
                        $query .= ", pdf_file = :pdf_file";
                        $params['pdf_file'] = $pdf_file;
                    }
                    if ($video_file) {
                        $query .= ", video_file = :video_file";
                        $params['video_file'] = $video_file;
                    }
                    $query .= " WHERE id = :id";
                    $stmt = $conn->prepare($query);
                    $stmt->execute($params);
                    $message = "Report updated successfully!";
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                } catch (PDOException $e) {
                    $error = "Failed to update report: " . $e->getMessage();
                }
            }
        }
    }
}

// Handle delete report
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_report'])) {
    if ($_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Invalid CSRF token.";
    } else {
        $report_id = filter_input(INPUT_POST, 'report_id', FILTER_VALIDATE_INT);
        if ($report_id) {
            try {
                // Delete files
                $stmt = $conn->prepare("SELECT pdf_file, video_file FROM reports WHERE id = :id");
                $stmt->execute(['id' => $report_id]);
                $files = $stmt->fetch();
                $upload_dir = 'C:/xampp/htdocs/takecare/uploads/';
                if ($files['pdf_file'] && file_exists($upload_dir . $files['pdf_file'])) {
                    unlink($upload_dir . $files['pdf_file']);
                }
                if ($files['video_file'] && file_exists($upload_dir . $files['video_file'])) {
                    unlink($upload_dir . $files['video_file']);
                }
                // Delete report
                $stmt = $conn->prepare("DELETE FROM reports WHERE id = :id");
                $stmt->execute(['id' => $report_id]);
                $message = "Report deleted successfully!";
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            } catch (PDOException $e) {
                $error = "Failed to delete report: " . $e->getMessage();
            }
        } else {
            $error = "Invalid report ID.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Reports | Takecare Admin</title>
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
            --purple: #8A2BE2;
            --dark-blue: #1A2B40;
            --accent: #e24e1b;
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

        .report-card {
            background: var(--white);
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            overflow: hidden;
            margin-bottom: 20px;
        }

        .report-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
        }

        .report-card-body {
            padding: 20px;
        }

        .report-card-title {
            font-size: 1.5rem;
            color: var(--purple);
            margin-bottom: 10px;
        }

        .report-card-text {
            color: var(--text-dark);
            font-size: 0.9rem;
            margin-bottom: 10px;
            overflow: hidden;
            text-overflow: ellipsis;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
        }

        .report-card .btn {
            padding: 6px 12px;
            font-size: 0.85rem;
            margin-right: 5px;
        }

        .report-card .btn-primary {
            background: linear-gradient(45deg, var(--purple), #A020F0);
            border: none;
        }

        .report-card .btn-primary:hover {
            background: linear-gradient(45deg, #A020F0, var(--purple));
        }

        .report-card .btn-danger {
            background: linear-gradient(45deg, #dc3545, #a71d2a);
            border: none;
        }

        .report-card .btn-danger:hover {
            background: linear-gradient(45deg, #a71d2a, #dc3545);
        }

        .report-card img, .report-card video {
            max-width: 100px;
            height: auto;
            border-radius: 5px;
            margin-top: 10px;
        }

        .modal-content {
            border-radius: 10px;
            border: none;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .modal-header {
            background: linear-gradient(135deg, var(--purple), var(--dark-blue));
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

        .modal-body img, .modal-body video {
            max-width: 100%;
            max-height: 300px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .modal-footer {
            border-top: none;
            padding: 15px 20px;
        }

        .success-message {
            color: #2e7d32;
            font-size: 1.1rem;
            text-align: center;
            margin-bottom: 20 warranty: .3s ease;
        }

        .error-message {
            color: #dc3545;
            font-size: 1.1rem;
            text-align: center;
            margin-bottom: 20px;
            animation: fadeIn 0.3s ease;
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

            .report-card {
                margin-bottom: 15px;
            }

            .report-card-title {
                font-size: 1.3rem;
            }

            .report-card-text {
                font-size: 0.8rem;
            }

            .report-card img, .report-card video {
                max-width: 80px;
            }
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    <?php include 'sidebar.php'; ?>
    <button class="sidebar-toggle" id="sidebarToggle"><i class="fas fa-bars"></i></button>
    <div class="container-fluid">
        <div class="page-header">
            <h1 class="page-title">Manage Reports</h1>
            <p class="subtitle">View, update, or delete reported issues</p>
        </div>

        <?php if ($message): ?>
            <p class="success-message"><?php echo htmlspecialchars($message); ?></p>
        <?php endif; ?>
        <?php if ($error): ?>
            <p class="error-message"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>

        <!-- Filter Form -->
        <div class="filter-form">
            <form method="POST" class="row g-3">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <div class="col-md-4">
                    <label for="status" class="form-label">Filter by Status</label>
                    <select id="status" name="status" class="form-select">
                        <option value="">All Statuses</option>
                        <option value="pending" <?php echo $filter_status == 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="resolved" <?php echo $filter_status == 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" name="filter" class="btn btn-primary w-100">Apply Filter</button>
                </div>
            </form>
        </div>

        <!-- Reports Grid -->
        <div class="row row-cols-1 row-cols-md-3 g-4">
            <?php if (!empty($reports)): ?>
                <?php foreach ($reports as $report): ?>
                    <div class="col">
                        <div class="report-card">
                            <div class="report-card-body">
                                <h5 class="report-card-title"><?php echo htmlspecialchars($report['name']); ?></h5>
                                <p class="report-card-text"><strong>इमेल:</strong> <?php echo htmlspecialchars($report['email']); ?></p>
                                <p class="report-card-text"><strong>समस्याको प्रकार:</strong> <?php echo htmlspecialchars(ucfirst($report['issue_type'])); ?></p>
                                <p class="report-card-text"><?php echo htmlspecialchars(substr($report['description'], 0, 100)) . (strlen($report['description']) > 100 ? '...' : ''); ?></p>
                                <?php if ($report['pdf_file']): ?>
                                    <p class="report-card-text">
                                        <strong>PDF/छवि:</strong>
                                        <?php if (in_array(strtolower(pathinfo($report['pdf_file'], PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png'])): ?>
                                            <img src="/takecare/uploads/<?php echo htmlspecialchars($report['pdf_file']); ?>" alt="Attachment">
                                        <?php else: ?>
                                            <a href="/takecare/uploads/<?php echo htmlspecialchars($report['pdf_file']); ?>" target="_blank">View PDF</a>
                                        <?php endif; ?>
                                    </p>
                                <?php endif; ?>
                                <?php if ($report['video_file']): ?>
                                    <p class="report-card-text">
                                        <strong>भिडियो:</strong>
                                        <video controls>
                                            <source src="/takecare/uploads/<?php echo htmlspecialchars($report['video_file']); ?>" type="video/<?php echo strtolower(pathinfo($report['video_file'], PATHINFO_EXTENSION)); ?>">
                                        </video>
                                    </p>
                                <?php endif; ?>
                                <p class="report-card-text"><strong>स्थिति:</strong> <?php echo htmlspecialchars(ucfirst($report['status'])); ?></p>
                                <p class="report-card-text"><strong>पेश गरिएको:</strong> <?php echo htmlspecialchars($report['created_at']); ?></p>
                                <div>
                                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#viewModal<?php echo $report['id']; ?>">
                                        <i class="fas fa-eye"></i> View
                                    </button>
                                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#editModal<?php echo $report['id']; ?>">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this report?');">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                        <input type="hidden" name="report_id" value="<?php echo $report['id']; ?>">
                                        <button type="submit" name="delete_report" class="btn btn-danger">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- View Modal -->
                    <div class="modal fade" id="viewModal<?php echo $report['id']; ?>" tabindex="-1" aria-labelledby="viewModalLabel<?php echo $report['id']; ?>" aria-hidden="true">
                        <div class="modal-dialog modal-lg modal-dialog-centered">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="viewModalLabel<?php echo $report['id']; ?>">Report Details</h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <p><strong>ID:</strong> <?php echo htmlspecialchars($report['id']); ?></p>
                                    <p><strong>नाम:</strong> <?php echo htmlspecialchars($report['name']); ?></p>
                                    <p><strong>इमेल:</strong> <?php echo htmlspecialchars($report['email']); ?></p>
                                    <p><strong>ठेगाना:</strong> <?php echo htmlspecialchars($report['address'] ?: 'N/A'); ?></p>
                                    <p><strong>समस्याको प्रकार:</strong> <?php echo htmlspecialchars(ucfirst($report['issue_type'])); ?></p>
                                    <p><strong>विवरण:</strong> <?php echo nl2br(htmlspecialchars($report['description'])); ?></p>
                                    <p><strong>PDF/छवि:</strong>
                                        <?php if ($report['pdf_file']): ?>
                                            <?php if (in_array(strtolower(pathinfo($report['pdf_file'], PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png'])): ?>
                                                <img src="/takecare/uploads/<?php echo htmlspecialchars($report['pdf_file']); ?>" alt="Attachment">
                                            <?php else: ?>
                                                <a href="/takecare/uploads/<?php echo htmlspecialchars($report['pdf_file']); ?>" target="_blank">View PDF</a>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            No File
                                        <?php endif; ?>
                                    </p>
                                    <p><strong>भिडियो:</strong>
                                        <?php if ($report['video_file']): ?>
                                            <video controls>
                                                <source src="/takecare/uploads/<?php echo htmlspecialchars($report['video_file']); ?>" type="video/<?php echo strtolower(pathinfo($report['video_file'], PATHINFO_EXTENSION)); ?>">
                                            </video>
                                        <?php else: ?>
                                            No Video
                                        <?php endif; ?>
                                    </p>
                                    <p><strong>स्थिति:</strong> <?php echo htmlspecialchars(ucfirst($report['status'])); ?></p>
                                    <p><strong>पेश गरिएको:</strong> <?php echo htmlspecialchars($report['created_at']); ?></p>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-outline-primary" data-bs-dismiss="modal">Close</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Edit Modal -->
                    <div class="modal fade" id="editModal<?php echo $report['id']; ?>" tabindex="-1" aria-labelledby="editModalLabel<?php echo $report['id']; ?>" aria-hidden="true">
                        <div class="modal-dialog modal-lg modal-dialog-centered">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="editModalLabel<?php echo $report['id']; ?>">Edit Report</h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <form method="POST" enctype="multipart/form-data">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                        <input type="hidden" name="report_id" value="<?php echo $report['id']; ?>">
                                        <div class="mb-3">
                                            <label for="name<?php echo $report['id']; ?>" class="form-label">नाम</label>
                                            <input type="text" class="form-control" id="name<?php echo $report['id']; ?>" name="name" value="<?php echo htmlspecialchars($report['name']); ?>" required>
                                        </div>
                                        <div class="mb-3">
                                            <label for="email<?php echo $report['id']; ?>" class="form-label">इमेल</label>
                                            <input type="email" class="form-control" id="email<?php echo $report['id']; ?>" name="email" value="<?php echo htmlspecialchars($report['email']); ?>" required>
                                        </div>
                                        <div class="mb-3">
                                            <label for="address<?php echo $report['id']; ?>" class="form-label">ठेगाना</label>
                                            <input type="text" class="form-control" id="address<?php echo $report['id']; ?>" name="address" value="<?php echo htmlspecialchars($report['address']); ?>">
                                        </div>
                                        <div class="mb-3">
                                            <label for="issue_type<?php echo $report['id']; ?>" class="form-label">समस्याको प्रकार</label>
                                            <select class="form-control" id="issue_type<?php echo $report['id']; ?>" name="issue_type" required>
                                                <option value="technical" <?php echo $report['issue_type'] == 'technical' ? 'selected' : ''; ?>>प्राविधिक समस्या</option>
                                                <option value="service" <?php echo $report['issue_type'] == 'service' ? 'selected' : ''; ?>>सेवा सम्बन्धी चासो</option>
                                                <option value="feedback" <?php echo $report['issue_type'] == 'feedback' ? 'selected' : ''; ?>>प्रतिक्रिया/सुझाव</option>
                                                <option value="safety" <?php echo $report['issue_type'] == 'safety' ? 'selected' : ''; ?>>सुरक्षा चासो</option>
                                                <option value="other" <?php echo $report['issue_type'] == 'other' ? 'selected' : ''; ?>>अन्य</option>
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label for="description<?php echo $report['id']; ?>" class="form-label">विवरण</label>
                                            <textarea class="form-control" id="description<?php echo $report['id']; ?>" name="description" required><?php echo htmlspecialchars($report['description']); ?></textarea>
                                        </div>
                                        <div class="mb-3">
                                            <label for="status<?php echo $report['id']; ?>" class="form-label">स्थिति</label>
                                            <select class="form-control" id="status<?php echo $report['id']; ?>" name="status" required>
                                                <option value="pending" <?php echo $report['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                <option value="resolved" <?php echo $report['status'] == 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label for="pdf_upload<?php echo $report['id']; ?>" class="form-label">PDF/छवि अपलोड</label>
                                            <input type="file" class="form-control" id="pdf_upload<?php echo $report['id']; ?>" name="pdf_upload" accept=".pdf,.jpg,.jpeg,.png">
                                            <?php if ($report['pdf_file']): ?>
                                                <p>Current: <a href="/takecare/uploads/<?php echo htmlspecialchars($report['pdf_file']); ?>" target="_blank"><?php echo htmlspecialchars($report['pdf_file']); ?></a></p>
                                            <?php endif; ?>
                                        </div>
                                        <div class="mb-3">
                                            <label for="video_upload<?php echo $report['id']; ?>" class="form-label">भिडियो अपलोड</label>
                                            <input type="file" class="form-control" id="video_upload<?php echo $report['id']; ?>" name="video_upload" accept="video/*">
                                            <?php if ($report['video_file']): ?>
                                                <p>Current: <a href="/takecare/uploads/<?php echo htmlspecialchars($report['video_file']); ?>" target="_blank"><?php echo htmlspecialchars($report['video_file']); ?></a></p>
                                            <?php endif; ?>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-outline-primary" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" name="update_report" class="btn btn-primary">Save Changes</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12">
                    <p class="text-center">No reports found.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const sidebarToggle = document.getElementById('sidebarToggle');
            const body = document.body;
            const container = document.querySelector('.container-fluid');

            sidebarToggle.addEventListener('click', function() {
                body.classList.toggle('sidebar-hidden');
                container.classList.toggle('sidebar-hidden');
                this.classList.toggle('hidden');
                if (this.classList.contains('hidden')) {
                    this.innerHTML = '<i class="fas fa-bars"></i>';
                } else {
                    this.innerHTML = '<i class="fas fa-times"></i>';
                }
            });
        });
    </script>
</body>
</html>