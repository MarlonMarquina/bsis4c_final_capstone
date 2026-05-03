<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] != "signatory") {
    header("Location: login.php");
    exit();
}

// Database connection
$conn = new mysqli("localhost", "root", "", "school_db");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle filters
$filter_course = $_GET['course'] ?? '';
$filter_status = $_GET['status'] ?? '';
$filter_date = $_GET['date'] ?? '';

$query = "SELECT * FROM clearance_requests WHERE 1=1";
if (!empty($filter_course)) $query .= " AND course='$filter_course'";
if (!empty($filter_status)) $query .= " AND status='$filter_status'";
if (!empty($filter_date)) $query .= " AND DATE(date_submitted)='$filter_date'";

$result = $conn->query($query);
?>

<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Reports | Signatory</title>
  <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
  <link rel="stylesheet" href="styles.css">
  <style>
    .report-container {
      margin: 30px auto;
      width: 90%;
      background: #fff;
      border-radius: 10px;
      padding: 20px;
      box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }

    h2 {
      text-align: center;
      color: darkgreen;
      margin-bottom: 20px;
    }

    .filter-form {
      display: flex;
      justify-content: space-between;
      flex-wrap: wrap;
      gap: 10px;
      margin-bottom: 20px;
    }

    select, input[type="date"], button {
      padding: 8px;
      border-radius: 5px;
      border: 1px solid #ccc;
      font-size: 14px;
    }

    button {
      background: darkgreen;
      color: white;
      border: none;
      cursor: pointer;
      transition: 0.3s;
    }

    button:hover {
      background: #0b5d0b;
    }

    table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 10px;
    }

    th, td {
      border: 1px solid #ddd;
      padding: 10px;
      text-align: center;
    }

    th {
      background: darkgreen;
      color: white;
    }

    tr:nth-child(even) {
      background: #f2f2f2;
    }

    .btn-export {
      margin-top: 15px;
      background: royalblue;
    }
  </style>
</head>
<body>
  <nav class="sidebar close">
    <header>
      <div class="image-text">
        <span class="image">
          <img src="bpc-logo.png" alt="logo">
        </span>
        <div class="text header-text">
          <span class="name"><?php echo $_SESSION['username']; ?></span>
          <span class="role">Signatory</span>
        </div>
      </div>
      <i class='bx bx-chevron-right toggle'></i>
    </header>

    <div class="menu-bar">
      <div class="menu">
        <ul class="menu-links">
          <li class="nav-link"><a href="signatory_dashboard.php"><i class='bx bx-home-alt icon'></i><span class="text nav-text">Dashboard</span></a></li>
          <li class="nav-link"><a href="signatory_history.php"><i class='bx bx-history icon'></i><span class="text nav-text">History</span></a></li>
          <li class="nav-link"><a href="signatory_report.php" class="active"><i class='bx bx-file icon'></i><span class="text nav-text">Reports</span></a></li>
        </ul>
      </div>
      <div class="bottom-content">
        <li class="nav-link">
          <a href="#" onclick="confirmLogout(event)">
            <i class='bx bx-log-out icon'></i>
            <span class="text nav-text">Logout</span>
          </a>
        </li>
      </div>
    </div>
  </nav>

  <section class="home">
    <div class="text">Reports</div>

    <div class="report-container">
      <h2>Clearance Report Summary</h2>

      <form class="filter-form" method="GET">
        <select name="course">
          <option value="">Filter by Course</option>
          <option value="BSIS" <?= ($filter_course == 'BSIS') ? 'selected' : '' ?>>BSIS</option>
          <option value="BSEd" <?= ($filter_course == 'BSEd') ? 'selected' : '' ?>>BSEd</option>
          <option value="BSBA" <?= ($filter_course == 'BSBA') ? 'selected' : '' ?>>BSBA</option>
        </select>

        <select name="status">
          <option value="">Filter by Status</option>
          <option value="Pending" <?= ($filter_status == 'Pending') ? 'selected' : '' ?>>Pending</option>
          <option value="Approved" <?= ($filter_status == 'Approved') ? 'selected' : '' ?>>Approved</option>
          <option value="Rejected" <?= ($filter_status == 'Rejected') ? 'selected' : '' ?>>Rejected</option>
        </select>

        <input type="date" name="date" value="<?= htmlspecialchars($filter_date) ?>">
        <button type="submit">Filter</button>
      </form>

      <table>
        <thead>
          <tr>
            <th>#</th>
            <th>Student Name</th>
            <th>Course</th>
            <th>Date Submitted</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php
          if ($result->num_rows > 0) {
            $i = 1;
            while ($row = $result->fetch_assoc()) {
              echo "<tr>
                      <td>{$i}</td>
                      <td>{$row['student_name']}</td>
                      <td>{$row['course']}</td>
                      <td>{$row['date_submitted']}</td>
                      <td>{$row['status']}</td>
                    </tr>";
              $i++;
            }
          } else {
            echo "<tr><td colspan='5'>No records found</td></tr>";
          }
          ?>
        </tbody>
      </table>

      <form method="POST" action="generate_report.php">
        <button class="btn-export">Download Report (PDF)</button>
      </form>
    </div>
  </section>

  <script src="script.js"></script>
  <script>
    function confirmLogout(event) {
      event.preventDefault();
      if (confirm("Are you sure you want to logout?")) {
        window.location.href = "logout.php";
      }
    }
  </script>
</body>
</html>
