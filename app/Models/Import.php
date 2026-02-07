<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Import extends Model
{
    /** @use HasFactory<\Database\Factories\ImportFactory> */
    use HasFactory;

    const STATUS_PENDING = 'pending';

    const STATUS_PHASE1_RUNNING = 'phase1_running';

    const STATUS_PHASE1_DONE = 'phase1_done';

    const STATUS_PHASE2_RUNNING = 'phase2_running';

    const STATUS_PHASE2_DONE = 'phase2_done';

    const STATUS_FAILED = 'failed';

    protected $fillable = [
        'user_id',
        'heroku_app_id',
        'heroku_app_name',
        'github_repository',
        'cloud_application_id',
        'cloud_environment_id',
        'cloud_database_cluster_id',
        'cloud_database_id',
        'status',
        'phase1_log',
        'phase2_log',
        'heroku_app_data',
        'error_message',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'phase1_log' => 'array',
            'phase2_log' => 'array',
            'heroku_app_data' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function appendPhase1Log(string $message): void
    {
        $log = $this->phase1_log ?? [];
        $log[] = '['.now()->toDateTimeString().'] '.$message;
        $this->update(['phase1_log' => $log]);
    }

    public function appendPhase2Log(string $message): void
    {
        $log = $this->phase2_log ?? [];
        $log[] = '['.now()->toDateTimeString().'] '.$message;
        $this->update(['phase2_log' => $log]);
    }

    public function markFailed(string $errorMessage): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'error_message' => $errorMessage,
        ]);
    }
}
