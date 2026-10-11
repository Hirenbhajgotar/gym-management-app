# Gym Management System with SAAS + CRM

A comprehensive gym management system built with Laravel 12 and Filament v3, offering Software as a Service (SAAS) capabilities and integrated CRM functionality. The system is designed to help gym owners and administrators efficiently manage their facilities, members, staff, and business operations.

---

## 📚 Documentation & Reference Guides

- **[Feature Overview & Catalog](FEATURES.md)** — Comprehensive breakdown of all platform modules, business workflows, and technical features.
- **[Changelog & Releases](CHANGELOG.md)** — Complete release history, upgrade notes, and unreleased changes.
- **[Technical Documentation (`/docs`)](docs/)**:
  - [Biometric Hardware Integration](docs/biometric/) — ZKTeco, Hikvision, and eSSL hardware engines & consent architecture.
  - [Dynamic QR Attendance System](docs/qr-attendance/) — Anti-proxy rotating QR check-in & kiosk scanners.
  - [WhatsApp Automation Suite](docs/whatsapp/) — Meta Cloud API (WABA) & Twilio WhatsApp integration.

---

## Features

### 🏢 Multi-Tenant SAAS Platform

- Support for multiple gyms under a single platform
- Each gym has its own dedicated space and management capabilities
- Customizable settings per gym instance

### 👥 User Management

Different user roles with specific permissions:

- **Super Admin**: Full system access and control
- **Gym Owners**: Manage their specific gym(s)
- **Managers**: Oversee gym operations
- **Receptionists**: Handle front desk operations
- **Trainers**: Manage their clients and sessions
- **Gym Members**: Access their personal dashboard and information

### 💼 CRM (Customer Relationship Management)

- Lead generation and tracking from social media platforms:
  - Facebook integration
  - Instagram integration
- Lead management and conversion tracking
- Automated notifications and follow-ups
- Customer communication history

### 💬 WhatsApp Marketing & Automation Suite

- **Dual Meta & Twilio Integration**: Official Meta Cloud API (WABA embedded signup) and Twilio WhatsApp services
- **Targeted Broadcast Campaigns**: High-throughput message broadcasting with queue chunking and delivery rate-limiting
- **Automated Transactional Notifications**: Instant payment receipts, renewal alerts (T-7, T-3, T-1 days), OTP logins, and birthday wishes
- **Prepaid Credit Wallet**: In-app credit top-ups via Razorpay with automated usage deduction and low-balance alerts

### 💪 Gym Management

- **Membership Plans**
  - Create and manage custom membership packages (durations, pricing, admission fees)
  - Define plan-specific access rules and perks
  - Trial period management

- **Payment Management**
  - Online checkout via Razorpay (UPI, Cards, NetBanking, Wallets)
  - Partial payments, balance tracking, and installment plans
  - Automated GST/tax invoice generation and receipts

- **Trainer & Staff Management**
  - Profile management and specialization tracking
  - Trainer commission and per-member fee tracking
  - Granular role-based portals (SuperAdmin, Gym Owner, Manager, Trainer, Receptionist)

### 📊 Member Management

- Comprehensive member profiles with health metrics and body measurement tracking
- **Intelligent Membership Freeze Engine**: Date-based or indefinite freeze with automated plan extension & threshold controls
- Member status lifecycle tracking (*Active*, *Expiring Soon*, *Expired*, *Frozen*, *Cancelled*)
- Interactive CRM with automated lead ingestion (Facebook, Instagram, IndiaMART, JustDial, Website)

### ⏰ Smart Attendance & Access Control

- **Biometric Device Integration**: Provider-agnostic engine supporting ZKTeco, Hikvision, and eSSL hardware
- **Dynamic Rotating QR Attendance**: Anti-proxy check-in with kiosk tablet scanning and geofencing
- Automated door relay access control based on active membership status
- Real-time facility occupancy counter and automated daily check-out

### 📈 Reporting, Analytics & Expenses

- Real-time revenue, cash flow, and attendance analytics
- Operational expense tracking with category budget threshold alerts
- AI-driven member churn risk indicators and retention metrics
- Automated scheduled email reports with export capabilities (Excel, PDF)

## Deployment

### Production Setup

1. **Environment Configuration**
   - Copy `.env.example` to `.env`
   - Set `APP_ENV=production`
   - Configure database settings for production
   - Set up Redis for queue, session, and cache
   - Configure mail settings for email notifications

2. **Razorpay Configuration**
   - Set `RAZORPAY_KEY_ID` with your live Razorpay key ID
   - Set `RAZORPAY_KEY_SECRET` with your live Razorpay key secret
   - Set `RAZORPAY_WEBHOOK_SECRET` with your webhook secret
   - Configure webhook URL in Razorpay dashboard: `https://yourdomain.com/payments/webhook`

3. **Queue & WebSockets Configuration**
   - Set `QUEUE_CONNECTION=redis`
   - Run queue worker: `php artisan app:work-queues`

     > **Named queues matter.** `queue:work` with no `--queue` argument consumes the
     > `default` queue *only*. The broadcast jobs use `queue.broadcast_queue`
     > (`broadcast`) and tenant notifications use `queue.notifications_queue`
     > (`notifications`), so a worker started without `--queue` will silently
     > leave both feature sets unprocessed while reporting healthy.
     >
     > `app:work-queues` reads those two names from configuration, so renaming
     > either one via `BROADCAST_QUEUE` / `NOTIFICATIONS_QUEUE` takes effect
     > without editing the command. Passing `--queue=...` to `queue:work` by hand
     > works too, but a literal list goes stale the moment either variable changes.
   - Start Laravel Reverb WebSocket server: `php artisan reverb:start`

4. **Scheduled Jobs**
   The application includes scheduled jobs configured in `routes/console.php`:
   - Membership expiry & freeze auto-unfreeze (`gym:check-membership-expiry` — daily)
   - Subscription renewal & expiry alerts (`notification:send-subscription-expiry-alerts` — daily)
   - Attendance auto checkout (`attendance:auto-checkout` — twice daily)
   - WhatsApp scheduled broadcasts (`whatsapp:process-scheduled` — every 2 minutes)
   - System backups (`backup:run` & `backup:clean` — daily)

   Add this to your server's crontab:
   ```bash
   * * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
   ```

5. **SSL Certificate**
   - Ensure SSL certificate is installed for HTTPS
   - Update `APP_URL` to use `https://`

6. **File Permissions**
   - Set proper permissions for `storage/` and `bootstrap/cache/` directories
   - Ensure web server can write to these directories

7. **Database Migration & Permissions**
   - Run `php artisan migrate --force` in production
   - Run `php artisan db:seed` if needed
   - Sync all panel roles and permissions: `php artisan permissions:sync`

8. **Cache and Optimization**
   - Run `php artisan config:cache`
   - Run `php artisan route:cache`
   - Run `php artisan view:cache`
   - Run `composer install --optimize-autoloader --no-dev`

## Technical Stack

- **Framework**: Laravel 12
- **Admin Panel**: Filament v3
- **Frontend**: Livewire v3, Volt, and Flux UI
- **Database**: MySQL 8.0 / SQLite
- **CSS**: Tailwind CSS v4
- **Real-time WebSockets**: Laravel Reverb & Laravel Echo
- **Authentication & RBAC**: Laravel Sanctum & Spatie Laravel Permission
- **Payment Gateway**: Razorpay
- **Queue System**: Redis / Laravel Queue
- **Caching & Sessions**: Redis & Laravel Cache
- **Testing**: PHPUnit

## 🐳 Docker Setup (Recommended)

The project includes a complete Docker environment (App, Nginx web server, MySQL 8.0, Redis, and phpMyAdmin).

```bash
# 1. Start all container services
docker-compose up -d

# 2. Run migrations, permission sync, and initial seeders inside the app container
docker-compose exec app php artisan migrate
docker-compose exec app php artisan permissions:sync
docker-compose exec app php artisan db:seed

# 3. (Optional) Seed sample gym owner, branches, plans, trainers, and members
docker-compose exec app php artisan app:seed-owner-data

# 4. Run real-time development stack (Laravel server + Queue worker + Vite)
docker-compose exec app composer run dev
```

## Local Installation (Without Docker)

```bash
# Clone the repository
git clone https://github.com/yourusername/gym-app.git

# Navigate to the project directory
cd gym-app

# Install PHP dependencies
composer install

# Install NPM dependencies
npm install

# Create environment file
cp .env.example .env

# Generate application key
php artisan key:generate

# Run database migrations
php artisan migrate

# Sync all roles and permissions across system and tenant panels
php artisan permissions:sync

# Seed initial system configuration and default admin
php artisan db:seed

# (Optional) Seed sample gym owner, branches, plans, trainers, and members
php artisan app:seed-owner-data

# Build frontend assets
npm run build

# Start the development server
composer run dev
```

### Fresh Database Setup (Development)

To perform a clean database reset with permissions and sample records:

```bash
# Reset database and run seeders
php artisan migrate:fresh --seed

# Sync and refresh all panel permissions
php artisan permissions:sync

# Populate sample gym owner, trainers, and members data
php artisan app:seed-owner-data
```

## Development & Quality Tools

```bash
# Start development server with hot reload
composer run dev

# Run test suite
composer run test

# Format code using Laravel Pint
vendor/bin/pint --dirty
```

## Contributing

Please read [CONTRIBUTING.md](CONTRIBUTING.md) for details on our code of conduct, and the process for submitting pull requests.

## Security

If you discover any security-related issues, please email [security@example.com](mailto:security@example.com) instead of using the issue tracker.

## License

This project is licensed under the MIT License - see the [LICENSE.md](LICENSE.md) file for details.

## Support

For support, please email [support@example.com](mailto:support@example.com) or raise an issue in the GitHub repository.
