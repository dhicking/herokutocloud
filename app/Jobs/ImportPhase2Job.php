<?php

namespace App\Jobs;

use App\Models\Import;
use App\Services\Heroku\HerokuApi;
use App\Services\LaravelCloud\CloudApi;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ImportPhase2Job implements ShouldQueue
{
    use Queueable;

    public function __construct(public Import $import) {}

    public function handle(): void
    {
        $import = $this->import;

        try {
            $import->update(['status' => Import::STATUS_PHASE2_RUNNING]);

            $user = $import->user;
            $herokuApi = new HerokuApi($user->herokuToken);
            $cloudApi = new CloudApi($user->cloudToken);

            // Step 1: Create Serverless Postgres on Cloud
            $import->appendPhase2Log('Creating Serverless Postgres cluster...');
            $clusterName = $import->heroku_app_name.'-db';
            $herokuRegion = $import->heroku_app_data['appData']['region']['name'] ?? 'us';
            $cloudRegion = ImportPhase1Job::mapRegion($herokuRegion);

            $cluster = $cloudApi->createDatabaseCluster(
                $clusterName,
                'serverless-postgres',
                $cloudRegion,
                '0.25',
                10,
            );
            $clusterId = $cluster['data']['id'] ?? $cluster['id'];
            $import->update(['cloud_database_cluster_id' => $clusterId]);
            $import->appendPhase2Log("Database cluster created: {$clusterId}");

            // Step 2: Create logical database
            $import->appendPhase2Log('Creating database...');
            $database = $cloudApi->createDatabase($clusterId, $import->heroku_app_name);
            $databaseId = $database['data']['id'] ?? $database['id'];
            $import->update(['cloud_database_id' => $databaseId]);
            $import->appendPhase2Log("Database created: {$databaseId}");

            // Step 3: Attach database to environment
            $import->appendPhase2Log('Attaching database to environment...');
            $cloudApi->updateEnvironment($import->cloud_environment_id, [
                'database_schema_id' => $databaseId,
            ]);
            $import->appendPhase2Log('Database attached to environment.');

            // Step 4: Capture Heroku backup
            $import->appendPhase2Log('Initiating Heroku database backup...');
            $herokuAddons = $import->heroku_app_data['addons'] ?? [];
            $postgresAddonId = null;
            foreach ($herokuAddons as $addon) {
                if (($addon['addon_service']['name'] ?? '') === 'heroku-postgresql') {
                    $postgresAddonId = $addon['id'];
                    break;
                }
            }

            if (! $postgresAddonId) {
                $import->appendPhase2Log('No Heroku Postgres addon found. Skipping backup/restore.');
                $import->appendPhase2Log('Database created and attached. You may need to restore manually.');
            } else {
                $herokuApi->captureBackup($postgresAddonId);
                $import->appendPhase2Log('Backup initiated. Waiting for completion...');

                $maxAttempts = 60;
                $latestTransfer = null;
                for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
                    sleep(5);
                    $transfers = $herokuApi->listTransfers($import->heroku_app_id);
                    $backups = array_filter($transfers, fn ($t) => ($t['from_type'] ?? '') === 'pg_dump');
                    $latestTransfer = end($backups) ?: null;

                    if ($latestTransfer && ($latestTransfer['succeeded'] ?? false)) {
                        break;
                    }

                    if ($latestTransfer && isset($latestTransfer['finished_at']) && ! ($latestTransfer['succeeded'] ?? false)) {
                        throw new \RuntimeException('Heroku backup failed.');
                    }
                }

                if (! $latestTransfer || ! ($latestTransfer['succeeded'] ?? false)) {
                    throw new \RuntimeException('Heroku backup timed out after 5 minutes.');
                }

                $import->appendPhase2Log('Backup completed. Retrieving download URL...');
                $urlResponse = $herokuApi->getBackupUrl($import->heroku_app_id, $latestTransfer['num']);
                $backupUrl = $urlResponse['url'];
                $import->appendPhase2Log('Backup URL retrieved.');

                $import->appendPhase2Log("Backup available at: {$backupUrl}");
                $import->appendPhase2Log('Note: Use pg_restore or Cloud CLI to restore this backup to your new database.');
            }

            // Step 5: Remove DATABASE_URL from env vars
            $import->appendPhase2Log('Removing Heroku DATABASE_URL from environment variables...');
            $cloudApi->setEnvironmentVariables($import->cloud_environment_id, [
                ['key' => 'DATABASE_URL', 'value' => ''],
            ]);
            $import->appendPhase2Log('DATABASE_URL cleared. Cloud will use auto-injected database credentials.');

            // Step 6: Trigger redeployment
            $import->appendPhase2Log('Triggering redeployment...');
            $cloudApi->createDeployment($import->cloud_environment_id);
            $import->appendPhase2Log('Redeployment triggered.');

            $import->update(['status' => Import::STATUS_PHASE2_DONE]);
            $import->appendPhase2Log('Phase 2 complete!');

        } catch (\Throwable $e) {
            $import->markFailed($e->getMessage());
            $import->appendPhase2Log('Phase 2 failed: '.$e->getMessage());
            throw $e;
        }
    }
}
