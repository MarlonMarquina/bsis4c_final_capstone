<?php
// FILE: student_dashboard.php
// FIXED: Allowed format display + no upload section for N/A requirements

session_start();
date_default_timezone_set('Asia/Manila');
if (!isset($_SESSION['role']) || $_SESSION['role'] != "student") {
    header("Location: login.php");
    exit();
}

include 'conn.php';
include 'send_email_notification.php';

$username = $_SESSION['username'];

// ==== Fetch student profile ====
$full_name = '';
$user_course = '';
$user_year = '';
$user_section = '';
$res = $conn->prepare("SELECT full_name, course, year, section FROM users WHERE username = ? LIMIT 1");
$res->bind_param("s", $username);
$res->execute();
$rres = $res->get_result();
if ($rres && $rres->num_rows > 0) {
    $row = $rres->fetch_assoc();
    $full_name = $row['full_name'] ?? '';
    $user_course = $row['course'] ?? '';
    $user_year = $row['year'] ?? '';
    $user_section = $row['section'] ?? '';
}
$res->close();
// Check email verification status + password change status
$email_check = $conn->prepare("SELECT email_verified, password_last_updated, role FROM users WHERE username = ? LIMIT 1");
$email_check->bind_param("s", $username);
$email_check->execute();
$email_check_row = $email_check->get_result()->fetch_assoc();
$email_check->close();
$email_not_verified = ($email_check_row['email_verified'] ?? 0) == 0;
$password_not_changed = is_null($email_check_row['password_last_updated'] ?? null);
$user_role = $email_check_row['role'] ?? 'student';
$default_password = ($user_role === 'signatory') ? '@Signatory01' : '@Student01';

// Fetch class adviser name for this student
$adv_stmt = $conn->prepare("
    SELECT full_name FROM users 
    WHERE role = 'signatory' 
    AND signatory_type = 'Class Adviser'
    AND status = 'active'
    AND FIND_IN_SET(
        CONCAT(?, '|', ?, '|', ?),
        REPLACE(section, ', ', ',')
    ) > 0
    LIMIT 1
");
$adv_stmt->bind_param("sss", $user_course, $user_year, $user_section);
$adv_stmt->execute();
$adv_row = $adv_stmt->get_result()->fetch_assoc();
$adv_stmt->close();
$class_adviser_name = $adv_row['full_name'] ?? '';

function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

/**
 * FIX: Robust extension extractor — handles all DB storage styles:
 *   "PDF (.pdf)"                    → [pdf]
 *   "PDF (.pdf), Word (.docx)"      → [pdf, docx]
 *   "pdf"                           → [pdf]
 *   ".pdf"                          → [pdf]
 *   "pdf,docx"                      → [pdf, docx]
 *   ".pdf,.docx"                    → [pdf, docx]
 *   "application/pdf"               → [pdf]
 *   "N/A" or ""                     → []
 */
function parseAllowedExtensions(string $formats): array {
    $formats = trim($formats);
    if ($formats === '' || strtoupper($formats) === 'N/A') return [];
    if (str_contains($formats, '.*')) return ['*']; // Any file allowed

    $exts = [];
    // Split by comma or semicolon
    $parts = preg_split('/[,;]+/', $formats);

    foreach ($parts as $part) {
        $part = trim($part);
        if ($part === '') continue;

        // Style: "PDF (.pdf)" or "Word (.docx)" — extract from parentheses
        if (preg_match('/\(\s*\.([a-zA-Z0-9]+)\s*\)/', $part, $m)) {
            $exts[] = strtolower($m[1]);
            continue;
        }

        // Style: starts with dot → ".pdf"
        if ($part[0] === '.') {
            $ext = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', substr($part, 1)));
            if ($ext) $exts[] = $ext;
            continue;
        }

        // Style: mime type like "application/pdf"
        if (str_contains($part, '/')) {
            $sub = strtolower(explode('/', $part)[1]);
            $mime_map = [
                'vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
                'vnd.openxmlformats-officedocument.spreadsheetml.sheet'       => 'xlsx',
                'vnd.ms-excel' => 'xls',
                'msword'       => 'doc',
                'jpeg'         => 'jpg',
            ];
            $exts[] = $mime_map[$sub] ?? $sub;
            continue;
        }

        // Style: plain word like "pdf" or "PDF" (not N/A)
        if (preg_match('/^[a-zA-Z0-9]+$/', $part) && strtoupper($part) !== 'N/A') {
            $exts[] = strtolower($part);
            continue;
        }
    }

    return array_unique(array_filter($exts));
}

/** Build a clean human-readable label for the formats string */
function buildFormatsLabel(string $formats): string {
    $exts = parseAllowedExtensions($formats);
    if (empty($exts)) return '';
    if (in_array('*', $exts)) return 'Any File Type';
    return implode(', ', array_map('strtoupper', $exts));
}

/** Build the accept attribute string for <input type="file"> */
function buildAcceptAttr(string $formats): string {
    $exts = parseAllowedExtensions($formats);
    if (empty($exts)) return '';
    if (in_array('*', $exts)) return '*/*'; // Accept all file types
    $parts = [];
    foreach ($exts as $ext) {
        $parts[] = '.' . $ext;
        // Add mime equivalents for better browser support
        $mime_map = [
            'pdf'  => 'application/pdf',
            'doc'  => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls'  => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png'  => 'image/png',
        ];
        if (isset($mime_map[$ext])) $parts[] = $mime_map[$ext];
    }
    return implode(',', array_unique($parts));
}

// ---- Config ----
$upload_dir = __DIR__ . '/uploads/';
if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
$maxFileSize = 30 * 1024 * 1024; // 30MB limit

// === Actions ===
$action = $_POST['action'] ?? ($_GET['action'] ?? null);

// ---------- Add Draft ----------
// ---------- Add Draft ----------
if ($action === 'add_draft') {
    $raw_input = $_POST['requirement_id'] ?? '';
    $parts = explode('_', $raw_input);
    $requirement_id = intval($parts[0] ?? 0);
    $target_signatory_id = isset($parts[1]) ? intval($parts[1]) : 0;

    if ($target_signatory_id <= 0) {
        echo "<script>alert('Please select a valid signatory.'); window.location.href='student_dashboard.php';</script>";
        exit;
    }

    $stmt = $conn->prepare("
        SELECT cr.signatory_id, cr.document_type_id, cr.requirement_id
        FROM course_requirements cr
        WHERE cr.signatory_id = ? 
          AND cr.course_id = (SELECT id FROM courses WHERE course_name = ? LIMIT 1)
          AND cr.requirements_configured = 1
          " . ($requirement_id > 0 ? "AND cr.requirement_id = ?" : "AND (cr.requirement_id = 0 OR cr.requirement_id IS NULL)") . "
        LIMIT 1
    ");

    if ($requirement_id > 0) {
        $stmt->bind_param("isi", $target_signatory_id, $user_course, $requirement_id);
    } else {
        $stmt->bind_param("is", $target_signatory_id, $user_course);
    }

    $stmt->execute();
    $rr = $stmt->get_result();

    if ($rr && $rr->num_rows > 0) {
        $row = $rr->fetch_assoc();
        $sig_id = intval($row['signatory_id']);
        $document_formats = trim($row['document_type_id'] ?? '');
        $requirement_id = intval($row['requirement_id'] ?? 0); // ✅ FIX: Get requirement_id from database!
    } else {
        echo "<script>alert('This requirement is not available for your course.'); window.location.href='student_dashboard.php';</script>";
        exit;
    }
    $stmt->close();

    // N/A = no file needed
    $is_no_file_required = (strtoupper(trim($document_formats)) === 'N/A' || empty($document_formats));

    $hasFile = (isset($_FILES['file']) && $_FILES['file']['error'] === 0);
    $newFile = null;

    if ($is_no_file_required) {
        // No file needed — skip all file validation
    } else {
        if (!$hasFile) {
            echo "<script>alert('⚠️ File upload is required for this requirement.'); window.location.href='student_dashboard.php';</script>";
            exit;
        }

        $uploaded_name = $_FILES['file']['name'];
        $tmp           = $_FILES['file']['tmp_name'];
        $size          = $_FILES['file']['size'];
        $ext           = strtolower(pathinfo($uploaded_name, PATHINFO_EXTENSION));

        if ($size > $maxFileSize) {
            echo "<script>alert('❌ File too large! Maximum size is 30MB.'); window.location.href='student_dashboard.php';</script>";
            exit;
        }

        // FIX: Use robust parser
        $allowed_formats = parseAllowedExtensions($document_formats);

        // Flexible extension synonyms
        $ext_variations = [$ext];
        if ($ext === 'doc')  $ext_variations[] = 'docx';
        if ($ext === 'docx') $ext_variations[] = 'doc';
        if ($ext === 'jpg')  $ext_variations[] = 'jpeg';
        if ($ext === 'jpeg') $ext_variations[] = 'jpg';

        $is_valid = false;
        if (in_array('*', $allowed_formats)) {
            $is_valid = true; // Any file type allowed
        } else {
            foreach ($ext_variations as $variation) {
                if (in_array($variation, $allowed_formats)) { $is_valid = true; break; }
            }
        }

        if (!$is_valid) {
            $allowed_display = !empty($allowed_formats)
                ? implode(', ', array_map('strtoupper', $allowed_formats))
                : $document_formats;
            echo "<script>alert('❌ Invalid file type!\\nAllowed formats: " . addslashes($allowed_display) . "'); window.location.href='student_dashboard.php';</script>";
            exit;
        }

        $base    = preg_replace('/[^A-Za-z0-9_\-]/', '_', pathinfo($uploaded_name, PATHINFO_FILENAME));
        $newFile = $base . '_' . time() . '.' . $ext;

        if (!move_uploaded_file($tmp, $upload_dir . $newFile)) {
            echo "<script>alert('❌ Failed to save uploaded file.'); window.location.href='student_dashboard.php';</script>";
            exit;
        }
    }

    // ✅ INSERT INTO DATABASE
    $ins = $conn->prepare("INSERT INTO draft_requirements (username, requirement_id, signatory_id, document_type_id, file_name) VALUES (?, ?, ?, ?, ?)");
    $ins->bind_param("siiss", $username, $requirement_id, $sig_id, $document_formats, $newFile);
    $ins->execute();
    $ins->close();

    echo "<script>alert('✅ Added to Preview. Click Submit when ready.'); window.location.href='student_dashboard.php';</script>";
    exit;
}

// ---------- Edit Draft ----------
if ($action === 'edit_draft') {
    $draft_id = intval($_POST['draft_id'] ?? 0);
    if ($draft_id <= 0) { header("Location: student_dashboard.php"); exit(); }

    $stmt = $conn->prepare("SELECT d.id, d.file_name, d.document_type_id FROM draft_requirements d WHERE d.id = ? AND d.username = ? LIMIT 1");
    $stmt->bind_param("is", $draft_id, $username);
    $stmt->execute();
    $resd = $stmt->get_result();
    if (!$resd || $resd->num_rows === 0) {
        echo "<script>alert('Draft not found.'); window.location.href='student_dashboard.php';</script>";
        exit;
    }
    $dr = $resd->fetch_assoc();
    $stmt->close();

    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== 0) {
        echo "<script>alert('Please choose a file to replace.'); window.location.href='student_dashboard.php';</script>";
        exit;
    }

    $uploaded_name = $_FILES['file']['name'];
    $tmp = $_FILES['file']['tmp_name'];
    $size = $_FILES['file']['size'];
    $ext = strtolower(pathinfo($uploaded_name, PATHINFO_EXTENSION));

    if ($size > $maxFileSize) {
        echo "<script>alert('❌ File too large! Maximum size is 30MB.'); window.location.href='student_dashboard.php';</script>";
        exit;
    }
    
    $base = pathinfo($uploaded_name, PATHINFO_FILENAME);
    $base = preg_replace('/[^A-Za-z0-9_\-]/','_', $base);
    $newFile = $base . '_' . time() . '.' . $ext;
    
    if (move_uploaded_file($tmp, $upload_dir . $newFile)) {
        if (!empty($dr['file_name']) && is_file($upload_dir . $dr['file_name'])) {
            @unlink($upload_dir . $dr['file_name']);
        }
        $u = $conn->prepare("UPDATE draft_requirements SET file_name = ?, created_at = NOW() WHERE id = ? AND username = ?");
        $u->bind_param("sis", $newFile, $draft_id, $username);
        $u->execute();
        echo "<script>alert('✅ Draft updated.'); window.location.href='student_dashboard.php';</script>";
    } else {
        echo "<script>alert('❌ Failed to save file.'); window.location.href='student_dashboard.php';</script>";
    }
    exit;
}

// ---------- Delete Draft ----------
if ($action === 'delete_draft' || isset($_GET['delete_draft'])) {
    $del = intval($_POST['draft_id'] ?? $_GET['delete_draft'] ?? 0);
    if ($del > 0) {
        $s = $conn->prepare("SELECT file_name FROM draft_requirements WHERE id = ? AND username = ?");
        $s->bind_param("is", $del, $username);
        $s->execute();
        $sr = $s->get_result();
        if ($sr && $sr->num_rows > 0) {
            $rr = $sr->fetch_assoc();
            if (!empty($rr['file_name']) && is_file($upload_dir . $rr['file_name'])) @unlink($upload_dir . $rr['file_name']);
        }
        $d = $conn->prepare("DELETE FROM draft_requirements WHERE id = ? AND username = ?");
        $d->bind_param("is", $del, $username);
        $d->execute();
    }
    echo "<script>window.location.href='student_dashboard.php';</script>";
    exit;
}

// ---------- Submit Single Draft ----------
if ($action === 'submit_draft') {
    $draft_id = intval($_POST['draft_id'] ?? 0);
    if ($draft_id <= 0) { header("Location: student_dashboard.php"); exit(); }

    $q = $conn->prepare("
        SELECT d.id, d.file_name, d.requirement_id, d.signatory_id,
               u.username AS signatory_username, u.signatory_type,
               d.document_type_id
        FROM draft_requirements d
        LEFT JOIN users u ON d.signatory_id = u.id
        WHERE d.id = ? AND d.username = ?
        LIMIT 1
    ");
    $q->bind_param("is", $draft_id, $username);
    $q->execute();
    $resq = $q->get_result();

    if (!$resq || $resq->num_rows === 0) {
        echo "<script>alert('Draft not found.'); window.location.href='student_dashboard.php';</script>";
        exit;
    }
    $dr = $resq->fetch_assoc();
    $q->close();

    $document_formats    = trim($dr['document_type_id'] ?? '');
    $file_to_submit      = $dr['file_name'];
    $is_no_file_required = (strtoupper(trim($document_formats)) === 'N/A' || empty($document_formats));

    if (!$is_no_file_required && empty($file_to_submit)) {
        echo "<script>alert('❌ Cannot submit: This requirement needs a file upload.'); window.location.href='student_dashboard.php';</script>";
        exit;
    }

    $file_to_submit      = empty($file_to_submit) ? 'N/A' : $file_to_submit;
    $signatory_username  = $dr['signatory_username'];

    $requirement_id_for_app = intval($dr['requirement_id'] ?? 0);
    $auto_status  = $is_no_file_required ? 'Approved' : 'Pending';
    $reviewed_val = $is_no_file_required ? date('Y-m-d H:i:s') : null;

    $stmt = $conn->prepare("INSERT INTO applications (username, signatory, course, requirement_id, document, status, submitted_at, reviewed_at) VALUES (?, ?, ?, ?, ?, ?, NOW(), ?)");
    $stmt->bind_param("sssisss", $username, $signatory_username, $user_course, $requirement_id_for_app, $file_to_submit, $auto_status, $reviewed_val);

    if ($stmt->execute()) {
        $app_id = $conn->insert_id;
        $stmt->close();

        // Log to signatory_history if auto-approved
        if ($is_no_file_required) {
            $system = 'System / Auto-Approved';
            $log = $conn->prepare(
                "INSERT INTO signatory_history (signatory_username, student_user, action, reason, remarks)
                 VALUES (?, ?, 'Approved', 'Auto-approved: no file required', ?)"
            );
            $log->bind_param("sss", $signatory_username, $username, $file_to_submit);
            $log->execute();
            $log->close();

            // Notify student
            $msg = "Your application (ID: {$app_id}) was automatically approved (no file required).";
            $notif = $conn->prepare(
                "INSERT INTO notifications (username, message, type, created_at) VALUES (?, ?, 'success', NOW())"
            );
            $notif->bind_param("ss", $username, $msg);
            $notif->execute();
            $notif->close();
        }

        $del = $conn->prepare("DELETE FROM draft_requirements WHERE id = ? AND username = ?");
        $del->bind_param("is", $draft_id, $username);
        $del->execute();
        $success_msg = $is_no_file_required
            ? '✅ Automatically approved! No file was required.'
            : '✅ Submitted! Waiting for signatory approval.';
        echo "<script>alert(" . json_encode($success_msg) . "); window.location.href='student_dashboard.php';</script>";
    } else {
        $stmt->close();
        echo "<script>alert('❌ Error submitting.'); window.location.href='student_dashboard.php';</script>";
    }
    exit;
}

// ---------- Submit All ----------
if ($action === 'submit_all') {
    $fetch = $conn->prepare("
        SELECT d.id, d.file_name, d.requirement_id, d.signatory_id,
               u.username AS signatory_username,
               d.document_type_id
        FROM draft_requirements d
        LEFT JOIN users u ON d.signatory_id = u.id
        WHERE d.username = ?
    ");
    $fetch->bind_param("s", $username);
    $fetch->execute();
    $fr      = $fetch->get_result();
    $submit  = 0;
    $skipped = 0;

    if ($fr) {
        while ($dr = $fr->fetch_assoc()) {
            $document_formats    = trim($dr['document_type_id'] ?? '');
            $file_to_submit      = $dr['file_name'];
            $is_no_file_required = (strtoupper(trim($document_formats)) === 'N/A' || empty($document_formats));

            if (!$is_no_file_required && empty($file_to_submit)) {
                $skipped++;
                continue;
            }

            $file_to_submit     = empty($file_to_submit) ? 'N/A' : $file_to_submit;
            $signatory_username = $dr['signatory_username'] ?? 'Unknown';
            $requirement_id_for_app = intval($dr['requirement_id'] ?? 0); // ✅ NOW IT WILL GET THE CORRECT ID

            $auto_status  = $is_no_file_required ? 'Approved' : 'Pending';
            $reviewed_val = $is_no_file_required ? date('Y-m-d H:i:s') : null;

            $ins = $conn->prepare("INSERT INTO applications (username, signatory, course, requirement_id, document, status, submitted_at, reviewed_at) VALUES (?, ?, ?, ?, ?, ?, NOW(), ?)");
            $ins->bind_param("sssisss", $username, $signatory_username, $user_course, $requirement_id_for_app, $file_to_submit, $auto_status, $reviewed_val);

            if ($ins->execute()) {
                $app_id = $conn->insert_id;
                $ins->close();

                // Log and notify if auto-approved
                if ($is_no_file_required) {
                    $log = $conn->prepare(
                        "INSERT INTO signatory_history (signatory_username, student_user, action, reason, remarks)
                         VALUES (?, ?, 'Approved', 'Auto-approved: no file required', ?)"
                    );
                    $log->bind_param("sss", $signatory_username, $username, $file_to_submit);
                    $log->execute();
                    $log->close();

                    $msg = "Your application (ID: {$app_id}) was automatically approved (no file required).";
                    $notif = $conn->prepare(
                        "INSERT INTO notifications (username, message, type, created_at) VALUES (?, ?, 'success', NOW())"
                    );
                    $notif->bind_param("ss", $username, $msg);
                    $notif->execute();
                    $notif->close();
                }

                $del = $conn->prepare("DELETE FROM draft_requirements WHERE id = ? AND username = ?");
                $del->bind_param("is", $dr['id'], $username);
                $del->execute();
                $submit++;
            } else {
                $ins->close();
            }
        }
    }
    $fetch->close();

    $msg = "✅ Submitted {$submit} item(s). Waiting for signatory approval.";
    if ($skipped > 0) $msg .= " ⚠️ Skipped {$skipped} draft(s) that need a file upload.";
    echo "<script>alert(" . json_encode($msg) . "); window.location.href='student_dashboard.php';</script>";
    exit;
}

// --- FETCH DATA FOR DISPLAY ---

// 1. Get already submitted signatories to prevent duplicates
$submitted_combo_keys = [];
$submitted_q = $conn->prepare("
    SELECT DISTINCT u.id AS signatory_id, a.requirement_id, a.status
    FROM applications a 
    JOIN users u ON a.signatory = u.username 
    WHERE a.username = ? 
    AND a.status IN ('Pending', 'Approved', 'Requires Action', 'Totally Rejected')
");
$submitted_q->bind_param("s", $username);
$submitted_q->execute();
$submitted_res = $submitted_q->get_result();
$totally_rejected_keys = [];
if ($submitted_res) {
    while ($row = $submitted_res->fetch_assoc()) {
        $key = intval($row['requirement_id']) . '_' . intval($row['signatory_id']);
        $submitted_combo_keys[] = $key;
        if ($row['status'] === 'Totally Rejected') {
            $totally_rejected_keys[] = $key;
        }
    }
}
$submitted_q->close();

// --- Check if Class Adviser is fully cleared before allowing Program Head ---
// Get all Class Adviser requirements for this student's course/year/section
$ca_total = 0;
$ca_approved = 0;
$ca_req_stmt = $conn->prepare("
    SELECT cr.requirement_id, cr.signatory_id
    FROM course_requirements cr
    JOIN users u ON cr.signatory_id = u.id
    WHERE cr.course_id = (SELECT id FROM courses WHERE course_name = ? LIMIT 1)
    AND u.signatory_type = 'Class Adviser'
    AND u.status = 'active'
    AND cr.requirements_configured = 1
    AND (cr.year_level = ? OR cr.year_level = 'All Years')
    AND (cr.sections LIKE ? OR cr.sections = 'All Sections' OR cr.sections = 'All Sections (Handled)')
");
$ca_section_like = '%' . $user_section . '%';
$ca_req_stmt->bind_param("sss", $user_course, $user_year, $ca_section_like);
$ca_req_stmt->execute();
$ca_res = $ca_req_stmt->get_result();
while ($ca_row = $ca_res->fetch_assoc()) {
    $ca_total++;
    $ca_key = intval($ca_row['requirement_id']) . '_' . intval($ca_row['signatory_id']);
    // Check if this combo is approved in submitted keys
    $chk = $conn->prepare("
        SELECT a.status FROM applications a
        JOIN users u ON a.signatory = u.username
        WHERE a.username = ? AND u.id = ? AND a.requirement_id = ?
        AND a.status = 'Approved'
        LIMIT 1
    ");
    $chk_sid = intval($ca_row['signatory_id']);
    $chk_rid = intval($ca_row['requirement_id']);
    $chk->bind_param("sii", $username, $chk_sid, $chk_rid);
    $chk->execute();
    if ($chk->get_result()->num_rows > 0) $ca_approved++;
    $chk->close();
}
$ca_req_stmt->close();
$class_adviser_cleared = ($ca_total === 0 || $ca_approved >= $ca_total);

// Dynamic prerequisite check function
function isSignatoryUnlocked($conn, $username, $user_course, $signatory_type, $course_id) {
    // Check for active prerequisites for this signatory type + course
    $prereq_q = $conn->prepare("
        SELECT before_type
        FROM signatory_prerequisites
        WHERE course_id = ?
        AND signatory_type = ?
        AND admin_enabled = 1
        AND signatory_enabled = 1
    ");
    $prereq_q->bind_param("is", $course_id, $signatory_type);
    $prereq_q->execute();
    $prereq_res = $prereq_q->get_result();
    $before_types = [];
    while ($p = $prereq_res->fetch_assoc()) $before_types[] = $p['before_type'];
    $prereq_q->close();

    // No dynamic prereqs — signal fallback
    if (empty($before_types)) return null;

    foreach ($before_types as $before_type) {
        // Get all signatories of this before_type assigned to this course
        $sigs_q = $conn->prepare("
            SELECT DISTINCT u.id, u.username
            FROM course_requirements cr
            JOIN users u ON cr.signatory_id = u.id
            WHERE cr.course_id = ?
            AND u.signatory_type = ?
            AND u.status = 'active'
            AND cr.requirements_configured = 1
        ");
        $sigs_q->bind_param("is", $course_id, $before_type);
        $sigs_q->execute();
        $sigs_res = $sigs_q->get_result();
        $before_sigs = [];
        while ($s = $sigs_res->fetch_assoc()) $before_sigs[] = $s;
        $sigs_q->close();

        // If no signatories of this type assigned — still blocked (not configured)
        if (empty($before_sigs)) return false;

        foreach ($before_sigs as $bsig) {
            // Count total requirements for this signatory
            $total_q = $conn->prepare("
                SELECT COUNT(*) as cnt FROM course_requirements
                WHERE signatory_id = ? AND course_id = ? AND requirements_configured = 1
            ");
            $total_q->bind_param("ii", $bsig['id'], $course_id);
            $total_q->execute();
            $total_cnt = (int)$total_q->get_result()->fetch_assoc()['cnt'];
            $total_q->close();

           if ($total_cnt === 0) return false; // Not configured = not cleared = still blocked

            // Count approved requirements from this signatory for this student
            $approved_q = $conn->prepare("
                SELECT COUNT(DISTINCT a.requirement_id) as cnt
                FROM applications a
                WHERE a.username = ? AND a.signatory = ? AND a.status = 'Approved'
            ");
            $approved_q->bind_param("ss", $username, $bsig['username']);
            $approved_q->execute();
            $approved_cnt = (int)$approved_q->get_result()->fetch_assoc()['cnt'];
            $approved_q->close();

            if ($approved_cnt < $total_cnt) return false; // Still blocked
        }
    }

    return true; // All prerequisites cleared
}

// Get course_id for dynamic checks
$course_id_for_prereq = 0;
$cid_q = $conn->prepare("SELECT id FROM courses WHERE course_name = ? LIMIT 1");
$cid_q->bind_param("s", $user_course);
$cid_q->execute();
$cid_row = $cid_q->get_result()->fetch_assoc();
$cid_q->close();
$course_id_for_prereq = intval($cid_row['id'] ?? 0);
$prereq_label_cache = [];
// 2. Fetch Requirements available for this course
$requirements = [];
$req_stmt = $conn->prepare("
    SELECT 
        cr.requirement_id AS id, 
        COALESCE(rl.requirement_name, 'No Requirement Set') AS requirement_name,
        cr.signatory_id, 
        u.username AS signatory_username, 
        u.signatory_type, 
        u.full_name AS signatory_full_name,
        cr.document_type_id
    FROM course_requirements cr
    LEFT JOIN requirement_library rl ON cr.requirement_id = rl.id
    JOIN users u ON cr.signatory_id = u.id
    WHERE cr.course_id = (SELECT id FROM courses WHERE course_name = ? LIMIT 1)
    AND cr.requirements_configured = 1
    AND u.status = 'active'
    AND u.role = 'signatory'
    AND (cr.year_level = ? OR cr.year_level = 'All Years')
    AND (cr.sections LIKE ? OR cr.sections = 'All Sections' OR cr.sections = 'All Sections (Handled)')
    ORDER BY u.signatory_type ASC, COALESCE(rl.requirement_name, 'No Requirement Set') ASC
");
$section_like = '%' . $user_section . '%';
$req_stmt->bind_param("sss", $user_course, $user_year, $section_like);
$req_stmt->execute();
$rr = $req_stmt->get_result();
if ($rr) while ($r = $rr->fetch_assoc()) $requirements[] = $r;
$req_stmt->close();

// 3. Fetch Current Drafts
$drafts = [];
$draft_unique_keys = [];
$dq = $conn->prepare("
    SELECT d.*, rl.requirement_name, u.username AS signatory_username, u.signatory_type, d.document_type_id
    FROM draft_requirements d 
    LEFT JOIN requirement_library rl ON d.requirement_id = rl.id 
    LEFT JOIN users u ON d.signatory_id = u.id 
    WHERE d.username = ? 
    ORDER BY d.created_at DESC
");
$dq->bind_param("s", $username);
$dq->execute();
$dres = $dq->get_result();
if ($dres) {
    while($row = $dres->fetch_assoc()) {
        $drafts[] = $row;
        $draft_unique_keys[] = intval($row['requirement_id']) . '_' . intval($row['signatory_id']); 
    }
}
$dq->close();

// 4. Stats
$total = 0; $pending = 0; $approved = 0; $requires_action = 0;
$stats_res = $conn->prepare("SELECT status, COUNT(*) as count FROM applications WHERE username = ? GROUP BY status");
$stats_res->bind_param("s", $username);
$stats_res->execute();
$sr = $stats_res->get_result();
if ($sr) {
    while($row = $sr->fetch_assoc()) {
        switch($row['status']){
            case 'Pending': $pending=$row['count']; break;
            case 'Approved': $approved=$row['count']; break;
            case 'Requires Action': $requires_action=$row['count']; break;
        }
        $total += $row['count'];
    }
}
$stats_res->close();

// 5. Signatory Map (for display)
$signatory_types_map = [];
$mapq = $conn->query("SELECT username, signatory_type FROM users WHERE role='signatory'");
if ($mapq) while($m = $mapq->fetch_assoc()) $signatory_types_map[$m['username']] = $m['signatory_type'];

// 6. Fetch Applications History (hide if requirement deleted)
$applications = [];
$app_res = $conn->prepare("
    SELECT a.id, a.signatory, a.course, a.document, a.status, 
           a.rejection_reason, a.submitted_at, a.reviewed_at,
           a.rejection_count,
           COALESCE(rl.requirement_name, 'N/A') AS requirement_name
    FROM applications a
    LEFT JOIN requirement_library rl ON a.requirement_id = rl.id
    WHERE a.username = ? 
    AND EXISTS (
        SELECT 1 FROM course_requirements cr
        JOIN users u ON cr.signatory_id = u.id
        WHERE u.username = a.signatory
        AND cr.course_id = (SELECT id FROM courses WHERE course_name = a.course LIMIT 1)
        AND cr.requirements_configured = 1
        AND (cr.requirement_id = a.requirement_id OR a.requirement_id = 0)
    )
    ORDER BY a.submitted_at DESC
");
$app_res->bind_param("s", $username);
$app_res->execute();
$ar = $app_res->get_result();
if ($ar) while($row = $ar->fetch_assoc()) $applications[] = $row;
$app_res->close();

// Pre-build JS data for requirements (formats + accept strings) — avoids inline escaping issues
$req_js_data = [];
foreach ($requirements as $r) {
    $fmt   = trim($r['document_type_id'] ?? '');
    $isNA  = (strtoupper($fmt) === 'N/A' || $fmt === '');
    $label = !$isNA ? buildFormatsLabel($fmt) : '';
    $accept= !$isNA ? buildAcceptAttr($fmt)   : '';
    $req_js_data[intval($r['id']) . '_' . intval($r['signatory_id'])] = [
        'isNoFile' => $isNA,
        'label'    => $label,
        'accept'   => $accept,
        'raw'      => $fmt,
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Student Dashboard | Smart Clearance System</title>
<link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
<link rel="stylesheet" href="styles.css">
<style>
.clearance-container{width:90%;margin:30px auto;background:rgba(255,255,255,0.95);border-radius:10px;padding:20px;text-align:center;box-shadow:0 4px 8px rgba(0,0,0,0.1);}
.clearance-header{display:flex;justify-content:center;align-items:center;background:darkgreen;color:white;border-radius:25px;padding:10px 20px;margin-bottom:30px;}
.clearance-header h2{font-size:20px;font-weight:700;}
.new-application-btn{background:royalblue;color:white;border:none;padding:8px 15px;border-radius:8px;font-weight:500;cursor:pointer;transition:0.3s;}
.new-application-btn:hover{background:#0d47a1;}
.stats-container{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:20px;justify-content:center;margin-bottom:30px;}
.stat-card{background:#fff;border:1px solid #ddd;border-radius:10px;padding:20px;text-align:center;transition:0.3s;}
.stat-card:hover{transform:translateY(-5px);box-shadow:0 4px 12px rgba(0,0,0,0.15);}
.stat-card .icon{font-size:40px;color:darkgreen;margin-bottom:10px;}
.stat-info h3{font-size:16px;font-weight:600;color:#333;}
.stat-info p{font-size:20px;font-weight:700;margin-top:5px;}
.container{display:grid;justify-items:end;margin-right:50px;}
.modal{display:none;position:fixed;z-index:3000;left:0;top:0;width:100%;height:100%;background:rgba(0,0,0,0.6);justify-content:center;align-items:center;}
.modal-content{background:#fff;padding:25px 30px;border-radius:12px;width:90%;max-width:600px;position:relative;animation:slideIn 0.3s ease;box-shadow:0 5px 20px rgba(0,0,0,0.3);}
.modal-content h3{color:darkgreen;margin-bottom:10px;text-align:center;display:block;}
.modal-content p{color:#444;font-size:14px;margin-bottom:20px;text-align:center;}
.close{position:absolute;right:20px;font-size:28px;cursor:pointer;color:#555;}
.close:hover{color:#000;}
.application-form label{display:block;font-weight:600;margin-top:10px;margin-bottom:5px;}
.application-form select,.application-form input[type="file"]{width:100%;padding:10px;border-radius:8px;border:1px solid #ccc;font-size:14px;}
.requirement-signatory-header{display:flex;justify-content:flex-start;font-weight:600;margin-bottom:5px;margin-top:15px;padding-left:10px;}
.requirement-signatory-header h4{color:darkgreen;margin:0;font-size:14px;font-weight:700;white-space:nowrap;}
.application-form select optgroup,.application-form select option{padding:8px 10px;border-bottom:1px solid #eee;}
.upload-box{margin-top:12px;border:2px dashed #00a859;border-radius:10px;padding:24px 20px;text-align:center;background:#f9f9f9;transition:0.3s;cursor:pointer;}
.upload-box:hover{background:#f0f9f0;}
.upload-box input[type="file"]{display:none;}
.upload-box-inner i{font-size:38px;color:darkgreen;}
.upload-box-inner strong{display:block;margin:6px 0 4px;color:#222;}
/* Format badges */
.format-badges{display:flex;flex-wrap:wrap;gap:6px;justify-content:center;margin-top:8px;}
.format-badge{background:#e8f5e9;border:1px solid #4CAF50;color:#1b5e20;border-radius:20px;padding:3px 12px;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;}
/* File chosen name */
.file-chosen{display:none;margin-top:8px;padding:6px 12px;background:#e3f2fd;border-radius:6px;font-size:13px;color:#0069c0;font-weight:600;word-break:break-all;}
/* Max size note */
.max-size-note{font-size:12px;color:#e53935;margin-top:4px;font-weight:600;}
.btn-group{display:flex;justify-content:space-between;margin-top:20px;}
.cancel-btn,.submit-btn{width:48%;padding:10px;font-weight:600;border-radius:8px;border:none;cursor:pointer;transition:0.3s;}
.cancel-btn{background:#ddd;color:#222;}
.cancel-btn:hover{background:#bbb;}
.submit-btn{background:darkgreen;color:#fff;}
.submit-btn:hover{background:#0d6d30;}
/* No file info box */
.no-file-box{display:none;margin-top:16px;background:#d4edda;border:2px solid #28a745;border-radius:10px;padding:20px;text-align:center;}
.no-file-box i{font-size:44px;color:#28a745;}
.no-file-box h4{color:#155724;margin:8px 0 4px;}
.no-file-box p{color:#155724;font-size:13px;margin:0;}
@keyframes slideIn{from{transform:translateY(-30px);opacity:0;}to{transform:translateY(0);opacity:1;}}
table{border-collapse:collapse;width:100%;margin-top:10px;}
table th,table td{padding:8px;border:1px solid #ddd;text-align:center;}
table th{background:darkgreen;color:white;}
.draft-actions button{margin:0 4px;padding:6px 8px;border-radius:6px;border:none;cursor:pointer}
.draft-edit{background:#4CAF50;color:#fff}
.draft-delete{background:#e74c3c;color:#fff}
.draft-submit{background:#00a859;color:#fff}
.submit-all{background:#0069c0;color:#fff;padding:8px 12px;border-radius:6px;border:none;cursor:pointer}
.status.RequiresAction{background:purple;color:white;}
.resubmit-alert{border:2px solid #d9534f;padding:15px;margin-top:15px;margin-bottom:15px;background:#fde8e8;border-radius:8px;text-align:left;}
.resubmit-alert h4{color:#d9534f;margin-top:0;}
.resubmit-alert p{margin-top:5px;margin-bottom:10px;color:#333;}
.resubmit-alert p strong{color:#a94442;}
.resubmit-btn{background:orange;color:white;padding:6px 12px;border:none;border-radius:6px;cursor:pointer;}
.resubmit-btn:hover{background:#e09200;}
.welcome-modal { display:none; position:fixed; z-index:9999; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.6); justify-content:center; align-items:center; }
.welcome-modal-content { background:#fff; padding:30px; border-radius:14px; width:90%; max-width:480px; text-align:center; box-shadow:0 10px 30px rgba(0,0,0,0.3); }
.welcome-modal-content h3 { color:darkgreen; margin-bottom:10px; }
.welcome-modal-content p { color:#444; font-size:14px; margin-bottom:8px; }
.welcome-modal-btns { display:flex; gap:10px; margin-top:20px; justify-content:center; }
.wm-btn-later { background:#ddd; color:#333; border:none; padding:10px 20px; border-radius:8px; cursor:pointer; font-weight:600; }
.wm-btn-now { background:darkgreen; color:#fff; border:none; padding:10px 20px; border-radius:8px; cursor:pointer; font-weight:600; }
.wm-btn-later:hover { background:#bbb; }
.wm-btn-now:hover { background:#0b4e12; }
nav.sidebar {
    position: fixed;
    top: 0;
    left: 0;
    height: 100%;
    overflow-y: auto;
}
</style>
</head>
<body>
<nav class="sidebar close">
<header>
<div class="image-text">
<span class="image"><img src="bpc-logo.png" alt="logo"></span>
<div class="text header-text">
<span class="name"><?= h($full_name) ?></span>
<span class="role"><?= h($user_course) ?> <?= h($user_year) ?> - <?= h($user_section) ?></span>
</div>
</div>
<i class='bx bx-chevron-right toggle'></i>
</header>
<div class="menu-bar">
<div class="menu">
<ul class="menu-links">
<li class="nav-link"><a href="student_dashboard.php"><i class='bx bx-home-alt icon'></i><span class="text nav-text">Dashboard</span></a></li>
<li class="nav-link"><a href="student_profile.php"><i class='bx bx-user icon'></i><span class="text nav-text">Profile</span></a></li>
<li class="nav-link"><a href="student_history.php"><i class='bx bx-history icon'></i><span class="text nav-text">History</span></a></li>
<li class="nav-link"><a href="student_notifications.php"><i class='bx bx-bell icon'></i><span class="text nav-text">Notifications</span></a></li>
</ul>
</div>
<div class="bottom-content">
<li class="nav-link"><a href="logout.php" onclick="return confirm('Are you sure you want to logout?');"><i class='bx bx-log-out icon'></i><span class="text nav-text">Logout</span></a></li>
</div>
</div>
</nav>

<section class="home">
<div class="text">
<div class="clearance-header" style="flex-direction: column; gap: 4px;">
    <h2><?= strtoupper(h($full_name)) ?> CLEARANCE</h2>
    <div style="font-size: 13px; font-weight: 500; opacity: 0.85; display: flex; gap: 14px; flex-wrap: wrap; justify-content: center;">
        <?php if ($user_course): ?>
        <span><i class='bx bx-book-bookmark' style="vertical-align: middle;"></i> <?= h($user_course) ?></span>
        <?php endif; ?>
        <?php if ($user_year): ?>
        <span><i class='bx bx-graduation' style="vertical-align: middle;"></i> <?= h($user_year) ?></span>
        <?php endif; ?>
        <?php if ($user_section): ?>
        <span><i class='bx bx-group' style="vertical-align: middle;"></i> Section <?= h($user_section) ?></span>
        <?php endif; ?>
        <?php if ($class_adviser_name): ?>
        <span><i class='bx bx-chalkboard' style="vertical-align: middle;"></i> Adviser: <?= h($class_adviser_name) ?></span>
        <?php endif; ?>
    </div>
</div>
<div class="container">
<button class="new-application-btn" id="openCreateDraft">+ Comply</button>
</div>
</div>

<div class="clearance-container">
<div class="stats-container">
<div class="stat-card"><i class='bx bx-file icon'></i><div class="stat-info"><h3>Total Applications</h3><p><?= $total ?></p></div></div>
<div class="stat-card"><i class='bx bx-time-five icon'></i><div class="stat-info"><h3>Pending</h3><p><?= $pending ?></p></div></div>
<div class="stat-card"><i class='bx bx-check-circle icon' style="color:green;"></i><div class="stat-info"><h3>Approved</h3><p><?= $approved ?></p></div></div>
<div class="stat-card"><i class='bx bx-error-circle icon' style="color:red;"></i><div class="stat-info"><h3>Requires Action</h3><p><?= $requires_action ?></p></div></div>
</div>

<h3 style="color:darkgreen; margin-top:10px;">Preview Application(s)</h3>
<?php if (count($drafts) > 0): ?>
<div style="text-align:right; margin-bottom:8px;">
    <button class="submit-all" id="submitAllBtn">Submit All</button>
</div>
<?php endif; ?>

<table>
<thead>
<tr><th>Requirement</th><th>Signatory</th><th>File</th><th>Added</th><th>Action</th></tr></thead>
<tbody id="draftsTable">
<?php if (count($drafts) === 0): ?>
<tr><td colspan="5">No draft items yet.</td></tr>
<?php else: foreach($drafts as $d): ?>
<tr>
<td><?= h($d['requirement_name'] ?? 'N/A') ?></td>
<td><?= h($d['signatory_type'] ?? $d['signatory_username'] ?? '') ?></td>
<td>
<?php if(!empty($d['file_name'])): ?>
<a href="uploads/<?= h($d['file_name']) ?>" target="_blank">View</a>
<?php else: ?>
<span style="color:#888;">No file</span>
<?php endif; ?>
</td>
<td><?= h($d['created_at']) ?></td>
<td class="draft-actions">
    <?php if (!empty($d['file_name'])): ?>
<form method="POST" enctype="multipart/form-data" style="display:inline-block">
    <input type="hidden" name="action" value="edit_draft">
    <input type="hidden" name="draft_id" value="<?= intval($d['id']) ?>">
    <?php 
    $draft_formats = trim($d['document_type_id'] ?? '');
    $draft_accept = buildAcceptAttr($draft_formats);
    ?>
    <input type="file" name="file" required 
           <?= !empty($draft_accept) ? 'accept="' . h($draft_accept) . '"' : '' ?>
           style="display:inline-block; max-width: 120px;">
    <button type="submit" class="draft-edit">Replace</button>
</form>
<?php endif; ?>

    <form method="POST" style="display:inline-block" onsubmit="return confirmAndDisable(this, 'Delete this draft?')">
    <input type="hidden" name="action" value="delete_draft">
    <input type="hidden" name="draft_id" value="<?= intval($d['id']) ?>">
    <button type="submit" class="draft-delete">Delete</button>
</form>

    <form method="POST" style="display:inline-block" onsubmit="return confirmAndDisable(this, 'Submit this application?')">
    <input type="hidden" name="action" value="submit_draft">
    <input type="hidden" name="draft_id" value="<?= intval($d['id']) ?>">
    <button type="submit" class="draft-submit">Submit</button>
</form>
</td>
</tr>
<?php endforeach; endif; ?>
</tbody>
</table>

<!-- ======== CREATE DRAFT MODAL ======== -->
<div id="createDraftModal" class="modal">
<div class="modal-content">
<span class="close" id="closeCreateDraft">&times;</span>
<h3>Add Clearance Request</h3>

<form id="createDraftForm" method="POST" enctype="multipart/form-data" class="application-form">
<input type="hidden" name="action" value="add_draft">

<div class="requirement-signatory-header">
    <h4>Signatory</h4>
    <h4 style="margin-left:15px;">|</h4>
    <h4 style="margin-left:15px;">Requirement</h4>
</div>

<select name="requirement_id" id="requirement_id" required>
    <option value="">-- Select Signatory / Requirement --</option>
    <?php foreach($requirements as $r):
        $req_id       = intval($r['id'] ?? 0);
        $sig_id_opt   = intval($r['signatory_id']);
        $req_name     = (!empty($r['requirement_name']) && $r['requirement_name'] !== 'No Requirement Set')
                        ? h($r['requirement_name']) : 'Clearance Only';
        $label        = h($r['signatory_type']) . ' (' . h($r['signatory_full_name']) . ') — ' . $req_name;
        $composite_val= $req_id . '_' . $sig_id_opt;
        $is_in_draft  = in_array($composite_val, $draft_unique_keys);
        $is_submitted = in_array($composite_val, $submitted_combo_keys);

        // Get the actual status for this combo key so we can show the right label
        $lock_status  = '';
        if ($is_submitted) {
            $ls = $conn->prepare("
                SELECT a.status FROM applications a
                JOIN users u ON a.signatory = u.username
                WHERE a.username = ? AND u.id = ? AND a.requirement_id = ?
                AND a.status IN ('Pending','Approved','Requires Action','Totally Rejected')
                ORDER BY a.submitted_at DESC LIMIT 1
            ");
            $ls_sig = intval($r['signatory_id']);
            $ls_req = intval($r['id'] ?? 0);
            $ls->bind_param("sii", $username, $ls_sig, $ls_req);
            $ls->execute();
            $ls_row = $ls->get_result()->fetch_assoc();
            $ls->close();
            $lock_status = $ls_row['status'] ?? '';
        }

        $is_program_head = (stripos($r['signatory_type'], 'Program Head') !== false);
$sig_type_for_check = trim($r['signatory_type'] ?? '');

// Dynamic prerequisite check (by type now)
$dynamic_check = isSignatoryUnlocked($conn, $username, $user_course, $sig_type_for_check, $course_id_for_prereq);

if ($dynamic_check === null) {
    // No dynamic prereqs configured — use hardcoded fallback
    $locked_by_adviser = ($is_program_head && !$class_adviser_cleared);
} elseif ($dynamic_check === false) {
    $locked_by_adviser = true;
} else {
    $locked_by_adviser = false;
}

        $disabled    = ($is_in_draft || $is_submitted || $locked_by_adviser) ? 'disabled' : '';
        $status_note = '';
        if ($is_in_draft) {
            $status_note = ' [In Preview]';
        } elseif ($lock_status === 'Approved') {
            $status_note = ' [✓ Already Complied]';
        } elseif ($lock_status === 'Pending') {
            $status_note = ' [⏳ Pending Review]';
        } elseif ($lock_status === 'Requires Action') {
            $status_note = ' [⚠️ Requires Action — Resubmit Below]';
        } elseif ($lock_status === 'Totally Rejected') {
            $status_note = ' [⛔ Permanently Rejected — Contact your signatory]';
        }
        if ($locked_by_adviser) {
    $cache_key = $course_id_for_prereq . '_' . $sig_type_for_check;
    
    if (!isset($prereq_label_cache[$cache_key])) {
        $prereq_label_q = $conn->prepare("
            SELECT before_type FROM signatory_prerequisites
            WHERE course_id = ? AND signatory_type = ? AND admin_enabled = 1 AND signatory_enabled = 1
        ");
        $prereq_label_q->bind_param("is", $course_id_for_prereq, $sig_type_for_check);
        $prereq_label_q->execute();
        $prereq_label_res = $prereq_label_q->get_result();
        $blocking_types = [];
        while ($bl = $prereq_label_res->fetch_assoc()) $blocking_types[] = $bl['before_type'];
        $prereq_label_q->close();
        $prereq_label_cache[$cache_key] = $blocking_types;
    } else {
        $blocking_types = $prereq_label_cache[$cache_key];
    }

    if (count($blocking_types) === 1) {
        $status_note = ' [🔒 Complete ' . $blocking_types[0] . ' first]';
    } elseif (count($blocking_types) === 2) {
        $status_note = ' [🔒 Complete ' . $blocking_types[0] . ' & ' . $blocking_types[1] . ' first]';
    } else {
        $status_note = ' [🔒 Complete prior requirements first (' . count($blocking_types) . ' pending)]';
    }
}
    ?>
    <option value="<?= $composite_val ?>"
            <?= $disabled ?>
            style="<?= $is_submitted ? 'color:#999;text-decoration:line-through;' : ($locked_by_adviser ? 'color:#999;font-style:italic;' : '') ?>">
        <?= $label . $status_note ?>
    </option>
    <?php endforeach; ?>
</select>

<!-- Shown when a file-required requirement is selected -->
<!-- Shown when a file-required requirement is selected -->
<div id="upload_section_wrapper" style="display:none;">
    <div class="upload-box" onclick="document.getElementById('file_input').click();">
        <div class="upload-box-inner">
            <i class='bx bx-cloud-upload'></i>
            <strong>Click to Upload File</strong>
            <div class="format-badges" id="formatBadges"></div>
            <p class="max-size-note">Max file size: 30MB</p>
        </div>
    </div>
    <input type="file" name="file" id="file_input" style="display:none;">
    <div class="file-chosen" id="fileChosenName"></div>
</div>

<!-- Shown when requirement is N/A (no file required) -->
<div class="no-file-box" id="no_file_info">
    <i class='bx bx-check-circle'></i>
    <h4>No File Upload Required</h4>
    <p>This signatory does not require any document.<br>
       Submitting will send your compliance for <strong>review and approval</strong>.</p>
</div>

<div class="btn-group">
    <button type="button" class="cancel-btn" id="cancelCreateDraft">Cancel</button>
<button type="submit" class="submit-btn" id="submitDraftBtn" disabled>Add to Preview</button></div>
</form>
</div>
</div>
<!-- ======== END MODAL ======== -->

<h3 style="color:darkgreen; margin-top:30px;">My Applications</h3>
<label for="statusFilter">Filter by Status:</label>
<select id="statusFilter" onchange="filterApplications()">
<option value="all">All</option>
<option value="Pending">Pending</option>
<option value="Approved">Approved</option>
<option value="Requires Action">Requires Action</option>
<option value="Totally Rejected">Permanently Rejected</option>
</select>

<table>
<thead>
<tr><th>Requirement</th><th>Signatory</th><th>Course & Section</th><th>Document</th><th>Status</th><th>Submitted At</th><th>Action</th></tr>
</thead>
<tbody id="applicationsTable">
<?php foreach($applications as $app): 
    $row_class = str_replace(' ', '', $app['status']);
    $is_no_file = ($app['document'] === 'N/A' || $app['document'] === 'No file required');
?>
<tr class="app-row <?= h($row_class) ?>" data-status="<?= h($app['status']) ?>">
<td><?= h($app['requirement_name'] ?? 'N/A') ?></td>  <!-- ADD THIS -->
<td><?= h($signatory_types_map[$app['signatory']] ?? $app['signatory']) ?></td>
<td><?= h($app['course']) ?></td>
<td>
<?php if($is_no_file): ?>
<span style="color:#888;">No file required</span>
<?php else: ?>
<a href="uploads/<?= h($app['document']) ?>" target="_blank">View</a>
<?php endif; ?>
</td>
<td>
    <?php if ($app['status'] === 'Totally Rejected'): ?>
        <span class="status" style="background:#8b0000; color:white; display:inline-block; padding:5px 10px; border-radius:5px; font-weight:bold;">
            ⛔ Permanently Rejected
        </span>
        <div style="font-size:12px; color:#8b0000; margin-top:4px; font-weight:600;">
            Please contact your signatory
        </div>
    <?php else: ?>
        <?php if ($app['status'] === 'Approved'): ?>
            <span style="background:#28a745; color:white; display:inline-block; padding:5px 10px; border-radius:5px; font-weight:600; font-size:12px;">✅ Approved</span>
        <?php else: ?>
            <span class="status <?= h($row_class) ?>"><?= h($app['status']) ?></span>
            <?php $rc = (int)$app['rejection_count']; ?>
            <?php if ($rc === 1): ?>
                <div style="font-size:11px; color:#888; margin-top:3px;">(Rejected 1x)</div>
            <?php elseif ($rc === 2): ?>
                <div style="font-size:11px; color:#e65100; font-weight:600; margin-top:3px;">(Rejected 2x)</div>
            <?php elseif ($rc >= 3): ?>
                <div style="font-size:11px; color:#c62828; font-weight:700; margin-top:3px;">⚠️ 3 rejections — 1 more will be permanent!</div>
            <?php endif; ?>
        <?php endif; ?>
    <?php endif; ?>
</td><td><?= h($app['submitted_at']) ?></td>
<td>
    <?php if ($app['status'] === 'Requires Action'): ?>
        <button type="button" class="resubmit-btn" onclick="openResubmitModal(<?= intval($app['id']) ?>)">
            <?= $is_no_file ? 'Review' : 'Resubmit' ?>
        </button>
    <?php elseif ($app['status'] === 'Pending'): ?>
        <span style="color:blue;">Reviewing...</span>
    <?php elseif ($app['status'] === 'Totally Rejected'): ?>
        <span style="color:#8b0000; font-weight:600; font-size:12px;">⛔ Permanently Rejected<br>Please contact your signatory</span>
    <?php else: ?>
        <span style="color:#28a745; font-weight:600;">Completed</span>
    <?php endif; ?>
</td>
</tr>

<?php if ($app['status'] === 'Requires Action'): ?>
<tr class="resubmit-row" id="resubmitRow<?= intval($app['id']) ?>" style="display:none;">
    <td colspan="7">
        <div class="resubmit-alert">
    <h4>⚠️ Action Required: <?= $is_no_file ? 'Review Note' : 'Re-submission' ?></h4>
    <?php $rc = (int)$app['rejection_count']; ?>
    <?php if ($rc >= 3): ?>
        <div style="background:#ffebee; border:2px solid #c62828; border-radius:6px; padding:10px 14px; margin-bottom:12px;">
            <strong style="color:#c62828;">🚨 FINAL WARNING: This is your last chance!</strong>
            <div style="color:#c62828; font-size:13px; margin-top:4px;">You have been rejected <?= $rc ?> times. <strong>One more rejection will permanently block this requirement.</strong></div>
        </div>
    <?php elseif ($rc === 2): ?>
        <div style="background:#fff3cd; border:1px solid #ffc107; border-radius:6px; padding:10px 14px; margin-bottom:12px;">
            <strong style="color:#856404;">⚠️ Warning: <?= $rc ?> rejections so far.</strong>
            <div style="color:#856404; font-size:13px; margin-top:4px;">You have 1 more attempt before the next rejection becomes permanent.</div>
        </div>
    <?php elseif ($rc === 1): ?>
        <div style="background:#f5f5f5; border:1px solid #ccc; border-radius:6px; padding:10px 14px; margin-bottom:12px;">
            <strong style="color:#555;">ℹ️ Rejected 1 time so far.</strong>
            <div style="color:#555; font-size:13px; margin-top:4px;">You have 2 more attempts remaining before permanent rejection.</div>
        </div>
    <?php endif; ?>
    <p>Returned by <strong><?= h($signatory_types_map[$app['signatory']] ?? $app['signatory']) ?></strong>.</p>
    <p><strong>Reason:</strong> <?= nl2br(h($app['rejection_reason'] ?? 'No reason provided.')) ?></p>
            <form action="student_resubmit_action.php" method="post" enctype="multipart/form-data"
                  onsubmit="return confirm('<?= $is_no_file ? 'Confirm acknowledging this note?' : 'Re-submit this requirement?' ?>')">
                <input type="hidden" name="app_id" value="<?= intval($app['id']) ?>">
                <input type="hidden" name="current_document" value="<?= h($app['document']) ?>">
                <?php 
// Fetch allowed formats for this application's requirement
$fmt_stmt = $conn->prepare("
    SELECT cr.document_type_id 
    FROM course_requirements cr
    JOIN users u ON cr.signatory_id = u.id
    WHERE u.username = ? 
    AND cr.course_id = (SELECT id FROM courses WHERE course_name = ? LIMIT 1)
    AND cr.requirement_id = (SELECT requirement_id FROM applications WHERE id = ? LIMIT 1)
    LIMIT 1
");
$fmt_stmt->bind_param("ssi", $app['signatory'], $user_course, $app['id']);
$fmt_stmt->execute();
$fmt_row = $fmt_stmt->get_result()->fetch_assoc();
$fmt_stmt->close();

$resubmit_formats = trim($fmt_row['document_type_id'] ?? '');
$resubmit_accept  = !empty($resubmit_formats) && strtoupper($resubmit_formats) !== 'N/A' ? buildAcceptAttr($resubmit_formats) : '';
$resubmit_label   = !empty($resubmit_formats) && strtoupper($resubmit_formats) !== 'N/A' ? buildFormatsLabel($resubmit_formats) : '';
?>
<?php if (!$is_no_file): ?>
    <label style="font-weight:bold;margin-bottom:5px;">Upload Revised Requirement:</label>
    <?php if (!empty($resubmit_label)): ?>
        <div style="margin-bottom:6px;">
            <small style="color:#555;">Allowed formats: </small>
            <?php foreach(explode(',', $resubmit_label) as $fmt): ?>
                <span style="background:#e8f5e9; border:1px solid #4CAF50; color:#1b5e20; border-radius:20px; padding:2px 10px; font-size:12px; font-weight:700;"><?= trim($fmt) ?></span>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <input type="file" name="document" id="new_file<?= intval($app['id']) ?>" required 
           <?= !empty($resubmit_accept) ? 'accept="' . h($resubmit_accept) . '"' : '' ?>
           style="margin-bottom:10px;border:1px solid orange;">
    <button type="submit" class="resubmit-btn">🔄 Confirm Resubmit</button>
<?php else: ?>
    <p style="margin-bottom:15px;color:#555;font-style:italic;">(No file needed. Click below to acknowledge.)</p>
    <button type="submit" class="resubmit-btn">✅ Acknowledge & Retry</button>
<?php endif; ?>
            </form>
        </div>
    </td>
</tr>
<?php endif; ?>

<?php endforeach; ?>
</tbody>
</table>

</div>
<?php if ($password_not_changed): ?>
<div class="welcome-modal" id="forcePasswordModal" style="display:flex; z-index:99999;">
    <div class="welcome-modal-content">
        <i class='bx bx-lock-alt' style="font-size:48px; color:darkgreen; margin-bottom:10px;"></i>
        <h3>Action Required: Change Your Password</h3>
        <p style="color:#856404; font-weight:600; background:#fff3cd; padding:10px; border-radius:8px; border:1px solid #ffc107;">
            ⚠️ You are using the default system password. You must change it before continuing.
        </p>
        <p>Your default password is <code style="background:#f0f0f0; padding:2px 6px; border-radius:4px;"><?= h($default_password) ?></code>. Please set a new, personal password now.</p>
        <div class="welcome-modal-btns">
            <button class="wm-btn-later" onclick="window.location.href='logout.php'">Logout</button>
            <button class="wm-btn-now" onclick="window.location.href='student_profile.php'">Change Password →</button>
        </div>
    </div>
</div>
<?php endif; ?>
<!-- EMAIL VERIFICATION REMINDER MODAL -->
<?php if ($email_not_verified): ?>
<div class="welcome-modal" id="emailReminderModal">
    <div class="welcome-modal-content">
        <i class='bx bx-envelope' style="font-size:48px; color:darkgreen; margin-bottom:10px;"></i>
        <p style="color:#856404; font-weight:600; background:#fff3cd; padding:10px; border-radius:8px; border:1px solid #ffc107;">
            ⚠️ Your email address has not been verified yet.
        </p>
        <p>Please head to your profile to verify your email address to ensure you receive important notifications.</p>
        <p style="color:#888; font-size:13px;">You may also update your password while you're there.</p>
        <div class="welcome-modal-btns">
            <button class="wm-btn-later" onclick="closeEmailReminder()">Do Later</button>
            <button class="wm-btn-now" onclick="window.location.href='student_profile.php'">Go to Profile →</button>
        </div>
    </div>
</div>
<?php endif; ?>
</section>

<!-- Requirement JS data (server-generated, no escaping issues) -->
<script>
const REQ_DATA = <?= json_encode($req_js_data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
</script>

<script>
// ---- Sidebar toggle ----
const body    = document.querySelector("body");
const sidebar = body.querySelector(".sidebar");
const toggle  = body.querySelector(".toggle");
if (toggle) toggle.addEventListener("click", () => sidebar.classList.toggle("close"));

// ---- Modal open/close ----
const createBtn   = document.getElementById('openCreateDraft');
const createModal = document.getElementById('createDraftModal');
const closeCreate = document.getElementById('closeCreateDraft');
const cancelCreate= document.getElementById('cancelCreateDraft');

function openModal()  { createModal.style.display = 'flex'; resetModal(); }
function closeModal() { createModal.style.display = 'none'; }

if (createBtn)   createBtn.addEventListener('click', openModal);
if (closeCreate) closeCreate.addEventListener('click', closeModal);
if (cancelCreate)cancelCreate.addEventListener('click', closeModal);
window.addEventListener('click', e => { if (e.target === createModal) closeModal(); });

function resetModal() {
    document.getElementById('requirement_id').value = '';
    document.getElementById('upload_section_wrapper').style.display = 'none';
    document.getElementById('no_file_info').style.display = 'none';
    document.getElementById('formatBadges').innerHTML = '';
    document.getElementById('fileChosenName').style.display = 'none';
    document.getElementById('fileChosenName').textContent = '';
    const fi = document.getElementById('file_input');
    fi.value = '';
    fi.removeAttribute('required');
    fi.removeAttribute('accept');
    const btn = document.getElementById('submitDraftBtn');
    btn.disabled = true;
    btn.textContent = 'Add to Preview';
    btn.style.background = 'darkgreen';
}

// ---- File input change handler (set up ONCE on page load) ----
const fileInputEl = document.getElementById('file_input');
if (fileInputEl) {
    fileInputEl.addEventListener('change', function () {
        const display = document.getElementById('fileChosenName');
        const submitBtn = document.getElementById('submitDraftBtn');
        
        if (this.files && this.files.length > 0) {
            // Show filename
            display.textContent = '📎 ' + this.files[0].name;
            display.style.display = 'block';
            
            // Enable submit button if in file-required mode
            if (submitBtn.textContent === 'Add to Preview') {
                submitBtn.disabled = false;
            }
        } else {
            // No file selected
            display.textContent = '';
            display.style.display = 'none';
            
            // Disable submit button if file is required
            if (submitBtn.textContent === 'Add to Preview') {
                submitBtn.disabled = true;
            }
        }
    });
}

// ---- Requirement dropdown change handler ----
const reqSelect = document.getElementById('requirement_id');
if (reqSelect) {
    reqSelect.addEventListener('change', function () {
        const val = this.value;
        const uploadWrapper = document.getElementById('upload_section_wrapper');
        const noFileInfo     = document.getElementById('no_file_info');
        const fileInput      = document.getElementById('file_input');
        const badgeContainer = document.getElementById('formatBadges');
        const submitBtn      = document.getElementById('submitDraftBtn');

        // Reset everything
        uploadWrapper.style.display = 'none';
        noFileInfo.style.display    = 'none';
        submitBtn.disabled          = true;
        badgeContainer.innerHTML    = '';
        fileInput.value             = '';
        document.getElementById('fileChosenName').style.display = 'none';
        document.getElementById('fileChosenName').textContent = '';

        if (!val) return;

        const selectedText = this.options[this.selectedIndex].text.toLowerCase();
        const data = REQ_DATA[val];

        // Determine if file is required
        const isNoFile = selectedText.includes('clearance only') || (data && data.isNoFile);

        if (isNoFile) {
            // NO FILE REQUIRED
            noFileInfo.style.display = 'block';
            uploadWrapper.style.display = 'none';
            fileInput.removeAttribute('required');
            fileInput.removeAttribute('accept');
            
            submitBtn.textContent = 'Comply & Add to Preview';
            submitBtn.style.background = '#0069c0';
            submitBtn.disabled = false;
        } else {
            // FILE REQUIRED
            uploadWrapper.style.display = 'block';
            noFileInfo.style.display    = 'none';
            
            // Set file restrictions
            if (data && data.accept) {
                fileInput.setAttribute('accept', data.accept);
            } else {
                fileInput.removeAttribute('accept');
            }
            fileInput.setAttribute('required', 'required');

            // Show format badges
            if (data && data.label) {
                const formats = data.label.split(',').map(f => f.trim());
                formats.forEach(fmt => {
                    const badge = document.createElement('span');
                    badge.className = 'format-badge';
                    badge.textContent = fmt;
                    badgeContainer.appendChild(badge);
                });
            }

            submitBtn.textContent = 'Add to Preview';
            submitBtn.style.background = 'darkgreen';
            submitBtn.disabled = true; // Keep disabled until file selected
        }
    });
}

// ---- Submit All ----
const submitAll = document.getElementById('submitAllBtn');
if (submitAll) {
    submitAll.addEventListener('click', function () {
        if (!confirm('Submit all draft items? Items requiring files but missing them will be skipped.')) return;
        this.disabled = true;
        this.style.opacity = '0.5';
        this.textContent = 'Submitting...';
        const f = document.createElement('form');
        f.method = 'POST'; f.style.display = 'none';
        const a = document.createElement('input');
        a.name = 'action'; a.value = 'submit_all'; f.appendChild(a);
        document.body.appendChild(f); f.submit();
    });
}

// ---- Resubmit toggle ----
function openResubmitModal(appId) {
    const row = document.getElementById('resubmitRow' + appId);
    if (row) row.style.display = (row.style.display === 'none' || !row.style.display) ? 'table-row' : 'none';
}

// ---- Status filter ----
function filterApplications() {
    const filter = document.getElementById('statusFilter').value;
    document.querySelectorAll('#applicationsTable .app-row').forEach(row => {
        const status = row.dataset.status || '';
        const btn    = row.querySelector('.resubmit-btn');
        const appId  = btn ? btn.getAttribute('onclick').match(/\((\d+)\)/)?.[1] : null;
        const resubmitRow = appId ? document.getElementById('resubmitRow' + appId) : null;

        const show = (filter === 'all' || status === filter);
        row.style.display = show ? 'table-row' : 'none';
        // Always collapse resubmit row when filtering
        if (resubmitRow) resubmitRow.style.display = 'none';
    });
}

// ---- Prevent double submissions on form submit ----
const createForm = document.getElementById('createDraftForm');
if (createForm) {
    createForm.addEventListener('submit', function(e) {
        const btn = document.getElementById('submitDraftBtn');
        
        // If already submitting, prevent
        if (btn.disabled && (btn.textContent === 'Processing...' || btn.textContent === 'Submitting...')) {
            e.preventDefault();
            return false;
        }
        
        // Disable button and show processing state
        btn.disabled = true;
        btn.style.opacity = '0.5';
        btn.textContent = 'Processing...';
    });
}

// Confirm and disable for draft actions
function confirmAndDisable(form, message) {
    if (!confirm(message)) return false;
    
    const btn = form.querySelector('button[type="submit"]');
    if (btn) {
        setTimeout(() => {
            btn.disabled = true;
            btn.style.opacity = '0.5';
            btn.textContent = 'Processing...';
        }, 10);
    }
    return true;
}
// ---- Email verification reminder ----
function closeEmailReminder() {
    document.getElementById('emailReminderModal').style.display = 'none';
}

<?php if ($email_not_verified && !$password_not_changed): ?>
window.addEventListener('load', function() {
    document.getElementById('emailReminderModal').style.display = 'flex';
});
<?php endif; ?>
</script>
</body>
</html>