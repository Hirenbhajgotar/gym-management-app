<?php

// @formatter:off
// phpcs:ignoreFile
/**
 * A helper file for your Eloquent Models
 * Copy the phpDocs from this file to the correct Model,
 * And remove them from this file, to prevent double declarations.
 *
 * @author Barry vd. Heuvel <barryvdh@gmail.com>
 */


namespace App\Models{
/**
 * @property int $id
 * @property string $addressable_type
 * @property int $addressable_id
 * @property string|null $street
 * @property int|null $city_id
 * @property int|null $state_id
 * @property int|null $country_id
 * @property string|null $postal_code
 * @property string|null $latitude
 * @property string|null $longitude
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Model|\Eloquent $addressable
 * @property-read \App\Models\City|null $city
 * @property-read \App\Models\Country|null $country
 * @property-read mixed $full_address
 * @property-read \App\Models\State|null $state
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Address newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Address newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Address onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Address query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Address whereAddressableId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Address whereAddressableType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Address whereCityId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Address whereCountryId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Address whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Address whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Address whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Address whereLatitude($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Address whereLongitude($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Address wherePostalCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Address whereStateId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Address whereStreet($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Address whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Address withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Address withoutTrashed()
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperAddress {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $attendanceable_type
 * @property int $attendanceable_id
 * @property int $gym_id
 * @property \Illuminate\Support\Carbon|null $check_in_time
 * @property \Illuminate\Support\Carbon|null $check_out_time
 * @property AttendanceStatus|null $status
 * @property string|null $note
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Model|\Eloquent $attendanceable
 * @property-read mixed $duration
 * @property-read mixed $duration_in_minutes
 * @property-read \App\Models\Gym $gym
 * @property-read mixed $is_active
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attendances absent()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attendances active()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attendances byDate($date)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attendances byGym($gymId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attendances completed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attendances newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attendances newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attendances query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attendances whereAttendanceableId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attendances whereAttendanceableType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attendances whereCheckInTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attendances whereCheckOutTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attendances whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attendances whereGymId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attendances whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attendances whereNote($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attendances whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attendances whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperAttendances {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int|null $gym_id
 * @property int|null $business_type_id
 * @property string|null $number_of_locations
 * @property string|null $years_in_business
 * @property string|null $website
 * @property string|null $gym_size
 * @property string|null $business_description
 * @property string|null $special_requirements
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\BusinessType|null $businessType
 * @property-read \App\Models\Gym|null $gym
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessDetail newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessDetail newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessDetail query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessDetail whereBusinessDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessDetail whereBusinessTypeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessDetail whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessDetail whereGymId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessDetail whereGymSize($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessDetail whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessDetail whereNumberOfLocations($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessDetail whereSpecialRequirements($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessDetail whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessDetail whereWebsite($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessDetail whereYearsInBusiness($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperBusinessDetail {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string|null $name
 * @property string|null $slug
 * @property bool|null $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessType active()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessType newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessType newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessType query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessType whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessType whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessType whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessType whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessType whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessType whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperBusinessType {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int|null $state_id
 * @property string|null $name
 * @property string|null $slug
 * @property int|null $status 1=Active, 0=Inactive
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\State|null $state
 * @method static Builder<static>|City active()
 * @method static \Database\Factories\CityFactory factory($count = null, $state = [])
 * @method static Builder<static>|City inactive()
 * @method static Builder<static>|City newModelQuery()
 * @method static Builder<static>|City newQuery()
 * @method static Builder<static>|City query()
 * @method static Builder<static>|City whereCreatedAt($value)
 * @method static Builder<static>|City whereId($value)
 * @method static Builder<static>|City whereName($value)
 * @method static Builder<static>|City whereSlug($value)
 * @method static Builder<static>|City whereStateId($value)
 * @method static Builder<static>|City whereStatus($value)
 * @method static Builder<static>|City whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperCity {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string|null $name
 * @property string|null $code
 * @property int|null $status 1=Active, 0=Inactive
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\State> $states
 * @property-read int|null $states_count
 * @method static Builder<static>|Country active()
 * @method static \Database\Factories\CountryFactory factory($count = null, $state = [])
 * @method static Builder<static>|Country inactive()
 * @method static Builder<static>|Country newModelQuery()
 * @method static Builder<static>|Country newQuery()
 * @method static Builder<static>|Country query()
 * @method static Builder<static>|Country whereCode($value)
 * @method static Builder<static>|Country whereCreatedAt($value)
 * @method static Builder<static>|Country whereId($value)
 * @method static Builder<static>|Country whereName($value)
 * @method static Builder<static>|Country whereStatus($value)
 * @method static Builder<static>|Country whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperCountry {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $user_id
 * @property string|null $date_of_birth
 * @property GenderEnum|null $gender
 * @property MaritalStatusEnum|null $marital_status
 * @property string|null $emergency_contact_name
 * @property string|null $emergency_contact_relationship
 * @property string|null $emergency_contact_phone
 * @property string|null $emergency_contact_email
 * @property string|null $employee_id
 * @property string|null $hire_date
 * @property string|null $employment_type
 * @property string|null $monthly_salary
 * @property array<array-key, mixed>|null $skills
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmployeeDetail newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmployeeDetail newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmployeeDetail query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmployeeDetail whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmployeeDetail whereDateOfBirth($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmployeeDetail whereEmergencyContactEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmployeeDetail whereEmergencyContactName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmployeeDetail whereEmergencyContactPhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmployeeDetail whereEmergencyContactRelationship($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmployeeDetail whereEmployeeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmployeeDetail whereEmploymentType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmployeeDetail whereGender($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmployeeDetail whereHireDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmployeeDetail whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmployeeDetail whereMaritalStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmployeeDetail whereMonthlySalary($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmployeeDetail whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmployeeDetail whereSkills($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmployeeDetail whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmployeeDetail whereUserId($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperEmployeeDetail {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int|null $user_id
 * @property string|null $error_type
 * @property string|null $error_message
 * @property string|null $error_hash
 * @property string|null $stack_trace
 * @property array<array-key, mixed>|null $context
 * @property ErrorCategory|null $category
 * @property ErrorStatus|null $status
 * @property string|null $user_description
 * @property ErrorPriority|null $priority
 * @property int $duplicate_count
 * @property \Illuminate\Support\Carbon|null $last_occurrence
 * @property \Illuminate\Support\Carbon $occurred_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User|null $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ErrorReport autoReported()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ErrorReport frequent()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ErrorReport highPriority()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ErrorReport newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ErrorReport newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ErrorReport pending()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ErrorReport query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ErrorReport recent()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ErrorReport resolved()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ErrorReport userReported()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ErrorReport whereCategory($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ErrorReport whereContext($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ErrorReport whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ErrorReport whereDuplicateCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ErrorReport whereErrorHash($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ErrorReport whereErrorMessage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ErrorReport whereErrorType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ErrorReport whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ErrorReport whereLastOccurrence($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ErrorReport whereOccurredAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ErrorReport wherePriority($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ErrorReport whereStackTrace($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ErrorReport whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ErrorReport whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ErrorReport whereUserDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ErrorReport whereUserId($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperErrorReport {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $user_id
 * @property int $rating Rating from 1-5
 * @property string $feedback
 * @property array<array-key, mixed>|null $files
 * @property string|null $email
 * @property \Illuminate\Support\Carbon $submitted_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Feedback newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Feedback newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Feedback query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Feedback whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Feedback whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Feedback whereFeedback($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Feedback whereFiles($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Feedback whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Feedback whereRating($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Feedback whereSubmittedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Feedback whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Feedback whereUserId($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperFeedback {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $lead_id
 * @property int $gym_id
 * @property int $user_id Staff who performed the follow-up
 * @property InteractionType $type
 * @property \Illuminate\Support\Carbon $scheduled_date
 * @property FollowUpStatus $status
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Gym $gym
 * @property-read \App\Models\Lead $lead
 * @property-read \App\Models\User $user
 * @method static \Database\Factories\FollowUpFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FollowUp newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FollowUp newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FollowUp query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FollowUp whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FollowUp whereGymId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FollowUp whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FollowUp whereLeadId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FollowUp whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FollowUp whereScheduledDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FollowUp whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FollowUp whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FollowUp whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FollowUp whereUserId($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperFollowUp {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $name
 * @property string|null $slug
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Address|null $address
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Attendances> $attendances
 * @property-read int|null $attendances_count
 * @property-read \App\Models\BusinessDetail|null $businessDetail
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\GymMember> $gymMembers
 * @property-read int|null $gym_members_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Lead> $leads
 * @property-read int|null $leads_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\GymMember> $members
 * @property-read int|null $members_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\MembershipPlan> $membershipPlans
 * @property-read int|null $membership_plans_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\GymSetting> $settings
 * @property-read int|null $settings_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\TenantSocialConfig> $socialConfigs
 * @property-read int|null $social_configs_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Trainer> $trainers
 * @property-read int|null $trainers_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\User> $users
 * @property-read int|null $users_count
 * @method static \Database\Factories\GymFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Gym newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Gym newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Gym query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Gym whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Gym whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Gym whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Gym whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Gym whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperGym {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $gym_id
 * @property string $name
 * @property string|null $profile_photo
 * @property string $email
 * @property string|null $phone
 * @property numeric|null $weight Weight in kg
 * @property numeric|null $height Height in cm
 * @property int|null $age
 * @property \Illuminate\Support\Carbon|null $dob
 * @property \Illuminate\Support\Carbon|null $membership_start
 * @property \Illuminate\Support\Carbon|null $membership_end
 * @property PaymentStatus|null $payment_status
 * @property int|null $gym_membership_plan_id
 * @property int|null $trainer_id
 * @property int|null $membership_plan_id
 * @property int|null $user_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Activitylog\Models\Activity> $activities
 * @property-read int|null $activities_count
 * @property-read \App\Models\Address|null $address
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Attendances> $attendances
 * @property-read int|null $attendances_count
 * @property-read \App\Models\Gym $gym
 * @property-read \App\Models\Lead|null $lead
 * @property-read \App\Models\MembershipPlan|null $membershipPlan
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\MemberPayment> $payments
 * @property-read int|null $payments_count
 * @property-read \App\Models\Trainer|null $trainer
 * @property-read \App\Models\User|null $user
 * @method static \Database\Factories\GymMemberFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GymMember hasTrainer()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GymMember membershipActive()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GymMember membershipExpired()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GymMember newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GymMember newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GymMember query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GymMember whereAge($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GymMember whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GymMember whereDob($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GymMember whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GymMember whereGymId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GymMember whereGymMembershipPlanId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GymMember whereHeight($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GymMember whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GymMember whereMembershipEnd($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GymMember whereMembershipPlanId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GymMember whereMembershipStart($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GymMember whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GymMember wherePaymentStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GymMember wherePhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GymMember whereProfilePhoto($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GymMember whereTrainerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GymMember whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GymMember whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GymMember whereWeight($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperGymMember {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $gym_id
 * @property string|null $key
 * @property string|null $value
 * @property string|null $type
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Gym $gym
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GymSetting newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GymSetting newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GymSetting query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GymSetting whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GymSetting whereGymId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GymSetting whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GymSetting whereKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GymSetting whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GymSetting whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GymSetting whereValue($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperGymSetting {}
}

namespace App\Models{
/**
 * @property-read \App\Models\Gym|null $gym
 * @property-read \App\Models\StaffMember|null $staffMember
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GymStaffMember active()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GymStaffMember inactive()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GymStaffMember newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GymStaffMember newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GymStaffMember query()
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperGymStaffMember {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $gym_id
 * @property int $balance_messages
 * @property int $total_messages_purchased
 * @property int $messages_used
 * @property bool $low_balance_alert_sent
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Gym $gym
 * @property-read mixed $remaining_messages
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GymWhatsAppBalance newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GymWhatsAppBalance newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GymWhatsAppBalance query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GymWhatsAppBalance whereBalanceMessages($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GymWhatsAppBalance whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GymWhatsAppBalance whereGymId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GymWhatsAppBalance whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GymWhatsAppBalance whereLowBalanceAlertSent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GymWhatsAppBalance whereMessagesUsed($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GymWhatsAppBalance whereTotalMessagesPurchased($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GymWhatsAppBalance whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperGymWhatsAppBalance {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $gym_id
 * @property int|null $membership_plan_id
 * @property string $name
 * @property string $email
 * @property string $phone
 * @property LeadSource $source
 * @property string|null $source_id
 * @property array<array-key, mixed>|null $raw_data
 * @property LeadStatus $status
 * @property int $order_column
 * @property array<array-key, mixed>|null $interest
 * @property array<array-key, mixed>|null $tags
 * @property string|null $notes
 * @property int $created_by
 * @property int|null $updated_by
 * @property \Illuminate\Support\Carbon|null $last_follow_up_date
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\LeadConversion|null $conversion
 * @property-read \App\Models\User $creator
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\FollowUp> $followUps
 * @property-read int|null $follow_ups_count
 * @property-read \App\Models\Gym $gym
 * @property-read \App\Models\MembershipPlan|null $membershipPlan
 * @property-read \App\Models\User|null $updatedByUser
 * @method static Builder<static>|Lead converted()
 * @method static \Database\Factories\LeadFactory factory($count = null, $state = [])
 * @method static Builder<static>|Lead lost()
 * @method static Builder<static>|Lead newModelQuery()
 * @method static Builder<static>|Lead newQuery()
 * @method static Builder<static>|Lead ordered(string $direction = 'asc')
 * @method static Builder<static>|Lead query()
 * @method static Builder<static>|Lead whereCreatedAt($value)
 * @method static Builder<static>|Lead whereCreatedBy($value)
 * @method static Builder<static>|Lead whereEmail($value)
 * @method static Builder<static>|Lead whereGymId($value)
 * @method static Builder<static>|Lead whereId($value)
 * @method static Builder<static>|Lead whereInterest($value)
 * @method static Builder<static>|Lead whereLastFollowUpDate($value)
 * @method static Builder<static>|Lead whereMembershipPlanId($value)
 * @method static Builder<static>|Lead whereName($value)
 * @method static Builder<static>|Lead whereNotes($value)
 * @method static Builder<static>|Lead whereOrderColumn($value)
 * @method static Builder<static>|Lead wherePhone($value)
 * @method static Builder<static>|Lead whereRawData($value)
 * @method static Builder<static>|Lead whereSource($value)
 * @method static Builder<static>|Lead whereSourceId($value)
 * @method static Builder<static>|Lead whereStatus($value)
 * @method static Builder<static>|Lead whereTags($value)
 * @method static Builder<static>|Lead whereUpdatedAt($value)
 * @method static Builder<static>|Lead whereUpdatedBy($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperLead {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $lead_id
 * @property int $gym_member_id
 * @property int $membership_plan_id
 * @property \Illuminate\Support\Carbon $conversion_date
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\GymMember $gymMember
 * @property-read \App\Models\Lead $lead
 * @property-read \App\Models\MembershipPlan $membershipPlan
 * @method static \Database\Factories\LeadConversionFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeadConversion newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeadConversion newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeadConversion query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeadConversion whereConversionDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeadConversion whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeadConversion whereGymMemberId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeadConversion whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeadConversion whereLeadId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeadConversion whereMembershipPlanId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeadConversion whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeadConversion whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperLeadConversion {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int|null $gym_id
 * @property int|null $member_id
 * @property int|null $membership_plan_id
 * @property numeric|null $amount
 * @property string|null $currency
 * @property string|null $payment_method
 * @property PaymentStatus|null $status
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon|null $payment_date
 * @property \Illuminate\Support\Carbon|null $membership_start
 * @property \Illuminate\Support\Carbon|null $membership_end
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Activitylog\Models\Activity> $activities
 * @property-read int|null $activities_count
 * @property-read \App\Models\Gym|null $gym
 * @property-read \App\Models\GymMember|null $member
 * @property-read \App\Models\MembershipPlan|null $membershipPlan
 * @method static \Database\Factories\MemberPaymentFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberPayment newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberPayment newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberPayment query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberPayment whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberPayment whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberPayment whereCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberPayment whereGymId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberPayment whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberPayment whereMemberId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberPayment whereMembershipEnd($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberPayment whereMembershipPlanId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberPayment whereMembershipStart($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberPayment whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberPayment wherePaymentDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberPayment wherePaymentMethod($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberPayment whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberPayment whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperMemberPayment {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $gym_id
 * @property string $name
 * @property string|null $description
 * @property numeric|null $price
 * @property int|null $duration
 * @property string|null $currency
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Gym $gym
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\GymMember> $members
 * @property-read int|null $members_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, MembershipPlan> $membershipPlans
 * @property-read int|null $membership_plans_count
 * @method static \Database\Factories\MembershipPlanFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MembershipPlan newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MembershipPlan newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MembershipPlan query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MembershipPlan whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MembershipPlan whereCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MembershipPlan whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MembershipPlan whereDuration($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MembershipPlan whereGymId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MembershipPlan whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MembershipPlan whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MembershipPlan whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MembershipPlan wherePrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MembershipPlan whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperMembershipPlan {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $gym_id
 * @property int|null $user_id
 * @property NotificationTemplateTypeEnum|null $type
 * @property string|null $channel
 * @property string|null $status
 * @property string|null $message
 * @property array<array-key, mixed>|null $response_data
 * @property string|null $external_id
 * @property \Illuminate\Support\Carbon|null $sent_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Gym $gym
 * @property-read \App\Models\User|null $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationLog newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationLog newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationLog query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationLog whereChannel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationLog whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationLog whereExternalId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationLog whereGymId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationLog whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationLog whereMessage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationLog whereResponseData($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationLog whereSentAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationLog whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationLog whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationLog whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationLog whereUserId($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperNotificationLog {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $gym_id
 * @property int|null $gym_owner_id
 * @property string|null $whatsapp_business_number
 * @property string|null $whatsapp_api_key
 * @property string|null $whatsapp_instance_id
 * @property string|null $whatsapp_provider
 * @property array<array-key, mixed>|null $notification_types
 * @property string|null $twilio_account_sid
 * @property string|null $twilio_auth_token
 * @property string|null $twilio_api_key
 * @property string|null $twilio_api_secret
 * @property NotificationAccountStatusEnum|null $account_status
 * @property NotificationVerificationStatusEnum|null $verification_status
 * @property \Illuminate\Support\Carbon|null $verified_at
 * @property array<array-key, mixed>|null $twilio_metadata
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Gym $gym
 * @property-read \App\Models\User|null $gymOwner
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationSetting newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationSetting newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationSetting query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationSetting whereAccountStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationSetting whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationSetting whereGymId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationSetting whereGymOwnerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationSetting whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationSetting whereNotificationTypes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationSetting whereTwilioAccountSid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationSetting whereTwilioApiKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationSetting whereTwilioApiSecret($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationSetting whereTwilioAuthToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationSetting whereTwilioMetadata($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationSetting whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationSetting whereVerificationStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationSetting whereVerifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationSetting whereWhatsappApiKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationSetting whereWhatsappBusinessNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationSetting whereWhatsappInstanceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationSetting whereWhatsappProvider($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperNotificationSetting {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $gym_id
 * @property int|null $gym_owner_id
 * @property NotificationTemplateTypeEnum|null $type
 * @property string|null $channel
 * @property string|null $subject
 * @property string|null $email_template
 * @property string|null $whatsapp_template
 * @property int|null $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Gym $gym
 * @property-read \App\Models\User|null $gymOwner
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationTemplate newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationTemplate newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationTemplate query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationTemplate whereChannel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationTemplate whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationTemplate whereEmailTemplate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationTemplate whereGymId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationTemplate whereGymOwnerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationTemplate whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationTemplate whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationTemplate whereSubject($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationTemplate whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationTemplate whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationTemplate whereWhatsappTemplate($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperNotificationTemplate {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int|null $user_id
 * @property int|null $gym_id
 * @property string $razorpay_order_id
 * @property string|null $razorpay_payment_id
 * @property numeric|null $amount
 * @property Currency|null $currency
 * @property PaymentStatus|null $status
 * @property string|null $payment_type
 * @property array<array-key, mixed>|null $metadata
 * @property \Illuminate\Support\Carbon|null $paid_at
 * @property \Illuminate\Support\Carbon|null $refunded_at
 * @property numeric|null $refund_amount
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Activitylog\Models\Activity> $activities
 * @property-read int|null $activities_count
 * @property-read mixed $formatted_amount
 * @property-read \App\Models\Gym|null $gym
 * @property-read \App\Models\User|null $user
 * @method static Builder<static>|Payment byType(string $type)
 * @method static Builder<static>|Payment completed()
 * @method static Builder<static>|Payment failed()
 * @method static Builder<static>|Payment newModelQuery()
 * @method static Builder<static>|Payment newQuery()
 * @method static Builder<static>|Payment pending()
 * @method static Builder<static>|Payment query()
 * @method static Builder<static>|Payment whereAmount($value)
 * @method static Builder<static>|Payment whereCreatedAt($value)
 * @method static Builder<static>|Payment whereCurrency($value)
 * @method static Builder<static>|Payment whereGymId($value)
 * @method static Builder<static>|Payment whereId($value)
 * @method static Builder<static>|Payment whereMetadata($value)
 * @method static Builder<static>|Payment wherePaidAt($value)
 * @method static Builder<static>|Payment wherePaymentType($value)
 * @method static Builder<static>|Payment whereRazorpayOrderId($value)
 * @method static Builder<static>|Payment whereRazorpayPaymentId($value)
 * @method static Builder<static>|Payment whereRefundAmount($value)
 * @method static Builder<static>|Payment whereRefundedAt($value)
 * @method static Builder<static>|Payment whereStatus($value)
 * @method static Builder<static>|Payment whereUpdatedAt($value)
 * @method static Builder<static>|Payment whereUserId($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperPayment {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $name
 * @property string|null $display_name
 * @property string|null $description
 * @property PermissionCategory|null $category
 * @property string $guard_name
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Permission> $permissions
 * @property-read int|null $permissions_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Permission\Models\Role> $roles
 * @property-read int|null $roles_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\User> $users
 * @property-read int|null $users_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Permission newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Permission newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Permission permission($permissions, $without = false)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Permission query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Permission role($roles, $guard = null, $without = false)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Permission whereCategory($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Permission whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Permission whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Permission whereDisplayName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Permission whereGuardName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Permission whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Permission whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Permission whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Permission withoutPermission($permissions)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Permission withoutRole($roles, $guard = null)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperPermission {}
}

namespace App\Models{
/**
 * @property int $id
 * @property DefaultRole|null $role
 * @property mixed|null $default_resources
 * @property array<array-key, mixed>|null $default_permissions
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Database\Factories\PermissionSettingFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PermissionSetting newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PermissionSetting newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PermissionSetting query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PermissionSetting whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PermissionSetting whereDefaultPermissions($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PermissionSetting whereDefaultResources($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PermissionSetting whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PermissionSetting whereRole($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PermissionSetting whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperPermissionSetting {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property numeric|null $price_monthly
 * @property numeric|null $price_yearly
 * @property Currency $currency
 * @property bool $is_active
 * @property int|null $trial_days
 * @property string|null $badge
 * @property string|null $description
 * @property string|null $brief_description
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\PlanFeature> $features
 * @property-read int|null $features_count
 * @method static Builder<static>|Plan active()
 * @method static Builder<static>|Plan newModelQuery()
 * @method static Builder<static>|Plan newQuery()
 * @method static Builder<static>|Plan query()
 * @method static Builder<static>|Plan whereBadge($value)
 * @method static Builder<static>|Plan whereBriefDescription($value)
 * @method static Builder<static>|Plan whereCreatedAt($value)
 * @method static Builder<static>|Plan whereCurrency($value)
 * @method static Builder<static>|Plan whereDescription($value)
 * @method static Builder<static>|Plan whereId($value)
 * @method static Builder<static>|Plan whereIsActive($value)
 * @method static Builder<static>|Plan whereName($value)
 * @method static Builder<static>|Plan wherePriceMonthly($value)
 * @method static Builder<static>|Plan wherePriceYearly($value)
 * @method static Builder<static>|Plan whereSlug($value)
 * @method static Builder<static>|Plan whereTrialDays($value)
 * @method static Builder<static>|Plan whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperPlan {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $plan_id
 * @property string $feature_name
 * @property string|null $value
 * @property int|null $order_column
 * @property string|null $brief_description
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlanFeature newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlanFeature newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlanFeature query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlanFeature whereBriefDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlanFeature whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlanFeature whereFeatureName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlanFeature whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlanFeature whereOrderColumn($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlanFeature wherePlanId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlanFeature whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlanFeature whereValue($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperPlanFeature {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int|null $gym_id
 * @property string $name
 * @property string $guard_name
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Permission> $permissions
 * @property-read int|null $permissions_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\User> $users
 * @property-read int|null $users_count
 * @method static \Database\Factories\RoleFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role permission($permissions, $without = false)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role whereGuardName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role whereGymId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role withoutPermission($permissions)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperRole {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $tenant_social_config_id
 * @property string|null $webhook_id
 * @property string $platform
 * @property array<array-key, mixed> $payload
 * @property array<array-key, mixed>|null $response
 * @property string $status
 * @property string|null $error_message
 * @property \Illuminate\Support\Carbon|null $processed_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\TenantSocialConfig|null $socialConfig
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SocialMediaWebhookLog newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SocialMediaWebhookLog newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SocialMediaWebhookLog query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SocialMediaWebhookLog whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SocialMediaWebhookLog whereErrorMessage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SocialMediaWebhookLog whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SocialMediaWebhookLog wherePayload($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SocialMediaWebhookLog wherePlatform($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SocialMediaWebhookLog whereProcessedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SocialMediaWebhookLog whereResponse($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SocialMediaWebhookLog whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SocialMediaWebhookLog whereTenantSocialConfigId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SocialMediaWebhookLog whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SocialMediaWebhookLog whereWebhookId($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperSocialMediaWebhookLog {}
}

namespace App\Models{
/**
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Gym> $gyms
 * @property-read int|null $gyms_count
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Permission> $permissions
 * @property-read int|null $permissions_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Permission\Models\Role> $roles
 * @property-read int|null $roles_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffMember newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffMember newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffMember permission($permissions, $without = false)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffMember query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffMember role($roles, $guard = null, $without = false)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffMember withoutPermission($permissions)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffMember withoutRole($roles, $guard = null)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperStaffMember {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int|null $country_id
 * @property string|null $name
 * @property string|null $slug
 * @property int|null $status 1=Active, 0=Inactive
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\City> $cities
 * @property-read int|null $cities_count
 * @property-read \App\Models\Country|null $country
 * @method static Builder<static>|State active()
 * @method static \Database\Factories\StateFactory factory($count = null, $state = [])
 * @method static Builder<static>|State inactive()
 * @method static Builder<static>|State newModelQuery()
 * @method static Builder<static>|State newQuery()
 * @method static Builder<static>|State query()
 * @method static Builder<static>|State whereCountryId($value)
 * @method static Builder<static>|State whereCreatedAt($value)
 * @method static Builder<static>|State whereId($value)
 * @method static Builder<static>|State whereName($value)
 * @method static Builder<static>|State whereSlug($value)
 * @method static Builder<static>|State whereStatus($value)
 * @method static Builder<static>|State whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperState {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $user_id
 * @property int $plan_id
 * @property SubscriptionStatus $status
 * @property string $billing_cycle
 * @property numeric $price
 * @property Currency $currency
 * @property \Illuminate\Support\Carbon|null $trial_ends_at
 * @property \Illuminate\Support\Carbon|null $current_period_start
 * @property \Illuminate\Support\Carbon|null $current_period_end
 * @property \Illuminate\Support\Carbon|null $ends_at
 * @property \Illuminate\Support\Carbon|null $cancelled_at
 * @property \Illuminate\Support\Carbon|null $grace_period_ends_at
 * @property array<array-key, mixed>|null $metadata
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read mixed $days_in_grace_period
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\SubscriptionFeature> $features
 * @property-read int|null $features_count
 * @property-read \App\Models\Plan $plan
 * @property-read \App\Models\User $user
 * @method static Builder<static>|Subscription active()
 * @method static Builder<static>|Subscription cancelled()
 * @method static Builder<static>|Subscription expired()
 * @method static Builder<static>|Subscription newModelQuery()
 * @method static Builder<static>|Subscription newQuery()
 * @method static Builder<static>|Subscription query()
 * @method static Builder<static>|Subscription suspended()
 * @method static Builder<static>|Subscription whereBillingCycle($value)
 * @method static Builder<static>|Subscription whereCancelledAt($value)
 * @method static Builder<static>|Subscription whereCreatedAt($value)
 * @method static Builder<static>|Subscription whereCurrency($value)
 * @method static Builder<static>|Subscription whereCurrentPeriodEnd($value)
 * @method static Builder<static>|Subscription whereCurrentPeriodStart($value)
 * @method static Builder<static>|Subscription whereEndsAt($value)
 * @method static Builder<static>|Subscription whereGracePeriodEndsAt($value)
 * @method static Builder<static>|Subscription whereId($value)
 * @method static Builder<static>|Subscription whereMetadata($value)
 * @method static Builder<static>|Subscription wherePlanId($value)
 * @method static Builder<static>|Subscription wherePrice($value)
 * @method static Builder<static>|Subscription whereStatus($value)
 * @method static Builder<static>|Subscription whereTrialEndsAt($value)
 * @method static Builder<static>|Subscription whereUpdatedAt($value)
 * @method static Builder<static>|Subscription whereUserId($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperSubscription {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $subscription_id
 * @property int $plan_feature_id
 * @property string $feature_name
 * @property string $value
 * @property string|null $brief_description
 * @property int $order_column
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\PlanFeature $planFeature
 * @property-read \App\Models\Subscription $subscription
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionFeature newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionFeature newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionFeature query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionFeature whereBriefDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionFeature whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionFeature whereFeatureName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionFeature whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionFeature whereOrderColumn($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionFeature wherePlanFeatureId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionFeature whereSubscriptionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionFeature whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionFeature whereValue($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperSubscriptionFeature {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $gym_id
 * @property string|null $platform
 * @property string|null $page_id
 * @property string|null $page_name
 * @property string|null $form_id
 * @property string|null $form_name
 * @property string|null $phone_number_id
 * @property string|null $phone_number
 * @property string|null $access_token
 * @property string|null $app_id
 * @property string|null $app_secret
 * @property string|null $webhook_verify_token
 * @property array<array-key, mixed>|null $additional_config
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $token_expires_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read mixed $masked_token
 * @property-read \App\Models\Gym $gym
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\SocialMediaWebhookLog> $webhookLogs
 * @property-read int|null $webhook_logs_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TenantSocialConfig active()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TenantSocialConfig byPlatform($platform)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TenantSocialConfig newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TenantSocialConfig newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TenantSocialConfig query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TenantSocialConfig whereAccessToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TenantSocialConfig whereAdditionalConfig($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TenantSocialConfig whereAppId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TenantSocialConfig whereAppSecret($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TenantSocialConfig whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TenantSocialConfig whereFormId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TenantSocialConfig whereFormName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TenantSocialConfig whereGymId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TenantSocialConfig whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TenantSocialConfig whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TenantSocialConfig wherePageId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TenantSocialConfig wherePageName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TenantSocialConfig wherePhoneNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TenantSocialConfig wherePhoneNumberId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TenantSocialConfig wherePlatform($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TenantSocialConfig whereTokenExpiresAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TenantSocialConfig whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TenantSocialConfig whereWebhookVerifyToken($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperTenantSocialConfig {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $gym_id
 * @property string|null $name
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $specialization
 * @property int|null $experience_years
 * @property string|null $certifications
 * @property bool|null $is_available
 * @property string|null $available_hours
 * @property numeric|null $basic_salary
 * @property numeric|null $pt_salary_per_member
 * @property string|null $currency
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Attendances> $attendances
 * @property-read int|null $attendances_count
 * @property-read float $total_earnings
 * @property-read \App\Models\Gym $gym
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\GymMember> $members
 * @property-read int|null $members_count
 * @method static Builder<static>|Trainer available()
 * @method static \Database\Factories\TrainerFactory factory($count = null, $state = [])
 * @method static Builder<static>|Trainer newModelQuery()
 * @method static Builder<static>|Trainer newQuery()
 * @method static Builder<static>|Trainer query()
 * @method static Builder<static>|Trainer whereAvailableHours($value)
 * @method static Builder<static>|Trainer whereBasicSalary($value)
 * @method static Builder<static>|Trainer whereCertifications($value)
 * @method static Builder<static>|Trainer whereCreatedAt($value)
 * @method static Builder<static>|Trainer whereCurrency($value)
 * @method static Builder<static>|Trainer whereEmail($value)
 * @method static Builder<static>|Trainer whereExperienceYears($value)
 * @method static Builder<static>|Trainer whereGymId($value)
 * @method static Builder<static>|Trainer whereId($value)
 * @method static Builder<static>|Trainer whereIsAvailable($value)
 * @method static Builder<static>|Trainer whereName($value)
 * @method static Builder<static>|Trainer wherePhone($value)
 * @method static Builder<static>|Trainer wherePtSalaryPerMember($value)
 * @method static Builder<static>|Trainer whereSpecialization($value)
 * @method static Builder<static>|Trainer whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperTrainer {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $phone
 * @property bool|null $is_active
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $remember_token
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Address|null $address
 * @property-read \App\Models\EmployeeDetail|null $employeeDetails
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Gym> $gyms
 * @property-read int|null $gyms_count
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Permission> $permissions
 * @property-read int|null $permissions_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Permission\Models\Role> $roles
 * @property-read int|null $roles_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Subscription> $subscriptions
 * @property-read int|null $subscriptions_count
 * @method static \Database\Factories\UserFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User permission($permissions, $without = false)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User role($roles, $guard = null, $without = false)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmailVerifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRememberToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User withoutPermission($permissions)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User withoutRole($roles, $guard = null)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User withoutTrashed()
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperUser {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $name
 * @property int $message_count
 * @property numeric $price
 * @property string $currency
 * @property string|null $description
 * @property bool $is_active
 * @property int $sort_order
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static Builder<static>|WhatsAppMessagePackage active()
 * @method static Builder<static>|WhatsAppMessagePackage newModelQuery()
 * @method static Builder<static>|WhatsAppMessagePackage newQuery()
 * @method static Builder<static>|WhatsAppMessagePackage ordered()
 * @method static Builder<static>|WhatsAppMessagePackage query()
 * @method static Builder<static>|WhatsAppMessagePackage whereCreatedAt($value)
 * @method static Builder<static>|WhatsAppMessagePackage whereCurrency($value)
 * @method static Builder<static>|WhatsAppMessagePackage whereDescription($value)
 * @method static Builder<static>|WhatsAppMessagePackage whereId($value)
 * @method static Builder<static>|WhatsAppMessagePackage whereIsActive($value)
 * @method static Builder<static>|WhatsAppMessagePackage whereMessageCount($value)
 * @method static Builder<static>|WhatsAppMessagePackage whereName($value)
 * @method static Builder<static>|WhatsAppMessagePackage wherePrice($value)
 * @method static Builder<static>|WhatsAppMessagePackage whereSortOrder($value)
 * @method static Builder<static>|WhatsAppMessagePackage whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperWhatsAppMessagePackage {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $gym_id
 * @property string $template_name
 * @property string $template_sid
 * @property string|null $language
 * @property array<array-key, mixed>|null $variables
 * @property string|null $status
 * @property \Illuminate\Support\Carbon|null $synced_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Gym $gym
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WhatsappTemplate newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WhatsappTemplate newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WhatsappTemplate query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WhatsappTemplate whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WhatsappTemplate whereGymId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WhatsappTemplate whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WhatsappTemplate whereLanguage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WhatsappTemplate whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WhatsappTemplate whereSyncedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WhatsappTemplate whereTemplateName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WhatsappTemplate whereTemplateSid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WhatsappTemplate whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WhatsappTemplate whereVariables($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperWhatsappTemplate {}
}

