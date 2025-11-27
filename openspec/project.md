# Project Context

## Purpose
A comprehensive gym management system built with Laravel 12 and Filament v3, offering Software as a Service (SAAS) capabilities and integrated CRM functionality. The system helps gym owners and administrators efficiently manage their facilities, members, staff, and business operations through multi-tenant architecture with role-based access control.

## Tech Stack
### Backend
- **Framework**: Laravel 12 (PHP 8.2+)
- **Admin Panel**: Filament v3
- **Real-time**: Laravel Reverb
- **Authentication**: Laravel Sanctum
- **Database**: MySQL/SQLite with multi-tenant support
- **Queue/Cache**: Laravel Queue, Redis/Predis
- **Testing**: PHPUnit/Pest

### Frontend
- **Interactive Components**: Livewire v3 with Volt single-file components
- **UI Framework**: Flux UI components
- **Styling**: Tailwind CSS v4
- **Build Tool**: Vite
- **Real-time**: Laravel Echo, Pusher.js

### Key Packages
- **Permissions**: Spatie Laravel Permission
- **Activity Logging**: Spatie Activity Log
- **Excel Import/Export**: Maatwebsite Excel, Filament Excel Import
- **Phone Validation**: Propaganistas Laravel Phone
- **Charts/Analytics**: Flowframe Laravel Trend, Laravel Pulse
- **Notifications**: Laravel Mail, Twilio SDK
- **Social Integration**: Facebook Graph SDK

## Project Conventions

### Code Style
- **PHP Version**: 8.2 minimum requirement
- **Formatting**: Laravel Pint preset with 4-space indentation
- **Naming**: camelCase variables/methods, PascalCase classes, kebab-case files
- **Strings**: Single quotes preferred
- **Properties**: Constructor property promotion
- **Return Types**: Explicit return types on all methods
- **Documentation**: PHPDoc blocks for all classes and public methods
- **Imports**: Grouped by type (Laravel, third-party, local)

### Architecture Patterns
- **MVC Pattern**: Traditional Laravel MVC with clear separation
- **Multi-Tenant**: Tenant isolation using tenant_id columns
- **Filament Resources**: Admin panels built with Filament resource classes
- **Livewire Components**: Interactive UI with Volt single-file components
- **Service Layer**: Business logic encapsulated in service classes
- **Repository Pattern**: Data access abstraction (implied through Eloquent)
- **Observer Pattern**: Model events handled by observers
- **Policy Pattern**: Authorization logic in policy classes

### Testing Strategy
- **Framework**: PHPUnit/Pest for unit and feature tests
- **Coverage**: Focus on critical business logic and user workflows
- **Database**: Use factories for test data creation
- **Performance**: Eager loading to prevent N+1 query issues
- **Commands**: Test artisan commands where they contain business logic
- **Location**: `tests/Feature/` for integration tests, `tests/Unit/` for unit tests

### Git Workflow
- **Branching**: Feature branches from main, named as `feature/description` or `fix/issue-description`
- **Commits**: Clear, descriptive messages following conventional commits when possible
- **Code Review**: Pull requests required for all changes
- **Linting**: Laravel Pint run before commits (via pre-commit hooks if configured)

## Domain Context
### Gym Management Domain
- **Membership Management**: Plans, pricing, trial periods, renewals
- **Member Lifecycle**: Registration, onboarding, retention, churn
- **Trainer Management**: Profiles, specializations, salary structures, client assignments
- **Attendance Tracking**: Check-in/check-out, history, analytics
- **Payment Processing**: Subscriptions, one-time payments, invoices, Razorpay integration

### CRM Features
- **Lead Generation**: Social media integration (Facebook, Instagram)
- **Lead Conversion**: Tracking prospects through sales funnel
- **Communication**: Email, SMS, WhatsApp notifications
- **Customer Journey**: From lead to active member tracking

### Multi-Tenant SAAS
- **Tenant Isolation**: Each gym operates in separate data space
- **Role Hierarchy**: Super Admin → Gym Owner → Manager → Receptionist → Trainer → Member
- **Permission Granularity**: Fine-grained access control per tenant

## Important Constraints
### Technical
- **Multi-Tenant Data Isolation**: Strict tenant data separation required
- **Real-time Performance**: Attendance tracking and notifications need low latency
- **Scalability**: Support multiple concurrent gyms with shared infrastructure
- **Mobile Responsiveness**: Admin panels must work on tablets and mobile devices

### Business
- **Payment Compliance**: Razorpay integration must handle subscription billing
- **Data Privacy**: Member personal data protection (GDPR considerations)
- **Availability**: 99.9% uptime for gym operations
- **Backup Recovery**: Regular data backups with quick recovery procedures

### Regulatory
- **Financial Records**: Accurate payment tracking and reporting
- **User Data Protection**: Secure handling of personal information
- **Business Continuity**: Disaster recovery planning

## External Dependencies
### Payment Processing
- **Razorpay**: Primary payment gateway for subscriptions and one-time payments
- **Webhooks**: Real-time payment status updates

### Communication
- **Twilio**: SMS notifications for members and staff
- **WhatsApp Business API**: Automated messaging and notifications
- **Laravel Mail**: Email notifications and marketing

### Social Media Integration
- **Facebook Graph API**: Lead generation from Facebook pages
- **Instagram Basic API**: Social media lead capture

### Infrastructure
- **Redis**: Caching, sessions, and queue management
- **Database**: MySQL primary, SQLite for testing/development
- **File Storage**: Laravel Storage with configurable drivers (local/S3)
