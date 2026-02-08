import { Head, Link } from '@inertiajs/react';
import { CheckCircle2, ExternalLink, XCircle } from 'lucide-react';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';

interface DeployResult {
    success: boolean;
    vanity_url?: string;
    error?: string;
    application_id?: string;
    domain_dns_records?: Record<string, string>;
}

interface DeployProps {
    deployResult: DeployResult | null;
}

export default function Deploy({ deployResult }: DeployProps) {
    const success = deployResult?.success ?? false;
    const vanityUrl = deployResult?.vanity_url;
    const dnsRecords = deployResult?.domain_dns_records ?? {};

    return (
        <>
            <Head title="Deploy — Import from Heroku" />
            <div className="min-h-screen bg-background">
                <header className="border-b px-4 py-3">
                    <div className="mx-auto flex max-w-4xl items-center justify-between">
                        <Link href="/import/configure" className="text-sm font-medium text-muted-foreground hover:text-foreground">
                            ← Configure
                        </Link>
                        <h1 className="text-sm font-semibold">Import from Heroku</h1>
                    </div>
                </header>
                <main className="mx-auto max-w-4xl px-4 py-8">
                    <div className="mb-6">
                        <h2 className="text-2xl font-semibold">Step 3: Deploy</h2>
                        <p className="mt-1 text-muted-foreground">
                            {deployResult ? (success ? 'Your app is live.' : 'Deployment failed.') : 'Run the deployment from the Configure step.'}
                        </p>
                    </div>
                    {!deployResult && (
                        <Card>
                            <CardContent className="py-8">
                                <p className="text-center text-muted-foreground">
                                    No deployment result. Go back to Configure and click &quot;Deploy to Laravel Cloud&quot;.
                                </p>
                                <div className="mt-4 flex justify-center">
                                    <Button asChild>
                                        <Link href="/import/configure">Back to Configure</Link>
                                    </Button>
                                </div>
                            </CardContent>
                        </Card>
                    )}
                    {deployResult && success && (
                        <div className="space-y-6">
                            <div className="flex items-center gap-2 rounded-lg border border-green-200 bg-green-50 p-4 dark:border-green-800/30 dark:bg-green-950/20">
                                <CheckCircle2 className="h-6 w-6 text-green-600 dark:text-green-400" />
                                <div>
                                    <h3 className="font-semibold text-green-900 dark:text-green-200">Your app is live on Laravel Cloud</h3>
                                    {vanityUrl && (
                                        <a
                                            href={`https://${vanityUrl}`}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            className="mt-1 inline-flex items-center gap-1 text-sm text-green-700 underline dark:text-green-300"
                                        >
                                            https://{vanityUrl}
                                            <ExternalLink className="h-3 w-3" />
                                        </a>
                                    )}
                                </div>
                            </div>
                            <Card>
                                <CardHeader>
                                    <CardTitle>Dashboard</CardTitle>
                                    <CardDescription>Manage your app and run the database migration when ready.</CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <Button asChild>
                                        <a href="https://cloud.laravel.com" target="_blank" rel="noopener noreferrer">
                                            <ExternalLink className="mr-2 h-4 w-4" />
                                            Open Laravel Cloud
                                        </a>
                                    </Button>
                                </CardContent>
                            </Card>
                            {Object.keys(dnsRecords).length > 0 && (
                                <Card>
                                    <CardHeader>
                                        <CardTitle>DNS records</CardTitle>
                                        <CardDescription>Configure these records for your custom domains.</CardDescription>
                                    </CardHeader>
                                    <CardContent>
                                        <Table>
                                            <TableHeader>
                                                <TableRow>
                                                    <TableHead>Domain</TableHead>
                                                    <TableHead>Target</TableHead>
                                                </TableRow>
                                            </TableHeader>
                                            <TableBody>
                                                {Object.entries(dnsRecords).map(([name, target]) => (
                                                    <TableRow key={name}>
                                                        <TableCell>{name}</TableCell>
                                                        <TableCell className="font-mono text-sm">{target}</TableCell>
                                                    </TableRow>
                                                ))}
                                            </TableBody>
                                        </Table>
                                    </CardContent>
                                </Card>
                            )}
                            <Alert>
                                <AlertDescription>
                                    <strong>Your app is connected to your Heroku database.</strong> Everything is running, but the database still lives on Heroku. To complete the migration, use the database migration tool in Laravel Cloud to transfer your data to Serverless Postgres. Heroku may rotate credentials over time — complete the migration soon.
                                </AlertDescription>
                            </Alert>
                        </div>
                    )}
                    {deployResult && !success && (
                        <Alert variant="destructive">
                            <XCircle className="h-4 w-4" />
                            <AlertDescription>
                                {deployResult.error ?? 'Deployment failed.'}
                            </AlertDescription>
                        </Alert>
                    )}
                </main>
            </div>
        </>
    );
}
