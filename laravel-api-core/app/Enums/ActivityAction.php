<?php

namespace App\Enums;

enum ActivityAction: string
{
    // Task
    case Created                 = 'created';
    case StatusChanged           = 'status_changed';
    case Assigned                = 'assigned';
    case Updated                 = 'updated';
    case TaskDeleted             = 'task_deleted';
    case ChecklistItemAdded      = 'checklist_item_added';
    case ChecklistItemCompleted  = 'checklist_item_completed';
    case ChecklistItemReopened   = 'checklist_item_reopened';
    case ChecklistItemRemoved    = 'checklist_item_removed';

    // Project
    case ProjectUpdated          = 'project_updated';
    case InvitationSent          = 'invitation_sent';
    case InvitationRevoked       = 'invitation_revoked';
    case InvitationAccepted      = 'invitation_accepted';
    case InvitationDeclined      = 'invitation_declined';
    case MemberRoleChanged       = 'member_role_changed';
    case MemberRemoved           = 'member_removed';
    case MemberLeft              = 'member_left';
}
