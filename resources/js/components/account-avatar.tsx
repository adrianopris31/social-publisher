import { PlatformIcon } from '@/components/platform-icon';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useInitials } from '@/hooks/use-initials';
import type { Platform } from '@/types';

/** A social account's picture with its platform's icon on the corner. */
export function AccountAvatar({
    platform,
    name,
    avatarUrl,
}: {
    platform: Platform;
    name: string;
    avatarUrl?: string | null;
}) {
    const getInitials = useInitials();

    return (
        <div className="relative shrink-0">
            <Avatar className="size-8">
                {avatarUrl && <AvatarImage src={avatarUrl} alt={name} />}
                <AvatarFallback>{getInitials(name)}</AvatarFallback>
            </Avatar>
            <PlatformIcon
                platform={platform}
                className="absolute -right-1.5 -bottom-1.5 ring-2 ring-background"
            />
        </div>
    );
}
