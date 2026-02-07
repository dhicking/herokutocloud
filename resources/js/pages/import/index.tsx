import { Head } from '@inertiajs/react';
import axios from 'axios';
import {
    AlertTriangle,
    ArrowLeft,
    ArrowRight,
    CheckCircle2,
    Cloud,
    Container,
    Database,
    Globe,
    Loader2,
    Rocket,
    Server,
    XCircle,
} from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { importMethod } from '@/routes';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Import from Heroku',
        href: importMethod().url,
    },
];

interface HerokuApp {
    id: string;
    name: string;
    web_url: string;
    region: { name: string };
    build_stack: { name: string };
}

interface HerokuAppDetails {
    app: HerokuApp;
    config_vars: Record<string, string>;
    formation: Array<{
        type: string;
        command: string;
        size: string;
        quantity: number;
    }>;
    addons: Array<{
        addon_service: { name: string };
        plan: { name: string };
    }>;
    domains: Array<{
        hostname: string;
        kind: string;
    }>;
    buildpack_installations: Array<{
        buildpack: { name: string; url: string };
    }>;
}

interface ImportRecord {
    id: number;
    status: string;
    phase1_log: string[] | null;
    phase2_log: string[] | null;
    error_message: string | null;
    cloud_application_id: string | null;
    cloud_environment_id: string | null;
}

const DYNO_SIZE_MAP: Record<string, string> = {
    eco: 'flex.g-1vcpu-512mb',
    basic: 'flex.g-1vcpu-512mb',
    'standard-1x': 'flex.g-1vcpu-512mb',
    'standard-2x': 'flex.g-2vcpu-1gb',
    'performance-m': 'pro.g-2vcpu-4gb',
    'performance-l': 'pro.g-8vcpu-16gb',
};

const REGION_MAP: Record<string, string> = {
    us: 'us-east-2 (Ohio)',
    eu: 'eu-west-2 (London)',
};

export default function ImportWizard() {
    const [step, setStep] = useState(1);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    // Step 1
    const [githubRepo, setGithubRepo] = useState('');

    // Step 2
    const [herokuApps, setHerokuApps] = useState<HerokuApp[]>([]);
    const [selectedAppId, setSelectedAppId] = useState('');
    const [appDetails, setAppDetails] = useState<HerokuAppDetails | null>(null);

    // Step 4
    const [importRecord, setImportRecord] = useState<ImportRecord | null>(null);

    const fetchApps = useCallback(async () => {
        setLoading(true);
        setError(null);
        try {
            const { data } = await axios.get('/api/heroku/apps');
            setHerokuApps(data);
        } catch {
            setError(
                'Failed to load Heroku apps. Make sure your Heroku account is connected.',
            );
        } finally {
            setLoading(false);
        }
    }, []);

    const fetchAppDetails = useCallback(async (appId: string) => {
        setLoading(true);
        setError(null);
        try {
            const { data } = await axios.get(`/api/heroku/apps/${appId}`);
            setAppDetails(data);
        } catch {
            setError('Failed to load app details.');
        } finally {
            setLoading(false);
        }
    }, []);

    const startImport = useCallback(async () => {
        if (!appDetails) return;
        setLoading(true);
        setError(null);
        try {
            const { data } = await axios.post('/api/imports', {
                heroku_app_id: appDetails.app.id,
                heroku_app_name: appDetails.app.name,
                github_repository: githubRepo,
            });
            setImportRecord(data);
            setStep(4);
        } catch (err: unknown) {
            const message =
                err instanceof Error ? err.message : 'Failed to start import.';
            setError(message);
        } finally {
            setLoading(false);
        }
    }, [appDetails, githubRepo]);

    // Poll import status
    useEffect(() => {
        if (!importRecord || step !== 4) return;

        const terminal = ['phase1_done', 'phase2_done', 'failed'];
        if (terminal.includes(importRecord.status)) return;

        const interval = setInterval(async () => {
            try {
                const { data } = await axios.get(
                    `/api/imports/${importRecord.id}`,
                );
                setImportRecord(data);
                if (terminal.includes(data.status)) {
                    clearInterval(interval);
                }
            } catch {
                // silently retry
            }
        }, 2000);

        return () => clearInterval(interval);
    }, [importRecord, step]);

    const goToStep2 = () => {
        if (!githubRepo.match(/^[a-zA-Z0-9._-]+\/[a-zA-Z0-9._-]+$/)) {
            setError('Repository must be in "owner/repo" format.');
            return;
        }
        setError(null);
        setStep(2);
        fetchApps();
    };

    const goToStep3 = () => {
        if (!selectedAppId) {
            setError('Please select a Heroku app.');
            return;
        }
        setError(null);
        fetchAppDetails(selectedAppId);
        setStep(3);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Import from Heroku" />

            <div className="mx-auto max-w-3xl px-4 py-8">
                <Heading
                    title="Import from Heroku"
                    description="Migrate your Heroku app to Laravel Cloud"
                />

                {/* Step indicator */}
                <div className="mb-8 flex items-center gap-2">
                    {[1, 2, 3, 4].map((s) => (
                        <div key={s} className="flex items-center gap-2">
                            <div
                                className={`flex h-8 w-8 items-center justify-center rounded-full text-sm font-medium transition-colors ${
                                    s === step
                                        ? 'bg-primary text-primary-foreground'
                                        : s < step
                                          ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
                                          : 'bg-muted text-muted-foreground'
                                }`}
                            >
                                {s < step ? (
                                    <CheckCircle2 className="h-4 w-4" />
                                ) : (
                                    s
                                )}
                            </div>
                            {s < 4 && (
                                <div
                                    className={`h-px w-8 ${s < step ? 'bg-green-300 dark:bg-green-700' : 'bg-border'}`}
                                />
                            )}
                        </div>
                    ))}
                    <span className="ml-3 text-sm text-muted-foreground">
                        {step === 1 && 'GitHub Repository'}
                        {step === 2 && 'Select Heroku App'}
                        {step === 3 && 'Review & Deploy'}
                        {step === 4 && 'Deploying'}
                    </span>
                </div>

                {error && (
                    <div className="mb-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-800/30 dark:bg-red-950/20 dark:text-red-300">
                        <div className="flex items-start gap-2">
                            <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0" />
                            <span>{error}</span>
                        </div>
                    </div>
                )}

                {/* Step 1: GitHub Repository */}
                {step === 1 && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Rocket className="h-5 w-5" />
                                GitHub Repository
                            </CardTitle>
                            <CardDescription>
                                Laravel Cloud deploys from GitHub. Your Heroku
                                app's code must already be in this repository.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid gap-2">
                                <Label htmlFor="github_repo">
                                    Repository (owner/repo)
                                </Label>
                                <Input
                                    id="github_repo"
                                    value={githubRepo}
                                    onChange={(e) =>
                                        setGithubRepo(e.target.value)
                                    }
                                    placeholder="my-org/my-laravel-app"
                                />
                                <p className="text-xs text-muted-foreground">
                                    The repository must be accessible from your
                                    Laravel Cloud organization.
                                </p>
                            </div>

                            <div className="flex justify-end">
                                <Button
                                    onClick={goToStep2}
                                    disabled={!githubRepo}
                                >
                                    Continue
                                    <ArrowRight className="ml-1.5 h-4 w-4" />
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* Step 2: Select Heroku App */}
                {step === 2 && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Container className="h-5 w-5" />
                                Select Heroku App
                            </CardTitle>
                            <CardDescription>
                                Choose the Heroku app you want to migrate.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {loading ? (
                                <div className="flex items-center justify-center py-8">
                                    <Loader2 className="h-6 w-6 animate-spin text-muted-foreground" />
                                    <span className="ml-2 text-sm text-muted-foreground">
                                        Loading your Heroku apps...
                                    </span>
                                </div>
                            ) : (
                                <>
                                    <div className="grid gap-2">
                                        <Label>Heroku App</Label>
                                        <Select
                                            value={selectedAppId}
                                            onValueChange={setSelectedAppId}
                                        >
                                            <SelectTrigger>
                                                <SelectValue placeholder="Select an app" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {herokuApps.map((app) => (
                                                    <SelectItem
                                                        key={app.id}
                                                        value={app.id}
                                                    >
                                                        <span className="font-medium">
                                                            {app.name}
                                                        </span>
                                                        <span className="ml-2 text-muted-foreground">
                                                            ({app.region?.name})
                                                        </span>
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>

                                    <div className="flex justify-between">
                                        <Button
                                            variant="outline"
                                            onClick={() => setStep(1)}
                                        >
                                            <ArrowLeft className="mr-1.5 h-4 w-4" />
                                            Back
                                        </Button>
                                        <Button
                                            onClick={goToStep3}
                                            disabled={!selectedAppId}
                                        >
                                            Continue
                                            <ArrowRight className="ml-1.5 h-4 w-4" />
                                        </Button>
                                    </div>
                                </>
                            )}
                        </CardContent>
                    </Card>
                )}

                {/* Step 3: Review Mapping */}
                {step === 3 && (
                    <div className="space-y-6">
                        {loading || !appDetails ? (
                            <Card>
                                <CardContent className="py-8">
                                    <div className="flex items-center justify-center">
                                        <Loader2 className="h-6 w-6 animate-spin text-muted-foreground" />
                                        <span className="ml-2 text-sm text-muted-foreground">
                                            Fetching app configuration...
                                        </span>
                                    </div>
                                </CardContent>
                            </Card>
                        ) : (
                            <>
                                <Card>
                                    <CardHeader>
                                        <CardTitle className="flex items-center gap-2">
                                            <Cloud className="h-5 w-5" />
                                            Migration summary
                                        </CardTitle>
                                        <CardDescription>
                                            Phase 1 will deploy your app on
                                            Laravel Cloud using your current
                                            Heroku database. No downtime.
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent>
                                        <dl className="space-y-4">
                                            <div className="flex items-start gap-3">
                                                <Server className="mt-0.5 h-4 w-4 shrink-0 text-muted-foreground" />
                                                <div>
                                                    <dt className="text-sm font-medium">
                                                        Application
                                                    </dt>
                                                    <dd className="text-sm text-muted-foreground">
                                                        {appDetails.app.name}{' '}
                                                        &rarr; {githubRepo}
                                                    </dd>
                                                </div>
                                            </div>

                                            <div className="flex items-start gap-3">
                                                <Globe className="mt-0.5 h-4 w-4 shrink-0 text-muted-foreground" />
                                                <div>
                                                    <dt className="text-sm font-medium">
                                                        Region
                                                    </dt>
                                                    <dd className="text-sm text-muted-foreground">
                                                        {appDetails.app.region
                                                            ?.name || 'us'}{' '}
                                                        &rarr;{' '}
                                                        {REGION_MAP[
                                                            appDetails.app
                                                                .region
                                                                ?.name || 'us'
                                                        ] || 'us-east-2'}
                                                    </dd>
                                                </div>
                                            </div>

                                            <div className="flex items-start gap-3">
                                                <Container className="mt-0.5 h-4 w-4 shrink-0 text-muted-foreground" />
                                                <div>
                                                    <dt className="text-sm font-medium">
                                                        Compute
                                                    </dt>
                                                    <dd className="space-y-1">
                                                        {appDetails.formation.map(
                                                            (f) => (
                                                                <div
                                                                    key={f.type}
                                                                    className="text-sm text-muted-foreground"
                                                                >
                                                                    {f.type} (
                                                                    {f.size}{' '}
                                                                    &times;
                                                                    {f.quantity})
                                                                    &rarr;{' '}
                                                                    {DYNO_SIZE_MAP[
                                                                        f.size?.toLowerCase()
                                                                    ] ||
                                                                        'flex.g-1vcpu-512mb'}
                                                                </div>
                                                            ),
                                                        )}
                                                    </dd>
                                                </div>
                                            </div>

                                            <div className="flex items-start gap-3">
                                                <Database className="mt-0.5 h-4 w-4 shrink-0 text-muted-foreground" />
                                                <div>
                                                    <dt className="text-sm font-medium">
                                                        Environment variables
                                                    </dt>
                                                    <dd className="text-sm text-muted-foreground">
                                                        {
                                                            Object.keys(
                                                                appDetails.config_vars,
                                                            ).length
                                                        }{' '}
                                                        variables (including
                                                        DATABASE_URL for Phase
                                                        1)
                                                    </dd>
                                                </div>
                                            </div>

                                            {appDetails.domains.filter(
                                                (d) => d.kind === 'custom',
                                            ).length > 0 && (
                                                <div className="flex items-start gap-3">
                                                    <Globe className="mt-0.5 h-4 w-4 shrink-0 text-muted-foreground" />
                                                    <div>
                                                        <dt className="text-sm font-medium">
                                                            Custom domains
                                                        </dt>
                                                        <dd className="text-sm text-muted-foreground">
                                                            {appDetails.domains
                                                                .filter(
                                                                    (d) =>
                                                                        d.kind ===
                                                                        'custom',
                                                                )
                                                                .map(
                                                                    (d) =>
                                                                        d.hostname,
                                                                )
                                                                .join(', ')}
                                                        </dd>
                                                    </div>
                                                </div>
                                            )}

                                            {appDetails.addons.length > 0 && (
                                                <div className="flex items-start gap-3">
                                                    <Database className="mt-0.5 h-4 w-4 shrink-0 text-muted-foreground" />
                                                    <div>
                                                        <dt className="text-sm font-medium">
                                                            Add-ons
                                                        </dt>
                                                        <dd className="space-y-1">
                                                            {appDetails.addons.map(
                                                                (a, i) => (
                                                                    <div
                                                                        key={i}
                                                                        className="text-sm text-muted-foreground"
                                                                    >
                                                                        {
                                                                            a
                                                                                .addon_service
                                                                                .name
                                                                        }{' '}
                                                                        (
                                                                        {
                                                                            a
                                                                                .plan
                                                                                .name
                                                                        }
                                                                        )
                                                                    </div>
                                                                ),
                                                            )}
                                                        </dd>
                                                    </div>
                                                </div>
                                            )}
                                        </dl>
                                    </CardContent>
                                </Card>

                                <div className="flex justify-between">
                                    <Button
                                        variant="outline"
                                        onClick={() => setStep(2)}
                                    >
                                        <ArrowLeft className="mr-1.5 h-4 w-4" />
                                        Back
                                    </Button>
                                    <Button
                                        onClick={startImport}
                                        disabled={loading}
                                    >
                                        {loading ? (
                                            <Loader2 className="mr-1.5 h-4 w-4 animate-spin" />
                                        ) : (
                                            <Rocket className="mr-1.5 h-4 w-4" />
                                        )}
                                        Start Phase 1
                                    </Button>
                                </div>
                            </>
                        )}
                    </div>
                )}

                {/* Step 4: Phase 1 Progress */}
                {step === 4 && importRecord && (
                    <div className="space-y-6">
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    {importRecord.status === 'failed' ? (
                                        <XCircle className="h-5 w-5 text-red-500" />
                                    ) : importRecord.status ===
                                      'phase1_done' ? (
                                        <CheckCircle2 className="h-5 w-5 text-green-500" />
                                    ) : (
                                        <Loader2 className="h-5 w-5 animate-spin" />
                                    )}
                                    {importRecord.status === 'failed'
                                        ? 'Import Failed'
                                        : importRecord.status === 'phase1_done'
                                          ? 'Phase 1 Complete'
                                          : 'Deploying to Laravel Cloud...'}
                                </CardTitle>
                                {importRecord.status === 'phase1_done' && (
                                    <CardDescription>
                                        Your app is now running on Laravel Cloud
                                        using your Heroku database. You can
                                        optionally migrate the database in Phase
                                        2.
                                    </CardDescription>
                                )}
                            </CardHeader>
                            <CardContent>
                                {/* Log output */}
                                {importRecord.phase1_log &&
                                    importRecord.phase1_log.length > 0 && (
                                        <div className="rounded-lg border bg-neutral-950 p-4">
                                            <div className="space-y-1 font-mono text-xs text-neutral-300">
                                                {importRecord.phase1_log.map(
                                                    (entry, i) => (
                                                        <div
                                                            key={i}
                                                            className={
                                                                entry.includes(
                                                                    'failed',
                                                                )
                                                                    ? 'text-red-400'
                                                                    : entry.includes(
                                                                            'complete',
                                                                          ) ||
                                                                        entry.includes(
                                                                            'successfully',
                                                                        )
                                                                      ? 'text-green-400'
                                                                      : ''
                                                            }
                                                        >
                                                            {entry}
                                                        </div>
                                                    ),
                                                )}
                                            </div>
                                        </div>
                                    )}

                                {importRecord.error_message && (
                                    <div className="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-800/30 dark:bg-red-950/20 dark:text-red-300">
                                        {importRecord.error_message}
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        {importRecord.status === 'phase1_done' && (
                            <div className="rounded-lg border border-blue-200 bg-blue-50 p-4 dark:border-blue-800/30 dark:bg-blue-950/20">
                                <h4 className="text-sm font-medium text-blue-900 dark:text-blue-200">
                                    What's next?
                                </h4>
                                <p className="mt-1 text-sm text-blue-700 dark:text-blue-300">
                                    Phase 2 (optional) migrates your Heroku
                                    Postgres database to Laravel Cloud's
                                    Serverless Postgres. This requires a brief
                                    maintenance window.
                                </p>
                            </div>
                        )}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
