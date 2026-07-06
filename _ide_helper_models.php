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
 * @property Carbon|null $last_occurrence
 * @property Carbon $occurred_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $user
 * @method static Builder<static>|ErrorReport autoReported()
 * @method static Builder<static>|ErrorReport frequent()
 * @method static Builder<static>|ErrorReport highPriority()
 * @method static Builder<static>|ErrorReport investigating()
 * @method static Builder<static>|ErrorReport newModelQuery()
 * @method static Builder<static>|ErrorReport newQuery()
 * @method static Builder<static>|ErrorReport pending()
 * @method static Builder<static>|ErrorReport query()
 * @method static Builder<static>|ErrorReport recent()
 * @method static Builder<static>|ErrorReport resolved()
 * @method static Builder<static>|ErrorReport userReported()
 * @method static Builder<static>|ErrorReport whereCategory($value)
 * @method static Builder<static>|ErrorReport whereContext($value)
 * @method static Builder<static>|ErrorReport whereCreatedAt($value)
 * @method static Builder<static>|ErrorReport whereDuplicateCount($value)
 * @method static Builder<static>|ErrorReport whereErrorHash($value)
 * @method static Builder<static>|ErrorReport whereErrorMessage($value)
 * @method static Builder<static>|ErrorReport whereErrorType($value)
 * @method static Builder<static>|ErrorReport whereId($value)
 * @method static Builder<static>|ErrorReport whereLastOccurrence($value)
 * @method static Builder<static>|ErrorReport whereOccurredAt($value)
 * @method static Builder<static>|ErrorReport wherePriority($value)
 * @method static Builder<static>|ErrorReport whereStackTrace($value)
 * @method static Builder<static>|ErrorReport whereStatus($value)
 * @method static Builder<static>|ErrorReport whereUpdatedAt($value)
 * @method static Builder<static>|ErrorReport whereUserDescription($value)
 * @method static Builder<static>|ErrorReport whereUserId($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperErrorReport {}
}

