# College Quiz System

A PHP + MySQL web app where students take timed course quizzes, download study materials, and see their timetable and results. Administrators manage users, courses, the question bank and materials.

**Requirements:** PHP 7.4 or newer (PHP 8.x recommended) with PDO MySQL, and MySQL or MariaDB. XAMPP and InfinityFree both meet these.

## Demo logins

| Role    | Registration number | Password      |
|---------|---------------------|---------------|
| Admin   | `ADMIN001`          | `Admin@123`   |
| Student | `STU001`            | `Student@123` |
| Student | `STU002`            | `Student@123` |

Change these passwords from **My profile** after your first login, or remove the demo students from **Users**.

---

## Run it on XAMPP

1. Copy the `college-quiz-system` folder into `C:\xampp\htdocs\`.
2. Start **Apache** and **MySQL** from the XAMPP Control Panel.
3. Open <http://localhost/phpmyadmin>, click **New**, name the database `college_quiz`, and click **Create**.
4. With `college_quiz` selected, open the **Import** tab, choose `database.sql`, and click **Import** (labelled **Go** in older versions).
5. Visit <http://localhost/college-quiz-system/> and sign in.

The `local` block in `config.php` already matches a default XAMPP install (user `root`, no password). If you gave MySQL a root password, put it in `db_pass`.

## Put it on InfinityFree

1. **Create the database.** In the InfinityFree control panel, open **MySQL Databases** and create one, for example `college_quiz`. Note the four values shown: **MySQL Hostname** (like `sql123.infinityfree.com`), **DB Name** (like `if0_12345678_college_quiz`), **Username** (like `if0_12345678`), and your hosting account password.
2. **Import the tables.** Click **phpMyAdmin** next to the database, open the **Import** tab, choose `database.sql`, and click **Go**.
3. **Edit `config.php`.** Fill in the `live` block with the four values from step 1.
4. **Upload the files.** Using the online File Manager or FileZilla (FTP details are on the control panel), upload **the contents** of the `college-quiz-system` folder into `htdocs`. `index.php` should end up directly inside `htdocs`. If you upload into `htdocs/quiz/` instead, the site will be at `yourdomain/quiz/`.
5. **Remove `database.sql` from the server.** The included `.htaccess` already blocks it from being downloaded, but it isn't needed once the import is done.
6. Visit your InfinityFree domain and sign in. A brand-new InfinityFree domain can take a while to start working.

The app picks the right settings by itself: `localhost`, LAN addresses and `*.test` domains use `local`; every other address uses `live`. The same files work in both places.

### Uploads on InfinityFree
- InfinityFree limits each file to 10 MB, so the app's limit is set to match (`max_upload_mb` in `config.php`).
- If uploads fail with a "writable" error, use the File Manager to give the `uploads/materials` folder permission `755`.

---

## What's inside

```
college-quiz-system/
├─ index.php          Sign-in page
├─ dashboard.php      Dashboard (different for students and admins)
├─ quizzes.php        Student: list of course quizzes
├─ take_quiz.php      Student: start screen + timed quiz
├─ result.php         Score and answer review for one attempt
├─ results.php        Student: own results · Admin: all results + CSV export
├─ courses.php        Course list · Admin: add / edit / delete
├─ timetable.php      Weekly schedule built from course days and times
├─ materials.php      Study materials · Admin: upload / delete
├─ download.php       Serves material files to signed-in users only
├─ questions.php      Admin: question bank per course
├─ users.php          Admin: add / edit / delete accounts, reset passwords
├─ profile.php        Update contact details, change password
├─ logout.php
├─ config.php         Database settings for XAMPP and InfinityFree
├─ database.sql       Tables + demo data (import once)
├─ includes/          Shared code (blocked from direct web access)
├─ assets/            CSS, JS, logo
└─ uploads/materials/ Uploaded files (blocked from direct web access)
```

## How quizzes work
- Each course has its own quiz. Admins set the time limit and how many attempts each student gets (0 = unlimited) on the **Courses** page.
- Questions are shuffled for every attempt. Answers autosave as students go, so a refresh or dropped connection doesn't lose work.
- The timer keeps running on the server. When it hits zero the quiz submits itself. Attempts left unfinished are closed and marked automatically.
- Scores are worked out on the server, never in the browser. Students can review which answers were right after they submit.
- Admins can delete a result from **Results** to give a student that attempt back.

## Security notes
- Passwords are stored with PHP's `password_hash()` (bcrypt), never MD5.
- Every database query uses prepared statements.
- Every form carries a CSRF token, and the sign-in page locks out for 5 minutes after 5 wrong passwords.
- Every page checks the session and role. Student and admin pages can't be opened by URL without signing in.
- Uploaded files get random names, only listed file types are accepted, and they can only be downloaded through `download.php` by signed-in users.
