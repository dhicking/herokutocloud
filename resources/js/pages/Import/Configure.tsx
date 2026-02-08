import { Head, Link, router } from '@inertiajs/react';
import axios from 'axios';
import { Loader2, Rocket } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

interface HerokuApp {
    id: string;
    name: string;
    web_url: string;
    region: { name: string };
    buildpack_provided_description: string | null;
    is_compatible: boolean;
}

interface MigrationPlanSummary {
    heroku_app_id: string;
    heroku_app_name: string;
    github_repository: string;
    application: { name: string; region: string };
    variables: { key: string; action: string }[];
    database: { name: string } | null;
    cache: { name: string } | null;
    domains: { name: string }[];
    warnings: string[];
}

export default function Configure() {
    const [apps, setApps] = useState<HerokuApp[]>([]);
    const [loadingApps, setLoadingApps] = useState(true);
    const [selectedAppId, setSelectedAppId] = useState('');
    const [githubRepo, setGithubRepo] = useState('');
    const [plan, setPlan] = useState<MigrationPlanSummary | null>(null);
    const [loadingPlan, setLoadingPlan] = useState(false);
    const [deploying, setDeploying] = useState(false);
    const [error, setError] = useState('');

    const loadApps = useCallback(async () => {
        setLoadingApps(true);
        setError('');
        try {
            const { data } = await axios.get<HerokuApp[]>('/api/heroku/apps');
            setApps(data.filter((a) => a.is_compatible));
        } catch {
            setError('Failed to load Heroku apps.');
        } finally {
            setLoadingApps(false);
        }
    }, []);

    useEffect(() => {
        loadApps();
    }, [loadApps]);

    const handleGeneratePlan = async () => {
        if (!selectedAppId || !githubRepo.match(/^[a-zA-Z0-9._-]+\/[a-zA-Z0-9._-]+$/)) {
            setError('Select an app and enter a valid GitHub repository (owner/repo).');
            return;
        }
        setLoadingPlan(true);
        setError('');
        try {
            const { data } = await axios.post<MigrationPlanSummary>('/api/import/plan', {
                heroku_app_id: selectedAppId,
                github_repository: githubRepo,
            });
            setPlan(data);
        } catch (err: unknown) {
            setError(axios.isAxiosError(err) && err.response?.data?.message ? String(err.response.data.message) : 'Failed to generate plan.');
        } finally {
            setLoadingPlan(false);
        }
    };

    const handleDeploy = async () => {
        setDeploying(true);
        setError('');
        try {
            await axios.post('/api/import/execute');
            router.visit('/import/deploy');
        } catch (err: unknown) {
            setError(axios.isAxiosError(err) && err.response?.data?.message ? String(err.response.data.message) : 'Deployment failed.');
        } finally {
            setDeploying(false);
        }
    };

    const varCount = plan ? plan.variables.filter((v) => v.action === 'import').length : 0;

    return (
        <>
            <Head title="Configure — Import from Heroku" />
            <div className="min-h-screen bg-background">
                <header className="border-b px-4 py-3">
                    <div className="mx-auto flex max-w-4xl items-center justify-between">
                        <Link href="/import" className="text-sm font-medium text-muted-foreground hover:text-foreground">
                            ← Connect
                        </Link>
                        <h1 className="text-sm font-semibold">Import from Heroku</h1>
                    </div>
                </header>
                <main className="mx-auto max-w-4xl px-4 py-8">
                    <div className="mb-6">
                        <h2 className="text-2xl font-semibold">Step 2: Review & Configure</h2>
                        <p className="mt-1 text-muted-foreground">
                            Select your Heroku app and GitHub repo. We'll create a migration plan with smart defaults.
                        </p>
                    </div>
                    {error && (
                        <Alert variant="destructive" className="mb-6">
                            <AlertDescription>{error}</AlertDescription>
                        </Alert>
                    )}
                    <Card className="mb-6">
                        <CardHeader>
                            <CardTitle>App & Repository</CardTitle>
                            <CardDescription>Choose the app to migrate and the GitHub repository for Laravel Cloud.</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid gap-2">
                                <Label>Heroku App</Label>
                                {loadingApps ? (
                                    <div className="flex items-center gap-2 text-sm text-muted-foreground">
                                        <Loader2 className="h-4 w-4 animate-spin" /> Loading apps...
                                    </div>
                                ) : (
                                    <Select value={selectedAppId} onValueChange={setSelectedAppId}>
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select an app" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {apps.map((app) => (
                                                <SelectItem key={app.id} value={app.id}>
                                                    {app.name} ({app.region?.name ?? 'us'})
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                )}
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="github_repo">GitHub repository (owner/repo)</Label>
                                <Input
                                    id="github_repo"
                                    value={githubRepo}
                                    onChange={(e) => setGithubRepo(e.target.value)}
                                    placeholder="my-org/my-laravel-app"
                                />
                            </div>
                            <Button onClick={handleGeneratePlan} disabled={loadingPlan || !selectedAppId || !githubRepo}>
                                {loadingPlan && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
                                Generate migration plan
                            </Button>
                        </CardContent>
                    </Card>
                    {plan && (
                        <Card className="mb-6">
                            <CardHeader>
                                <CardTitle>Migration summary</CardTitle>
                                <CardDescription>
                                    {varCount} env vars, {plan.database ? 'database' : 'no database'}, {plan.cache ? 'cache' : 'no cache'}, {plan.domains.length} domain(s).
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                {plan.warnings.length > 0 && (
                                    <Alert>
                                        <AlertDescription>
                                            <ul className="list-inside list-disc text-sm">
                                                {plan.warnings.map((w, i) => (
                                                    <li key={i}>{w}</li>
                                                ))}
                                            </ul>
                                        </AlertDescription>
                                    </Alert>
                                )}
                                <Dialog>
                                    <DialogTrigger asChild>
                                        <Button disabled={deploying}>
                                            {deploying && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
                                            <Rocket className="mr-2 h-4 w-4" />
                                            Deploy to Laravel Cloud
                                        </Button>
                                    </DialogTrigger>
                                    <DialogContent>
                                        <DialogHeader>
                                            <DialogTitle>Deploy to Laravel Cloud</DialogTitle>
                                            <DialogDescription>
                                                This will create the application, environment, and resources. Continue?
                                            </DialogDescription>
                                        </DialogHeader>
                                        <DialogFooter>
                                            <Button variant="outline" onClick={() => {}}>
                                                Cancel
                                            </Button>
                                            <Button onClick={handleDeploy} disabled={deploying}>
                                                {deploying && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
                                                Deploy
                                            </Button>
                                        </DialogFooter>
                                    </DialogContent>
                                </Dialog>
                            </CardContent>
                        </Card>
                    )}
                </main>
            </div>
        </>
    );
}
