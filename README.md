# Gym Management System with SAAS + CRM

A comprehensive gym management system built with Laravel 12 and Filament v3, offering Software as a Service (SAAS) capabilities and integrated CRM functionality. The system is designed to help gym owners and administrators efficiently manage their facilities, members, staff, and business operations.

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

### 💪 Gym Management

- **Membership Plans**
  - Create and manage different membership packages
  - Define duration and pricing
  - Set plan-specific features and benefits
  - Trial period management

- **Payment Management**
  - Process membership payments
  - Track payment history
  - Handle recurring payments
  - Multiple payment method support
  - Generate invoices and receipts

- **Trainer Management**
  - Profile management
  - Specialization tracking
  - Experience and certification records
  - Salary management:
    - Basic salary
    - Per-member training fees
    - Total earnings calculation
  - Availability tracking

### 📊 Member Management

- Comprehensive member profiles
- Membership status tracking
- Payment history
- Attendance records
- Progress tracking
- Personal trainer assignments
- Health metrics monitoring
- Profile photo management

### ⏰ Attendance Management

- Member check-in/check-out system
- Attendance history
- Automated attendance reports
- Trainer attendance tracking
- Real-time attendance monitoring

### 🔐 Advanced Security & Permissions

- Role-based access control
- Granular permissions system
- Secure authentication
- Activity logging
- Data protection measures

### 📈 Reporting & Analytics

- Member statistics
- Revenue reports
- Attendance analytics
- Trainer performance metrics
- Lead conversion rates
- Financial reports

### 🔄 Additional Features

- Responsive dashboard
- Export/Import functionality for members and trainers
- Email notifications
- SMS notifications (optional)
- File management system
- Activity logging
- Data backup and recovery
- System settings management

## Deployment

### Production Setup

1. **Environment Configuration**
   - Copy `.env.example` to `.env`
   - Set `APP_ENV=production`
   - Configure database settings for production
   - Set up Redis for queue and cache if using
   - Configure mail settings for email notifications

2. **Razorpay Configuration**
   - Set `RAZORPAY_KEY_ID` with your live Razorpay key ID
   - Set `RAZORPAY_KEY_SECRET` with your live Razorpay key secret
   - Set `RAZORPAY_WEBHOOK_SECRET` with your webhook secret
   - Configure webhook URL in Razorpay dashboard: `https://yourdomain.com/payments/webhook`

3. **Queue Configuration**
   - Set `QUEUE_CONNECTION` to `database` or `redis`
   - Run queue worker: `php artisan queue:work`
   - Schedule the renewal job to run daily at 2 AM

4. **Scheduled Jobs**
   The application includes scheduled jobs that should run periodically:
   - Subscription renewal job (runs daily at 2 AM)
   - Error report cleanup (runs daily at 1 AM)
   - Subscription expiry alerts (runs daily at 9 AM)

   Add this to your server's crontab:
   ```
   * * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
   ```

5. **SSL Certificate**
   - Ensure SSL certificate is installed for HTTPS
   - Update `APP_URL` to use `https://`

6. **File Permissions**
   - Set proper permissions for storage and bootstrap/cache directories
   - Ensure web server can write to these directories

7. **Database Migration**
   - Run `php artisan migrate --force` in production
   - Run `php artisan db:seed` if needed

8. **Cache and Optimization**
   - Run `php artisan config:cache`
   - Run `php artisan route:cache`
   - Run `php artisan view:cache`
   - Run `composer install --optimize-autoloader --no-dev`

## Technical Stack

- **Framework**: Laravel 12
- **Admin Panel**: Filament v3
- **Frontend**: Livewire v3, Volt, and Flux UI
- **Database**: MySQL/SQLite
- **CSS**: Tailwind CSS v4
- **Real-time Features**: Laravel Reverb
- **Authentication**: Laravel Sanctum
- **File Storage**: Laravel Storage
- **Queue System**: Laravel Queue
- **Caching**: Laravel Cache
- **Testing**: PHPUnit

## Installation

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

# Run migrations
php artisan migrate

# Seed the database
php artisan db:seed

# Build assets
npm run build

# Start the development server
composer run dev
```

## Development

```bash
# Start the development server with hot reload
composer run dev

# Run tests
composer run test

# Format code using Laravel Pint
vendor/bin/pint
```

## Contributing

Please read [CONTRIBUTING.md](CONTRIBUTING.md) for details on our code of conduct, and the process for submitting pull requests.

## Security

If you discover any security-related issues, please email [security@example.com](mailto:security@example.com) instead of using the issue tracker.

## License

This project is licensed under the MIT License - see the [LICENSE.md](LICENSE.md) file for details.

## Support

For support, please email [support@example.com](mailto:support@example.com) or raise an issue in the GitHub repository.
