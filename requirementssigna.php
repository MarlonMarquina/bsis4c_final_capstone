<?php
// FILE: requirementssigna.php
session_start();
date_default_timezone_set('Asia/Manila'); // ← MANILA TIMEZONE

if (!isset($_SESSION['role']) || $_SESSION['role'] != "signatory") {
    header("Location: login.php");
    exit();
}
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require 'vendor/autoload.php';
include 'conn.php';

$conn->query("SET time_zone = '+08:00'"); // ← MYSQL MANILA TIMEZONE

$signatory = $_SESSION['username'];

// Check requirement lock
$lock_check = $conn->prepare("SELECT requirement_lock FROM system_settings WHERE id = 1");
$lock_check->execute();
$lock_row = $lock_check->get_result()->fetch_assoc();
$lock_check->close();
$requirements_locked = ($lock_row['requirement_lock'] ?? 0) == 1;

// 1. FETCH SIGNATORY INFO
$sigInfoStmt = $conn->prepare("SELECT id, full_name, signatory_type, section, department FROM users WHERE username = ? AND role = 'signatory' LIMIT 1");
$sigInfoStmt->bind_param("s", $signatory);
$sigInfoStmt->execute();
$sigResult = $sigInfoStmt->get_result();
$signatoryData = $sigResult->fetch_assoc();

$current_sig_id = $signatoryData['id'] ?? 0;
$signatoryFullName = $signatoryData['full_name'] ?? $signatory;
$sig_type_display = $signatoryData['signatory_type'] ?? '';
$sig_type = strtolower($sig_type_display);
$sig_dept = trim($signatoryData['department'] ?? $signatoryData['section'] ?? '');

// Logic: Identify signatory type and restrictions
$is_class_adviser = (strpos($sig_type, 'class adviser') !== false || strpos($sig_type, 'classadviser') !== false);
$is_program_head = (strpos($sig_type, 'program head') !== false);

$restricted_roles = ['adviser', 'program head', 'department head'];
$is_restricted = false;
foreach ($restricted_roles as $role) {
    if (strpos($sig_type, $role) !== false) {
        $is_restricted = true;
        break;
    }
}

// Parse program head departments if applicable
$program_head_depts = [];
if ($is_program_head && !empty($sig_dept)) {
    $depts = explode(',', $sig_dept);
    foreach ($depts as $dept) {
        $program_head_depts[] = trim($dept);
    }
}
$sigInfoStmt->close();

// Parse class adviser assignments if applicable
$adviser_classes = [];
if ($is_class_adviser && !empty($signatoryData['section'])) {
    $classes = explode(',', $signatoryData['section']);
    
    foreach ($classes as $class) {
        $parts = explode('|', trim($class));
        
        if (count($parts) == 3) {
            $course = trim($parts[0]);
            $year = trim($parts[1]);
            $section = trim($parts[2]);
        } elseif (count($parts) == 2) {
            $course = $sig_dept;
            $year = trim($parts[0]);
            $section = trim($parts[1]);
        } else {
            continue;
        }
        
        if (!isset($adviser_classes[$course])) {
            $adviser_classes[$course] = [];
        }
        if (!isset($adviser_classes[$course][$year])) {
            $adviser_classes[$course][$year] = [];
        }
        $adviser_classes[$course][$year][] = $section;
    }
}

// Sort sections alphabetically for each course and year
if (!empty($adviser_classes)) {
    foreach ($adviser_classes as $course => &$years) {
        foreach ($years as $year => &$sections) {
            sort($sections, SORT_NATURAL);
        }
    }
    unset($years, $sections);
}

// Function to get courses based on signatory scope
function getSignatoryCourses($conn, $current_sig_id, $is_class_adviser, $adviser_classes, $is_program_head, $program_head_depts, $is_restricted, $sig_dept) {
    $sql = "SELECT id, course_name FROM courses";
    $conditions = [];
    
    if ($is_class_adviser && !empty($adviser_classes)) {
        $allowed_courses = array_keys($adviser_classes);
        $course_list = "'" . implode("','", array_map([$conn, 'real_escape_string'], $allowed_courses)) . "'";
        $conditions[] = "course_name IN ($course_list)";
    } elseif ($is_program_head && !empty($program_head_depts)) {
        $dept_conditions = [];
        foreach ($program_head_depts as $dept) {
            $dept_conditions[] = "course_name = '" . mysqli_real_escape_string($conn, $dept) . "'";
        }
        $conditions[] = "(" . implode(' OR ', $dept_conditions) . ")";
    } elseif ($is_restricted && !empty($sig_dept)) {
        $depts = explode(',', $sig_dept);
        $dept_conditions = [];
        foreach ($depts as $d) {
            $dept_conditions[] = "course_name LIKE '%" . trim($d) . "%'";
        }
        $conditions[] = "(" . implode(' OR ', $dept_conditions) . ")";
    }
    
    if (!empty($conditions)) {
        $sql .= " WHERE " . implode(' AND ', $conditions);
    }
    
    $sql .= " ORDER BY course_name ASC";
    return $conn->query($sql);
}

$fixed_doc_types = [
    "Any File (.*)",
    "PDF (.pdf)",
    "Word (.doc)",
    "Word (.docx)",
    "Excel (.xls)",
    "Excel (.xlsx)",
    "PowerPoint (.ppt)",
    "PowerPoint (.pptx)",
    "Text (.txt)",
    "Rich Text (.rtf)",
    "CSV (.csv)",
    "Image (.jpg)",
    "Image (.jpeg)",
    "Image (.png)",
    "Image (.gif)",
    "Image (.webp)",
    "Image (.bmp)",
    "Image (.tiff)",
    "ZIP (.zip)",
    "RAR (.rar)",
    "7-Zip (.7z)",
    "Audio (.mp3)",
    "Audio (.wav)",
    "Audio (.m4a)",
    "Video (.mp4)",
    "Video (.mov)",
    "Video (.avi)",
    "Video (.mkv)",
    "HTML (.html)",
    "JSON (.json)",
    "XML (.xml)",
    "SVG (.svg)",
    "PSD (.psd)",
];

// Function to auto-approve students for auto_approve requirements
function autoApproveStudents($conn, $course_id, $requirement_id, $signatory_username, $year_level, $sections_str) {
    // Get course name
    $course_query = $conn->prepare("SELECT course_name FROM courses WHERE id = ?");
    $course_query->bind_param("i", $course_id);
    $course_query->execute();
    $course_row = $course_query->get_result()->fetch_assoc();
    $course_name = $course_row['course_name'];
    $course_query->close();
    
    // Get requirement name
    $req_query = $conn->prepare("SELECT requirement_name FROM requirement_library WHERE id = ?");
    $req_query->bind_param("i", $requirement_id);
    $req_query->execute();
    $req_row = $req_query->get_result()->fetch_assoc();
    $requirement_name = $req_row['requirement_name'];
    $req_query->close();
    
    // Get all affected students
    $sections_clean = str_replace(', ', ',', $sections_str);
    $student_query = $conn->prepare("
        SELECT username, full_name, email 
        FROM users 
        WHERE role = 'student' 
        AND course = ?
        AND (? = 'All Years' OR year = ?)
        AND (? = 'All Sections' OR FIND_IN_SET(section, ?))
    ");
    $student_query->bind_param("sssss", $course_name, $year_level, $year_level, $sections_clean, $sections_clean);
    $student_query->execute();
    $students = $student_query->get_result();
    
    $auto_approved_count = 0;
    
    while ($student = $students->fetch_assoc()) {
        // Check if application already exists
        $check_app = $conn->prepare("SELECT id FROM applications WHERE username = ? AND signatory = ? AND requirement_id = ? AND course = ?");
        $check_app->bind_param("ssis", $student['username'], $signatory_username, $requirement_id, $course_name);
        $check_app->execute();
        
        if ($check_app->get_result()->num_rows == 0) {
            // Auto-approve the student (NOW() uses Manila time via SET time_zone)
            $insert_app = $conn->prepare("
                INSERT INTO applications (username, signatory, requirement_id, course, document, status, submitted_at, auto_approved, auto_approved_at) 
                VALUES (?, ?, ?, ?, 'N/A', 'Approved', NOW(), 1, NOW())
            ");
            $insert_app->bind_param("ssis", $student['username'], $signatory_username, $requirement_id, $course_name);
            $insert_app->execute();
            $insert_app->close();
            $auto_approved_count++;
        }
        $check_app->close();
    }
    
    $student_query->close();
    
    return $auto_approved_count;
}

// ─────────────────────────────────────────────────────────────────────────────
// assignGlobalToAllCourses — used ONLY for auto_approve requirements now.
// Regular (non-auto-approve) requirements require Step 2 manual assignment.
// ─────────────────────────────────────────────────────────────────────────────
function assignGlobalToAllCourses($conn, $requirement_id, $signatory_username, $current_sig_id, $is_class_adviser, $adviser_classes, $is_program_head, $program_head_depts, $is_restricted, $sig_dept) {
    $courses_result = getSignatoryCourses($conn, $current_sig_id, $is_class_adviser, $adviser_classes, $is_program_head, $program_head_depts, $is_restricted, $sig_dept);
    
    $assigned_count = 0;
    $total_auto_approved = 0;
    
    // Get requirement details
    $req_info = $conn->prepare("SELECT requirement_name, auto_approve, allowed_formats FROM requirement_library WHERE id = ?");
    $req_info->bind_param("i", $requirement_id);
    $req_info->execute();
    $req_data = $req_info->get_result()->fetch_assoc();
    $req_info->close();
    
    $auto_approve = $req_data['auto_approve'] ?? 0;
    $formats = $auto_approve ? 'AUTO_APPROVE' : ($req_data['allowed_formats'] ?? 'N/A');
    $year_level = 'All Years';
    $sections_str = 'All Sections';
    
    while ($course = $courses_result->fetch_assoc()) {
        $course_id = $course['id'];
        
        // Check if assignment already exists
        $check = $conn->prepare(
            "SELECT cr.id FROM course_requirements cr
             WHERE cr.signatory_id = ? 
             AND cr.requirement_id = ? 
             AND cr.course_id = ?
             AND cr.year_level = ?
             AND cr.sections = ?"
        );
        $check->bind_param("iiiss", $current_sig_id, $requirement_id, $course_id, $year_level, $sections_str);
        $check->execute();
        
        if ($check->get_result()->num_rows == 0) {
            $stmt = $conn->prepare("INSERT INTO course_requirements (course_id, requirement_id, signatory_id, document_type_id, year_level, sections, requirements_configured) VALUES (?, ?, ?, ?, ?, ?, 1)");
            $stmt->bind_param("iiisss", $course_id, $requirement_id, $current_sig_id, $formats, $year_level, $sections_str);
            $stmt->execute();
            $stmt->close();
            $assigned_count++;
            
            // Auto-approve students if this is an auto-approve requirement
            if ($auto_approve == 1) {
                $total_auto_approved += autoApproveStudents($conn, $course_id, $requirement_id, $signatory_username, $year_level, $sections_str);
            }
        }
        $check->close();
    }
    
    return ['assigned' => $assigned_count, 'auto_approved' => $total_auto_approved];
}

// --- POST HANDLERS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    // ─────────────────────────────────────────────────────────────────────────
    // ADD TO LIBRARY
    //
    // • If auto_approve is checked  → auto-assign to ALL courses in scope
    //                                 (same behaviour as before)
    // • If auto_approve is NOT checked → only add to library, NO auto-assign.
    //   The signatory must use Step 2 (assign_requirement) to assign manually.
    // ─────────────────────────────────────────────────────────────────────────
    if ($action === 'add_library') {
        $name        = trim($_POST['new_requirement_name'] ?? '');
        $formats     = isset($_POST['library_formats']) ? $_POST['library_formats'] : [];
        $auto_approve = isset($_POST['auto_approve']) ? 1 : 0;

        // Auto-generate a name when auto-approve is on and name is blank
        if ($auto_approve == 1 && $name === '') {
            $name = 'Approved No Requirement';
        }

        if ($name !== '') {
            if ($auto_approve == 0 && empty($formats)) {
                echo "<script>alert('⚠️ Please select at least one format or check Auto-Approve!');</script>";
            } else {
                $chk = $conn->prepare("SELECT id FROM requirement_library WHERE requirement_name = ? AND signatory_id = ?");
                $chk->bind_param("si", $name, $current_sig_id);
                $chk->execute();
                if ($chk->get_result()->num_rows == 0) {
                    $formats_str = $auto_approve ? 'AUTO_APPROVE' : implode(', ', $formats);
                    $stmt = $conn->prepare("INSERT INTO requirement_library (requirement_name, allowed_formats, signatory_id, auto_approve) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param("ssii", $name, $formats_str, $current_sig_id, $auto_approve);
                    
                    if ($stmt->execute()) {
                        $new_id = $stmt->insert_id;
                        $stmt->close();

                        if ($auto_approve == 1) {
                            // ── Auto-approve: assign to ALL courses automatically ──
                            $result = assignGlobalToAllCourses(
                                $conn, $new_id, $signatory, $current_sig_id,
                                $is_class_adviser, $adviser_classes,
                                $is_program_head, $program_head_depts,
                                $is_restricted, $sig_dept
                            );
                            echo "<script>alert('✅ Auto-Approve requirement added and assigned globally!\\n\\nRequirement: " . addslashes($name) . "\\nAssigned to: {$result['assigned']} course(s)\\nAuto-Approved: {$result['auto_approved']} student(s)'); window.location.href='requirementssigna.php';</script>";
                        } else {
                            // ── Regular: added to library only, Step 2 required ──
                            echo "<script>alert('✅ Requirement added to library!\\n\\n📋 Next Step: Use the \"Override Assignment\" panel (Step 2) below to assign this requirement to the specific course, year level, and section(s) you want.'); window.location.href='requirementssigna.php';</script>";
                        }
                    } else {
                        echo "<script>alert('❌ Error adding requirement. Please try again.');</script>";
                    }
                } else {
                    echo "<script>alert('⚠️ A requirement with this name already exists!');</script>";
                }
                $chk->close();
            }
        } else {
            echo "<script>alert('⚠️ Please provide requirement name!');</script>";
        }
    }

    // EDIT LIBRARY ITEM
    if ($action === 'edit_library') {
        $lib_id = intval($_POST['library_id'] ?? 0);
        $name = trim($_POST['edit_requirement_name'] ?? '');
        $formats = isset($_POST['edit_library_formats']) ? $_POST['edit_library_formats'] : [];
        $auto_approve = isset($_POST['edit_auto_approve']) ? 1 : 0;
        
        if ($lib_id > 0 && $name !== '') {
            if ($auto_approve == 0 && empty($formats)) {
                echo "<script>alert('⚠️ Please select at least one format or check Auto-Approve!');</script>";
            } else {
                $formats_str = $auto_approve ? 'AUTO_APPROVE' : implode(', ', $formats);
                $stmt = $conn->prepare("UPDATE requirement_library SET requirement_name = ?, allowed_formats = ?, auto_approve = ? WHERE id = ? AND signatory_id = ?");
                $stmt->bind_param("ssiii", $name, $formats_str, $auto_approve, $lib_id, $current_sig_id);
                if ($stmt->execute()) {
                    echo "<script>alert('✅ Requirement updated successfully!'); window.location.href='requirementssigna.php';</script>";
                } else {
                    echo "<script>alert('❌ Error updating requirement.');</script>";
                }
                $stmt->close();
            }
        } else {
            echo "<script>alert('⚠️ Please provide all required information!');</script>";
        }
    }

    // DELETE FROM LIBRARY (CASCADE)
    if ($action === 'delete_library') {
        $lib_id = intval($_POST['delete_library_id'] ?? 0);
        
        if ($lib_id > 0) {
            $count_assignments = $conn->prepare("SELECT COUNT(*) as cnt FROM course_requirements WHERE requirement_id = ? AND signatory_id = ?");
            $count_assignments->bind_param("ii", $lib_id, $current_sig_id);
            $count_assignments->execute();
            $assign_count = $count_assignments->get_result()->fetch_assoc()['cnt'];
            $count_assignments->close();
            
            $count_apps = $conn->prepare("SELECT COUNT(*) as cnt FROM applications WHERE signatory = ? AND requirement_id = ?");
            $count_apps->bind_param("si", $signatory, $lib_id);
            $count_apps->execute();
            $app_count = $count_apps->get_result()->fetch_assoc()['cnt'];
            $count_apps->close();
            
            $count_drafts = $conn->prepare("SELECT COUNT(*) as cnt FROM draft_requirements WHERE signatory_id = ? AND requirement_id = ?");
            $count_drafts->bind_param("ii", $current_sig_id, $lib_id);
            $count_drafts->execute();
            $draft_count = $count_drafts->get_result()->fetch_assoc()['cnt'];
            $count_drafts->close();

            $del_apps = $conn->prepare("DELETE FROM applications WHERE signatory = ? AND requirement_id = ?");
            $del_apps->bind_param("si", $signatory, $lib_id);
            $del_apps->execute();
            $del_apps->close();
            
            $del_drafts = $conn->prepare("DELETE FROM draft_requirements WHERE signatory_id = ? AND requirement_id = ?");
            $del_drafts->bind_param("ii", $current_sig_id, $lib_id);
            $del_drafts->execute();
            $del_drafts->close();
            
            $del_assign = $conn->prepare("DELETE FROM course_requirements WHERE requirement_id = ? AND signatory_id = ?");
            $del_assign->bind_param("ii", $lib_id, $current_sig_id);
            $del_assign->execute();
            $del_assign->close();
            
            $del_lib = $conn->prepare("DELETE FROM requirement_library WHERE id = ? AND signatory_id = ?");
            $del_lib->bind_param("ii", $lib_id, $current_sig_id);
            if ($del_lib->execute()) {
                $total_removed = $assign_count + $app_count + $draft_count;
                echo "<script>alert('✅ Requirement deleted!\\n\\nRemoved:\\n- {$assign_count} assignment(s)\\n- {$app_count} student submission(s)\\n- {$draft_count} draft(s)'); window.location.href='requirementssigna.php';</script>";
            } else {
                echo "<script>alert('❌ Error deleting requirement.');</script>";
            }
            $del_lib->close();
        }
    }

    // SAVE SIGNATORY PREREQUISITE TOGGLES
    if ($action === 'save_signatory_prereqs') {
        $prereq_course_id = intval($_POST['prereq_course_id'] ?? 0);
        $enabled_ids = array_map('intval', $_POST['enabled_prereq_ids'] ?? []);

        if ($prereq_course_id > 0) {
            $all_rules = $conn->prepare("SELECT id FROM signatory_prerequisites WHERE course_id = ? AND signatory_type = ? AND admin_enabled = 1");
            $all_rules->bind_param("is", $prereq_course_id, $sig_type_display);
            $all_rules->execute();
            $rules_res = $all_rules->get_result();

            while ($rule = $rules_res->fetch_assoc()) {
                $rule_id = $rule['id'];
                $is_enabled = in_array($rule_id, $enabled_ids) ? 1 : 0;
                $upd = $conn->prepare("UPDATE signatory_prerequisites SET signatory_enabled = ? WHERE id = ? AND signatory_type = ?");
                $upd->bind_param("iis", $is_enabled, $rule_id, $sig_type_display);
                $upd->execute();
                $upd->close();
            }
            $all_rules->close();
        }
        echo "<script>alert('✅ Prerequisite settings saved!'); window.location.href='requirementssigna.php';</script>";
        exit();
    }

    // BULK DELETE ASSIGNMENTS
    if ($action === 'bulk_delete_assignments') {
        if ($requirements_locked) {
            echo "<script>alert('🔒 Requirement assignments are currently locked by the administrator. Please contact the admin.');</script>";
            exit();
        }
        $assignment_ids = $_POST['assignment_ids'] ?? [];
        
        if (!empty($assignment_ids)) {
            $deleted_count = 0;
            $total_related_deleted = 0;

            foreach ($assignment_ids as $assign_id) {
                $assign_id = intval($assign_id);

                $get_assign = $conn->prepare("SELECT cr.requirement_id, c.course_name, u.username AS signatory_username, cr.signatory_id
                    FROM course_requirements cr 
                    JOIN courses c ON c.id = cr.course_id
                    JOIN users u ON u.id = cr.signatory_id
                    WHERE cr.id = ? AND cr.signatory_id = ?");
                $get_assign->bind_param("ii", $assign_id, $current_sig_id);
                $get_assign->execute();
                $assign_row = $get_assign->get_result()->fetch_assoc();
                $get_assign->close();

                if ($assign_row) {
                    $requirement_id = $assign_row['requirement_id'];
                    $course_name = $assign_row['course_name'];
                    $sig_username = $assign_row['signatory_username'];
                    $sig_id = $assign_row['signatory_id'];
                    
                    $del_apps = $conn->prepare("DELETE FROM applications 
                        WHERE signatory = ? 
                        AND course = ? 
                        AND requirement_id = ?");
                    $del_apps->bind_param("ssi", $sig_username, $course_name, $requirement_id);
                    $del_apps->execute();
                    $total_related_deleted += $del_apps->affected_rows;
                    $del_apps->close();
                    
                    $del_drafts = $conn->prepare("DELETE FROM draft_requirements 
                        WHERE signatory_id = ? 
                        AND requirement_id = ?");
                    $del_drafts->bind_param("ii", $sig_id, $requirement_id);
                    $del_drafts->execute();
                    $total_related_deleted += $del_drafts->affected_rows;
                    $del_drafts->close();
                }

                $stmt = $conn->prepare("DELETE FROM course_requirements WHERE id = ? AND signatory_id = ?");
                $stmt->bind_param("ii", $assign_id, $current_sig_id);
                if ($stmt->execute()) {
                    $deleted_count++;
                }
                $stmt->close();
            }
            
            echo "<script>alert('✅ Successfully deleted {$deleted_count} assignment(s)!\\n\\nAlso removed:\\n- {$total_related_deleted} student submission(s) and draft(s)'); window.location.href='requirementssigna.php';</script>";
            exit();
        } else {
            echo "<script>alert('⚠️ No assignments selected!');</script>";
        }
    }

    // ASSIGN REQUIREMENT (Step 2 — manual/specific assignment, required for non-auto-approve)
    if ($action === 'assign_requirement') {
        if ($requirements_locked) {
            echo "<script>alert('🔒 Requirement assignments are currently locked by the administrator. Please contact the admin.');</script>";
            exit();
        }
        
        $req_id = intval($_POST['requirement_id'] ?? 0);
        $courses = $_POST['course_ids'] ?? [];
        $year_level = trim($_POST['year_level'] ?? '');
        $sections = [];
        
        if (count($courses) == 1) {
            if (isset($_POST['all_sections_checkbox'])) {
                $sections = ['All Sections'];
            } else {
                $sections = $_POST['section_ids'] ?? [];
                sort($sections, SORT_NATURAL);
            }
        } else if (count($courses) > 1) {
            if ($is_class_adviser) {
                $sections = ['All Sections (Handled)'];
            } else {
                $sections = ['All Sections'];
            }
        }
        
        $sections_str = !empty($sections) ? implode(', ', $sections) : 'All Sections';
        $assigned_count = 0;
        $auto_approve_enabled = false;

        if (!empty($courses) && !empty($year_level)) {
            // Get requirement details once
            $req_info = $conn->prepare("SELECT requirement_name, auto_approve, allowed_formats FROM requirement_library WHERE id = ?");
            $req_info->bind_param("i", $req_id);
            $req_info->execute();
            $req_data = $req_info->get_result()->fetch_assoc();
            $req_info->close();
            
            $auto_approve = $req_data['auto_approve'] ?? 0;
            $formats = $auto_approve ? 'AUTO_APPROVE' : ($req_data['allowed_formats'] ?? 'N/A');
            
            foreach ($courses as $c_id) {
                $check = $conn->prepare(
                    "SELECT cr.id FROM course_requirements cr
                     WHERE cr.signatory_id = ? 
                     AND cr.requirement_id = ? 
                     AND cr.course_id = ?
                     AND cr.year_level = ?
                     AND cr.sections = ?"
                );
                $check->bind_param("iiiss", $current_sig_id, $req_id, $c_id, $year_level, $sections_str);
                $check->execute();
                
                if ($check->get_result()->num_rows == 0) {
                    $stmt = $conn->prepare("INSERT INTO course_requirements (course_id, requirement_id, signatory_id, document_type_id, year_level, sections, requirements_configured) VALUES (?, ?, ?, ?, ?, ?, 1)");
                    $stmt->bind_param("iiisss", $c_id, $req_id, $current_sig_id, $formats, $year_level, $sections_str);
                    $stmt->execute();
                    $stmt->close();
                    $assigned_count++;
                    
                    if ($auto_approve == 1) {
                        $auto_approve_enabled = true;
                        autoApproveStudents($conn, $c_id, $req_id, $signatory, $year_level, $sections_str);
                    }
                }
            }
            
            if ($assigned_count > 0) {
                $auto_msg = ($auto_approve_enabled) ? " Students with this requirement have been automatically approved!" : "";
                echo "<script>alert('✅ Done! $assigned_count assignment(s) configured.$auto_msg'); window.location.href='requirementssigna.php';</script>";
            } else {
                echo "<script>alert('⚠️ No new assignments created. This combination may already exist.');</script>";
            }
        } else {
            echo "<script>alert('⚠️ Please select at least one course and a year level!');</script>";
        }
    }
}

// DELETE ASSIGNMENT (not library)
if (isset($_GET['delete_assign'])) {
    if ($requirements_locked) {
        echo "<script>alert('🔒 Requirement assignments are currently locked by the administrator. Please contact the admin.'); window.location.href='requirementssigna.php';</script>";
        exit();
    }
    $assign_id = intval($_GET['delete_assign']);
    
    $get_assign = $conn->prepare("SELECT cr.course_id, cr.requirement_id, u.username AS signatory_username, c.course_name
        FROM course_requirements cr 
        JOIN users u ON u.id = cr.signatory_id
        JOIN courses c ON c.id = cr.course_id
        WHERE cr.id = ? AND cr.signatory_id = ?");
    $get_assign->bind_param("ii", $assign_id, $current_sig_id);
    $get_assign->execute();
    $assign_result = $get_assign->get_result();
    
    if ($assign_row = $assign_result->fetch_assoc()) {
        $course_id = $assign_row['course_id'];
        $requirement_id = $assign_row['requirement_id'];
        $sig_username = $assign_row['signatory_username'];
        $course_name = $assign_row['course_name'];
        
        $total_deleted = 0;
        
        $del_apps = $conn->prepare("DELETE FROM applications 
            WHERE signatory = ? 
            AND course = ? 
            AND requirement_id = ?");
        $del_apps->bind_param("ssi", $sig_username, $course_name, $requirement_id);
        $del_apps->execute();
        $total_deleted += $del_apps->affected_rows;
        $del_apps->close();
        
        $del_drafts = $conn->prepare("DELETE FROM draft_requirements 
            WHERE signatory_id = ? 
            AND requirement_id = ?");
        $del_drafts->bind_param("ii", $current_sig_id, $requirement_id);
        $del_drafts->execute();
        $total_deleted += $del_drafts->affected_rows;
        $del_drafts->close();
        
        $stmt = $conn->prepare("DELETE FROM course_requirements WHERE id = ? AND signatory_id = ?");
        $stmt->bind_param("ii", $assign_id, $current_sig_id);
        
        if ($stmt->execute()) {
            $msg = $total_deleted > 0 
                ? "✅ Assignment deleted!\\n\\nAlso removed:\\n- {$total_deleted} student submission(s) and draft(s)" 
                : "✅ Assignment deleted successfully!";
            echo "<script>alert('$msg'); window.location.href='requirementssigna.php';</script>";
        } else {
            echo "<script>alert('❌ Error deleting assignment.');</script>";
        }
        $stmt->close();
    } else {
        echo "<script>alert('❌ Assignment not found or unauthorized.');</script>";
    }
    $get_assign->close();
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Requirements | Smart Clearance</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { background: #f0f2f5; color: #1a1f36; overflow-x: hidden; }

        .home { position: absolute; top: 0; left: 260px; min-height: 100vh; width: calc(100% - 260px); transition: all 0.4s ease; padding: 40px; }
        nav.sidebar.close ~ .home { left: 88px; width: calc(100% - 88px); }

        .dashboard-header { margin-bottom: 35px; background: #fff; padding: 25px; border-radius: 16px; box-shadow: 0 2px 10px rgba(0,0,0,0.03); border-left: 6px solid #0b4e12; }
        .dashboard-header h1 { color: #0b4e12; font-size: 28px; font-weight: 700; }

        /* ── STEP FLOW BANNER ─────────────────────────────────── */
        .step-flow-banner {
            display: flex;
            align-items: center;
            gap: 0;
            background: #fff;
            border: 1px solid #e3e8ee;
            border-radius: 16px;
            padding: 18px 28px;
            margin-bottom: 28px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }
        .step-flow-item {
            display: flex;
            align-items: center;
            gap: 12px;
            flex: 1;
        }
        .step-circle {
            width: 40px; height: 40px;
            border-radius: 50%;
            background: #0b4e12;
            color: #fff;
            font-weight: 700;
            font-size: 16px;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
            box-shadow: 0 3px 8px rgba(11,78,18,0.3);
        }
        .step-flow-text strong { display: block; font-size: 14px; color: #1a1f36; font-weight: 600; }
        .step-flow-text span { font-size: 12px; color: #6c757d; }
        .step-arrow {
            font-size: 24px; color: #0b4e12; margin: 0 16px;
            font-weight: 700; flex-shrink: 0;
        }
        /* Dynamic connector badge — updated via JS */
        .step-connector-badge {
            border-radius: 20px;
            padding: 4px 14px;
            font-size: 12px;
            font-weight: 600;
            white-space: nowrap;
            transition: all 0.3s ease;
        }
        .step-connector-badge.auto {
            background: #e8f5e9;
            border: 1px solid #a5d6a7;
            color: #2e7d32;
        }
        .step-connector-badge.manual {
            background: #fff3e0;
            border: 1px solid #ffcc80;
            color: #e65100;
        }

        .management-grid { display: grid; grid-template-columns: 1fr 1.5fr; gap: 25px; margin-bottom: 35px; }
        .card-management { background: #fff; padding: 30px; border-radius: 20px; border: 1px solid #e3e8ee; box-shadow: 0 10px 15px rgba(0,0,0,0.05); }
        .card-management h3 { font-size: 18px; margin-bottom: 20px; color: #3c4257; display: flex; align-items: center; gap: 12px; font-weight: 600; }
        
        .form-group { margin-bottom: 20px; position: relative; }
        .form-group label { display: block; font-size: 14px; font-weight: 500; color: #4f566b; margin-bottom: 8px; }
        .input-style, select.input-style { width: 100%; height: 48px; padding: 0 16px; border: 2px solid #e3e8ee; border-radius: 12px; font-size: 15px; background: #fff; display: flex; align-items: center; color: #3c4257; transition: border-color 0.2s; }
        .input-style:focus { outline: none; border-color: #0b4e12; }
        select.input-style { appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%234f566b' d='M6 9L1 4h10z'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 16px center; }

        /* name field state when auto-approve */
        .input-style.auto-mode { border-color: #4caf50; background: #f1fff3; color: #2e7d32; }
        .input-style.auto-mode::placeholder { color: #81c784; }

        .dropdown-content { display: none; position: absolute; top: 100%; left: 0; width: 100%; background: #fff; border: 1px solid #e3e8ee; border-radius: 14px; z-index: 1000; box-shadow: 0 20px 25px rgba(0,0,0,0.1); max-height: 250px; overflow-y: auto; padding: 10px; }
        .dropdown-content.show { display: block; }
        .checkbox-item { display: flex; align-items: center; gap: 12px; padding: 10px 12px; cursor: pointer; border-radius: 10px; font-size: 14px; }
        .checkbox-item:hover { background: #f7fafc; }

        .confirm-btn { background: #0b4e12; color: #fff; height: 50px; border-radius: 12px; border: none; cursor: pointer; font-weight: 600; width: 100%; transition: 0.3s; font-size: 16px; }
        .confirm-btn:hover { background: #083a0d; transform: translateY(-2px); }
        .btn-secondary { background: #6c757d; color: #fff; height: 40px; border-radius: 10px; border: none; cursor: pointer; font-weight: 500; padding: 0 20px; transition: 0.3s; font-size: 14px; }
        .btn-secondary:hover { background: #5a6268; }

        .auto-card { background: linear-gradient(135deg, #e8f5e9, #c8e6c9); border: 2px solid #4caf50; padding: 20px; border-radius: 14px; margin-bottom: 25px; }
        .auto-card h4 { color: #2e7d32; margin-bottom: 15px; display: flex; align-items: center; gap: 8px; }

        /* highlight box shown when auto-approve toggled */
        .auto-approve-active-hint {
            display: none;
            background: #e8f5e9;
            border: 1px dashed #4caf50;
            border-radius: 10px;
            padding: 10px 14px;
            font-size: 13px;
            color: #2e7d32;
            margin-top: 8px;
        }

        /* hint shown when auto-approve is NOT checked */
        .manual-assign-hint {
            display: none;
            background: #fff8e1;
            border: 1px dashed #ffb300;
            border-radius: 10px;
            padding: 10px 14px;
            font-size: 13px;
            color: #7b5c00;
            margin-top: 8px;
        }
        
        .disabled-field { opacity: 0.5; pointer-events: none; filter: grayscale(0.2); }

        .library-list { max-height: 400px; overflow-y: auto; margin-top: 20px; }
        .library-item { background: #f8f9fa; padding: 15px; border-radius: 12px; margin-bottom: 12px; display: flex; justify-content: space-between; align-items: center; border: 1px solid #e3e8ee; transition: 0.2s; }
        .library-item:hover { background: #fff; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        .library-item-info { flex: 1; }
        .library-item-name { font-weight: 600; color: #3c4257; font-size: 15px; margin-bottom: 4px; }
        .library-item-formats { font-size: 13px; color: #6c757d; }
        .library-item-actions { display: flex; gap: 8px; }
        .btn-icon { width: 35px; height: 35px; border-radius: 8px; border: none; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 18px; transition: 0.2s; }
        .btn-edit { background: #e7f3ff; color: #0056b3; }
        .btn-edit:hover { background: #0056b3; color: #fff; }
        .btn-delete-lib { background: #ffe0e0; color: #c53030; }
        .btn-delete-lib:hover { background: #c53030; color: #fff; }

        /* ── Step 2 card: highlight border when step 2 is required ── */
        .card-step2 { transition: border-color 0.35s, box-shadow 0.35s; }
        .card-step2.required {
            border: 2px solid #ff9800 !important;
            box-shadow: 0 0 0 4px rgba(255,152,0,0.12) !important;
        }
        .step2-required-banner {
            display: none;
            background: #fff3e0;
            border: 1px solid #ffb300;
            border-radius: 10px;
            padding: 12px 16px;
            font-size: 13px;
            color: #7b5c00;
            margin-bottom: 18px;
            align-items: center;
            gap: 10px;
        }
        .step2-required-banner i { font-size: 20px; flex-shrink: 0; }
        .step2-required-banner.visible { display: flex; }

        .table-container { background: #fff; border-radius: 20px; padding: 10px; border: 1px solid #e3e8ee; }
        .table { width: 100%; border-collapse: separate; border-spacing: 0; }
        .table th { background: #f9fafb; padding: 18px 20px; text-align: left; font-size: 12px; color: #4f566b; text-transform: uppercase; font-weight: 700; border-bottom: 2px solid #f0f2f5; }
        .table td { padding: 20px; border-bottom: 1px solid #f0f2f5; font-size: 15px; color: #3c4257; }
        
        .badge-format { background: #eef2ff; color: #4338ca; padding: 4px 10px; border-radius: 8px; font-size: 12px; font-weight: 600; border: 1px solid #e0e7ff; }
        .badge-auto { background: #d4edda; color: #155724; padding: 4px 10px; border-radius: 8px; font-size: 12px; font-weight: 600; border: 1px solid #c3e6cb; }
        .badge-global { background: #2d5016; color: white; padding: 4px 10px; border-radius: 8px; font-size: 11px; font-weight: 600; margin-left: 8px; }
        .badge-pending-assign { background: #fff3e0; color: #e65100; padding: 4px 10px; border-radius: 8px; font-size: 11px; font-weight: 600; border: 1px solid #ffcc80; margin-left: 8px; }
        .btn-delete { color: #a0aec0; font-size: 22px; transition: 0.2s; padding: 8px; }
        .btn-delete:hover { color: #e53e3e; background: #fff5f5; border-radius: 10px; }

        .modal { display: none; position: fixed; z-index: 9999; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); overflow: auto; }
        .modal-content { background-color: #fff; margin: 5% auto; padding: 30px; border-radius: 16px; width: 90%; max-width: 600px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .modal-header h3 { color: #0b4e12; font-size: 20px; font-weight: 600; }
        .modal-close { font-size: 28px; font-weight: bold; color: #aaa; cursor: pointer; line-height: 1; }
        .modal-close:hover { color: #000; }
        .modal-body { margin-bottom: 20px; }
        .modal-footer { display: flex; justify-content: flex-end; gap: 10px; }
        
        .info-text { font-size: 12px; color: #666; margin-top: 5px; }
        .scope-info { background: #e3f2fd; padding: 10px; border-radius: 8px; margin-bottom: 15px; font-size: 13px; }

        /* Step 2 — override panel notice */
        .override-notice {
            background: #fff8e1;
            border: 1px solid #ffe082;
            border-radius: 10px;
            padding: 12px 16px;
            font-size: 13px;
            color: #7b5c00;
            margin-bottom: 18px;
            display: flex;
            gap: 10px;
            align-items: flex-start;
        }
        .override-notice i { font-size: 18px; margin-top: 1px; flex-shrink: 0; }
    </style>
</head>
<body>
    
    <?php include 'sidebar_signa.php'; ?>

    <section class="home">
        <div class="dashboard-header">
            <h1>Requirement Settings</h1>
            <p>Role: <strong><?= ucwords($sig_type) ?></strong> | Scope: <strong><?= $is_class_adviser ? "Class Adviser (".htmlspecialchars($sig_dept).")" : ($is_restricted ? "Departmental (".htmlspecialchars($sig_dept).")" : "Global (All Courses)") ?></strong></p>
        </div>

        <?php if ($requirements_locked): ?>
        <div style="background:#fff3cd; border:2px solid #ffc107; border-radius:12px; padding:18px 24px; margin-bottom:25px; display:flex; align-items:center; gap:15px;">
            <i class='bx bx-lock-alt' style="font-size:28px; color:#856404;"></i>
            <div>
                <strong style="color:#856404; font-size:15px;">🔒 Requirements Are Currently Locked</strong>
                <p style="margin:4px 0 0; color:#856404; font-size:13px;">The administrator has locked requirement assignments. You cannot add or delete assignments at this time. Please contact the administrator.</p>
            </div>
        </div>
        <?php endif; ?>

        <!-- STEP FLOW BANNER -->
        <div class="step-flow-banner">
            <div class="step-flow-item">
                <div class="step-circle">1</div>
                <div class="step-flow-text">
                    <strong>Add to Library</strong>
                    <span>Define requirement name &amp; allowed formats</span>
                </div>
            </div>
            <div class="step-arrow">→</div>
            <!-- Badge updates dynamically based on auto-approve checkbox state -->
            <div id="stepConnectorBadge" class="step-connector-badge auto">⚡ Auto-Assigned to All Courses</div>
            <div class="step-arrow">→</div>
            <div class="step-flow-item">
                <div class="step-circle" style="background:#1e3c72;">2</div>
                <div class="step-flow-text">
                    <strong id="step2Label">Override (Optional)</strong>
                    <span id="step2Sub">Re-assign to specific course / year / section</span>
                </div>
            </div>
        </div>

        <div class="management-grid">
            <!-- LIBRARY SECTION -->
            <div class="card-management">
                <h3><i class='bx bx-bookmark-plus'></i> 1. Requirement Library</h3>
                
                <!-- Auto-Approve Info Card -->
                <div class="auto-card">
                    <h4><i class='bx bx-check-shield'></i> Auto-Approve Requirements</h4>
                    <p style="font-size: 13px; margin-bottom: 10px;">Check <strong>Auto-Approve</strong> to skip file uploads entirely — the requirement name is <em>optional</em> and auto-assigned to all courses immediately.</p>
                    <p style="font-size: 12px; color: #555;">Without Auto-Approve, the requirement is saved to the library only — you must <strong>manually assign</strong> it using <strong>Step 2</strong>.</p>
                </div>
                
                <form method="POST" id="addLibraryForm">
                    <input type="hidden" name="action" value="add_library">
                    <div class="form-group">
                        <label>
                            Requirement Name
                            <span id="nameOptionalBadge" style="display:none; margin-left:6px; background:#e8f5e9; color:#2e7d32; font-size:11px; font-weight:600; padding:2px 8px; border-radius:20px; border:1px solid #a5d6a7;">Optional</span>
                        </label>
                        <input type="text" name="new_requirement_name" id="newReqName" class="input-style" placeholder="e.g. Student Journal">
                        <div class="auto-approve-active-hint" id="autoApproveHint">
                            <i class='bx bx-info-circle'></i> Leave blank to use the name <strong>"Approved No Requirement"</strong>, or type your own.
                        </div>
                        <div class="manual-assign-hint" id="manualAssignHint">
                            <i class='bx bx-info-circle'></i> This requirement will be saved to the library only. Use <strong>Step 2</strong> to assign it to a specific course, year, and section.
                        </div>
                    </div>
                    <div class="form-group" id="formatGroupWrapper">
                        <label>Allowed File Formats <span style="font-size:12px; color:#aaa;">(required unless Auto-Approve)</span></label>
                        <div class="input-style dropdown-toggle" onclick="toggleLocalDropdown('libraryFormatDropdown')" id="formatDropdownTrigger">
                            <span id="libraryFormatPlaceholder" style="color:#a0aec0;">Select Formats...</span>
                            <i class='bx bx-chevron-down' style="margin-left:auto;"></i>
                        </div>
                        <div id="libraryFormatDropdown" class="dropdown-content">
                            <?php foreach($fixed_doc_types as $f): ?>
                                <label class="checkbox-item">
                                    <input type="checkbox" name="library_formats[]" value="<?= $f ?>" data-name="<?= $f ?>" onchange="updatePlaceholder('libraryFormatDropdown', 'libraryFormatPlaceholder', 'Select Formats...')">
                                    <?= $f ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="form-group">
                        <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                            <input type="checkbox" name="auto_approve" value="1" id="autoApproveCheck" onchange="toggleAutoApprove(this)">
                            <span>✅ Auto-Approve — No file upload needed, students get automatic approval</span>
                        </label>
                    </div>

                    <?php if (!$requirements_locked): ?>
                        <button type="submit" class="confirm-btn" id="addLibraryBtn">
                            <i class='bx bx-plus-circle'></i>&nbsp; <span id="addLibraryBtnText">Add to Library (Assign in Step 2)</span>
                        </button>
                    <?php else: ?>
                        <button type="button" class="confirm-btn" disabled style="opacity:0.4; cursor:not-allowed;">🔒 Assignments Locked</button>
                    <?php endif; ?>
                </form>

                <!-- LIBRARY LIST -->
                <div class="library-list">
                    <?php
                    $lib_stmt = $conn->prepare("
                        SELECT rl.*, 
                               (SELECT COUNT(*) FROM course_requirements cr WHERE cr.requirement_id = rl.id AND cr.signatory_id = rl.signatory_id) as assignment_count
                        FROM requirement_library rl 
                        WHERE rl.signatory_id = ? 
                        ORDER BY rl.requirement_name ASC
                    ");
                    $lib_stmt->bind_param("i", $current_sig_id);
                    $lib_stmt->execute();
                    $lib_query = $lib_stmt->get_result();
                    if ($lib_query->num_rows > 0):
                        while($lib = $lib_query->fetch_assoc()):
                            $needs_assignment = ($lib['auto_approve'] != 1 && $lib['assignment_count'] == 0);
                    ?>
                        <div class="library-item" style="<?= $needs_assignment ? 'border-color:#ffb300; background:#fffdf0;' : '' ?>">
                            <div class="library-item-info">
                                <div class="library-item-name">
                                    <?= htmlspecialchars($lib['requirement_name']) ?>
                                    <?php if(isset($lib['auto_approve']) && $lib['auto_approve'] == 1): ?>
                                        <span class="badge-auto" style="margin-left: 8px;">⚡ Auto-Approve</span>
                                    <?php elseif($needs_assignment): ?>
                                        <span class="badge-pending-assign">⚠️ Needs Assignment (Step 2)</span>
                                    <?php endif; ?>
                                </div>
                                <div class="library-item-formats">
                                    <?php if(isset($lib['auto_approve']) && $lib['auto_approve'] == 1): ?>
                                        <span style="color: #4caf50;">✅ No file required — Auto-approved globally</span>
                                    <?php else: ?>
                                        <?= !empty($lib['allowed_formats']) ? htmlspecialchars($lib['allowed_formats']) : '<em>No formats specified</em>' ?>
                                        <?php if(!$needs_assignment): ?>
                                            <span style="color:#0b4e12; font-weight:600; margin-left:6px;">· <?= $lib['assignment_count'] ?> assignment(s)</span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="library-item-actions">
                                <button class="btn-icon btn-edit" onclick="openEditModal(<?= $lib['id'] ?>, '<?= htmlspecialchars($lib['requirement_name'], ENT_QUOTES) ?>', '<?= htmlspecialchars($lib['allowed_formats'] ?? '', ENT_QUOTES) ?>', <?= isset($lib['auto_approve']) ? $lib['auto_approve'] : 0 ?>)">
                                    <i class='bx bx-edit'></i>
                                </button>
                                <button class="btn-icon btn-delete-lib" onclick="confirmLibraryDelete(<?= $lib['id'] ?>, '<?= htmlspecialchars($lib['requirement_name'], ENT_QUOTES) ?>')">
                                    <i class='bx bx-trash'></i>
                                </button>
                            </div>
                        </div>
                    <?php 
                        endwhile;
                    else:
                    ?>
                        <p style="text-align:center; color:#a0aec0; padding:20px;">No requirements in library yet.</p>
                    <?php endif; ?>
                    <?php $lib_stmt->close(); ?>
                </div>
            </div>

            <!-- STEP 2: ASSIGN / OVERRIDE -->
            <div class="card-management card-step2" id="step2Card">
                <h3><i class='bx bx-task'></i> 2. Assign Requirement <span id="step2HeaderBadge" style="font-size:13px; color:#a0aec0; font-weight:400;">(Optional — for override only)</span></h3>

                <!-- Dynamic banner — shown when a regular requirement was just added -->
                <div class="step2-required-banner" id="step2RequiredBanner">
                    <i class='bx bx-error-circle'></i>
                    <div>
                        <strong>Action Required:</strong> Your new requirement was added to the library. Now assign it to a specific course, year level, and section using this form.
                    </div>
                </div>

                <div class="override-notice" id="overrideNotice">
                    <i class='bx bx-info-circle'></i>
                    <div id="overrideNoticeText">
                        Requirements added with <strong>Auto-Approve</strong> are already assigned to all courses automatically.
                        Use this panel <strong>only</strong> if you need to assign a requirement to a <em>specific course, year level, or section</em>.
                    </div>
                </div>
                
                <form method="POST" id="assignForm" onsubmit="return validateAssignForm()">
                    <input type="hidden" name="action" value="assign_requirement">
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        <!-- Requirement Selection -->
                        <div class="form-group">
                            <label>Requirement</label>
                            <select name="requirement_id" id="req_select" class="input-style" required>
                                <option value="">-- Choose Requirement --</option>
                                <?php 
                                $lib_stmt2 = $conn->prepare("SELECT * FROM requirement_library WHERE signatory_id = ? ORDER BY requirement_name ASC");
                                $lib_stmt2->bind_param("i", $current_sig_id);
                                $lib_stmt2->execute();
                                $lib = $lib_stmt2->get_result();
                                while($l = $lib->fetch_assoc()):
                                    $auto_badge = ($l['auto_approve'] == 1) ? ' ⚡ [Auto-Approve]' : '';
                                ?>
                                    <option value="<?= $l['id'] ?>"><?= htmlspecialchars($l['requirement_name']) . $auto_badge ?></option>
                                <?php endwhile; ?>
                                <?php $lib_stmt2->close(); ?>
                            </select>
                        </div>

                        <!-- Course Selection -->
                        <div class="form-group">
                            <label>Target Courses</label>
                            <div class="input-style dropdown-toggle" onclick="toggleLocalDropdown('courseDropdown')">
                                <span id="coursePlaceholder" style="color:#a0aec0;">Select Courses...</span>
                                <i class='bx bx-chevron-down' style="margin-left:auto;"></i>
                            </div>
                            <div id="courseDropdown" class="dropdown-content">
                                <label class="checkbox-item" style="border-bottom: 2px solid #f0f2f5; font-weight: bold; color: #0b4e12; margin-bottom: 5px;">
                                    <input type="checkbox" id="selectAllCourses" onchange="toggleSelectAll('courseDropdown', this)"> Select All Available
                                </label>

                                <?php 
                                $courses_result = getSignatoryCourses($conn, $current_sig_id, $is_class_adviser, $adviser_classes, $is_program_head, $program_head_depts, $is_restricted, $sig_dept);
                                while($course_row = $courses_result->fetch_assoc()):
                                ?>
                                    <label class="checkbox-item">
                                        <input type="checkbox" name="course_ids[]" value="<?= $course_row['id'] ?>" 
                                               data-name="<?= htmlspecialchars($course_row['course_name']) ?>" 
                                               data-course="<?= htmlspecialchars($course_row['course_name']) ?>"
                                               onchange="updatePlaceholder('courseDropdown', 'coursePlaceholder', 'Select Courses...'); handleCourseSelection();">
                                        <?= htmlspecialchars($course_row['course_name']) ?>
                                    </label>
                                <?php endwhile; ?>
                            </div>
                        </div>

                        <!-- Year Level Selection -->
                        <div class="form-group">
                            <label>Year Level</label>
                            <select name="year_level" id="yearLevelSelect" class="input-style" required onchange="handleYearLevelChange()">
                                <option value="">-- Select Year Level --</option>
                                <?php
                                if ($is_class_adviser && !empty($adviser_classes)) {
                                    $all_years = [];
                                    foreach ($adviser_classes as $course => $years) {
                                        foreach ($years as $year => $sections) {
                                            $all_years[$year] = true;
                                        }
                                    }
                                    foreach ($all_years as $year => $v) {
                                        echo "<option value='".htmlspecialchars($year)."'>".htmlspecialchars($year)."</option>";
                                    }
                                    echo "<option value='All Years'>All Years (My Classes)</option>";
                                } else {
                                    echo "<option value='1st Year'>1st Year</option>";
                                    echo "<option value='2nd Year'>2nd Year</option>";
                                    echo "<option value='3rd Year'>3rd Year</option>";
                                    echo "<option value='4th Year'>4th Year</option>";
                                    echo "<option value='All Years'>All Years</option>";
                                }
                                ?>
                            </select>
                        </div>

                        <!-- Section Selection (Dynamic) -->
                        <div class="form-group" id="sectionGroup">
                            <label>Sections</label>
                            <div style="margin-bottom: 10px;">
                                <label style="display: flex; align-items: center; gap: 8px; font-weight: 500;">
                                    <input type="checkbox" name="all_sections_checkbox" id="allSectionsCheck" onchange="toggleAllSections(this)">
                                    Select All Sections
                                </label>
                            </div>
                            <div class="input-style dropdown-toggle" onclick="toggleLocalDropdown('sectionDropdown')" id="sectionDropdownToggle">
                                <span id="sectionPlaceholder" style="color:#a0aec0;">Select Sections...</span>
                                <i class='bx bx-chevron-down' style="margin-left:auto;"></i>
                            </div>
                            <div id="sectionDropdown" class="dropdown-content">
                                <!-- Sections will be populated dynamically -->
                            </div>
                        </div>

                        <div style="grid-column: span 2;">
                            <?php if (!$requirements_locked): ?>
                                <button type="submit" class="confirm-btn" style="background:#1e3c72;">
                                    <i class='bx bx-transfer'></i>&nbsp; Assign Requirement
                                </button>
                            <?php else: ?>
                                <button type="button" class="confirm-btn" disabled style="opacity:0.4; cursor:not-allowed;">🔒 Assignments Locked</button>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- ASSIGNMENTS TABLE -->
        <div class="table-container">
            <div id="bulkDeleteBar" style="display: none; background: #fff3cd; padding: 15px; border-radius: 12px; margin-bottom: 15px; justify-content: space-between; align-items: center; border: 2px solid #ffc107;" <?= $requirements_locked ? 'hidden' : '' ?>>
                <button onclick="bulkDeleteAssignments()" class="btn-delete-bulk" style="background: #dc3545; color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; font-weight: 600; transition: 0.3s;">
                    <i class='bx bx-trash'></i> Delete Selected
                </button>
            </div>
            
            <table class="table">
                <thead>
                    <tr>
                        <th style="width: 50px;">
                            <?php if (!$requirements_locked): ?>
                                <input type="checkbox" id="selectAllAssignments" onchange="toggleSelectAllAssignments(this)" style="cursor: pointer; width: 18px; height: 18px;">
                            <?php endif; ?>
                        </th>
                        <th>Requirement</th>
                        <th>Allowed Formats</th>
                        <th>Course</th>
                        <th>Year Level</th>
                        <th>Sections</th>
                        <th style="text-align:center;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $stmt_list = $conn->prepare("SELECT cr.id, rl.requirement_name, rl.auto_approve, cr.document_type_id, c.course_name, cr.year_level, cr.sections FROM course_requirements cr LEFT JOIN requirement_library rl ON cr.requirement_id = rl.id JOIN courses c ON cr.course_id = c.id WHERE cr.signatory_id = ? AND cr.requirements_configured = 1 ORDER BY cr.id DESC");
                    $stmt_list->bind_param("i", $current_sig_id);
                    $stmt_list->execute();
                    $res_list = $stmt_list->get_result();
                    if ($res_list->num_rows > 0):
                        while($row = $res_list->fetch_assoc()): 
                            $no_file = ($row['document_type_id'] === 'N/A' || $row['document_type_id'] === 'AUTO_APPROVE');
                            $is_auto = ($row['auto_approve'] == 1);
                            $is_global = ($row['year_level'] == 'All Years' && $row['sections'] == 'All Sections');
                    ?>
                        <tr>
                            <td style="text-align: center;">
                                <?php if (!$requirements_locked): ?>
                                    <input type="checkbox" class="assignment-checkbox" value="<?= $row['id'] ?>" onchange="updateBulkDeleteButton()" style="cursor: pointer; width: 18px; height: 18px;">
                                <?php endif; ?>
                            </td>
                            <td style="font-weight:600;">
                                <?= $row['requirement_name'] ?: '<span style="color:#d39e00;">System Approval Only</span>' ?>
                                <?php if($is_auto): ?>
                                    <span class="badge-auto">⚡ Auto-Approved</span>
                                <?php endif; ?>
                                <?php if($is_global): ?>
                                    <span class="badge-global"> 👥 All Signatories</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if($is_auto): ?>
                                    <span class="badge-auto">✅ Auto-Approved — No file needed</span>
                                <?php elseif(!$no_file): ?>
                                    <span class="badge-format"><?= htmlspecialchars($row['document_type_id']) ?></span>
                                <?php else: ?>
                                    <span style="color:#cbd5e0;">---</span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($row['course_name']) ?></td>
                            <td><?= htmlspecialchars($row['year_level'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($row['sections'] ?? 'N/A') ?></td>
                            <td style="text-align:center;">
                                <?php if (!$requirements_locked): ?>
                                    <a href="?delete_assign=<?= $row['id'] ?>" class="btn-delete" onclick="return confirm('⚠️ Remove this assignment?\n\nNote: This may affect student submissions.')"><i class='bx bx-trash-alt'></i></a>
                                <?php else: ?>
                                    <span style="color:#cbd5e0;"><i class='bx bx-lock-alt'></i></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php 
                        endwhile;
                    else:
                    ?>
                        <tr>
                            <td colspan="7" style="text-align:center; color:#a0aec0; padding:40px;">No assignments yet. Add a requirement above — if it is an Auto-Approve requirement it will be assigned globally. Otherwise use Step 2 to assign manually.</td>
                        </tr>
                    <?php endif; ?>
                    <?php $stmt_list->close(); ?>
                </tbody>
            </table>
        </div>
    </section>

    <!-- EDIT MODAL -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit Requirement</h3>
                <span class="modal-close" onclick="closeEditModal()">&times;</span>
            </div>
            <form method="POST" id="editLibraryForm">
                <input type="hidden" name="action" value="edit_library">
                <input type="hidden" name="library_id" id="edit_library_id">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Requirement Name</label>
                        <input type="text" name="edit_requirement_name" id="edit_requirement_name" class="input-style" required>
                    </div>
                    <div class="form-group">
                        <label>Allowed File Formats</label>
                        <div class="input-style dropdown-toggle" onclick="toggleLocalDropdown('editFormatDropdown')">
                            <span id="editFormatPlaceholder" style="color:#a0aec0;">Select Formats...</span>
                            <i class='bx bx-chevron-down' style="margin-left:auto;"></i>
                        </div>
                        <div id="editFormatDropdown" class="dropdown-content">
                            <?php foreach($fixed_doc_types as $f): ?>
                                <label class="checkbox-item">
                                    <input type="checkbox" name="edit_library_formats[]" value="<?= $f ?>" data-name="<?= $f ?>" class="edit-format-checkbox" onchange="updatePlaceholder('editFormatDropdown', 'editFormatPlaceholder', 'Select Formats...')">
                                    <?= $f ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="form-group">
                        <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                            <input type="checkbox" name="edit_auto_approve" value="1" id="edit_auto_approve">
                            <span>✅ Auto-Approve (No file upload needed)</span>
                        </label>
                        <small style="color: #6c757d; font-size: 12px; margin-top: 5px; display: block;">
                            <i class='bx bx-info-circle'></i> When enabled, students will be automatically approved without file upload.
                        </small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-secondary" onclick="closeEditModal()">Cancel</button>
                    <button type="submit" class="confirm-btn" style="width:auto; padding:0 30px;">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- DELETE LIBRARY CONFIRMATION FORM -->
    <form method="POST" id="deleteLibraryForm" style="display:none;">
        <input type="hidden" name="action" value="delete_library">
        <input type="hidden" name="delete_library_id" id="delete_library_id">
    </form>

    <!-- PREREQUISITE MODAL -->
    <div id="prereqModal" style="display:none; position:fixed; z-index:9999; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.5); overflow:auto;">
        <div style="background:#fff; margin:5% auto; padding:30px; border-radius:16px; width:90%; max-width:620px; max-height:80vh; overflow-y:auto; box-shadow:0 10px 30px rgba(0,0,0,0.2);">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                <h3 style="color:#1e1b4b; font-size:18px; font-weight:700;"><i class='bx bx-sort-alt-2'></i> Prerequisite Settings</h3>
                <span onclick="closePrereqModal()" style="font-size:28px; color:#aaa; cursor:pointer; line-height:1;">&times;</span>
            </div>
            <p style="font-size:13px; color:#555; margin-bottom:20px;">
                Toggle which signatory types must be fully cleared before students can comply with your requirements. Options are set by the administrator per course.
            </p>
            <div id="prereqModalBody">
                <p style="text-align:center; color:#aaa; padding:30px;">Loading...</p>
            </div>
        </div>
    </div>

    <script>
        // Class Adviser Data
        const isClassAdviser = <?= $is_class_adviser ? 'true' : 'false' ?>;
        const adviserClasses = <?= json_encode($adviser_classes) ?>;

        document.addEventListener("DOMContentLoaded", function() {
            const sidebar = document.querySelector('nav.sidebar');
            const toggle = document.querySelector(".toggle");
            if (toggle && sidebar) { toggle.addEventListener("click", () => sidebar.classList.toggle("close")); }
            
            handleCourseSelection();

            // Check if redirected after a regular (non-auto) add
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('step2_required') === '1') {
                showStep2Required();
            }

            const editAutoApprove = document.getElementById('edit_auto_approve');
            if (editAutoApprove) {
                editAutoApprove.addEventListener('change', function() {
                    const editFormatDropdown = document.getElementById('editFormatDropdown');
                    const editFormatPlaceholder = document.getElementById('editFormatPlaceholder');
                    
                    if (this.checked) {
                        editFormatDropdown.style.opacity = '0.5';
                        editFormatDropdown.style.pointerEvents = 'none';
                        editFormatPlaceholder.innerText = 'Auto-Approve (No formats needed)';
                        editFormatPlaceholder.style.color = '#4caf50';
                        document.querySelectorAll('.edit-format-checkbox').forEach(cb => cb.checked = false);
                    } else {
                        editFormatDropdown.style.opacity = '1';
                        editFormatDropdown.style.pointerEvents = 'auto';
                        editFormatPlaceholder.innerText = 'Select Formats...';
                        editFormatPlaceholder.style.color = '#a0aec0';
                    }
                });
            }

            // Initialise banner state (default: no auto-approve)
            updateStepBanner(false);
        });

        // ── Update step flow banner & Step 2 card based on auto-approve state ──
        function updateStepBanner(isAutoApprove) {
            const badge        = document.getElementById('stepConnectorBadge');
            const step2Label   = document.getElementById('step2Label');
            const step2Sub     = document.getElementById('step2Sub');
            const step2Badge   = document.getElementById('step2HeaderBadge');
            const step2Card    = document.getElementById('step2Card');
            const overrideText = document.getElementById('overrideNoticeText');
            const addBtnText   = document.getElementById('addLibraryBtnText');

            if (isAutoApprove) {
                badge.className = 'step-connector-badge auto';
                badge.textContent = '⚡ Auto-Assigned to All Courses';
                step2Label.textContent = 'Override (Optional)';
                step2Sub.textContent = 'Re-assign to specific course / year / section';
                step2Badge.textContent = '(Optional — for override only)';
                step2Badge.style.color = '#a0aec0';
                step2Card.classList.remove('required');
                overrideText.innerHTML = 'Requirements added with <strong>Auto-Approve</strong> are already assigned to all courses automatically. Use this panel <strong>only</strong> if you need to assign to a <em>specific course, year level, or section</em>.';
                addBtnText.textContent = 'Add to Library & Auto-Assign to All Courses';
            } else {
                badge.className = 'step-connector-badge manual';
                badge.textContent = '📋 Manual Assignment Required (Step 2)';
                step2Label.textContent = 'Assign Requirement (Required)';
                step2Sub.textContent = 'Choose course, year level & section';
                step2Badge.textContent = '(Required for non-auto-approve)';
                step2Badge.style.color = '#e65100';
                step2Card.classList.add('required');
                overrideText.innerHTML = 'This requirement will be saved to the library only. You <strong>must</strong> use this form to assign it to a specific course, year level, and section before students can see it.';
                addBtnText.textContent = 'Add to Library (Assign in Step 2)';
            }
        }

        function showStep2Required() {
            const banner = document.getElementById('step2RequiredBanner');
            const card   = document.getElementById('step2Card');
            banner.classList.add('visible');
            card.classList.add('required');
            card.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }

        // ── TOGGLE AUTO-APPROVE ──────────────────────────────────
        function toggleAutoApprove(checkbox) {
            const nameInput         = document.getElementById('newReqName');
            const nameOptBadge      = document.getElementById('nameOptionalBadge');
            const autoHint          = document.getElementById('autoApproveHint');
            const manualHint        = document.getElementById('manualAssignHint');
            const formatTrigger     = document.getElementById('formatDropdownTrigger');
            const formatPlaceholder = document.getElementById('libraryFormatPlaceholder');
            const formatCheckboxes  = document.querySelectorAll('input[name="library_formats[]"]');

            updateStepBanner(checkbox.checked);

            if (checkbox.checked) {
                // Name becomes optional
                nameInput.removeAttribute('required');
                nameInput.placeholder = 'Optional — leave blank to use "Approved No Requirement"';
                nameInput.classList.add('auto-mode');
                nameOptBadge.style.display = 'inline';
                autoHint.style.display = 'block';
                manualHint.style.display = 'none';

                // Disable format selection
                formatTrigger.style.opacity = '0.5';
                formatTrigger.style.pointerEvents = 'none';
                document.getElementById('libraryFormatDropdown').style.pointerEvents = 'none';
                formatPlaceholder.innerText = 'Not required — Auto-Approve enabled';
                formatPlaceholder.style.color = '#4caf50';
                formatCheckboxes.forEach(cb => cb.checked = false);
            } else {
                // Restore name
                nameInput.placeholder = 'e.g. Student Journal';
                nameInput.classList.remove('auto-mode');
                nameOptBadge.style.display = 'none';
                autoHint.style.display = 'none';
                manualHint.style.display = 'block';

                // Restore format selection
                formatTrigger.style.opacity = '1';
                formatTrigger.style.pointerEvents = 'auto';
                document.getElementById('libraryFormatDropdown').style.pointerEvents = 'auto';
                formatPlaceholder.innerText = 'Select Formats...';
                formatPlaceholder.style.color = '#a0aec0';
            }
        }

        function toggleLocalDropdown(id) {
            const dropdowns = document.querySelectorAll('.dropdown-content');
            dropdowns.forEach(d => { if(d.id !== id) d.classList.remove('show'); });
            document.getElementById(id).classList.toggle('show');
        }

        function updatePlaceholder(dropdownId, placeholderId, defaultText) {
            const container = document.getElementById(dropdownId);
            const selected = container.querySelectorAll('input:checked:not(#selectAllCourses)');
            const display = document.getElementById(placeholderId);
            let names = Array.from(selected).map(cb => cb.getAttribute('data-name'));
            display.innerText = names.length > 0 ? names.join(', ') : defaultText;
            display.style.color = names.length > 0 ? "#3c4257" : "#a0aec0";
        }

        function toggleSelectAll(id, master) {
            const checkboxes = document.querySelectorAll(`#${id} input[type="checkbox"]:not(#selectAllCourses):not(.disabled)`);
            checkboxes.forEach(cb => cb.checked = master.checked);
            updatePlaceholder(id, id === 'courseDropdown' ? 'coursePlaceholder' : 'sectionPlaceholder', 
                              id === 'courseDropdown' ? 'Select Courses...' : 'Select Sections...');
            if (id === 'courseDropdown') {
                handleCourseSelection();
            }
        }

        function handleCourseSelection() {
            const selectedCourses = document.querySelectorAll('input[name="course_ids[]"]:checked');
            const sectionGroup = document.getElementById('sectionGroup');
            const sectionDropdown = document.getElementById('sectionDropdown');
            
            if (selectedCourses.length === 1) {
                sectionGroup.style.display = 'block';
                populateSections();
            } else if (selectedCourses.length > 1) {
                sectionGroup.style.display = 'none';
                sectionDropdown.innerHTML = '';
            } else {
                sectionGroup.style.display = 'block';
                sectionDropdown.innerHTML = '<p style="padding:10px; color:#a0aec0;">Select a course first</p>';
            }
        }

        function handleYearLevelChange() {
            const yearLevel = document.getElementById('yearLevelSelect').value;
            const allSectionsCheck = document.getElementById('allSectionsCheck');
            
            if (yearLevel === 'All Years') {
                allSectionsCheck.checked = true;
                toggleAllSections(allSectionsCheck);
            } else {
                allSectionsCheck.checked = false;
                toggleAllSections(allSectionsCheck);
            }
            
            populateSections();
        }

        function populateSections() {
            const selectedCourses = document.querySelectorAll('input[name="course_ids[]"]:checked');
            const yearLevel = document.getElementById('yearLevelSelect').value;
            const sectionDropdown = document.getElementById('sectionDropdown');
            
            if (selectedCourses.length !== 1 || !yearLevel) {
                sectionDropdown.innerHTML = '<p style="padding:10px; color:#a0aec0;">Select course and year level first</p>';
                return;
            }

            const courseName = selectedCourses[0].getAttribute('data-course');
            
            fetch(`get_sections.php?course=${encodeURIComponent(courseName)}&year=${encodeURIComponent(yearLevel)}`)
                .then(response => response.json())
                .then(sections => {
                    if (sections.length > 0) {
                        let html = '';
                        sections.forEach(section => {
                            let isAllowed = true;
                            if (isClassAdviser) {
                                isAllowed = false;
                                if (adviserClasses[courseName] && adviserClasses[courseName][yearLevel] && adviserClasses[courseName][yearLevel].includes(section)) {
                                    isAllowed = true;
                                }
                            }
                            
                            if (isAllowed) {
                                html += `<label class="checkbox-item">
                                    <input type="checkbox" name="section_ids[]" value="${section}" data-name="${section}" onchange="updatePlaceholder('sectionDropdown', 'sectionPlaceholder', 'Select Sections...')">
                                    Section ${section}
                                </label>`;
                            }
                        });
                        
                        if (html === '') {
                            html = '<p style="padding:10px; color:#e53e3e;">No sections available for your assignment</p>';
                        }
                        
                        sectionDropdown.innerHTML = html;
                    } else {
                        sectionDropdown.innerHTML = '<p style="padding:10px; color:#a0aec0;">No sections found</p>';
                    }
                })
                .catch(error => {
                    console.error('Error fetching sections:', error);
                    sectionDropdown.innerHTML = '<p style="padding:10px; color:#e53e3e;">Error loading sections</p>';
                });
        }

        function toggleAllSections(checkbox) {
            const sectionInputs = document.querySelectorAll('input[name="section_ids[]"]');
            const sectionDropdownToggle = document.getElementById('sectionDropdownToggle');
            
            if (checkbox.checked) {
                sectionInputs.forEach(input => input.disabled = true);
                sectionDropdownToggle.style.opacity = '0.5';
                sectionDropdownToggle.style.pointerEvents = 'none';
                document.getElementById('sectionPlaceholder').innerText = 'All Sections Selected';
            } else {
                sectionInputs.forEach(input => input.disabled = false);
                sectionDropdownToggle.style.opacity = '1';
                sectionDropdownToggle.style.pointerEvents = 'auto';
                document.getElementById('sectionPlaceholder').innerText = 'Select Sections...';
                document.getElementById('sectionPlaceholder').style.color = '#a0aec0';
            }
        }

        function validateAssignForm() {
            const courses = document.querySelectorAll('input[name="course_ids[]"]:checked');
            const yearLevel = document.getElementById('yearLevelSelect').value;
            const reqSelect = document.getElementById('req_select');
            
            if (!reqSelect.value) {
                alert('⚠️ Please select a requirement.');
                return false;
            }

            if (courses.length === 0) {
                alert('⚠️ Please select at least one course.');
                return false;
            }
            
            if (!yearLevel) {
                alert('⚠️ Please select a year level.');
                return false;
            }
            
            if (courses.length === 1 && !document.getElementById('allSectionsCheck').checked) {
                const sections = document.querySelectorAll('input[name="section_ids[]"]:checked');
                if (sections.length === 0) {
                    alert('⚠️ Please select at least one section or check "Select All Sections".');
                    return false;
                }
            }
            
            return true;
        }

        function openEditModal(id, name, formats, autoApprove = false) {
            document.getElementById('edit_library_id').value = id;
            document.getElementById('edit_requirement_name').value = name;
            document.getElementById('edit_auto_approve').checked = autoApprove;
            
            document.querySelectorAll('.edit-format-checkbox').forEach(cb => cb.checked = false);
            
            if (autoApprove) {
                const editFormatDropdown = document.getElementById('editFormatDropdown');
                const editFormatPlaceholder = document.getElementById('editFormatPlaceholder');
                editFormatDropdown.style.opacity = '0.5';
                editFormatDropdown.style.pointerEvents = 'none';
                editFormatPlaceholder.innerText = 'Auto-Approve (No formats needed)';
                editFormatPlaceholder.style.color = '#4caf50';
            } else {
                const editFormatDropdown = document.getElementById('editFormatDropdown');
                const editFormatPlaceholder = document.getElementById('editFormatPlaceholder');
                editFormatDropdown.style.opacity = '1';
                editFormatDropdown.style.pointerEvents = 'auto';
                
                if (formats && formats !== 'AUTO_APPROVE') {
                    const formatArray = formats.split(', ');
                    document.querySelectorAll('.edit-format-checkbox').forEach(cb => {
                        if (formatArray.includes(cb.value)) {
                            cb.checked = true;
                        }
                    });
                    updatePlaceholder('editFormatDropdown', 'editFormatPlaceholder', 'Select Formats...');
                } else {
                    editFormatPlaceholder.innerText = 'Select Formats...';
                    editFormatPlaceholder.style.color = '#a0aec0';
                }
            }
            
            document.getElementById('editModal').style.display = 'block';
        }

        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }

        function confirmLibraryDelete(id, name) {
            if (confirm(`⚠️ DELETE REQUIREMENT FROM LIBRARY?\n\nRequirement: ${name}\n\nThis will permanently delete:\n✗ This requirement from the library\n✗ All course assignments using it\n✗ All student submissions for this requirement\n\nThis action cannot be undone!\n\nContinue?`)) {
                document.getElementById('delete_library_id').value = id;
                document.getElementById('deleteLibraryForm').submit();
            }
        }

        window.onclick = function(e) {
            const editModal = document.getElementById('editModal');
            if (e.target == editModal) {
                closeEditModal();
            }
            const prereqModal = document.getElementById('prereqModal');
            if (e.target == prereqModal) {
                closePrereqModal();
            }
            if (!e.target.closest('.dropdown-toggle') && !e.target.closest('.dropdown-content')) {
                document.querySelectorAll('.dropdown-content').forEach(d => d.classList.remove('show'));
            }
        }

        function toggleSelectAllAssignments(master) {
            const checkboxes = document.querySelectorAll('.assignment-checkbox');
            checkboxes.forEach(cb => cb.checked = master.checked);
            updateBulkDeleteButton();
        }

        function updateBulkDeleteButton() {
            const checkboxes = document.querySelectorAll('.assignment-checkbox:checked');
            const bulkBar = document.getElementById('bulkDeleteBar');
            
            if (checkboxes.length > 0) {
                bulkBar.style.display = 'flex';
            } else {
                bulkBar.style.display = 'none';
                document.getElementById('selectAllAssignments').checked = false;
            }
        }

        function bulkDeleteAssignments() {
            const checkboxes = document.querySelectorAll('.assignment-checkbox:checked');
            
            if (checkboxes.length === 0) {
                alert('⚠️ Please select at least one assignment to delete.');
                return;
            }
            
            const ids = Array.from(checkboxes).map(cb => cb.value);
            const confirmMsg = `⚠️ DELETE ${ids.length} ASSIGNMENT(S)?\n\nThis will remove the selected assignments and may affect student submissions.\n\nThis action cannot be undone!\n\nContinue?`;
            
            if (confirm(confirmMsg)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'requirementssigna.php';
                
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'bulk_delete_assignments';
                form.appendChild(actionInput);
                
                ids.forEach(id => {
                    const idInput = document.createElement('input');
                    idInput.type = 'hidden';
                    idInput.name = 'assignment_ids[]';
                    idInput.value = id;
                    form.appendChild(idInput);
                });
                
                document.body.appendChild(form);
                form.submit();
            }
        }

        function openPrereqModal() {
            document.getElementById('prereqModal').style.display = 'block';
            loadPrereqContent();
        }

        function closePrereqModal() {
            document.getElementById('prereqModal').style.display = 'none';
        }

        function loadPrereqContent() {
            fetch('get_signatory_prereqs.php')
                .then(r => r.json())
                .then(data => {
                    const body = document.getElementById('prereqModalBody');
                    if (!data || data.length === 0) {
                        body.innerHTML = '<p style="text-align:center; color:#aaa; padding:30px;">No prerequisite options have been set by the administrator yet.</p>';
                        return;
                    }
                    let html = '';
                    data.forEach(course => {
                        html += `<div style="margin-bottom:20px; background:#f8f9fa; border-radius:12px; padding:18px; border:1px solid #e3e8ee;">
                            <strong style="color:#0b4e12; font-size:14px; display:block; margin-bottom:12px;"><i class='bx bx-book'></i> ${course.course_name}</strong>
                            <form method="POST" action="requirementssigna.php">
                                <input type="hidden" name="action" value="save_signatory_prereqs">
                                <input type="hidden" name="prereq_course_id" value="${course.course_id}">`;
                        course.rules.forEach(rule => {
                            const checked = rule.signatory_enabled ? 'checked' : '';
                            const badge = rule.signatory_enabled
                                ? `<span style="background:#d4edda; color:#155724; padding:3px 10px; border-radius:20px; font-size:12px; font-weight:600;">Active</span>`
                                : `<span style="background:#f0f0f0; color:#888; padding:3px 10px; border-radius:20px; font-size:12px; font-weight:600;">Inactive</span>`;
                            html += `<div style="display:flex; align-items:center; gap:12px; padding:10px; background:#fff; border-radius:8px; margin-bottom:8px; border:1px solid #e8ecf0;">
                                <input type="checkbox" name="enabled_prereq_ids[]" value="${rule.id}" id="pr_${rule.id}" ${checked} style="width:18px; height:18px; cursor:pointer; accent-color:#0b4e12;">
                                <label for="pr_${rule.id}" style="cursor:pointer; flex:1;">
                                    <div style="font-weight:600; font-size:14px; color:#3c4257;">${rule.before_type}</div>
                                    <div style="font-size:12px; color:#888;">must be fully cleared before students can comply with you</div>
                                </label>
                                ${badge}
                            </div>`;
                        });
                        html += `<button type="submit" style="margin-top:10px; height:40px; font-size:14px; background:#0b4e12; color:#fff; border:none; border-radius:12px; cursor:pointer; font-weight:600; width:100%;">Save Settings</button>
                            </form>
                        </div>`;
                    });
                    body.innerHTML = html;
                })
                .catch(() => {
                    document.getElementById('prereqModalBody').innerHTML = '<p style="color:#e53e3e; text-align:center;">Error loading prerequisites.</p>';
                });
        }
    </script>
</body>
</html>