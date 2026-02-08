import { Head, Link, router } from '@inertiajs/react';
import axios from 'axios';
import { CheckCircle2, ExternalLink, Loader2, XCircle } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

interface ConnectProps {
    herokuConnected: boolean;
    cloudConnected: boolean;
    cloudOrganizationName: string | null;
    status?: string;
}

export default function Connect({
    herokuConnected,
    cloudConnected,
    cloudOrganizationName,
    status,
}: ConnectProps) {
    const [cloudToken, setCloudToken] = useState('');
    const [verifying, setVerifying] = useState(false);
    const [cloudError, setCloudError] = useState('');

    const handleVerifyCloud = async () => {
        setVerifying(true);
        setCloudError('');
        try {
            await axios.post('/auth/cloud/verify', { api_token: cloudToken });
            router.reload();
        } catch (err: unknown) {
            setCloudError(
                axios.isAxiosError(err) && err.response?.data?.message
                    ? String(err.response.data.message)
                    : 'Verification failed.',
            );
        } finally {
            setVerifying(false);
        }
    };

    const canProceed = herokuConnected && cloudConnected;

    return (
        <>
            <Head title="Connect — Import from Heroku" />
            <div className="min-h-screen bg-background">
                <header className="border-b px-4 py-3">
                    <div className="mx-auto flex max-w-4xl items-center justify-between">
                        <Link href="/" className="text-sm font-medium text-muted-foreground hover:text-foreground">
                            ← Home
                        </Link>
                        <h1 className="text-sm font-semibold">Import from Heroku</h1>
                    </div>
                </header>
                <main className="mx-auto max-w-4xl px-4 py-8">
                    <div className="mb-6">
                        <h2 className="text-2xl font-semibold">Step 1: Connect</h2>
                        <p className="mt-1 text-muted-foreground">
                            Connect Heroku and Laravel Cloud to continue.
                        </p>
                    </div>
                    {status === 'heroku-connected' && (
                        <div className="mb-6 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-800/30 dark:bg-green-950/20 dark:text-green-300">
                            Heroku connected successfully.
                        </div>
                    )}
                    <div className="grid gap-6 md:grid-cols-2">
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    {herokuConnected ? (
                                        <CheckCircle2 className="h-5 w-5 text-green-500" />
                                    ) : (
                                        <XCircle className="h-5 w-5 text-muted-foreground" />
                                    )}
                                    Heroku
                                </CardTitle>
                                <CardDescription>
                                    {herokuConnected
                                        ? 'Your Heroku account is connected.'
                                        : 'Authorize with Heroku to read your apps and config.'}
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                {herokuConnected ? (
                                    <p className="text-sm text-muted-foreground">You can proceed to the next step.</p>
                                ) : (
                                    <Button asChild>
                                        <a href="/auth/heroku">
                                            <ExternalLink className="mr-2 h-4 w-4" />
                                            Connect with Heroku
                                        </a>
                                    </Button>
                                )}
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    {cloudConnected ? (
                                        <CheckCircle2 className="h-5 w-5 text-green-500" />
                                    ) : (
                                        <XCircle className="h-5 w-5 text-muted-foreground" />
                                    )}
                                    Laravel Cloud
                                </CardTitle>
                                <CardDescription>
                                    {cloudConnected
                                        ? cloudOrganizationName
                                            ? `Connected to ${cloudOrganizationName}`
                                            : 'API token verified.'
                                        : 'Paste your API token from cloud.laravel.com'}
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                {cloudConnected ? (
                                    <p className="text-sm text-muted-foreground">You can proceed to the next step.</p>
                                ) : (
                                    <div className="space-y-3">
                                        <Label htmlFor="cloud_token">API Token</Label>
                                        <Input
                                            id="cloud_token"
                                            type="password"
                                            value={cloudToken}
                                            onChange={(e) => setCloudToken(e.target.value)}
                                            placeholder="Paste your token"
                                        />
                                        {cloudError && (
                                            <p className="text-sm text-destructive">{cloudError}</p>
                                        )}
                                        <Button onClick={handleVerifyCloud} disabled={verifying || !cloudToken}>
                                            {verifying && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
                                            Verify
                                        </Button>
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </div>
                    <div className="mt-8 flex justify-end">
                        <Button asChild disabled={!canProceed}>
                            <Link href="/import/configure">Continue to Configure →</Link>
                        </Button>
                    </div>
                </main>
            </div>
        </>
    );
}
