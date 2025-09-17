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
