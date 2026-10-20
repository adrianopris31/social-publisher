export type Platform =
    | 'youtube'
    | 'instagram'
    | 'facebook'
    | 'tiktok'
    | 'threads'
    | 'linkedin';

export type PublicationStatus =
    | 'pending'
    | 'uploading'
    | 'processing'
    | 'published'
    | 'failed';

export type VideoStatus = 'pending' | 'ready' | 'rejected';

export type Publication = {
    id: number;
    platform: Platform;
    account: {
        id: number;
        display_name: string;
        avatar_url: string | null;
    } | null;
    title: string | null;
    caption: string | null;
    privacy: string | null;
    status: PublicationStatus;
    /** A TikTok upload the creator finishes in the TikTok app. */
    is_tiktok_inbox: boolean;
    external_url: string | null;
    error_message: string | null;
    published_at: string | null;
};

export type Post = {
    id: number;
    caption: string | null;
    publish_at: string | null;
    dispatched_at: string | null;
    created_at: string | null;
    video: {
        id: number;
        original_filename: string;
        duration_ms: number | null;
    };
    publications: Publication[];
};

export type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

/** Shape of an Eloquent API resource collection built from a paginator. */
export type ResourcePage<T> = {
    data: T[];
    links: {
        first: string | null;
        last: string | null;
        prev: string | null;
        next: string | null;
    };
    meta: {
        current_page: number;
        last_page: number;
        total: number;
        links: PaginationLink[];
    };
};

/** Shape of a LengthAwarePaginator serialized directly. */
export type Paginator<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};
