<?php
/**
 * FILE: clearance_template.php
 * DESCRIPTION: Shared clearance HTML template used by both student_history.php (print)
 *              and admin_verify_action.php (email body).
 * 
 * REQUIRED VARIABLES (pass in before including, or call getClearanceHTML()):
 *   $full_name, $user_course, $user_year, $user_section,
 *   $user_email, $username, $academic_year, $semester,
 *   $sigs_data, $registrar_name, $registrar_approved_date, $admin_approved
 */

function getClearanceHTML(
    $full_name,
    $user_course,
    $user_year,
    $user_section,
    $user_email,
    $username,
    $academic_year,
    $semester,
    $sigs_data,
    $registrar_name,
    $registrar_approved_date,
    $admin_approved
) {
    function _h($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

    ob_start();
?>
<div style="position:relative; text-align:center; font-family:Arial,sans-serif; padding:30px 40px; max-width:800px; margin:0 auto; min-height:100vh;">

    <!-- BACKGROUND LOGO -->
    <div style="position:absolute; top:50%; left:50%; transform:translate(-50%,-50%); opacity:0.08; z-index:0; width:400px; height:400px;">
        <img src="bpc-logo.png" style="width:100%; height:100%; object-fit:contain;" alt="BPC Logo">
    </div>

    <!-- CONTENT -->
    <div style="position:relative; z-index:1;">

        <!-- HEADER -->
        <div style="margin-bottom:25px;">
            <h2 style="margin:0; color:#006400; font-size:20px; font-weight:bold;">BULACAN POLYTECHNIC COLLEGE</h2>
            <p style="margin:3px 0; font-size:12px;">Bulihan, City of Malolos, Bulacan, Philippines</p>
            <p style="margin:3px 0; font-size:11px;">(044) 802 6716 / (044) 796 2306</p>
            <h3 style="margin:20px 0 25px 0; font-size:16px; font-weight:bold;">STUDENT CLEARANCE FORM</h3>
        </div>

        <!-- STUDENT INFORMATION -->
        <div style="text-align:left; margin-bottom:30px; font-size:13px;">
            <table style="width:100%; border-collapse:collapse; border:none;">
                <tr>
                    <td style="border:none; padding:6px 5px; width:15%;"><strong>Name:</strong></td>
                    <td style="border:none; border-bottom:1px solid #000; padding:6px 5px; width:35%;"><?= _h($full_name) ?></td>
                    <td style="border:none; padding:6px 5px; width:15%;"><strong>Section:</strong></td>
                    <td style="border:none; border-bottom:1px solid #000; padding:6px 5px; width:35%;"><?= _h($user_section) ?></td>
                </tr>
                <tr>
                    <td style="border:none; padding:6px 5px;"><strong>Course &amp; Year:</strong></td>
                    <td style="border:none; border-bottom:1px solid #000; padding:6px 5px;"><?= _h($user_course) ?> - <?= _h($user_year) ?></td>
                    <td style="border:none; padding:6px 5px;"><strong>Academic Year:</strong></td>
                    <td style="border:none; border-bottom:1px solid #000; padding:6px 5px;"><?= _h($academic_year) ?></td>
                </tr>
                <tr>
                    <td style="border:none; padding:6px 5px;"><strong>Student ID:</strong></td>
                    <td style="border:none; border-bottom:1px solid #000; padding:6px 5px;"><?= _h($username) ?></td>
                    <td style="border:none; padding:6px 5px;"><strong>Semester:</strong></td>
                    <td style="border:none; border-bottom:1px solid #000; padding:6px 5px;"><?= _h($semester) ?></td>
                </tr>
                <tr>
                    <td style="border:none; padding:6px 5px;"><strong>Email:</strong></td>
                    <td style="border:none; border-bottom:1px solid #000; padding:6px 5px;" colspan="3"><?= _h($user_email) ?></td>
                </tr>
            </table>
        </div>

        <!-- SIGNATORIES -->
        <div style="margin-top:35px;">
            <h4 style="text-align:center; font-size:14px; margin-bottom:15px; font-weight:bold;">Signatories</h4>
            <table style="width:100%; border:1px solid #000; border-collapse:collapse; font-size:12px;">
                <?php if (empty($sigs_data)): ?>
                <tr>
                    <td colspan="2" style="border:1px solid #000; padding:10px; text-align:center; color:#888;">
                        No signatories assigned.
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($sigs_data as $key => $data): ?>
                <tr>
                    <td style="border:1px solid #000; padding:10px; width:35%; text-align:left; vertical-align:middle;">
                        <?php
                            $label_display = $data['label'];
                            if (!empty($data['name']) && stripos($label_display, 'Class Adviser') !== false) {
                                $label_display = 'Class Adviser - ' . $data['name'];
                            }
                        ?>
                        <?= _h($label_display) ?>:
                    </td>
                    <td style="border:1px solid #000; padding:10px; width:65%; vertical-align:middle;">
                        <?php if ($data['approved']): ?>
                            <span style="color:green; font-size:11px;">✓ SIGNED - <?= _h($data['date']) ?></span>
                            <?php if (!empty($data['name'])): ?>
                                <span style="margin-left:10px; font-size:11px; color:#333;">(<?= _h($data['name']) ?>)</span>
                            <?php endif; ?>
                        <?php else: ?>
                            <span style="color:#aaa; font-size:11px; font-style:italic;">Not yet signed</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </table>
        </div>

        <!-- CERTIFICATION -->
        <div style="margin-top:40px; padding-top:20px;">
            <h4 style="text-align:center; font-size:14px; margin-bottom:20px; font-weight:bold;">CERTIFICATION</h4>
            <p style="font-size:12px; margin-bottom:40px; text-align:center;">
                I hereby certify that the above named student has no pending accountable requirements as required by<br>
                Bulacan Polytechnic College.
            </p>

            <div style="text-align:center;">
                <?php if ($admin_approved == 1): ?>
                    <div style="margin-bottom:10px;">
                        <span style="color:green; font-weight:bold; font-size:14px;">✓ SIGNED - <?= _h($registrar_approved_date) ?></span>
                    </div>
                    <div style="border-bottom:2px solid #000; width:300px; margin:0 auto 5px;"></div>
                    <?php if (!empty($registrar_name)): ?>
                        <div style="font-size:13px; margin-bottom:3px;"><?= _h($registrar_name) ?></div>
                    <?php endif; ?>
                    <div style="font-size:13px; font-weight:bold; margin-bottom:2px;">Karen-Anne Rose Payumo</div>
                    <strong style="font-size:13px;">College Registrar</strong>
                <?php else: ?>
                    <div style="height:60px;"></div>
                    <div style="border-bottom:2px solid #000; width:300px; margin:0 auto 5px;"></div>
                    <div style="font-size:13px; font-weight:bold; margin-bottom:2px;">Karen-Anne Rose Payumo</div>
                    <strong style="font-size:13px;">College Registrar</strong>
                    <div style="margin-top:3px; font-size:11px;">Date: __________</div>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>
<?php
    return ob_get_clean();
}