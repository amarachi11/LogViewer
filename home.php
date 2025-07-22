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
  <title>Log Folder Viewer</title>
  <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

  <style>
    body {
      font-family: 'Nunito', sans-serif;
      background-color: #f8f9fa;
    }

    body.no-scroll {
      overflow: hidden;
    }

    #search-container {
      max-width: 400px;
      margin: 30px auto;
    }

    .card-custom {
      transition: transform 0.3s ease;
      color: white;
      height: 100%;
      min-width: 180px;
    }

    .card-custom:hover {
      transform: scale(1.05);
      box-shadow: 0 8px 20px rgba(0, 0, 0, 0.25);
    }

    #card-container {
      margin: 0 auto !important;
    }

    .card-img-container {
      height: 120px;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .card-img-container img {
      max-height: 80px;
    }

    .card-title {
      text-align: center;
      font-size: 1rem;
      margin-top: 10px;
    }

    .bg-color-0 { background-color: #007bff; }
    .bg-color-1 { background-color: #28a745; }
    .bg-color-2 { background-color: #ffc107; color: black; }
    .bg-color-3 { background-color: #dc3545; }
    .bg-color-4 { background-color: #6610f2; }

    #overlay {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background-color: rgba(0, 0, 0, 0.5);
      display: flex;
      justify-content: center;
      align-items: center;
      z-index: 1000;
      overflow-y: auto;
      padding: 40px 0;
    }

    #overlay-content {
      background-color: white;
      padding: 20px;
      border-radius: 8px;
      width: 80%;
      max-width: 800px;
      box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
      max-height: 90vh;
      overflow-y: auto;
    }

    #log {
      margin-top: 20px;
    }

    .close-btn {
      cursor: pointer;
      float: right;
      font-size: 1.3rem;
      color: #000;
    }

    .class {
      font-weight: 600;
      color: #333;
      margin-bottom: 20px;
    }

    /* Centered AJAX Loader */
    #ajax-loader {
      position: fixed;
      top: 0;
      left: 0;
      width: 100vw;
      height: 100vh;
      background-color: rgba(255, 255, 255, 0.7);
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 2000;
      visibility: hidden;
      opacity: 0;
      transition: opacity 0.2s ease;
    }

    #ajax-loader.show {
      visibility: visible;
      opacity: 1;
    }

    .spinner-border {
      width: 3rem;
      height: 3rem;
    }
    .btn{
      background-color: #007bff;
      color: white;
      border: none;
      padding: 10px 20px;
      font-size: 1rem;
      cursor: pointer;
    }
  </style>
</head>
<body>

  <!-- Centered global loading spinner -->
  <!-- <div id="ajax-loader">
    <div class="spinner-border text-primary" role="status">
      <span class="visually-hidden">Loading...</span>
    </div>
  </div> -->

  <div class="container">
    <div class="d-flex justify-content-end align-items-center mt-4 mb-3">
      <a href="backend/logout.php" class="btn btn-danger">Log out</a>
    </div>
    <h2 class="text-center mt-4">Log Folders</h2>

    <div id="search-container">
      <input type="text" id="search" class="form-control" placeholder="Search folders ...">
    </div>

    <div class="d-flex gap-5 flex-wrap justify-content-center pb-5" id="card-container"></div>

    <div id="overlay">
      <div id="overlay-content">
        <span class="close-btn">&times;</span>
        <div class="row mt-4" id="log">
          <div class="col-md-4" style="max-height:400px; overflow-y:auto;">
            <h5>Log Files</h5>
            <ul id="log-file-list" class="list-group"></ul>
          </div>
          <div class="col-md-8">
            <h5>File Preview</h5>
            <pre id="log-preview" style="background:#f8f9fa; padding:10px; border:1px solid #ccc; height:400px; overflow:auto;"></pre>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script>
// AutoLogout functionality
    let autoLogoutTimer;
  const AUTO_LOGOUT_SECONDS = 7200; //match php timeout

  function resetAutoLogoutTimer() {
  clearTimeout(autoLogoutTimer);
  autoLogoutTimer = setTimeout(function() {
    window.location.href = 'backend/logout.php?timeout=1';
  }, AUTO_LOGOUT_SECONDS * 1000);
}

// Reset timer on user activity
['mousemove', 'keydown', 'scroll', 'click'].forEach(evt => {
  window.addEventListener(evt, resetAutoLogoutTimer);
});

// Start timer on page load
resetAutoLogoutTimer();

    const bgClasses = ['bg-color-0', 'bg-color-1', 'bg-color-2', 'bg-color-3', 'bg-color-4'];
    const imageSrc = 'https://cdn-icons-png.flaticon.com/512/2991/2991108.png';

    $(document).ready(function () {
      $('#overlay').hide();

      // Show/hide loader on any AJAX request
      $(document).ajaxStart(function () {
        $('#ajax-loader').addClass('show');
      });
      $(document).ajaxStop(function () {
        $('#ajax-loader').removeClass('show');
      });

      $.post('backend/backend.php', { action: 'getLogFolders' }, function (response) {
        const folders = JSON.parse(response);
        const container = $('#card-container');

        if (folders.error === 'timeout' || folders.error === 'not_logged_in') {
          window.location.href = 'index.html?timeout=1';
          return;
        }

        folders.forEach((folder, i) => {
          const bgClass = bgClasses[i % bgClasses.length];
          const card = `
            <div class="card card-custom ${bgClass} logcard" data-folder="${folder}">
              <div class="card-img-container">
                <img src="${imageSrc}" alt="Folder Icon" class="img-fluid">
              </div>
              <div class="card-body p-2 d-flex justify-content-center align-items-center">
                <div class="card-title">${folder}</div>
              </div>
            </div>
          `;
          container.append(card);
        });

        $('.card-custom').on('click', function () {
          const folder = $(this).data('folder');
          $('#log-file-list').empty();
          $('#log-preview').text('');
          $('#overlay').show();
          $('body').addClass('no-scroll');

          $.post('backend/backend.php', { action: 'getLogFiles', folder }, function (files) {
            const logFiles = JSON.parse(files);
            if (logFiles.length === 0) {
              $('#log-file-list').append('<li class="list-group-item">No .log files found.</li>');
              return;
            }

            logFiles.forEach(file => {
              $('#log-file-list').append(`<li class="list-group-item list-group-item-action log-file" data-folder="${folder}" data-file="${file}">${file}</li>`);
            });

            $('.log-file').on('click', function () {
              const folder = $(this).data('folder');
              const file = $(this).data('file');
              $('#log-preview').text('Loading...');
              $.post('backend/backend.php', { action: 'readLogFile', folder, file }, function (content) {
                $('#log-preview').text(content);
              });
            });
          });
        });
      });

      $('#search').on('input', function () {
        const keyword = $(this).val().toLowerCase();
        $('.logcard').each(function () {
          const title = $(this).find('.card-title').text().toLowerCase();
          $(this).toggle(title.includes(keyword));
        });
      });

      function closeOverlay() {
        $('#overlay').hide();
        $('body').removeClass('no-scroll');
      }

      $('.close-btn').on('click', closeOverlay);

      $('#overlay').on('click', function (e) {
        if ($(e.target).is('#overlay')) {
          closeOverlay();
        }
      });

      $(document).keyup(function (e) {
        if (e.key === "Escape") {
          closeOverlay();
        }
      });
    });
  </script>
</body>
</html>
