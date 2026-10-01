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
 * App\Models\PendingRegistration
 *
 * @property int                           $id
 * @property string                        $name
 * @property string                        $email
 * @property string                        $password
 * @property string                        $otp_code
 * @property int                           $attempt
 * @property string                        $expired_at
 * @property string|null                   $created_at
 * @property string|null                   $updated_at
 * @property-read Collection|Task[]        $tasks
 * @property-read Collection|ProjectUser[] $projectUsers
 * @property int $attempts
 * @property \Illuminate\Support\Carbon $expires_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PendingRegistration newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PendingRegistration newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PendingRegistration query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PendingRegistration whereAttempts($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PendingRegistration whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PendingRegistration whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PendingRegistration whereExpiresAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PendingRegistration whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PendingRegistration whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PendingRegistration whereOtpCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PendingRegistration wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PendingRegistration whereUpdatedAt($value)
 */
	class PendingRegistration extends \Eloquent {}
}

namespace App\Models{
/**
 * App\Models\Project
 *
 * @property int                           $id
 * @property string                        $name
 * @property string                        $description
 * @property string|null                   $created_at
 * @property string|null                   $updated_at
 * @property-read Collection|Task[]        $tasks
 * @property-read Collection|ProjectUser[] $projectUsers
 * @property-read int|null $project_users_count
 * @property-read int|null $tasks_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Project newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Project newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Project query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Project whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Project whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Project whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Project whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Project whereUpdatedAt($value)
 */
	class Project extends \Eloquent {}
}

namespace App\Models{
/**
 * App\Models\ProjectUser
 *
 * @property int                     $id
 * @property int                     $project_id
 * @property string                  $email
 * @property string                  $role
 * @property string                  $token
 * @property string                  $status
 * @property string|null             $created_at
 * @property string|null             $updated_at
 * @property-read Collection|Project $project
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectInvitation newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectInvitation newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectInvitation query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectInvitation whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectInvitation whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectInvitation whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectInvitation whereProjectId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectInvitation whereRole($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectInvitation whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectInvitation whereToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectInvitation whereUpdatedAt($value)
 */
	class ProjectInvitation extends \Eloquent {}
}

namespace App\Models{
/**
 * App\Models\ProjectUser
 *
 * @property int                     $id
 * @property int                     $user_id
 * @property int                     $project_id
 * @property string                  $role
 * @property string|null             $created_at
 * @property string|null             $updated_at
 * @property-read Collection|User    $user
 * @property-read Collection|Project $project
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectUser newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectUser newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectUser query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectUser whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectUser whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectUser whereProjectId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectUser whereRole($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectUser whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectUser whereUserId($value)
 */
	class ProjectUser extends \Eloquent {}
}

namespace App\Models{
/**
 * App\Models\Task
 *
 * @property int                            $id
 * @property int                            $project_id
 * @property int                            $assigned_to
 * @property string                         $title
 * @property string                         $description
 * @property string                         $status
 * @property string                         $due_date
 * @property string|null                    $created_at
 * @property string|null                    $updated_at
 * @property-read Collection|Project        $project
 * @property-read Collection|User           $user
 * @property-read Collection|TaskActivity[] $taskActivities
 * @property-read \App\Models\User|null $assignee
 * @property-read int|null $task_activities_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Task newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Task newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Task query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Task whereAssignedTo($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Task whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Task whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Task whereDueDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Task whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Task whereProjectId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Task whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Task whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Task whereUpdatedAt($value)
 */
	class Task extends \Eloquent {}
}

namespace App\Models{
/**
 * App\Models\TaskActivities
 *
 * @property int                  $id
 * @property int                  $task_id
 * @property int                  $user_id
 * @property string               $action
 * @property string               $description
 * @property string|null          $created_at
 * @property string|null          $updated_at
 * @property-read Collection|User $user
 * @property-read Collection|Task $tasks
 * @property-read \App\Models\Task $task
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskActivity newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskActivity newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskActivity query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskActivity whereAction($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskActivity whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskActivity whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskActivity whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskActivity whereTaskId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskActivity whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskActivity whereUserId($value)
 */
	class TaskActivity extends \Eloquent {}
}

namespace App\Models{
/**
 * App\Models\User
 *
 * @property int                           $id
 * @property string                        $name
 * @property string                        $email
 * @property string|null                   $email_verified_at
 * @property string                        $password
 * @property string|null                   $remember_token
 * @property string|null                   $created_at
 * @property string|null                   $updated_at
 * @property-read Collection|Task[]        $tasks
 * @property-read Collection|ProjectUser[] $projectUsers
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read int|null $project_users_count
 * @property-read int|null $tasks_count
 * @method static \Database\Factories\UserFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmailVerifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRememberToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUpdatedAt($value)
 */
	class User extends \Eloquent {}
}

