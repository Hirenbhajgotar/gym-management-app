
# Project Overview

This is a Laravel 12 application for a gym management system. It utilizes a multi-tenancy architecture with Filament for the admin panel. The application includes features for managing gyms, members, payments, and staff. It uses Livewire and Volt for dynamic interfaces and Spatie's `laravel-permission` for role-based access control.

**Key Technologies:**

* **Backend:** Laravel 12, PHP 8.2
* **Frontend:** Vite, Tailwind CSS, Alpine.js
* **Database:** MySQL (inferred from `docker-compose.yml`)
* **Admin Panel:** Filament
* **Dynamic Interfaces:** Livewire, Volt
* **Real-time Events:** Laravel Reverb, Laravel Echo
* **Roles & Permissions:** Spatie `laravel-permission`

**Architecture:**

The application follows a standard Laravel project structure. It employs a multi-tenancy model where each gym is a tenant. The `User` model is configured to work with Filament's multi-tenancy features, and access to different panels and resources is controlled by user roles.

# Building and Running

**Prerequisites:**

* PHP 8.2
* Node.js & npm
* Composer
* Docker (optional, for local development)

**Development:**

1. **Install Dependencies:**

    ```bash
    composer install
    npm install
    ```

2. **Set up Environment:**
    * Copy `.env.example` to `.env`.
    * Generate an application key: `php artisan key:generate`
    * Configure your database and other environment variables in `.env`.

3. **Run Migrations:**

    ```bash
    php artisan migrate
    ```

4. **Start Development Servers:**

    ```bash
    npm run dev
    ```

    This will start the Vite development server and the Laravel development server concurrently.

**Testing:**

* Run the test suite:

    ```bash
    php artisan test
    ```

# Development Conventions

* **Coding Style:** The project uses Laravel Pint for code formatting. Run `vendor/bin/pint` to format your code.
* **Database Migrations:** Migrations are used to manage the database schema.
* **Frontend:** Frontend assets are managed with Vite and Tailwind CSS.
* **Admin Panel:** The admin panel is built with Filament. When creating new resources or pages, follow the existing conventions in the `app/Filament` directory.
* **Dynamic Components:** Livewire and Volt are used for creating dynamic components.
* **Routing:** Routes are defined in the `routes/` directory.
* **Authentication:** The application uses Laravel's built-in authentication.
* **Authorization:** Authorization is handled using Spatie's `laravel-permission` package and Filament's authorization features.
