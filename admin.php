<?php
session_start();
$timeout = 7200;
if (!isset($_SESSION['user'])) {
    header("Location: index.html");
    exit;
}
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout) {
    session_unset();
    session_destroy();
    header("Location: index.html?timeout=1");
    exit;
}
$_SESSION['last_activity'] = time();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Tolaram Log Admin</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
  <style>
    body {
      font-family: 'Nunito', sans-serif;
      background: #f4f4f4;
    }

    .overlay {
      position: fixed;
      top: 0; left: 0;
      width: 100%; height: 100%;
      background: rgba(0, 0, 0, 0.5);
      display: none;
      justify-content: center;
      align-items: center;
      z-index: 1000;
      padding: 40px 0;
    }

    .overlay-content {
      background: white;
      padding: 20px;
      border-radius: 8px;
      width: 100%;
      max-width: 600px;
      box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
    }

    .notification-icon {
      position: relative;
      cursor: pointer;
    }

    .notification-icon img {
      height: 30px;
    }

    .notification-count {
      position: absolute;
      top: -8px;
      right: -10px;
      background: red;
      color: white;
      border-radius: 50%;
      padding: 2px 6px;
      font-size: 12px;
    }

    .table-container {
      background-color: #fff;
      border-radius: 8px;
      box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
      padding: 20px;
      max-width: 1000px;
      margin: 0 auto;
    }
  </style>
</head>
<body>

  <nav class="navbar navbar-light bg-white mb-4 shadow-sm px-4">
    <div class="container-fluid d-flex justify-content-between align-items-center">
      <img src="https://www.tolaram.com/wp-content/uploads/2021/10/Tolaram-logo-red.png" alt="Logo" style="height: 40px;">
      <h2 class="mb-0 text-center flex-grow-1 fw-bold">Logviewer Admin</h2>
      <div class="notification-icon" onclick="openPendingOverlay()">
        <img src="img/email.png" alt="Mail Icon">
        <span id="pending-count" class="notification-count">0</span>
      </div>
    </div>
  </nav>

 
  <div class="table-container mb-5">
    <table id="userTable" class="table table-striped">
      <thead>
        <tr>
          <th>Email</th>
          <th>Role</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody></tbody>
    </table>
  </div>


  <div class="overlay" id="editOverlay">
    <div class="overlay-content">
      <h5>Edit User</h5>
      <form id="editForm">
        <input type="hidden" id="editEmail">
        <div class="mb-3">
          <label>Role</label>
          <select id="editRole" class="form-select">
            <option value="user">User</option>
            <option value="admin">Admin</option>
          </select>
        </div>
        <div class="mb-3">
          <label>Status</label>
          <select id="editStatus" class="form-select">
            <option value="approved">Approved</option>
            <option value="disabled">Disabled</option>
          </select>
        </div>
        <div class="d-flex justify-content-end">
          <button type="submit" class="btn btn-primary me-2">Update</button>
          <button type="button" class="btn btn-secondary" onclick="closeEditOverlay()">Cancel</button>
        </div>
      </form>
    </div>
  </div>

  <div class="overlay" id="pendingOverlay">
    <div class="overlay-content">
      <h5>Pending Approvals</h5>
      <div id="pendingList"></div>
      <div class="text-end">
        <button class="btn btn-secondary mt-3" onclick="closePendingOverlay()">Close</button>
      </div>
    </div>
  </div>

  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
  <script>
    let userTable;

    function handleSessionError(response) {
      if (response && typeof response === 'object' && (response.error === 'timeout' || response.error === 'not_logged_in')) {
        window.location.href = 'index.html?timeout=1';
        return true;
      }
      return false;
    }


    $(document).ready(function () {

      userTable = $('#userTable').DataTable();
      loadUsers();
      loadPendingUsers();

      $('#editForm').on('submit', function (e) {
        e.preventDefault();
        const email = $('#editEmail').val();
        const role = $('#editRole').val();
        const status = $('#editStatus').val();

        $.post('backend/backend.php', {
          action: 'updateUser',
          email, role, status
        }, function (data) {
          if (handleSessionError(data)) return;

          alert('User updated');
          closeEditOverlay();
          loadUsers();
          loadPendingUsers();
        });
      });
    });

    function loadUsers() {
      $.post('backend/backend.php', { action: 'getUsers' }, function (data) {
        if (handleSessionError(data)) return;

        userTable.clear();
        data.forEach(user => {
          userTable.row.add([
            user.email,
            user.role,
            user.status,
            `<button class="btn btn-sm btn-warning" onclick="editUser('${user.email}')">Edit</button>`
          ]);
        });
        userTable.draw();
      }, 'json');
    }

    function loadPendingUsers() {
      $.post('backend/backend.php', { action: 'getPendingUsers' }, function (data) {
        if (handleSessionError(data)) return;

        $('#pending-count').text(data.length);
        let html = '';
        data.forEach(user => {
          html += `
            <div class="d-flex justify-content-between align-items-center mb-2">
              <span>${user.email}</span>
              <div>
                <button class="btn btn-success btn-sm me-2" onclick="approveUser('${user.email}')">Approve</button>
                <button class="btn btn-danger btn-sm" onclick="rejectUser('${user.email}')">Reject</button>
              </div>
            </div>
          `;
        });
        $('#pendingList').html(html);
      }, 'json');
    }

    function approveUser(email) {
      $.post('backend/backend.php', { action: 'updateUserStatus', email, status: 'approved' }, function () {
        if (handleSessionError(data)) return;

        loadUsers();
        loadPendingUsers();
      });
    }

    function rejectUser(email) {
      $.post('backend/backend.php', { action: 'updateUserStatus', email, status: 'rejected' }, function () {
        if (handleSessionError(data)) return;

        loadUsers();
        loadPendingUsers();
      });
    }

    function editUser(email) {
      $.post('backend/backend.php', { action: 'getUser', email }, function (res) {
        if (handleSessionError(res)) return;

        $('#editEmail').val(res.email);
        $('#editRole').val(res.role);
        $('#editStatus').val(res.status);
        $('#editOverlay').css('display', 'flex').hide().fadeIn();
      }, 'json');
    }

    function closeEditOverlay() {
      $('#editOverlay').fadeOut();
    }

    function openPendingOverlay() {
      $('#pendingOverlay').css('display', 'flex').hide().fadeIn();
    }

    function closePendingOverlay() {
      $('#pendingOverlay').fadeOut();
    }
  </script>
</body>
</html>
