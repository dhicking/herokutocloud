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

interface Connections {
    heroku: boolean;
    cloud: boolean;
}

export default function ImportWizard() {
    const [step, setStep] = useState(1);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [connections, setConnections] = useState<Connections | null>(null);

    // Step 1
    const [githubRepo, setGithubRepo] = useState('');

    // Step 2
    const [herokuApps, setHerokuApps] = useState<HerokuApp[]>([]);
    const [selectedAppId, setSelectedAppId] = useState('');
    const [appDetails, setAppDetails] = useState<HerokuAppDetails | null>(null);

    // Step 4
    const [importRecord, setImportRecord] = useState<ImportRecord | null>(null);

    useEffect(() => {
        axios
            .get('/api/connections')
            .then(({ data }) => setConnections(data))
            .catch(() => setConnections({ heroku: false, cloud: false }));
    }, []);

    useEffect(() => {
        const params = new URLSearchParams(window.location.search);
        const importId = params.get('import');
        if (!importId) return;

        let cancelled = false;
        axios
            .get(`/api/imports/${importId}`)
            .then(({ data }) => {
                if (!cancelled) {
                    setImportRecord(data);
                    setStep(4);
                }
            })
            .catch(() => {
                if (!cancelled) {
                    window.history.replaceState(null, '', '/import');
                }
            });
        return () => {
            cancelled = true;
        };
    }, []);

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
            window.history.replaceState(null, '', `/import?import=${data.id}`);
        } catch (err: unknown) {
            const message =
                err instanceof Error ? err.message : 'Failed to start import.';
            setError(message);
        } finally {
            setLoading(false);
        }
    }, [appDetails, githubRepo]);

    const startPhase2 = useCallback(async () => {
        if (!importRecord) return;
        setLoading(true);
        setError(null);
        try {
            const { data } = await axios.post(
                `/api/imports/${importRecord.id}/phase2`,
            );
            setImportRecord(data);
        } catch (err: unknown) {
            const message =
                err instanceof Error
                    ? err.message
                    : 'Failed to start Phase 2.';
            setError(message);
        } finally {
            setLoading(false);
        }
    }, [importRecord]);

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

                {connections === null ? (
                    <div className="flex items-center justify-center py-12">
                        <Loader2 className="h-6 w-6 animate-spin text-muted-foreground" />
                    </div>
                ) : !connections.cloud ? (
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Cloud className="h-5 w-5" />
                                Connect Laravel Cloud first
                            </CardTitle>
                            <CardDescription>
                                We need your Laravel Cloud API token to create
                                the app, look up repositories, and run the
                                migration. Add it in Settings → Integrations,
                                then return here.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Button asChild>
                                <a href="/settings/integrations">
                                    Open Integrations
                                    <ArrowRight className="ml-1.5 h-4 w-4" />
                                </a>
                            </Button>
                        </CardContent>
                    </Card>
                ) : (
                    <>
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
                        {step === 4 && 'Deploy & Migrate'}
                    </span>
                </div>

                {error && (
                    <div className="mb-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-800/30 dark:bg-red-950/20 dark:text-red-300">
                        <div className="flex flex-col gap-2">
                            <div className="flex items-start gap-2">
                                <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0" />
                                <span>{error}</span>
                            </div>
                            {error.includes('Heroku account') && (
                                <a
                                    href="/heroku/redirect"
                                    className="inline-flex w-fit items-center rounded border border-red-300 bg-white px-3 py-1.5 text-sm font-medium text-red-700 hover:bg-red-50 dark:border-red-700 dark:bg-red-950/20 dark:text-red-300 dark:hover:bg-red-950/40"
                                >
                                    Connect Heroku account
                                </a>
                            )}
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

                {/* Step 4: Progress */}
                {step === 4 && importRecord && (
                    <div className="space-y-6">
                        {/* Phase 1 card */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    {importRecord.status === 'failed' &&
                                    !importRecord.phase2_log?.length ? (
                                        <XCircle className="h-5 w-5 text-red-500" />
                                    ) : [
                                          'phase1_done',
                                          'phase2_running',
                                          'phase2_done',
                                      ].includes(importRecord.status) ? (
                                        <CheckCircle2 className="h-5 w-5 text-green-500" />
                                    ) : importRecord.status === 'failed' ? (
                                        <XCircle className="h-5 w-5 text-red-500" />
                                    ) : (
                                        <Loader2 className="h-5 w-5 animate-spin" />
                                    )}
                                    Phase 1: Deploy to Laravel Cloud
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                {importRecord.status === 'pending' &&
                                    (!importRecord.phase1_log ||
                                        importRecord.phase1_log.length === 0) && (
                                        <div className="rounded-lg border border-amber-200 bg-amber-50 p-4 dark:border-amber-800/30 dark:bg-amber-950/20">
                                            <div className="flex items-start gap-2">
                                                <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0 text-amber-600 dark:text-amber-400" />
                                                <div>
                                                    <h4 className="text-sm font-medium text-amber-900 dark:text-amber-200">
                                                        Phase 1 is queued
                                                    </h4>
                                                    <p className="mt-1 text-sm text-amber-700 dark:text-amber-300">
                                                        Nothing is happening
                                                        because no queue worker
                                                        is running. On Laravel
                                                        Cloud: add a background
                                                        process (e.g. in your
                                                        app cluster) that runs{' '}
                                                        <code className="rounded bg-amber-100 px-1 dark:bg-amber-900/40">
                                                            php artisan
                                                            queue:work
                                                        </code>
                                                        . Then Phase 1 will
                                                        run automatically.
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    )}
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
                            </CardContent>
                        </Card>

                        {/* Phase 2 section */}
                        {importRecord.status === 'phase1_done' && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <Database className="h-5 w-5" />
                                        Phase 2: Migrate Database
                                    </CardTitle>
                                    <CardDescription>
                                        Provision Serverless Postgres on Laravel
                                        Cloud and migrate your Heroku database.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <div className="rounded-lg border border-amber-200 bg-amber-50 p-4 dark:border-amber-800/30 dark:bg-amber-950/20">
                                        <div className="flex items-start gap-2">
                                            <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0 text-amber-600 dark:text-amber-400" />
                                            <div>
                                                <h4 className="text-sm font-medium text-amber-900 dark:text-amber-200">
                                                    Downtime required
                                                </h4>
                                                <p className="mt-1 text-sm text-amber-700 dark:text-amber-300">
                                                    Any new data written to your
                                                    Heroku app during this
                                                    migration will not be moved
                                                    over. Consider putting your
                                                    Heroku app in maintenance
                                                    mode or read-only before
                                                    proceeding.
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                    <Button
                                        onClick={startPhase2}
                                        disabled={loading}
                                    >
                                        {loading ? (
                                            <Loader2 className="mr-1.5 h-4 w-4 animate-spin" />
                                        ) : (
                                            <Database className="mr-1.5 h-4 w-4" />
                                        )}
                                        Start Phase 2
                                    </Button>
                                </CardContent>
                            </Card>
                        )}

                        {/* Phase 2 progress */}
                        {['phase2_running', 'phase2_done'].includes(
                            importRecord.status,
                        ) && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        {importRecord.status ===
                                        'phase2_done' ? (
                                            <CheckCircle2 className="h-5 w-5 text-green-500" />
                                        ) : (
                                            <Loader2 className="h-5 w-5 animate-spin" />
                                        )}
                                        Phase 2: Migrate Database
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    {importRecord.phase2_log &&
                                        importRecord.phase2_log.length > 0 && (
                                            <div className="rounded-lg border bg-neutral-950 p-4">
                                                <div className="space-y-1 font-mono text-xs text-neutral-300">
                                                    {importRecord.phase2_log.map(
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
                                </CardContent>
                            </Card>
                        )}

                        {/* Phase 2 failed */}
                        {importRecord.status === 'failed' &&
                            importRecord.phase2_log &&
                            importRecord.phase2_log.length > 0 && (
                                <Card>
                                    <CardHeader>
                                        <CardTitle className="flex items-center gap-2">
                                            <XCircle className="h-5 w-5 text-red-500" />
                                            Phase 2 Failed
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <div className="rounded-lg border bg-neutral-950 p-4">
                                            <div className="space-y-1 font-mono text-xs text-neutral-300">
                                                {importRecord.phase2_log.map(
                                                    (entry, i) => (
                                                        <div
                                                            key={i}
                                                            className={
                                                                entry.includes(
                                                                    'failed',
                                                                )
                                                                    ? 'text-red-400'
                                                                    : ''
                                                            }
                                                        >
                                                            {entry}
                                                        </div>
                                                    ),
                                                )}
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>
                            )}

                        {/* Error message */}
                        {importRecord.error_message && (
                            <div className="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-800/30 dark:bg-red-950/20 dark:text-red-300">
                                {importRecord.error_message}
                            </div>
                        )}

                        {/* Completion message */}
                        {importRecord.status === 'phase2_done' && (
                            <div className="rounded-lg border border-green-200 bg-green-50 p-4 dark:border-green-800/30 dark:bg-green-950/20">
                                <h4 className="text-sm font-medium text-green-900 dark:text-green-200">
                                    Migration complete
                                </h4>
                                <p className="mt-1 text-sm text-green-700 dark:text-green-300">
                                    Your app is fully migrated to Laravel Cloud
                                    with its own Serverless Postgres database.
                                    You can now safely decommission your Heroku
                                    app.
                                </p>
                            </div>
                        )}
                    </div>
                )}
                    </>
                )}
            </div>
        </AppLayout>
    );
}
