<?php

namespace App\Jobs;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendNotificationJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    // Retry failed jobs 3 times with exponential backoff
    public $tries = 3;
    public $timeout = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $userId,
        public string $type,
        public array $data
    ) {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Verify user exists
            $user = User::findOrFail($this->userId);

            // Create the notification
            Notification::create([
                'user_id' => $this->userId,
                'type' => $this->type,
                'data' => $this->data,
                'read_at' => null,
            ]);

            Log::info("Notification sent successfully", [
                'user_id' => $this->userId,
                'type' => $this->type,
                'data' => $this->data
            ]);

        } catch (\Exception $e) {
            Log::error("Failed to send notification", [
                'user_id' => $this->userId,
                'type' => $this->type,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("SendNotificationJob failed after {$this->tries} attempts", [
            'user_id' => $this->userId,
            'type' => $this->type,
            'error' => $exception->getMessage()
        ]);
    }
}
