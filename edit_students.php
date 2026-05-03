<?php
// FILE: edit_students.php
include 'conn.php';
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] != "admin") {
    header("Location: login.php");
    exit();
}

$msg = '';
$student_id = $_GET['id'] ?? 0;

// 1. Fetch Course Options
$courseOptions = [];
$course_q = $conn->query("SELECT course_name FROM courses ORDER BY course_name ASC");
while ($row = $course_q->fetch_assoc()) {
    $courseOptions[] = $row['course_name'];
}

// 2. Fetch Course/Year/Section data for Dynamic Select
$courseYearSections = [];
$student_query = $conn->query("SELECT DISTINCT course, year, section FROM users WHERE role = 'student' AND year != '' AND section != ''");
while ($row = $student_query->fetch_assoc()) {
    $courseYearSections[$row['course']][$row['year']][] = $row['section'];
}

// 3. Fetch Current Student Data
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ? AND role = 'student'");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$student_data = $stmt->get_result()->fetch_assoc();
if (!$student_data) { header("Location: manage_students.php"); exit(); }

// 4. Update Handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_student'])) {
    $full_name = trim($_POST['full_name']);
    $username  = trim($_POST['username']);
    $email     = trim($_POST['email']); 
    $course    = trim($_POST['course']);
    $year      = trim($_POST['year']); 
    $section   = trim($_POST['section']);
    $password  = trim($_POST['password']);

    // Check conflict
    $check = $conn->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
    $check->bind_param("ssi", $username, $email, $student_id);
    $check->execute();
    
    if ($check->get_result()->num_rows > 0) {
        $msg = "⚠️ Username or Email already exists.";
    } else {
        $sql = "UPDATE users SET username=?, full_name=?, email=?, course=?, year=?, section=?";
        $params = [$username, $full_name, $email, $course, $year, $section];
        $types = "ssssss";

        if (!empty($password)) {
            $sql .= ", password=?";
            $types .= "s";
            $params[] = password_hash($password, PASSWORD_DEFAULT);
        }
        $sql .= " WHERE id=?";
        $types .= "i";
        $params[] = $student_id;

        $upd = $conn->prepare($sql);
        $upd->bind_param($types, ...$params);
        if ($upd->execute()) {
            $_SESSION['message'] = "✅ Student updated successfully!";
            header("Location: manage_students.php"); exit();
        } else { $msg = "❌ Update failed."; }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Student | Admin Panel</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #006400; /* Dark Green matching your theme */
            --primary-light: #008000;
            --bg: #f0f2f5;
            --text: #1c1e21;
            --white: #ffffff;
            --shadow: 0 8px 30px rgba(0,0,0,0.05);
        }

        body { 
            font-family: 'Inter', sans-serif; 
            background: var(--bg); 
            color: var(--text);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
        }

        .container { 
            width: 100%; 
            max-width: 700px; 
            background: var(--white); 
            padding: 40px; 
            border-radius: 24px; 
            box-shadow: var(--shadow);
        }

        .header { 
            text-align: center;
            margin-bottom: 30px;
        }

        .header h2 {
            font-weight: 700;
            color: var(--primary);
            font-size: 28px;
            margin: 0;
            text-transform: uppercase;
        }

        .header p { color: #65676b; margin-top: 5px; }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .input-group { margin-bottom: 20px; }
        .full-width { grid-column: span 2; }

        label { 
            display: block; 
            margin-bottom: 8px; 
            font-weight: 600; 
            font-size: 13px;
            color: #4b4b4b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        input, select { 
            width: 100%; 
            padding: 12px 16px; 
            border: 1.5px solid #e4e6eb; 
            border-radius: 12px; 
            font-size: 15px;
            transition: all 0.3s ease;
            box-sizing: border-box;
        }

        input:focus, select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(0, 100, 0, 0.1);
        }

        .section-title {
            font-size: 14px;
            color: var(--primary);
            font-weight: 700;
            margin: 25px 0 15px 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .section-title::after {
            content: "";
            flex: 1;
            height: 1px;
            background: #e4e6eb;
        }

        .btn-save {
            background: var(--primary);
            color: white;
            border: none;
            padding: 16px;
            border-radius: 14px;
            width: 100%;
            font-weight: 700;
            font-size: 16px;
            cursor: pointer;
            margin-top: 20px;
            transition: 0.3s;
        }

        .btn-save:hover {
            background: var(--primary-light);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,100,0,0.2);
        }

        .error-msg {
            background: #fff0f0;
            border-left: 5px solid #d93025;
            color: #d93025;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 25px;
            font-size: 14px;
        }

        .back-link {
            display: block;
            text-align: center;
            margin-top: 20px;
            color: #65676b;
            text-decoration: none;
            font-size: 14px;
        }

        .back-link:hover { color: var(--primary); }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <h2>Edit Student</h2>
        <p>Update student profile and academic classification</p>
    </div>

    <?php if ($msg): ?> <div class="error-msg"><?= $msg ?></div> <?php endif; ?>

    <form method="POST">
        <div class="section-title"><i class='bx bx-user'></i> Personal Information</div>
        <div class="form-grid">
            <div class="input-group full-width">
                <label>Full Name</label>
                <input type="text" name="full_name" value="<?= htmlspecialchars($student_data['full_name']) ?>" required>
            </div>
            <div class="input-group">
                <label>Username / Student ID</label>
                <input type="text" name="username" value="<?= htmlspecialchars($student_data['username']) ?>" required>
            </div>
            <div class="input-group">
                <label>Email Address</label>
                <input type="email" name="email" value="<?= htmlspecialchars($student_data['email']) ?>" required>
            </div>
        </div>

        <div class="section-title"><i class='bx bx-lock-alt'></i> Security</div>
        <div class="input-group">
            <label>New Password <span style="font-weight:400; text-transform:none;">(Leave blank to keep current)</span></label>
            <input type="password" name="password" placeholder="••••••••">
        </div>

        <div class="section-title"><i class='bx bx-book-reader'></i> Academic Details</div>
        <div class="form-grid">
            <div class="input-group full-width">
                <label>Course</label>
                <select name="course" id="course" required onchange="loadYears()">
                    <option value="">-- Select Course --</option>
                    <?php foreach($courseOptions as $c): ?>
                        <option value="<?= $c ?>" <?= ($student_data['course'] == $c) ? 'selected' : '' ?>><?= $c ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="input-group">
                <label>Year Level</label>
                <select name="year" id="year" required onchange="loadSections()">
                    <option value="">-- Select Year --</option>
                </select>
            </div>
            <div class="input-group">
                <label>Section</label>
                <select name="section" id="section" required>
                    <option value="">-- Select Section --</option>
                </select>
            </div>
        </div>

        <button type="submit" name="update_student" class="btn-save">Save Changes</button>
        <a href="manage_students.php" class="back-link"><i class='bx bx-arrow-back'></i> Return to Student List</a>
    </form>
</div>

<script>
const courseData = <?= json_encode($courseYearSections) ?>;
const current = {
    course: "<?= $student_data['course'] ?>",
    year: "<?= $student_data['year'] ?>",
    section: "<?= $student_data['section'] ?>"
};

function loadYears() {
    const course = document.getElementById('course').value;
    const yearSelect = document.getElementById('year');
    yearSelect.innerHTML = '<option value="">-- Select Year --</option>';
    
    if(courseData[course]) {
        Object.keys(courseData[course]).forEach(y => {
            let opt = new Option(y, y);
            if(y == current.year && course == current.course) opt.selected = true;
            yearSelect.add(opt);
        });
    }
    loadSections();
}

function loadSections() {
    const course = document.getElementById('course').value;
    const year = document.getElementById('year').value;
    const sectionSelect = document.getElementById('section');
    sectionSelect.innerHTML = '<option value="">-- Select Section --</option>';

    if(courseData[course] && courseData[course][year]) {
        courseData[course][year].forEach(s => {
            let opt = new Option(s, s);
            if(s == current.section && course == current.course && year == current.year) opt.selected = true;
            sectionSelect.add(opt);
        });
    }
}

document.addEventListener('DOMContentLoaded', loadYears);
</script>

</body>
</html>