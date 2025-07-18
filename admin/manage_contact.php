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
$contacts = [];
$message = '';
$error = '';
$filter_status = '';

// Fetch all contacts with optional status filter
try {
    $query = "SELECT id, name, email, message, status, created_at FROM contacts WHERE 1=1";
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
    $contacts = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Failed to fetch contacts: " . $e->getMessage();
}

// Handle add contact
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_contact'])) {
    if ($_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Invalid CSRF token.";
    } else {
        $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        $message_text = filter_input(INPUT_POST, 'message', FILTER_SANITIZE_STRING);
        $status = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_STRING) ?: 'pending';

        if (!$name || !$email || !$message_text) {
            $error = "All fields are required.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Invalid email format.";
        } else {
            try {
                $stmt = $conn->prepare("INSERT INTO contacts (name, email, message, status) VALUES (:name, :email, :message, :status)");
                $stmt->execute([
                    'name' => $name,
                    'email' => $email,
                    'message' => $message_text,
                    'status' => $status
                ]);
                $message = "Contact added successfully!";
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            } catch (PDOException $e) {
                $error = "Failed to add contact: " . $e->getMessage();
            }
        }
    }
}

// Handle update contact
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_contact'])) {
    if ($_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Invalid CSRF token.";
    } else {
        $contact_id = filter_input(INPUT_POST, 'contact_id', FILTER_VALIDATE_INT);
        $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        $message_text = filter_input(INPUT_POST, 'message', FILTER_SANITIZE_STRING);
        $status = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_STRING);

        if (!$contact_id || !$name || !$email || !$message_text || !$status) {
            $error = "All fields are required.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Invalid email format.";
        } else {
            try {
                $stmt = $conn->prepare("
                    UPDATE contacts
                    SET name = :name, email = :email, message = :message, status = :status
                    WHERE id = :id
                ");
                $stmt->execute([
                    'id' => $contact_id,
                    'name' => $name,
                    'email' => $email,
                    'message' => $message_text,
                    'status' => $status
                ]);
                $message = "Contact updated successfully!";
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            } catch (PDOException $e) {
                $error = "Failed to update contact: " . $e->getMessage();
            }
        }
    }
}

// Handle delete contact
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_contact'])) {
    if ($_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Invalid CSRF token.";
    } else {
        $contact_id = filter_input(INPUT_POST, 'contact_id', FILTER_VALIDATE_INT);
        if ($contact_id) {
            try {
                $stmt = $conn->prepare("DELETE FROM contacts WHERE id = :id");
                $stmt->execute(['id' => $contact_id]);
                $message = "Contact deleted successfully!";
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            } catch (PDOException $e) {
                $error = "Failed to delete contact: " . $e->getMessage();
            }
        } else {
            $error = "Invalid contact ID.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Contacts | Takecare Admin</title>
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
            margin-left: 250px;
            transition: margin-left 0.3s ease;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .container-fluid {
            padding: 20px;
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
            max-width: 500px;
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
            left: 10px;
            z-index: 1001;
            background: var(--primary-color);
            color: var(--white);
            border: none;
            padding: 10px;
            border-radius: 5px;
            cursor: pointer;
            transition: background 0.3s ease;
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
            .sidebar-toggle {
                top: 70px;
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
    <div class="container-fluid">
        <div class="page-header">
            <h1 class="page-title">Manage Contacts</h1>
            <p class="subtitle">View, add, update, or delete contact messages</p>
        </div>

        <?php if ($message): ?>
            <p class="success-message"><?php echo htmlspecialchars($message); ?></p>
        <?php endif; ?>
        <?php if ($error): ?>
            <p class="error-message"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>

        <!-- Add Contact Button -->
        <div class="mb-3">
            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addContactModal">
                <i class="fas fa-plus"></i> Add Contact
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
                        <option value="pending" <?php echo $filter_status == 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="resolved" <?php echo $filter_status == 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" name="filter" class="btn btn-primary w-100">Apply Filter</button>
                </div>
            </form>
        </div>

        <!-- Contacts Table -->
        <div class="table-responsive">
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Message</th>
                        <th>Status</th>
                        <th>Created At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($contacts)): ?>
                        <?php foreach ($contacts as $contact): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($contact['id']); ?></td>
                                <td><?php echo htmlspecialchars($contact['name']); ?></td>
                                <td><?php echo htmlspecialchars($contact['email']); ?></td>
                                <td><?php echo nl2br(htmlspecialchars(substr($contact['message'], 0, 50))) . (strlen($contact['message']) > 50 ? '...' : ''); ?></td>
                                <td><?php echo htmlspecialchars($contact['status']); ?></td>
                                <td><?php echo htmlspecialchars($contact['created_at']); ?></td>
                                <td>
                                    <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#viewModal<?php echo $contact['id']; ?>">
                                        <i class="fas fa-eye"></i> View
                                    </button>
                                    <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#editModal<?php echo $contact['id']; ?>">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this contact?');">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                        <input type="hidden" name="contact_id" value="<?php echo $contact['id']; ?>">
                                        <button type="submit" name="delete_contact" class="btn btn-danger btn-sm">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <!-- View Modal -->
                            <div class="modal fade" id="viewModal<?php echo $contact['id']; ?>" tabindex="-1" aria-labelledby="viewModalLabel<?php echo $contact['id']; ?>" aria-hidden="true">
                                <div class="modal-dialog modal-lg modal-dialog-centered">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="viewModalLabel<?php echo $contact['id']; ?>">Contact Details</h5>
                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            <p><strong>ID:</strong> <?php echo htmlspecialchars($contact['id']); ?></p>
                                            <p><strong>Name:</strong> <?php echo htmlspecialchars($contact['name']); ?></p>
                                            <p><strong>Email:</strong> <?php echo htmlspecialchars($contact['email']); ?></p>
                                            <p><strong>Message:</strong> <?php echo nl2br(htmlspecialchars($contact['message'])); ?></p>
                                            <p><strong>Status:</strong> <?php echo htmlspecialchars($contact['status']); ?></p>
                                            <p><strong>Created At:</strong> <?php echo htmlspecialchars($contact['created_at']); ?></p>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-outline-primary" data-bs-dismiss="modal">Close</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- Edit Modal -->
                            <div class="modal fade" id="editModal<?php echo $contact['id']; ?>" tabindex="-1" aria-labelledby="editModalLabel<?php echo $contact['id']; ?>" aria-hidden="true">
                                <div class="modal-dialog modal-lg modal-dialog-centered">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="editModalLabel<?php echo $contact['id']; ?>">Edit Contact</h5>
                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            <form method="POST">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                <input type="hidden" name="contact_id" value="<?php echo $contact['id']; ?>">
                                                <div class="mb-3">
                                                    <label for="name<?php echo $contact['id']; ?>" class="form-label">Name</label>
                                                    <input type="text" class="form-control" id="name<?php echo $contact['id']; ?>" name="name" value="<?php echo htmlspecialchars($contact['name']); ?>" required>
                                                </div>
                                                <div class="mb-3">
                                                    <label for="email<?php echo $contact['id']; ?>" class="form-label">Email</label>
                                                    <input type="email" class="form-control" id="email<?php echo $contact['id']; ?>" name="email" value="<?php echo htmlspecialchars($contact['email']); ?>" required>
                                                </div>
                                                <div class="mb-3">
                                                    <label for="message<?php echo $contact['id']; ?>" class="form-label">Message</label>
                                                    <textarea class="form-control" id="message<?php echo $contact['id']; ?>" name="message" rows="5" required><?php echo htmlspecialchars($contact['message']); ?></textarea>
                                                </div>
                                                <div class="mb-3">
                                                    <label for="status<?php echo $contact['id']; ?>" class="form-label">Status</label>
                                                    <select id="status<?php echo $contact['id']; ?>" name="status" class="form-select" required>
                                                        <option value="pending" <?php echo $contact['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                        <option value="resolved" <?php echo $contact['status'] == 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                                                    </select>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-outline-primary" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" name="update_contact" class="btn btn-primary">Update Contact</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center">No contacts found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Add Contact Modal -->
        <div class="modal fade" id="addContactModal" tabindex="-1" aria-labelledby="addContactModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addContactModalLabel">Add New Contact</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                            <div class="mb-3">
                                <label for="add_name" class="form-label">Name</label>
                                <input type="text" class="form-control" id="add_name" name="name" required>
                            </div>
                            <div class="mb-3">
                                <label for="add_email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="add_email" name="email" required>
                            </div>
                            <div class="mb-3">
                                <label for="add_message" class="form-label">Message</label>
                                <textarea class="form-control" id="add_message" name="message" rows="5" required></textarea>
                            </div>
                            <div class="mb-3">
                                <label for="add_status" class="form-label">Status</label>
                                <select id="add_status" name="status" class="form-select" required>
                                    <option value="pending">Pending</option>
                                    <option value="resolved">Resolved</option>
                                </select>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-outline-primary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" name="add_contact" class="btn btn-success">Add Contact</button>
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
                if (sidebar.classList.contains('hidden')) {
                    toggleIcon.classList.remove('fa-xmark');
                    toggleIcon.classList.add('fa-bars');
                } else {
                    toggleIcon.classList.remove('fa-bars');
                    toggleIcon.classList.add('fa-xmark');
                }
            });

            document.querySelectorAll('.nav-item').forEach(item => {
                item.addEventListener('click', e => {
                    document.querySelectorAll('.nav-item').forEach(nav => nav.classList.remove('active'));
                    item.classList.add('active');
                });
            });
        });
    </script>
</body>
</html>