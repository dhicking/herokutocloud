import { Head, router } from '@inertiajs/react';
import axios from 'axios';
import { CheckCircle2, ExternalLink, Loader2, Unplug, XCircle } from 'lucide-react';
import { type FormEventHandler, useState } from 'react';
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
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { edit } from '@/routes/integrations';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Integrations',
        href: edit().url,
    },
];

interface Props {
    herokuConnected: boolean;
    cloudConnected: boolean;
    cloudOrganizationName: string | null;
    status?: string;
}

export default function Integrations({
    herokuConnected,
    cloudConnected,
    cloudOrganizationName,
    status,
}: Props) {
    const [cloudApiToken, setCloudApiToken] = useState('');
    const [cloudOrgName, setCloudOrgName] = useState('');
    const [cloudSaving, setCloudSaving] = useState(false);
    const [cloudError, setCloudError] = useState('');

    const submitCloudToken: FormEventHandler = async (e) => {
        e.preventDefault();
        setCloudSaving(true);
        setCloudError('');
        try {
            await axios.post('/api/cloud/token', {
                api_token: cloudApiToken,
                organization_name: cloudOrgName || null,
            });
            setCloudApiToken('');
            setCloudOrgName('');
            router.reload();
        } catch (err: unknown) {
            if (
                axios.isAxiosError(err) &&
                err.response?.status === 422 &&
                err.response?.data?.errors?.api_token
            ) {
                const messages = err.response.data.errors.api_token;
                setCloudError(Array.isArray(messages) ? messages[0] : messages);
            } else {
                setCloudError('Failed to save Cloud API token.');
            }
        } finally {
            setCloudSaving(false);
        }
    };

    const disconnectHeroku = () => {
        router.delete('/heroku/disconnect', {
            preserveScroll: true,
        });
    };

    const disconnectCloud = () => {
        router.delete('/api/cloud/token', {
            preserveScroll: true,
            onSuccess: () => router.reload(),
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Integrations" />

            <h1 className="sr-only">Integrations</h1>

            <SettingsLayout>
                <div className="space-y-8">
                    <Heading
                        variant="small"
                        title="Service connections"
                        description="Connect Laravel Cloud first (API token), then Heroku, to enable app migration"
                    />

                    {status === 'heroku-connected' && (
                        <div className="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-800/30 dark:bg-green-950/20 dark:text-green-300">
                            Heroku account connected successfully.
                        </div>
                    )}

                    {status === 'heroku-disconnected' && (
                        <div className="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-800/30 dark:bg-amber-950/20 dark:text-amber-300">
                            Heroku account disconnected.
                        </div>
                    )}

                    <Card>
                        <CardHeader>
                            <div className="space-y-1">
                                <CardTitle className="flex items-center gap-2">
                                    Laravel Cloud
                                    {cloudConnected ? (
                                        <CheckCircle2 className="h-4 w-4 text-green-500" />
                                    ) : (
                                        <XCircle className="h-4 w-4 text-muted-foreground" />
                                    )}
                                </CardTitle>
                                <CardDescription>
                                    {cloudConnected
                                        ? `Connected${cloudOrganizationName ? ` to ${cloudOrganizationName}` : ''}. We use this to create apps, look up repos, and manage resources.`
                                        : 'Add your API token first. We use it to create Cloud apps, look up repositories, and run the migration.'}
                                </CardDescription>
                            </div>
                        </CardHeader>
                        <CardContent>
                            {cloudConnected ? (
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={disconnectCloud}
                                >
                                    <Unplug className="mr-1.5 h-3.5 w-3.5" />
                                    Disconnect
                                </Button>
                            ) : (
                                <form
                                    onSubmit={submitCloudToken}
                                    className="space-y-4"
                                >
                                    <div className="grid gap-2">
                                        <Label htmlFor="api_token">
                                            API Token
                                        </Label>
                                        <Input
                                            id="api_token"
                                            type="password"
                                            value={cloudApiToken}
                                            onChange={(e) =>
                                                setCloudApiToken(e.target.value)
                                            }
                                            placeholder="Your Laravel Cloud API token"
                                        />
                                        {cloudError && (
                                            <p className="text-sm text-red-600 dark:text-red-400">
                                                {cloudError}
                                            </p>
                                        )}
                                        <p className="text-xs text-muted-foreground">
                                            Generate a token from your{' '}
                                            <a
                                                href="https://cloud.laravel.com"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                className="underline underline-offset-2 hover:text-foreground"
                                            >
                                                Cloud organization settings
                                            </a>
                                            .
                                        </p>
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="organization_name">
                                            Organization name{' '}
                                            <span className="text-muted-foreground">
                                                (optional)
                                            </span>
                                        </Label>
                                        <Input
                                            id="organization_name"
                                            value={cloudOrgName}
                                            onChange={(e) =>
                                                setCloudOrgName(e.target.value)
                                            }
                                            placeholder="My Organization"
                                        />
                                    </div>

                                    <Button
                                        type="submit"
                                        size="sm"
                                        disabled={cloudSaving || !cloudApiToken}
                                    >
                                        {cloudSaving && (
                                            <Loader2 className="mr-1.5 h-3.5 w-3.5 animate-spin" />
                                        )}
                                        Save token
                                    </Button>
                                </form>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <div className="flex items-center justify-between">
                                <div className="space-y-1">
                                    <CardTitle className="flex items-center gap-2">
                                        Heroku
                                        {herokuConnected ? (
                                            <CheckCircle2 className="h-4 w-4 text-green-500" />
                                        ) : (
                                            <XCircle className="h-4 w-4 text-muted-foreground" />
                                        )}
                                    </CardTitle>
                                    <CardDescription>
                                        {herokuConnected
                                            ? 'Your Heroku account is connected. We can read your apps, config, and add-ons.'
                                            : 'Connect Heroku via OAuth so we can read the app you want to migrate.'}
                                    </CardDescription>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent>
                            {herokuConnected ? (
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={disconnectHeroku}
                                >
                                    <Unplug className="mr-1.5 h-3.5 w-3.5" />
                                    Disconnect
                                </Button>
                            ) : (
                                <Button asChild size="sm">
                                    <a href="/heroku/redirect">
                                        <ExternalLink className="mr-1.5 h-3.5 w-3.5" />
                                        Connect Heroku
                                    </a>
                                </Button>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
