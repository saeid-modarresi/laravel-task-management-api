<?php

namespace App\Listeners;

use App\Events\TaskUpdated;
use App\Jobs\SendNotificationJob;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendTaskNotification implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     * 
     * @param TaskUpdated $event
     * @return void
     * 
     * TODO: In a real application, we should send notifications only to:
     * - Users assigned to the task
     * - Project stakeholders
     * - Task watchers/followers
     * For now, sending to all users for demonstration purposes
     */
    public function handle(TaskUpdated $event): void
    {
        $task = $event->task;
        $updatedFields = $event->updatedFields;

        // For demonstration, we'll send notification to all users
        // In a real application, you'd send to assigned users or stakeholders
        $users = User::all();

        foreach ($users as $user) {
            $notificationData = [
                'task_id' => $task->id,
                'task_title' => $task->title,
                'message' => 'A task has been updated',
                'updated_fields' => $updatedFields,
                'updated_at' => now()->toISOString(),
            ];

            SendNotificationJob::dispatch(
                $user->id,
                'task_updated',
                $notificationData
            );
        }
    }
}
