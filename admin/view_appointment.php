<?php
if (session_status() === PHP_SESSION_NONE) session_start();

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Check if admin is logged in
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

// Initialize variables
$appointments = [];
$doctors = [];
$message = '';
$error = '';
$filter_doctor_id = '';
$filter_date_start = '';
$filter_date_end = '';

// Fetch all doctors for filter dropdown
try {
    $stmt = $conn->query("SELECT id, name FROM doctors ORDER BY name");
    $doctors = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Failed to fetch doctors: " . $e->getMessage();
}

// Handle filters
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['filter'])) {
    if ($_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Invalid CSRF token.";
    } else {
        $filter_doctor_id = filter_input(INPUT_POST, 'doctor_id', FILTER_VALIDATE_INT) ?: '';
        $filter_date_start = filter_input(INPUT_POST, 'date_start', FILTER_SANITIZE_STRING) ?: '';
        $filter_date_end = filter_input(INPUT_POST, 'date_end', FILTER_SANITIZE_STRING) ?: '';
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); // Refresh CSRF token
    }
}

// Fetch appointments with optional filters
try {
    $query = "
        SELECT a.id, a.doctor_id, a.patient_name, a.appointment_date, a.appointment_time, a.reason, a.created_at, d.name AS doctor_name
        FROM appointments a
        JOIN doctors d ON a.doctor_id = d.id
        WHERE 1=1
    ";
    $params = [];
    if ($filter_doctor_id) {
        $query .= " AND a.doctor_id = :doctor_id";
        $params['doctor_id'] = $filter_doctor_id;
    }
    if ($filter_date_start) {
        $query .= " AND a.appointment_date >= :date_start";
        $params['date_start'] = $filter_date_start;
    }
    if ($filter_date_end) {
        $query .= " AND a.appointment_date <= :date_end";
        $params['date_end'] = $filter_date_end;
    }
    $query .= " ORDER BY a.appointment_date DESC, a.appointment_time DESC";
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $appointments = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Failed to fetch appointments: " . $e->getMessage();
}

// Handle update appointment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_appointment'])) {
    if ($_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Invalid CSRF token.";
    } else {
        $appointment_id = filter_input(INPUT_POST, 'appointment_id', FILTER_VALIDATE_INT);
        $doctor_id = filter_input(INPUT_POST, 'doctor_id', FILTER_VALIDATE_INT);
        $patient_name = filter_input(INPUT_POST, 'patient_name', FILTER_SANITIZE_STRING);
        $appointment_date = filter_input(INPUT_POST, 'appointment_date', FILTER_SANITIZE_STRING);
        $appointment_time = filter_input(INPUT_POST, 'appointment_time', FILTER_SANITIZE_STRING);
        $reason = filter_input(INPUT_POST, 'reason', FILTER_SANITIZE_STRING);

        if (!$appointment_id || !$doctor_id || !$patient_name || !$appointment_date || !$appointment_time || !$reason) {
            $error = "All fields are required.";
        } else {
            $date = DateTime::createFromFormat('Y-m-d', $appointment_date);
            $time = DateTime::createFromFormat('H:i', $appointment_time);
            if (!$date || $date < new DateTime('today') || !$time || $time->format('H:i') < '09:00' || $time->format('H:i') > '18:00') {
                $error = "Invalid date or time. Appointments must be scheduled between 9:00 AM and 6:00 PM.";
            } else {
                try {
                    $stmt = $conn->prepare("SELECT id FROM doctors WHERE id = :id");
                    $stmt->execute(['id' => $doctor_id]);
                    if (!$stmt->fetch()) {
                        $error = "Doctor not found.";
                    } else {
                        $stmt = $conn->prepare("
                            UPDATE appointments
                            SET doctor_id = :doctor_id, patient_name = :patient_name, appointment_date = :appointment_date,
                                appointment_time = :appointment_time, reason = :reason
                            WHERE id = :id
                        ");
                        $stmt->execute([
                            'id' => $appointment_id,
                            'doctor_id' => $doctor_id,
                            'patient_name' => $patient_name,
                            'appointment_date' => $appointment_date,
                            'appointment_time' => $appointment_time,
                            'reason' => $reason
                        ]);
                        $message = "Appointment updated successfully!";
                        $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); // Refresh CSRF token
                    }
                } catch (PDOException $e) {
                    $error = "Failed to update appointment: " . $e->getMessage();
                }
            }
        }
    }
}

// Handle delete appointment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_appointment'])) {
    if ($_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Invalid CSRF token.";
    } else {
        $appointment_id = filter_input(INPUT_POST, 'appointment_id', FILTER_VALIDATE_INT);
        if ($appointment_id) {
            try {
                $stmt = $conn->prepare("DELETE FROM appointments WHERE id = :id");
                $stmt->execute(['id' => $appointment_id]);
                $message = "Appointment deleted successfully!";
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); // Refresh CSRF token
            } catch (PDOException $e) {
                $error = "Failed to delete appointment: " . $e->getMessage();
            }
        } else {
            $error = "Invalid appointment ID.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Appointments | Takecare Admin</title>
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
            padding-top: 70px; /* Space for fixed header */
            margin-left: 250px; /* Space for sidebar */
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
            max-width: 700px;
            margin: 0 auto 30px;
        }

        .table {
            background: var(--white);
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.06);
            overflow: hidden;
            transition: transform 0.3s ease;
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

        .btn-primary, .btn-outline-primary, .btn-danger {
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
            box-shadow: 0 5px 15px rgba(26, 115, 232, 0.3);
        }

        .btn-danger {
            background: linear-gradient(to right, #dc3545, #a71d2a);
            border: none;
        }

        .btn-danger:hover {
            background: linear-gradient(to right, #a71d2a, #dc3545);
            box-shadow: 0 5px 15px rgba(220, 53, 69, 0.3);
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
            animation: fadeIn 0.3s ease;
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

        /* Sidebar Toggle Button */
        .sidebar-toggle {
            display: none;
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

        /* Sidebar Styles */
        aside {
            transition: transform 0.3s ease;
        }

        .sidebar-hidden {
            transform: translateX(-250px);
        }

        .content-shift {
            margin-left: 0 !important;
        }

        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @media (max-width: 768px) {
            body {
                margin-left: 0;
            }

            aside {
                transform: translateX(-250px);
                z-index: 1000;
            }

            aside.active {
                transform: translateX(0);
            }

            .sidebar-toggle {
                display: block;
            }

            .content-shift {
                margin-left: 0 !important;
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
    <button class="sidebar-toggle" id="sidebarToggle"><i class="fas fa-bars"></i></button>
    <div class="container-fluid">
        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-title">Manage Appointments</h1>
            <p class="subtitle">View, update, or delete appointments</p>
        </div>

        <!-- Messages -->
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
                    <label for="doctor_id" class="form-label">Filter by Doctor</label>
                    <select id="doctor_id" name="doctor_id" class="form-select">
                        <option value="">All Doctors</option>
                        <?php foreach ($doctors as $doc): ?>
                            <option value="<?php echo $doc['id']; ?>" <?php echo $filter_doctor_id == $doc['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($doc['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="date_start" class="form-label">Start Date</label>
                    <input type="date" id="date_start" name="date_start" class="form-control" value="<?php echo htmlspecialchars($filter_date_start); ?>">
                </div>
                <div class="col-md-3">
                    <label for="date_end" class="form-label">End Date</label>
                    <input type="date" id="date_end" name="date_end" class="form-control" value="<?php echo htmlspecialchars($filter_date_end); ?>">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" name="filter" class="btn btn-primary w-100">Apply Filter</button>
                </div>
            </form>
        </div>

        <!-- Appointments Table -->
        <div class="table-responsive">
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Doctor</th>
                        <th>Patient Name</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Reason</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($appointments)): ?>
                        <?php foreach ($appointments as $appt): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($appt['id']); ?></td>
                                <td><?php echo htmlspecialchars($appt['doctor_name']); ?></td>
                                <td><?php echo htmlspecialchars($appt['patient_name']); ?></td>
                                <td><?php echo htmlspecialchars($appt['appointment_date']); ?></td>
                                <td><?php echo htmlspecialchars($appt['appointment_time']); ?></td>
                                <td><?php echo nl2br(htmlspecialchars(substr($appt['reason'], 0, 50))) . (strlen($appt['reason']) > 50 ? '...' : ''); ?></td>
                                <td>
                                    <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#viewModal<?php echo $appt['id']; ?>">
                                        <i class="fas fa-eye"></i> View
                                    </button>
                                    <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#editModal<?php echo $appt['id']; ?>">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this appointment?');">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                        <input type="hidden" name="appointment_id" value="<?php echo $appt['id']; ?>">
                                        <button type="submit" name="delete_appointment" class="btn btn-danger btn-sm">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <!-- View Modal -->
                            <div class="modal fade" id="viewModal<?php echo $appt['id']; ?>" tabindex="-1" aria-labelledby="viewModalLabel<?php echo $appt['id']; ?>" aria-hidden="true">
                                <div class="modal-dialog modal-lg modal-dialog-centered">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="viewModalLabel<?php echo $appt['id']; ?>">Appointment Details</h5>
                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            <p><strong>ID:</strong> <?php echo htmlspecialchars($appt['id']); ?></p>
                                            <p><strong>Doctor:</strong> <?php echo htmlspecialchars($appt['doctor_name']); ?></p>
                                            <p><strong>Patient Name:</strong> <?php echo htmlspecialchars($appt['patient_name']); ?></p>
                                            <p><strong>Date:</strong> <?php echo htmlspecialchars($appt['appointment_date']); ?></p>
                                            <p><strong>Time:</strong> <?php echo htmlspecialchars($appt['appointment_time']); ?></p>
                                            <p><strong>Reason:</strong> <?php echo nl2br(htmlspecialchars($appt['reason'])); ?></p>
                                            <p><strong>Created At:</strong> <?php echo htmlspecialchars($appt['created_at']); ?></p>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-outline-primary" data-bs-dismiss="modal">Close</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- Edit Modal -->
                            <div class="modal fade" id="editModal<?php echo $appt['id']; ?>" tabindex="-1" aria-labelledby="editModalLabel<?php echo $appt['id']; ?>" aria-hidden="true">
                                <div class="modal-dialog modal-lg modal-dialog-centered">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="editModalLabel<?php echo $appt['id']; ?>">Edit Appointment</h5>
                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            <form method="POST">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                <input type="hidden" name="appointment_id" value="<?php echo $appt['id']; ?>">
                                                <div class="mb-3">
                                                    <label for="doctor_id<?php echo $appt['id']; ?>" class="form-label">Doctor</label>
                                                    <select id="doctor_id<?php echo $appt['id']; ?>" name="doctor_id" class="form-select" required>
                                                        <?php foreach ($doctors as $doc): ?>
                                                            <option value="<?php echo $doc['id']; ?>" <?php echo $appt['doctor_id'] == $doc['id'] ? 'selected' : ''; ?>>
                                                                <?php echo htmlspecialchars($doc['name']); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <div class="mb-3">
                                                    <label for="patient_name<?php echo $appt['id']; ?>" class="form-label">Patient Name</label>
                                                    <input type="text" class="form-control" id="patient_name<?php echo $appt['id']; ?>" name="patient_name" value="<?php echo htmlspecialchars($appt['patient_name']); ?>" required>
                                                </div>
                                                <div class="mb-3">
                                                    <label for="appointment_date<?php echo $appt['id']; ?>" class="form-label">Appointment Date</label>
                                                    <input type="date" class="form-control" id="appointment_date<?php echo $appt['id']; ?>" name="appointment_date" value="<?php echo htmlspecialchars($appt['appointment_date']); ?>" min="<?php echo date('Y-m-d'); ?>" required>
                                                </div>
                                                <div class="mb-3">
                                                    <label for="appointment_time<?php echo $appt['id']; ?>" class="form-label">Appointment Time</label>
                                                    <input type="time" class="form-control" id="appointment_time<?php echo $appt['id']; ?>" name="appointment_time" value="<?php echo htmlspecialchars($appt['appointment_time']); ?>" min="09:00" max="18:00" required>
                                                </div>
                                                <div class="mb-3">
                                                    <label for="reason<?php echo $appt['id']; ?>" class="form-label">Reason for Visit</label>
                                                    <textarea class="form-control" id="reason<?php echo $appt['id']; ?>" name="reason" rows="4" required><?php echo htmlspecialchars($appt['reason']); ?></textarea>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-outline-primary" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" name="update_appointment" class="btn btn-primary">Update Appointment</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center">No appointments found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js" integrity="sha512-fD9DI5bZwQxOi7MhYWnnNPlvXdp/2Pj3XSTRrFs5FQa4mizyGLnJcN6tuvUS6LbmgN1ut+XGSABKvjN0H6Aoow==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script>
        // Sidebar toggle functionality
        document.addEventListener("DOMContentLoaded", () => {
            const sidebar = document.querySelector('aside');
            const toggleButton = document.getElementById('sidebarToggle');
            const content = document.querySelector('.container-fluid');

            toggleButton.addEventListener('click', () => {
                sidebar.classList.toggle('active');
                content.classList.toggle('content-shift');
            });

            // Navigation active state
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