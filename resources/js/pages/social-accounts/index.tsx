import { Form, Head } from '@inertiajs/react';
import { Facebook, Instagram, Music2, Plus, Youtube } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import SocialAccountController from '@/actions/App/Http/Controllers/SocialAccountController';
import Heading from '@/components/heading';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { index as socialAccountsIndex } from '@/routes/social-accounts';
import { redirect as tiktokRedirect } from '@/routes/tiktok';
import { redirect as youtubeRedirect } from '@/routes/youtube';

type Platform = 'youtube' | 'instagram' | 'facebook' | 'tiktok';

type SocialAccount = {
    id: number;
    platform: Platform;
    display_name: string;
    avatar_url: string | null;
    connected_at: string | null;
};

type PlatformInfo = {
    platform: Platform;
    name: string;
    accountNoun: string;
    description: string;
    icon: LucideIcon;
    /** OAuth start URL; null until the integration exists. */
    connectUrl: string | null;
};

const platforms: PlatformInfo[] = [
    {
        platform: 'youtube',
        name: 'YouTube',
        accountNoun: 'channel',
        description: 'Publish Shorts to your YouTube channels.',
        icon: Youtube,
        connectUrl: youtubeRedirect.url(),
    },
    {
        platform: 'instagram',
        name: 'Instagram',
        accountNoun: 'account',
        description: 'Publish Reels to professional Instagram accounts.',
        icon: Instagram,
        connectUrl: null,
    },
    {
        platform: 'facebook',
        name: 'Facebook',
        accountNoun: 'Page',
        description: 'Publish Reels to your Facebook Pages.',
        icon: Facebook,
        connectUrl: null,
    },
    {
        platform: 'tiktok',
        name: 'TikTok',
        accountNoun: 'account',
        description: 'Send videos to your TikTok inbox or profile.',
        icon: Music2,
        connectUrl: tiktokRedirect.url(),
    },
];

const dateFormatter = new Intl.DateTimeFormat(undefined, {
    dateStyle: 'medium',
});

function initials(name: string): string {
    return name
        .split(/\s+/)
        .slice(0, 2)
        .map((word) => word.charAt(0).toUpperCase())
        .join('');
}

export default function SocialAccountsIndex({
    accounts,
}: {
    accounts: SocialAccount[];
}) {
    return (
        <>
            <Head title="Connected accounts" />

            <div className="mx-auto w-full max-w-3xl p-4 md:p-6">
                <Heading
                    title="Connected accounts"
                    description="Connect the channels and profiles you want to publish to. You can connect several accounts per platform."
                />

                <div className="space-y-4">
                    {platforms.map((info) => (
                        <PlatformCard
                            key={info.platform}
                            info={info}
                            accounts={accounts.filter(
                                (account) => account.platform === info.platform,
                            )}
                        />
                    ))}
                </div>
            </div>
        </>
    );
}

function PlatformCard({
    info,
    accounts,
}: {
    info: PlatformInfo;
    accounts: SocialAccount[];
}) {
    const Icon = info.icon;

    return (
        <Card className="gap-4">
            <CardHeader className="flex flex-row items-start justify-between gap-4">
                <div className="flex items-start gap-3">
                    <div className="flex size-10 shrink-0 items-center justify-center rounded-lg border bg-muted">
                        <Icon className="size-5" />
                    </div>
                    <div className="space-y-1">
                        <CardTitle>{info.name}</CardTitle>
                        <CardDescription>{info.description}</CardDescription>
                    </div>
                </div>

                {info.connectUrl ? (
                    // A plain link, not <Link>: the OAuth flow needs a full page visit to the provider.
                    <Button
                        asChild
                        size="sm"
                        variant={accounts.length > 0 ? 'outline' : 'default'}
                    >
                        <a href={info.connectUrl}>
                            <Plus />
                            {accounts.length > 0
                                ? `Add ${info.accountNoun}`
                                : 'Connect'}
                        </a>
                    </Button>
                ) : (
                    <Badge variant="secondary">Coming soon</Badge>
                )}
            </CardHeader>

            {accounts.length > 0 && (
                <CardContent>
                    <ul className="divide-y rounded-lg border">
                        {accounts.map((account) => (
                            <AccountRow
                                key={account.id}
                                account={account}
                                platformName={info.name}
                            />
                        ))}
                    </ul>
                </CardContent>
            )}
        </Card>
    );
}

function AccountRow({
    account,
    platformName,
}: {
    account: SocialAccount;
    platformName: string;
}) {
    return (
        <li className="flex items-center justify-between gap-4 p-3">
            <div className="flex min-w-0 items-center gap-3">
                <Avatar className="size-9">
                    {account.avatar_url && (
                        <AvatarImage
                            src={account.avatar_url}
                            alt={account.display_name}
                        />
                    )}
                    <AvatarFallback>
                        {initials(account.display_name)}
                    </AvatarFallback>
                </Avatar>
                <div className="min-w-0">
                    <p className="truncate text-sm font-medium">
                        {account.display_name}
                    </p>
                    {account.connected_at && (
                        <p className="text-xs text-muted-foreground">
                            Connected{' '}
                            {dateFormatter.format(
                                new Date(account.connected_at),
                            )}
                        </p>
                    )}
                </div>
            </div>

            <DisconnectDialog account={account} platformName={platformName} />
        </li>
    );
}

function DisconnectDialog({
    account,
    platformName,
}: {
    account: SocialAccount;
    platformName: string;
}) {
    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button variant="ghost" size="sm">
                    Disconnect
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Disconnect {account.display_name}?</DialogTitle>
                <DialogDescription>
                    You won&apos;t be able to publish to this {platformName}{' '}
                    account until you connect it again. Posts already published
                    stay on {platformName}.
                </DialogDescription>

                <Form
                    {...SocialAccountController.destroy.form(account.id)}
                    options={{ preserveScroll: true }}
                >
                    {({ processing }) => (
                        <DialogFooter className="gap-2">
                            <DialogClose asChild>
                                <Button variant="secondary">Cancel</Button>
                            </DialogClose>
                            <Button
                                type="submit"
                                variant="destructive"
                                disabled={processing}
                            >
                                Disconnect
                            </Button>
                        </DialogFooter>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

SocialAccountsIndex.layout = {
    breadcrumbs: [
        {
            title: 'Connected accounts',
            href: socialAccountsIndex(),
        },
    ],
};
