# UserRoleAssignmentSeeder Test Documentation

This document describes the comprehensive test suite for the `UserRoleAssignmentSeeder` class, which handles user role assignments and demo data creation for the gym management system.

## Test Files Overview

### 1. `tests/Unit/UserRoleAssignmentSeederTest.php`
**Main unit test file covering core functionality:**

#### Main Run Method Tests
- `it_can_run_with_create_super_admin_choice()` - Tests the main run method with super admin creation choice
- `it_can_run_with_create_demo_users_for_specific_gym_choice()` - Tests demo user creation for a specific gym
- `it_can_run_with_invalid_choice()` - Tests handling of invalid menu choices

#### Role Management Tests
- `it_creates_global_roles_correctly()` - Verifies creation of global roles (super_admin, gym_owner)
- `it_creates_tenant_roles_for_specific_gym()` - Tests tenant role creation for a specific gym
- `it_creates_tenant_roles_for_all_gyms()` - Tests tenant role creation for all existing gyms

#### Super Admin Creation Tests
- `it_validates_super_admin_creation_inputs()` - Tests input validation (empty name)
- `it_validates_super_admin_email()` - Tests email format validation
- `it_validates_existing_email()` - Tests duplicate email prevention
- `it_validates_password_length()` - Tests minimum password length requirement
- `it_validates_password_confirmation()` - Tests password confirmation matching

#### Demo User Creation Tests
- `it_creates_demo_users_with_correct_roles()` - Verifies demo users are created with proper role assignments
- `it_creates_trainer_profiles()` - Tests trainer profile creation in the trainers table
- `it_creates_member_profiles()` - Tests member profile creation in the gym_members table
- `it_handles_no_gyms_scenario()` - Tests behavior when no gyms exist

#### Utility Method Tests
- `it_gets_available_gyms()` - Tests the gym retrieval functionality
- `it_displays_final_summary_with_users()` - Tests the summary display with actual data
- `it_handles_exception_during_super_admin_creation()` - Tests error handling during user creation

#### User Management Tests
- `it_creates_or_updates_user_with_global_role()` - Tests global role assignment
- `it_creates_or_updates_user_with_tenant_role()` - Tests tenant role assignment

### 2. `tests/Unit/UserRoleAssignmentSeederAdditionalTest.php`
**Extended unit tests covering advanced scenarios:**

#### Role Assignment to Existing Users
- `it_can_assign_roles_to_existing_users_with_global_context()` - Tests assigning global roles to existing users
- `it_can_assign_roles_to_existing_users_with_gym_context()` - Tests assigning tenant roles to existing users
- `it_handles_no_users_without_roles_scenario()` - Tests when all users already have roles
- `it_handles_roles_that_dont_exist_when_assigning()` - Tests error handling for missing roles

#### Role Updates for Existing Users
- `it_can_update_existing_user_roles_global_context()` - Tests updating global roles
- `it_can_remove_all_global_roles()` - Tests removing all global roles from a user
- `it_can_update_tenant_roles_for_specific_gym()` - Tests updating tenant roles for specific gym
- `it_can_remove_tenant_roles_for_specific_gym()` - Tests removing tenant roles for specific gym
- `it_handles_no_users_with_roles_scenario()` - Tests when no users have any roles

#### Demo User Creation for All Gyms
- `it_creates_demo_users_for_all_gyms()` - Tests creating demo users across multiple gyms
- `it_handles_no_gyms_for_all_gyms_scenario()` - Tests behavior when no gyms exist

#### User Management Edge Cases
- `it_updates_existing_user_instead_of_creating_new_one()` - Tests user update vs creation logic
- `it_skips_profile_creation_when_no_profile_data()` - Tests profile creation skipping

#### Summary Display Tests
- `it_displays_summary_with_no_global_users()` - Tests summary with no global users
- `it_displays_warning_for_users_without_roles()` - Tests warning display for unassigned users

### 3. `tests/Feature/UserRoleAssignmentSeederFeatureTest.php`
**Integration tests with actual Laravel command system:**

#### Artisan Command Integration
- `it_can_be_called_via_artisan_command()` - Tests seeder execution via Artisan
- `it_creates_all_required_global_roles()` - Tests global role creation through Artisan
- `it_creates_tenant_roles_for_existing_gyms()` - Tests tenant role creation through Artisan

#### Demo Data Creation
- `it_creates_demo_users_programmatically()` - Tests programmatic demo user creation
- `it_handles_multiple_gyms_correctly()` - Tests multi-gym scenario handling

#### Idempotency Tests
- `it_does_not_duplicate_roles_when_run_multiple_times()` - Tests role creation idempotency
- `it_does_not_duplicate_users_when_run_multiple_times()` - Tests user creation idempotency

#### Edge Case Handling
- `it_works_with_no_existing_gyms()` - Tests behavior with empty gym database

## Test Coverage Areas

### 1. **User Input Validation**
- Name validation (non-empty)
- Email format validation
- Email uniqueness validation
- Password length validation (minimum 8 characters)
- Password confirmation matching

### 2. **Role Management**
- Global role creation (super_admin, gym_owner)
- Tenant role creation (manager, receptionist, trainer, member)
- Role assignment to users
- Role updates and removals
- Duplicate role prevention

### 3. **User Management**
- User creation with proper attributes
- User role assignment (global vs tenant)
- Gym-user pivot table management
- Profile creation for specific roles (trainers, members)

### 4. **Demo Data Creation**
- Demo users for specific gyms
- Demo users for all gyms
- Proper role assignments
- Profile data population

### 5. **Error Handling**
- Invalid menu choices
- Missing gyms
- Database exceptions
- Missing roles
- Validation failures

### 6. **Integration Testing**
- Artisan command execution
- Database transactions
- Multi-gym scenarios
- Idempotency verification

## Running the Tests

### Run All UserRoleAssignmentSeeder Tests
```bash
php artisan test --filter=UserRoleAssignmentSeeder
```

### Run Specific Test Files
```bash
# Unit tests only
php artisan test tests/Unit/UserRoleAssignmentSeederTest.php
php artisan test tests/Unit/UserRoleAssignmentSeederAdditionalTest.php

# Feature tests only
php artisan test tests/Feature/UserRoleAssignmentSeederFeatureTest.php
```

### Run Specific Test Methods
```bash
php artisan test --filter=it_can_run_with_create_super_admin_choice
php artisan test --filter=it_creates_demo_users_with_correct_roles
```

## Test Database Requirements

The tests use Laravel's `RefreshDatabase` trait, which means:
- Database is migrated fresh for each test
- All data is cleaned up after each test
- Tests are isolated from each other

### Required Database Tables
- `users` - User accounts
- `roles` - Spatie permission roles
- `model_has_roles` - User-role assignments
- `gyms` - Gym entities
- `gym_user` - Gym-user pivot table
- `trainers` - Trainer profiles
- `gym_members` - Member profiles

## Mock Strategy

The tests use **Mockery** to mock the Laravel Command interface:
- Command input methods (`ask`, `secret`, `choice`, `confirm`)
- Command output methods (`info`, `error`, `warn`, `fail`, `table`)
- Command interaction methods (`newLine`)

This allows testing the seeder logic without actual user interaction.

## Key Testing Patterns

### 1. **Reflection for Private Methods**
```php
$reflection = new \ReflectionClass($this->seeder);
$method = $reflection->getMethod('privateMethodName');
$method->setAccessible(true);
$result = $method->invoke($this->seeder, $parameters);
```

### 2. **Command Mocking**
```php
$this->mockCommand->shouldReceive('ask')
    ->with('Enter name')
    ->andReturn('Test Name');
```

### 3. **Database Assertions**
```php
$this->assertDatabaseHas('users', ['email' => 'test@example.com']);
$this->assertDatabaseMissing('roles', ['name' => 'deleted_role']);
```

### 4. **Model Relationship Testing**
```php
$this->assertTrue($user->hasRole('super_admin'));
$this->assertEquals(1, $user->gyms()->count());
```

## Best Practices Demonstrated

1. **Comprehensive Input Validation Testing**
2. **Edge Case Coverage**
3. **Error Handling Verification**
4. **Database State Verification**
5. **Integration Testing**
6. **Idempotency Testing**
7. **Mock Isolation**
8. **Clear Test Naming**
9. **Proper Setup/Teardown**
10. **Documentation**

This test suite ensures the `UserRoleAssignmentSeeder` works correctly across all scenarios and maintains data integrity throughout the user and role management process.