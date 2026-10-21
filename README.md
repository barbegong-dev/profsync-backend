# eTrackFac - Backend API

This is the REST API backend for the eTrackFac system. It is built using [Laravel](https://laravel.com/) and provides authentication, role management, and document tracking logic.

## Features

*   **Authentication**: Secure API authentication using Laravel Sanctum (Stateful).
*   **Role-Based Access Control**: Managed permissions using `spatie/laravel-permission` (Admin, Dean, Program Chair, Faculty).
*   **User Management**: Registration with Admin approval workflow.
*   **File Handling**: Secure file uploads and storage for document submissions.
*   **Reporting**: Aggregated data for compliance reports.

## Prerequisites

Ensure you have the following installed:
*   [PHP](https://www.php.net/) (v8.1 or higher)
*   [Composer](https://getcomposer.org/)
*   Database Server (MySQL, SQLite, etc.)

## Installation

1.  **Clone the repository:**
    ```bash
    git clone https://github.com/PrimeX-Ryan/Etrackfac-API.git
    cd Etrackfac-API
    ```

2.  **Install PHP dependencies:**
    ```bash
    composer install
    ```

3.  **Environment Configuration:**
    Copy the example environment file and configure your database settings.
    ```bash
    cp .env.example .env
    ```
    Open `.env` and set your database credentials:
    ```env
    DB_CONNECTION=mysql
    DB_HOST=127.0.0.1
    DB_PORT=3306
    DB_DATABASE=your_database_name
    DB_USERNAME=your_username
    DB_PASSWORD=your_password
    
    FRONTEND_URL=http://localhost:3000
    SANCTUM_STATEFUL_DOMAINS=localhost:3000
    ```

4.  **Generate Application Key:**
    ```bash
    php artisan key:generate
    ```

5.  **Run Migrations and Seeders:**
    This will set up the database structure and populate it with default roles, departments, and requirements.
    ```bash
    php artisan migrate --seed
    ```

    **Default Admin Account:**
    *   Email: `admin@etrack.com`
    *   Password: `password`

## Running Locally

1.  **Start the local development server:**
    ```bash
    php artisan serve
    ```

2.  The API will be accessible at [http://localhost:8000](http://localhost:8000).

## API Endpoints

*   `POST /api/login`: Authenticate user.
*   `POST /api/register`: Register new account (Pending status).
*   `GET /api/admin/users`: List all users (Admin only).
*   `POST /api/admin/users/{id}/approve`: Approve user account.
*   `GET /api/submissions/checklist`: Get faculty requirements.
*   `POST /api/submissions/upload`: Upload requirement document.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
