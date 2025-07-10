# 📁 Log Folder Viewer

**Log Folder Viewer** is a responsive, web-based utility designed to help developers and IT staff **view log files from any directory on a local server**, without needing direct remote access (e.g. RDP or file browsing). Built for efficiency, it provides a modern interface for navigating, selecting, and previewing `.log` files from various environments like **WAMP**, **XAMPP**, **NGINX**, and **IIS**.

---

## 🔧 Features

- 🔍 **Live Search** – Quickly filter folders by name in real time.
- 📂 **Smart Folder Detection** – Automatically detects folders containing `.log` files within your configured root directory.
- 📜 **File List & Preview** – Lists all `.log` files inside a selected folder and displays contents in a scrollable viewer.
- 💡 **Overlay Viewer** – Opens log previews in a modal overlay without navigating away from the page.
- 🎨 **Color-coded Cards** – Visual folder indicators using Bootstrap styles for a clean user experience.
- ⚙️ **Cross-stack Compatibility** – Works seamlessly with **WAMP**, **XAMPP**, **NGINX**, and **IIS**.

---

## ✅ Use Case

This tool is ideal for teams that frequently need to inspect server logs but want to avoid:
- Logging into the server directly via RDP or SSH
- Navigating file explorers manually
- Risking accidental modification of log files

With Log Folder Viewer, you can securely explore logs from a browser window.

---

## 🛠️ Technologies Used

- **Frontend**:   
  - Bootstrap 5  
  - jQuery 3.6  
- **Backend**:  
  - PHP 8+  


---

## ⚙️ Setup Instructions

1. **Install a Local Server**
   - Ensure you have a local web stack like **WAMP**, **XAMPP**, or a custom setup (NGINX/IIS with PHP).
   - Place the project folder into your server’s root directory (e.g. `C:\wamp64\www\LogViewer`).

2. **Directory Requirements**
   - Inside your root folder (`www`, `htdocs`, etc.), ensure some folders contain `.log` files. Only folders with `.log` files will appear.

3. **Launch the App**
   - Start your local server and visit:
     ```
     http://localhost/LogViewer/index.html
     ```

4. **Configuration**
   - The base directory being scanned is currently:
     ```php
     C:\wamp64\www\
     ```
   - You can change this path in `backend.php` inside the `getLogFolders()` function.

---

## 🔐 Authentication (Coming Soon)

Security is a top priority. A login system will be introduced in the next version to:
- Protect access to sensitive log files
- Provide user-level permissions
- Restrict directory visibility

Until then, please use this tool only in trusted or isolated environments.

---

## ⚠️ Security Notice

- This tool is **not intended for public web access** in its current version.
- Always keep your PHP environment updated.
- Avoid deploying on production-facing web servers without adding:
  - Authentication
  - Input validation
  - Directory traversal protection

---

## 🚀 Future Enhancements

- 🔑 Authentication & user roles  
- 📦 Download/export logs  
- 🔁 Real-time log tailing  
-  Syntax highlighting for structured logs  
- 📊 Analytics: error counts, timestamps, severity levels  

---

## 📁 Sample Folder Layout

C:\wamp64\www

├── app1

│ └── error.log

├── app2

│ └── logs

│ └── debug.log

└── LogViewer

└── index.html

