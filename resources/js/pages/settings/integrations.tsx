import { Head, router, useForm } from '@inertiajs/react';
import { CheckCircle2, ExternalLink, Unplug, XCircle } from 'lucide-react';
import { type FormEventHandler } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
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
    const cloudForm = useForm({
        api_token: '',
        organization_name: '',
    });

    const submitCloudToken: FormEventHandler = (e) => {
        e.preventDefault();
        cloudForm.post('/api/cloud/token', {
            preserveScroll: true,
            onSuccess: () => {
                cloudForm.reset();
                router.reload();
            },
        });
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
                        description="Connect your Heroku and Laravel Cloud accounts to enable app migration"
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
                                            : 'Connect your Heroku account via OAuth to import your apps.'}
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
                                        ? `Connected${cloudOrganizationName ? ` to ${cloudOrganizationName}` : ''}. Your API token is stored securely.`
                                        : 'Enter your Laravel Cloud API token to create and manage Cloud resources.'}
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
                                            value={cloudForm.data.api_token}
                                            onChange={(e) =>
                                                cloudForm.setData(
                                                    'api_token',
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="Your Laravel Cloud API token"
                                        />
                                        <InputError
                                            message={cloudForm.errors.api_token}
                                        />
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
                                            value={
                                                cloudForm.data.organization_name
                                            }
                                            onChange={(e) =>
                                                cloudForm.setData(
                                                    'organization_name',
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="My Organization"
                                        />
                                    </div>

                                    <Button
                                        type="submit"
                                        size="sm"
                                        disabled={cloudForm.processing}
                                    >
                                        Save token
                                    </Button>
                                </form>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
