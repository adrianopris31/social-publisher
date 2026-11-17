import { inFlightStatuses } from '@/lib/platforms';
import type { Post } from '@/types';

export type CalendarDay = {
    /** Local date as "YYYY-MM-DD": unique per cell and used to match posts to days. */
    key: string;
    date: Date;
    isCurrentMonth: boolean;
    isToday: boolean;
};

/** Monday-first weekday labels, matching the Mon–Sun range the controller queries. */
export const weekdayLabels = [
    'Mon',
    'Tue',
    'Wed',
    'Thu',
    'Fri',
    'Sat',
    'Sun',
] as const;

/**
 * "2026-11" → [2026, 10]. Month indexes start at 0 (January), like `Date` expects.
 */
function parseMonth(month: string): [year: number, monthIndex: number] {
    const [year, monthNumber] = month.split('-').map(Number);

    return [year, monthNumber - 1];
}

/**
 * A local date as "YYYY-MM-DD".
 *
 * Not `toISOString()`: that converts to UTC first and can shift the day by one.
 */
export function toDateKey(date: Date): string {
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${date.getFullYear()}-${month}-${day}`;
}

/**
 * "2026-11" → "November 2026".
 *
 * Built from numbers, not `new Date('2026-11')`: that string is read as UTC midnight and can
 * land on the previous month in timezones west of UTC.
 */
export function formatMonth(month: string): string {
    const [year, monthIndex] = parseMonth(month);

    return new Date(year, monthIndex, 1).toLocaleDateString(undefined, {
        month: 'long',
        year: 'numeric',
    });
}

/**
 * Every day the grid shows for a month: whole Monday–Sunday weeks, so the first and last
 * weeks include days of the neighbouring months.
 */
export function getCalendarDays(month: string): CalendarDay[] {
    const [year, monthIndex] = parseMonth(month);

    // getDay() counts from Sunday (0); this counts the days since Monday instead.
    const daysBeforeMonth = (new Date(year, monthIndex, 1).getDay() + 6) % 7;
    // Day 0 of the next month is the last day of this one.
    const daysInMonth = new Date(year, monthIndex + 1, 0).getDate();
    const cellCount = Math.ceil((daysBeforeMonth + daysInMonth) / 7) * 7;
    const todayKey = toDateKey(new Date());

    return Array.from({ length: cellCount }, (_, index) => {
        // A fresh Date per cell; days outside the month roll over into the neighbouring ones.
        const date = new Date(year, monthIndex, 1 - daysBeforeMonth + index);
        const key = toDateKey(date);

        return {
            key,
            date,
            isCurrentMonth: date.getMonth() === monthIndex,
            isToday: key === todayKey,
        };
    });
}

/**
 * "2026-11" shifted by whole months: -1 → "2026-10", +2 → "2027-01".
 */
export function shiftMonth(month: string, months: number): string {
    const [year, monthIndex] = parseMonth(month);

    return toDateKey(new Date(year, monthIndex + months, 1)).slice(0, 7);
}

/**
 * A scheduled post waits for its publish time and can still be edited or moved.
 */
export function isScheduledPost(post: Post): boolean {
    return post.dispatched_at === null && post.publish_at !== null;
}

/**
 * The moment that places a post on the calendar: its publish time when scheduled, else its creation.
 */
export function postCalendarDate(post: Post): Date {
    return new Date(post.publish_at ?? post.created_at ?? Date.now());
}

/**
 * Posts keyed by the local day they belong to, keeping the server's order inside each day.
 */
export function groupPostsByDay(posts: Post[]): Record<string, Post[]> {
    const postsByDay: Record<string, Post[]> = {};

    for (const post of posts) {
        const key = toDateKey(postCalendarDate(post));

        (postsByDay[key] ??= []).push(post);
    }

    return postsByDay;
}

export type PostCalendarStatus =
    | 'scheduled'
    | 'publishing'
    | 'published'
    | 'failed';

/**
 * One status for the whole post: a single failed platform marks it failed.
 */
export function postCalendarStatus(post: Post): PostCalendarStatus {
    if (isScheduledPost(post)) {
        return 'scheduled';
    }

    const statuses = post.publications.map((publication) => publication.status);

    if (statuses.includes('failed')) {
        return 'failed';
    }

    return statuses.some((status) => inFlightStatuses.includes(status))
        ? 'publishing'
        : 'published';
}

/**
 * A local time as "HH:MM", the value format of `<input type="time">`.
 */
export function toTimeValue(date: Date): string {
    const hours = String(date.getHours()).padStart(2, '0');
    const minutes = String(date.getMinutes()).padStart(2, '0');

    return `${hours}:${minutes}`;
}

/**
 * "2026-11-20" + "14:30" → that local moment, or null while either input is incomplete.
 */
export function atLocalTime(dayKey: string, time: string): Date | null {
    const [year, monthNumber, day] = dayKey.split('-').map(Number);
    const [hours, minutes] = time.split(':').map(Number);

    if ([year, monthNumber, day, hours, minutes].some(Number.isNaN)) {
        return null;
    }

    return new Date(year, monthNumber - 1, day, hours, minutes);
}

/**
 * The same local time of day on another day.
 */
export function moveToDay(isoDateTime: string, dayKey: string): Date {
    const original = new Date(isoDateTime);
    const [year, monthNumber, day] = dayKey.split('-').map(Number);

    return new Date(
        year,
        monthNumber - 1,
        day,
        original.getHours(),
        original.getMinutes(),
    );
}
