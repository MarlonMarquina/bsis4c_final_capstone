<?php
// FILE: edit_signatory.php
include 'conn.php';
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] != "admin") {
    header("Location: login.php");
    exit();
}

$msg = '';
$signatory_id = $_GET['id'] ?? 0;

if (!$signatory_id) {
    header("Location: manage_signatories.php"); exit();
}

// 1. FETCH LAHAT NG TAKEN DEPARTMENTS PARA SA PROGRAM HEAD (EXCEPT CURRENT USER)
$takenDepts = [];
$taken_query = $conn->prepare("SELECT department FROM users WHERE role = 'signatory' AND signatory_type = 'Program Head' AND id != ?");
$taken_query->bind_param("i", $signatory_id);
$taken_query->execute();
$taken_res = $taken_query->get_result();
while ($t_row = $taken_res->fetch_assoc()) {
    $depts = explode(',', $t_row['department']);
    foreach ($depts as $d) {
        $trimmed = trim($d);
        if (!empty($trimmed)) $takenDepts[] = $trimmed;
    }
}

// Fetch current signatory data
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ? AND role = 'signatory'");
$stmt->bind_param("i", $signatory_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows !== 1) {
    header("Location: manage_signatories.php"); exit();
}
$signatory_data = $result->fetch_assoc();
$stmt->close();

$currentDepts = array_map('trim', explode(',', $signatory_data['department'] ?? ''));
$currentYears = array_map('trim', explode(',', $signatory_data['year'] ?? ''));
$currentSections = array_map('trim', explode(',', $signatory_data['section'] ?? ''));

// Fetch Courses Options
$departmentOptions = [];
$dept_query = $conn->query("SELECT DISTINCT course_name FROM courses ORDER BY course_name ASC");
while ($row = $dept_query->fetch_assoc()) {
    if (!empty(trim($row['course_name']))) $departmentOptions[] = trim($row['course_name']);
}

$signatoryOptions = ["Student Government (SG)", "Scholarship Office", "Class Adviser", "PTCA", "Research Office", "Program Head"];

// --- UPDATE HANDLER ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_signatory'])) {
    $full_name      = trim($_POST['full_name']);
    $username       = trim($_POST['username']);
    $email          = trim($_POST['email']);
    $signatory_type = $_POST['signatory_type'];
    $password       = trim($_POST['password'] ?? '');
    
    $department_arr = $_POST['department'] ?? [];
    $year_arr       = $_POST['year'] ?? [];
    $section_arr    = $_POST['section'] ?? [];

    $department = implode(',', $department_arr);
    $year       = implode(',', $year_arr);
    $section    = implode(',', $section_arr);

    $hasConflict = false;

    // --- NEW VALIDATION: BAWAL MAG SAVE KAPAG CLASS ADVISER PERO WALANG YEAR/SECTION ---
    if ($signatory_type === 'Class Adviser') {
        if (empty($department_arr)) {
            $hasConflict = true;
            $msg = "⚠️ Error: Please select at least one Course for Class Adviser.";
        } elseif (empty($year_arr) || empty($section_arr)) {
            $hasConflict = true;
            $msg = "⚠️ Error: Cannot save. You must select at least one Year and Section.";
        }
    }

    // A. PROGRAM HEAD CONFLICT CHECK
    if (!$hasConflict && $signatory_type === 'Program Head') {
        foreach ($department_arr as $selectedDept) {
            if (in_array(trim($selectedDept), $takenDepts)) {
                $hasConflict = true;
                $msg = "⚠️ Error: Ang department na <strong>$selectedDept</strong> ay mayroon nang Program Head.";
                break;
            }
        }
    }

    // B. CLASS ADVISER CONFLICT CHECK
    if (!$hasConflict && $signatory_type === 'Class Adviser') {
        foreach ($department_arr as $d) {
            foreach ($year_arr as $y) {
                foreach ($section_arr as $s) {
                    $check_adv = $conn->prepare("SELECT full_name FROM users WHERE role='signatory' AND signatory_type='Class Adviser' AND FIND_IN_SET(?, department) AND FIND_IN_SET(?, year) AND FIND_IN_SET(?, section) AND id != ?");
                    $check_adv->bind_param("sssi", $d, $y, $s, $signatory_id);
                    $check_adv->execute();
                    $res_adv = $check_adv->get_result();
                    if ($res_adv->num_rows > 0) {
                        $other = $res_adv->fetch_assoc();
                        $hasConflict = true;
                        $msg = "⚠️ Conflict: Si <strong>{$other['full_name']}</strong> ay naka-assign na sa $d - $y - $s.";
                        break 3; 
                    }
                }
            }
        }
    }

    if (!$hasConflict) {
        if (($signatory_type === 'Program Head' || $signatory_type === 'Class Adviser') && empty($department)) {
            $msg = "⚠️ Kindly select a Department(s).";
        } else {
            $sql = "UPDATE users SET username=?, full_name=?, email=?, signatory_type=?, department=?, year=?, section=?";
            $types = "sssssss";
            $params = [$username, $full_name, $email, $signatory_type, $department, $year, $section];

            if (!empty($password)) {
                $sql .= ", password=?";
                $types .= "s";
                $params[] = password_hash($password, PASSWORD_DEFAULT);
            }
            $sql .= " WHERE id=?";
            $types .= "i";
            $params[] = $signatory_id;

            $update = $conn->prepare($sql);
            $update->bind_param($types, ...$params);
            if ($update->execute()) {
                $_SESSION['message'] = "✅ Signatory account updated successfully!";
                header("Location: manage_signatories.php"); exit();
            } else { $msg = "❌ Error: " . $conn->error; }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Signatory | Admin Panel</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #006400;
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
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .input-group {
            margin-bottom: 20px;
        }

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

        .checkbox-card {
            background: #f9fafb;
            border: 1.5px solid #e4e6eb;
            border-radius: 16px;
            padding: 15px;
            max-height: 200px;
            overflow-y: auto;
        }

        .check-item {
            display: flex;
            align-items: center;
            padding: 10px;
            border-radius: 10px;
            transition: 0.2s;
            cursor: pointer;
            margin-bottom: 5px;
        }

        .check-item:hover { background: #f0f2f5; }

        .check-item input { width: auto; margin-right: 12px; }

        .status-taken {
            font-size: 11px;
            color: #d93025;
            background: #feeeee;
            padding: 2px 8px;
            border-radius: 20px;
            margin-left: 10px;
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

        .password-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }
        .password-wrapper input {
            padding-right: 45px;
        }
        .password-toggle {
            position: absolute;
            right: 15px;
            cursor: pointer;
            color: #65676b;
            font-size: 20px;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <h2>Update Signatory</h2>
        <p>Modify access levels and department assignments</p>
    </div>

    <?php if ($msg): ?> <div class="error-msg"><?= $msg ?></div> <?php endif; ?>

    <form method="POST" id="editForm">
        <div class="form-grid">
            <div class="input-group">
                <label>Full Name</label>
                <input type="text" name="full_name" value="<?= htmlspecialchars($signatory_data['full_name']) ?>" required>
            </div>
            <div class="input-group">
                <label>Username</label>
                <input type="text" name="username" value="<?= htmlspecialchars($signatory_data['username']) ?>" required>
            </div>
            <div class="input-group full-width">
                <label>Email Address</label>
                <input type="email" name="email" value="<?= htmlspecialchars($signatory_data['email']) ?>" required>
            </div>
            <div class="input-group full-width">
                <label>Update Password <span style="font-weight:400; text-transform:none;">(Leave blank if no change)</span></label>
                <div class="password-wrapper">
                    <input type="password" name="password" id="passwordInput" placeholder="••••••••">
                    <i class='bx bx-hide password-toggle' id="togglePassword"></i>
                </div>
            </div>
            <div class="input-group full-width">
                <label>Signatory Designation</label>
                <select name="signatory_type" id="sigType" onchange="toggleFields()" required>
                    <?php foreach ($signatoryOptions as $type): ?>
                        <option value="<?= $type ?>" <?= ($signatory_data['signatory_type'] == $type) ? 'selected' : '' ?>><?= $type ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div id="deptBox" style="display:none;">
            <div class="section-title"><i class='bx bx-buildings'></i> Scope of Authority</div>
            <label>Department / Course</label>
            <div class="checkbox-card">
                <?php foreach ($departmentOptions as $dept): 
                    $isTaken = in_array($dept, $takenDepts);
                    $isCurrent = in_array($dept, $currentDepts);
                    $disabled = ($isTaken && !$isCurrent) ? 'disabled' : '';
                ?>
                <label class="check-item" style="<?= ($isTaken && !$isCurrent) ? 'opacity:0.6; cursor:not-allowed;' : '' ?>">
                    <input type="checkbox" name="department[]" class="course-check" 
                           value="<?= $dept ?>" <?= $isCurrent ? 'checked' : '' ?> <?= $disabled ?> 
                           onchange="fetchYearSections()">
                    <span><?= $dept ?></span>
                    <?php if($isTaken && !$isCurrent): ?>
                        <span class="status-taken">Occupied (PH)</span>
                    <?php endif; ?>
                </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div id="adviserBox" style="display:none;">
            <div class="section-title"><i class='bx bx-user-pin'></i> Class Scope</div>
            <div class="form-grid">
                <div class="input-group">
                    <label>Year Levels</label>
                    <div id="yearList" class="checkbox-card"><i>Select course...</i></div>
                </div>
                <div class="input-group">
                    <label>Sections</label>
                    <div id="sectionList" class="checkbox-card"><i>Select course...</i></div>
                </div>
            </div>
        </div>

        <button type="submit" name="update_signatory" class="btn-save">Save Updated Account</button>
        <div style="text-align: center; margin-top: 20px;">
            <a href="manage_signatories.php" style="color: #65676b; text-decoration: none; font-size: 14px;"><i class='bx bx-arrow-back'></i> Return to List</a>
        </div>
    </form>
</div>

<script>
// Logic para sa Show/Hide Password
const togglePassword = document.querySelector('#togglePassword');
const passwordInput = document.querySelector('#passwordInput');

togglePassword.addEventListener('click', function () {
    const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
    passwordInput.setAttribute('type', type);
    this.classList.toggle('bx-show');
    this.classList.toggle('bx-hide');
});

// Logic para sa Dynamic Fields
const savedYears = <?= json_encode($currentYears) ?>;
const savedSections = <?= json_encode($currentSections) ?>;
const signatoryId = "<?= $signatory_id ?>";

function toggleFields() {
    const type = document.getElementById('sigType').value;
    const deptBox = document.getElementById('deptBox');
    const adviserBox = document.getElementById('adviserBox');
    
    deptBox.style.display = (type === 'Program Head' || type === 'Class Adviser') ? 'block' : 'none';
    adviserBox.style.display = (type === 'Class Adviser') ? 'block' : 'none';
    
    if (type === 'Class Adviser') {
        fetchYearSections();
    }
}

function fetchYearSections() {
    const type = document.getElementById('sigType').value;
    if (type !== 'Class Adviser') return;

    let selectedCourses = [];
    document.querySelectorAll('.course-check:checked').forEach(el => selectedCourses.push(el.value));
    
    const yearList = document.getElementById('yearList');
    const sectionList = document.getElementById('sectionList');

    if(selectedCourses.length === 0) {
        yearList.innerHTML = "<i>Select course above...</i>";
        sectionList.innerHTML = "<i>Select course above...</i>";
        return;
    }

    // Kinukuha natin ang filter galing sa database para sigurado ang real-time data
    fetch('fetch_filters.php?courses=' + encodeURIComponent(selectedCourses.join(',')) + '&exclude_id=' + signatoryId)
    .then(res => res.json())
    .then(data => {
        // Render Years (Checkbox Style)
        let yearHTML = '';
        if (data.years && data.years.length > 0) {
            data.years.forEach(y => {
                let chk = savedYears.includes(y.toString()) ? 'checked' : '';
                yearHTML += `<label class="check-item"><input type="checkbox" name="year[]" value="${y}" ${chk}> ${y}</label>`;
            });
        } else {
            yearHTML = "<i>No available years found.</i>";
        }
        yearList.innerHTML = yearHTML;

        // Render Sections (Checkbox Style with Occupied status check)
        let sectionHTML = '';
        if (data.sections && data.sections.length > 0) {
            data.sections.forEach(s => {
                let sName = (typeof s === 'object') ? s.name : s;
                let sTaken = (typeof s === 'object') ? s.is_taken : false;
                
                let isCurrent = savedSections.includes(sName.toString());
                let isOccupied = sTaken && !isCurrent; 
                let disabled = isOccupied ? 'disabled' : '';

                sectionHTML += `
                    <label class="check-item" style="${isOccupied ? 'opacity:0.6; cursor:not-allowed;' : ''}">
                        <input type="checkbox" name="section[]" value="${sName}" ${isCurrent ? 'checked' : ''} ${disabled}> 
                        <span>${sName}</span>
                        ${isOccupied ? '<span class="status-taken">Occupied</span>' : ''}
                    </label>`;
            });
        } else {
            sectionHTML = "<i>No available sections found.</i>";
        }
        sectionList.innerHTML = sectionHTML;
    })
    .catch(err => {
        console.error("Error:", err);
        yearList.innerHTML = "<i>Error loading data.</i>";
        sectionList.innerHTML = "<i>Error loading data.</i>";
    });
}

document.addEventListener('DOMContentLoaded', toggleFields);
</script>
</body>
</html>