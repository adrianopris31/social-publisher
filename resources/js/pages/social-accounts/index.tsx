import { Form, Head } from '@inertiajs/react';
import {
    AlertTriangle,
    AtSign,
    ChevronDown,
    Facebook,
    Instagram,
    Linkedin,
    Music2,
    Plus,
    Youtube,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import SocialAccountController from '@/actions/App/Http/Controllers/SocialAccountController';
import Heading from '@/components/heading';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
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
import { redirect as linkedinRedirect } from '@/routes/linkedin';
import { redirect as metaRedirect } from '@/routes/meta';
import { index as socialAccountsIndex } from '@/routes/social-accounts';
import { redirect as threadsRedirect } from '@/routes/threads';
import { redirect as tiktokRedirect } from '@/routes/tiktok';
import { redirect as youtubeRedirect } from '@/routes/youtube';

import type { Platform } from '@/types';

type SocialAccount = {
    id: number;
    platform: Platform;
    display_name: string;
    avatar_url: string | null;
    needs_reconnection: boolean;
    connected_at: string | null;
};

type PlatformInfo = {
    platform: Platform;
    name: string;
    accountNoun: string;
    description: string;
    icon: LucideIcon;
    /** What the user needs on the platform's side before connecting. */
    requirements: string[];
    /** OAuth start URL; null until the integration exists. */
    connectUrl: string | null;
    /** Replaces the description and requirements when direct posting is enabled. */
    directPost?: Pick<PlatformInfo, 'description' | 'requirements'>;
};

const platforms: PlatformInfo[] = [
    {
        platform: 'youtube',
        name: 'YouTube',
        accountNoun: 'channel',
        description: 'Publish Shorts to your YouTube channels.',
        icon: Youtube,
        requirements: [
            "A YouTube channel on the Google account you sign in with. Create one at youtube.com if you haven't yet.",
            'If you manage several channels (Brand Accounts), pick the right one when Google asks.',
            'Vertical videos up to 3 minutes are published as Shorts.',
        ],
        connectUrl: youtubeRedirect.url(),
    },
    {
        platform: 'instagram',
        name: 'Instagram',
        accountNoun: 'account',
        description:
            'Publish Reels to professional Instagram accounts linked to a Facebook Page. Connecting goes through Facebook Login.',
        icon: Instagram,
        requirements: [
            "A professional Instagram account (Creator or Business). Personal accounts can't publish through the API. Switch in Instagram: Settings → Account type and tools → Switch to professional account.",
            'The Instagram account must be linked to a Facebook Page you manage. In Facebook, open the Page → Settings → Linked accounts → Instagram → Connect. Linking Instagram to your personal Facebook profile (Accounts Center) is not enough.',
            'In the Facebook window, choose Edit access and select both the Page and the Instagram account.',
        ],
        connectUrl: metaRedirect.url(),
    },
    {
        platform: 'facebook',
        name: 'Facebook',
        accountNoun: 'Page',
        description:
            'Publish Reels to your Facebook Pages. Connecting also adds the Instagram accounts linked to them.',
        icon: Facebook,
        requirements: [
            "A Facebook Page where you have full control. Personal profiles can't publish through the API.",
            'In the Facebook window, choose Edit access and select every Page (and linked Instagram account) you want to publish to.',
            'Facebook Reels need vertical 9:16 videos, at least 540×960, 3–90 seconds long.',
        ],
        connectUrl: metaRedirect.url(),
    },
    {
        platform: 'tiktok',
        name: 'TikTok',
        accountNoun: 'account',
        description:
            'Send videos to your TikTok inbox to finish posting in the app.',
        icon: Music2,
        requirements: [
            'Sign in with the TikTok account you want to post to.',
            'Videos arrive in your TikTok inbox: open the notification in the TikTok app to edit and post them.',
        ],
        directPost: {
            description:
                'Post videos straight to your TikTok profile, or send them to your inbox to finish in the app.',
            requirements: [
                'Sign in with the TikTok account you want to post to, and allow posting when TikTok asks.',
                'Pick "Post directly" in the composer to set the caption and who can view the video here.',
                'Pick "Send to inbox" to edit and post the video from the TikTok app instead.',
            ],
        },
        connectUrl: tiktokRedirect.url(),
    },
    {
        platform: 'threads',
        name: 'Threads',
        accountNoun: 'profile',
        description: 'Publish video posts to your Threads profiles.',
        icon: AtSign,
        requirements: [
            'A Threads profile. Threads profiles are created from an Instagram account in the Threads app.',
            'Sign in with the Instagram account behind your Threads profile.',
        ],
        connectUrl: threadsRedirect.url(),
    },
    {
        platform: 'linkedin',
        name: 'LinkedIn',
        accountNoun: 'profile',
        description:
            'Share videos on your LinkedIn profile. LinkedIn asks you to reconnect every 60 days.',
        icon: Linkedin,
        requirements: [
            'Posts go to your personal LinkedIn profile. Company Pages are not supported yet.',
            "LinkedIn doesn't allow long-lived access: reconnect every 60 days when prompted.",
        ],
        connectUrl: linkedinRedirect.url(),
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
    tiktokDirectPost,
}: {
    accounts: SocialAccount[];
    /** Whether the TikTok app may post directly to profiles. */
    tiktokDirectPost: boolean;
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
                            info={
                                info.platform === 'tiktok' &&
                                tiktokDirectPost &&
                                info.directPost
                                    ? { ...info, ...info.directPost }
                                    : info
                            }
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

            <CardContent className="space-y-4">
                <Requirements
                    items={info.requirements}
                    defaultOpen={accounts.length === 0}
                />

                {accounts.length > 0 && (
                    <ul className="divide-y rounded-lg border">
                        {accounts.map((account) => (
                            <AccountRow
                                key={account.id}
                                account={account}
                                platformName={info.name}
                                connectUrl={info.connectUrl}
                            />
                        ))}
                    </ul>
                )}
            </CardContent>
        </Card>
    );
}

function Requirements({
    items,
    defaultOpen,
}: {
    items: string[];
    defaultOpen: boolean;
}) {
    return (
        <Collapsible defaultOpen={defaultOpen}>
            <CollapsibleTrigger className="group flex items-center gap-1 text-sm font-medium text-muted-foreground hover:text-foreground">
                <ChevronDown className="size-4 transition-transform group-data-[state=closed]:-rotate-90" />
                Before you connect
            </CollapsibleTrigger>
            <CollapsibleContent>
                <ol className="mt-2 list-decimal space-y-1.5 pl-9 text-sm text-muted-foreground">
                    {items.map((item) => (
                        <li key={item}>{item}</li>
                    ))}
                </ol>
            </CollapsibleContent>
        </Collapsible>
    );
}

function AccountRow({
    account,
    platformName,
    connectUrl,
}: {
    account: SocialAccount;
    platformName: string;
    connectUrl: string | null;
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
                    {account.needs_reconnection && (
                        <p className="flex items-center gap-1 text-xs text-amber-600 dark:text-amber-400">
                            <AlertTriangle className="size-3.5" />
                            Access expired or was revoked. Reconnect to keep
                            publishing.
                        </p>
                    )}
                    {!account.needs_reconnection && account.connected_at && (
                        <p className="text-xs text-muted-foreground">
                            Connected{' '}
                            {dateFormatter.format(
                                new Date(account.connected_at),
                            )}
                        </p>
                    )}
                </div>
            </div>

            <div className="flex shrink-0 items-center gap-1">
                {account.needs_reconnection && connectUrl && (
                    <Button asChild size="sm">
                        <a href={connectUrl}>Reconnect</a>
                    </Button>
                )}
                <DisconnectDialog
                    account={account}
                    platformName={platformName}
                />
            </div>
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
