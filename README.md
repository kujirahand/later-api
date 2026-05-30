# Later API

`Later API` is a lightweight, high-performance PHP-based API and web dashboard designed for secure and efficient synchronization of tasks managed via [later-cli](https://github.com/kujirahand/later-cli).

## Features

- 👤 **Multi-User Support**
  - Allows multiple users to create independent accounts and sync their tasks securely.
- 🔑 **Secure Passwordless Authentication (OTP)**
  - Users log in using a 6-digit one-time passcode (OTP) sent to their registered email address. Since no passwords are saved on the server, this mechanism is extremely secure.
- 🗄️ **SQLite Sharding (Distributed Storage)**
  - Shared global information (user list, metadata, API keys, etc.) is stored in `data/users.db`.
  - Individual user task data is automatically sharded into separate SQLite databases for every 10 users (e.g., `data/users/user-0.db`), ensuring high performance even under large data loads.
- 🎫 **Flexible API Key Management**
  - Users can generate and manage up to 10 API keys each.
  - Validity periods can be set to 1, 3, 5, or 10 years.
  - For maximum security, keys are displayed only once upon generation, and only their hashed values are stored on the server.
- 📧 **Built-in Mail Debugger**
  - Built-in debugger for local testing without setting up a real mail server: logs simulated emails to `data/mail_debug.log` and renders the OTP directly on the screen in debug mode.
- 🔌 **Zero External Dependencies**
  - Works out of the box with zero external packages (no Composer required), running extremely fast on PHP 8.0+ and the standard SQLite3 extension.

---

## System Requirements

- **PHP 8.0 or higher** (Recommended)
  - PHP 8.0+ is recommended due to strict type declarations (`strict_types`) and modern type hinting used throughout the codebase.
- **SQLite3 Extension** (Ensure `pdo_sqlite` is enabled)
- **Web Server** (Apache, Nginx, or PHP built-in web server for local testing)
- **Email Sending Environment** (For production use)
  - PHP's built-in `mail()` function, or an external SMTP server.

---

## Detailed Installation Steps

### Step 1: Clone the Repository
Clone the source code into your server's web root directory.

```bash
git clone https://github.com/kujirahand/later-api.git
cd later-api
```

### Step 2: Create and Configure the Settings File
Copy the template file `config.tpl.php` to create your active configuration file `config.inc.php`.

```bash
cp config.tpl.php config.inc.php
```

Open `config.inc.php` in a text editor and update the configurations as needed.

#### Core Configuration Options:
```php
return [
    // Database storage paths
    'db' => [
        'users_db' => __DIR__ . '/data/users.db',   // Main users database
        'users_dir' => __DIR__ . '/data/users',     // Directory for individual sharded user databases
    ],

    // Mail configurations
    'mail' => [
        // 'log': Write emails to a local file (data/mail_debug.log) (For local dev/testing)
        // 'mail': Use PHP's built-in mail() function
        // 'smtp': Use a custom SMTP server
        'driver' => 'log', 

        // SMTP settings (Only used if driver is 'smtp')
        'smtp_host' => 'smtp.example.com',
        'smtp_port' => 587,
        'smtp_secure' => 'tls', // 'ssl', 'tls', or null
        'smtp_username' => 'your-username@example.com',
        'smtp_password' => 'your-smtp-password',

        // Sender information
        'from_address' => 'no-reply@example.com',
        'from_name' => 'Later API',
    ],

    // Session security settings
    'session' => [
        'cookie_secure' => false, // Set to true if hosting over HTTPS
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
    ],

    // Developer / Debug options
    'debug' => true, // Set to false in production environments
];
```

### Step 3: Configure Directory Permissions
Since SQLite is file-based, ensure the web server has appropriate write permissions to create and update database files inside the `data/` directory.

```bash
# Example: If your web server runs as _www or www-data
chmod 755 data
# Or grant write access recursively
chmod -R 777 data
```
> [!IMPORTANT]
> - SQLite database files (`users.db` and sharded `users/user-*.db`) will be automatically created inside the `data/` directory.
> - A `.htaccess` file is pre-configured inside `data/` to block direct HTTP access to the SQLite databases on Apache. If you use Nginx, ensure you explicitly deny direct HTTP requests to the `data/` directory in your Nginx configuration.

### Step 4: Automatic Database Initialization
No manual database schema execution is required.
Upon the very first request to the web service (`index.php`) or the API (`api.php`), the necessary database tables are **automatically initialized** using `users.sql` and `user_db.sql`.

---

## How to Run & Verify

### 1. Local Development (PHP Built-in Server)
To test locally, use PHP's built-in web server, which is the easiest way to get started.

```bash
php -S localhost:8000
```
Open [http://localhost:8000](http://localhost:8000) in your web browser to access the Later API web dashboard.

- **Registration**: Enter an email and nickname to sign up. If `driver => 'log'` is enabled, you can find the 6-digit OTP passcode at the bottom of the registration screen or inside the `data/mail_debug.log` file.
- **Login**: Enter your registered email address and log in using the OTP sent to your inbox or log.
- **Issue API Key**: In the dashboard, select a validity period and click "Generate API Key". **Make sure to copy the key immediately, as it will never be displayed again.**

### 2. Production Environment (Apache / Nginx)
Point your web server virtual host to the repository root directory.
- `index.php` serves as the entrypoint for the user dashboard (web interface).
- `api.php` serves as the entrypoint for the synchronization API.

---

## API Specifications

All API requests must use the **POST method** and contain your API Key as a **Bearer Token** in the HTTP request headers.

### Authorization Header
```http
Authorization: Bearer <YOUR_API_KEY>
Content-Type: application/json
```

### 1. Hello (Authentication Test)
Verify if the API key is valid.

- **Endpoint**: `POST /api.php?method=hello`
- **Request Body (JSON)**:
  ```json
  {
      "message": "Hello, Later API!"
  }
  ```
- **Response (JSON)**:
  ```json
  {
      "message": "Hello, Later API!"
  }
  ```

### 2. Post (Record Events)
Synchronize task operation events (additions, deletions, status changes) generated in `later-cli`.

- **Endpoint**: `POST /api.php?method=post`
- **Request Body (JSON)**:
  ```json
  {
      "events": [
          {
              "event": "add",
              "guid": "123e4567-e89b-12d3-a456-426614174000",
              "timestamp": "2026-05-29 14:00:00",
              "task": "Task content details",
              "status": "done",
              "date": "2026-05-30 08:00:00"
          },
          {
              "event": "delete",
              "guid": "987f6543-e21b-32d1-b654-216614174000",
              "timestamp": "2026-05-29 14:05:00",
              "task": "Unwanted task",
              "status": "pending",
              "date": "2026-05-30 08:05:00"
          }
      ]
  }
  ```
- **Response (JSON)**:
  ```json
  {
      "success": true,
      "message": "Events processed successfully",
      "inserted": 2,
      "skipped": 0
  }
  ```

### 3. Get (Retrieve Events)
Fetch synchronized task events within a specified date/time range.

- **Endpoint**: `POST /api.php?method=get`
- **Request Body (JSON)**:
  ```json
  {
      "date_from": "2026-05-29 00:00:00",
      "date_to": "2026-05-30 23:59:59"
  }
  ```
  *(※ If `date_to` is omitted, it defaults to the current datetime. If `date_from` is omitted, it defaults to 7 days prior to `date_to`)*
- **Response (JSON)**:
  ```json
  [
      {
          "event": "add",
          "guid": "123e4567-e89b-12d3-a456-426614174000",
          "timestamp": "2026-05-29 14:00:00",
          "task": "Task content details",
          "status": "done",
          "date": "2026-05-30 08:00:00"
      }
  ]
  ```

---

## Running Integration Tests

An automated end-to-end integration test suite is provided.
Running the test runner command will isolate the current environment configuration, spawn a local background PHP server, run all integration checks, and restore original configurations automatically:

```bash
php tests/api_test.php
```

---

## License

[MIT License](LICENSE)
