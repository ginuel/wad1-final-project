# Campus Library & Books Resources Reservation System

## Project Overview
The Campus Library & Books Resources Reservation System is a lightweight, responsive web application designed to digitalize library catalog management and book reservations for students, faculty, and librarians. Built with a modular, zero-dependency architecture, it replaces manual paper-based logs with a seamless web interface for tracking book availability, processing reservations, managing loan periods, and maintaining system users.

---

## Key Features & User Roles

* **Visitors:** Browse the public book catalog to check titles, authors, categories, and real-time copy availability without needing an account.
* **Students & Teachers:** 
  * Register accounts and log in securely via password hashing.
  * View a personal dashboard displaying reservation statuses (*Pending*, *Approved*, *Rejected*, *Returned*, or *Overdue*).
  * Reserve available books directly from the catalog.
  * Renew active loans up to two times (7 days per renewal) as long as they are not past their due date.
* **Librarians (Admins):**
  * Review, approve, or reject pending student/teacher book reservation requests.
  * Manage the physical inventory by adding, editing, or deleting books (including uploading cover images).
  * Create custom book categories and track inventory counts per category.
  * Monitor overdue loans and manage system users.

---

## Group Members
* **Ginuel Marcelo Maquellao** (Lead Developer & System Architect)
* Development & Project Team Members

---

## Technical Stack
* **Backend:** PHP (PHP Data Objects / PDO)
* **Database:** SQLite (`database.sqlite`) with automatic schema instantiation and data seeding
* **Frontend:** HTML5, CSS3, Vanilla JavaScript, and Bootstrap 5-inspired responsive styling
* **Security Features:** BCRYPT password hashing (`password_hash` / `password_verify`), strict PDO prepared statements against SQL injection, and file-upload validation checks.

---

## Basic Instructions for Running the Project

### Prerequisites
* A local PHP development environment equipped with **PHP 8.0 or higher** (such as **XAMPP**, **Laragon**, or a native PHP CLI server).
* The **PDO SQLite** PHP extension enabled (enabled by default in most modern PHP bundles).

### Step-by-Step Setup Guide

1. **Clone or Place Files:**
   * Place all project files into your local web server's root directory (e.g., `htdocs/library/` in XAMPP).

2. **Verify Folder Permissions:**
   * Ensure that the `uploads/books/` directory has write permissions enabled so that book cover image uploads can be successfully stored.

3. **Run the Application:**
   * Start your local server (Apache via XAMPP Control Panel, etc.).
   * Open your web browser and navigate to:
     ```text
     http://localhost/library/index.php
     ```
   * *Note:* The system features an automatic setup script (`db.php`) that will instantly generate the SQLite database (`database.sqlite`), create all necessary tables, and pre-populate sample data and default accounts on its first launch.

---

## Default Login Credentials
The system automatically provisions administrative and sample test accounts on startup:

* **Librarian Account:**
  * **Username:** `librarian`
  * **Password:** `admin123`

* **Sample Student Account:**
  * **Username:** `garrett`
  * **Password:** `student123`

