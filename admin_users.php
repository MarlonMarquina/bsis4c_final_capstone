<?php
/**
 * FILE: admin_users.php
 * DESCRIPTION: Hierarchical Admin Panel with Accordion View
 * UPDATED: Uses accordion structure similar to manage_signatories.php and manage_students.php
 */
include 'conn.php';
session_start();
date_default_timezone_set('Asia/Manila');
// --- Authorization ---
if (!isset($_SESSION['role']) || $_SESSION['role'] != "admin") {
    header("Location: login.php");
    exit();
}

$msg = $_GET['msg'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="stylesheet" href="styles.css">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users Directory | Admin Control</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
        body { font-family: 'Poppins', sans-serif; background-color: #E4E9F7; /* ← change from #f4f7f6 */ margin: 0; padding: 0; }
       .home { 
    padding: 25px; 
    min-height: 100vh; 
    box-sizing: border-box;
     background: #E4E9F7; 
}
.sidebar.close ~ .home { left: 88px; width: calc(100% - 88px); }

        .message { 
            background:#d4edda; 
            color:#155724; 
            padding:15px; 
            border-radius:8px; 
            margin-bottom:20px; 
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        /* Search Box */
        .filter-bar {
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
            align-items: center;
        }
        .search-box {
            position: relative;
            flex: 1;
            max-width: 500px;
        }
        .search-box input {
            width: 100%;
            padding: 12px 40px 12px 15px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s ease;
        }
        .search-box input:focus {
            border-color: #006400;
            outline: none;
            box-shadow: 0 0 0 3px rgba(0, 100, 0, 0.1);
        }

        /* Statistics Cards */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .stat-card.green { background: linear-gradient(135deg, #2d5016 0%, #1a3409 100%); }
        
        .stat-card h4 {
            margin: 0 0 10px 0;
            font-size: 14px;
            opacity: 0.9;
            font-weight: 500;
        }
        .stat-card .stat-number {
            font-size: 32px;
            font-weight: 700;
            margin: 0;
        }

        /* Hierarchy Container */
        .hierarchy-container {
            width: 100%;
            margin-top: 20px;
        }

        /* Category Headers */
        .category-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 15px;
            font-weight: 700;
            font-size: 18px;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .category-header.admin-header {
            background: linear-gradient(135deg, #2d5016 0%, #1a3409 100%);
        }
        .category-header.signatory-header {
            background: linear-gradient(135deg, #2d5016 0%, #1a3409 100%);
        }
        .category-header.student-header {
            background: linear-gradient(135deg, #2d5016 0%, #1a3409 100%);
        }

        /* Global Roles List (for Admins & Global Signatories) */
        .global-list {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 25px;
        }

        .user-card {
            background: white;
            padding: 15px 20px;
            margin-bottom: 10px;
            border-radius: 8px;
            border: 2px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s ease;
        }
        .user-card:hover {
            border-color: #3498db;
            box-shadow: 0 2px 8px rgba(52, 152, 219, 0.2);
        }
        .user-card .user-info {
            flex: 1;
        }
        .user-card .user-name {
            font-weight: 600;
            color: #2c3e50;
            font-size: 15px;
            margin-bottom: 4px;
        }
        .user-card .user-details {
            font-size: 13px;
            color: #7f8c8d;
        }
        .user-card .user-actions {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        /* Department/Course Level Accordion */
        .dept-row {
            background: #f8f9fa;
            padding: 15px 18px;
            margin-bottom: 8px;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
            border: 2px solid #e9ecef;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .dept-row:hover {
            background: #e9ecef;
            border-color: #2d5016;
        }
        .dept-row.expanded {
            background: linear-gradient(135deg, #2d5016 0%, #1a3409 100%);
            color: white;
            border-color: #2d5016;
        }
        .dept-row .toggle-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background-color: #dee2e6;
            color: #2d5016;
            transition: all 0.3s ease;
            font-size: 18px;
        }
        .dept-row.expanded .toggle-icon {
            transform: rotate(90deg);
            background-color: white;
            color: #2d5016;
        }
        .dept-row .dept-name {
            font-weight: 700;
            font-size: 16px;
            flex: 1;
        }
        .dept-row .dept-badge {
            background: #2d5016;
            color: white;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .dept-row.expanded .dept-badge {
            background: white;
            color: #2d5016;
        }

        /* Year/Section Container */
        .dept-content {
            display: none;
            padding-left: 40px;
            margin-top: 8px;
            margin-bottom: 10px;
        }
        .dept-content.active {
            display: block;
        }

        /* Year Level Row */
        .year-row {
            background: #fff;
            padding: 15px 18px;
            margin-bottom: 6px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            border: 2px solid #e9ecef;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .year-row:hover {
            background: #f8f9fa;
            border-color: #3498db;
        }
        .year-row.expanded {
            background: #3498db;
            color: white;
            border-color: #3498db;
        }
        .year-row .toggle-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background-color: #e9ecef;
            color: #3498db;
            transition: all 0.3s ease;
            font-size: 16px;
        }
        .year-row.expanded .toggle-icon {
            transform: rotate(90deg);
            background-color: white;
            color: #3498db;
        }
        .year-row .year-label {
            font-weight: 600;
            font-size: 15px;
            flex: 1;
        }
        .year-row .year-badge {
            background: #3498db;
            color: white;
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 11px;
            font-weight: 600;
        }
        .year-row.expanded .year-badge {
            background: white;
            color: #3498db;
        }

        /* Section Container */
        .section-container {
            display: none;
            padding-left: 40px;
            margin-top: 6px;
        }
        .section-container.active {
            display: block;
        }

        /* Section Row */
        .section-row {
            background: #fff;
            padding: 12px 16px;
            margin-bottom: 5px;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s ease;
            border: 2px solid #dee2e6;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .section-row:hover {
            background: #f1f8f1;
            border-color: #27ae60;
        }
        .section-row.expanded {
            background: #27ae60;
            color: white;
            border-color: #27ae60;
        }
        .section-row .toggle-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 22px;
            height: 22px;
            border-radius: 50%;
            background-color: #e9ecef;
            color: #27ae60;
            transition: all 0.3s ease;
            font-size: 14px;
        }
        .section-row.expanded .toggle-icon {
            transform: rotate(90deg);
            background-color: white;
            color: #27ae60;
        }
        .section-row .section-label {
            font-weight: 600;
            font-size: 14px;
            flex: 1;
        }
        .section-row .section-badge {
            background: #27ae60;
            color: white;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 600;
        }
        .section-row.expanded .section-badge {
            background: white;
            color: #27ae60;
        }

        /* Users Container */
        .users-container {
            display: none;
            padding-left: 40px;
            margin-top: 5px;
            margin-bottom: 10px;
        }
        .users-container.active {
            display: block;
        }

        /* Status Pills */
        .status-pill { 
            padding: 4px 10px; 
            border-radius: 50px; 
            font-size: 10px; 
            font-weight: 700; 
            text-transform: uppercase; 
        }
        .status-active { background: #e6fffa; color: #008767; border: 1px solid #008767; }
        .status-inactive { background: #fff5f5; color: #e53e3e; border: 1px solid #e53e3e; }
        .status-pending { background: #fff3cd; color: #856404; border: 1px solid #ffc107; }
        .status-under-review { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        .status-approved { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .status-not-requested { background: #f8f9fa; color: #6c757d; border: 1px solid #dee2e6; }

        /* Action Buttons */
        .btn-action { 
            padding: 6px 12px; 
            border-radius: 6px; 
            cursor: pointer; 
            font-size: 11px; 
            border: none; 
            font-weight: 600; 
            color: white; 
            transition: 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .btn-visit { background: #3182ce; }
        .btn-deactivate { background: #e53e3e; }
        .btn-activate { background: #38a169; }
        .btn-action:hover { opacity: 0.8; transform: translateY(-1px); }

        /* Empty State */
        .empty-section {
            padding: 30px;
            text-align: center;
            color: #adb5bd;
            font-size: 14px;
            background: #f8f9fa;
            border-radius: 8px;
            border: 2px dashed #dee2e6;
            margin: 15px 0;
        }
        .empty-section i {
            font-size: 40px;
            display: block;
            margin-bottom: 10px;
            opacity: 0.5;
        }

        /* Loading Spinner */
        .loading-spinner {
            text-align: center;
            padding: 40px;
            color: #adb5bd;
        }
        .loading-spinner i {
            font-size: 40px;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        /* Modal Styles */
        
       .modal { 
            display: none; 
            position: fixed; 
            z-index: 9999; 
            left: 0; 
            top: 0; 
            width: 100%; 
            height: 100%; 
            background: rgba(0,0,0,0.6); 
            backdrop-filter: blur(3px);
            overflow-y: auto;
        }
        .modal-content { 
            background: white; 
            margin: 5% auto; 
            padding: 25px; 
            width: 560px;
            max-width: 90vw;
            border-radius: 15px; 
            position: relative; 
            box-shadow: 0 10px 30px rgba(0,0,0,0.2); 
            max-height: 85vh; 
            overflow-y: auto;
            overflow-x: visible;
        }
        .close-modal { 
            position: absolute; 
            right: 20px; 
            top: 20px; 
            font-size: 24px; 
            cursor: pointer; 
            color: #999; 
        }
        .close-modal:hover { color: #333; }
        .status-item { 
            display: flex; 
            justify-content: space-between; 
            padding: 12px; 
            border-bottom: 1px solid #f0f0f0; 
            align-items: center; 
        }
        .status-badge { 
            padding: 4px 10px; 
            border-radius: 4px; 
            font-size: 11px; 
            font-weight: 600; 
        }
        .badge-cleared { background: #d4edda; color: #155724; }
        .badge-pending { background: #f8d7da; color: #721c24; }
        .btn-approve { 
            background: #27ae60; 
            color: white; 
            border: none; 
            padding: 12px; 
            border-radius: 8px; 
            cursor: pointer; 
            width: 100%; 
            font-weight: 700; 
            margin-top: 15px; 
        }
        .btn-approve:hover { background: #229954; }
        .student-info { 
            background: #f8f9fa; 
            padding: 15px; 
            border-radius: 8px; 
            margin-bottom: 15px; 
        }
        .student-info p { 
            margin: 5px 0; 
            font-size: 13px; 
        }
        /* Admin Edit Profile Modal */
        .admin-edit-modal {
            display: none;
            position: fixed;
            z-index: 10000;
            left: 0; top: 0;
            width: 100%; height: 100%;
            background: rgba(0,0,0,0.6);
            backdrop-filter: blur(3px);
            justify-content: center;
            align-items: center;
        }
        .admin-edit-modal.active { display: flex; }
        .admin-edit-modal .modal-box {
            background: white;
            width: 460px;
            max-width: 95vw;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.25);
            overflow: hidden;
            max-height: 90vh;
            overflow-y: auto;
        }
        .admin-edit-modal .modal-header {
            background: linear-gradient(135deg, #2d5016 0%, #1a3409 100%);
            color: white;
            padding: 20px 25px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .admin-edit-modal .modal-header h3 {
            margin: 0;
            font-size: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .admin-edit-modal .modal-close {
            background: rgba(255,255,255,0.2);
            border: none;
            color: white;
            width: 30px; height: 30px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 18px;
            display: flex; align-items: center; justify-content: center;
            transition: background 0.2s;
        }
        .admin-edit-modal .modal-close:hover { background: rgba(255,255,255,0.35); }
        .admin-edit-modal .modal-body { padding: 25px; }
        .admin-edit-modal .field-row {
            margin-bottom: 18px;
        }
        .admin-edit-modal .field-row label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: #555;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 6px;
        }
        .admin-edit-modal .field-display {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 10px 14px;
            font-size: 14px;
            color: #333;
        }
        .admin-edit-modal .field-display span { flex: 1; }
        .admin-edit-modal .btn-change {
            background: #2d5016 ;
            color: white;
            border: none;
            padding: 5px 13px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 11px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            transition: 0.2s;
            white-space: nowrap;
        }
        .admin-edit-modal .btn-change:hover { background: #6c3483; }
        /* Sub-modals (input + OTP) */
        .admin-sub-modal {
            display: none;
            position: fixed;
            z-index: 10100;
            left: 0; top: 0;
            width: 100%; height: 100%;
            background: rgba(0,0,0,0.5);
            justify-content: center;
            align-items: center;
        }
        .admin-sub-modal.active { display: flex; }
        .admin-sub-modal .sub-box {
            background: white;
            width: 400px;
            max-width: 95vw;
            border-radius: 12px;
            padding: 28px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.2);
            text-align: center;
        }
        .admin-sub-modal .sub-box h3 {
            color: #2d5016;
            margin: 0 0 8px;
            font-size: 17px;
        }
        .admin-sub-modal .sub-box p {
            color: #666;
            font-size: 13px;
            margin-bottom: 16px;
        }
        .admin-sub-modal .sub-input {
            width: 100%;
            padding: 11px 14px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 14px;
            box-sizing: border-box;
            margin-bottom: 8px;
            transition: border-color 0.2s;
        }
        .admin-sub-modal .sub-input:focus {
            border-color: #2d5016;
            outline: none;
        }
        .admin-sub-modal .sub-input.otp-input {
            text-align: center;
            letter-spacing: 10px;
            font-size: 22px;
            font-weight: 700;
        }
        .admin-sub-modal .hint {
            font-size: 11px;
            color: #999;
            margin-bottom: 16px;
        }
        .admin-sub-modal .pw-wrap {
            position: relative;
            margin-bottom: 10px;
        }
        .admin-sub-modal .pw-wrap .sub-input { margin-bottom: 0; }
        .admin-sub-modal .pw-toggle {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #aaa;
            font-size: 18px;
        }
        .admin-sub-modal .btn-row {
            display: flex;
            gap: 10px;
            margin-top: 18px;
        }
        .admin-sub-modal .btn-primary {
            flex: 1;
            background: #2d5016;
            color: white;
            border: none;
            padding: 11px;
            border-radius: 8px;
            font-weight: 700;
            cursor: pointer;
            font-size: 14px;
            transition: 0.2s;
        }
        .admin-sub-modal .btn-primary:hover { background: #6c3483; }
        .admin-sub-modal .btn-primary:disabled { background: #ccc; cursor: not-allowed; }
        .admin-sub-modal .btn-cancel {
            flex: 1;
            background: #e9ecef;
            color: #555;
            border: none;
            padding: 11px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            font-size: 14px;
            transition: 0.2s;
        }
        .admin-sub-modal .btn-cancel:hover { background: #dee2e6; }
        .admin-sub-modal .timer-text {
            font-size: 12px;
            color: #888;
            margin-top: 8px;
            min-height: 20px;
        }
        /* Edit Profile button on admin card */
        .btn-edit-profile {
            background: #2d5016;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 11px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: 0.2s;
        }
        .btn-edit-profile:hover { background: #2d5016; transform: translateY(-1px); }
        /* Checkbox styles */
        .student-checkbox {
            width: 18px; height: 18px;
            cursor: pointer;
            accent-color: #2d5016;
            margin-left: 8px;
            flex-shrink: 0;
        }
        /* Bulk action bar per section */
        .bulk-action-bar {
            display: flex;
            align-items: center;
            gap: 10px;
            background: #f0f8f0;
            border: 2px solid #2d5016;
            border-radius: 8px;
            padding: 10px 15px;
            margin-bottom: 8px;
        }
        .bulk-action-bar label {
            font-size: 13px;
            font-weight: 600;
            color: #2d5016;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .btn-bulk-approve {
            background: linear-gradient(135deg, #27ae60, #1e8449);
            color: white;
            border: none;
            padding: 7px 16px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: 0.2s;
            margin-left: auto;
        }
        .btn-bulk-approve:hover { opacity: 0.85; transform: translateY(-1px); }
        .btn-bulk-approve:disabled { background: #ccc; cursor: not-allowed; transform: none; }

        /* Nav arrows on modal */
       .modal-nav {
            position: fixed;
            top: 50%;
            transform: translateY(-50%);
            background: #2d5016;
            color: white;
            border: none;
            width: 44px; height: 44px;
            border-radius: 50%;
            font-size: 24px;
            cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            transition: 0.2s;
            z-index: 10002;
            box-shadow: 0 3px 12px rgba(0,0,0,0.3);
        }
        .modal-nav:hover { background: #1a3409; transform: translateY(-50%) scale(1.1); }
        .modal-nav:disabled { background: #aaa; cursor: not-allowed; transform: translateY(-50%); }
        .modal-nav.prev { left: calc(50% - 400px); }
.modal-nav.next { right: calc(50% - 360px); }

        /* Message box in modal */
        .admin-message-box {
            margin-top: 18px;
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            border: 2px solid #e9ecef;
        }
        .admin-message-box h4 {
            margin: 0 0 10px 0;
            color: #2d5016;
            font-size: 14px;
            display: flex; align-items: center; gap: 6px;
        }
        .admin-message-box textarea {
            width: 100%;
            padding: 10px 12px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 13px;
            font-family: 'Poppins', sans-serif;
            resize: vertical;
            min-height: 80px;
            box-sizing: border-box;
            transition: border-color 0.2s;
        }
        .admin-message-box textarea:focus {
            border-color: #2d5016;
            outline: none;
        }
        .btn-send-message {
            background: #3498db;
            color: white;
            border: none;
            padding: 9px 18px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            margin-top: 8px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: 0.2s;
        }
        .btn-send-message:hover { background: #2980b9; }
        .btn-send-message:disabled { background: #ccc; cursor: not-allowed; }
        .message-sent-notice {
            background: #d4edda;
            color: #155724;
            border-radius: 6px;
            padding: 8px 12px;
            font-size: 12px;
            margin-top: 8px;
            display: none;
        }
        /* Previous message display */
        .prev-message-display {
            background: #fff3cd;
            border-left: 4px solid #f39c12;
            border-radius: 6px;
            padding: 10px 12px;
            font-size: 12px;
            color: #856404;
            margin-bottom: 10px;
        }
        .prev-message-display strong { display: block; margin-bottom: 4px; }

        /* Add Admin Modal */
        .add-admin-modal {
            display: none;
            position: fixed;
            z-index: 10000;
            left: 0; top: 0;
            width: 100%; height: 100%;
            background: rgba(0,0,0,0.6);
            backdrop-filter: blur(3px);
            justify-content: center;
            align-items: center;
        }
        .add-admin-modal.active { display: flex; }
        .add-admin-modal .modal-box {
            background: white;
            width: 460px;
            max-width: 95vw;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.25);
            overflow: hidden;
        }
        .add-admin-modal .modal-header {
            background: linear-gradient(135deg, #2d5016 0%, #1a3409 100%)
            color: white;
            padding: 20px 25px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .add-admin-modal .modal-header h3 {
            margin: 0; font-size: 16px;
            display: flex; align-items: center; gap: 8px;
        }
        .add-admin-modal .modal-close {
            background: rgba(255,255,255,0.2);
            border: none; color: white;
            width: 30px; height: 30px;
            border-radius: 50%;
            cursor: pointer; font-size: 18px;
            display: flex; align-items: center; justify-content: center;
        }
        .add-admin-modal .modal-close:hover { background: rgba(255,255,255,0.35); }
        .add-admin-modal .modal-body { padding: 25px; }
        .add-admin-modal .field-row { margin-bottom: 16px; }
        .add-admin-modal .field-row label {
            display: block; font-size: 12px; font-weight: 600;
            color: #555; text-transform: uppercase;
            letter-spacing: 0.5px; margin-bottom: 6px;
        }
        .add-admin-modal .field-row input {
            width: 100%; padding: 10px 14px;
            border: 2px solid #e9ecef; border-radius: 8px;
            font-size: 14px; box-sizing: border-box;
            transition: border-color 0.2s;
        }
        .add-admin-modal .field-row input:focus {
            border-color: #2d5016 ; outline: none;
        }
        .add-admin-modal .btn-submit {
            width: 100%; background: #2d5016 ; color: white;
            border: none; padding: 12px;
            border-radius: 8px; font-weight: 700;
            cursor: pointer; font-size: 14px;
            margin-top: 5px; transition: 0.2s;
        }
        .add-admin-modal .btn-submit:hover { background: #2d5016 ; }
        .add-admin-modal .notice {
            background: #f3e5f5; border-left: 4px solid #2d5016 ;
            border-radius: 6px; padding: 10px 12px;
            font-size: 12px; color: #6c3483;
            margin-bottom: 18px;
        }
    </style>
</head>
<body>

<?php 
    $admin_user = $_SESSION['username'];
    $admin_stmt = $conn->prepare("SELECT full_name FROM users WHERE username = ? AND role = 'admin'");
    $admin_stmt->bind_param("s", $admin_user);
    $admin_stmt->execute();
    $admin_data = $admin_stmt->get_result()->fetch_assoc();
    $signatoryFullName = !empty($admin_data['full_name']) ? $admin_data['full_name'] : $admin_user;
    $userRole = "Administrator"; 
    include 'sidebar_admin.php'; 
?>

<section class="home">
       <div class="text"> 
    <div style="margin-bottom: 20px; display: flex; align-items: flex-start; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
        <div>
            <h2 style="color: #006400; margin: 0;">USER AUDIT & DIRECTORY</h2>
            <p style="color: #666; font-size: 14px;">Monitor account status and verify clearance compliance.</p>
        </div>
        <?php
            $can_add = false;
            $cap_stmt = $conn->prepare("SELECT can_add_admin FROM users WHERE username = ? AND role = 'admin'");
            $cap_stmt->bind_param("s", $admin_user);
            $cap_stmt->execute();
            $cap_row = $cap_stmt->get_result()->fetch_assoc();
            $cap_stmt->close();
            if ($cap_row && $cap_row['can_add_admin'] == 1) $can_add = true;
        ?>
        <?php 
$lock_stmt = $conn->prepare("SELECT requirement_lock FROM system_settings WHERE id = 1");
$lock_stmt->execute();
$lock_row = $lock_stmt->get_result()->fetch_assoc();
$lock_stmt->close();
$is_locked = ($lock_row['requirement_lock'] ?? 0) == 1;
?>
<?php if ($can_add): ?>
        <div style="display:flex; gap:10px; align-items:center;">
            <button onclick="openAddAdminModal()" style="
                background: linear-gradient(135deg, #2d5016, #1a3409);
                color: white; border: none; padding: 10px 20px;
                border-radius: 8px; font-weight: 700; font-size: 13px;
                cursor: pointer; display: inline-flex; align-items: center;
                gap: 8px; box-shadow: 0 3px 10px rgba(142,68,173,0.3);
                transition: 0.2s;">
                <i class='bx bx-user-plus'></i> Add Admin
            </button>
            <button onclick="openLockModal()" id="lockToggleBtn" style="
                background: <?= $is_locked ? 'linear-gradient(135deg,#c0392b,#922b21)' : 'linear-gradient(135deg,#2d5016,#1a3409)' ?>;
                color: white; border: none; padding: 10px 20px;
                border-radius: 8px; font-weight: 700; font-size: 13px;
                cursor: pointer; display: inline-flex; align-items: center;
                gap: 8px; transition: 0.2s;">
                <i class='bx <?= $is_locked ? "bx-lock-alt" : "bx-lock-open-alt" ?>'></i>
                <?= $is_locked ? ' Unlock Requirements' : ' Lock Requirements' ?>
            </button>
        </div>
        <?php endif; ?>

<!-- LOCK OTP MODAL -->
<div id="lockModal" style="display:none; position:fixed; z-index:10000; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.6); justify-content:center; align-items:center;">
    <div style="background:white; width:420px; max-width:95vw; border-radius:15px; overflow:hidden; box-shadow:0 10px 40px rgba(0,0,0,0.25);">
        <div style="background:linear-gradient(135deg,#2d5016,#1a3409); padding:20px 25px; display:flex; justify-content:space-between; align-items:center;">
            <h3 style="color:white; margin:0; font-size:16px;"><i class='bx bx-lock-alt'></i> <?= $is_locked ? 'Unlock' : 'Lock' ?> Requirements</h3>
            <button onclick="closeLockModal()" style="background:rgba(255,255,255,0.2); border:none; color:white; width:30px; height:30px; border-radius:50%; cursor:pointer; font-size:18px;">×</button>
        </div>
        <div style="padding:25px;">
            <!-- Step 1: Send OTP -->
            <div id="lockStep1">
                <p style="font-size:13px; color:#555; margin-bottom:20px;">
                    An OTP will be sent to your registered email to confirm this action.
                </p>
                <button onclick="sendLockOTP()" style="width:100%; background:#2d5016; color:white; border:none; padding:12px; border-radius:8px; font-weight:700; cursor:pointer; font-size:14px;">
                    Send OTP to My Email
                </button>
            </div>
            <!-- Step 2: Enter OTP -->
            <div id="lockStep2" style="display:none;">
                <p style="font-size:13px; color:#555; margin-bottom:15px;">Enter the 6-digit OTP sent to your email:</p>
                <input type="text" id="lockOtpInput" maxlength="6" placeholder="000000"
                    style="width:100%; padding:12px; text-align:center; letter-spacing:10px; font-size:22px; font-weight:700; border:2px solid #e9ecef; border-radius:8px; box-sizing:border-box; margin-bottom:10px;"
                    oninput="this.value=this.value.replace(/[^0-9]/g,'').slice(0,6)">
                <div id="lockOtpTimer" style="font-size:12px; color:#888; text-align:center; margin-bottom:15px;"></div>
                <button onclick="verifyLockOTP()" style="width:100%; background:#2d5016; color:white; border:none; padding:12px; border-radius:8px; font-weight:700; cursor:pointer; font-size:14px;">
                    Verify & <?= $is_locked ? 'Unlock' : 'Lock' ?>
                </button>
            </div>
            <div id="lockError" style="color:#c0392b; font-size:13px; margin-top:10px; text-align:center;"></div>
        </div>
    </div>
</div>
    </div>

    <?php if ($msg): ?>
        <div class="message">
            <i class='bx bx-info-circle'></i> 
            <?= htmlspecialchars($msg) ?>
        </div>
    <?php endif; ?>

    <!-- Search Bar -->
    <div class="filter-bar">
        <div class="search-box" style="flex: 1; max-width: 500px;">
            <input type="text" id="searchInput" placeholder="Search by name, username, course, or section..." style="flex-grow: 1;">
            <i class='bx bx-search' style="position: absolute; right: 15px; top: 50%; transform: translateY(-50%); color: #adb5bd; font-size: 18px;"></i>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="stats-container">
        <div class="stat-card green">
            <h4>Total Users</h4>
            <p class="stat-number" id="totalUsers">0</p>
        </div>
        <div class="stat-card green">
            <h4>Active Students</h4>
            <p class="stat-number" id="activeStudents">0</p>
        </div>
        <div class="stat-card green">
            <h4>Signatories</h4>
            <p class="stat-number" id="totalSignatories">0</p>
        </div>
        <div class="stat-card green">
            <h4>Pending Approvals</h4>
            <p class="stat-number" id="pendingApprovals">0</p>
        </div>
    </div>

    <!-- Hierarchical User Display -->
    <div class="hierarchy-container" id="userHierarchy">
        <div class="loading-spinner">
            <i class='bx bx-loader-alt'></i>
            <p>Loading user data...</p>
        </div>
    </div>
</section>

<!-- Verification Modal -->
<div id="verifyModal" class="modal">
    <!-- Navigation Arrows (outside modal-content so they don't scroll) -->
    <button class="modal-nav prev" id="modalPrevBtn" onclick="navigateModal(-1)" title="Previous Student">&#8249;</button>
    <button class="modal-nav next" id="modalNextBtn" onclick="navigateModal(1)" title="Next Student">&#8250;</button>
    <div class="modal-content">

        <span class="close-modal" onclick="closeModal()">&times;</span>
        <h3><i class='bx bx-shield-check'></i> Student Clearance Verification</h3>
        <div style="font-size:11px; color:#aaa; margin-bottom:10px;" id="modalNavCounter"></div>

        <div id="studentInfo" class="student-info"></div>
        
        <h4 style="margin-top: 20px; color: #333;">Signatory Compliance:</h4>
        <div id="requirementsList"></div>
        <div id="modalFooter"></div>

        <!-- Admin Message Box -->
        <div class="admin-message-box">
            <h4><i class='bx bx-envelope'></i> Send Message to Student</h4>
            <div id="prevMessageDisplay"></div>
            <textarea id="adminMessageInput" placeholder="Type a message to notify the student about their clearance status..."></textarea>
            <br>
            <button class="btn-send-message" id="sendMessageBtn" onclick="sendAdminMessage()">
                <i class='bx bx-send'></i> Send Message
            </button>
            <div class="message-sent-notice" id="messageSentNotice">
                <i class='bx bx-check-circle'></i> Message sent to student's email successfully!
            </div>
        </div>
    </div>
</div>

<!-- ===== ADD ADMIN MODAL ===== -->
<div id="addAdminModal" class="add-admin-modal">
    <div class="modal-box">
        <div class="modal-header">
            <h3><i class='bx bx-shield-plus'></i> Add New Admin</h3>
            <button class="modal-close" onclick="closeAddAdminModal()">×</button>
        </div>
        <div class="modal-body">
            <div class="notice">
                <i class='bx bx-info-circle'></i>
                This admin will have full access <strong>except</strong> the ability to add other admins.
            </div>
            <div class="field-row">
                <label>Full Name</label>
                <input type="text" id="newAdminFullName" placeholder="Enter full name">
            </div>
            <div class="field-row">
                <label>Username</label>
                <input type="text" id="newAdminUsername" placeholder="4-20 characters, letters/numbers/underscore">
            </div>
            <div class="field-row">
                <label>Email Address</label>
                <input type="email" id="newAdminEmail" placeholder="Enter email address">
            </div>
            <div class="field-row">
                <label>Password</label>
                <input type="password" id="newAdminPassword" placeholder="Minimum 8 characters">
            </div>
            <button class="btn-submit" onclick="submitAddAdmin()">
                <i class='bx bx-user-plus'></i> Create Admin Account
            </button>
        </div>
    </div>
</div>

<!-- ===== ADMIN EDIT PROFILE MODAL ===== -->
<div id="adminEditModal" class="admin-edit-modal">
    <div class="modal-box">
        <div class="modal-header">
            <h3><i class='bx bx-shield-alt'></i> Edit Admin Profile</h3>
            <button class="modal-close" onclick="closeAdminEditModal()">×</button>
        </div>
        <div class="modal-body">
            <div class="field-row">
                <label>Full Name</label>
                <div class="field-display">
                    <span id="aep_nameDisplay">—</span>
                    <button class="btn-change" onclick="openAdminSubModal('name')">
                        <i class='bx bx-edit'></i> Change
                    </button>
                </div>
            </div>
            <div class="field-row">
                <label>Username</label>
                <div class="field-display">
                    <span id="aep_usernameDisplay">—</span>
                    <button class="btn-change" onclick="openAdminSubModal('username')">
                        <i class='bx bx-edit'></i> Change
                    </button>
                </div>
            </div>
            <div class="field-row">
                <label>Email Address</label>
                <div class="field-display">
                    <span id="aep_emailDisplay">—</span>
                    <button class="btn-change" onclick="openAdminSubModal('email')">
                        <i class='bx bx-edit'></i> Change
                    </button>
                </div>
            </div>
            <div class="field-row">
                <label>Password</label>
                <div class="field-display">
                    <span>••••••••</span>
                    <button class="btn-change" onclick="openAdminSubModal('password')">
                        <i class='bx bx-edit'></i> Change
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ===== NAME INPUT SUB-MODAL ===== -->
<div id="adminNameModal" class="admin-sub-modal">
    <div class="sub-box">
        <h3>Change Full Name</h3>
        <p>Enter your new full name. An OTP will be sent to your current email to confirm.</p>
        <input type="text" id="adminNewNameInput" class="sub-input" placeholder="Enter full name">
        <div class="hint">Use your real full name as it will appear in official documents.</div>
        <div class="btn-row">
            <button class="btn-primary" id="nameModalBtn" onclick="adminSendOTP('name')">Send OTP</button>
            <button class="btn-cancel" onclick="closeAdminSubModal('name')">Cancel</button>
        </div>
    </div>
</div>

<!-- ===== USERNAME INPUT SUB-MODAL ===== -->
<div id="adminUsernameModal" class="admin-sub-modal">
    <div class="sub-box">
        <h3>Change Username</h3>
        <p>Enter your new username. An OTP will be sent to your current email to confirm.</p>
        <input type="text" id="adminNewUsernameInput" class="sub-input" placeholder="Enter new username">
        <div class="hint">4–20 characters (letters, numbers, underscore only)</div>
        <div class="btn-row">
            <button class="btn-primary" id="usernameModalBtn" onclick="adminSendOTP('username')">Send OTP</button>
            <button class="btn-cancel" onclick="closeAdminSubModal('username')">Cancel</button>
        </div>
    </div>
</div>

<!-- ===== EMAIL INPUT SUB-MODAL ===== -->
<div id="adminEmailModal" class="admin-sub-modal">
    <div class="sub-box">
        <h3>Change Email Address</h3>
        <p>Enter your new email. An OTP will be sent <strong>to the new email</strong> to verify it.</p>
        <input type="email" id="adminNewEmailInput" class="sub-input" placeholder="Enter new email address">
        <div class="hint">A 6-digit code will be sent to this address.</div>
        <div class="btn-row">
            <button class="btn-primary" id="emailModalBtn" onclick="adminSendOTP('email')">Send OTP</button>
            <button class="btn-cancel" onclick="closeAdminSubModal('email')">Cancel</button>
        </div>
    </div>
</div>

<!-- ===== PASSWORD INPUT SUB-MODAL ===== -->
<div id="adminPasswordModal" class="admin-sub-modal">
    <div class="sub-box">
        <h3>Change Password</h3>
        <p>Enter your current and new password. An OTP will be sent to your current email.</p>
        <div class="pw-wrap">
            <input type="password" id="adminCurrentPwInput" class="sub-input" placeholder="Current Password">
            <i class='bx bx-hide pw-toggle' onclick="adminTogglePw('adminCurrentPwInput', this)"></i>
        </div>
        <div class="pw-wrap">
            <input type="password" id="adminNewPwInput" class="sub-input" placeholder="New Password">
            <i class='bx bx-hide pw-toggle' onclick="adminTogglePw('adminNewPwInput', this)"></i>
        </div>
        <div class="pw-wrap">
            <input type="password" id="adminConfirmPwInput" class="sub-input" placeholder="Confirm New Password">
            <i class='bx bx-hide pw-toggle' onclick="adminTogglePw('adminConfirmPwInput', this)"></i>
        </div>
        <div class="hint">At least 8 characters.</div>
        <div class="btn-row">
            <button class="btn-primary" id="passwordModalBtn" onclick="adminSendOTP('password')">Send OTP</button>
            <button class="btn-cancel" onclick="closeAdminSubModal('password')">Cancel</button>
        </div>
    </div>
</div>

<!-- ===== SHARED OTP VERIFICATION SUB-MODAL ===== -->
<div id="adminOtpModal" class="admin-sub-modal">
    <div class="sub-box">
        <h3 id="adminOtpTitle">Verify Change</h3>
        <p>An OTP has been sent to <strong id="adminOtpDest">your email</strong>.</p>
        <input type="text" id="adminOtpInput" class="sub-input otp-input" placeholder="000000" maxlength="6"
               oninput="this.value=this.value.replace(/[^0-9]/g,'').slice(0,6)">
        <div class="timer-text" id="adminOtpTimer">Resend in 60s</div>
        <div class="btn-row">
            <button class="btn-primary" id="adminOtpVerifyBtn" onclick="adminVerifyOTP()">Verify</button>
            <button class="btn-cancel" onclick="closeAdminOtpModal()">Cancel</button>
        </div>
    </div>
</div>
<script>
// Global data
let allUsersData = [];
window._sectionMap = {};
// Modal navigation state
let currentSectionStudents = [];
let currentModalIndex = 0;
let currentModalStudentUsername = '';

// Load all users
function loadUserHierarchy() {
    fetch('get_all_users.php')
    .then(r => r.json())
    .then(users => {
        allUsersData = users;
        buildUserHierarchy(users);
        updateStatistics(users);
    })
    .catch(err => {
        document.getElementById('userHierarchy').innerHTML = `
            <div class="empty-section">
                <i class='bx bx-error-circle'></i>
                <p>Error loading user data. Please refresh the page.</p>
            </div>`;
    });
}

function buildUserHierarchy(users) {
    window._sectionMap = {};
    const container = document.getElementById('userHierarchy');
    let html = '';

    const admins     = users.filter(u => u.role === 'admin');
    const signatories = users.filter(u => u.role === 'signatory');
    const students   = users.filter(u => u.role === 'student');

    // 1. ADMINISTRATORS
    if (admins.length > 0) {
        html += `
            <div class="category-header admin-header">
                <i class='bx bx-shield-alt'></i> Administrators
            </div>
            <div class="global-list">`;

        // Sort: can_add_admin first, then the rest
        const sortedAdmins = [...admins].sort((a, b) => (b.can_add_admin == 1 ? 1 : 0) - (a.can_add_admin == 1 ? 1 : 0));
        const isCanAdmin = '<?php echo addslashes($admin_user); ?>' === sortedAdmins[0]?.username || <?php echo $can_add ? 'true' : 'false'; ?>;

        sortedAdmins.forEach(admin => {
            const statusClass = admin.status === 'active' ? 'status-active' : 'status-inactive';
            const isCurrentAdmin = admin.username === '<?php echo addslashes($admin_user); ?>';
            const isSuperAdmin = admin.can_add_admin == 1;

            html += `
                <div class="user-card" id="adminCard_${admin.username}">
                    <div class="user-info">
                        <div class="user-name" id="adminDisplayName_${admin.username}">
                            ${admin.full_name}
                            ${isSuperAdmin ? `<span style="background: #2d5016; color: white; font-size:10px;font-weight:700;padding:2px 8px;border-radius:10px;margin-left:8px;vertical-align:middle;">MAIN ADMIN</span>` : ''}
                        </div>
                        <div class="user-details">
                            <i class='bx bx-user'></i> <span id="adminDisplayUsername_${admin.username}">${admin.username}</span> | 
                            <i class='bx bx-envelope'></i> <span id="adminDisplayEmail_${admin.username}">${admin.email || 'N/A'}</span>
                        </div>
                    </div>
                    <div class="user-actions">
                        <span class="status-pill ${statusClass}">${admin.status}</span>
                        ${(isCurrentAdmin || (<?php echo $can_add ? 'true' : 'false'; ?> && !isSuperAdmin)) ? `
                        <button class="btn-edit-profile" onclick="openAdminEditModal('${admin.username}', '${admin.full_name.replace(/'/g,"\\'")}', '${(admin.email||'').replace(/'/g,"\\'")}')">
                            <i class='bx bx-edit'></i> ${isCurrentAdmin ? 'Edit Profile' : 'Edit'}
                        </button>` : ''}
                        ${<?php echo $can_add ? 'true' : 'false'; ?> && !isCurrentAdmin && !isSuperAdmin ? `
                        ${admin.status === 'active'
                            ? `<button class="btn-action btn-deactivate" onclick="toggleStatus('${admin.username}', 'inactive')">Deactivate</button>`
                            : `<button class="btn-action btn-activate" onclick="toggleStatus('${admin.username}', 'active')">Activate</button>`}
                        ` : ''}
                    </div>
                </div>`;
        });
        html += `</div>`;
    }

    // 2. SIGNATORIES
    if (signatories.length > 0) {
        html += `
            <div class="category-header signatory-header">
                <i class='bx bx-shield-alt-2'></i> Signatories
            </div>`;

        const globalRoles = ["Student Government (SG)", "PTCA", "Research Office", "Scholarship Office", "Registrar"];
        const globalSignatories = signatories.filter(s => globalRoles.includes(s.signatory_type));
        const programHeads  = signatories.filter(s => s.signatory_type === 'Program Head');
        const classAdvisers = signatories.filter(s => s.signatory_type === 'Class Adviser');

        if (globalSignatories.length > 0) {
            html += `<div class="global-list">`;
            globalSignatories.forEach(sig => {
                const statusClass = sig.status === 'active' ? 'status-active' : 'status-inactive';
                html += `
                    <div class="user-card">
                        <div class="user-info">
                            <div class="user-name"><i class='bx bx-shield-alt-2'></i> ${sig.signatory_type}</div>
                            <div class="user-details">
                                <strong>${sig.full_name}</strong> | 
                                <i class='bx bx-user'></i> ${sig.username} | 
                                <i class='bx bx-envelope'></i> ${sig.email || 'N/A'}
                            </div>
                        </div>
                        <div class="user-actions">
                            <span class="status-pill ${statusClass}">${sig.status}</span>
                            ${sig.status === 'active'
                                ? `<button class="btn-action btn-deactivate" onclick="toggleStatus('${sig.username}', 'inactive')">Deactivate</button>`
                                : `<button class="btn-action btn-activate" onclick="toggleStatus('${sig.username}', 'active')">Activate</button>`}
                        </div>
                    </div>`;
            });
            html += `</div>`;
        }

        if (programHeads.length > 0) {
            html += `
                <div style="margin-top:15px; font-weight:600; color:#f39c12; margin-bottom:10px;">
                    <i class='bx bx-building'></i> Program Heads
                </div>
                <div class="global-list">`;
            programHeads.forEach(ph => {
                const statusClass = ph.status === 'active' ? 'status-active' : 'status-inactive';
                const depts = ph.department ? ph.department.split(',').map(d=>d.trim()).join(', ') : 'No departments';
                html += `
                    <div class="user-card">
                        <div class="user-info">
                            <div class="user-name">${ph.full_name}</div>
                            <div class="user-details">
                                <i class='bx bx-building'></i> ${depts} | 
                                <i class='bx bx-user'></i> ${ph.username} | 
                                <i class='bx bx-envelope'></i> ${ph.email || 'N/A'}
                            </div>
                        </div>
                        <div class="user-actions">
                            <span class="status-pill ${statusClass}">${ph.status}</span>
                            ${ph.status === 'active'
                                ? `<button class="btn-action btn-deactivate" onclick="toggleStatus('${ph.username}', 'inactive')">Deactivate</button>`
                                : `<button class="btn-action btn-activate" onclick="toggleStatus('${ph.username}', 'active')">Activate</button>`}
                        </div>
                    </div>`;
            });
            html += `</div>`;
        }

        if (classAdvisers.length > 0) {
            html += `
                <div style="margin-top:15px; font-weight:600; color:#2d5016; margin-bottom:10px;">
                    <i class='bx bx-chalkboard'></i> Class Advisers
                </div>`;

            const adviserDeptGroups = {};
            classAdvisers.forEach(ca => {
                const depts = ca.department ? ca.department.split(',').map(d=>d.trim()) : [];
                depts.forEach(dept => {
                    if (!adviserDeptGroups[dept]) adviserDeptGroups[dept] = [];
                    adviserDeptGroups[dept].push(ca);
                });
            });

            Object.keys(adviserDeptGroups).sort().forEach((dept, index) => {
                const advisers = adviserDeptGroups[dept];
                html += `
                    <div class="dept-row" data-dept-index="adviser-${index}" onclick="toggleDept('adviser-${index}')">
                        <i class='bx bx-chevron-right toggle-icon'></i>
                        <span class="dept-name"><i class='bx bx-book-bookmark'></i> ${dept}</span>
                        <span class="dept-badge">${advisers.length} Adviser(s)</span>
                    </div>
                    <div class="dept-content" data-dept-index="adviser-${index}">`;

                advisers.forEach(adviser => {
                    const statusClass = adviser.status === 'active' ? 'status-active' : 'status-inactive';
                    const courseGroups = {};
                    if (adviser.section) {
                        adviser.section.split(',').map(c=>c.trim()).forEach(combo => {
                            if (combo.includes('|')) {
                                const parts = combo.split('|').map(p=>p.trim());
                                if (parts.length === 3) {
                                    const [crs, yr, sec] = parts;
                                    if (!courseGroups[crs]) courseGroups[crs] = [];
                                    courseGroups[crs].push(`${yr} - Section ${sec}`);
                                } else if (parts.length === 2) {
                                    const [yr, sec] = parts;
                                    if (!courseGroups[dept]) courseGroups[dept] = [];
                                    courseGroups[dept].push(`${yr} - Section ${sec}`);
                                }
                            }
                        });
                    }
                    let sectionDisplay = 'No sections assigned';
                    const courseKeys = Object.keys(courseGroups);
                    if (courseKeys.length > 0) {
                        const parts = [];
                        if (courseGroups[dept]) parts.push(`${dept}: ${courseGroups[dept].join(', ')}`);
                        courseKeys.forEach(crs => {
                            if (crs !== dept) parts.push(`(${crs}: ${courseGroups[crs].join(', ')})`);
                        });
                        sectionDisplay = parts.join('  ');
                    }
                    html += `
                        <div class="user-card" style="margin-bottom:8px;">
                            <div class="user-info">
                                <div class="user-name">${adviser.full_name}</div>
                                <div class="user-details">
                                    <i class='bx bx-calendar'></i> ${sectionDisplay} |
                                    <i class='bx bx-user'></i> ${adviser.username} | 
                                    <i class='bx bx-envelope'></i> ${adviser.email || 'N/A'}
                                </div>
                            </div>
                            <div class="user-actions">
                                <span class="status-pill ${statusClass}">${adviser.status}</span>
                                ${adviser.status === 'active'
                                    ? `<button class="btn-action btn-deactivate" onclick="toggleStatus('${adviser.username}', 'inactive')">Deactivate</button>`
                                    : `<button class="btn-action btn-activate" onclick="toggleStatus('${adviser.username}', 'active')">Activate</button>`}
                            </div>
                        </div>`;
                });
                html += `</div>`;
            });
        }
    }

    // 3. STUDENTS
    if (students.length > 0) {
        html += `
            <div class="category-header student-header">
                <i class='bx bxs-group'></i> Students
            </div>`;

        const courseGroups = {};
        students.forEach(student => {
            const course  = student.course   || 'No Course';
            const year    = student.year     || 'No Year';
            const section = student.section  || 'No Section';
            if (!courseGroups[course]) courseGroups[course] = {};
            if (!courseGroups[course][year]) courseGroups[course][year] = {};
            if (!courseGroups[course][year][section]) courseGroups[course][year][section] = [];
            courseGroups[course][year][section].push(student);
        });

        Object.keys(courseGroups).sort().forEach((course, courseIndex) => {
            const courseStudents = Object.values(courseGroups[course]).flatMap(y => Object.values(y).flat());
            const courseRequested = courseStudents.filter(s => s.final_clearance_status === 'pending').length;
            const courseApproved  = courseStudents.filter(s => s.admin_approved == 1).length;
            const coursePending  = courseStudents.filter(s => s.admin_messaged == 1 && s.admin_approved == 0 && s.final_clearance_status === 'pending').length;
const courseReminder = courseStudents.filter(s => s.admin_messaged == 1 && s.admin_approved == 0 && s.final_clearance_status !== 'pending').length;

            html += `
                <div class="dept-row" data-course-index="${courseIndex}" onclick="toggleCourse(${courseIndex})">
                    <i class='bx bx-chevron-right toggle-icon'></i>
                    <span class="dept-name"><i class='bx bx-book-bookmark'></i> ${course}</span>
                    <span class="dept-badge">
                        ${courseStudents.length} Students | 
                        <span style="color:#f0c040;">${courseRequested} Requested</span> | 
                        <span style="color:#90ee90;">${courseApproved} Approved</span>
                        ${coursePending > 0 ? ` | <span style="color:#f0c040;">${coursePending} Pending</span>` : ''}
                    </span>
                </div>
                <div class="dept-content" data-course-index="${courseIndex}">`;

            const yearOrder = ["1st Year","2nd Year","3rd Year","4th Year","5th Year"];
Object.keys(courseGroups[course]).sort((a,b) => yearOrder.indexOf(a) - yearOrder.indexOf(b)).forEach((year, yearIndex) => {
                const yearStudents = Object.values(courseGroups[course][year]).flat();
                const yearKey      = `${courseIndex}-${yearIndex}`;
                const yearRequested = yearStudents.filter(s => s.final_clearance_status === 'pending').length;
                const yearApproved  = yearStudents.filter(s => s.admin_approved == 1).length;
                const yearPending  = yearStudents.filter(s => s.admin_messaged == 1 && s.admin_approved == 0 && s.final_clearance_status === 'pending').length;
const yearReminder = yearStudents.filter(s => s.admin_messaged == 1 && s.admin_approved == 0 && s.final_clearance_status !== 'pending').length;

                html += `
                    <div class="year-row" data-year-key="${yearKey}" onclick="event.stopPropagation(); toggleYear('${yearKey}')">
                        <i class='bx bx-chevron-right toggle-icon'></i>
                        <span class="year-label"><i class='bx bx-graduation'></i> ${year}</span>
                        <span class="year-badge">
                            ${yearStudents.length} Students | 
                            <span style="color:#f0c040;">${yearRequested} Requested</span> | 
                            <span style="color:#90ee90;">${yearApproved} Approved</span>
                            ${yearPending > 0 ? ` | <span style="color:#f0c040;">${yearPending} Pending</span>` : ''}
                        </span>
                    </div>
                    <div class="section-container" data-year-key="${yearKey}">`;

                Object.keys(courseGroups[course][year]).sort().forEach((section, sectionIndex) => {
                    const sectionStudents = courseGroups[course][year][section];
const sectionKey      = `${yearKey}-${sectionIndex}`;
const sectionRequested = sectionStudents.filter(s => s.final_clearance_status === 'pending').length;
const sectionApproved  = sectionStudents.filter(s => s.admin_approved == 1).length;

// Find class adviser for this section
const sectionAdviser = allUsersData.find(u =>
    u.role === 'signatory' &&
    u.signatory_type === 'Class Adviser' &&
    u.section && u.section.split(',').map(s => s.trim()).some(combo => {
        const parts = combo.split('|').map(p => p.trim());
        return parts.length === 3 && parts[0] === course && parts[1] === year && parts[2] === section;
    })
);
const adviserLabel = sectionAdviser ? ` | Adviser: ${sectionAdviser.full_name}` : '';
                   const sectionPending  = sectionStudents.filter(s => s.admin_messaged == 1 && s.admin_approved == 0 && s.final_clearance_status === 'pending').length;
const sectionReminder = sectionStudents.filter(s => s.admin_messaged == 1 && s.admin_approved == 0 && s.final_clearance_status !== 'pending').length;

                    // Build student list for this section (used by modal nav)
                    const sectionUsernames = sectionStudents.map(s => s.username);
sectionUsernames.forEach(un => { window._sectionMap[un] = sectionUsernames; });

                   html += `
                        <div class="section-row" data-section-key="${sectionKey}" onclick="event.stopPropagation(); toggleSection('${sectionKey}')">
                            <i class='bx bx-chevron-right toggle-icon'></i>
                            <span class="section-label"><i class='bx bx-group'></i> ${section}</span>
                            ${sectionAdviser ? `<span style="font-size:14px; font-weight:700; flex:2; text-align:center;"><i class='bx bx-chalkboard'></i> Class Adviser: ${sectionAdviser.full_name}</span>` : '<span style="flex:2;"></span>'}
                            <span class="section-badge">
                                ${sectionStudents.length} Students | 
                                <span style="color:#f0c040;">${sectionRequested} Requested</span> | 
                                <span style="color:#90ee90;">${sectionApproved} Approved</span>
                                ${sectionPending > 0 ? ` | <span style="color:#f0c040;">${sectionPending} Pending</span>` : ''}
                            </span>
                        </div>
                        <div class="users-container" data-section-key="${sectionKey}">

                            <!-- Bulk Action Bar --> 
                            <div class="bulk-action-bar">
                                <label>
                                    <input type="checkbox" class="select-all-cb" data-section="${sectionKey}" onchange="toggleSelectAll('${sectionKey}', this.checked)">
                                    Select All
                                </label>
                                <span id="selectedCount_${sectionKey}" style="font-size:12px; color:#555;"></span>
                                <button class="btn-bulk-approve" id="bulkBtn_${sectionKey}" disabled onclick="bulkApprove('${sectionKey}')">
                                    <i class='bx bx-check-shield'></i> Bulk Approve Selected
                                </button>
                            </div>`;

                    // Student cards
                    sectionStudents.forEach(student => {
                        const statusClass = student.status === 'active' ? 'status-active' : 'status-inactive';
                        let clearanceStatus, clearanceClass;
                        if (student.admin_approved == 1 && student.admin_messaged == 1) {
                            clearanceStatus = 'Approved + Follow-up'; clearanceClass = 'status-approved';
                        } else if (student.admin_approved == 1) {
                            clearanceStatus = 'Approved'; clearanceClass = 'status-approved';
                        } else if (student.admin_messaged == 1 && student.final_clearance_status === 'pending') {
                            clearanceStatus = 'Pending'; clearanceClass = 'status-pending';
                        } else if (student.admin_messaged == 1) {
                            clearanceStatus = 'Reminder Sent'; clearanceClass = 'status-pending';
                        } else if (student.final_clearance_status === 'pending') {
                            clearanceStatus = 'Under Review'; clearanceClass = 'status-under-review';
                        } else {
                            clearanceStatus = 'Not Requested'; clearanceClass = 'status-not-requested';
                        }

                        const sectionUsernamesJson = JSON.stringify(sectionUsernames).replace(/'/g, "\\'");

                        html += `
                            <div class="user-card" style="margin-bottom:8px;" id="studentCard_${student.username}">
                                <div class="user-info">
                                    <div class="user-name">${student.full_name}</div>
                                    <div class="user-details">
                                        <i class='bx bx-id-card'></i> ${student.student_id || student.username} | 
                                        <i class='bx bx-envelope'></i> ${student.email || 'N/A'}
                                    </div>
                                </div>
                                <div class="user-actions">
                                    <span class="status-pill ${statusClass}">${student.status}</span>
                                    <span class="status-pill ${clearanceClass}">${clearanceStatus}</span>
<button class="btn-action btn-visit" data-username="${student.username}" onclick="event.stopPropagation(); openVerification(this.dataset.username)">                                        <i class='bx bx-show'></i> View
                                    </button>
                                    <input type="checkbox" class="student-checkbox section-cb-${sectionKey}" 
                                           data-username="${student.username}" data-section="${sectionKey}"
                                           onclick="event.stopPropagation()"
                                           onchange="updateBulkBar('${sectionKey}')"
                                           ${student.admin_approved == 1 ? 'disabled title="Already approved"' : student.final_clearance_status !== 'pending' ? 'disabled title="Student has not requested clearance yet"' : ''}>
                                </div>
                            </div>`;
                    });

                    html += `</div>`; // close users-container only
                });

                html += `</div>`; // close section-container
            });

            html += `</div>`; // close dept-content (course)
        });
    }

    container.innerHTML = html || `
        <div class="empty-section">
            <i class='bx bx-user-x'></i>
            <p>No users found.</p>
        </div>`;
}

function updateStatistics(users) {
    document.getElementById('totalUsers').textContent       = users.length;
    document.getElementById('activeStudents').textContent   = users.filter(u => u.role === 'student' && u.status === 'active').length;
    document.getElementById('totalSignatories').textContent = users.filter(u => u.role === 'signatory').length;
    document.getElementById('pendingApprovals').textContent = users.filter(u => u.role === 'student' && u.final_clearance_status === 'pending' && u.admin_approved == 0).length;
}

// ===== ACCORDION TOGGLES =====
function toggleDept(deptIndex) {
    const deptRow     = document.querySelector(`.dept-row[data-dept-index="${deptIndex}"]`);
    const deptContent = document.querySelector(`.dept-content[data-dept-index="${deptIndex}"]`);
    const isExpanded  = deptRow.classList.contains('expanded');
    document.querySelectorAll('.dept-row[data-dept-index]').forEach(r => r.classList.remove('expanded'));
    document.querySelectorAll('.dept-content[data-dept-index]').forEach(c => c.classList.remove('active'));
    if (!isExpanded) { deptRow.classList.add('expanded'); deptContent.classList.add('active'); }
}

function toggleCourse(index) {
    const courseRow     = document.querySelector(`.dept-row[data-course-index="${index}"]`);
    const courseContent = document.querySelector(`.dept-content[data-course-index="${index}"]`);
    const isExpanded    = courseRow.classList.contains('expanded');
    document.querySelectorAll('.dept-row[data-course-index]').forEach(r => r.classList.remove('expanded'));
    document.querySelectorAll('.dept-content[data-course-index]').forEach(c => c.classList.remove('active'));
    document.querySelectorAll('.year-row').forEach(r => r.classList.remove('expanded'));
    document.querySelectorAll('.section-container').forEach(c => c.classList.remove('active'));
    document.querySelectorAll('.section-row').forEach(r => r.classList.remove('expanded'));
    document.querySelectorAll('.users-container').forEach(c => c.classList.remove('active'));
    if (!isExpanded) { courseRow.classList.add('expanded'); courseContent.classList.add('active'); }
}

function toggleYear(yearKey) {
    const yearRow          = document.querySelector(`.year-row[data-year-key="${yearKey}"]`);
    const sectionContainer = document.querySelector(`.section-container[data-year-key="${yearKey}"]`);
    const isExpanded       = yearRow.classList.contains('expanded');
    const courseIndex      = yearKey.split('-')[0];
    document.querySelectorAll('.year-row').forEach(r => {
        if (r.dataset.yearKey && r.dataset.yearKey.startsWith(courseIndex + '-')) r.classList.remove('expanded');
    });
    document.querySelectorAll('.section-container').forEach(c => {
        if (c.dataset.yearKey && c.dataset.yearKey.startsWith(courseIndex + '-')) c.classList.remove('active');
    });
    document.querySelectorAll('.section-row').forEach(r => r.classList.remove('expanded'));
    document.querySelectorAll('.users-container').forEach(c => c.classList.remove('active'));
    if (!isExpanded) { yearRow.classList.add('expanded'); sectionContainer.classList.add('active'); }
}

function toggleSection(sectionKey) {
    const sectionRow    = document.querySelector(`.section-row[data-section-key="${sectionKey}"]`);
    const usersContainer = document.querySelector(`.users-container[data-section-key="${sectionKey}"]`);
    const isExpanded    = sectionRow.classList.contains('expanded');
    const yearKey       = sectionKey.substring(0, sectionKey.lastIndexOf('-'));
    document.querySelectorAll('.section-row').forEach(r => {
        if (r.dataset.sectionKey && r.dataset.sectionKey.startsWith(yearKey + '-')) r.classList.remove('expanded');
    });
    document.querySelectorAll('.users-container').forEach(c => {
        if (c.dataset.sectionKey && c.dataset.sectionKey.startsWith(yearKey + '-')) c.classList.remove('active');
    });
    if (!isExpanded) { sectionRow.classList.add('expanded'); usersContainer.classList.add('active'); }
}

// ===== SEARCH =====
document.getElementById('searchInput').addEventListener('input', function() {
    const term = this.value.toLowerCase().trim();
    if (!term) { loadUserHierarchy(); return; }
    const filtered = allUsersData.filter(u =>
        (u.full_name||'').toLowerCase().includes(term) ||
        (u.username||'').toLowerCase().includes(term) ||
        (u.course||'').toLowerCase().includes(term) ||
        (u.section||'').toLowerCase().includes(term) ||
        (u.email||'').toLowerCase().includes(term)
    );
    buildUserHierarchy(filtered);
    updateStatistics(filtered);
});

// ===== STATUS TOGGLE =====
function toggleStatus(username, newStatus) {
    if (confirm(`Set account ${username} to ${newStatus}?`)) {
        window.location.href = `admin_audit_action.php?id=${username}&status=${newStatus}`;
    }
}

// ===== CHECKBOXES & BULK APPROVE =====
function toggleSelectAll(sectionKey, checked) {
    document.querySelectorAll(`.section-cb-${sectionKey}:not(:disabled)`).forEach(cb => cb.checked = checked);
    updateBulkBar(sectionKey);
}

function updateBulkBar(sectionKey) {
    const checked = document.querySelectorAll(`.section-cb-${sectionKey}:checked`).length;
    const total   = document.querySelectorAll(`.section-cb-${sectionKey}:not(:disabled)`).length;
    const countEl = document.getElementById(`selectedCount_${sectionKey}`);
    const btnEl   = document.getElementById(`bulkBtn_${sectionKey}`);
    const allCb   = document.querySelector(`.select-all-cb[data-section="${sectionKey}"]`);
    if (countEl) countEl.textContent = checked > 0 ? `${checked} selected` : '';
    if (btnEl)   btnEl.disabled = checked === 0;
    if (allCb)   allCb.indeterminate = checked > 0 && checked < total;
    if (allCb && checked === total && total > 0) allCb.checked = true;
    if (allCb && checked === 0) allCb.checked = false;
}

function bulkApprove(sectionKey) {
    const checked = [...document.querySelectorAll(`.section-cb-${sectionKey}:checked`)];
    if (checked.length === 0) return;
    const names = checked.map(cb => cb.dataset.username);
    if (!confirm(`Approve clearance for ${checked.length} student(s)?\n\n${names.join(', ')}`)) return;

    const btn = document.getElementById(`bulkBtn_${sectionKey}`);
    btn.disabled = true;
    btn.innerHTML = `<i class='bx bx-loader-alt' style="animation:spin 1s linear infinite"></i> Approving...`;

    fetch('admin_bulk_approve.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ usernames: names })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert(`✅ ${data.approved} student(s) approved successfully.`);
            loadUserHierarchy();
        } else {
            alert('Error: ' + (data.message || 'Bulk approve failed.'));
            btn.disabled = false;
            btn.innerHTML = `<i class='bx bx-check-shield'></i> Bulk Approve Selected`;
        }
    })
    .catch(() => {
        alert('Request failed. Please try again.');
        btn.disabled = false;
        btn.innerHTML = `<i class='bx bx-check-shield'></i> Bulk Approve Selected`;
    });
}

// ===== VERIFICATION MODAL =====
function openVerification(studentUsername) {
    currentSectionStudents   = window._sectionMap[studentUsername] || [];
    currentModalStudentUsername = studentUsername;
    currentModalIndex        = currentSectionStudents.indexOf(studentUsername);

    document.getElementById('verifyModal').style.display = 'block';
    loadModalData(studentUsername);
}

function loadModalData(studentUsername) {
    currentModalStudentUsername = studentUsername;
    currentModalIndex = currentSectionStudents.indexOf(studentUsername);

    // Update nav arrows
    const prevBtn = document.getElementById('modalPrevBtn');
    const nextBtn = document.getElementById('modalNextBtn');
    const counter = document.getElementById('modalNavCounter');
    prevBtn.disabled = currentModalIndex <= 0;
    nextBtn.disabled = currentModalIndex >= currentSectionStudents.length - 1;
    if (currentSectionStudents.length > 1) {
        counter.textContent = `Student ${currentModalIndex + 1} of ${currentSectionStudents.length}`;
    } else {
        counter.textContent = '';
    }

    // Reset message area
    document.getElementById('adminMessageInput').value = '';
    document.getElementById('messageSentNotice').style.display = 'none';

    // Load student data
    document.getElementById('studentInfo').innerHTML = `<p style="color:#aaa; font-size:13px;"><i class='bx bx-loader-alt'></i> Loading...</p>`;
    document.getElementById('requirementsList').innerHTML = '';
    document.getElementById('modalFooter').innerHTML = '';

    fetch('get_student_status.php?id=' + studentUsername)
    .then(res => res.json())
    .then(data => {
        if (data.error) { alert(data.error); return; }

        document.getElementById('studentInfo').innerHTML = `
            <p><strong>Student Name:</strong> ${data.student_info.full_name}</p>
            <p><strong>Student ID:</strong> ${data.student_info.student_id}</p>
            <p><strong>Course:</strong> ${data.student_info.course} — ${data.student_info.year}</p>
            <p><strong>Section:</strong> ${data.student_info.section}</p>
            <p><strong>Clearance Status:</strong> <span class="status-badge ${data.student_info.clearance_class}">${data.student_info.clearance_status}</span></p>
        `;

        let reqHtml = '';
        let clearedCount = 0;
        data.requirements.forEach(item => {
            const bClass = item.status === 'cleared' ? 'badge-cleared' : 'badge-pending';
            if (item.status === 'cleared') clearedCount++;
            reqHtml += `
    <div class="status-item">
        <span><strong>${item.office}</strong></span>
        <span style="font-size:12px; color:#666;">${item.progress} requirements</span>
        <span class="status-badge ${bClass}">${item.status.toUpperCase()}</span>
    </div>`;
        });
        document.getElementById('requirementsList').innerHTML = reqHtml;

        const totalRequired = data.requirements.length;
        const canApprove = clearedCount === totalRequired && totalRequired > 0 && data.student_info.clearance_status === 'Under Review';

        if (data.student_info.admin_approved == 1) {
            document.getElementById('modalFooter').innerHTML = `
                <div style="text-align:center; color:#28a745; font-weight:600; margin-top:15px;">
                    <i class='bx bx-check-circle' style="font-size:24px;"></i><br>Already Approved
                </div>`;
        } else if (canApprove) {
            document.getElementById('modalFooter').innerHTML = `
                <button class="btn-approve" onclick="confirmFinal('${studentUsername}')">
                    <i class='bx bx-check-shield'></i> APPROVE FINAL CLEARANCE
                </button>`;
        } else if (data.student_info.clearance_status === 'Under Review') {
            document.getElementById('modalFooter').innerHTML = `
                <p style="text-align:center; color:#dc3545; font-size:12px; margin-top:10px;">
                    ⚠️ Incomplete Requirements (${clearedCount}/${totalRequired})
                </p>`;
        } else {
            document.getElementById('modalFooter').innerHTML = `
                <p style="text-align:center; color:#6c757d; font-size:12px; margin-top:10px;">
                    Student has not requested verification yet
                </p>`;
        }

        // Show previous message if any
        const prevDisplay = document.getElementById('prevMessageDisplay');
        if (data.student_info.admin_messaged == 1 && data.student_info.admin_message_text) {
            prevDisplay.innerHTML = `
                <div class="prev-message-display">
                    <strong><i class='bx bx-message-check'></i> Previous message sent to this student:</strong>
                    ${escHtml(data.student_info.admin_message_text)}
                </div>`;
        } else {
            prevDisplay.innerHTML = '';
        }
    })
    .catch(() => alert('Error loading student data.'));
}

function navigateModal(direction) {
    const newIndex = currentModalIndex + direction;
    if (newIndex < 0 || newIndex >= currentSectionStudents.length) return;
    loadModalData(currentSectionStudents[newIndex]);
}

function closeModal() {
    document.getElementById('verifyModal').style.display = 'none';
    currentSectionStudents = [];
    currentModalIndex = 0;
}

function confirmFinal(sid) {
    if (confirm("Confirm final approval? Student can then generate their clearance PDF.")) {
        window.location.href = "admin_verify_action.php?action=approve&id=" + sid;
    }
}

// ===== SEND ADMIN MESSAGE =====
function sendAdminMessage() {
    const msg = document.getElementById('adminMessageInput').value.trim();
    if (!msg) { alert('Please type a message first.'); return; }
    if (!confirm(`Send this message to the student's email?`)) return;

    const btn = document.getElementById('sendMessageBtn');
    btn.disabled = true;
    btn.innerHTML = `<i class='bx bx-loader-alt'></i> Sending...`;

    fetch('admin_send_message.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ username: currentModalStudentUsername, message: msg })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            document.getElementById('messageSentNotice').style.display = 'block';
            document.getElementById('adminMessageInput').value = '';
            // Refresh the prev message display
            document.getElementById('prevMessageDisplay').innerHTML = `
                <div class="prev-message-display">
                    <strong><i class='bx bx-message-check'></i> Message just sent:</strong>
                    ${escHtml(msg)}
                </div>`;
            // Update the in-memory data so badge counts update on next rebuild
            const u = allUsersData.find(x => x.username === currentModalStudentUsername);
            if (u) { u.admin_messaged = 1; u.admin_message_text = msg; }
        } else {
            alert('Error: ' + (data.message || 'Failed to send message.'));
        }
    })
    .catch(() => alert('Request failed. Please try again.'))
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = `<i class='bx bx-send'></i> Send Message`;
    });
}

function escHtml(text) {
    return text.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/\n/g,'<br>');
}

// ===== ADD ADMIN MODAL =====
function openAddAdminModal() {
    ['newAdminFullName','newAdminUsername','newAdminEmail','newAdminPassword'].forEach(id => {
        document.getElementById(id).value = '';
    });
    document.getElementById('addAdminModal').classList.add('active');
}

function closeAddAdminModal() {
    document.getElementById('addAdminModal').classList.remove('active');
}

function submitAddAdmin() {
    const full_name = document.getElementById('newAdminFullName').value.trim();
    const username  = document.getElementById('newAdminUsername').value.trim();
    const email     = document.getElementById('newAdminEmail').value.trim();
    const password  = document.getElementById('newAdminPassword').value.trim();

    if (!full_name || !username || !email || !password) {
        alert('Please fill in all fields.'); return;
    }

    fetch('admin_add_admin.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ full_name, username, email, password })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert('✅ Admin account created successfully!');
            closeAddAdminModal();
            loadUserHierarchy();
        } else {
            alert('Error: ' + (data.message || 'Failed to create admin.'));
        }
    })
    .catch(() => alert('Request failed. Please try again.'));
}

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    loadUserHierarchy();
});

// LOCK MODAL FUNCTIONS
function openLockModal() {
    document.getElementById('lockModal').style.display = 'flex';
    document.getElementById('lockStep1').style.display = 'block';
    document.getElementById('lockStep2').style.display = 'none';
    document.getElementById('lockOtpInput') && (document.getElementById('lockOtpInput').value = '');
    document.getElementById('lockError').textContent = '';
}

function closeLockModal() {
    document.getElementById('lockModal').style.display = 'none';
}

function sendLockOTP() {
    document.getElementById('lockError').textContent = '';
    fetch('admin_toggle_lock.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=send_otp'
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            document.getElementById('lockStep1').style.display = 'none';
            document.getElementById('lockStep2').style.display = 'block';
            startLockOtpTimer(300);
        } else {
            document.getElementById('lockError').textContent = data.message || 'Failed to send OTP.';
        }
    })
    .catch(() => document.getElementById('lockError').textContent = 'Request failed. Try again.');
}

function verifyLockOTP() {
    const otp = document.getElementById('lockOtpInput').value.trim();
    if (otp.length !== 6) {
        document.getElementById('lockError').textContent = 'Please enter the 6-digit OTP.';
        return;
    }
    document.getElementById('lockError').textContent = '';

    fetch('admin_toggle_lock.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=verify_and_toggle&otp=' + encodeURIComponent(otp)
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            closeLockModal();
            alert(data.new_status === '1' ? '🔒 Requirements are now LOCKED.' : '🔓 Requirements are now UNLOCKED.');
            location.reload();
        } else {
            document.getElementById('lockError').textContent = data.message || 'Invalid OTP.';
        }
    })
    .catch(() => document.getElementById('lockError').textContent = 'Request failed. Try again.');
}

let lockOtpTimerInterval;
function startLockOtpTimer(duration) {
    clearInterval(lockOtpTimerInterval);
    let t = duration;
    const el = document.getElementById('lockOtpTimer');
    el.textContent = `OTP expires in ${Math.floor(t/60)}:${String(t%60).padStart(2,'0')}`;
    lockOtpTimerInterval = setInterval(() => {
        t--;
        if (t > 0) {
            el.textContent = `OTP expires in ${Math.floor(t/60)}:${String(t%60).padStart(2,'0')}`;
        } else {
            clearInterval(lockOtpTimerInterval);
            el.textContent = 'OTP expired. Please close and try again.';
        }
    }, 1000);
}
// ========== ADMIN EDIT PROFILE (unchanged) ==========
let adminEditTarget = {};
let adminPendingChange = {};
let adminOtpTimer;

function openAdminEditModal(username, fullName, email) {
    const isSelfEdit = username === '<?php echo addslashes($admin_user); ?>';
    const isMainAdmin = <?php echo $can_add ? 'true' : 'false'; ?>;

    // Only allow: self-edit OR main admin editing others
    if (!isSelfEdit && !isMainAdmin) {
        alert('You do not have permission to edit this profile.');
        return;
    }

    adminEditTarget = { username, fullName, email, isSelfEdit };
    document.getElementById('aep_nameDisplay').textContent     = fullName;
    document.getElementById('aep_usernameDisplay').textContent = username;
    document.getElementById('aep_emailDisplay').textContent    = email || 'N/A';
    document.getElementById('adminEditModal').classList.add('active');
}
function closeAdminEditModal() { document.getElementById('adminEditModal').classList.remove('active'); }
function openAdminSubModal(type) {
    const isMainAdminEditingOther = !adminEditTarget.isSelfEdit && <?php echo $can_add ? 'true' : 'false'; ?>;

    const inputMap = { name:'adminNewNameInput', username:'adminNewUsernameInput', email:'adminNewEmailInput' };
    if (inputMap[type]) document.getElementById(inputMap[type]).value = '';
    if (type === 'password') {
        ['adminCurrentPwInput','adminNewPwInput','adminConfirmPwInput'].forEach(id => {
            const el = document.getElementById(id); el.value = ''; el.type = 'password';
        });
        document.querySelectorAll('.admin-sub-modal .pw-toggle').forEach(i => {
            i.classList.remove('bx-show'); i.classList.add('bx-hide');
        });
    }

    // Hide current password field if main admin editing others
    const currentPwWrap = document.getElementById('adminCurrentPwInput')?.closest('.pw-wrap');
    if (currentPwWrap) currentPwWrap.style.display = isMainAdminEditingOther ? 'none' : 'block';

    // Update the description text in each modal
    const descMap = {
        name: { self: 'Enter your new full name. An OTP will be sent to your current email to confirm.', other: 'Enter the new full name for this admin.' },
        username: { self: 'Enter your new username. An OTP will be sent to your current email to confirm.', other: 'Enter the new username for this admin.' },
        email: { self: 'Enter your new email. An OTP will be sent to the new email to verify it.', other: 'Enter the new email for this admin.' },
        password: { self: 'Enter your current and new password. An OTP will be sent to your email.', other: 'Enter a new password for this admin.' }
    };
    const modalEl = document.getElementById(`admin${type.charAt(0).toUpperCase()+type.slice(1)}Modal`);
    const descEl  = modalEl?.querySelector('p');
    if (descEl && descMap[type]) descEl.textContent = isMainAdminEditingOther ? descMap[type].other : descMap[type].self;

    // Update button
    const btnId = type + 'ModalBtn';
    const btn = document.getElementById(btnId);
    if (btn) {
        if (isMainAdminEditingOther) {
            btn.textContent = 'Save Changes';
            btn.onclick = () => applyAdminChangeDirectly(type);
        } else {
            btn.textContent = 'Send OTP';
            btn.onclick = () => adminSendOTP(type);
        }
    }

    modalEl.classList.add('active');
}
function closeAdminSubModal(type) {
    document.getElementById(`admin${type.charAt(0).toUpperCase()+type.slice(1)}Modal`).classList.remove('active');
}
function closeAdminOtpModal() { document.getElementById('adminOtpModal').classList.remove('active'); clearInterval(adminOtpTimer); }
function adminTogglePw(inputId, icon) {
    const el = document.getElementById(inputId);
    el.type = el.type === 'password' ? 'text' : 'password';
    icon.classList.toggle('bx-hide'); icon.classList.toggle('bx-show');
}
function adminSendOTP(type) {
    let destination = '';
    if (type === 'name') {
        const val = document.getElementById('adminNewNameInput').value.trim();
        if (!val) { alert('Please enter a name.'); return; }
        destination = val; adminPendingChange = { type, value: val };
    } else if (type === 'username') {
        const val = document.getElementById('adminNewUsernameInput').value.trim();
        if (!val) { alert('Please enter a username.'); return; }
        if (!/^[a-zA-Z0-9_]{4,20}$/.test(val)) { alert('Username must be 4-20 characters.'); return; }
        if (val === adminEditTarget.username) { alert('This is already your current username.'); return; }
        destination = adminEditTarget.email; adminPendingChange = { type, value: val };
    } else if (type === 'email') {
        const val = document.getElementById('adminNewEmailInput').value.trim();
        if (!val || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val)) { alert('Please enter a valid email.'); return; }
        if (val === adminEditTarget.email) { alert('This is already your current email.'); return; }
        destination = val; adminPendingChange = { type, value: val };
    } else if (type === 'password') {
        const cur  = document.getElementById('adminCurrentPwInput').value.trim();
        const nw   = document.getElementById('adminNewPwInput').value.trim();
        const conf = document.getElementById('adminConfirmPwInput').value.trim();
        if (!cur || !nw || !conf) { alert('Please fill in all password fields.'); return; }
        if (nw.length < 8) { alert('New password must be at least 8 characters.'); return; }
        if (nw !== conf) { alert('New passwords do not match.'); return; }
        if (cur === nw) { alert('New password must be different from current.'); return; }
        destination = adminEditTarget.email; adminPendingChange = { type, value: nw, currentPw: cur };
    }
    closeAdminSubModal(type);



    fetch('handle_otp.php', {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({ action:'send', type: type==='name'?'admin_name':(type==='password'?'password':type), destination })
    })
    .then(r=>r.json())
    .then(data => {
        if (data.success) {
            const titles = {name:'Confirm Name Change',username:'Confirm Username Change',email:'Verify New Email',password:'Confirm Password Change'};
            document.getElementById('adminOtpTitle').textContent = titles[type]||'Verify';
            document.getElementById('adminOtpDest').textContent  = type==='name'?'your registered email':destination;
            document.getElementById('adminOtpInput').value = '';
            document.getElementById('adminOtpModal').classList.add('active');
            adminStartOtpTimer(60, type);
        } else { alert('Error: '+(data.message||'Could not send OTP.')); openAdminSubModal(type); }
    })
    .catch(() => { alert('Failed to send OTP.'); openAdminSubModal(type); });
}
function applyAdminChangeDirectly(type) {
    // Read the input values directly since adminSendOTP was skipped
    let value = '';
    if (type === 'name') {
        value = document.getElementById('adminNewNameInput').value.trim();
        if (!value) { alert('Please enter a name.'); return; }
    } else if (type === 'username') {
        value = document.getElementById('adminNewUsernameInput').value.trim();
        if (!value) { alert('Please enter a username.'); return; }
        if (!/^[a-zA-Z0-9_]{4,20}$/.test(value)) { alert('Username must be 4-20 characters.'); return; }
        if (value === adminEditTarget.username) { alert('This is already the current username.'); return; }
    } else if (type === 'email') {
        value = document.getElementById('adminNewEmailInput').value.trim();
        if (!value || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) { alert('Please enter a valid email.'); return; }
        if (value === adminEditTarget.email) { alert('This is already the current email.'); return; }
    } else if (type === 'password') {
        const nw   = document.getElementById('adminNewPwInput').value.trim();
        const conf = document.getElementById('adminConfirmPwInput').value.trim();
        if (!nw || !conf) { alert('Please fill in the password fields.'); return; }
        if (nw.length < 8) { alert('Password must be at least 8 characters.'); return; }
        if (nw !== conf) { alert('Passwords do not match.'); return; }
        value = nw;
    }

    adminPendingChange = { type, value };
    closeAdminSubModal(type);

    const payload = {
        action: 'direct_update',
        target_username: adminEditTarget.username,
        type,
        value
    };
    if (type === 'password') {
        payload.new_password = value;
        delete payload.value;
    }

    fetch('handle_otp.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(payload)
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            if (type === 'name') {
                document.getElementById('aep_nameDisplay').textContent = value;
                const el = document.getElementById(`adminDisplayName_${adminEditTarget.username}`);
                if (el) {
                    const badge = el.querySelector('span');
                    el.textContent = value;
                    if (badge) el.appendChild(badge);
                }
                alert('Name updated! ✅');
            } else if (type === 'username') {
                alert('Username updated! Reloading… ✅');
                setTimeout(() => location.reload(), 1200);
            } else if (type === 'email') {
                document.getElementById('aep_emailDisplay').textContent = value;
                alert('Email updated! ✅');
            } else if (type === 'password') {
                alert('Password updated! ✅');
            }
            adminPendingChange = {};
        } else {
            alert('Error: ' + (data.message || 'Update failed.'));
        }
    })
    .catch(() => alert('Request failed.'));
}
function adminVerifyOTP() {
    const otp = document.getElementById('adminOtpInput').value.trim();
    if (otp.length !== 6) { alert('Please enter the 6-digit OTP.'); return; }
    const btn = document.getElementById('adminOtpVerifyBtn');
    btn.textContent = 'Verifying...'; btn.disabled = true;
    const type = adminPendingChange.type;
    const payload = { action:'verify', type:type==='name'?'admin_name':(type==='password'?'password':type), destination:adminPendingChange.value, otp };
    if (type==='password') { payload.destination=''; payload.current_password=adminPendingChange.currentPw; payload.new_password=adminPendingChange.value; }
    fetch('handle_otp.php', { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(payload) })
    .then(r=>r.json())
    .then(data => {
        if (data.success) {
            closeAdminOtpModal();
            const u = adminEditTarget.username;
            if (type==='name') {
                const n = adminPendingChange.value;
                document.getElementById('aep_nameDisplay').textContent = n;
                const el = document.getElementById(`adminDisplayName_${u}`);
                if (el) {
                    const badge = el.querySelector('span');
                    el.textContent = n;
                    if (badge) el.appendChild(badge);
                }
                adminEditTarget.fullName = n; alert('Full name updated! ✅');
            } else if (type==='username') {
                const n = data.new_username||adminPendingChange.value;
                document.getElementById('aep_usernameDisplay').textContent = n;
                alert('Username updated! Reloading… ✅'); setTimeout(()=>location.reload(),1200);
            } else if (type==='email') {
                const n = adminPendingChange.value;
                document.getElementById('aep_emailDisplay').textContent = n;
                adminEditTarget.email = n; alert('Email updated! ✅'); setTimeout(()=>location.reload(),1200);
            } else if (type==='password') { alert('Password changed! ✅'); }
            adminPendingChange = {};
        } else { alert('Error: '+(data.message||'Invalid OTP.')); }
    })
    .catch(()=>alert('Verification failed.'))
    .finally(()=>{ btn.textContent='Verify'; btn.disabled=false; });
}
function adminStartOtpTimer(duration, type) {
    clearInterval(adminOtpTimer);
    let t = duration;
    const el = document.getElementById('adminOtpTimer');
    el.style.color = '#888'; el.textContent = `Resend in ${t}s`;
    adminOtpTimer = setInterval(()=>{
        t--;
        if (t>0) { el.textContent=`Resend in ${t}s`; }
        else {
            clearInterval(adminOtpTimer);
            el.innerHTML=`<button onclick="adminResendOTP('${type}')" style="background: #2d5016 ;color:white;border:none;padding:6px 14px;border-radius:6px;cursor:pointer;font-size:12px;font-weight:600;">Resend Code</button>`;
        }
    },1000);
}
function adminResendOTP(type) { closeAdminOtpModal(); openAdminSubModal(type); }
</script>

</body>
</html>